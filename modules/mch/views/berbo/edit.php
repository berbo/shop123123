<?php
defined('YII_ENV') or exit('Access Denied');

/**
 * Created by IntelliJ IDEA.
 * User: luwei
 * Date: 2017/8/24
 * Time: 10:18
 */

use yii\widgets\LinkPager;

/* @var \app\models\Coupon $model */

$urlManager = Yii::$app->urlManager;
$this->title = '优惠券编辑';
$this->params['active_nav_group'] = 7;
?>
<style>

    .cat-list .cat-item {
        text-align: center;
        width: 120px;
        border: 1px solid #e3e3e3;
        height: 110px;
        cursor: pointer;
        display: inline-block;
        vertical-align: top;
        margin: 1rem 1rem;
        border-radius: .15rem;
    }

    .cat-list .cat-item:hover {
        background: rgba(238, 238, 238, 0.54);
    }

    .cat-list .cat-item img {
        width: 4rem;
        height: 4rem;
        border-radius: 999px;
        margin-bottom: 3px;
        margin-top: 1rem;
    }

    .cat-list .cat-item.active {
        background: rgba(2, 117, 216, 0.69);
        color: #fff;
    }
</style>
<div id="panel-body">
<div class="panel mb-3">
    <div class="panel-header"><?= $this->title ?></div>
    <div class="panel-body">
        <form class="form auto-form" method="post" autocomplete="off" return="<?= $urlManager->createUrl(['mch/berbo/edit','id' => $model->id]) ?>">
            <div class="form-body">
                <div class="form-group row">
                    <div class="form-group-label col-3 text-right">
                        <label class=" col-form-label required">Izina Hano</label>
                    </div>
                    <div class="col-9">
                        <input class="form-control" name="name" value="<?= $model->name ?>">
                    </div>
                </div>
                <div class="form-group row">
                    <div class="form-group-label col-3 text-right">
                        <label class=" col-form-label required">Email</label>
                    </div>
                    <div class="col-9">
                        <input class="form-control" type="text" name="email"
                               value="<?= $model->email ? $model->email : "" ?>">
                        <div class="fs-sm text-muted">what is this?</div>
                    </div>
                </div>

                <div class="form-group row">
                    <div class="form-group-label col-3 text-right">
                    </div>
                    <div class="col-9">
                        <a class="btn btn-primary auto-form-btn" href="javascript:">KUBIKA</a>
                        <input type="button" class="btn btn-default ml-4" 
                               name="Submit" value="返回">
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
</div>
