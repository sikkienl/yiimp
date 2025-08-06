<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\Coins;

class AdminController extends Controller
{
    public $defaultAction='dashboard';

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['login'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
                'denyCallback' => function(){
                    return $this->goHome();
                }
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays Main Dashboard.
     *
     * @return Response|string
     */
    public function actionDashboard()
	{
		return $this->render('dashboard');
	}


    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        if ((!is_null(Yii::$app->user->identity)) && (Yii::$app->user->identity->is_admin)) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /* Dashboard sub-parts */
    public function actionCommon_results()
	{  
        return $this->renderPartial('common_results');
	}

    /////////////////////////////////////////////////
    /* generating data for graphs */

	public function actionGraph_assets_results()
	{
		return $this->renderPartial('results/graph_assets_results');
	}

	public function actionGraph_negative_results()
	{
		return $this->renderPartial('results/graph_negative_results');
	}

	public function actionGraph_profit_results()
	{
		return $this->renderPartial('results/graph_profit_results');
	}

    /////////////////////////////////////////////////

    public function actionGraph_market_balance()
	{
        $coinid = Yii::$app->getRequest()->getQueryParam('id');
		return $this->renderPartial('results/graph_market_balance', ['id' => $coinid]);
	}

    public function actionGraph_market_prices()
	{
        $coinid = Yii::$app->getRequest()->getQueryParam('id');
		return $this->renderPartial('results/graph_market_prices', ['id' => $coinid]);
	}

    /////////////////////////////////////////////////
    /* coin list and information */

    public function actionCoinlist()
	{
		return $this->render('coinlist');
	}

	public function actionCoin_create()
	{
		$coin = new Coins;
		$coin->txmessage = true;
		$coin->created = time();

		if (isset($_POST['Coins'])) {
            $coin->setAttributes($_POST['Coins'], false);
    
            if ($coin->validate() && $coin->save())
            {
                return $this->redirect(array('coinlist'));
            }
        }

		return $this->render('coin_update', array('coin'=>$coin, 'update'=>false));
	}

	public function actionCoin_update()
	{
        $coinid = (int) Yii::$app->getRequest()->getQueryParam('id');
		$coin = Coins::findOne($coinid);

        if (isset($_POST['Coins'])) {
            $coin->setAttributes($_POST['Coins'], false);
    
            if ($coin->validate() && $coin->save())
            {
                return $this->redirect(array('coinlist'));
            }
        }

		return $this->render('coin_update', array('coin'=>$coin, 'update'=>true));
	}

	/////////////////////////////////////////////////

	public function actionCoinwallets()
	{
		return $this->render('coinwallets');
	}

	public function actionCoinwallet_results()
	{
		return $this->renderPartial('coinwallet_results');
	}

    /////////////////////////////////////////////////

    public function actionCoinwallet()
	{
		return $this->render('coinwallet');
	}

    public function actionCoinwallet_details()
	{
		return $this->renderPartial('coinwallet_details');
	}

}