<?php
namespace app\modules\order\controllers\backend;

use yii;
use app\modules\order\models\tools\OrderSearch;
use app\modules\order\models\Order;
use app\modules\order\models\Payment;
use app\modules\order\models\tools\ElementSearch;
use app\modules\order\models\Field;
use app\modules\order\models\PaymentType;
use app\modules\order\models\ShippingType;
use yii\web\Controller;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use app\modules\order\events\OrderEvent;

class OrderController extends Controller
{
    public function behaviors()
    {
        return [
            'adminAccess' => [
                'class' => AccessControl::className(),
                'only'  => ['create', 'update', 'index', 'view', 'push-elements', 'print', 'delete', 'editable', 'to-order', 'update-status'],
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => $this->module->adminRoles,
                    ],
                ],
            ],
            'verbs'       => [
                'class'   => VerbFilter::className(),
                'actions' => [
                    'delete'        => ['post'],
                    'push-elements' => ['post'],
                    'to-order'      => ['post'],
                ],
            ],
        ];
    }

    public function beforeAction($action)
    {
        if ($action->id == 'print' | $action->id == 'create') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    public function actionIndex($tab = 'orders')
    {
        $searchModel = new OrderSearch();

        $searchParams = yii::$app->request->queryParams;

        //if(!yii::$app->user->can(current(yii::$app->getModule('order')->adminRoles))) {
        //    $searchParams['OrderSearch']['seller_user_id'] = yii::$app->user->id;
        //}

        $dataProvider = $searchModel->search($searchParams);

        if ($tab == 'assigments') {
            $dataProvider->query->andWhere(['order.is_assigment' => '1']);
        } else {
            $dataProvider->query->andWhere('(order.is_assigment IS NULL OR order.is_assigment = 0)');
        }

        if (yii::$app->request->get('time_start')) {
            $dataProvider->query->orderBy('order.timestamp ASC');
        }

        $dataProvider->pagination = ["pageSize" => 100];

        $paymentTypes = ArrayHelper::map(PaymentType::find()->all(), 'id', 'name');
        $shippingTypes = ArrayHelper::map(ShippingType::find()->all(), 'id', 'name');

        $this->getView()->registerJs('oak.orders_list.elementsUrl = "' . Url::toRoute(['/order/tools/ajax-elements-list']) . '";');

        return $this->render('index', [
            'tab'           => Html::encode($tab),
            'searchModel'   => $searchModel,
            'shippingTypes' => $shippingTypes,
            'paymentTypes'  => $paymentTypes,
            'module'        => $this->module,
            'dataProvider'  => $dataProvider,
        ]);
    }

    public function actionPushElements($id)
    {
        if ($model = $this->findModel($id)) {
            yii::$app->order->pushCartElements($id);
        }

        $this->redirect(['/order/order/view', 'id' => $id]);
    }

    public function actionView($id, $push_cart = false)
    {
        $model = $this->findModel($id);

        $searchModel = new ElementSearch;
        $params = yii::$app->request->queryParams;
        if (empty($params['ElementSearch'])) {
            $params = ['ElementSearch' => ['order_id' => $model->id]];
        }

        $dataProvider = $searchModel->search($params);

        $paymentTypes = ArrayHelper::map(PaymentType::find()->all(), 'id', 'name');
        $shippingTypes = ArrayHelper::map(ShippingType::find()->all(), 'id', 'name');

        $fieldFind = Field::find();

        $this->getView()->registerJs('oak.order.outcomingAction = "' . Url::toRoute(['/order/tools/outcoming']) . '";');

        return $this->render('view', [
            'searchModel'   => $searchModel,
            'dataProvider'  => $dataProvider,
            'shippingTypes' => $shippingTypes,
            'fieldFind'     => $fieldFind,
            'paymentTypes'  => $paymentTypes,
            'model'         => $model,
        ]);
    }

    public function actionPrintLast()
    {
        $this->layout = 'print';

        $this->enableCsrfValidation = false;

        $model = Order::find()->orderBy('id DESC')->limit(1)->one();

        $searchModel = new ElementSearch;
        $params = yii::$app->request->queryParams;
        if (empty($params['ElementSearch'])) {
            $params = ['ElementSearch' => ['order_id' => $model->id]];
        }

        $dataProvider = $searchModel->search($params);

        $paymentTypes = ArrayHelper::map(PaymentType::find()->all(), 'id', 'name');
        $shippingTypes = ArrayHelper::map(ShippingType::find()->all(), 'id', 'name');

        $fieldFind = Field::find();

        return $this->render('print', [
            'searchModel'   => $searchModel,
            'dataProvider'  => $dataProvider,
            'shippingTypes' => $shippingTypes,
            'fieldFind'     => $fieldFind,
            'paymentTypes'  => $paymentTypes,
            'module'        => $this->module,
            'model'         => $model,
        ]);
    }

    public function actionPrint($id)
    {
        $this->layout = 'print';

        $this->enableCsrfValidation = false;

        $model = $this->findModel($id);
        $searchModel = new ElementSearch;
        $params = yii::$app->request->queryParams;
        if (empty($params['ElementSearch'])) {
            $params = ['ElementSearch' => ['order_id' => $model->id]];
        }

        $dataProvider = $searchModel->search($params);

        $paymentTypes = ArrayHelper::map(PaymentType::find()->all(), 'id', 'name');
        $shippingTypes = ArrayHelper::map(ShippingType::find()->all(), 'id', 'name');

        $fieldFind = Field::find();

        return $this->render('print', [
            'searchModel'   => $searchModel,
            'dataProvider'  => $dataProvider,
            'shippingTypes' => $shippingTypes,
            'fieldFind'     => $fieldFind,
            'paymentTypes'  => $paymentTypes,
            'module'        => $this->module,
            'model'         => $model,
        ]);
    }

    public function actionCreate()
    {
        $orderModel = yii::$app->orderModel;

        $model = new $orderModel;

        $this->getView()->registerJs("jQuery('.buy-by-code-input').focus();");

        if ($model->load(yii::$app->request->post()) && $model->save()) {

            if ($ordersEmail = yii::$app->getModule('order')->ordersEmail) {
                $sender = yii::$app->getModule('order')->mail
                    ->compose('admin_notification', ['model' => $model])
                    ->setTo($ordersEmail)
                    ->setFrom(yii::$app->getModule('order')->robotEmail)
                    ->setSubject(Yii::t('order', 'New order') . " #{$model->id} ({$model->client_name})")
                    ->send();
            }

            $module = $this->module;
            $orderEvent = new OrderEvent(['model' => $model, 'elements' => $model->elements]);
            $this->module->trigger($module::EVENT_ORDER_CREATE, $orderEvent);

            return $this->redirect([$this->module->orderCreateRedirect, 'id' => $model->id]);
        } else {
            //yii::$app->cart->truncate();
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    public function actionGetOrderFormLight($useAjax = false)
    {
        return $this->renderAjax('formLight', [
            'useAjax' => $useAjax,
        ]);
    }

    public function actionCreateAjax()
    {
        $orderModel = yii::$app->orderModel;

        $model = new $orderModel;

        if ((yii::$app->has('worksess')) && $session = yii::$app->worksess->soon()) {
            $model->sessionId = $session->id;
        } else {
            $model->sessionId = null;
        }

        $order = yii::$app->request->post('Order');
        if (isset($order['staffer'])) {
            $model->staffer = $order['staffer'];
        }

        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        // $model->staffer = yii::$app->request->post();
        if ($model->load(yii::$app->request->post()) && $model->save()) {
            $model = yii::$app->order->get($model->id);

            if ($ordersEmail = yii::$app->getModule('order')->ordersEmail) {
                $sender = yii::$app->getModule('order')->mail
                    ->compose('admin_notification', ['model' => $model])
                    ->setTo($ordersEmail)
                    ->setFrom(yii::$app->getModule('order')->robotEmail)
                    ->setSubject(Yii::t('order', 'New order') . " #{$model->id} ({$model->client_name})")
                    ->send();
            }

            $module = $this->module;
            $orderEvent = new OrderEvent(['model' => $model, 'elements' => $model->elements]);
            $this->module->trigger($module::EVENT_ORDER_CREATE, $orderEvent);

            $nextStepAction = false;

            // создаём заказ, очищаем информер корзины
            if ($model->cost == 0) {
                return [
                    'status'   => 'success',
                    'nextStep' => $nextStepAction,
                ];
            }

            // создаём заказ, отдаём урл на рендер формы оплаты
            if (\yii::$app->getModule('order')->paymentFormAction) {
                $nextStepAction = Url::to([\yii::$app->getModule('order')->paymentFormAction, 'id' => $model->id, 'useAjax' => 1]);
            }

            if ($this->module->paymentFreeTypeIds && in_array($model->payment_type_id, $this->module->paymentFreeTypeIds)) {
                \Yii::$app->order->setStatus($model->id, 'payed');
            }

            return [
                'status'   => 'success',
                'nextStep' => $nextStepAction,
            ];

        } else {
            return [
                'status' => 'error',
            ];
        }
    }

    public function actionCustomerCreate()
    {
        $model = new Order(['scenario' => 'customer']);

        if ($model->load(yii::$app->request->post())) {
            $model->date = date('Y-m-d');
            $model->time = date('H:i:s');
            $model->timestamp = time();
            $model->status = $this->module->defaultStatus;
            $model->payment = 'no';
            $model->user_id = yii::$app->user->id;

            if ($model->save()) {
                if ($ordersEmail = yii::$app->getModule('order')->ordersEmail) {
                    $sender = yii::$app->getModule('order')->mail
                        ->compose('admin_notification', ['model' => $model])
                        ->setTo($ordersEmail)
                        ->setFrom(yii::$app->getModule('order')->robotEmail)
                        ->setSubject(Yii::t('order', 'New order') . " #{$model->id} ({$model->client_name})")
                        ->send();
                }

                $module = $this->module;
                $orderEvent = new OrderEvent(['model' => $model]);
                $this->module->trigger($module::EVENT_ORDER_CREATE, $orderEvent);

                if ($paymentType = $model->paymentType) {
                    $payment = new Payment;
                    $payment->order_id = $model->id;
                    $payment->payment_type_id = $paymentType->id;
                    $payment->date = date('Y-m-d H:i:s');
                    $payment->amount = $model->getCost();
                    $payment->description = yii::t('order', 'Order #' . $model->id);
                    $payment->user_id = yii::$app->user->id;
                    $payment->ip = yii::$app->getRequest()->getUserIP();
                    $payment->save();

                    if ($widget = $paymentType->widget) {
                        return $widget::widget([
                            'autoSend'    => true,
                            'orderModel'  => $model,
                            'description' => yii::t('order', 'Order #' . $model->id),
                        ]);
                    }
                }

                return $this->redirect([yii::$app->getModule('order')->successUrl, 'id' => $model->id, 'payment' => $model->payment_type_id]);
            } else {
                yii::$app->session->setFlash('orderError', yii::t('order', 'Error (check required fields)'));

                return $this->redirect(yii::$app->request->referrer);
            }
        } else {
            yii::$app->session->setFlash('orderError', yii::t('order', 'Error (check required fields)'));

            return $this->redirect(yii::$app->request->referrer);
        }
    }

    public function actionUpdateStatus()
    {
        if ($id = yii::$app->request->post('id')) {
            $model = Order::findOne($id);
            $model->status = yii::$app->request->post('status');
            if ($model->save(false)) {
                die(json_encode(['result' => 'success']));
            } else {
                die(json_encode(['result' => 'fail', 'error' => 'enable to save']));
            }
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    public function actionDelete($id)
    {
        $model = $this->findModel($id);

        $module = $this->module;
        $orderEvent = new OrderEvent(['model' => $model]);
        $this->module->trigger($module::EVENT_ORDER_DELETE, $orderEvent);

        $model->delete();

        return $this->redirect(['index']);
    }

    public function actionEditable()
    {
        $name = yii::$app->request->post('name');
        $value = yii::$app->request->post('value');
        $pk = unserialize(base64_decode(yii::$app->request->post('pk')));
        OrderElement::editField($pk, $name, $value);
    }

    public function actionToOrder()
    {
        if ($order = $this->findModel(yii::$app->request->post('id'))) {
            $order->is_assigment = 0;
            $order->date = date('Y-m-d H:i:s');
            $order->timestamp = time();

            if ($staffers = yii::$app->request->post('staffers')) {
                $order->staffer = $staffers;
            }

            $order->save(false);

            $module = $this->module;
            $orderEvent = new OrderEvent(['model' => $order, 'elements' => $order->elements]);
            $this->module->trigger($module::EVENT_ORDER_CREATE, $orderEvent);

            $json = ['result' => 'success', 'order_view_location' => Url::toRoute(['/order/order/view', 'id' => $order->id])];
        } else {
            $json = ['result' => 'fail'];
        }

        return json_encode($json);
    }

    protected function findModel($id)
    {
        $orderModel = yii::$app->orderModel;

        if (($model = $orderModel::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested order does not exist.');
        }
    }
}
