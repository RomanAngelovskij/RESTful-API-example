<?php
namespace app\models;

use yii\base\ModelEvent;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class Proposals extends ActiveRecord{

    const EVENT_CHANGE_STATUS = 'changeStatus';

    public function init()
    {
        parent::init();
        $this->on(Proposals::EVENT_CHANGE_STATUS, ['app\core\events\EProposal', 'onChangeStatus']);
    }

    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

    public function rules()
    {
        return [
            [['status_id'], 'safe'],
            ['status_id', 'default', 'value' => ProposalsStatuses::PENDING_STATUS_ID],
            [['worksheet_id', 'mfo_id', 'status_id'], 'required'],
            ['worksheet_id', 'exist', 'targetClass' => Worksheets::className(), 'targetAttribute' => 'id'],
            ['mfo_id', 'exist', 'targetClass' => Mfo::className(), 'targetAttribute' => 'id'],
            ['status_id', 'exist', 'targetClass' => ProposalsStatuses::className(), 'targetAttribute' => 'id'],
        ];
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        /*
         * Если изменился статус
         */
        if ($this->isNewRecord === false && isset($changedAttributes['status_id'])){
            $event = new ModelEvent();
            $this->trigger(self::EVENT_CHANGE_STATUS, $event);
        }
    }

    public function getMfo()
    {
        return $this->hasOne(Mfo::className(), ['id' => 'mfo_id']);
    }

    public function getWorksheet()
    {
        return $this->hasOne(Worksheets::className(), ['id' => 'worksheet_id']);
    }

    public function getStatus()
    {
        return $this->hasOne(ProposalsStatuses::className(), ['id' => 'status_id']);
    }
}