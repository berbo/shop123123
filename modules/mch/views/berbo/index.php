<?php
defined('YII_ENV') or exit('Access Denied');

/**
 * Created by IntelliJ IDEA.
 * User: luwei
 * Date: 2017/8/24
 * Time: 10:18
 */

use yii\widgets\LinkPager;

/* @var \app\models\Coupon[] $list */

$urlManager = Yii::$app->urlManager;
$this->title = '优惠券管理';
$this->params['active_nav_group'] = 7;
?>

<style>
    .table tbody tr td{
        vertical-align: middle;
    }
</style>

<div class="panel mb-3">
    <div class="panel-header"><?= $this->title ?></div>
    <div class="panel-body">
        <a class="btn btn-primary mb-3" href="<?= $urlManager->createUrl(['mch/berbo/edit']) ?>">添加优惠券</a>
        <table class="table table-bordered bg-white">
            <thead>
            <tr>
                <th>ID</th>
                <th>Izina</th>
                <th>EMAIL</th>
                <th>操作</th>
            </tr>
            </thead>
            <?php foreach ($list as $item) : ?>
                <tr>
                    <td><?= $item->id ?></td>
                    <td><?= $item->name ?></td>
                    <td><?= $item->email ?></td>
                    <td>
                        <a class="btn btn-sm btn-primary"
                           href="<?= $urlManager->createUrl(['mch/berbo/edit', 'id' => $item->id]) ?>">编辑</a>
                        <a class="btn btn-sm btn-primary"
                           href="<?= $urlManager->createUrl(['mch/berbo/send', 'id' => $item->id]) ?>">发放</a>
                        <a class="btn btn-sm btn-danger delete-confirm"
                           href="<?= $urlManager->createUrl(['mch/berbo/delete', 'id' => $item->id]) ?>">删除</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<script>
    $(document).on("click", ".delete-confirm", function () {
        var url = $(this).attr("href");
        $.myConfirm({
            content: "确认删除？",
            confirm: function () {
                $.myLoading();
                $.ajax({
                    url: url,
                    dataType: "json",
                    success: function (res) {
                        if (res.code == 0) {
                            location.reload();
                        } else {
                            $.myLoadingHide();
                            $.myAlert({
                                content: res.msg,
                            });
                        }
                    }
                });
            },
        });
        return false;
    });
</script>