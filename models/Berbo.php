<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "{{%berbo}}".
 *
 * @property integer $id
 * @property string $name
 * @property string $email
 */
class Berbo extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public $num;
    public $type;
    public static function tableName()
    {
        return '{{%berbo}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name','email'], 'required'],
            ['email', 'email'],
            ['name', 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'email' => 'Email',
        ];
    }

    /**
     * 给用户发放优惠券
     * @param integer $user_id 用户id
     * @param integer $coupon_id 优惠券id
     * @param integer $coupon_auto_send_id 自动发放id
     * @param integer $type 领券类型
     * @return boolean
     */
    public static function userAddBerbo($user_id, $name, $email)
    {
        $user = User::findOne($user_id);
        if (!$user) {
            return false;
        }
        $berbo = Berbo::findOne([
            'id' => $id,
        ]);
        if (!$berbo) {
            return false;
        }

        $berbo->id =  $id;
        $berbo->name = 'Some Name';
        $berbo->email = 'Some@email.me';
        return $berbo->save();
    }
}
