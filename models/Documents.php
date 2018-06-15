<?php
namespace app\models;

use yii\db\ActiveRecord;

class Documents extends ActiveRecord{

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCountry()
    {
        return $this->hasOne(Countries::className(), ['id' => 'country_id']);
    }

    public function getFields()
    {
        return $this->hasMany(DocumentsFields::className(), ['doc_id' => 'id'])
            ->indexBy('symb_id');
    }
}