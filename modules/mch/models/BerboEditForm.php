<?php
/**
 * Created by IntelliJ IDEA.
 * User: luwei
 * Date: 2017/8/24
 * Time: 11:59
 */

namespace app\modules\mch\models;

use app\models\Coupon;
use app\models\Berbo;
use app\models\UserCoupon;

/**
 * @property Coupon $coupon
 */
class BerboEditForm extends MchModel
{
    public $store_id;
    public $id;
    public $name;
    public $email;
    public $berbo;


    public function rules()
    {
        return [
            [['name','email'], 'trim'],
            [['name', 'email'], 'required'],
            [['id'], 'integer', 'min' => 0, 'max' => 999999],
        ];
    }

    public function attributeLabels()
    { 
        return [
            'name' => 'Izina',
            'email' => 'Imeyili',
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->errorResponse;
        }
        $this->berbo->name = $this->name;
        $this->berbo->email = $this->email;

        if ($this->berbo->save()) {
            return [
                'code' => 0,
                'msg' => '保存成功',
            ];
        } else {
            return $this->getErrorResponse($this->berbo);
        }
    }
}
