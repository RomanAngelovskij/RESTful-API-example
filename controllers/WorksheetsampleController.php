<?php
namespace app\controllers;

use app\models\Worksheetsample;
use Yii;
use yii\web\NotFoundHttpException;

class WorksheetsampleController extends APIBaseController{

    public $modelClass = 'app\models\Worksheetsample';

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['index'];
        return $behaviors;
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index']);
        return $actions;
    }

    public function actionIndex()
    {
        $result = [];
        $model = Worksheetsample::find()->all();
        foreach ($model as $worksheetsample){
            $result[$worksheetsample->country_id] = [
                'name' => $worksheetsample->name
            ];
        }

        return $result;
    }

    /**
     * Документы связанные с типом заявки
     *
     * @param $type
     * @return mixed
     * @throws NotFoundHttpException
     */
    public function actionDocuments($type)
    {
        $result = [];
        $typeModel = Worksheetsample::findOne(['country_id' => $type]);
        if (empty($typeModel) || empty($typeModel->documents)){
            throw new NotFoundHttpException('Тип заявки не найден');
        }

        foreach ($typeModel->documents as $document){
            $result[$document->symb_id] = [];
            foreach ($document->fields as $field){
                preg_match('|^/(.*)/|', $field->regexp_rule, $regexp);
                $result[$document->symb_id][] = [
                    'name' => $field->symb_id,
                    'min' => $field->min,
                    'max' => $field->max,
                    'maxlength' => $field->maxlength,
                    'type' => $field->type,
                    'input_mask' => $field->input_mask,
                    'regexp' => isset($regexp[1]) ? $regexp[1] : '',
                    'label' => $field->label,
                    'description' => $field->description,
                    'sample' => $field->sample,
                    'placeholder' => $field->placeholder,
                ];
            }
        }

        return $result;
    }

    /**
     * @param $type
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionQuestions($type)
    {
        $result = [];
        $typeModel = Worksheetsample::findOne(['country_id' => $type]);
        if (empty($typeModel) || empty($typeModel->questions)){
            throw new NotFoundHttpException('Тип заявки не найден');
        }

        foreach ($typeModel->questions as $question){
            //Костыль для пропуска вопроса о номере телефона в телеграме
            if ($this->client() == 'telegram' && $question->name == 'LOGIN'){
                continue;
            }
            preg_match('|^/(.*)/|', $question->regexp_rule, $regexp);
            $result[] = [
                'name' => $question->name,
                'min' => $question->min,
                'max' => $question->max,
                'maxlength' => $question->maxlength,
                'type' => $question->type,
                'input_mask' => $question->input_mask,
                'regexp' => isset($regexp[1]) ? $regexp[1] : '',
                'label' => $question->label,
                'description' => $question->description,
                'sample' => $question->sample,
                'placeholder' => $question->placeholder
            ];
        }

        return $result;
    }

    public function actionAddress($type)
    {
        $result = [];
        $typeModel = Worksheetsample::findOne(['country_id' => $type]);
        if (empty($typeModel) || empty($typeModel->questions)){
            throw new NotFoundHttpException('Тип заявки не найден');
        }

        foreach ($typeModel->address as $address){
            preg_match('|^/(.*)/|', $address->regexp_rule, $regexp);
            $result[] = [
                'name' => $address->name,
                'min' => $address->min,
                'max' => $address->max,
                //'maxlength' => $address->maxlength,
                'type' => $address->type,
                'input_mask' => $address->input_mask,
                'regexp' => isset($regexp[1]) ? $regexp[1] : '',
                'label' => $address->label,
                'description' => $address->description,
                'sample' => $address->sample,
                'placeholder' => $address->placeholder
            ];
        }

        return $result;
    }
}