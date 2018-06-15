<?php

namespace app\controllers;

use app\core\exceptions\BadRequestException;
use app\models\Documents;
use app\models\Worksheetsample;
use app\models\WorksheetsDocuments;
use Yii;
use app\core\exceptions\WorksheetValidationException;
use app\models\WorksheetForm;
use app\models\Worksheets;
use yii\web\NotFoundHttpException;

class WorksheetController extends APIBaseController
{

    public $modelClass = 'app\models\Worksheets';

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['create']);
        unset($actions['index']);
        unset($actions['view']);

        return $actions;
    }

    public function actionIndex()
    {
        $worksheets = Worksheets::findAll(['user_id' => \Yii::$app->user->id]);

        $result = ['count' => count($worksheets), 'items' => []];
        if (!empty($worksheets)) {
            foreach ($worksheets as $worksheet) {
                $result['items'][] = $worksheet;
            }
        }

        return $result;
    }

    /**
     * GET /worksheets/{id}
     *
     * @param $worksheetId
     * @return null|static
     * @throws NotFoundHttpException
     */
    public function actionView($worksheetId)
    {
        $worksheet = Worksheets::findOne(['id' => $worksheetId, 'user_id' => \Yii::$app->user->id]);
        if (empty($worksheet)){
            throw new NotFoundHttpException('Worksheet not found');
        }

        return $worksheet;
    }

    /**
     * POST. Создание заявки
     * {
     *      "worksheetsample": "ru",
     *      "min_sum": 20000,
     *      "max_sum": 50000,
     *      "days": 30,
     *      "documents": {
     *          "internal_passport": {
     *              "series": "09 04",
     *              "number": "453453"
     *          },
     *      }
     * }
     *
     * @return Worksheets
     * @throws WorksheetValidationException
     */
    public function actionCreate()
    {
        $model = new WorksheetForm(['scenario' => WorksheetForm::CREATE_SCENARIO]);
        $worksheetSample = Worksheetsample::findOne(['country_id' => Yii::$app->user->identity->data->country]);
        $model->worksheetsample = $worksheetSample->country_id;

        if ($model->load(Yii::$app->request->post(), '')) {
            foreach ($worksheetSample->questions as $name => $question) {
                $model->questions[$name] = Yii::$app->request->post($name);
            }
            $model->userId = Yii::$app->user->id;
            //Костыль для пропуска вопроса о номере телефона в телеграме
            if ($this->client() == 'telegram'){
                $model->questions['LOGIN'] = Yii::$app->user->identity->phone;
            }
            if ($model->save()) {
                return $model->response();
            } else {
                throw new WorksheetValidationException($model);
            }
        }
    }

    /**
     * GET /worksheets/{worksheetsId}/documents/{document}
     *
     * @param $worksheetId
     * @param $docSymbId
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionViewDocument($worksheetId, $docSymbId)
    {
        $model = Worksheets::findOne(['id' => $worksheetId, 'user_id' => \Yii::$app->user->id]);

        if (empty($model)) {
            throw new NotFoundHttpException('Worksheet not found');
        }

        $document = Documents::findOne(['symb_id' => $docSymbId, 'country_id' => $model->sample->country_id]);
        if (empty($document)) {
            throw new NotFoundHttpException('Documents not found');
        }

        $worksheetDocument = WorksheetsDocuments::findAll(['worksheet_id' => $worksheetId, 'doc_id' => $document->id]);
        if (empty($worksheetDocument)) {
            throw new NotFoundHttpException('Documents for worksheet not found');
        }

        $result = [];
        foreach ($worksheetDocument as $document) {
            $result[$document->field->symb_id] = $document->value;
        }
        return $result;
    }

    /**
     * POST /worksheets/{worksheetsId}/documents/{document}
     *
     * @param $worksheetId
     * @param $docSymbId
     * @return mixed
     * @throws BadRequestException
     * @throws NotFoundHttpException
     * @throws WorksheetValidationException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function actionCreateDocument($worksheetId, $docSymbId)
    {
        $model = Worksheets::findOne($worksheetId);

        $data = Yii::$app->request->post();
        if (empty($data)) {
            throw new BadRequestException('Empty parameters');
        }

        if (empty($model)) {
            throw new NotFoundHttpException('Worksheet not found');
        }

        if ($model->user_id !== Yii::$app->user->id) {
            throw new \yii\web\ForbiddenHttpException('Access forbidden.', 403);
        }

        $formModel = new WorksheetForm(['scenario' => WorksheetForm::CREATE_DOCUMENT_SCENARIO]);
        $formModel->worksheetId = $worksheetId;
        $formModel->documents = [
            $docSymbId => $data
        ];

        if ($formModel->addDocuments()) {
            return $formModel->documents[$docSymbId];
        } else {
            throw new WorksheetValidationException($formModel);
        }
    }

    /**
     * GET /worksheets/{worksheetsId}/documents
     *
     * @param $worksheetId
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionDocuments($worksheetId)
    {
        $model = Worksheets::findOne(['id' => $worksheetId, 'user_id' => \Yii::$app->user->id]);

        if (empty($model)) {
            throw new NotFoundHttpException('Worksheet not found');
        }

        if (empty($model->documents)) {
            throw new NotFoundHttpException('Documents for worksheet not found');
        }

        $result = [];
        $processedDocuments = [];
        foreach ($model->documents as $worksheetDocument) {
            //Если документ еще не обрабатывался и не добавлен
            if (!isset($processedDocuments[$worksheetDocument->id])) {
                $fields = $worksheetDocument->document->fields;
                if (!empty($fields)) {
                    $fieldsValues = [];
                    foreach ($fields as $field) {
                        $value = WorksheetsDocuments::findOne(['worksheet_id' => $worksheetId, 'field_id' => $field->id]);
                        $fieldsValues[$field->symb_id] = !empty($value) ? $value->value : null;
                    }
                }
                $result = array_merge(['type' => $worksheetDocument->document->symb_id], $fieldsValues);
            }
        }

        return $result;
    }

    public function actionCreateAddress($worksheetId)
    {
        $model = Worksheets::findOne($worksheetId);

        $data = Yii::$app->request->post();
        if (empty($data)) {
            throw new BadRequestException('Empty parameters');
        }

        if (empty($model)) {
            throw new NotFoundHttpException('Worksheet not found');
        }

        if ($model->user_id !== Yii::$app->user->id) {
            throw new \yii\web\ForbiddenHttpException('Access forbidden.', 403);
        }

        $model = new WorksheetForm(['scenario' => WorksheetForm::CREATE_ADDRESS_SCENARIO]);
        $worksheetSample = Worksheetsample::findOne(['country_id' => Yii::$app->user->identity->data->country]);
        $model->worksheetsample = $worksheetSample->country_id;
        $model->worksheetId = $worksheetId;
        $model->address = $data;

        $model->userId = Yii::$app->user->id;
        if ($model->saveAddress()) {
            return $data;
        } else {
            throw new WorksheetValidationException($model);
        }
    }

    public function actionProposals($worksheetId)
    {
        $worksheets = Worksheets::findOne(['id' => $worksheetId, 'user_id' => \Yii::$app->user->id]);
        if (empty($worksheets)) {
            throw new NotFoundHttpException('Worksheets not found');
        }

        $result = ['count' => count($worksheets), 'items' => []];
        if (!empty($worksheets->proposals)) {
            foreach ($worksheets->proposals as $proposal) {
                $result['items'][] = [
                    'id' => $proposal->id,
                    'mfo_id' => $proposal->mfo_id,
                    'amount' => $proposal->worksheet->max_sum,
                    'percent' => (float) $proposal->mfo->percent,
                    'date' => $proposal->created_at,
                    'currency' => 'RUR',
                    'status' => $proposal->status->symb_id,
                    'link' => $proposal->mfo->link,
                    'duration' => $proposal->worksheet->days,
                ];
            }
        }

        return $result;
    }
}