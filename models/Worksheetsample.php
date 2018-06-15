<?php
namespace app\models;

use yii\db\ActiveRecord;

class Worksheetsample extends ActiveRecord{

    public static function tableName()
    {
        return 'worksheetsamples';
    }

    public function getDocuments()
    {
        return $this->hasMany(Documents::className(), ['id' => 'doc_id'])
            ->viaTable('worksheetsamples_documents', ['worksheetsample_id' => 'id'])
            ->indexBy('symb_id');
    }

    public function getQuestions()
    {
        return $this->hasMany(WorksheetsamplesQuestions::className(), ['worksheetsample_id' => 'id'])
            ->indexBy('name');
    }

    public function getAddress()
    {
        return $this->hasMany(WorksheetsamplesAddresses::className(), ['worksheetsample_id' => 'id'])
            ->orderBy(['order_position' => SORT_ASC])
            ->indexBy('name');
    }
}