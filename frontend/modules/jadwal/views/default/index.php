<?php

/** @var yii\web\View $this */
/** @var yii\data\ArrayDataProvider $provider */
/** @var string $hari */

$this->title = 'Jadwal Dokter';
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

$currentHariLabel = $hari !== '' ? ($hariLabels[$hari] ?? $hari) : 'Semua hari';
$hariFilterButtons = [
    '' => 'Semua Jadwal',
    'SENIN' => 'Senin',
    'SELASA' => 'Selasa',
    'RABU' => 'Rabu',
    'KAMIS' => 'Kamis',
    'JUMAT' => 'Jumat',
    'SABTU' => 'Sabtu',
    'AKHAD' => 'Ahad',
];
?>
<div class="module-default-index">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="mb-2"><?= yii\helpers\Html::encode($this->title) ?></h1>
            <p class="text-muted mb-0">Jadwal dokter aktif dari database SIMRS Khanza untuk portal pegawai dan pasien.</p>
        </div>
        <div class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
            Filter hari: <?= yii\helpers\Html::encode($currentHariLabel) ?>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($hariFilterButtons as $filterValue => $label): ?>
                    <?php $isActive = $hari === $filterValue; ?>
                    <a class="btn btn-sm <?= $isActive ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= yii\helpers\Url::to(array_merge(['/jadwal'], $filterValue === '' ? [] : ['hari' => $filterValue])) ?>">
                        <?= yii\helpers\Html::encode($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="table-responsive card shadow-sm border-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Hari</th>
                    <th>Jam Praktik</th>
                    <th>Dokter</th>
                    <th>Poli</th>
                    <th>No. Izin Praktik</th>
                    <th class="text-end">Kuota</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($provider->models as $row): ?>
                    <tr>
                        <td>
                            <span class="badge bg-info-subtle text-info border border-info-subtle">
                                <?= yii\helpers\Html::encode($hariLabels[$row['hari_kerja']] ?? $row['hari_kerja']) ?>
                            </span>
                        </td>
                        <td>
                            <?= yii\helpers\Html::encode(substr((string) $row['jam_mulai'], 0, 5)) ?>
                            -
                            <?= yii\helpers\Html::encode($row['jam_selesai'] ? substr((string) $row['jam_selesai'], 0, 5) : '-') ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= yii\helpers\Html::encode($row['nm_dokter'] ?? '-') ?></div>
                            <small class="text-muted"><?= yii\helpers\Html::encode($row['kd_dokter'] ?? '-') ?></small>
                        </td>
                        <td><?= yii\helpers\Html::encode($row['nm_poli'] ?? '-') ?></td>
                        <td><?= yii\helpers\Html::encode($row['no_ijn_praktek'] ?? '-') ?></td>
                        <td class="text-end">
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                <?= yii\helpers\Html::encode((string) ($row['kuota'] ?? 0)) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($provider->models)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">Belum ada jadwal dokter untuk filter ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
