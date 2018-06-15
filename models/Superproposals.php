<?php
namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class Superproposals extends ActiveRecord{

    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

    public function getMfo()
    {
        return $this->hasOne(Mfo::className(), ['id' => 'mfo_id']);
    }
}