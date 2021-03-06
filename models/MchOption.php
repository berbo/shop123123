<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "{{%mch_option}}".
 *
 * @property integer $id
 * @property integer $store_id
 * @property integer $mch_id
 * @property string $group
 * @property string $name
 * @property string $value
 */
class MchOption extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%mch_option}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['store_id', 'mch_id'], 'integer'],
            [['value'], 'required'],
            [['value'], 'string'],
            [['group', 'name'], 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'store_id' => 'Store ID',
            'mch_id' => 'Mch ID',
            'group' => 'Group',
            'name' => 'Name',
            'value' => 'Value',
        ];
    }

    /**
     * @param $name string Name
     * @param $value mixed Value
     */
    public static function set($name, $value, $store_id = 0, $mch_id = 0, $group = '')
    {
        if (empty($name)) {
            return false;
        }
        $model = self::findOne([
            'name' => $name,
            'store_id' => $store_id,
            'mch_id' => $mch_id,
            'group' => $group,
        ]);
        if (!$model) {
            $model = new self();
            $model->name = $name;
            $model->store_id = $store_id;
            $model->mch_id = $mch_id;
            $model->group = $group;
        }
        $model->value = Yii::$app->serializer->encode($value);
        return $model->save();
    }

    /**
     * @param $name string Name
     */
    public static function get($name, $store_id = 0, $mch_id = 0, $group = '', $default = null)
    {
        $model = self::findOne([
            'name' => $name,
            'store_id' => $store_id,
            'mch_id' => $mch_id,
            'group' => $group,
        ]);
        if (!$model) {
            return $default;
        }
        return Yii::$app->serializer->decode($model->value);
    }

    /**
     * @param $list array [ ['name'=>'nameA','value'=>'valueA','store_id'=>'store_id','group'=>'group'], ... ]
     * @return boolean
     */
    public static function setList($list, $store_id = 0, $mch_id = 0, $group = '')
    {
        if (!is_array($list)) {
            return false;
        }
        foreach ($list as $item) {
            self::set($item['name'], $item['value'], (isset($item['store_id']) ? $item['store_id'] : $store_id), (isset($item['mch_id']) ? $item['mch_id'] : $mch_id), (isset($item['group']) ? $item['group'] : $group));
        }
        return true;
    }

    /**
     * @param $names array|string ['nameA','nameB'] or 'nameA,nameB'
     * @return array ['nameA'=>valueA,'nameB'=>valueB]
     */
    public static function getList($names, $store_id = 0, $mch_id = 0, $group = '', $default = null)
    {
        if (is_string($names)) {
            $names = explode(',', $names);
        }
        if (!is_array($names)) {
            return [];
        }
        $list = [];
        foreach ($names as $name) {
            if (empty($name)) {
                continue;
            }
            $value = self::get($name, $store_id, $mch_id, $group, $default);
            $list[$name] = $value;
        }
        return $list;
    }


    public static function remove($name, $store_id = 0, $mch_id = 0, $group = '')
    {
        $model = self::findOne([
            'name' => $name,
            'store_id' => $store_id,
            'mch_id' => $mch_id,
            'group' => $group,
        ]);
        if ($model) {
            return $model->delete();
        }
        return false;
    }
}
