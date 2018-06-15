<?php
namespace app\core;

use app\core\exceptions\Exception;
use Yii;
use yii\web\Response;

class ApiErrorHandler extends \yii\web\ErrorHandler
{

    /**
     * @inheridoc
     */

    protected function renderException($exception)
    {
        if (Yii::$app->has('response')) {
            $response = Yii::$app->getResponse();
        } else {
            $response = new Response();
        }

        $response->data = $this->convertExceptionToArray($exception);
        $response->setStatusCode(isset($exception->statusCode) ? $exception->statusCode : 500);

        $response->send();
    }

    /**
     * @inheritdoc
     */

    protected function convertExceptionToArray($exception)
    {
        $response = [
            'errors'=>[
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ],
        ];

        if ($exception instanceof Exception){
            $fields = $exception->getFields();
            if (!empty($fields)) {
                $response['fields'] = [];
                foreach ($fields as $name => $fieldErrorData) {
                    $response['fields'][$name] = $fieldErrorData;
                }
            }
        }

        return $response;
    }
}