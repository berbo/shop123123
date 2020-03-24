<?php

namespace app\models;

use app\models\common\admin\log\CommonActionLog;
use Yii;

/**
 * 采购单
 * This is the model class for table "{{%purchase_goods}}".
 *
 * @property integer $id
 * @property integer $store_id
 * @property integer $name
 * @property string $num
 * @property integer $addtime
 * @property integer $state
 * @property integer $updatetime
 * @property string $purchase_detail_id
 * @property string $purchase_id
 */
class PurchaseGoods extends \yii\db\ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%purchase_goods}}';
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
            'store_id' => '商城id',
            'parent_id' => '上级分类id',
            'addtime' => 'Addtime',
            'status' => '采购单状态',
            'updatetime' => '更新时间',
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
