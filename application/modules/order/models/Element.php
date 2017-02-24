<?php

namespace app\modules\order\models;

use yii;
use yii\db\Query;

class Element extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return '{{%order_element}}';
    }

    public function rules()
    {
        return [
            [['order_id', 'model', 'item_id'], 'required'],
            [['description', 'model', 'options'], 'string'],
            [['price'], 'double'],
            [['item_id', 'count'], 'integer'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => yii::t('order', 'ID'),
            'price' => yii::t('order', 'Price'),
            'base_price' => yii::t('order', 'Base price'),
            'description' => yii::t('order', 'Description'),
            'options' => yii::t('order', 'Options'),
            'model' => yii::t('order', 'Model name'),
            'order_id' => yii::t('order', 'Order ID'),
            'item_id' => yii::t('order', 'Product'),
            'count' => yii::t('order', 'Count'),
            'is_assigment' => yii::t('order', 'Assigment'),
        ];
    }

    public function getProduct()
    {
        $modelStr = $this->model;
        $productModel = new $modelStr();

        return $this->hasOne($productModel::className(), ['id' => 'item_id'])->one();
    }

    public function getOrder()
    {
        return $this->hasOne(Order::className(), ['id' => 'order_id']);
    }

    public function getModel($withCartElementModel = true)
    {
        if(!$withCartElementModel) {
            return $this->model;
        }

        if(is_string($this->model)) {
            if(class_exists($this->model)) {
                $model = '\\'.$this->model;
                $productModel = new $model();
                if ($productModel = $productModel::findOne($this->item_id)) {
                    $model = $productModel;
                } else {
                    throw new \yii\base\Exception('Element model not found');
                }
            } else {
                //throw new \yii\base\Exception('Unknow element model');
            }
        } else {
            $model = $this->model;
        }

        return $model;
    }

    public static function editField($id, $name, $value)
    {
        $setting = Element::findOne($id);
        $setting->$name = $value;
        $setting->save();
    }

    public function afterDelete()
    {
        parent::afterDelete();

        $this->order->reCount();

        return true;
    }

    public function beforeDelete()
    {
        parent::beforeDelete();

        $this->getModel()->plusAmount($this->count);

        return true;
    }
}
