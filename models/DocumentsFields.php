<?php
namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class DocumentsFields extends ActiveRecord{

    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

    public function getDocument()
    {
        return $this->hasOne(Documents::className(), ['id' => 'doc_id']);
    }
}