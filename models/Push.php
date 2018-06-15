<?php
namespace app\models;

use Yii;
use yii\base\Model;

class Push extends Model {

    private $_gcm;

    public function init()
    {
        $this->_gcm = Yii::$app->gcm;
    }

    public function command($command, $regIds, $data = [])
    {
        $message = [
            'command' => $command,
            'message' => $data,
        ];

        $sendResult = $this->_gcm->sendMulti($regIds, $message,
            [
                'customerProperty' => 1,
            ],
            [
                'timeToLive' => 3
            ]
        );

        $result = $this->_gcm;

        return $result;
    }

}