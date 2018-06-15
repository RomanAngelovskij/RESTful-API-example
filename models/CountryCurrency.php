<?php
namespace app\models;

use yii\db\ActiveRecord;

class CountryCurrency extends ActiveRecord{

    public function rules()
    {
        return [
            [['country_id', 'currency_id'], 'required'],
            ['country_id', 'unique'],
            ['country_id', 'exist', 'targetClass' => Countries::className(), 'targetAttribute' => 'id'],
            ['currency_id', 'exist', 'targetClass' => Currency::className(), 'targetAttribute' => 'id'],
        ];
    }
}