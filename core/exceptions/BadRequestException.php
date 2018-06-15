<?php
namespace app\core\exceptions;

use yii\web\HttpException;

class BadRequestException extends HttpException{

    public function __construct($message = null, $code = 0, \Exception $previous = null)
    {
        parent::__construct(400, $message, $code, $previous);
    }
}