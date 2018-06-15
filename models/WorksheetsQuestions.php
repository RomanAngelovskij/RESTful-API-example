<?php
namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class WorksheetsQuestions extends ActiveRecord{

    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

    public function rules()
    {
        return [
            [['worksheet_id', 'question_id', 'value'], 'required'],
            ['worksheet_id', 'exist', 'targetClass' => Worksheets::className(), 'targetAttribute' => 'id'],
            ['question_id', 'exist', 'targetClass' => WorksheetsamplesQuestions::className(), 'targetAttribute' => 'id'],
        ];
    }

    public function getQuestion()
    {
        return $this->hasOne(WorksheetsamplesQuestions::className(), ['id' => 'question_id']);
    }
}