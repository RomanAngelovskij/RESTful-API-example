<?php
namespace app\models;

use app\core\exceptions\Exception;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Class Worksheets
 * @package app\models
 *
 * @property int $user_id
 * @property int $worksheetsample_id
 * @property int $status_id
 * @property int $min_sum
 * @property int $max_sum
 * @property int $days
 */
class Worksheets extends ActiveRecord{

    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

    public function rules()
    {
        return [
            ['user_id', 'exist', 'targetClass' => User::className(), 'targetAttribute' => 'id'],
            ['status_id', 'exist', 'targetClass' => WorksheetsStatuses::className(), 'targetAttribute' => 'id'],
            ['status_id', 'default', 'value' => 1],
            ['min_sum', 'default', 'value' => 0],
            ['max_sum', 'default', 'value' => 1000000],
            ['days', 'default', 'value' => 30],
            ['worksheetsample_id', 'exist', 'targetClass' => Worksheetsample::className(), 'targetAttribute' => 'id'],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'status' => 'statusSymbId',
            'user_id',
            'min_sum', 'max_sum', 'days',
            'date' => 'created_at'];
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        if ($insert === true){
            $mfos = Mfo::find()
                ->where(['country_id' => $this->user->data->country])
                ->all();
            foreach ($mfos as $mfo){
                $proposal = new Proposals();
                $proposal->worksheet_id = $this->id;
                $proposal->mfo_id = $mfo->id;
                $proposal->save();
            }
        }
    }

    public function getSample()
    {
        return $this->hasOne(Worksheetsample::className(), ['id' => 'worksheetsample_id']);
    }

    public function getDocuments()
    {
        return $this->hasMany(WorksheetsDocuments::className(), ['worksheet_id' => 'id']);
    }

    public function getStatus()
    {
        return $this->hasOne(WorksheetsStatuses::className(), ['id' => 'status_id']);
    }

    public function getStatusSymbId()
    {
        return $this->status->symb_id;
    }

    public function getProposals()
    {
        return $this->hasMany(Proposals::className(), ['worksheet_id' => 'id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }
}