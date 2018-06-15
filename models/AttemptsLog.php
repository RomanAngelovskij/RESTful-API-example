<?php
namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class AttemptsLog extends ActiveRecord{

    const TYPE_SEND_RESTORE_CODE = 'send-restore-code';

    const TYPE_RESTORE_PASSWORD = 'restore-password';

    const TYPE_EMAIL_LOGIN = 'email-login';

    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }
}