<?php
namespace app\controllers;

use app\models\Creditcards;
use yii\web\NotFoundHttpException;

class CreditcardsController extends APIBaseController{

    public $modelClass = 'app\models\Creditcards';

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
        $creditcards = Creditcards::find()->all();
        if (empty($creditcards)) {
            throw new NotFoundHttpException('Not found');
        }

        $result = ['count' => count($creditcards), 'items' => []];
        if (!empty($creditcards)) {
            foreach ($creditcards as $creditcard) {
                $result['items'][] = [
                    'id' => $creditcard->id,
                    'name' => $creditcard->name,
                    'legal_name' => $creditcard->legal_name,
                    'percent' => (float)$creditcard->percent,
                    'limit' => $creditcard->amount,
                    'duration' => $creditcard->duration,
                    'probability' => $creditcard->probability,
                    'rating' => $creditcard->rating,
                    'description' => $creditcard->description,
                    'link' => $creditcard->link,
                    'logo' => $creditcard->logo,
                    'small_logo' => $creditcard->small_logo,
                ];
            }
        }

        return $result;
    }
}