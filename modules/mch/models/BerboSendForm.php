<?php
/**
 * Created by IntelliJ IDEA.
 * User: luwei
 * Date: 2017/8/24
 * Time: 17:27
 */

namespace app\modules\mch\models;

use app\models\ActivityMsgTpl;
use app\models\Coupon;
use app\models\Berbo;
use app\models\User;
use app\models\WechatTplMsgSender;

class BerboSendForm extends MchModel
{
    

    public function rules()
    {
        return [
            [['id'], 'required'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'AN ID',
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->errorResponse;
        }
        $berbo = Berbo::findOne([
            'id' => 1,
        ]);
        if (!$berbo) {
            return [
                'code' => 1,
                'msg' => '优惠券不存在',
            ];
        }
        $count = 11;

        return [
            'code' => 0,
            'msg' => "操作完成，共发放{$count}人次。",
        ];
    }
}
