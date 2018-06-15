<?php
namespace app\core\exceptions;

use yii\web\HttpException;

class Exception extends HttpException{

    protected $_fields;

    public function __construct($message = null, $code = 0, \Exception $previous = null)
    {
        parent::__construct(400, $message, $code, $previous);
    }

    public function addFieldError($fieldName, $message, $code = 10)
    {
        $this->_fields[$fieldName] = [
            'code' => $code,
            'message' => $message
        ];

        return $this;
    }


    public function getFields()
    {
        return $this->_fields;
    }
}