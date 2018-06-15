<?php
namespace app\models;

use yii\db\ActiveRecord;

class Countries extends ActiveRecord{

    public function rules()
    {
        return [
            [['id', 'name'], 'required'],
            ['id', 'unique'],
            ['name', 'unique'],
            ['id', 'match', 'pattern' => '/^[a-z]{2}$/'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => '2-буквенный идентификатор',
            'name' => 'Название',
            'currency' => 'Валюта',
        ];
    }

    public function getCurrency()
    {
        return $this->hasOne(Currency::className(), ['id' => 'currency_id'])
            ->viaTable('country_currency', ['country_id' => 'id']);
    }
}