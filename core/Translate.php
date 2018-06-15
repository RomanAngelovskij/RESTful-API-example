<?php
namespace app\core;

class Translate{

    public static function t($category, $message, $params = [], $language = null)
    {
        if ($category == 'templates'){

        }

        return \Yii::t($category, $message, $params, $language);
    }
}