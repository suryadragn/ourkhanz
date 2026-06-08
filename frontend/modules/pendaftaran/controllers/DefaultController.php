<?php

namespace frontend\modules\pendaftaran\controllers;

use DateTimeImmutable;
use Yii;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\Json;
use yii\data\Pagination;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\Response;

class DefaultController extends Controller
{
    public function actionIndex(): string|Response
    {
        $db = Yii::$app->db;

        $tanggalPeriksa = Yii::$app->request->get('tanggal_periksa', date('Y-m-d'));
        [$tanggalPeriksa, $hariKerja] = $this->resolveTanggalDanHari($tanggalPeriksa);
        $recentPage = max(0, (int) Yii::$app->request->get('recent_page', 0));
        $recentSearch = trim((string) Yii::$app->request->get('recent_search', ''));
        $recentPageSize = 8;

        $bookingMessage = null;
        $bookingError = null;

        if (Yii::$app->request->isPost) {
            $noRkmMedis = trim((string) Yii::$app->request->post('no_rkm_medis', ''));
            $tanggalInput = trim((string) Yii::$app->request->post('tanggal_periksa', $tanggalPeriksa));
            $scheduleKey = trim((string) Yii::$app->request->post('schedule_key', ''));

            $postDate = DateTimeImmutable::createFromFormat('Y-m-d', $tanggalInput);
            if ($noRkmMedis === '' || $scheduleKey === '' || $postDate === false) {
                $bookingError = 'Lengkapi nomor RM, tanggal periksa, dan jadwal dokter.';
            } else {
                [$kdDokter, $hariKey, $jamMulai] = array_pad(explode('|', $scheduleKey, 3), 3, null);

                if ($kdDokter === null || $hariKey === null || $jamMulai === null) {
                    $bookingError = 'Jadwal dokter yang dipilih tidak valid.';
                } else {
                    $transaction = $db->beginTransaction();

                    try {
                        $schedule = (new Query())
                            ->from(['j' => 'jadwal'])
                            ->select([
                                'j.kd_dokter',
                                'j.hari_kerja',
                                'j.jam_mulai',
                                'j.jam_selesai',
                                'j.kd_poli',
                                'j.kuota',
                                'd.nm_dokter',
                                'p.nm_poli',
                            ])
                            ->leftJoin(['d' => 'dokter'], 'd.kd_dokter = j.kd_dokter')
                            ->leftJoin(['p' => 'poliklinik'], 'p.kd_poli = j.kd_poli')
                            ->where([
                                'j.kd_dokter' => $kdDokter,
                                'j.hari_kerja' => $hariKey,
                                'j.jam_mulai' => $jamMulai,
                            ])
                            ->andWhere(['>', 'j.kuota', 0])
                            ->one($db);

                        if ($schedule === null) {
                            throw new BadRequestHttpException('Kuota jadwal sudah habis atau jadwal tidak ditemukan.');
                        }

                        $quotaUpdated = $db->createCommand()
                            ->update(
                                'jadwal',
                                ['kuota' => new Expression('kuota - 1')],
                                [
                                    'and',
                                    ['kd_dokter' => $kdDokter],
                                    ['hari_kerja' => $hariKey],
                                    ['jam_mulai' => $jamMulai],
                                    ['>', 'kuota', 0],
                                ]
                            )
                            ->execute();

                        if ($quotaUpdated === 0) {
                            throw new BadRequestHttpException('Kuota jadwal sudah habis.');
                        }

                        $db->createCommand()->insert('booking_registrasi', [
                            'tanggal_booking' => date('Y-m-d'),
                            'jam_booking' => date('H:i:s'),
                            'no_rkm_medis' => $noRkmMedis,
                            'tanggal_periksa' => $tanggalInput,
                            'kd_dokter' => $kdDokter,
                            'kd_poli' => $schedule['kd_poli'],
                            'no_reg' => null,
                            'kd_pj' => 'A09',
                            'limit_reg' => 0,
                            'waktu_kunjungan' => date('Y-m-d H:i:s'),
                            'status' => 'Belum',
                        ])->execute();

                        $transaction->commit();

                        $bookingMessage = sprintf(
                            'Pendaftaran berhasil untuk %s pada %s jam %s. Kuota dokter sudah berkurang 1 slot.',
                            $schedule['nm_dokter'] ?? $kdDokter,
                            $postDate->format('d-m-Y'),
                            substr((string) $schedule['jam_mulai'], 0, 5)
                        );

                        Yii::$app->session->setFlash('success', $bookingMessage);
                        return $this->redirect(['index', 'tanggal_periksa' => $tanggalInput]);
                    } catch (\Throwable $throwable) {
                        if ($transaction->isActive) {
                            $transaction->rollBack();
                        }

                        $bookingError = $throwable instanceof BadRequestHttpException ? $throwable->getMessage() : 'Gagal menyimpan pendaftaran.';
                        Yii::$app->session->setFlash('error', $bookingError);
                    }
                }
            }
        }

        $availableSchedules = $this->loadAvailableSchedules($hariKerja, $tanggalPeriksa);
        [$recentBookings, $recentPagination] = $this->loadRecentRegistrations($tanggalPeriksa, $recentPage, $recentPageSize, $recentSearch);

        return $this->render('index', [
            'tanggalPeriksa' => $tanggalPeriksa,
            'hariKerja' => $hariKerja,
            'availableSchedules' => $availableSchedules,
            'recentBookings' => $recentBookings,
            'recentPagination' => $recentPagination,
            'recentSearch' => $recentSearch,
            'bookingMessage' => $bookingMessage,
            'bookingError' => $bookingError,
        ]);
    }

    public function actionSchedules(string $tanggal_periksa, int $recent_page = 0, string $recent_search = ''): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        [$tanggalPeriksa, $hariKerja] = $this->resolveTanggalDanHari($tanggal_periksa);
        $schedules = $this->loadAvailableSchedules($hariKerja, $tanggalPeriksa);
        [$recentRegistrations, $recentPagination] = $this->loadRecentRegistrations($tanggalPeriksa, max(0, $recent_page), 8, trim($recent_search));

        return [
            'success' => true,
            'tanggal_periksa' => $tanggalPeriksa,
            'hari_kerja' => $hariKerja,
            'hari_label' => $this->hariLabel($hariKerja),
            'schedules' => array_map(static function (array $schedule): array {
                return [
                    'kd_dokter' => $schedule['kd_dokter'],
                    'hari_kerja' => $schedule['hari_kerja'],
                    'jam_mulai' => $schedule['jam_mulai'],
                    'jam_selesai' => $schedule['jam_selesai'],
                    'kd_poli' => $schedule['kd_poli'],
                    'kuota' => $schedule['kuota'],
                    'kuota_tersisa' => $schedule['kuota_tersisa'],
                    'jumlah_pendaftar' => $schedule['jumlah_pendaftar'],
                    'nm_dokter' => $schedule['nm_dokter'],
                    'no_ijn_praktek' => $schedule['no_ijn_praktek'],
                    'nm_poli' => $schedule['nm_poli'],
                ];
            }, $schedules),
            'recent_registrations' => array_map(static function (array $row): array {
                $penangananStatus = 'Belum Ditangani';
                $penangananClass = 'warning';

                if (in_array($row['stts_periksa'] ?? null, ['Sudah', 'Sudah Anamnesa'], true)) {
                    $penangananStatus = 'Sudah Ditangani';
                    $penangananClass = 'success';
                } elseif (($row['stts_periksa'] ?? null) === 'Batal') {
                    $penangananStatus = 'Dibatalkan';
                    $penangananClass = 'secondary';
                }

                return [
                    'no_rawat' => $row['no_rawat'],
                    'tglDaftar' => $row['tglDaftar'],
                    'no_rkm_medis' => $row['no_rkm_medis'],
                    'nm_pasien' => $row['nm_pasien'],
                    'kd_dokter' => $row['kd_dokter'] ?? null,
                    'nm_dokter' => $row['nm_dokter'] ?? '-',
                    'jam_registrasi' => $row['jam_registrasi'] ?? '-',
                    'jam_booking' => $row['jam_booking'] ?? '-',
                    'kdPoli' => $row['kdPoli'],
                    'nmPoli' => $row['nmPoli'],
                    'noUrut' => $row['noUrut'],
                    'status' => $row['status'],
                    'stts_periksa' => $row['stts_periksa'] ?? null,
                    'status_penanganan' => $penangananStatus,
                    'status_penanganan_class' => $penangananClass,
                ];
            }, $recentRegistrations),
            'recent_pagination' => [
                'page' => (int) $recentPagination->getPage(),
                'pageCount' => (int) $recentPagination->getPageCount(),
                'pageSize' => (int) $recentPagination->getPageSize(),
                'totalCount' => (int) $recentPagination->totalCount,
            ],
        ];
    }

    public function actionPatients(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $request = Yii::$app->request;
        $draw = (int) $request->get('draw', 1);
        $start = max(0, (int) $request->get('start', 0));
        $length = (int) $request->get('length', 10);
        $length = $length > 0 ? min($length, 25) : 10;
        $searchParam = $request->get('search', []);
        $searchValue = '';

        if (is_array($searchParam)) {
            $searchValue = trim((string) ($searchParam['value'] ?? ''));
        } else {
            $searchValue = trim((string) $searchParam);
        }

        if ($searchValue === '') {
            $searchValue = trim((string) $request->get('search[value]', ''));
        }

        $db = Yii::$app->db;
        $totalRecords = (int) (new Query())->from('pasien')->count('*', $db);

        $query = (new Query())
            ->from(['p' => 'pasien'])
            ->select([
                'p.no_rkm_medis',
                'p.nm_pasien',
                'p.no_ktp',
                'p.jk',
                'p.tgl_lahir',
                'p.no_tlp',
                'p.alamat',
                'p.pekerjaan',
                'p.keluarga',
                'p.namakeluarga',
            ]);

        if ($searchValue !== '') {
            $query->andWhere([
                'or',
                ['like', 'p.no_rkm_medis', $searchValue],
                ['like', 'p.nm_pasien', $searchValue],
                ['like', 'p.no_ktp', $searchValue],
                ['like', 'p.no_tlp', $searchValue],
                ['like', 'p.alamat', $searchValue],
            ]);
        }

        $filteredQuery = clone $query;
        $recordsFiltered = (int) $filteredQuery->count('*', $db);

        $rows = $query
            ->orderBy(new Expression('p.nm_pasien, p.no_rkm_medis'))
            ->offset($start)
            ->limit($length)
            ->all($db);

        $data = array_map(static function (array $row): array {
            return [
                'no_rkm_medis' => $row['no_rkm_medis'],
                'nm_pasien' => $row['nm_pasien'],
                'no_ktp' => $row['no_ktp'],
                'jk' => $row['jk'],
                'tgl_lahir' => $row['tgl_lahir'],
                'no_tlp' => $row['no_tlp'],
                'alamat' => $row['alamat'],
                'display_name' => trim(($row['no_rkm_medis'] ?? '') . ' - ' . ($row['nm_pasien'] ?? '')),
            ];
        }, $rows);

        return [
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
    }

    private function resolveTanggalDanHari(string $tanggalPeriksa): array
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

        $tanggalObject = DateTimeImmutable::createFromFormat('Y-m-d', $tanggalPeriksa) ?: new DateTimeImmutable('today');

        return [
            $tanggalObject->format('Y-m-d'),
            $hariLabels[(int) $tanggalObject->format('N')] ?? 'SENIN',
        ];
    }

    private function hariLabel(string $hariKerja): string
    {
        $labels = [
            'SENIN' => 'Senin',
            'SELASA' => 'Selasa',
            'RABU' => 'Rabu',
            'KAMIS' => 'Kamis',
            'JUMAT' => 'Jumat',
            'SABTU' => 'Sabtu',
            'AKHAD' => 'Ahad',
        ];

        return $labels[$hariKerja] ?? $hariKerja;
    }

    private function loadAvailableSchedules(string $hariKerja, string $tanggalPeriksa): array
    {
        $activeBookings = (new Query())
            ->from(['br' => 'booking_registrasi'])
            ->select([
                'br.kd_dokter',
                'br.kd_poli',
                'COUNT(*) AS jumlah_pendaftar',
            ])
            ->where(['br.tanggal_periksa' => $tanggalPeriksa])
            ->andWhere(['not in', 'br.status', ['Batal', 'Dokter Berhalangan']])
            ->groupBy(['br.kd_dokter', 'br.kd_poli']);

        $rows = (new Query())
            ->from(['j' => 'jadwal'])
            ->select([
                'j.kd_dokter',
                'j.hari_kerja',
                'j.jam_mulai',
                'j.jam_selesai',
                'j.kd_poli',
                'j.kuota',
                'COALESCE(b.jumlah_pendaftar, 0) AS jumlah_pendaftar',
                'GREATEST(j.kuota - COALESCE(b.jumlah_pendaftar, 0), 0) AS kuota_tersisa',
                'd.nm_dokter',
                'd.no_ijn_praktek',
                'p.nm_poli',
            ])
            ->leftJoin(['d' => 'dokter'], 'd.kd_dokter = j.kd_dokter')
            ->leftJoin(['p' => 'poliklinik'], 'p.kd_poli = j.kd_poli')
            ->leftJoin(['b' => $activeBookings], 'b.kd_dokter = j.kd_dokter AND b.kd_poli = j.kd_poli')
            ->where(['j.hari_kerja' => $hariKerja])
            ->andWhere(['>', 'j.kuota', 0])
            ->orderBy(new Expression('j.jam_mulai, d.nm_dokter'))
            ->all(Yii::$app->db);

        return array_values(array_filter($rows, static function (array $schedule): bool {
            return (int) ($schedule['kuota_tersisa'] ?? 0) > 0;
        }));
    }

    private function loadRecentRegistrations(string $tanggalPeriksa, int $page, int $pageSize, string $search = ''): array
    {
        $query = (new Query())
            ->from(['b' => 'pcare_pendaftaran'])
            ->select([
                'b.no_rawat',
                'b.tglDaftar',
                'b.no_rkm_medis',
                'b.nm_pasien',
                'r.kd_dokter',
                'd.nm_dokter',
                'r.jam_reg AS jam_registrasi',
                'bk.jam_booking',
                'b.kdPoli',
                'b.nmPoli',
                'b.noUrut',
                'b.status',
                'r.stts AS stts_periksa',
            ])
            ->leftJoin(['r' => 'reg_periksa'], 'r.no_rawat = b.no_rawat')
            ->leftJoin(['d' => 'dokter'], 'd.kd_dokter = r.kd_dokter')
            ->leftJoin(['bk' => 'booking_registrasi'], 'bk.no_rkm_medis = b.no_rkm_medis AND bk.tanggal_periksa = b.tglDaftar')
            ->where(['b.tglDaftar' => $tanggalPeriksa])
            ->orderBy(new Expression('b.no_rawat DESC'));

        if ($search !== '') {
            $query->andWhere([
                'or',
                ['like', 'b.no_rawat', $search],
                ['like', 'b.no_rkm_medis', $search],
                ['like', 'b.nm_pasien', $search],
                ['like', 'b.noUrut', $search],
                ['like', 'd.nm_dokter', $search],
                ['like', 'r.jam_reg', $search],
                ['like', 'bk.jam_booking', $search],
                ['like', 'b.status', $search],
                ['like', 'r.stts', $search],
            ]);
        }

        $countQuery = clone $query;
        $pagination = new Pagination([
            'totalCount' => (int) $countQuery->count('*', Yii::$app->db),
            'pageSize' => $pageSize,
            'page' => $page,
        ]);

        $rows = $query
            ->offset($pagination->offset)
            ->limit($pagination->limit)
            ->all(Yii::$app->db);

        return [$rows, $pagination];
    }
}
