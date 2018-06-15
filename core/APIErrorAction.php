<?php
namespace app\core;

use Yii;
use yii\filters\ContentNegotiator;
use yii\web\ErrorAction;
use yii\web\Response;

class APIErrorAction extends ErrorAction{

    protected $defaultErrorCode = 0;

    public $view = '/api-error';

    public function behaviors()
    {
        return [
            'contentNegotiator' => [
                'class' => ContentNegotiator::className(),
                'formats' => [
                    'application/json' => Response::FORMAT_JSON
                ]
            ]
        ];
    }

    public function run()
    {
        Yii::$app->getResponse()->setStatusCodeByException($this->exception);
        return $this->renderJsonResponse();
    }

    public function renderJsonResponse(){
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        return [
            'code' => $this->exception->getCode(),
            'message' => $this->getExceptionMessage(),
        ];
    }
}