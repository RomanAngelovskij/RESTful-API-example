<?php
namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class Mfo extends ActiveRecord{

    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }
}