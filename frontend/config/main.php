<?php

declare(strict_types=1);

$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php',
);

return [
    'id' => 'app-frontend',
    'basePath' => dirname(__DIR__),
    'homeUrl' => '/',
    'bootstrap' => ['log'],
    'controllerNamespace' => 'frontend\controllers',
    'modules' => [
        'pasien' => [
            'class' => frontend\modules\pasien\Module::class,
        ],
        'pendaftaran' => [
            'class' => frontend\modules\pendaftaran\Module::class,
        ],
        'antrean' => [
            'class' => frontend\modules\antrean\Module::class,
        ],
        'jadwal' => [
            'class' => frontend\modules\jadwal\Module::class,
        ],
        'billing' => [
            'class' => frontend\modules\billing\Module::class,
        ],
        'lab' => [
            'class' => frontend\modules\lab\Module::class,
        ],
        'radiologi' => [
            'class' => frontend\modules\radiologi\Module::class,
        ],
        'farmasi' => [
            'class' => frontend\modules\farmasi\Module::class,
        ],
    ],
    'components' => [
        'request' => [
            'csrfParam' => '_csrf-frontend',
        ],
        'user' => [
            'identityClass' => \common\models\User::class,
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity-frontend', 'httpOnly' => true],
        ],
        'session' => [
            // this is the name of the session cookie used for login on the frontend
            'name' => 'advanced-frontend',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                '' => 'site/index',
                '<module:[a-z-]+>' => '<module>/default/index',
                '<module:[a-z-]+>/<controller:[a-z-]+>/<action:[a-z-]+>' => '<module>/<controller>/<action>',
            ],
        ],
    ],
    'params' => $params,
];
