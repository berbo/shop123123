<?php

namespace app\models;

use app\models\common\admin\log\CommonActionLog;
use Yii;

/**
 * 发货单详情[自提订单]
 * This is the model class for table "{{%send_purchase_detail}}".
 *
 * @property integer $id
 * @property integer $order_no
 * @property string $addtime
 * @property string $updatetime
 * @property integer $state
 * @property string $name
 * @property integer $mobile
 * @property integer $address
 * @property string $send_purchase_id
 *@property integer $order_detail_id
 * @property integer $nickname
 *
 *
 */
class SendPurchaseDetail extends \yii\db\ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%send_purchase_detail}}';
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

        ];
    }

    /**
     * @return array
     */
    public function saveSendPurchaseDetail()
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
