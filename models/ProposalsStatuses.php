<?php
namespace app\models;

use yii\db\ActiveRecord;

class ProposalsStatuses extends ActiveRecord{


    /**
     * На рассмотрении
     */
    const PENDING_STATUS_ID = 1;

    const ACCEPTED_STATUS_ID = 2;

    const DECLINE_STATUS_ID = 3;
}