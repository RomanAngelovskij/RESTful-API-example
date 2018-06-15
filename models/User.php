<?php
namespace app\models;

use app\core\utils\SmsSender;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
use paragraph1\phpFCM\Client;
use paragraph1\phpFCM\Message;
use paragraph1\phpFCM\Notification;
use paragraph1\phpFCM\Recipient\Device;
use VK\Client\VKApiClient;
use VK\OAuth\VKOAuth;
use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\filters\auth\HttpBearerAuth;
use yii\web\BadRequestHttpException;
use yii\web\IdentityInterface;

/**
 * User model
 *
 * @property integer $id
 * @property string $username
 * @property string $password_hash
 * @property string $password_reset_token
 * @property string $email
 * @property string $auth_key
 * @property integer $status
 * @property integer $created_at
 * @property integer $updated_at
 * @property string $password write-only password
 * @property string $activate_key
 */
class User extends ActiveRecord implements IdentityInterface
{
    const STATUS_DELETED = 0;
    const STATUS_ACTIVE = 10;


    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%user}}';
    }

    public function fields()
    {
        return [
            'id', 'email', 'phone', 'go_id', 'od_id', 'vk_id', 'fb_id'
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['username', 'default', 'value' => function(){
                return time() . mt_rand(10, 99);
            }],
            ['status', 'default', 'value' => self::STATUS_DELETED],
            ['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_DELETED]],
            ['username', 'unique', 'message' => Yii::t('errors', 'This username already used')],
            ['email', 'email'],
            ['phone', 'unique', 'skipOnEmpty' => true],
            ['fb_id', 'unique', 'skipOnEmpty' => true],
            ['vk_id', 'unique', 'skipOnEmpty' => true],
            ['od_id', 'unique', 'skipOnEmpty' => true],
            ['go_id', 'unique', 'skipOnEmpty' => true],
            ['register_source', 'default', 'value' => 'Android'],
        ];
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)){
            $loginFields = ['phone', 'email', 'fb_id', 'vk_id', 'od_id', 'go_id'];
            foreach ($loginFields as $field){
                if (isset($this->$field) && !empty($this->$field)){
                    return true;
                }
            }
            $this->addError('username', 'Empty login');
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        $log = new AuthLog();
        $log->token_type = $type;
        $log->ip = Yii::$app->request->userIP;
        $log->token = $token;
        $log->save();

        return $type === HttpBearerAuth::className() ? static::findOne(['access_token' => $token]) : static::authSocialToken($token, $type);
    }

    public static function authSocialToken($token, $type)
    {
        switch ($type){
            case 'fb':
                return static::authFacebook($token);
                break;
            case 'vk':
                return static::authVk($token);
                break;
            case 'tg':
                return static::authTg($token);
                break;
            case 'go':
                return static::authGoogle($token);
                break;
            case 'od':
                return static::authOdnoklassniki($token);
                break;
            default:
                return null;
        }
    }

    /**
     * Авторизация по токену Facebook
     * @param $token
     * @return null|static
     */
    public static function authFacebook($token)
    {
        $fb = new Facebook([
            'app_id' => Yii::$app->params['facebookAppId'],
            'app_secret' => Yii::$app->params['facebookSecret']
        ]);

        try {
            $response = $fb->get('/me?fields=id,name', $token);
        } catch(FacebookResponseException $e) {
            return null;
            echo 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch(FacebookSDKException $e) {
            return null;
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }

        $user = $response->getGraphUser();
        return static::findOne(['fb_id' => $user->getId()]);
    }

    /**
     * Авторизация по токену ВКонтакте
     * @param $token
     * @return null|static
     */
    public static function authVk($token)
    {
        try {
            if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
                $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
            }
            $vkAuth = json_decode(file_get_contents('https://api.vk.com/oauth/access_token?v=5.21&client_id=' . Yii::$app->params['vkAppId'] . '&client_secret=' . Yii::$app->params['vkSecretKey'] . '&grant_type=client_credentials'), true);
            if (!isset($vkAuth['access_token'])){
                return null;
            }

            $vk = new VKApiClient();
            $vkResponse = $vk->secure()->checkToken($vkAuth['access_token'], [
                'client_secret' => Yii::$app->params['vkSecretKey'],
                'token' => $token,
                'ip' => Yii::$app->request->userIP
            ]);

            if (isset($vkResponse['success']) && $vkResponse['success'] == 1) {
                return static::findOne(['vk_id' => $vkResponse['user_id']]);
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Авторизация через Телеграм. Основана на получение номера телефона и сравнении IP откуда пришел запрос,
     * со списком доверенных IP
     *
     * @param $telegramId
     * @return null|static
     */
    public static function authTg($telegramId)
    {
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }

        if (!in_array(Yii::$app->request->userIP, Yii::$app->params['tgBotServerIP'])){
            return null;
        }

        return static::findOne(['phone' => (int)$telegramId]);
    }

    /**
     * Авторизация через Google. Приложение присылает token_id (не access_token), после декодирования которого
     * id юзера хранится в ключе sub
     *
     * @param $token
     * @return null|static
     */
    public static function authGoogle($token)
    {
        $googleClient = new \Google_Client([
            'client_id' => Yii::$app->params['googleClientId'],
            'client_secret' => Yii::$app->params['googleSecret'],
            'access_type' => 'offline',
        ]);
        $googleClient->setScopes(['https://www.googleapis.com/auth/userinfo.profile', 'profile']);
        $result = $googleClient
            ->verifyIdToken($token);

        if (isset($result['sub'])){
            return static::findOne(['go_id' => $result['sub']]);
        }

        return null;
    }

    public static function authOdnoklassniki($token)
    {
        $secretKey = md5($token . Yii::$app->params['okSecret']);
        $sig = md5('application_key=' . Yii::$app->params['okPublicKey'] . 'format=jsonmethod=users.getLoggedInUser' . $secretKey);
        $endpoint = 'https://api.ok.ru/fb.do?application_key=' . Yii::$app->params['okPublicKey'] . '&format=json&method=users.getLoggedInUser&sig=' . $sig . '&access_token=' . $token;
        $result = json_decode(file_get_contents($endpoint), true);

        return static::findOne(['od_id' => $result]);
    }

    /**
     * Finds user by username
     *
     * @param string $username
     * @return static|null
     */
    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Finds user by password reset token
     *
     * @param string $token password reset token
     * @return static|null
     */
    public static function findByPasswordResetToken($token)
    {
        if (!static::isPasswordResetTokenValid($token)) {
            return null;
        }

        return static::findOne([
            'password_reset_token' => $token,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Finds out if password reset token is valid
     *
     * @param string $token password reset token
     * @return boolean
     */
    public static function isPasswordResetTokenValid($token)
    {
        if (empty($token)) {
            return false;
        }

        $timestamp = (int) substr($token, strrpos($token, '_') + 1);
        $expire = Yii::$app->params['user.passwordResetTokenExpire'];
        return $timestamp + $expire >= time();
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->getPrimaryKey();
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return boolean if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return md5($password . Yii::$app->params['salt']) == $this->password_hash;
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password_hash = md5($password . Yii::$app->params['salt']);
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken()
    {
        $this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Removes password reset token
     */
    public function removePasswordResetToken()
    {
        $this->password_reset_token = null;
    }

    public function register()
    {
        $activateKey = mt_rand(1000, 9999);
        $password = mt_rand(100000, 999999);
        $this->username = Yii::$app->request->post('phone_country_code') . Yii::$app->request->post('phone');
        $this->email = Yii::$app->request->post('email');
        $this->phone = Yii::$app->request->post('phone');
        $this->activate_key = $activateKey;

        $this->password_hash = md5($password . Yii::$app->params['salt']);

        if ($this->save() === false){
            return false;
        }

        $userData = new UserData();
        $userData->user_id = $this->id;
        $userData->first_name = Yii::$app->request->post('first_name');
        $userData->second_name = Yii::$app->request->post('second_name');
        $userData->last_name = Yii::$app->request->post('last_name');
        if ($userData->save() === false){
            return false;
        }

        try{
            $sms = new SmsSender();
            if ($sms->send($this->username, '{%code%}', ['code' => $activateKey]) === false){
                //TODO: продумать логику обработки ошибки отправки смс
            }
        } catch (\Exception $e) {
            //TODO: продумать логику обработки ошибки отправки смс
            throw new BadRequestHttpException();
        }

        return true;
    }

    public function getData()
    {
        return $this->hasOne(UserData::className(), ['user_id' => 'id']);
    }

    /**
     * Отправка Push-уведомления
     *
     * @param $message
     * @return bool|mixed
     */
    public function sendPush($message, $title = 'Finanso')
    {
        if ($this->register_source == 'Android'){
            return $this->_sendAndroidPush($message, $title);
        }

        if ($this->register_source == 'Telegram'){
            return $this->_sendTelegramPush($message, $title);
        }

        return false;
    }

    private function _sendAndroidPush($message, $title)
    {
        $device = UsersDevices::findOne(['user_id' => $this->id]);

        if (!empty($device)) {
            $client = new Client();
            $client->setApiKey('AIzaSyC1-v9c2aKYJrUw_bLcJt9KA3ngLtk8q8c');
            $client->injectHttpClient(new \GuzzleHttp\Client());

            $note = new Notification($title, $message);

            $message = new Message();
            $message->addRecipient(new Device($device->device_id));
            $message->setNotification($note);

            $response = $client->send($message);
            return $response->getStatusCode() == 200;
        }

        return false;
    }

    private function _sendTelegramPush($message, $title)
    {
        $data = json_encode([
            'phone' => $this->phone,
            'text' => $message,
        ]);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'http://bottelegram.lm23.net/telegram/api/messages');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data)
            ]
        );

        $response = curl_exec($curl);
        $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return $responseCode == 201;
    }
}
