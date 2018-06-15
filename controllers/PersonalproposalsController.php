<?php
namespace app\controllers;

use app\models\Personalproposals;
use app\models\ProposalsStatuses;
use yii\web\NotFoundHttpException;

class PersonalproposalsController extends APIBaseController{

    public $modelClass = 'app\models\Personalproposals';

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index']);
        unset($actions['view']);
        unset($actions['create']);
        unset($actions['update']);
        unset($actions['delete']);
        unset($actions['options']);
        return $actions;
    }

    public function actionIndex()
    {
        $proposals = Personalproposals::findAll(['user_id' => \Yii::$app->user->id]);
        if (empty($proposals)) {
            throw new NotFoundHttpException('Personal proposals not found');
        }

        $result = ['count' => count($proposals), 'items' => []];
        if (!empty($proposals)) {
            foreach ($proposals as $proposal) {
                $result['items'][] = [
                    'id' => $proposal->id,
                    'mfo_id' => $proposal->mfo_id,
                    'amount' => $proposal->mfo->amount,
                    'percent' => (float) $proposal->mfo->percent,
                    'date' => $proposal->created_at,
                    'currency' => 'RUR',
                    'status' => ProposalsStatuses::findOne(ProposalsStatuses::PENDING_STATUS_ID)->symb_id,
                    'link' => $proposal->mfo->link,
                    'duration' => 60,
                ];
            }
        }

        return $result;
    }
}