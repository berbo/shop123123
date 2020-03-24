<?php

namespace app\models;

use app\models\common\admin\log\CommonActionLog;
use Yii;

/**
 * 采购单
 * This is the model class for table "{{%purchase_detail}}".
 *
 * @property integer $id
 * @property integer $purchase_id
 * @property string $good_name
 * @property string $good_attr
 * @property integer $num
 * @property integer $complete_num
 * @property string $order_detail_id
 * @property integer $addtime
 * @property integer $status
 * @property integer $updatetime
 * @property string $pic
 * @property integer $price
 */
class PurchaseDetail extends \yii\db\ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%purchase_detail}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [[ 'status', 'addtime','num','complete_num', 'updatetime'], 'integer'],
            [['good_name','good_attr','order_detail_id'], 'string'],
            [['updatetime', 'status', 'addtime','num','complete_num'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'purchase_id' => '采购单id',
            'good_name' => '商品名称',
            'good_attr' => '商品规格',
            'num' => '商品数量',
            'complete_num' => '实际采购数量',
            'addtime'=>'添加时间',
            'status' => '状态',
            'updatetime' => '更新时间',
            'order_detail_id'=>'订单详情id',
            'pic'=>'商品图片',
            'price'=>'商品单价',
        ];
    }

    /**
     * @return array
     */
    public function savePurchaseDetail()
    {
        if ($this->validate()) {
            if ($this->save(false)) {
                return [
                    'code' => 0,
                    'msg' => '成功'
                ];
            } else {
                return [
                    'code' => 1,
                    'msg' => '失败'
                ];
            }
        } else {
            return (new Model())->getErrorResponse($this);
        }
    }

    public function afterSave($insert, $changedAttributes)
    {
        $data = $insert ? json_encode($this->attributes) : json_encode($changedAttributes);
        CommonActionLog::storeActionLog('', $insert,0 , $data, $this->id);
    }
}
