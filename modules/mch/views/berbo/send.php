<?php
defined('YII_ENV') or exit('Access Denied');

/**
 * Created by IntelliJ IDEA.
 * User: luwei
 * Date: 2017/8/24
 * Time: 10:18
 */

use yii\widgets\LinkPager;

/* @var \app\models\Coupon $coupon */

$urlManager = Yii::$app->urlManager;
$this->title = '优惠券发放';
$this->params['active_nav_group'] = 7;
$returnUrl = Yii::$app->request->referrer;
if (!$returnUrl) {
    $returnUrl = $urlManager->createUrl(['mch/berbo/berbo']);
}
?>
<style>

    .user-list .user-item {
        text-align: center;
        width: 120px;
        border: 1px solid #e3e3e3;
        padding: 1rem 0;
        cursor: pointer;
        display: inline-block;
        vertical-align: top;
        margin: 0 1rem 1rem 0;
        border-radius: .15rem;
    }

    .user-list .user-item:hover {
        background: rgba(238, 238, 238, 0.54);
    }

    .user-list .user-item img {
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 999px;
        margin-bottom: 1rem;
    }

    .user-list .user-item.active {
        background: rgba(2, 117, 216, 0.69);
        color: #fff;
    }
</style>
<div class="panel mb-3" id="page">
    <div class="panel-header"><?= $this->title ?></div>
    <div class="panel-body">
        <form class="form auto-form" method="post" autocomplete="off" data-timeout="5" return="<?= $returnUrl ?>">
            <div class="form-body">
                <div class="form-group row">
                    <div class="form-group-label col-3 text-right">
                        <label class="">优惠券名称Izinaa</label>
                    </div>
                    <div class="col-9">
                        <?= $berbo->name ?>
                    </div>
                </div>
                <div class="form-group row">
                    <div class="form-group-label col-3 text-right">
                        <label class="">最低消费金额（元）</label>
                    </div>
                    <div class="col-9">
                        <?= $berbo->email ?>
                    </div>
                </div>

                <div class="form-group row">
                    <div class="form-group-label col-3 text-right">
                    </div>
                    <div class="col-9">
                        <a class="btn btn-primary auto-form-btn" href="javascript:">BIKA RERO</a>
                    </div>
                </div>
            </div>

        </form>
    </div>
</div>
<script>

    var app = new Vue({
        el: "#page",
        data: {
            user_list: false,
        }
    });

    $(document).on("change", "input[name=expire_type]", function () {
        $(".expire-type").hide();
        $(".expire-type-" + this.value).show();
    });
    $(document).on("change", "input[name='user_id_list[]']", function () {
        if ($(this).prop("checked")) {
            $(this).parents("label").addClass("active");
        } else {
            $(this).parents("label").removeClass("active");
        }
    });

    (function () {
        $.datetimepicker.setLocale('zh');
        $('#begin_time').datetimepicker({
            format: 'Y-m-d',
            onShow: function (ct) {
                this.setOptions({
                    maxDate: $('#end_time').val() ? $('#end_time').val() : false
                })
            },
            timepicker: false,
        });
        $('#end_time').datetimepicker({
            format: 'Y-m-d',
            onShow: function (ct) {
                this.setOptions({
                    minDate: $('#begin_time').val() ? $('#begin_time').val() : false
                })
            },
            timepicker: false,
        });
    })();


    function search_user() {
        var btn = $(".search-user-btn");
        var keyword = $(".search-user-keyword").val();
        btn.btnLoading("正在查找");
        $.ajax({
            url: "<?=$urlManager->createUrl(['mch/coupon/search-user'])?>",
            dataType: "json",
            data: {
                keyword: keyword,
            },
            success: function (res) {
                btn.btnReset();
                if (res.code == 0) {
                    app.user_list = res.data.list;
                }
            }
        });
    }


</script>