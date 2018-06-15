<?php
namespace app\core\exceptions;
use app\models\WorksheetForm;

class WorksheetValidationException extends ValidationException{

    public function __construct(WorksheetForm $model, \Exception $previous = null)
    {
        $this->_model = $model;
        $this->processError();
        parent::__construct($this->_message, $this->_code, $previous);
    }

    /**
     * Обработка ошибок модели для установки свойств $_message и $_code
     */
    /*public function processError()
    {
        $this->_message = 'Unknown error';
        $this->_code = 0;
        $modelErrors = $this->_model->getErrors();

        if (!empty($modelErrors)) {
            reset($modelErrors);
            $firstFailedAttributeName = key($modelErrors);
            $this->_message = $modelErrors[$firstFailedAttributeName][0];
        }
    }*/
}