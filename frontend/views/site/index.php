<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->title = 'Portal SIMRS Khanza';

$modules = [
    ['name' => 'Pasien', 'route' => ['/pasien/default/index'], 'desc' => 'Portal data pasien dan riwayat kunjungan.'],
    ['name' => 'Pendaftaran', 'route' => ['/pendaftaran/default/index'], 'desc' => 'Registrasi online dan verifikasi administrasi.'],
    ['name' => 'Antrean', 'route' => ['/antrean/default/index'], 'desc' => 'Informasi antrean pelayanan real-time.'],
    ['name' => 'Jadwal', 'route' => ['/jadwal/default/index'], 'desc' => 'Jadwal dokter, poli, dan ketersediaan layanan.'],
    ['name' => 'Billing', 'route' => ['/billing/default/index'], 'desc' => 'Tagihan dan status pembayaran pasien.'],
    ['name' => 'Laboratorium', 'route' => ['/lab/default/index'], 'desc' => 'Akses hasil pemeriksaan laboratorium.'],
    ['name' => 'Radiologi', 'route' => ['/radiologi/default/index'], 'desc' => 'Permintaan dan hasil pemeriksaan radiologi.'],
    ['name' => 'Farmasi', 'route' => ['/farmasi/default/index'], 'desc' => 'Informasi resep dan penebusan obat.'],
];
?>
<div class="site-index">
    <div class="p-4 p-lg-5 mb-4 bg-primary text-white rounded-4">
        <h1 class="fw-bold mb-2">Portal Web SIMRS Khanza (Yii2 Advanced)</h1>
        <p class="mb-0 opacity-75">Fondasi frontend modular siap dikembangkan untuk fitur layanan pasien end-to-end.</p>
    </div>

    <div class="row g-3">
        <?php foreach ($modules as $module): ?>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body d-flex flex-column">
                        <h2 class="h5"><?= Html::encode($module['name']) ?></h2>
                        <p class="text-body-secondary small flex-grow-1"><?= Html::encode($module['desc']) ?></p>
                        <?= Html::a('Buka Modul', $module['route'], ['class' => 'btn btn-outline-primary btn-sm']) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
