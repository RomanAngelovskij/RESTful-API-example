<?php
namespace app\controllers;

use app\models\Personalproposals;
use app\models\ProposalsStatuses;
use yii\web\NotFoundHttpException;

class ProposalController extends APIBaseController{

    public $modelClass = 'app\models\Proposals';

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['update'];
        return $behaviors;
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index']);
        unset($actions['view']);
        unset($actions['create']);
        //unset($actions['update']);
        unset($actions['delete']);
        unset($actions['options']);
        return $actions;
    }
}