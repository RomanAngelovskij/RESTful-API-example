<?php
namespace app\core\auth;

use yii\filters\auth\AuthMethod;

class HttpSocialAuth extends AuthMethod
{
    /**
     * @var string the HTTP authentication realm
     */
    public $realm = 'api';

    protected $_network;

    protected $_token;


    /**
     * @inheritdoc
     */
    public function authenticate($user, $request, $response)
    {
        $authTypeHeader = $request->getHeaders()->get('Token-type');
        $authTokenHeader = $request->getHeaders()->get('Token');
        if (($authTypeHeader !== null && preg_match('/^([a-z]+)$/', $authTypeHeader, $matchesType))
            && ($authTokenHeader !== null && preg_match('/^(.*?)$/', $authTokenHeader, $matchesToken))
        ) {
            $identity = $user->loginByAccessToken($matchesToken[1], $matchesType[1]);
            if ($identity === null) {
                $this->handleFailure($response);
            }

            return $identity;
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function challenge($response)
    {
        $response->getHeaders()->set('WWW-Authenticate', "Bearer realm=\"{$this->realm}\"");
    }
}
