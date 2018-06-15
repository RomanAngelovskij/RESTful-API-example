<?php
namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class UsersDevices extends ActiveRecord{

    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

    public function rules()
    {
        return [
            [['user_id', 'device_id'], 'required'],
            ['user_id', 'exist', 'targetClass' => User::className(), 'targetAttribute' => 'id'],
            ['user_id', 'unique'],
            ['device_id', 'unique'],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }
}