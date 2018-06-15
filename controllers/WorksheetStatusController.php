<?php
namespace app\controllers;

class WorksheetStatusController extends APIBaseController
{
    public $modelClass = 'app\models\WorksheetsStatuses';

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['create']);
        return $actions;
    }


}