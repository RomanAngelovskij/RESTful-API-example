<?php
namespace app\models;

use dastanaron\translit\Translit;
use yii\db\ActiveRecord;


/**
 * Class UserData
 * @package app\models
 *
 * @property string $phone
 * @property string $first_name
 * @property string $second_name
 * @property string $last_name
 */
class UserData extends ActiveRecord{

    public static function primaryKey()
    {
        return ['user_id'];
    }

    public function rules()
    {
        return [
            [['first_name', 'second_name', 'last_name'], 'safe'],
            [['country'], 'required'],
            ['user_id', 'exist', 'targetClass' => User::className(), 'targetAttribute' => 'id'],
            ['birthday', 'integer'],
            ['gender', 'in', 'range' => ['m', 'f']],
            ['first_name', 'filter', 'filter' => function($value){
                //TODO:: Only for Russia
                $translit = new Translit();
                return $translit->translit($value, false, 'en-ru');
            }],
            ['second_name', 'filter', 'filter' => function($value){
                //TODO:: Only for Russia
                $translit = new Translit();
                return $translit->translit($value, false, 'en-ru');
            }],
            ['last_name', 'filter', 'filter' => function($value){
                //TODO:: Only for Russia
                $translit = new Translit();
                return $translit->translit($value, false, 'en-ru');
            }],
            ['last_name', 'validateFIO'],
        ];
    }

    public function validateFIO($attribute, $value)
    {
        $fullName = $this->last_name . ' ' . $this->first_name . ' ' . $this->second_name;
        $daDataClient = new \Dadata\Client(new \GuzzleHttp\Client(), [
            'token' => \Yii::$app->params['dadataSecretKey'],
            'secret' => \Yii::$app->params['dadataSecretKeyStandard'],
        ]);
        $cleanPassport = $daDataClient->cleanName($fullName);
        if($cleanPassport->qc > 0){
            $this->addError($attribute, 'Incorrect value');
        }
    }
}