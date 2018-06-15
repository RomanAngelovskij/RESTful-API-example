<?php
namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class PushLog extends ActiveRecord{

    public function behaviors()
    {
        return [
            TimestampBehavior::className()
        ];
    }
}