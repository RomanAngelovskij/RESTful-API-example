<?php
namespace app\models;

use yii\db\ActiveRecord;

class Currency extends ActiveRecord{

    public function rules()
    {
        return [
            [['name', 'symb_id'], 'required'],
            ['symb_id', 'unique'],
            ['name', 'unique'],
            ['symb_id', 'match', 'pattern' => '/^[A-Z]{3}$/'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'name' => 'Название',
            'symb_id' => 'Международное название',
            'countries' => 'Страны',
        ];
    }

    public function getCountries()
    {
        return $this->hasMany(Countries::className(), ['id' => 'country_id'])
            ->viaTable('country_currency', ['currency_id' => 'id']);
    }
}