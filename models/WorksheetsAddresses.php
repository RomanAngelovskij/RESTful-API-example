<?php
namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class WorksheetsAddresses extends ActiveRecord{

    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

    public function rules()
    {
        return [
            [['worksheet_id', 'address_id', 'value'], 'required'],
            ['worksheet_id', 'exist', 'targetClass' => Worksheets::className(), 'targetAttribute' => 'id'],
            ['address_id', 'exist', 'targetClass' => WorksheetsamplesAddresses::className(), 'targetAttribute' => 'id'],
        ];
    }

    public function getAddress()
    {
        return $this->hasOne(WorksheetsamplesAddresses::className(), ['id' => 'address_id']);
    }
}