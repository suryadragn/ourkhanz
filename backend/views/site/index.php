<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->title = 'Dashboard SIMRS Khanza';
$username = Yii::$app->user->identity?->username;

$modules = [
    ['name' => 'Master Data', 'route' => ['/master/default/index']],
    ['name' => 'Pendaftaran', 'route' => ['/pendaftaran/default/index']],
    ['name' => 'Rawat Jalan', 'route' => ['/rawatjalan/default/index']],
    ['name' => 'Rawat Inap', 'route' => ['/rawatinap/default/index']],
    ['name' => 'IGD', 'route' => ['/igd/default/index']],
    ['name' => 'Farmasi', 'route' => ['/farmasi/default/index']],
    ['name' => 'Laboratorium', 'route' => ['/lab/default/index']],
    ['name' => 'Radiologi', 'route' => ['/radiologi/default/index']],
    ['name' => 'Keuangan', 'route' => ['/keuangan/default/index']],
    ['name' => 'Laporan', 'route' => ['/laporan/default/index']],
    ['name' => 'Bridging', 'route' => ['/bridging/default/index']],
    ['name' => 'Setting', 'route' => ['/setting/default/index']],
];
?>
<div class="site-index">
    <div class="dashboard-banner text-white rounded-4 p-4 p-lg-5 mb-4 bg-success">
        <h1 class="fw-bold mb-2">Control Center SIMRS</h1>
        <p class="mb-0 opacity-75">Login sebagai: <?= Html::encode((string) $username) ?></p>
    </div>

    <div class="row g-3">
        <?php foreach ($modules as $module): ?>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h2 class="h6 mb-3"><?= Html::encode($module['name']) ?></h2>
                        <?= Html::a('Masuk Modul', $module['route'], ['class' => 'btn btn-outline-success btn-sm mt-auto']) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
