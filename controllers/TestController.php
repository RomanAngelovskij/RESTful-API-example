<?php
namespace app\controllers;

use Facebook\Authentication\AccessToken;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
use Yii;
use yii\web\Controller;
use app\models\TestForm;

class TestController extends Controller {

    public function actionIndex()
    {
        $token = 'EAACJF4r6XaMBAC4IfR1T9n9DqwHaGDTS9kWlBIAVPCiI4xoVRIJAXVyxJCNiUjo8yyQda2AyZCQVCff5Ngiskpo01nV0XMl3MipnAVEToIEiFgKLfJ9J1d4xPVqC2lyJdjYSJygYqQHez7mmVwxRoMNdzlvKZB3jCj7wrLZBEdqcEgU1J8HQbRIOFwHxrPzeSf8a5Rejr5p8Ar2Wmxz6PFVDsShqdQ0qcOjIFSOdAZDZD';
        $fb = new Facebook([
            'app_id' => Yii::$app->params['facebookAppId'],
            'app_secret' => Yii::$app->params['facebookSecret']
        ]);

        try {
            // Returns a `Facebook\FacebookResponse` object
            $response = $fb->get('/me?fields=id,name', $token);
        } catch(FacebookResponseException $e) {
            echo 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch(FacebookSDKException $e) {
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }

        $user = $response->getGraphUser();

        var_dump($user->getId());
    }
}