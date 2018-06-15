<?php
namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class WorksheetsDocuments extends ActiveRecord{

    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

    public function rules()
    {
        return [
            [['worksheet_id', 'doc_id', 'field_id', 'value'], 'required'],
            ['worksheet_id', 'exist', 'targetClass' => Worksheets::className(), 'targetAttribute' => 'id'],
            ['doc_id', 'exist', 'targetClass' => Documents::className(), 'targetAttribute' => 'id'],
            ['field_id', 'exist', 'targetClass' => DocumentsFields::className(), 'targetAttribute' => 'id'],
        ];
    }

    public function getDocument()
    {
        return $this->hasOne(Documents::className(), ['id' => 'doc_id']);
    }

    public function getFields()
    {
        return $this->hasMany(DocumentsFields::className(), ['doc_id' => 'id'])
            ->indexBy('symb_id');
    }

    public function getField()
    {
        return $this->hasOne(DocumentsFields::className(), ['id' => 'field_id']);
    }
}