<?php

namespace app\models;

use Dadata\Client;
use dastanaron\translit\Translit;
use yii\base\Model;

class WorksheetForm extends Model
{

    const CREATE_SCENARIO = 'create';

    const CREATE_DOCUMENT_SCENARIO = 'createDocument';

    const CREATE_ADDRESS_SCENARIO = 'createAddress';

    const UPDATE_SCENARIO = 'update';

    public $worksheetId;

    public $worksheetsample;

    public $userId;

    public $min_sum;

    public $max_sum;

    public $days;

    public $documents;

    public $questions;

    public $address;

    public function scenarios()
    {
        return [
            self::CREATE_SCENARIO => ['worksheetsample', 'userId', 'min_sum', 'max_sum', 'days', 'documents', 'questions'],
            self::CREATE_DOCUMENT_SCENARIO => ['worksheetId', 'documents'],
            self::CREATE_ADDRESS_SCENARIO => ['worksheetId', 'address'],
        ];
    }

    public function rules()
    {
        return [
            ['documents', 'safe'],
            [['worksheetsample', 'userId', 'questions'], 'required', 'on' => self::CREATE_SCENARIO],
            [['worksheetId', 'documents'], 'required', 'on' => self::CREATE_DOCUMENT_SCENARIO],
            [['worksheetId', 'address'], 'required', 'on' => self::CREATE_ADDRESS_SCENARIO],
            ['worksheetsample', 'exist', 'targetClass' => Worksheetsample::className(), 'targetAttribute' => 'country_id', 'on' => [self::CREATE_SCENARIO]],
            ['min_sum', 'default', 'value' => 0, 'on' => self::CREATE_SCENARIO],
            ['max_sum', 'default', 'value' => 1000000, 'on' => self::CREATE_SCENARIO],
            ['days', 'default', 'value' => 30, 'on' => self::CREATE_SCENARIO],
            ['documents', 'validateDocument', 'on' => self::CREATE_DOCUMENT_SCENARIO],
            ['questions', 'validateQuestions', 'on' => self::CREATE_SCENARIO],
            ['address', 'validateAddress', 'on' => self::CREATE_ADDRESS_SCENARIO],
        ];
    }

    /**
     * Сохранение заявки
     *
     * @return bool
     */
    public function save()
    {
        if ($this->validate() === false) {
            return false;
        }

        $user = User::findOne($this->userId);
        $worksheetsample = Worksheetsample::findOne(['country_id' => $this->worksheetsample]);

        $worksheet = new Worksheets();
        $worksheet->user_id = $this->userId;
        $worksheet->worksheetsample_id = $worksheetsample->id;
        $worksheet->status_id = 1;
        $worksheet->min_sum = $this->min_sum;
        $worksheet->max_sum = $this->max_sum;
        $worksheet->days = $this->days;
        if ($worksheet->save() === false) {
            $worksheetErrors = $worksheet->getErrors();
            foreach ($worksheetErrors as $attribute => $errors) {
                foreach ($errors as $error) {
                    $this->addError($attribute, $error);
                }
            }
            return false;
        } else {
            $this->worksheetId = $worksheet->id;
            if (!empty($this->questions)) {
                foreach ($this->questions as $name => $value) {
                    $worksheetQuestion = new WorksheetsQuestions();
                    $worksheetQuestion->worksheet_id = $worksheet->id;
                    $worksheetQuestion->question_id = $worksheetsample->questions[$name]->id;
                    $worksheetQuestion->value = $this->questions[$name];
                    if ($worksheetQuestion->save() === false) {
                        $worksheetQuestionErrors = $worksheetQuestion->getErrors();
                        foreach ($worksheetQuestionErrors as $attribute => $errors) {
                            foreach ($errors as $error) {
                                $this->addError($attribute, $error);
                            }
                        }
                        return false;
                    }
                    /*
                     * Обновляем связанные с вопросом данные анкеты пользователя
                     */
                    if (!empty($worksheetsample->questions[$name]->user_field)){
                        $userField = $worksheetsample->questions[$name]->user_field;
                        $value = $userField == 'birthday' ? strtotime($this->questions[$name]) : $this->questions[$name];
                        $user->data->{$userField} = $value;
                        $user->data->save();
                    }
                }
            }
        }

        return true;
    }

    /**
     * Добавление документов к заявке
     *
     * @return bool
     */
    public function addDocuments()
    {
        if ($this->validate() === false) {
            return false;
        }

        $worksheet = Worksheets::findOne($this->worksheetId);
        if (!empty($this->documents)) {
            $documents = $worksheet->sample->documents;
            foreach ($this->documents as $docSymbId => $documentFields) {
                if (!empty($documentFields)) {
                    foreach ($documentFields as $fieldSymbId => $value) {
                        $worksheetDocument = new WorksheetsDocuments();
                        $worksheetDocument->worksheet_id = $this->worksheetId;
                        $worksheetDocument->doc_id = $documents[$docSymbId]->id;
                        $worksheetDocument->field_id = $documents[$docSymbId]->fields[$fieldSymbId]->id;
                        $worksheetDocument->value = $value;

                        if (!$worksheetDocument->save()) {
                            return false;
                        }
                    }
                }
            }
        }

        return true;
    }

    public function saveAddress()
    {
        if ($this->validate() === false) {
            return false;
        }

        $worksheet = Worksheets::findOne($this->worksheetId);
        if (!empty($this->address)) {
            $addresses = $worksheet->sample->address;
            foreach ($this->address as $addressSymbId => $addressValue) {
                $worksheetAddress = new WorksheetsAddresses();
                $worksheetAddress->worksheet_id = $this->worksheetId;
                $worksheetAddress->address_id = $addresses[$addressSymbId]->id;
                $worksheetAddress->value = $addressValue;

                if (!$worksheetAddress->save()) {
                    return false;
                }
            }
        }

        return true;
    }

    public function response()
    {
        return Worksheets::findOne($this->worksheetId);
    }

    public function validateDocument($attribute, $value)
    {
        if ($this->hasErrors() === false) {
            $worksheet = Worksheets::findOne($this->worksheetId);

            foreach ($this->documents as $docSymbId => $docFields) {
                if (WorksheetsDocuments::find()->where(['doc_id' => $worksheet->sample->documents[$docSymbId]->id, 'worksheet_id' => $this->worksheetId])->exists()) {
                    $this->addError($attribute, 'Document already added');
                    continue;
                }

                if (!isset($worksheet->sample->documents[$docSymbId])) {
                    $this->addError($attribute, 'Incorrect document type');
                    continue;
                }

                $russianPassport = [];
                /*
                 * Обход для проверки всех полей документа
                 */
                foreach ($worksheet->sample->documents[$docSymbId]->fields as $field) {
                    if (!isset($this->documents[$docSymbId][$field->symb_id])) {
                        $this->addError($field->symb_id, 'Empty value ' . $field->label . ' in ' . $worksheet->sample->documents[$docSymbId]->name);
                        continue;
                    }

                    if (!preg_match($field->regexp_rule, $this->documents[$docSymbId][$field->symb_id], $match)) {
                        $this->addError($field->symb_id, 'Incorrect value ' . $field->label);
                        continue;
                    }

                    if (in_array($field->symb_id, ['series', 'number'])){
                        $russianPassport[] = $this->documents[$docSymbId][$field->symb_id];
                    }
                }

                if (!empty($russianPassport)){
                    $daDataClient = new \Dadata\Client(new \GuzzleHttp\Client(), [
                        'token' => \Yii::$app->params['dadataSecretKey'],
                        'secret' => \Yii::$app->params['dadataSecretKeyStandard'],
                    ]);
                    $cleanPassport = $daDataClient->cleanPassport(implode(' ', $russianPassport));
                    if($cleanPassport->qc != 0){
                        $this->addError('series', 'Incorrect value series');
                        $this->addError('number', 'Incorrect value number');
                    }
                }
            }
        }
    }

    /**
     * Валидация массива вопросов с ответами
     *
     * @param $attribute
     * @param $value
     */
    public function validateQuestions($attribute, $value)
    {
        if ($this->hasErrors() === false) {
            $translit = new Translit();
            $worksheetsample = Worksheetsample::findOne(['country_id' => $this->worksheetsample]);

            $fullName = [];

            foreach ($worksheetsample->questions as $name => $question) {
                $this->questions[$name] = trim($this->questions[$name]);

                if (in_array($name, ['LASTNAME', 'FIRSTNAME', 'MIDDLENAME'])){
                    $this->questions[$name] = $translit->translit($this->questions[$name], false, 'en-ru');
                    $fullName[] = $this->questions[$name];
                }

                if (!empty($question->regexp_rule) && !preg_match($question->regexp_rule, $this->questions[$name], $match)) {
                    $this->addError($name, 'Incorrect value ' . $question->label);
                }
            }

            if (!empty($fullName)){
                $daDataClient = new \Dadata\Client(new \GuzzleHttp\Client(), [
                    'token' => \Yii::$app->params['dadataSecretKey'],
                    'secret' => \Yii::$app->params['dadataSecretKeyStandard'],
                ]);
                $cleanPassport = $daDataClient->cleanName(implode(' ', $fullName));
                if($cleanPassport->qc > 0){
                    $this->addError('LASTNAME', 'Incorrect value LASTNAME');
                    $this->addError('FIRSTNAME', 'Incorrect value FIRSTNAME');
                    $this->addError('MIDDLENAME', 'Incorrect value MIDDLENAME');
                }
            }
        }
    }

    /**
     * @param $attribute
     * @param $value
     */
    public function validateAddress($attribute, $value)
    {
        if ($this->hasErrors() === false) {
            $daDataClient = new \Dadata\Client(new \GuzzleHttp\Client(), [
                'token' => \Yii::$app->params['dadataSecretKey'],
                'secret' => \Yii::$app->params['dadataSecretKeyStandard'],
            ]);
            $worksheetsample = Worksheetsample::findOne(['country_id' => $this->worksheetsample]);

            foreach ($worksheetsample->address as $name => $address) {
                //Если необязательное и не заполнено, не проходим проверки
                if ($address->required == false && empty($this->address[$name])){
                    unset($this->address[$name]);
                    continue;
                }

                if (empty($this->address[$name])){
                    $this->addError($name, 'Required field ' . $address->label);
                }

                if (in_array($name, ['REGION', 'CITY', 'STREET']) && $daDataClient->cleanAddress($this->address[$name])->qc > 1){
                    $this->addError($name, 'Incorrect value ' . $address->label);
                    continue;
                }

                if (isset($this->address[$name]) && !empty($address->regexp_rule) && !preg_match($address->regexp_rule, $this->address[$name], $match)) {
                    $this->addError($name, 'Incorrect value ' . $address->label);
                }
            }

            /*$response = $daDataClient->cleanAddress(implode(' ', $this->address));
            if (empty($response->result)){
                $this->addError($attribute, 'Maybe incorrect value');
            }*/
        }
    }

    /*public function validateDocuments($attribute, $value)
    {
        if ($this->hasErrors() === false){
            $worksheet = Worksheets::findOne($this->worksheetId);

            foreach ($worksheet->sample->documents as $worksheetsampleDocument){
                if (!isset($this->documents[$worksheetsampleDocument->symb_id])){
                    $this->addError($attribute, 'Document ' . $worksheetsampleDocument->name . ' is empty');
                    continue;
                }

                foreach ($worksheetsampleDocument->fields as $field){
                    if (!isset($this->documents[$worksheetsampleDocument->symb_id][$field->symb_id])){
                        $this->addError($attribute, 'Empty value ' . $field->label . ' in ' . $worksheetsampleDocument->name);
                        continue;
                    }

                    if (!preg_match($field->regexp_rule, $this->documents[$worksheetsampleDocument->symb_id][$field->symb_id], $match)){
                        $this->addError($attribute, 'Incorrect value ' . $field->label);
                    }
                }
            }
        }
    }*/
}