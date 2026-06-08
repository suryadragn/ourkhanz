<?php

namespace frontend\modules\jadwal\controllers;

use Yii;
use yii\data\ArrayDataProvider;
use yii\db\Expression;
use yii\db\Query;
use yii\web\Controller;

class DefaultController extends Controller
{
    public function actionIndex(): string
    {
        $hari = Yii::$app->request->get('hari', '');

        $query = (new Query())
            ->from(['j' => 'jadwal'])
            ->select([
                'j.kd_dokter',
                'j.hari_kerja',
                'j.jam_mulai',
                'j.jam_selesai',
                'j.kd_poli',
                'j.kuota',
                'd.nm_dokter',
                'd.no_ijn_praktek',
                'p.nm_poli',
            ])
            ->leftJoin(['d' => 'dokter'], 'd.kd_dokter = j.kd_dokter')
            ->leftJoin(['p' => 'poliklinik'], 'p.kd_poli = j.kd_poli')
            ->orderBy(new Expression("FIELD(j.hari_kerja, 'SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU', 'AKHAD'), j.jam_mulai, d.nm_dokter"));

        if ($hari !== '') {
            $query->andWhere(['j.hari_kerja' => $hari]);
        }

        $provider = new ArrayDataProvider([
            'allModels' => $query->all(),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        return $this->render('index', [
            'provider' => $provider,
            'hari' => $hari,
        ]);
    }
}
