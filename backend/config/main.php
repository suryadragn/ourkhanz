<?php

$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'app-backend',
    'basePath' => dirname(__DIR__),
    'homeUrl' => '/admin',
    'controllerNamespace' => 'backend\controllers',
    'bootstrap' => ['log'],
    'modules' => [
        'master' => [
            'class' => backend\modules\master\Module::class,
        ],
        'pendaftaran' => [
            'class' => backend\modules\pendaftaran\Module::class,
        ],
        'rawatjalan' => [
            'class' => backend\modules\rawatjalan\Module::class,
        ],
        'rawatinap' => [
            'class' => backend\modules\rawatinap\Module::class,
        ],
        'igd' => [
            'class' => backend\modules\igd\Module::class,
        ],
        'farmasi' => [
            'class' => backend\modules\farmasi\Module::class,
        ],
        'lab' => [
            'class' => backend\modules\lab\Module::class,
        ],
        'radiologi' => [
            'class' => backend\modules\radiologi\Module::class,
        ],
        'keuangan' => [
            'class' => backend\modules\keuangan\Module::class,
        ],
        'laporan' => [
            'class' => backend\modules\laporan\Module::class,
        ],
        'bridging' => [
            'class' => backend\modules\bridging\Module::class,
        ],
        'setting' => [
            'class' => backend\modules\setting\Module::class,
        ],
    ],
    'components' => [
        'request' => [
            'csrfParam' => '_csrf-backend',
        ],
        'user' => [
            'identityClass' => \common\models\User::class,
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity-backend', 'httpOnly' => true],
        ],
        'session' => [
            // this is the name of the session cookie used for login on the backend
            'name' => 'advanced-backend',
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
            'baseUrl' => '/admin',
            'rules' => [
                '' => 'site/index',
                '<module:[a-z-]+>' => '<module>/default/index',
                '<module:[a-z-]+>/<controller:[a-z-]+>/<action:[a-z-]+>' => '<module>/<controller>/<action>',
            ],
        ],
    ],
    'params' => $params,
];
