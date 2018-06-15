<?php
namespace app\controllers;

use app\models\Mfo;
use yii\web\NotFoundHttpException;

class MfoController extends APIBaseController{

    public $modelClass = 'app\models\Mfo';

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index']);
        unset($actions['create']);
        unset($actions['update']);
        unset($actions['delete']);
        unset($actions['options']);
        return $actions;
    }

    public function actionIndex()
    {
        $mfos = Mfo::find()->where(['country_id' => \Yii::$app->user->identity->data->country])->all();
        if (empty($mfos)) {
            throw new NotFoundHttpException('MFO not found');
        }

        $result = ['count' => count($mfos), 'items' => []];
        if (!empty($mfos)) {
            foreach ($mfos as $mfo) {
                $result['items'][] = [
                    'id' => $mfo->id,
                    'name' => $mfo->name,
                    'legal_name' => $mfo->legal_name,
                    'percent' => (float)$mfo->percent,
                    'limit' => $mfo->amount,
                    'duration' => $mfo->duration,
                    'probability' => $mfo->probability,
                    'rating' => $mfo->rating,
                    'description' => strip_tags($mfo->description),
                    'link' => $mfo->link,
                    'logo' => $mfo->logo,
                    'small_logo' => $mfo->small_logo,
                ];
            }
        }

        return $result;
    }
}