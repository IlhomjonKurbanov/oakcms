<?php
/**
 * @package    oakcms
 * @author     Hryvinskyi Volodymyr <script@email.ua>
 * @copyright  Copyright (c) 2015 - 2017. Hryvinskyi Volodymyr
 * @version    0.0.1-beta.0.1
 */

namespace app\modules\menu\controllers\backend;


use app\components\BackendController;
use app\modules\admin\components\behaviors\StatusController;
use app\modules\menu\models\MenuItem;
use app\modules\menu\models\MenuItemSearch;
use app\modules\menu\models\MenuLinkParams;
use app\modules\system\models\DbState;
use kartik\widgets\Alert;
use Yii;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\VarDumper;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class ItemController
 * @package app\modules\menu\controllers\backend
 */
class ItemController extends BackendController
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class'   => VerbFilter::className(),
                'actions' => [
                    'delete'      => ['post', 'delete'],
                    'bulk-delete' => ['post'],
                    'publish'     => ['post'],
                    'unpublish'   => ['post'],
                    'status'      => ['post'],
                    //'type-items' => ['post'],
                ],
            ],
            [
                'class' => StatusController::className(),
                'model' => MenuItem::className(),
            ],
        ];
    }

    /**
     * Lists all MenuItem models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new MenuItemSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel'  => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Lists all MenuItem models with linkType == LINK_ROUTE.
     * @return mixed
     */
    public function actionSelect()
    {
        $searchModel = new MenuItemSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams());

        Yii::$app->getView()->applyModalLayout();

        return $this->render('select', [
            'dataProvider' => $dataProvider,
            'searchModel'  => $searchModel,
        ]);
    }

    /**
     * Отдает список пунктов меню для Select2 виджета
     *
     * @param string|null  $q
     * @param string|null  $language
     * @param integer|null $exclude
     * @param integer|null $menu_type_id
     *
     * @return array
     */
    public function actionItemList($q = null, $language = null, $exclude = null, $menu_type_id = null)
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        $query = MenuItem::find()->excludeRoots();
        if ($exclude && $item = MenuItem::findOne($exclude)) {
            /** @var $item MenuItem */
            $query->excludeItem($item);
        }

        $results = $query->select('id, title AS text')->andFilterWhere(['like', 'title', urldecode($q)])->andFilterWhere(['language' => $language, 'menu_type_id' => $menu_type_id])->limit(20)->asArray()->all();

        return ['results' => $results];
    }

    /**
     * Lists all routers.
     * @return mixed
     */
    public function actionRouters()
    {
        Yii::$app->getView()->applyModalLayout();

        return $this->render('routers');
    }

    /**
     * Используется CkEditor для выбора типа ссылки.
     *
     * @param string $CKEditor
     * @param string $CKEditorFuncNum
     * @param string $langCode
     *
     * @return mixed
     */
    public function actionCkeditorSelect($CKEditor, $CKEditorFuncNum, $langCode)
    {
        Yii::$app->getView()->applyModalLayout();

        return $this->render('ckeditor-select', [
            'CKEditor'        => $CKEditor,
            'CKEditorFuncNum' => $CKEditorFuncNum,
            'langCode'        => $langCode,
        ]);
    }

    /**
     * Используется CkEditor для выбора ссылки на компонент.
     *
     * @param string $CKEditor
     * @param string $CKEditorFuncNum
     * @param string $langCode
     *
     * @return mixed
     */
    public function actionCkeditorSelectComponent($CKEditor, $CKEditorFuncNum, $langCode)
    {
        Yii::$app->getView()->applyModalLayout();

        return $this->render('ckeditor-select-component', [
            'CKEditor'        => $CKEditor,
            'CKEditorFuncNum' => $CKEditorFuncNum,
            'langCode'        => $langCode,
        ]);
    }

    /**
     * Используется CkEditor для выбора ссылки пункт меню.
     *
     * @param string $CKEditor
     * @param string $CKEditorFuncNum
     * @param string $langCode
     *
     * @return mixed
     */
    public function actionCkeditorSelectMenu($CKEditor, $CKEditorFuncNum, $langCode)
    {
        Yii::$app->getView()->applyModalLayout();

        return $this->render('ckeditor-select-menu', [
            'CKEditor'        => $CKEditor,
            'CKEditorFuncNum' => $CKEditorFuncNum,
            'langCode'        => $langCode,
        ]);
    }

    /**
     * Displays a single MenuItem model.
     *
     * @param integer $id
     *
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Finds the MenuItem model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     *
     * @param integer $id
     *
     * @return MenuItem the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = MenuItem::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException(Yii::t('menu', 'The requested page does not exist.'));
        }
    }

    /**
     * Creates a new MenuItem model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     *
     * @param null        $menuTypeId
     * @param null        $sourceId
     * @param null        $parentId
     * @param null        $language
     * @param string|null $backUrl
     *
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionCreate(
        $menuTypeId = null, $sourceId = null, $parentId = null, $language = null, $backUrl = null
    ) {
        $model = new MenuItem();
        $model->loadDefaultValues();
        $model->status = MenuItem::STATUS_PUBLISHED;
        $model->language = $language ? $language : Yii::$app->language;

        // по дефолту выставляем ендпоинт на "временную старницу"
        $model->link_type = MenuItem::LINK_ROUTE;
        $model->link = '';

        if (isset($menuTypeId)) $model->menu_type_id = $menuTypeId;

        if (isset($parentId)) {
            $parentCategory = $this->findModel($parentId);
            $model->parent_id = $parentCategory->id;
            if ($parentCategory->language) {
                $model->language = $parentCategory->language;
            }
        }

        if (isset($sourceId) && $language) {
            $sourceModel = $this->findModel($sourceId);
            /** @var MenuItem $parentItem */
            // если локализуемый пункт меню имеет родителя, то пытаемся найти релевантную локализацию для родителя создаваемого пункта меню
            if (!($sourceModel->level > 2 && $parentItem = @$sourceModel->parent->translations[$language])) {
                $parentItem = MenuItem::find()->roots()->one();
            }

            $model->language = $language;
            $model->parent_id = $parentItem->id;
            $model->menu_type_id = $sourceModel->menu_type_id;
            $model->translation_id = $sourceModel->translation_id;
            $model->alias = $sourceModel->alias;
            $model->status = $sourceModel->status;
            $model->link = $sourceModel->link;
            $model->link_type = $sourceModel->link_type;
            $model->ordering = $sourceModel->ordering;
            $model->layout_path = $sourceModel->layout_path;
            $model->access_rule = $sourceModel->access_rule;
            $model->link_params = $sourceModel->link_params;
        } else {
            $sourceModel = null;
        }

        $linkParamsModel = new MenuLinkParams();
        $linkParamsModel->setAttributes($model->getLinkParams());

        $createParam = [
            'model'           => $model,
            'linkParamsModel' => $linkParamsModel,
            'sourceModel'     => $sourceModel,
        ];

        if ($model->load(Yii::$app->request->post()) && $linkParamsModel->load(Yii::$app->request->post()) && $model->validate() && $linkParamsModel->validate()) {
            $model->setLinkParams($linkParamsModel->toArray());
            $model->saveNode(false);

            if (Yii::$app->request->post('submit-type') == 'continue')
                return $this->redirect(['update', 'id' => $model->id]);
            elseif (Yii::$app->request->post('submit-type') == 'createNew')
                return $this->redirect(['create']);
            else
                return $this->redirect(['index']);
        } else {
            return $this->render('create', $createParam);
        }
    }

    /**
     * Updates an existing MenuItem model.
     * If update is successful, the browser will be redirected to the 'view' page.
     *
     * @param integer     $id
     * @param string|null $backUrl
     *
     * @return mixed
     */
    public function actionUpdate($id, $backUrl = null)
    {
        $model = $this->findModel($id);
        $linkParamsModel = new MenuLinkParams();
        $linkParamsModel->setAttributes($model->getLinkParams());

        if ($model->load(Yii::$app->request->post()) && $linkParamsModel->load(Yii::$app->request->post()) && $model->validate() && $linkParamsModel->validate()) {
            $model->setLinkParams($linkParamsModel->toArray());
            $model->saveNode(false);

            if (Yii::$app->request->post('submit-type') == 'continue')
                return $this->redirect(['update', 'id' => $model->id]);
            elseif (Yii::$app->request->post('submit-type') == 'createNew')
                return $this->redirect(['create']);
            else
                return $this->redirect(['index']);

        } else {
            return $this->render('update', [
                'model'           => $model,
                'linkParamsModel' => $linkParamsModel,
            ]);
        }
    }

    /**
     * Deletes an existing MenuItem model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     *
     * @param integer $id
     *
     * @return mixed
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);

        if ($model->children()->count()) {
            Yii::$app->session->setFlash(Alert::TYPE_DANGER, Yii::t('menu', "It's impossible to remove menu item ID:{id} so far it contains descendants.", ['id' => $model->id]));
        } else {
            $model->delete();
        }

        if (Yii::$app->request->getIsDelete()) {
            return $this->redirect(ArrayHelper::getValue(Yii::$app->request, 'referrer', ['index']));
        }

        return $this->redirect(['index']);
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function actionBulkDelete()
    {
        $data = Yii::$app->request->getBodyParam('data', []);

        $models = MenuItem::find()->where(['id' => $data])->orderBy(['lft' => SORT_DESC])->all();

        foreach ($models as $model) {
            /** @var MenuItem $model */
            if ($model->children()->count()) continue;

            $model->delete();
        }

        if (!Yii::$app->request->getIsAjax()) {
            return $this->redirect(ArrayHelper::getValue(Yii::$app->request, 'referrer', ['index']));
        }
    }

    /**
     * @return Response
     */
    public function actionSorting()
    {
        $data = \Yii::$app->request->post('sorting');

        foreach ($data as $order => $id) {
            if ($target = MenuItem::findOne($id)) {
                $target->updateAttributes(['ordering' => intval($order)]);
            }
        }

        MenuItem::find()->roots()->one()->reorderNode('ordering');
        DbState::updateState(MenuItem::tableName());

        //return $this->redirect(ArrayHelper::getValue(Yii::$app->request, 'referrer', ['index']));
    }

    /**
     * @param integer $id
     *
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionPublish($id)
    {
        $model = $this->findModel($id);

        $model->status = MenuItem::STATUS_PUBLISHED;
        $model->save();

        return $this->redirect(ArrayHelper::getValue(Yii::$app->request, 'referrer', ['index']));
    }

    /**
     * @param integer $id
     *
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionUnpublish($id)
    {
        $model = $this->findModel($id);

        $model->status = MenuItem::STATUS_UNPUBLISHED;
        $model->save();

        return $this->redirect(ArrayHelper::getValue(Yii::$app->request, 'referrer', ['index']));
    }

    public function actionOn($id)
    {
        return $this->changeStatus($id, MenuItem::STATUS_PUBLISHED);
    }

    public function actionOff($id)
    {
        return $this->changeStatus($id, MenuItem::STATUS_UNPUBLISHED);
    }

    /**
     * @param integer $id
     * @param         $status
     *
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionStatus($id, $status)
    {
        $model = $this->findModel($id);

        $model->status = $status;
        if (!$model->save()) {
            Yii::$app->session->setFlash(Alert::TYPE_DANGER, $model->getFirstError('status'));
        }

        return $this->redirect(ArrayHelper::getValue(Yii::$app->request, 'referrer', ['index']));
    }

    /**
     * todo remove
     *
     * @param null   $update_item_id
     * @param string $selected
     */
    public function actionTypeItems($update_item_id = null, $selected = '')
    {
        if (isset($_POST['depdrop_parents'])) {
            $parents = $_POST['depdrop_parents'];
            if ($parents != null) {
                $typeId = $parents[0];
                $language = $parents[1];
                //исключаем редактируемый пункт и его подпункты из списка
                if (!empty($update_item_id) && $updateItem = MenuItem::findOne($update_item_id)) {
                    $excludeIds = array_merge([$update_item_id], $updateItem->children()->select('id')->column());
                    //если выбранный тип меню совпадает с типом меню редактируемого пункта, выбираем текущее значение родительского элемента
                    $selected = $updateItem->menu_type_id == $typeId ? $updateItem->parent_id : '';
                } else {
                    $excludeIds = [];
                }

                $out = array_map(function ($value) {
                    return [
                        'id'   => $value['id'],
                        'name' => str_repeat(" • ", $value['level'] - 1) . $value['title'],
                    ];
                }, MenuItem::find()->excludeRoots()->type($typeId)->language($language)->orderBy('lft')->andWhere(['not in', 'id', $excludeIds])->asArray()->all());
                /** @var MenuItem $root */
                $root = MenuItem::find()->roots()->one();
                array_unshift($out, [
                    'id'   => $root->id,
                    'name' => Yii::t('menu', 'Root'),
                ]);

                echo Json::encode(['output' => $out, 'selected' => $selected ? $selected : $root->id]);

                return;
            }
        }
        echo Json::encode(['output' => '', 'selected' => $selected]);
    }
}
