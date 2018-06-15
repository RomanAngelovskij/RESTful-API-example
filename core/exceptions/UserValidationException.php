<?php
namespace app\core\exceptions;

class UserValidationException extends ValidationException{

    public function __construct($model, \Exception $previous = null)
    {
        $this->_model = $model;
        $this->processError();
        parent::__construct($this->_message, $this->_code, $previous);
    }
}