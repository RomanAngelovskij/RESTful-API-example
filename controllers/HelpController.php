<?php
namespace app\controllers;

use app\models\Faq;
use yii\web\NotFoundHttpException;

class HelpController extends APIBaseController{

    public $modelClass = 'app\models\Faq';

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['index'];
        return $behaviors;
    }

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
        $faq = Faq::find()->orderBy(['sort' => SORT_DESC])->all();
        if (empty($faq)) {
            throw new NotFoundHttpException('Help not found');
        }

        $result = ['count' => count($faq), 'items' => []];
        if (!empty($faq)) {
            foreach ($faq as $qa) {
                $result['items'][] = [
                    'question' => strip_tags(\Yii::t('page', $qa->question)),
                    'answer' => strip_tags(\Yii::t('page', $qa->answer)),
                    ];
            }
        }

        return $result;
    }
}