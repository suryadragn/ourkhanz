<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;
use yii\helpers\Url;

$menuItems = [
    ['label' => 'Dashboard', 'url' => ['/site/index']],
    ['label' => 'Pendaftaran', 'url' => ['/pendaftaran/default/index']],
    ['label' => 'Antrean', 'url' => ['/antrean/default/index']],
    ['label' => 'Jadwal', 'url' => ['/jadwal/default/index']],
    ['label' => 'Billing', 'url' => ['/billing/default/index']],
    ['label' => 'Laboratorium', 'url' => ['/lab/default/index']],
    ['label' => 'Radiologi', 'url' => ['/radiologi/default/index']],
    ['label' => 'Farmasi', 'url' => ['/farmasi/default/index']],
];

?>
<header id="header" class="app-header app-header-frontend">
    <div class="container py-3">
        <div class="app-header-top">
            <a class="app-brand" href="<?= Url::to(['/site/index']) ?>">
                <span class="app-brand-title">SIMRS Khanza</span>
                <span class="app-brand-subtitle">Portal Pegawai</span>
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
                    <span class="app-user-badge">User: <?= Html::encode((string) Yii::$app->user->identity?->username) ?></span>
                    <?= Html::beginForm(['/site/logout'], 'post', ['class' => 'd-inline']) ?>
                    <?= Html::submitButton('Logout', ['class' => 'btn btn-sm btn-danger']) ?>
                    <?= Html::endForm() ?>
                <?php endif; ?>
            </div>
        </div>

        <nav class="app-nav" aria-label="Navigasi utama frontend">
            <?php foreach ($menuItems as $item): ?>
                <?= Html::a(Html::encode($item['label']), $item['url'], ['class' => 'app-nav-link']) ?>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
