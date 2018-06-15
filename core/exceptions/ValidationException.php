<?php
namespace app\core\exceptions;

class ValidationException extends Exception {

    protected $_model;

    protected $_message;

    protected $_code;

    public function __construct($message = null, $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Обработка ошибок модели для установки свойств $_message и $_code
     */
    public function processError()
    {
        $this->_message = 'Ошибка валидации';
        $this->_code = 10;
        $modelErrors = $this->_model->getErrors();

        if (!empty($modelErrors)) {
            foreach ($modelErrors as $attribute => $errors){
                $this->addFieldError($attribute, $errors[0], 10);
            }
        }
    }
}