<?php

namespace app\models;

use app\models\common\admin\log\CommonActionLog;
use Yii;

/**
 * 发货单[自提订单]
 * This is the model class for table "{{%purchase}}".
 *
 * @property integer $id
 * @property integer $order_no
 * @property string $addtime
 * @property string $updatetime
 * @property integer $state
 * @property string $name
 * @property integer $mobile
 * @property integer $address
 *@property integer $parent_id
 * @property integer $shop_id
 *
 */
class SendPurchase extends \yii\db\ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%send_purchase}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'order_no' => '订单号',
            'addtime' => '添加时间',
            'updatetime' => '更新时间',
            'status' => '采购单状态',
            'name' => '收货人姓名',
            'mobile' => '手机号',
            'address' => '收货地址',
        ];
    }

    /**
     * @return array
     */
    public function savePurchase()
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
