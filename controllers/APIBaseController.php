<?php
namespace app\controllers;

use app\core\ApiErrorHandler;
use app\core\auth\HttpSocialAuth;
use yii\filters\auth\CompositeAuth;
use yii\filters\auth\HttpBearerAuth;
use yii\rest\ActiveController;
use yii\web\Response;

/**
 * Class APIBaseController
 * @package app\controllers
 *
 * Базовый класс для всех контролддеров API
 */
class APIBaseController extends ActiveController{

    public function behaviors()
    {
        return [
            'authenticator' => [
                'class' => CompositeAuth::className(),
                'authMethods' => [
                    [
                        'class' => HttpBearerAuth::className(),
                    ],
                    [
                        'class' => HttpSocialAuth::className(),
                    ]
                ]
            ],
        ];
    }

    public function init()
    {
        parent::init();
        \Yii::$app->user->enableSession = false;

        \Yii::$app->language = \Yii::$app->request->getHeaders('Accept-Language');

        $handler = new ApiErrorHandler();
        \Yii::$app->set('errorHandler', $handler);
        //необходимо вызывать register, это обязательный метод для регистрации обработчика
        $handler->register();
    }

    protected function client()
    {
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }

        if (in_array(\Yii::$app->request->userIP, \Yii::$app->params['tgBotServerIP'])){
            return 'telegram';
        }

        return 'android';
    }

}