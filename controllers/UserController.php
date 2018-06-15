<?php

namespace app\controllers;

use app\core\exceptions\BadRequestException;
use app\core\exceptions\FeedbackException;
use app\core\exceptions\UserDeviceValidationException;
use app\core\exceptions\UserValidationException;
use app\models\AttemptsLog;
use app\models\Feedback;
use app\models\UserData;
use app\models\UsersDevices;
use paragraph1\phpFCM\Client;
use paragraph1\phpFCM\Message;
use paragraph1\phpFCM\Notification;
use paragraph1\phpFCM\Recipient\Device;
use VK\Client\VKApiClient;
use VK\OAuth\VKOAuth;
use Yii;
use app\models\User;
use yii\base\Exception;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;
use yii\web\UnauthorizedHttpException;

class UserController extends APIBaseController
{

    public $modelClass = 'app\models\User';

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['test', 'token', 'index', 'create', 'confirm-phone', 'restore', 'set-password', 'validate-code', 'autentication'];
        return $behaviors;
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index']);
        unset($actions['create']);
        unset($actions['update']);
        unset($actions['view']);
        return $actions;
    }

    /**
     * GET. Проверка существования юзера
     *
     * @return array
     * @throws BadRequestException
     * @throws NotFoundHttpException
     */
    public function actionIndex()
    {
        $filter = Yii::$app->request->get();
        $userModel = new User();
        $fields = $userModel->fields();

        if (!empty($filter)) {
            foreach ($filter as $key => $val) {
                if (!in_array($key, $fields)) {
                    unset($filter[$key]);
                }
            }
        }

        if (empty($filter)) {
            throw new BadRequestException('Empty parameters');
        }

        $user = User::findOne($filter);
        if (empty($user)) {
            //TODO: Вернуть 404, реализовать обработку на стороне клиента
            throw new NotFoundHttpException('User not found');
            //throw new UnauthorizedHttpException('User not found');
        }

        return ['id' => $user->id];
    }


    /**
     * POST. Добавление (регистрация)
     * {
     *   fb_id: string,
     *   vk_id: string,
     *   od_id: string,
     *   go_id: string,
     *   email: string,
     *   phone: bigint,
     *   profile
     *   {
     *     first_name: string
     *     second_name: string
     *     last_name: string
     *     birthday: int
     *     country: string (ru, kz, de...)
     *   }
     * }
     * @return array
     * @throws BadRequestException
     * @throws UserValidationException
     */
    public function actionCreate()
    {
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }

        $data = Yii::$app->request->post();
        if (empty($data)) {
            throw new BadRequestException('Empty parameters');
        }

        $model = new User();
        $model->register_source = in_array(Yii::$app->request->userIP, Yii::$app->params['tgBotServerIP']) ? 'Telegram' : 'Android';
        if ($model->load(Yii::$app->request->post(), '') && $model->save()) {
            $profileModel = new UserData();
            $profileModel->load(Yii::$app->request->post('profile'), '');
            $profileModel->user_id = $model->id;
            if ($profileModel->save() === false) {
                $model->delete();
                throw new UserValidationException($profileModel);
            }
            Yii::$app->response->statusCode = 201;
            $response = [];
            $fields = $model->fields();
            foreach ($fields as $field) {
                $response[$field] = $model->{$field};
            }
            $response['profile'] = $profileModel->attributes;
            return $response;
        } else {
            throw new UserValidationException($model);
        }
    }


    /**
     * GET /users/{id}
     * Получение данных по пользователю.
     *
     * @param $id
     * @return array
     * @throws \yii\web\ForbiddenHttpException
     */
    public function actionView($id)
    {
        $user = User::findOne($id);
        if ($user->id !== \Yii::$app->user->id) {
            throw new \yii\web\ForbiddenHttpException('Access forbidden.', 403);
        }

        return [
            'fb_id' => $user->fb_id,
            'vk_id' => $user->vk_id,
            'od_id' => $user->od_id,
            'go_id' => $user->go_id,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile' => [
                'first_name' => $user->data->first_name,
                'second_name' => $user->data->second_name,
                'last_name' => $user->data->last_name,
                'birthday' => $user->data->birthday,
                'country' => $user->data->country,
                'gender' => $user->data->gender,
            ]
        ];
    }

    /**
     * PUT /users/{id}
     * Редакирование пользователя.
     *
     * @param $id
     * @return array
     * @throws UserValidationException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function actionUpdate($id)
    {
        $model = User::findOne($id);
        if ($model->id !== \Yii::$app->user->id) {
            throw new \yii\web\ForbiddenHttpException('Access forbidden.', 403);
        }

        if ($model->load(Yii::$app->getRequest()->getBodyParams(), '') && $model->save()) {
            $profileModel = UserData::findOne(['user_id' => $id]);
            $profileModel->load(Yii::$app->request->post('profile'), '');
            if ($profileModel->save() === false) {
                throw new UserValidationException($profileModel);
            }
            Yii::$app->response->statusCode = 201;
            $response = [];
            $fields = $model->fields();
            foreach ($fields as $field) {
                $response[$field] = $model->{$field};
            }
            $response['profile'] = $profileModel->attributes;
            return $response;
        } else {
            throw new UserValidationException($model);
        }
    }

    /**
     * POST /users/{id}/restore
     * Отправка кода восстановления на почту.
     *
     * @param $id
     * @throws NotFoundHttpException
     * @throws ServerErrorHttpException
     */
    public function actionRestore($id)
    {
        $model = User::findOne($id);
        if (empty($model)) {
            throw new NotFoundHttpException('User not found');
        }

        //Сколько было попыток выслать код за последние 5 минут
        $attempts = AttemptsLog::find()
            ->where(['user_id' => $id, 'type' => AttemptsLog::TYPE_SEND_RESTORE_CODE])
            ->andWhere(['>=', 'created_at', time() - 5 * 60])
            ->count();

        if ($attempts <= 5) {
            $model->password_reset_token = mt_rand(1000, 9999);
            $model->password_reset_token_created_at = time();

            if ($model->save()) {
                switch (Yii::$app->request->post('type')) {
                    case 'email':
                        $sendResult = Yii::$app->mailer->compose('restore-password', ['code' => $model->password_reset_token])
                            ->setFrom('admin@mazilla.com.ru')
                            ->setTo($model->email)
                            ->setSubject('Восстановление пароля')
                            ->send();

                        if ($sendResult === false) {
                            throw new ServerErrorHttpException("Can't send e-mail", 500);
                        }
                        break;
                    case 'phone':
                        $message = 'Код восстановления ' . $model->password_reset_token;
                        $params = [
                            'login' => Yii::$app->params['smsLogin'],
                            'psw' => Yii::$app->params['smsPwd'],
                            'charset' => 'utf-8',
                            'phones' => $model->phone,
                            'mes' => $message
                        ];
                        $result = file_get_contents('https://smsc.ru/sys/send.php?' . http_build_query($params));
                        return $result;
                        break;
                    default:
                }
            }
            $attemptLog = new AttemptsLog();
            $attemptLog->user_id = $id;
            $attemptLog->type = AttemptsLog::TYPE_SEND_RESTORE_CODE;
            $attemptLog->save();

            Yii::$app->response->setStatusCode(201);
        } else {
            throw new ServerErrorHttpException('More than 5 attempts', 400);
        }
    }

    /**
     * @param $id
     * @throws BadRequestException
     * @throws Exception
     * @throws NotFoundHttpException
     */
    public function actionValidateCode($id)
    {
        $code = Yii::$app->request->get('code');
        //Сколько было попыток выслать код за последние 5 минут
        $attempts = AttemptsLog::find()
            ->where(['user_id' => $id, 'type' => AttemptsLog::TYPE_RESTORE_PASSWORD])
            ->andWhere(['>=', 'created_at', time() - 5 * 60])
            ->count();

        if ($attempts > 10) {
            throw new BadRequestException('More than 5 attempts', 400);
        }

        $attemptLog = new AttemptsLog();
        $attemptLog->user_id = $id;
        $attemptLog->type = AttemptsLog::TYPE_RESTORE_PASSWORD;
        $attemptLog->save();

        if (empty($code)) {
            throw new BadRequestException('Empty code');
        }

        $model = User::findOne(['id' => $id, 'password_reset_token' => $code]);
        if (empty($model)) {
            throw new NotFoundHttpException('Reset code not found');
        }

        if ((time() - $model->password_reset_token_created_at) > (60 * 60)) {
            throw new BadRequestException('Code has expired', 410);
        }
    }

    /**
     * PATCH /users/{id}/restore
     * Сеттинг нового пароля.
     *
     * @param $id
     * @throws BadRequestException
     * @throws Exception
     * @throws NotFoundHttpException
     */
    public function actionSetPassword($id)
    {
        $code = Yii::$app->request->get('code');
        $password = Yii::$app->request->post('password');

        //Сколько было попыток выслать код за последние 5 минут
        $attempts = AttemptsLog::find()
            ->where(['user_id' => $id, 'type' => AttemptsLog::TYPE_RESTORE_PASSWORD])
            ->andWhere(['>=', 'created_at', time() - 5 * 60])
            ->count();

        if ($attempts > 10) {
            throw new BadRequestException('More than 5 attempts', 400);
        }

        $attemptLog = new AttemptsLog();
        $attemptLog->user_id = $id;
        $attemptLog->type = AttemptsLog::TYPE_RESTORE_PASSWORD;
        $attemptLog->save();

        if (empty($code)) {
            throw new BadRequestException('Empty code');
        }

        $model = User::findOne(['id' => $id, 'password_reset_token' => $code]);
        if (empty($model)) {
            throw new NotFoundHttpException('Reset code not found');
        }

        if ((time() - $model->password_reset_token_created_at) > (60 * 60)) {
            throw new BadRequestException('Code has expired', 410);
        }

        if (empty($password) || strlen($password) < 4) {
            throw new BadRequestException('Empty or too short password', 400);
        }

        $model->password_hash = md5(Yii::$app->request->post('password') . Yii::$app->params['salt']);
        $model->password_reset_token = null;
        $model->password_reset_token_created_at = null;
        if ($model->save() === false) {
            throw new BadRequestException("Can't set password", 400);
        }
    }

    /**
     * @param $id
     * @return array
     * @throws BadRequestException
     * @throws NotFoundHttpException
     */
    public function actionAutentication($id)
    {
        $model = User::findOne($id);
        if (empty($model)) {
            throw new NotFoundHttpException('User not found');
        }
        $password = Yii::$app->request->post('password');

        //Сколько было попыток входа за последние 5 минут
        $attempts = AttemptsLog::find()
            ->where(['user_id' => $id, 'type' => AttemptsLog::TYPE_EMAIL_LOGIN])
            ->andWhere(['>=', 'created_at', time() - 5 * 60])
            ->count();

        if ($attempts > 5) {
            throw new BadRequestException('More than 5 attempts', 400);
        }

        if ($model->validatePassword($password)) {
            $token = Yii::$app->security->generateRandomString();
            $model->access_token = $token;
            if ($model->save()) {
                Yii::$app->response->setStatusCode(201);
                return ['token' => $token];
            }
        } else {
            $attemptLog = new AttemptsLog();
            $attemptLog->user_id = $id;
            $attemptLog->type = AttemptsLog::TYPE_EMAIL_LOGIN;
            $attemptLog->save();
            throw new BadRequestException('Incorrect password', 400);
        }
    }

    /**
     * POST /users/{id}/devices
     * Регистрация device_id пользователей для пушей.
     *
     * @param $id
     * @throws UserDeviceValidationException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function actionAddDevice($id)
    {
        if ((int)$id !== \Yii::$app->user->id) {
            throw new \yii\web\ForbiddenHttpException('Access forbidden.', 403);
        }

        UsersDevices::deleteAll(['user_id' => $id]);
        $model = new UsersDevices();
        $model->user_id = $id;
        $model->device_id = Yii::$app->request->post('device_id');
        if ($model->save() === false) {
            throw new UserDeviceValidationException($model);
        }

        Yii::$app->response->statusCode = 201;
    }

    /**
     * /users/{id}/feedback
     * Отправка запроса в техподдержку.
     *
     * @param $id
     * @throws FeedbackException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function actionFeedback($id)
    {
        if ((int)$id !== \Yii::$app->user->id) {
            throw new \yii\web\ForbiddenHttpException('Access forbidden.', 403);
        }

        $model = new Feedback();
        $model->user_id = $id;
        $model->message = Yii::$app->request->post('text');
        if ($model->save() === false) {
            throw new FeedbackException($model);
        }

        Yii::$app->response->statusCode = 201;
    }

    public function actionToken()
    {
        Yii::$app->response->format = Response::FORMAT_HTML;

        $redirect_uri = 'https://apiapps.lm23.net/user/token';
        $clientId = '156413191207-1tf6ucjuadbl6r94s14f33pel8sabesq.apps.googleusercontent.com';
        $secret = 'm0BM-SDMHAHQ2AqXUOdw5CKy';
        $client = new \Google_Client([
            'client_id' => $clientId,
            'client_secret' => $secret,
        ]);
        $client->setRedirectUri($redirect_uri);
        $client->setScopes(['profile']);
        $oauth = new \Google_Service_Oauth2($client);
        var_dump($client->createAuthUrl());

        if (isset($_GET['code'])) {
            $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
            $client->setAccessToken($token);
            var_dump($token);
        }
    }

    public function actionTest()
    {
        $daDataClient = new \Dadata\Client(new \GuzzleHttp\Client(), [
            'token' => \Yii::$app->params['dadataSecretKey'],
            'secret' => \Yii::$app->params['dadataSecretKeyStandard'],
        ]);
        var_dump($daDataClient->cleanAddress('семипалатинская')->qc);
    }
}