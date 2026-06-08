<?php

namespace frontend\modules\pendaftaran\services;

use DateTimeImmutable;
use Yii;
use yii\db\Expression;
use yii\db\Query;
use yii\web\BadRequestHttpException;

/**
 * RegistrationService
 * 
 * Handles complete patient registration workflow in SIMRS-Khanza
 * 
 * Three-phase workflow:
 * 1. BOOKING: Patient books appointment online
 * 2. REGISTRATION: Patient arrives, creates registration record
 * 3. EXAMINATION: Doctor examines patient
 */
class RegistrationService
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Yii::$app->db;
    }

    /**
     * PHASE 1: Create Booking Record
     * 
     * When patient selects appointment online
     * - Validates quota available
     * - Decrements quota atomically
     * - Creates booking_registrasi record
     * 
     * @param string $noRkmMedis Patient medical record number
     * @param string $tanggalPeriksa Appointment date (YYYY-MM-DD)
     * @param string $kdDokter Doctor code
     * @param string $kdPoli Clinic code
     * @return array Booking data with success message
     * @throws BadRequestHttpException on validation or quota error
     */
    public function createBooking(string $noRkmMedis, string $tanggalPeriksa, string $kdDokter, string $kdPoli): array
    {
        $transaction = $this->db->beginTransaction();

        try {
            // Step 1: Validate patient exists
            $patient = (new Query())
                ->from('pasien')
                ->where(['no_rkm_medis' => $noRkmMedis])
                ->one($this->db);

            if (!$patient) {
                throw new BadRequestHttpException("Pasien dengan nomor RM '{$noRkmMedis}' tidak ditemukan.");
            }

            // Step 2: Check schedule exists and quota available
            $schedule = (new Query())
                ->from(['j' => 'jadwal'])
                ->select(['j.*', 'd.nm_dokter', 'p.nm_poli'])
                ->leftJoin(['d' => 'dokter'], 'd.kd_dokter = j.kd_dokter')
                ->leftJoin(['p' => 'poliklinik'], 'p.kd_poli = j.kd_poli')
                ->where([
                    'j.kd_dokter' => $kdDokter,
                    'j.kd_poli' => $kdPoli,
                    'j.hari_kerja' => $this->getDayOfWeek($tanggalPeriksa),
                ])
                ->andWhere(['>', 'j.kuota', 0])
                ->one($this->db);

            if (!$schedule) {
                throw new BadRequestHttpException('Jadwal dokter tidak ditemukan atau kuota sudah habis.');
            }

            // Step 3: Decrement quota (ATOMIC - race condition guard)
            $quotaUpdated = $this->db->createCommand()
                ->update(
                    'jadwal',
                    ['kuota' => new Expression('kuota - 1')],
                    [
                        'and',
                        ['kd_dokter' => $kdDokter],
                        ['kd_poli' => $kdPoli],
                        ['hari_kerja' => $this->getDayOfWeek($tanggalPeriksa)],
                        ['>', 'kuota', 0], // ⭐ CRITICAL: Guard against race condition
                    ]
                )
                ->execute();

            if ($quotaUpdated === 0) {
                throw new BadRequestHttpException('Kuota jadwal sudah habis. Silakan pilih jadwal lain.');
            }

            // Step 4: Create booking record
            $this->db->createCommand()->insert('booking_registrasi', [
                'tanggal_booking' => date('Y-m-d'),
                'jam_booking' => date('H:i:s'),
                'no_rkm_medis' => $noRkmMedis,
                'tanggal_periksa' => $tanggalPeriksa,
                'kd_dokter' => $kdDokter,
                'kd_poli' => $kdPoli,
                'no_reg' => null, // Will be assigned during registration
                'kd_pj' => 'A09', // Default payment method
                'limit_reg' => 0,
                'waktu_kunjungan' => date('Y-m-d H:i:s', strtotime($tanggalPeriksa)),
                'status' => 'Belum', // Pending registration
            ])->execute();

            $transaction->commit();

            return [
                'success' => true,
                'message' => sprintf(
                    'Booking berhasil untuk %s pada %s jam %s',
                    $schedule['nm_dokter'] ?? $kdDokter,
                    (new DateTimeImmutable($tanggalPeriksa))->format('d-m-Y'),
                    substr($schedule['jam_mulai'] ?? '', 0, 5)
                ),
                'booking_data' => [
                    'no_rkm_medis' => $noRkmMedis,
                    'nm_pasien' => $patient['nm_pasien'],
                    'tanggal_periksa' => $tanggalPeriksa,
                    'kd_dokter' => $kdDokter,
                    'nm_dokter' => $schedule['nm_dokter'],
                    'kd_poli' => $kdPoli,
                    'nm_poli' => $schedule['nm_poli'],
                ],
            ];
        } catch (\Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $e;
        }
    }

    /**
     * PHASE 2: Register Patient (Check-in)
     * 
     * When patient arrives at clinic
     * - Creates registration record in pcare_pendaftaran
     * - Queue number assigned by database trigger
     * - Updates booking status to 'Terdaftar'
     * 
     * @param string $noRkmMedis Patient medical record number
     * @param string $tanggalPeriksa Appointment date (YYYY-MM-DD)
     * @return array Registration data with queue number and no_rawat
     * @throws BadRequestHttpException on validation error
     */
    public function registerPatient(string $noRkmMedis, string $tanggalPeriksa): array
    {
        $transaction = $this->db->beginTransaction();

        try {
            // Step 1: Verify booking exists
            $booking = (new Query())
                ->from('booking_registrasi')
                ->where([
                    'no_rkm_medis' => $noRkmMedis,
                    'tanggal_periksa' => $tanggalPeriksa,
                    'status' => 'Belum', // Not yet registered
                ])
                ->one($this->db);

            if (!$booking) {
                throw new BadRequestHttpException('Booking tidak ditemukan atau sudah terdaftar.');
            }

            // Step 2: Get patient data
            $patient = (new Query())
                ->from('pasien')
                ->where(['no_rkm_medis' => $noRkmMedis])
                ->one($this->db);

            if (!$patient) {
                throw new BadRequestHttpException('Data pasien tidak ditemukan.');
            }

            // Step 3: Generate registration number (format: YYYY/MM/DD/NNNNN)
            $noRawat = $this->generateNoRawat($tanggalPeriksa);

            // Step 4: Get clinic info from booking
            $clinic = (new Query())
                ->from('poliklinik')
                ->where(['kd_poli' => $booking['kd_poli']])
                ->one($this->db);

            if (!$clinic) {
                throw new BadRequestHttpException('Data poliklinik tidak ditemukan.');
            }

            // Step 5: Insert registration record
            // NOTE: noUrut will be assigned by database trigger/procedure
            $this->db->createCommand()->insert('pcare_pendaftaran', [
                'no_rawat' => $noRawat,
                'tglDaftar' => $tanggalPeriksa,
                'no_rkm_medis' => $noRkmMedis,
                'nm_pasien' => $patient['nm_pasien'],
                'kdPoli' => $booking['kd_poli'],
                'nmPoli' => $clinic['nm_poli'],
                'noUrut' => '', // Will be auto-assigned by trigger
                'status' => 'Masuk', // Entered/Checked-in
                // Vital signs - can be filled by nurse later
                'sistolik' => null,
                'diastolik' => null,
                'tinggi_badan' => null,
                'berat_badan' => null,
                'suhu_tubuh' => null,
                'tekanan_nadi' => null,
                'pernafasan' => null,
                'gula_darah' => null,
            ])->execute();

            // Step 6: Update booking status to registered
            $this->db->createCommand()
                ->update(
                    'booking_registrasi',
                    [
                        'no_reg' => $noRawat,
                        'status' => 'Terdaftar', // Registered
                    ],
                    [
                        'no_rkm_medis' => $noRkmMedis,
                        'tanggal_periksa' => $tanggalPeriksa,
                    ]
                )
                ->execute();

            // Step 7: Retrieve the newly created registration with queue number
            $registration = (new Query())
                ->from('pcare_pendaftaran')
                ->where(['no_rawat' => $noRawat])
                ->one($this->db);

            $transaction->commit();

            return [
                'success' => true,
                'message' => sprintf('Pendaftaran berhasil. Nomor antrian: %s', $registration['noUrut']),
                'registration_data' => [
                    'no_rawat' => $registration['no_rawat'],
                    'noUrut' => $registration['noUrut'], // Queue number
                    'nm_pasien' => $registration['nm_pasien'],
                    'no_rkm_medis' => $registration['no_rkm_medis'],
                    'nmPoli' => $registration['nmPoli'],
                    'status' => $registration['status'],
                    'tglDaftar' => $registration['tglDaftar'],
                ],
            ];
        } catch (\Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $e;
        }
    }

    /**
     * PHASE 3: Start Examination
     * 
     * When doctor starts examining patient
     * - Creates examination record in reg_periksa
     * - Records examination start time
     * 
     * @param string $noRawat Registration number
     * @param string $kdDokter Doctor code
     * @return array Examination data
     * @throws BadRequestHttpException on validation error
     */
    public function startExamination(string $noRawat, string $kdDokter): array
    {
        $transaction = $this->db->beginTransaction();

        try {
            // Step 1: Verify registration exists
            $registration = (new Query())
                ->from('pcare_pendaftaran')
                ->where(['no_rawat' => $noRawat])
                ->one($this->db);

            if (!$registration) {
                throw new BadRequestHttpException('Nomor rawat tidak ditemukan.');
            }

            // Step 2: Check if examination already started
            $existingExam = (new Query())
                ->from('reg_periksa')
                ->where(['no_rawat' => $noRawat])
                ->one($this->db);

            if ($existingExam && $existingExam['stts'] !== 'Belum') {
                throw new BadRequestHttpException('Pemeriksaan sudah dimulai atau sudah selesai.');
            }

            // Step 3: If first time, create exam record
            if (!$existingExam) {
                $this->db->createCommand()->insert('reg_periksa', [
                    'no_rawat' => $noRawat,
                    'kd_dokter' => $kdDokter,
                    'jam_reg' => date('H:i:s'),
                    'tgl_reg' => date('Y-m-d'),
                    'stts' => 'Belum', // Examination started but not completed
                ])->execute();
            } else {
                // Update existing record with current time
                $this->db->createCommand()
                    ->update(
                        'reg_periksa',
                        [
                            'jam_reg' => date('H:i:s'),
                            'stts' => 'Belum',
                        ],
                        ['no_rawat' => $noRawat]
                    )
                    ->execute();
            }

            $transaction->commit();

            return [
                'success' => true,
                'message' => 'Pemeriksaan dimulai',
                'examination_data' => [
                    'no_rawat' => $noRawat,
                    'kd_dokter' => $kdDokter,
                    'jam_reg' => date('H:i:s'),
                    'status' => 'Belum',
                ],
            ];
        } catch (\Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Complete Examination
     * 
     * When doctor finishes examining patient
     * - Updates examination status
     * - Records examination completion time
     * 
     * @param string $noRawat Registration number
     * @param string $status Completion status ('Sudah', 'Sudah Anamnesa', 'Batal')
     * @return array Updated examination data
     * @throws BadRequestHttpException on validation error
     */
    public function completeExamination(string $noRawat, string $status = 'Sudah'): array
    {
        // Validate status
        $validStatuses = ['Sudah', 'Sudah Anamnesa', 'Batal'];
        if (!in_array($status, $validStatuses)) {
            throw new BadRequestHttpException('Status pemeriksaan tidak valid.');
        }

        // Update exam status
        $this->db->createCommand()
            ->update(
                'reg_periksa',
                ['stts' => $status],
                ['no_rawat' => $noRawat]
            )
            ->execute();

        // Also update pcare_pendaftaran status
        $statusMap = [
            'Sudah' => 'Pulang',
            'Sudah Anamnesa' => 'Masuk',
            'Batal' => 'Batal',
        ];

        $this->db->createCommand()
            ->update(
                'pcare_pendaftaran',
                ['status' => $statusMap[$status]],
                ['no_rawat' => $noRawat]
            )
            ->execute();

        return [
            'success' => true,
            'message' => 'Pemeriksaan selesai',
            'status' => $status,
        ];
    }

    /**
     * Cancel Booking
     * 
     * Cancel an existing booking and restore quota
     * 
     * @param string $noRkmMedis Patient medical record number
     * @param string $tanggalPeriksa Appointment date
     * @return array Cancellation confirmation
     * @throws BadRequestHttpException on validation error
     */
    public function cancelBooking(string $noRkmMedis, string $tanggalPeriksa): array
    {
        $transaction = $this->db->beginTransaction();

        try {
            // Get booking
            $booking = (new Query())
                ->from('booking_registrasi')
                ->where([
                    'no_rkm_medis' => $noRkmMedis,
                    'tanggal_periksa' => $tanggalPeriksa,
                ])
                ->one($this->db);

            if (!$booking) {
                throw new BadRequestHttpException('Booking tidak ditemukan.');
            }

            // Mark as cancelled
            $this->db->createCommand()
                ->update(
                    'booking_registrasi',
                    ['status' => 'Batal'],
                    [
                        'no_rkm_medis' => $noRkmMedis,
                        'tanggal_periksa' => $tanggalPeriksa,
                    ]
                )
                ->execute();

            // Restore quota (only if not yet registered)
            if ($booking['status'] === 'Belum') {
                $this->db->createCommand()
                    ->update(
                        'jadwal',
                        ['kuota' => new Expression('kuota + 1')],
                        [
                            'kd_dokter' => $booking['kd_dokter'],
                            'kd_poli' => $booking['kd_poli'],
                            'hari_kerja' => $this->getDayOfWeek($tanggalPeriksa),
                        ]
                    )
                    ->execute();
            }

            $transaction->commit();

            return [
                'success' => true,
                'message' => 'Booking dibatalkan. Kuota dikembalikan.',
            ];
        } catch (\Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Helper: Generate Registration Number
     * 
     * Format: YYYY/MM/DD/NNNNN
     * Example: 2026/06/09/00001
     * 
     * @param string $tanggalPeriksa Date in format YYYY-MM-DD
     * @return string Generated no_rawat
     */
    private function generateNoRawat(string $tanggalPeriksa): string
    {
        // Parse date
        [$year, $month, $day] = explode('-', $tanggalPeriksa);

        // Get counter for this date
        $lastRegistration = (new Query())
            ->from('pcare_pendaftaran')
            ->where(['tglDaftar' => $tanggalPeriksa])
            ->orderBy(['no_rawat' => SORT_DESC])
            ->one($this->db);

        $counter = 1;
        if ($lastRegistration) {
            // Extract counter from last registration number
            // Format: YYYY/MM/DD/NNNNN -> extract NNNNN
            $parts = explode('/', $lastRegistration['no_rawat']);
            $lastCounter = (int) end($parts);
            $counter = $lastCounter + 1;
        }

        return sprintf('%04d/%02d/%02d/%05d', $year, $month, $day, $counter);
    }

    /**
     * Helper: Get Day of Week
     * 
     * @param string $tanggal Date in format YYYY-MM-DD
     * @return string Day code (SENIN, SELASA, etc)
     */
    private function getDayOfWeek(string $tanggal): string
    {
        $hariLabels = [
            1 => 'SENIN',
            2 => 'SELASA',
            3 => 'RABU',
            4 => 'KAMIS',
            5 => 'JUMAT',
            6 => 'SABTU',
            7 => 'AKHAD',
        ];

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $tanggal);
        return $hariLabels[(int) $date->format('N')] ?? 'SENIN';
    }

    /**
     * Get Registration Status
     * 
     * Get complete status of a registration including queue info
     * 
     * @param string $noRawat Registration number
     * @return array|null Complete registration and examination data
     */
    public function getRegistrationStatus(string $noRawat): ?array
    {
        $registration = (new Query())
            ->from(['p' => 'pcare_pendaftaran'])
            ->select([
                'p.*',
                'r.kd_dokter',
                'r.jam_reg',
                'r.stts as stts_periksa',
                'd.nm_dokter',
            ])
            ->leftJoin(['r' => 'reg_periksa'], 'r.no_rawat = p.no_rawat')
            ->leftJoin(['d' => 'dokter'], 'd.kd_dokter = r.kd_dokter')
            ->where(['p.no_rawat' => $noRawat])
            ->one($this->db);

        if (!$registration) {
            return null;
        }

        // Map examination status to display text
        $penangananStatus = 'Belum Ditangani';
        $penangananClass = 'warning';

        if (in_array($registration['stts_periksa'] ?? null, ['Sudah', 'Sudah Anamnesa'], true)) {
            $penangananStatus = 'Sudah Ditangani';
            $penangananClass = 'success';
        } elseif (($registration['stts_periksa'] ?? null) === 'Batal') {
            $penangananStatus = 'Dibatalkan';
            $penangananClass = 'secondary';
        }

        return [
            'no_rawat' => $registration['no_rawat'],
            'noUrut' => $registration['noUrut'],
            'nm_pasien' => $registration['nm_pasien'],
            'nmPoli' => $registration['nmPoli'],
            'nm_dokter' => $registration['nm_dokter'] ?? '-',
            'jam_reg' => $registration['jam_reg'] ?? '-',
            'tglDaftar' => $registration['tglDaftar'],
            'status_registration' => $registration['status'],
            'status_examination' => $registration['stts_periksa'] ?? 'Belum',
            'status_penanganan' => $penangananStatus,
            'status_penanganan_class' => $penangananClass,
        ];
    }
}
