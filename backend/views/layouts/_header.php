<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;
use yii\helpers\Url;

$menuItems = [
    ['label' => 'Dashboard', 'url' => ['/site/index']],
    ['label' => 'Pendaftaran', 'url' => ['/pendaftaran/default/index']],
    ['label' => 'Rawat Jalan', 'url' => ['/rawatjalan/default/index']],
    ['label' => 'Rawat Inap', 'url' => ['/rawatinap/default/index']],
    ['label' => 'IGD', 'url' => ['/igd/default/index']],
    ['label' => 'Farmasi', 'url' => ['/farmasi/default/index']],
    ['label' => 'Laboratorium', 'url' => ['/lab/default/index']],
    ['label' => 'Radiologi', 'url' => ['/radiologi/default/index']],
    ['label' => 'Keuangan', 'url' => ['/keuangan/default/index']],
    ['label' => 'Laporan', 'url' => ['/laporan/default/index']],
];
?>
<header id="header" class="app-header app-header-backend">
    <div class="container py-3">
        <div class="app-header-top">
            <a class="app-brand" href="<?= Url::to(['/site/index']) ?>">
                <span class="app-brand-title">SIMRS Khanza Admin</span>
                <span class="app-brand-subtitle">Operasional Rumah Sakit</span>
            </a>

            <div class="app-header-actions">
                <?= Html::button('Theme', [
                    'id' => 'theme-toggle',
                    'class' => 'btn btn-sm btn-outline-secondary',
                    'aria-label' => 'Toggle color mode',
                ]) ?>
                <?php if (Yii::$app->user->isGuest): ?>
                    <?= Html::a('Login', ['/site/login'], ['class' => 'btn btn-sm btn-primary']) ?>
                <?php else: ?>
                    <span class="app-user-badge">Petugas: <?= Html::encode((string) Yii::$app->user->identity?->username) ?></span>
                    <?= Html::beginForm(['/site/logout'], 'post', ['class' => 'd-inline']) ?>
                    <?= Html::submitButton('Logout', ['class' => 'btn btn-sm btn-danger']) ?>
                    <?= Html::endForm() ?>
                <?php endif; ?>
            </div>
        </div>

        <nav class="app-nav" aria-label="Navigasi utama backend">
            <?php foreach ($menuItems as $item): ?>
                <?= Html::a(Html::encode($item['label']), $item['url'], ['class' => 'app-nav-link']) ?>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
