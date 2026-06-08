<?php

/** @var yii\web\View $this */
/** @var string $tanggalPeriksa */
/** @var string $hariKerja */
/** @var array $availableSchedules */
/** @var array $recentBookings */
/** @var yii\data\Pagination $recentPagination */
/** @var string $recentSearch */
/** @var string|null $bookingMessage */
/** @var string|null $bookingError */

$this->title = 'Pendaftaran Pasien';
$this->params['breadcrumbs'][] = $this->title;

$hariLabels = [
    'SENIN' => 'Senin',
    'SELASA' => 'Selasa',
    'RABU' => 'Rabu',
    'KAMIS' => 'Kamis',
    'JUMAT' => 'Jumat',
    'SABTU' => 'Sabtu',
    'AKHAD' => 'Ahad',
];

$successFlash = Yii::$app->session->getFlash('success');
$errorFlash = Yii::$app->session->getFlash('error');

$this->registerCssFile('https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css', ['depends' => [yii\web\JqueryAsset::class]]);
$this->registerJsFile('https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js', ['depends' => [yii\web\JqueryAsset::class]], 'dataTables-core');
$this->registerJsFile('https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js', ['depends' => [yii\web\JqueryAsset::class]], 'dataTables-bootstrap');
?>
<div class="module-default-index">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="mb-2"><?= yii\helpers\Html::encode($this->title) ?></h1>
            <p class="text-muted mb-0">Pilih tanggal, pilih jadwal dokter aktif, lalu simpan pendaftaran. Kuota slot akan otomatis berkurang.</p>
        </div>
        <div class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
            Hari praktik: <?= yii\helpers\Html::encode($hariLabels[$hariKerja] ?? $hariKerja) ?>
        </div>
    </div>

    <?php if (!empty($successFlash) || !empty($bookingMessage)): ?>
        <div class="alert alert-success border-0 shadow-sm"><?= yii\helpers\Html::encode($successFlash ?: $bookingMessage) ?></div>
    <?php endif; ?>

    <?php if (!empty($errorFlash) || !empty($bookingError)): ?>
        <div class="alert alert-danger border-0 shadow-sm"><?= yii\helpers\Html::encode($errorFlash ?: $bookingError) ?></div>
    <?php endif; ?>

    <div class="row g-4 align-items-stretch">
        <div class="col-12 col-xl-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Form Pendaftaran</h5>
                    <form method="post" action="<?= yii\helpers\Url::to(['/pendaftaran']) ?>">
                        <input type="hidden" name="_csrf-frontend" value="<?= yii\helpers\Html::encode(Yii::$app->request->getCsrfToken()) ?>">
                        <div class="mb-3">
                            <label class="form-label" for="tanggal_periksa">Tanggal Periksa</label>
                            <input class="form-control" type="date" id="tanggal_periksa" name="tanggal_periksa" value="<?= yii\helpers\Html::encode($tanggalPeriksa) ?>" required>
                            <div class="form-text">Ganti tanggal akan memuat jadwal dokter secara AJAX.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="no_rkm_medis">Nomor RM Pasien</label>
                            <div class="input-group">
                                <input class="form-control" type="text" id="no_rkm_medis" name="no_rkm_medis" placeholder="Klik untuk cari RM" readonly required style="cursor:pointer;">
                                <button class="btn btn-outline-primary" type="button" id="openPatientModal">Cari RM</button>
                            </div>
                            <div class="form-text" id="selectedPatientInfo">Belum ada pasien dipilih.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="schedule_key">Jadwal Dokter</label>
                            <select class="form-select" id="schedule_key" name="schedule_key" required>
                                <option value="">Pilih jadwal dokter yang masih tersedia</option>
                                <?php foreach ($availableSchedules as $schedule): ?>
                                    <?php $scheduleKey = implode('|', [$schedule['kd_dokter'], $schedule['hari_kerja'], $schedule['jam_mulai']]); ?>
                                    <option value="<?= yii\helpers\Html::encode($scheduleKey) ?>">
                                        <?= yii\helpers\Html::encode($schedule['nm_dokter'] ?? '-') ?> - <?= yii\helpers\Html::encode($schedule['nm_poli'] ?? '-') ?>
                                        (<?= yii\helpers\Html::encode(substr((string) $schedule['jam_mulai'], 0, 5)) ?>-<?= yii\helpers\Html::encode(substr((string) $schedule['jam_selesai'], 0, 5)) ?>, sisa <?= yii\helpers\Html::encode((string) ($schedule['kuota_tersisa'] ?? $schedule['kuota'] ?? 0)) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Simpan Pendaftaran</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <h5 class="card-title mb-0">Jadwal Tersedia</h5>
                        <small class="text-muted" id="scheduleSummary"><?= yii\helpers\Html::encode($hariLabels[$hariKerja] ?? $hariKerja) ?>, <?= yii\helpers\Html::encode($tanggalPeriksa) ?></small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Dokter</th>
                                    <th>Poli</th>
                                    <th>Jam</th>
                                    <th class="text-end">Kuota</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($availableSchedules as $schedule): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= yii\helpers\Html::encode($schedule['nm_dokter'] ?? '-') ?></div>
                                            <small class="text-muted"><?= yii\helpers\Html::encode($schedule['kd_dokter'] ?? '-') ?></small>
                                        </td>
                                        <td><?= yii\helpers\Html::encode($schedule['nm_poli'] ?? '-') ?></td>
                                        <td><?= yii\helpers\Html::encode(substr((string) $schedule['jam_mulai'], 0, 5)) ?> - <?= yii\helpers\Html::encode(substr((string) $schedule['jam_selesai'], 0, 5)) ?></td>
                                        <td class="text-end">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle"><?= yii\helpers\Html::encode((string) ($schedule['kuota_tersisa'] ?? $schedule['kuota'] ?? 0)) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($availableSchedules)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Tidak ada jadwal aktif untuk tanggal ini.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-0">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <h5 class="card-title mb-0">Pendaftar Terbaru</h5>
                        <small class="text-muted" id="recentSummary"><?= yii\helpers\Html::encode($hariLabels[$hariKerja] ?? $hariKerja) ?>, <?= yii\helpers\Html::encode($tanggalPeriksa) ?></small>
                    </div>
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-12 col-lg-8">
                            <label class="form-label mb-1" for="recentSearchInput">Cari pendaftar</label>
                            <input type="search" class="form-control" id="recentSearchInput" placeholder="Cari no rawat, RM, nama, dokter, jam, atau status" value="<?= yii\helpers\Html::encode($recentSearch ?? '') ?>">
                        </div>
                        <div class="col-12 col-lg-4 d-grid d-lg-flex gap-2 justify-content-lg-end">
                            <button type="button" class="btn btn-primary" id="recentSearchButton">Cari</button>
                            <button type="button" class="btn btn-outline-secondary" id="recentResetButton">Reset</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>No Rawat</th>
                                    <th>Tgl Daftar</th>
                                    <th>Jam Registrasi</th>
                                    <th>Jam Booking</th>
                                    <th>No RM</th>
                                    <th>Nama Pasien</th>
                                    <th>Dokter</th>
                                    <th>No Antrian</th>
                                    <th>Status Pcare</th>
                                    <th>Status Penanganan</th>
                                </tr>
                            </thead>
                            <tbody id="recentBookingBody">
                                <?php foreach ($recentBookings as $booking): ?>
                                    <tr>
                                        <td><?= yii\helpers\Html::encode($booking['no_rawat'] ?? '-') ?></td>
                                        <td><?= yii\helpers\Html::encode($booking['tglDaftar'] ?? '-') ?></td>
                                        <td><?= yii\helpers\Html::encode($booking['jam_registrasi'] ?? '-') ?></td>
                                        <td><?= yii\helpers\Html::encode($booking['jam_booking'] ?? '-') ?></td>
                                        <td><?= yii\helpers\Html::encode($booking['no_rkm_medis'] ?? '-') ?></td>
                                        <td><?= yii\helpers\Html::encode($booking['nm_pasien'] ?? '-') ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= yii\helpers\Html::encode($booking['nm_dokter'] ?? '-') ?></div>
                                            <small class="text-muted"><?= yii\helpers\Html::encode($booking['kd_dokter'] ?? '-') ?></small>
                                        </td>
                                        <td><span class="badge bg-info-subtle text-info border border-info-subtle"><?= yii\helpers\Html::encode($booking['noUrut'] ?? '-') ?></span></td>
                                        <td><span class="badge bg-warning-subtle text-warning border border-warning-subtle"><?= yii\helpers\Html::encode($booking['status'] ?? '-') ?></span></td>
                                        <td><span class="badge bg-<?= yii\helpers\Html::encode($booking['status_penanganan_class'] ?? 'warning') ?>-subtle text-<?= yii\helpers\Html::encode($booking['status_penanganan_class'] ?? 'warning') ?> border border-<?= yii\helpers\Html::encode($booking['status_penanganan_class'] ?? 'warning') ?>-subtle"><?= yii\helpers\Html::encode($booking['status_penanganan'] ?? '-') ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentBookings)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">Belum ada pendaftaran terbaru.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <nav class="d-flex justify-content-end mt-3" id="recentPager" aria-label="Pendaftar terbaru pagination"></nav>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="patientModal" tabindex="-1" aria-labelledby="patientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="patientModalLabel">Cari Pasien / Nomor RM</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="patientTable">
                        <thead class="table-light">
                            <tr>
                                <th>No RM</th>
                                <th>Nama Pasien</th>
                                <th>No KTP</th>
                                <th>JK</th>
                                <th>Tgl Lahir</th>
                                <th>No Tlp</th>
                                <th>Alamat</th>
                                <th></th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$scheduleUrl = yii\helpers\Url::to(['/pendaftaran/default/schedules']);
$patientUrl = yii\helpers\Url::to(['/pendaftaran/default/patients']);
$initialRecentPage = (int) $recentPagination->getPage();
$initialRecentPageCount = (int) $recentPagination->getPageCount();
$initialRecentSearch = yii\helpers\Json::encode((string) ($recentSearch ?? ''));
$this->registerJs(<<<JS
(function () {
    const tanggalInput = document.getElementById('tanggal_periksa');
    const hariBadge = document.querySelector('.badge.bg-primary-subtle');
    const scheduleSelect = document.getElementById('schedule_key');
    const scheduleSummary = document.getElementById('scheduleSummary');
    const recentSummary = document.getElementById('recentSummary');
    const recentSearchInput = document.getElementById('recentSearchInput');
    const recentSearchButton = document.getElementById('recentSearchButton');
    const recentResetButton = document.getElementById('recentResetButton');
    const recentPager = document.getElementById('recentPager');
    const schedulesTableBody = document.querySelector('.card.shadow-sm.border-0.mb-4 .table tbody');
    const recentBookingBody = document.getElementById('recentBookingBody');
    const patientInput = document.getElementById('no_rkm_medis');
    const patientInfo = document.getElementById('selectedPatientInfo');
    const patientModalElement = document.getElementById('patientModal');
    const patientModal = new bootstrap.Modal(patientModalElement);
    const patientTableSelector = '#patientTable';
    let patientTable = null;
    let currentRecentPage = {$initialRecentPage};
    let currentRecentPageCount = {$initialRecentPageCount};

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderSchedules(schedules, hariLabel, tanggalPeriksa) {
        hariBadge.textContent = 'Hari praktik: ' + hariLabel;
        scheduleSummary.textContent = hariLabel + ', ' + tanggalPeriksa;

        scheduleSelect.innerHTML = '<option value="">Pilih jadwal dokter yang masih tersedia</option>';
        schedulesTableBody.innerHTML = '';

        if (!schedules.length) {
            schedulesTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Tidak ada jadwal aktif untuk tanggal ini.</td></tr>';
            return;
        }

        schedules.forEach(function (schedule) {
            const scheduleKey = [schedule.kd_dokter, schedule.hari_kerja, schedule.jam_mulai].join('|');
            const option = document.createElement('option');
            option.value = scheduleKey;
            option.textContent = schedule.nm_dokter + ' - ' + schedule.nm_poli + ' (' + schedule.jam_mulai.substring(0, 5) + '-' + schedule.jam_selesai.substring(0, 5) + ', sisa ' + (schedule.kuota_tersisa ?? schedule.kuota) + ')';
            scheduleSelect.appendChild(option);

            const row = document.createElement('tr');
            row.innerHTML =
                '<td><div class="fw-semibold">' + escapeHtml(schedule.nm_dokter) + '</div><small class="text-muted">' + escapeHtml(schedule.kd_dokter) + '</small></td>' +
                '<td>' + escapeHtml(schedule.nm_poli) + '</td>' +
                '<td>' + escapeHtml(schedule.jam_mulai.substring(0, 5)) + ' - ' + escapeHtml(schedule.jam_selesai.substring(0, 5)) + '</td>' +
                '<td class="text-end"><span class="badge bg-success-subtle text-success border border-success-subtle">' + escapeHtml(schedule.kuota_tersisa ?? schedule.kuota) + '</span></td>';
            schedulesTableBody.appendChild(row);
        });
    }

    function renderRecentRegistrations(rows, hariLabel, tanggalPeriksa) {
        recentSummary.textContent = hariLabel + ', ' + tanggalPeriksa;
        recentBookingBody.innerHTML = '';

        if (!rows.length) {
            recentBookingBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Belum ada pendaftaran terbaru.</td></tr>';
            return;
        }

        rows.forEach(function (row) {
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + escapeHtml(row.no_rawat) + '</td>' +
                '<td>' + escapeHtml(row.tglDaftar) + '</td>' +
                '<td>' + escapeHtml(row.jam_registrasi || '-') + '</td>' +
                '<td>' + escapeHtml(row.jam_booking || '-') + '</td>' +
                '<td>' + escapeHtml(row.no_rkm_medis) + '</td>' +
                '<td>' + escapeHtml(row.nm_pasien) + '</td>' +
                '<td><div class="fw-semibold">' + escapeHtml(row.nm_dokter) + '</div><small class="text-muted">' + escapeHtml(row.kd_dokter || '-') + '</small></td>' +
                '<td><span class="badge bg-info-subtle text-info border border-info-subtle">' + escapeHtml(row.noUrut) + '</span></td>' +
                '<td><span class="badge bg-warning-subtle text-warning border border-warning-subtle">' + escapeHtml(row.status) + '</span></td>' +
                '<td><span class="badge bg-' + escapeHtml(row.status_penanganan_class || 'warning') + '-subtle text-' + escapeHtml(row.status_penanganan_class || 'warning') + ' border border-' + escapeHtml(row.status_penanganan_class || 'warning') + '-subtle">' + escapeHtml(row.status_penanganan || '-') + '</span></td>';
            recentBookingBody.appendChild(tr);
        });
    }

    function renderRecentPager(pageCount, activePage, tanggalPeriksa) {
        recentPager.innerHTML = '';

        if (!pageCount || pageCount <= 1) {
            return;
        }

        const nav = document.createElement('ul');
        nav.className = 'pagination mb-0';

        function addItem(label, pageIndex, disabled, active, ariaLabel) {
            const item = document.createElement('li');
            item.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');

            const link = document.createElement('button');
            link.type = 'button';
            link.className = 'page-link';
            link.textContent = label;
            if (ariaLabel) {
                link.setAttribute('aria-label', ariaLabel);
            }
            if (!disabled && !active) {
                link.addEventListener('click', function () {
                    refreshSchedules(pageIndex, tanggalPeriksa);
                });
            }

            item.appendChild(link);
            nav.appendChild(item);
        }

        addItem('«', Math.max(activePage - 1, 0), activePage <= 0, false, 'Halaman sebelumnya');
        for (let pageIndex = 0; pageIndex < pageCount; pageIndex += 1) {
            addItem(String(pageIndex + 1), pageIndex, false, pageIndex === activePage, 'Halaman ' + (pageIndex + 1));
        }
        addItem('»', Math.min(activePage + 1, pageCount - 1), activePage >= pageCount - 1, false, 'Halaman berikutnya');

        recentPager.appendChild(nav);
    }

    function refreshSchedules(recentPage, forcedTanggalPeriksa, forcedSearch) {
        const tanggalPeriksa = forcedTanggalPeriksa || tanggalInput.value;
        if (!tanggalPeriksa) {
            return;
        }

        const pageValue = typeof recentPage === 'number' ? recentPage : 0;
        const searchValue = typeof forcedSearch === 'string' ? forcedSearch : (recentSearchInput ? recentSearchInput.value.trim() : '');

        fetch('{$scheduleUrl}?tanggal_periksa=' + encodeURIComponent(tanggalPeriksa) + '&recent_page=' + encodeURIComponent(pageValue) + '&recent_search=' + encodeURIComponent(searchValue), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                if (payload && payload.success) {
                    renderSchedules(payload.schedules || [], payload.hari_label || '', payload.tanggal_periksa || tanggalPeriksa);
                    renderRecentRegistrations(payload.recent_registrations || [], payload.hari_label || '', payload.tanggal_periksa || tanggalPeriksa);
                    currentRecentPage = (payload.recent_pagination && typeof payload.recent_pagination.page === 'number') ? payload.recent_pagination.page : 0;
                    currentRecentPageCount = (payload.recent_pagination && typeof payload.recent_pagination.pageCount === 'number') ? payload.recent_pagination.pageCount : 0;
                    renderRecentPager(currentRecentPageCount, currentRecentPage, payload.tanggal_periksa || tanggalPeriksa);
                }
            })
            .catch(function () {
                schedulesTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Gagal memuat jadwal dokter.</td></tr>';
                recentBookingBody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Gagal memuat pendaftar terbaru.</td></tr>';
                recentPager.innerHTML = '';
            });
    }

    tanggalInput.addEventListener('change', function () {
        refreshSchedules(0, tanggalInput.value);
    });

    recentSearchButton.addEventListener('click', function () {
        currentRecentPage = 0;
        refreshSchedules(0, tanggalInput.value, recentSearchInput.value.trim());
    });

    recentSearchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            currentRecentPage = 0;
            refreshSchedules(0, tanggalInput.value, recentSearchInput.value.trim());
        }
    });

    recentResetButton.addEventListener('click', function () {
        recentSearchInput.value = '';
        currentRecentPage = 0;
        refreshSchedules(0, tanggalInput.value, '');
    });

    patientInput.addEventListener('click', function () {
        patientModal.show();
    });

    document.getElementById('openPatientModal').addEventListener('click', function () {
        patientModal.show();
    });

    patientModalElement.addEventListener('shown.bs.modal', function () {
        if (patientTable) {
            patientTable.columns.adjust().draw(false);
            return;
        }

        patientTable = $(patientTableSelector).DataTable({
            processing: true,
            serverSide: true,
            searching: true,
            lengthChange: false,
            pageLength: 10,
            ajax: {
                url: '{$patientUrl}',
                dataSrc: 'data'
            },
            columns: [
                { data: 'no_rkm_medis' },
                { data: 'nm_pasien' },
                { data: 'no_ktp' },
                { data: 'jk' },
                { data: 'tgl_lahir' },
                { data: 'no_tlp' },
                { data: 'alamat' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (_, __, row) {
                        return '<button type="button" class="btn btn-sm btn-primary select-patient" data-no-rkm="' + escapeHtml(row.no_rkm_medis) + '" data-nama="' + escapeHtml(row.nm_pasien) + '">Pilih</button>';
                    }
                }
            ],
            createdRow: function (row, data) {
                $(row).attr('data-no-rkm', data.no_rkm_medis);
            }
        });

        $(patientTableSelector + ' tbody').on('click', '.select-patient', function () {
            const button = this;
            patientInput.value = button.getAttribute('data-no-rkm') || '';
            patientInfo.textContent = 'Pasien terpilih: ' + (button.getAttribute('data-nama') || '-') + ' (' + (button.getAttribute('data-no-rkm') || '-') + ')';
            patientModal.hide();
        });
    });

    recentSearchInput.value = {$initialRecentSearch};
    renderRecentPager(currentRecentPageCount, currentRecentPage, tanggalInput.value);

    refreshSchedules(currentRecentPage, tanggalInput.value, recentSearchInput.value.trim());
})();
JS,
\yii\web\View::POS_END);
?>
