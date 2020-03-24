<?php

namespace app\modules\mch\controllers\permission;
use app\models\Cat;
use app\models\Goods;
use app\models\GoodsCat;
use app\models\GoodsPic;
use app\models\Option;
use app\models\Order;
use app\models\OrderDetail;
use app\models\OrderRefund;
use app\models\Purchase;
use app\models\PurchaseDetail;
use app\models\PurchaseGoods;
use app\models\SendPurchase;
use app\models\SendPurchaseDetail;
use app\models\Shop;
use app\models\Store;
use app\models\User;
use app\models\WechatApp;
use app\models\WechatTplMsgSender;
use app\models\YyOrder;
use app\modules\api\controllers\Controller;
use app\modules\api\models\ApiModel;
use app\modules\mch\events\goods\BaseAddGoodsEvent;
use app\modules\mch\models\GoodsForm;
use app\modules\mch\models\GoodsSearchForm;
use app\modules\mch\models\LevelListForm;
use app\modules\mch\models\OrderMessageForm;
use app\modules\mch\models\StoreDataForm;
use app\modules\user\models\mch\OrderSendForm;
use app\utils\PinterOrder;
use app\utils\Refund;
use Hejiang\Event\EventArgument;
use luweiss\wechat\Pay;
use luweiss\wechat\Wechat;
use Yii;
use yii\data\Pagination;


/**
 * Created by PhpStorm.
 * User: linwei
 * Date: 2020/3/11
 * Time: 9:57
 */
class AppController extends Controller
{
    public $limit = 10;
    public $page = 1;
    public $enableCsrfValidation = false;
    public function actionIndex()
    {
        $form = new StoreDataForm();
        $form->sign =$_REQUEST['sign'];
        $form->type =$_REQUEST['type'];
        return new \app\hejiang\BaseApiResponse($form->search());
    }
    //首页公告保存
    public function actionNotice()
    {

        $notice = trim($_REQUEST['notice']);
        Option::set('notice', $notice, 1, 'admin');
        return [
            'code' => 0,
            'msg' => '保存成功',
        ];
    }
    public function actionGetCatList($parent_id = 0)
    {
        $list = Cat::find()->select('id,name')->where(['is_delete' => 0, 'parent_id' => $parent_id, 'store_id' => '1'])->asArray()->all();
        return [
            'code' => 0,
            'data' => $list,
        ];
    }
    public function actionGetCatListAll()
    {
        $list = Cat::find()->select('id,name')->where(['is_delete' => 0,'store_id' => '1'])->asArray()->all();
        return [
            'code' => 0,
            'data' => $list,
        ];
    }
    /**
     * 获取顶级分类
     * @return array
     */
    public function actionGetcat()
    {
        $query=Cat::find()->alias("ct")
            ->where([
                'ct.parent_id'=>"0",
                'ct.is_show'=>"1",
                'ct.is_delete'=>"0",
            ]);
        $list=$query->select('ct.*')->asArray()->all();
        return [
            'code' => 0,
            'msg' => '操作成功',
            'list'=>$list
        ];
    }
    /**
     * 商品修改/保存
     * @param int $id
     * @return string
     */
    public function actionGoodsAppEdit($id = 0)
    {
        $goods = Goods::findOne(['id' => $id, 'store_id' =>'1', 'mch_id' => 0]);
        if (!$goods) {
            $goods = new Goods();
        }
        $levelForm = new LevelListForm();
        $levelList = $levelForm->getAllLevel();

        $form = new GoodsForm();
        if (Yii::$app->request->isPost) {
            $model =$_POST['model'];
            if ($model['quick_purchase'] == 0) {
                $model['hot_cakes'] = 0;
            }
            $model['store_id'] = "1";
            $form->attributes = $model;
            $form->attr = Yii::$app->request->post('attr');
            $form->goods_card = Yii::$app->request->post('goods_card');
            $form->full_cut = Yii::$app->request->post('full_cut');
            $form->integral = Yii::$app->request->post('integral');

            // 单规格会员价数据
            $attr_member_price_List = [];
            foreach ($levelList as $level) {
                $keyName = 'member' . $level['level'];
                $attr_member_price_List[$keyName] = Yii::$app->request->post($keyName);
            }
            $form->attr_member_price_List = $attr_member_price_List;

            $form->goods = $goods;
            $form->plugins = Yii::$app->request->post('plugins');
            return $form->save();
        }

        $searchForm = new GoodsSearchForm();
        $this->store->id="1";
        $searchForm->goods = $goods;
        $searchForm->store = $this->store;
        $list = $searchForm->search();
         $goods_pic_list_query=GoodsPic::find()->alias('pic')
         ->where(['pic.goods_id'=>$id,
             'is_delete'=>0
         ]);
        $goods_pic_list=$goods_pic_list_query->select('pic.*')->asArray()->all();
        $args = new EventArgument();
        $args['goods'] = $goods;
        Yii::$app->eventDispatcher->dispatch(new BaseAddGoodsEvent(), $args);
        $plugins = $args->getResults();
        return [
            'goods' => $list['goods'],
            'cat_list' => $list['cat_list'],
            'goods_pic_list'=>$goods_pic_list,
            'levelList' => $levelList,
            'postageRiles' => $list['postageRiles'],
            'card_list' => Yii::$app->serializer->encode($list['card_list']),
            'goods_card_list' => Yii::$app->serializer->encode($list['goods_card_list']),
            'goods_cat_list' => Yii::$app->serializer->encode($list['goods_cat_list']),
            'plugins' => $plugins
        ];
    }
    /**
     * 获取订单提示列表
     * @return array
     */
    public function actionOrder()
    {
        $form = new OrderMessageForm();
        $form->store_id = "1";
        $form->limit = 5;
        $form->is_read = 1;
        $arr = $form->search();
        return [
            'code' => 0,
            'msg' => '',
            'data' => $arr['list']
        ];
    }
    /**
     * 返回采购单数据
     * TODO 1，优化返回数据，商品名称 *数量  二级
     *
     * @param null $is_offline
     * @return string
     */
    public function actionPurchase($is_offline = null)
    {
        $parent_idi=$_REQUEST['parent_id'];
        $this->store_id='1';
                //获取采购商品集合
                $List = $this->getGoodsSaleTopList(null, null, 0, 10, $parent_idi);
                 $array = array();
                $array_goods_list = array();
                $s = 0;
                $order_detail_idarray = "";

               //对采购商品集合开始重组
                for ($i = 0; $i < count($List); $i++) {
                    $name = $List[$i]['name'];
                    $attr = $List[$i]['attr'];
                    $num = $List[$i]['num'];
                    $pic = $List[$i]['pic'];
                    $price = $List[$i]['price'];
                    $order_no=$List[$i]['order_no'];
                    $order_detail_idarray = $order_detail_idarray . ',' . $List[$i]['id'] .
                        $found_key = array_search($name . $attr, array_column($array, 'atname'));
                    if (is_numeric($found_key)) {
                        $array[$found_key]['num'] = $array[$found_key]['num'] + $num;
                        $array[$found_key]['order_detail_id'] = $array[$found_key]['order_detail_id'] . ',' . $List[$i]['id'];
                    } else {
                        $array[$s]['atname'] = $name . $attr;
                        $array[$s]['good_name'] = $name;
                        $array[$s]['good_attr'] = $attr;
                        $array[$s]['num'] = $num;
                        $array[$s]['pic'] = $pic;
                        $array[$s]['price'] = $price;
                        $array[$s]['order_no'] = $order_no;
                        $array[$s]['order_detail_id'] = $List[$i]['id'];
                        $s++;
                    }
//                    $Order_detail = new OrderDetail();
//                    $Order_detail->updateAll(['is_true' => 1], ['id' =>  $List[$i]['id']]);
                }

                $totalnum = 0;
                //发货清单商品
                $PurchaseDetailarray = array();
                $PurchaseDetailarray['goods']=array();
                $u = 0;
                $s=0;
                for ($a = 0; $a < count($array); $a++) {

                    $totalnum = $totalnum + $array[$a]['num'];
                    //通过商品名称查询商品是否已近在数组
                    $found_keys = array_search($array[$a]['good_name'], array_column($PurchaseDetailarray['goods'], 'atname'));
                    if (is_numeric($found_keys)) {
                        //如果商品存在于数组中那么追加id
                        $goodsDetailList=array();
                        $goodsDetailList[$s]['good_name']=$array[$a]['good_name'];
                        $goodsDetailList[$s]['good_attr']=$array[$a]['good_attr'];
                        $goodsDetailList[$s]['num']=$array[$a]['num'];
                        $goodsDetailList[$s]['pic']=$array[$a]['pic'];
                        $goodsDetailList[$s]['price']=$array[$a]['price'];
                        $goodsDetailList[$s]['order_no']=$array[$a]['order_no'];
                        $goodsDetailList[$s]['order_detail_id']=$array[$a]['order_detail_id'];
                        $PurchaseDetailarray['goods'][$found_keys]['purchase_goods_detail'] =array_merge($PurchaseDetailarray['goods'][$found_keys]['purchase_goods_detail'],$goodsDetailList);
                    } else {
                        $goodsDetailList=array();
                        $goodsDetailList[$u]['good_name']=$array[$a]['good_name'];
                        $goodsDetailList[$u]['good_attr']=$array[$a]['good_attr'];
                        $goodsDetailList[$u]['num']=$array[$a]['num'];
                        $goodsDetailList[$u]['pic']=$array[$a]['pic'];
                        $goodsDetailList[$u]['price']=$array[$a]['price'];
                        $goodsDetailList[$u]['order_no']=$array[$a]['order_no'];
                        $goodsDetailList[$u]['order_detail_id']=$array[$a]['order_detail_id'];
                        $PurchaseDetailarray['goods'][$s]['atname'] = $array[$a]['good_name'];
                        $PurchaseDetailarray['goods'][$s]['good_name'] = $array[$a]['good_name'];
                        $PurchaseDetailarray['goods'][$s]['pic'] = $array[$a]['pic'];
                        $PurchaseDetailarray['goods'][$s]['purchase_goods_detail'] =$goodsDetailList;
                        $s++;

                    }
                }

        return [
            "code" => "0",
            "msg" => "查询数据成功",
            'list' => $PurchaseDetailarray,
        ];



    }
    /**
     * 缺货处理
     */
    public function actionShortage()
    {
        //缺货商品订单详情id
        $order_detail_id = $_REQUEST['order_detail_id'];
        $vegetables = explode(",", $order_detail_id);
        if (count($vegetables) < 2) {
            $query = OrderDetail::find()->alias("od")
                ->leftJoin(['o' => Order::tableName()], 'od.order_id=o.id')
                ->leftJoin(['re'=>OrderRefund::tableName()],'od.id=re.order_detail_id')
                ->where([
                    'od.id' => $order_detail_id,
                    'o.is_delete' => "0",
                    'o.is_send' => "0",
                    'o.is_confirm' => "0",
                    'o.type' => Order::ORDER_TYPE_STORE,
                    'o.apply_delete' => "0",
                    'o.is_pay' => Order::IS_PAY_TRUE,
                    'o.pay_type' => '1',
                    'o.store_id'=>'1'
                ]);

            $list = $query->select('od.id as order_detail_id,od.total_price,o.id,o.order_no,o.user_id,re.id as orderrefundid')->asArray()->all();

            if(!empty($list[0]['orderrefundid'])||empty($list))
            {
                return ['code'=>1,
                    'msg'=>'商品已近做过缺货处理，请勿重复提交!'];
            }
            $strs="QWERTYUIOPASDFGHJKLZXCVBNM1234567890qwertyuiopasdfghjklzxcvbnm";
             $name=substr(str_shuffle($strs),mt_rand(0,strlen($strs)-11),5);
            if (isset($list)) {
                //售后订单
                $OrderRefund = new OrderRefund();
                $OrderRefund->addtime = time();
                $OrderRefund->store_id = '1';
                $OrderRefund->order_detail_id = $list[0]['order_detail_id'];
                $OrderRefund->order_refund_no = $list[0]['order_no'].$name;
                $OrderRefund->refund_price = $list[0]['total_price'];
                $OrderRefund->type = '1';
                $OrderRefund->user_id = $list[0]['user_id'];
                $OrderRefund->status = '1';
                $OrderRefund->is_delete = '0';
                $OrderRefund->is_agree = '1';
                $OrderRefund->order_id=$list[0]['id'];
                $OrderRefund->desc='商品缺货';
                $OrderRefund->save();
                $order = Order::findOne($OrderRefund->order_id);
                $res =$this->wxRefund($order, $list[0]['total_price'], $OrderRefund->order_refund_no);
                $OrderD=new OrderDetail();
                $order=new Order();
                $OrderD->updateAll(['is_delete'=>1],['id'=>$list[0]['order_detail_id']]);
                return ['code'=>0,
                    'msg'=>'缺货受理成功!'];
            }else
                {
                    return ['code'=>1,
                          'msg'=>'缺货受理失败!'];
                }
        } else {
            $query = OrderDetail::find()->alias("od")
                ->leftJoin(['o' => Order::tableName()], 'od.order_id=o.id')
                ->leftJoin(['re'=>OrderRefund::tableName()],'od.id=re.order_detail_id')
                ->where([
                    'in', 'od.id', $vegetables,
                    'o.is_delete' => "0",
                    'o.is_send' => "0",
                    'o.is_confirm' => "0",
                    'o.type' => Order::ORDER_TYPE_STORE,
                    'o.apply_delete' => "0",
                    'o.is_pay' => Order::IS_PAY_TRUE,
                    'o.pay_type' => '1',
                ]);
            $list = $query->select('od.id as order_detail_id,od.total_price,o.id,o.order_no,o.user_id')->asArray()->all();
            if (empty($list)) {
                for ($i = 0; $i < count($list); $i++) {
                    if(empty($list[0]['orderrefundid'])||empty($list))
                    {
                        return ['code'=>1,
                            'msg'=>'商品已近做过缺货处理，请勿重复提交!'];
                    }
                    //售后订单
                    $OrderRefund = new OrderRefund();
                    $OrderRefund->addtime = time();
                    $OrderRefund->store_id = 1;
                    $OrderRefund->order_detail_id = $list[$i]['order_detail_id'];
                    $OrderRefund->order_refund_no = $list[$i]['order_no'];
                    $OrderRefund->refund_price = $list[$i]['total_price'];
                    $OrderRefund->type = 1;
                    $OrderRefund->user_id = $list[$i]['user_id'];
                    $OrderRefund->status = 1;
                    $OrderRefund->is_delete = 0;
                    $OrderRefund->is_agree = 1;
                    $OrderRefund->order_id=$list[0]['id'];
                    $OrderRefund->desc='商品缺货';
                    $OrderRefund->save();
                    $order = Order::findOne($list[$i]['order_detail_id']);
                    $res = Refund::refund($order, $order->order_no, $list[0]['total_price']);
                    $OrderD=new OrderDetail();
                    $OrderD->updateAll(['is_delete'=>1],['id'=>$list[0]['order_detail_id']]);
//                    $PurchaseDetail=new PurchaseDetail();
//                    $PurchaseDetail->updateAll(['status'=>3],['id'=>$id]);
                }
            }else
                {
                    return ['code'=>0,
                        'msg'=>'缺货受理失败!'];
                }
        }
    }

    /**
     * 微信支付退款
     * @param $order
     * @param $refundFee
     * @param $orderRefundNo
     * @param null $refund_account
     * @return array|bool
     */
    public function wxRefund($order, $refundFee, $orderRefundNo)
    {

        if (isset($order->pay_price)) {
            $payPrice = $order->pay_price;
        } else {
            // 联合订单支付的总额
            $payPrice = $order->price;
        }
        $store = Store::findOne($order->store_id);
        if (!$store) {
           return ['code'=>0,
                'msg'=>'缺货受理失败!'];
        }

        $wechat_app = WechatApp::findOne($store->wechat_app_id);
        if (!$wechat_app) {
            return ['code'=>0,
                'msg'=>'缺货受理失败!'];
        }
         $data = [
            'out_trade_no' => $order->order_no,
            'out_refund_no' =>$orderRefundNo,
            'total_fee' => $payPrice * 100,
            'refund_fee' => $refundFee * 100,
            'refund_desc'=>'商品缺货退款处理',
        ];
        $wechat = new \luweiss\wechat\Wechat([
            'appId' => $wechat_app->app_id,
            'appSecret' =>$wechat_app->app_secret,
            'mchId' => $wechat_app->mch_id,
            'apiKey' =>$wechat_app->key,
            'certPem' =>'/www/wwwroot/shop.yaorongda.com/shop/vendor/luweiss/wechat/src/apiclient_cert.pem',
            'keyPem' =>'/www/wwwroot/shop.yaorongda.com/shop/vendor/luweiss/wechat/src/apiclient_key.pem',
            'cachePath' =>\Yii::$app->runtimePath . '/cache'
        ]);
        $res = $wechat->pay->refund($data);
        if (!$res) {
            return [
                'code' => 1,
                'msg' => '订单缺货处理失败，退款失败，服务端配置出错',
            ];
        }
        if ($res['return_code'] != 'SUCCESS') {
            return [
                'code' => 1,
                'msg' => '订单缺货处理失败，退款失败，' . $res['return_msg'],
                'res' => $res,
            ];
        }
        if ($res['result_code'] != 'SUCCESS') {
            $refundQuery = $wechat->pay->refundQuery($order->order_no);
            if ($refundQuery['return_code'] != 'SUCCESS') {
                return [
                    'code' => 1,
                    'msg' => '订单缺货处理失败，退款失败，' . $refundQuery['return_msg'],
                    'res' => $refundQuery,
                ];
            }
            if ($refundQuery['result_code'] == 'FAIL') {
                return [
                    'code' => 1,
                    'msg' => '订单缺货处理失败，退款失败，' . $res['err_code_des'],
                    'res' => $res,
                ];
            }
            if ($refundQuery['result_code'] != 'SUCCESS') {
                return [
                    'code' => 1,
                    'msg' => '订单缺货处理失败，退款失败，' . $refundQuery['err_code_des'],
                    'res' => $refundQuery,
                ];
            }
            if ($refundQuery['refund_status_0'] != 'SUCCESS') {
                return [
                    'code' => 1,
                    'msg' => '订单缺货处理失败，退款失败，' . $refundQuery['err_code_des'],
                    'res' => $refundQuery,
                ];
            }
        }
        return $res;
    }
    /**
     * 查询发货单收货信息
     */
    public function actionSendPurchase()
    {
       $parent_id=$_REQUEST['parent_id'];
       $shop_id=$_REQUEST['shop_id'];
      return $this->getGoodsSaleTopLists('','','','',$parent_id,$shop_id);
    }
    /**
     * 查询采购单记录
     */
    public function actionGetPurchaseinfo()
    {
        $y=@$_REQUEST['y'];
        $m=@$_REQUEST['m'];
        $parent_id=@$_REQUEST['parent_id'];
        $query = Purchase::find()->alias('od')
            ->where([
                'od.status'=>1,
                'od.parent_id'=>$parent_id
            ]);
        if ($y!== null&&$m!==null) {
            $query->andWhere(['>=', 'od.addtime', $this->mFristAndLast($y,$m)['firstday']]);
            $query->andWhere(['<=', 'od.addtime', $this->mFristAndLast($y,$m)['lastday']]);
        }
        $Purchaseinfo= $query->select('od.*')
            ->orderBy('od.addtime asc')
            ->asArray()->all();
        $GetPurchaseinfo=array();
        $GetPurchaseinfo['totalnum']=0;
        for($i=0;$i<count($Purchaseinfo);$i++)
        {
            $query1=PurchaseDetail::find()->alias('pd')
                ->where(['pd.purchase_id'=>$Purchaseinfo[$i]['id']]);
            $PurchaseDetail=$query1->select('pd.*')
                ->asArray()->all();
            $GetPurchaseinfo['Purchase'][$i]=$Purchaseinfo[$i];
            $GetPurchaseinfo['Purchase'][$i]['PurchaseDetail']=$PurchaseDetail;
            $GetPurchaseinfo['totalnum']=$GetPurchaseinfo['totalnum']+$Purchaseinfo[$i]['Totalnum'];
            $GetPurchaseinfo['totalnumBars']=count($Purchaseinfo);
        }
        return ["code"=>"0",
            "msg"=>"查询数据成功",
            'totalnum'=>$GetPurchaseinfo['totalnum'],
            'totalnumBars'=>$GetPurchaseinfo['totalnumBars'],
            'list' =>$GetPurchaseinfo];
    }

    function mFristAndLast($y = "", $m = ""){
        if ($y == "") $y = date("Y");
        if ($m == "") $m = date("m");
        $m = sprintf("%02d", intval($m));
        $y = str_pad(intval($y), 4, "0", STR_PAD_RIGHT);

        $m>12 || $m<1 ? $m=1 : $m=$m;
        $firstday = strtotime($y . $m . "01000000");
        $firstdaystr = date("Y-m-01", $firstday);
        $lastday = strtotime(date('Y-m-d 23:59:59', strtotime("$firstdaystr +1 month -1 day")));

        return array(
            "firstday" => $firstday,
            "lastday" => $lastday
        );
    }
    /**
 * 判断之前是否存在未完成的采购单
 */
    public function isPurchaseTrue($parent_idi)
    {
        $Purchasequery=Purchase::find()->alias('od')
            ->where([
                'od.status'=>0,
                'od.parent_id'=>$parent_idi,
            ]);
        $Purchasequeryarray=$Purchasequery->select('od.*')
            ->asArray()->all();
        if(empty($Purchasequeryarray)){
            return false;
        }
        $PurchaseGoodsquery=PurchaseGoods::find()->alias('gd')
            ->where([
                'gd.purchase_id'=>$Purchasequeryarray[0]['id'],
                'gd.state'=>0,
            ]);
        //采购单商品名称列表
        $PurchaseGoodsqueryarray=$PurchaseGoodsquery->select('gd.*')
            ->orderBy('gd.addtime asc')
            ->asArray()->all();
           $array=array();
           if(count($PurchaseGoodsqueryarray)<2)
           {

             $PurchaseDetailquery=PurchaseDetail::find()->alias('o')
                ->where([
                    'o.id'=>$PurchaseGoodsqueryarray[0]['purchase_detail_id'],
                    'o.status'=>"0",
                ]);
             $PurchaseDetailarray=$PurchaseDetailquery->select('o.*')
                ->orderBy('o.addtime asc')
                ->asArray()->all();

              $array['goods']=$PurchaseGoodsqueryarray;
              $array['goodsdetail']=$PurchaseDetailarray;
             return $array;
        }else
            {
                for($i=0;$i<count($PurchaseGoodsqueryarray);$i++){
                $vegetables = explode(",", $PurchaseGoodsqueryarray[$i]['purchase_detail_id']);
                if(count($vegetables)>1){
                $PurchaseDetailquery=PurchaseDetail::find()->alias('o')
                    ->where([
                        'in','o.id',$vegetables,

                    ])->andWhere(['o.status'=>'0']);
                $PurchaseDetailarray=$PurchaseDetailquery->select('o.*')
                    ->orderBy('o.addtime asc')
                    ->asArray()->all();

                $array['goods'][$i]=$PurchaseGoodsqueryarray[$i];
                $array['goods'][$i]['purchase_goods_detail']=$PurchaseDetailarray;
                }
                else
                    {
                        $PurchaseDetailquery=PurchaseDetail::find()->alias('o')
                            ->where([
                                'o.status'=>"0",
                                'o.id'=>$PurchaseGoodsqueryarray[$i]['purchase_detail_id'],
                            ]);
                        $PurchaseDetailarray=$PurchaseDetailquery->select('o.*')
                            ->orderBy('o.addtime asc')
                            ->asArray()->all();
                        $array['goods'][$i]=$PurchaseGoodsqueryarray[$i];
                        $array['goods'][$i]['purchase_goods_detail']=$PurchaseDetailarray;
                    }
                }
                return $array;
            }
    }
    /**
     * 判断之前是否存在未完成的发货单
     */
    public function isSendPurchaseTrue($parent_idi,$shop_id)
    {
        $query = SendPurchase::find()->alias('od')
            ->leftJoin(['o' => SendPurchaseDetail::tableName()], 'od.id=o.send_purchase_id')
            ->where([
                'od.state'=>0,
                'od.parent_id'=>$parent_idi,
                'o.state'=>0,
                'od.shop_id'=>$shop_id
            ]);
        return $query->select('o.*')
            ->orderBy('o.addtime asc')
            ->asArray()->all();
    }
    /**
     * 单商品采购/批量商品采购完成
     */
    public function actionPurchaseComplete()
    {
        //商品id
        $order_detail_id=$_REQUEST['order_detail_id'];
        //single 单个 All 所有
        $type=$_REQUEST['type'];
        if($type=='single')
        {

            return $result=$this->HandlePurchase($order_detail_id);
        }else
            {
                return $this->HandlePurchaseAll($order_detail_id);
                //批量商品采购完成
            }
    }
    /**
     * 全部采购处理
     * @param $purchase_id
     */
    public function HandlePurchaseAll($order_detail_id)
    {
        $vegetables = explode(",", $order_detail_id);
        if($vegetables<2)
        {
            $query = OrderDetail::find()->alias('od')
            ->where([
                'od.id'=>$order_detail_id,
                'od.is_delete'=>0
            ]);
            $OrderDetaillist= $query->select('od.*')
                ->orderBy('od.addtime asc')
                ->asArray()->all();
            //更新订单详情采购记录
            for($i=0;$i<count($OrderDetaillist);$i++)
            {
                $OrderD=new OrderDetail();
                $OrderD->updateAll(['is_purchase'=>1],['id'=>$OrderDetaillist[$i]['id']]);
            }
            return
                [
                    "code"=>"0",
                    "msg"=>"采购处理完成!",
                ];
        }else
            {
                $query = OrderDetail::find()->alias('od')
                    ->where([
                        'in','od.id',$vegetables,
                        'od.is_delete'=>0
                    ]);
                $OrderDetaillist= $query->select('od.*')
                    ->orderBy('od.addtime asc')
                    ->asArray()->all();
                //更新订单详情采购记录
                for($i=0;$i<count($OrderDetaillist);$i++)
                {
                    $OrderD=new OrderDetail();
                    $OrderD->updateAll(['is_purchase'=>1],['id'=>$OrderDetaillist[$i]['id']]);
                }
                return
                    [
                        "code"=>"0",
                        "msg"=>"采购处理完成!",
                    ];
            }
    }
    /**
     * 采购商品数据处理单件
     * @param $id
     * @param $order_detail_id
     * @param $complete_num
     * @return array
     */
    public function HandlePurchase($order_detail_id)
    {
        $vegetables = explode(",", $order_detail_id);
        if(count($vegetables)<2){
        $query = OrderDetail::find()->alias('od')
            ->where([
                'od.id'=>$vegetables[0],
                'od.is_delete'=>0
            ]);
            $OrderDetaillist= $query->select('od.*')
                ->orderBy('od.addtime asc')
                ->asArray()->all();
            //处理订单详情采购状态
            for($i=0;$i<count($OrderDetaillist);$i++)
            {
                    $OrderD=new OrderDetail();
                    $OrderD->updateAll(['is_purchase'=>1],['id'=>$OrderDetaillist[$i]['id']]);
            }
            return
                [
                    "code"=>"0",
                    "msg"=>"采购处理完成!",
                ];
        }else
            {
                    $query = OrderDetail::find()->alias('od')
                        ->where([
                            'in','od.id',$vegetables,
                            'od.is_delete'=>0
                        ]);
                $OrderDetaillist= $query->select('od.*')
                    ->orderBy('od.addtime asc')
                    ->asArray()->all();
                //处理订单详情采购状态
                for($i=0;$i<count($OrderDetaillist);$i++)
                {
                        $OrderD=new OrderDetail();
                        $OrderD->updateAll(['is_purchase'=>1],['id'=>$OrderDetaillist[$i]['id']]);
                }
                //处理采购单详情采购状态
                return
                    [
                        "code"=>"0",
                        "msg"=>"采购处理完成!",
                    ];
            }

    }
    public function getpurchasedetail($id)
    {
        $query = PurchaseDetail::find()->alias('od')
            ->where([
                'od.status'=>0,
                'od.id'=>$id,
            ]);
        return $query->select('od.*')
            ->orderBy('od.addtime asc')
            ->asArray()->all();
    }
    /**
     *查询自提门店信息
     */
    public function actionGetStore()
    {

        $query = Shop::find()->alias('od')
            ->where([
                'od.is_delete'=>0,
            ]);
        $GetStoreList=$query->select('od.*')
            ->orderBy('od.addtime asc')
            ->asArray()->all();
        return
            [
                "code"=>"0",
                "msg"=>"查询成功",
                "list"=>$GetStoreList
            ];
    }
    /**
     * 查询发货单商品清单
     */
    public function actionSendPurchaseDetail()
    {
        $order_num=$_REQUEST['order_num'];
        $order_num_array= explode(",", $order_num);
        $OrderDetaillistresponse=array();
        for($i=0;$i<count($order_num_array);$i++){
        $query = OrderDetail::find()->alias('od')
            ->leftJoin(['o' => Order::tableName()], 'od.order_id=o.id')
            ->leftJoin(['g' => Goods::tableName()], 'od.goods_id=g.id')
            ->where([
                'o.order_no'=>$order_num_array[$i],
                'od.is_delete'=>0
            ]);
            $OrderDetaillistresponse[$order_num_array[$i]]= $query->select('od.*,g.name as goodname,o.order_no,o.name as sname,o.address,o.mobile,o.remark,o.content,o.total_price,o.pay_price,o.express_price')
            ->orderBy('od.addtime asc')
            ->asArray()->all();
        }
        return
            [
                "code"=>"0",
                "msg"=>"查询成功",
                "list"=>$OrderDetaillistresponse
            ];
    }
    /**
     * 确认发货
     */
    public function actionConfirmSend()
    {
        $order_no=$_REQUEST['order_no'];
        $order_id_array= explode(",",$order_no);

        //判断一个收货人是否有多笔订单
        if(count($order_id_array)<2)
        {
            $order = Order::find()->alias('od')
                ->where([
                   'od.order_no'=>$order_no,
                    'od.is_delete' => 0,
                    'od.store_id' => '1',
                    'od.mch_id'=>'0'
                ]);
            $OrderDetaillist= $order->select('*')
                ->orderBy('od.addtime asc')
                ->asArray()->all();

            if (!$OrderDetaillist) {
                return [
                    'code' => 1,
                    'msg' => '订单不存在或已删除',
                ];
            }
            for($i=0;$i<count($OrderDetaillist);$i++){
                if ($OrderDetaillist[$i]['is_pay'] == 0 && $OrderDetaillist[$i]['pay_type']!= 2) {
                    return [
                        'code'=>1,
                        'msg'=>'订单未支付'
                    ];
                }
                $OrderDetaillist[$i]['is_send']=1;
                $OrderDetaillist[$i]['send_time']= time();
                if (Order::updateAll(['is_send'=>1,'send_time'=>time()],['id'=>$OrderDetaillist[$i]['id']])) {
                    try {
                        $wechat_tpl_meg_sender = new WechatTplMsgSender('1',$OrderDetaillist[$i]['id'] , $this->getWechat());
                        $wechat_tpl_meg_sender->sendMsg();
                    } catch (\Exception $e) {
                        Yii::warning($e->getMessage());
                    }

                } else {
                    return [
                        'code' => 1,
                        'msg' => '操作失败',
                    ];
                }
            }
            //保存发货记录
            $SendPurchase=new SendPurchase();
            $SendPurchase->state=0;
            $SendPurchase->addtime=time();
            $SendPurchase->order_no=$order_no;
            $SendPurchase->parent_id=12;
            $SendPurchase->save();
            return [
                'code' => 0,
                'msg' => '发货成功',
            ];
        }else{
        $order = Order::find()->alias('od')
        ->where([
            'in','od.order_no',$order_id_array,
            'od.is_delete' => 0,
            'od.store_id' => '1',
            'od.mch_id'=>'0'
        ]);
        $OrderDetaillist= $order->select('*')
            ->orderBy('od.addtime asc')
            ->asArray()->all();
        if (!$OrderDetaillist) {
            return [
                'code' => 1,
                'msg' => '订单不存在或已删除',
            ];
        }
        for($i=0;$i<count($OrderDetaillist);$i++){
        if ($OrderDetaillist[$i]['is_pay'] == 0 && $OrderDetaillist[$i]['pay_type']!= 2) {
            return [
                'code'=>1,
                'msg'=>'订单未支付'
            ];
        }
        $OrderDetaillist[$i]['is_send']=1;
        $OrderDetaillist[$i]['send_time']= time();
        if (Order::updateAll(['is_send'=>1,'send_time'=>time()],['id'=>$OrderDetaillist[$i]['id']])) {
            try {
                $wechat_tpl_meg_sender = new WechatTplMsgSender('1',$OrderDetaillist[$i]['id'] , $this->getWechat());
                $wechat_tpl_meg_sender->sendMsg();
            } catch (\Exception $e) {
                Yii::warning($e->getMessage());
            }

        } else {
            return [
                'code' => 1,
                'msg' => '操作失败',
            ];
        }
        }
        //保存发货记录
            $SendPurchase=new SendPurchase();
            $SendPurchase->state=0;
            $SendPurchase->addtime=time();
            $SendPurchase->order_no=$order_no;
            $SendPurchase->parent_id=12;
            $SendPurchase->save();
        return [
            'code' => 0,
            'msg' => '发货成功',
        ];
        }
    }
    /**
     * 发货完成处理发货单状态
     */
//    public function completeSendPurchase($id,$send_purchase_id)
//    {
//        SendPurchaseDetail::updateAll(['state'=>'1','updatetime'=>time()],['order_detail_id'=>$id]);
//        $query = SendPurchaseDetail::find()->alias('od')
//            ->leftJoin(['o' => SendPurchase::tableName()], 'od.send_purchase_id=o.id')
//            ->where([
//                'od.send_purchase_id'=>$send_purchase_id,
//                'od.state'=>0,
//                'o.state'=>0
//            ]);
//        $OrderDetaillist= $query->select('od.*')
//            ->orderBy('od.addtime asc')
//            ->asArray()->all();
//        if(empty($OrderDetaillist))
//        {
//            SendPurchase::updateAll(['state'=>'1','updatetime'=>time()],['id'=>$send_purchase_id]);
//        }else
//            {
//
//            }
//    }
    /**
     * 查询发货记录
     */
    public function actionGetSendPurchase()
    {
        $y=@$_REQUEST['y'];
        $m=@$_REQUEST['m'];
        $parent_id=@$_REQUEST['parent_id'];
        $query1=SendPurchase::find()->alias('sp')
            ->where(
                ['sp.parent_id'=>$parent_id]
            );
        $SendList= $query1->select('o.*')
            ->orderBy('o.send_time asc')
            ->asArray()->all();
        for($i=0;$i<count($SendList);$i++)
        {
            $query = Order::find()->alias('od')
                ->where([
                    'o.is_send'=>1,
                    'c.parent_id'=>$parent_id
                ]);
            if ($y!== null&&$m!==null) {
                $query->andWhere(['>=', 'o.send_time', $this->mFristAndLast($y,$m)['firstday']]);
                $query->andWhere(['<=', 'o.send_time', $this->mFristAndLast($y,$m)['lastday']]);
            }
            $Purchaseinfo= $query->select('o.*')
                ->orderBy('o.send_time asc')
                ->asArray()->all();
        }


        $GetPurchaseinfo=array();
        $GetPurchaseinfo['totalnum']=0;
        $Purchaseinfos=array();
        $Purchaseinfos['id']=$Purchaseinfo[0]['id'];
        $Purchaseinfos['addtime']=$Purchaseinfo[0]['send_time'];
        $Purchaseinfos['updatetime']=$Purchaseinfo[0]['send_time'];
        $Purchaseinfos['state']=1;
        $Purchaseinfos['parent_id']=$parent_id;
        $Purchaseinfos['shop_id']=$Purchaseinfo[0]['shop_id'];
        $PurchaseDetail=array();
        for($i=0;$i<count($Purchaseinfo);$i++)
        {

            /******************************************/

            $PurchaseDetail[$i]['id']=$Purchaseinfo[$i]['id'];
            $PurchaseDetail[$i]['addtime']=$Purchaseinfo[$i]['send_time'];
            $PurchaseDetail[$i]['updatetime']=$Purchaseinfo[$i]['send_time'];
            $PurchaseDetail[$i]['order_no']=$Purchaseinfo[$i]['order_no'];
            $PurchaseDetail[$i]['name']=$Purchaseinfo[$i]['name'];
            $PurchaseDetail[$i]['address']=$Purchaseinfo[$i]['address'];
            $PurchaseDetail[$i]['mobile']=$Purchaseinfo[$i]['address'];
            $PurchaseDetail[$i]['state']=1;

        }
        $GetPurchaseinfo['Purchase']=$Purchaseinfos;
        $GetPurchaseinfo['Purchase']['totalnumBars']=count($PurchaseDetail);
        $GetPurchaseinfo['Purchase']['PurchaseDetail']=$PurchaseDetail;
        return ["code"=>"0",
            "msg"=>"查询数据成功",
            'list' =>$GetPurchaseinfo];

    }
     /**
     *TODO 不包含快递订单
     * 查询待发货的商品
     * @param null $startTime
     * @param null $endTime
     * @param int $mchId
     * @param int $limit
     * @param $parent_id
     * @param $good_id
     * @return array|\yii\db\ActiveRecord[]
     */
    public function getGoodsSaleTopLists($startTime = null, $endTime = null, $mchId = 0, $limit = 10,$parent_id,$shop_id)
    {
        $cat=Cat::find()->alias('ct')
            ->where(['ct.parent_id'=>$parent_id,
                'is_delete'=>'0']);
        $list=$cat->select('ct.*')
            ->asArray()->all();
        //订单商品详情，订单表，商品表
            if (empty($list)&&$shop_id !== null) {
                $query = OrderDetail::find()->alias('od')
                    ->leftJoin(['o' => Order::tableName()], 'od.order_id=o.id')
                    ->leftJoin(['g' => Goods::tableName()], 'od.goods_id=g.id')
                    ->leftJoin(['s' => GoodsCat::tableName()], 'g.id=s.goods_id')
                    ->leftJoin(['c' => Cat::tableName()], 's.cat_id=c.id')
                    ->leftJoin(['u' => User::tableName()], 'u.id=o.user_id')
                    ->where([
                        'g.store_id' => '1',
                        'g.is_delete' => "0",
                        'o.is_delete' => "0",
                        'od.is_delete' => "0",
                        'o.is_send' => "0",
                        'o.is_confirm' => "0",
                        'g.mch_id' => $mchId,
                        'o.mch_id' => $mchId,
                        'o.type' => Order::ORDER_TYPE_STORE,
                        'o.apply_delete' => "0",
                        'o.is_pay' => Order::IS_PAY_TRUE,
                        'o.pay_type' => '1',
                        's.is_delete' => "0",
                        'c.id' => $parent_id,
                        'od.is_purchase' => 1,
                        'o.is_offline' => 1,
                        'o.shop_id' => $shop_id,
                    ]);
                $send1list = $query->select('o.*,od.id as order_detail_id,u.nickname')
                    ->orderBy('od.addtime asc')
                    ->asArray()->all();
                $array = array();
                $s = 0;
                for ($i = 0; $i < count($send1list); $i++) {
                    $tick = $send1list[$i]['name'] . $send1list[$i]['mobile'];
                    $found_key = array_search($tick, array_column($array, 'tick'));
                    if (is_numeric($found_key)) {
                        $array[$found_key]['order_no'] = $array[$found_key]['order_no'] . ',' . $send1list[$i]['order_no'];
                        $array[$found_key]['order_detail_id'] = $array[$found_key]['order_detail_id'] . ',' . $send1list[$i]['order_detail_id'];
                    } else {
                        $array[$s]['tick'] = $send1list[$i]['name'] . $send1list[$i]['mobile'];
                        $array[$s]['id'] = $send1list[$i]['id'];
                        $array[$s]['order_no'] = $send1list[$i]['order_no'];
                        $array[$s]['order_detail_id'] = $send1list[$i]['order_detail_id'];
                        $array[$s]['total_price'] = $send1list[$i]['total_price'];
                        $array[$s]['pay_price'] = $send1list[$i]['pay_price'];
                        $array[$s]['express_price'] = $send1list[$i]['express_price'];
                        $array[$s]['name'] = $send1list[$i]['name'];
                        $array[$s]['nickname'] = $send1list[$i]['nickname'];
                        $array[$s]['mobile'] = $send1list[$i]['mobile'];
                        $array[$s]['address'] = $send1list[$i]['address'];
                        $array[$s]['is_offline'] = $send1list[$i]['is_offline'];
                        $array[$s]['shop_id'] = $send1list[$i]['shop_id'];
                        $s++;
                    }
                    $Order_detail = new OrderDetail();
                    $Order_detail->updateAll(['is_true' => 1], ['id' => $array[$s]['order_detail_id']]);

                }
                if (!empty($array)) {
                    //保存发货记录
                    $SendPurchase = new SendPurchase();
                    $SendPurchase->addtime = time();
                    $SendPurchase->state = '0';
                    $SendPurchase->parent_id = $parent_id;
                    $SendPurchase->shop_id = $shop_id;
                    $SendPurchase->save();
                    $id = $SendPurchase->attributes['id'];
                    $res = array();
                    for ($a = 0; $a < count($array); $a++) {
                        $SendPurchaseDetail = new SendPurchaseDetail();
                        $SendPurchaseDetail->send_purchase_id = $id;
                        $SendPurchaseDetail->addtime = time();
                        $SendPurchaseDetail->address = $array[$a]['address'];
                        $SendPurchaseDetail->name = $array[$a]['name'];
                        $SendPurchaseDetail->nickname = $array[$a]['nickname'];
                        $SendPurchaseDetail->mobile = $array[$a]['mobile'];
                        $SendPurchaseDetail->state = 0;
                        $SendPurchaseDetail->order_no = $array[$a]['order_no'];
                        $SendPurchaseDetail->order_detail_id = $array[$a]['order_detail_id'];
                        $SendPurchaseDetail->save();
                        $res[$a] = $SendPurchaseDetail;
                    }
                    return [
                        "code" => "0",
                        "msg" => "查询成功!",
                        "list" => $res,
                        "totalnum" => count($array),
                    ];
                } else {
                    return [
                        "code" => "1",
                        "msg" => "不存在待发货的订单!",
                    ];
                }
            } else {
                $query = OrderDetail::find()->alias('od')
                    ->leftJoin(['o' => Order::tableName()], 'od.order_id=o.id')
                    ->leftJoin(['g' => Goods::tableName()], 'od.goods_id=g.id')
                    ->leftJoin(['s' => GoodsCat::tableName()], 'g.id=s.goods_id')
                    ->leftJoin(['c' => Cat::tableName()], 's.cat_id=c.id')
                    ->leftJoin(['u' => User::tableName()], 'u.id=o.user_id')
                    ->where([
                        'g.store_id' => '1',
                        'g.is_delete' => "0",
                        'o.is_delete' => "0",
                        'od.is_delete' => "0",
                        'o.is_send' => "0",
                        'o.is_confirm' => "0",
                        'g.mch_id' => $mchId,
                        'o.mch_id' => $mchId,
                        'o.type' => Order::ORDER_TYPE_STORE,
                        'o.apply_delete' => "0",
                        'o.is_pay' => Order::IS_PAY_TRUE,
                        'o.pay_type' => '1',
                        's.is_delete' => "0",
                        'c.parent_id' => $parent_id,
                        'od.is_purchase' => 1,
                        'o.is_offline' => 1,
                    ]);
                $send1list = $query->select('o.*,od.id as order_detail_id,u.nickname')
                    ->orderBy('od.addtime asc')
                    ->asArray()->all();
                $array = array();
                for ($i = 0; $i < count($send1list); $i++) {
                    $tick = $send1list[$i]['name'] . $send1list[$i]['mobile'];
                    $found_key = array_search($tick, array_column($array, 'tick'));
                    if (is_numeric($found_key)) {
                        $array[$found_key]['order_no'] = $array[$found_key]['order_no'] . ',' . $send1list[$i]['order_no'];
                        $array[$found_key]['order_detail_id'] = $array[$found_key]['order_detail_id'] . ',' . $send1list[$i]['order_detail_id'];
                    } else {
                        $array[$i]['tick'] = $send1list[$i]['name'] . $send1list[$i]['mobile'];
                        $array[$i]['id'] = $send1list[$i]['id'];
                        $array[$i]['order_detail_id'] = $send1list[$i]['order_detail_id'];
                        $array[$i]['order_no'] = $send1list[$i]['order_no'];
                        $array[$i]['total_price'] = $send1list[$i]['total_price'];
                        $array[$i]['pay_price'] = $send1list[$i]['pay_price'];
                        $array[$i]['express_price'] = $send1list[$i]['express_price'];
                        $array[$i]['name'] = $send1list[$i]['name'];
                        $array[$i]['nickname'] = $send1list[$i]['nickname'];
                        $array[$i]['mobile'] = $send1list[$i]['mobile'];
                        $array[$i]['address'] = $send1list[$i]['address'];
                        $array[$i]['is_offline'] = $send1list[$i]['is_offline'];
                        $array[$i]['shop_id'] = $send1list[$i]['shop_id'];
                    }
                    $Order_detail = new OrderDetail();
                    $Order_detail->updateAll(['is_true' => 1], ['id' => $array[$i]['order_detail_id']]);
                }
                //保存发货记录
                $SendPurchase = new SendPurchase();
                $SendPurchase->addtime = time();
                $SendPurchase->state = '0';
                $SendPurchase->parent_id = $parent_id;
                $SendPurchase->save();
                $id = $SendPurchase->attributes['id'];
                $res = array();
                for ($a = 0; $a < count($array); $a++) {
                    $SendPurchaseDetail = new SendPurchaseDetail();
                    $SendPurchaseDetail->send_purchase_id = $id;
                    $SendPurchaseDetail->addtime = time();
                    $SendPurchaseDetail->address = $array[$a]['address'];
                    $SendPurchaseDetail->name = $array[$a]['name'];
                    $SendPurchaseDetail->nickname = $array[$a]['nickname'];
                    $SendPurchaseDetail->mobile = $array[$a]['mobile'];
                    $SendPurchaseDetail->state = 0;
                    $SendPurchaseDetail->order_no = $array[$a]['order_no'];
                    $SendPurchaseDetail->order_detail_id = $array[$a]['order_detail_id'];
                    $SendPurchaseDetail->saveSendPurchaseDetail();
                    $res[$a] = $SendPurchaseDetail;
                }
                return [
                    "code" => "0",
                    "msg" => "查询成功!",
                    "list" => $res,
                    "totalnum" => count($array),
                ];
            }
       }
    /**
     * 查询已付款，待发货商品
     * @param null $startTime
     * @param null $endTime
     * @param int $mchId
     * @param int $limit
     * @param $parent_id
     * @return array|\yii\db\ActiveRecord[]
     */
    public function getGoodsSaleTopList($startTime = null, $endTime = null, $mchId = 0, $limit = 10,$parent_id)
    {
        $cat=Cat::find()->alias('ct')
            ->where(['ct.parent_id'=>$parent_id,
            'is_delete'=>'0']);
        $list=$cat->select('ct.*')
            ->asArray()->all();
            if(empty($list)){
           //订单商品详情，订单表，商品表
            $query = OrderDetail::find()->alias('od')
                ->leftJoin(['o' => Order::tableName()], 'od.order_id=o.id')
                ->leftJoin(['g' => Goods::tableName()], 'od.goods_id=g.id')
                ->leftJoin(['s'=>GoodsCat::tableName()],'g.id=s.goods_id')
                ->leftJoin(['c'=>Cat::tableName()],'s.cat_id=c.id')
                ->where([
                    'g.store_id' =>'1',
                    'g.is_delete' => "0",
                    'o.is_delete' => "0",
                    'od.is_delete' => "0",
                    'o.is_send'=>"0",
                    'o.is_confirm'=>"0",
                    'g.mch_id' => $mchId,
                    'o.mch_id' => $mchId,
                    'o.type' => Order::ORDER_TYPE_STORE,
                    'o.apply_delete'=>"0",
                    'o.is_pay' => Order::IS_PAY_TRUE,
                    'o.pay_type' =>'1',
                    's.is_delete'=>"0",
                    'od.is_purchase'=>"0",
                    'od.is_true'=>"0",
                    'c.id'=>$parent_id,

                ]);
            if ($startTime !== null) {
                $query->andWhere(['>=', 'o.addtime', $startTime]);
            }
            if ($endTime !== null) {
                $query->andWhere(['<=', 'o.addtime', $endTime]);
            }
            return $query->select('o.order_no,od.attr,g.name,od.num,od.id,od.pic,od.total_price as price')
                ->orderBy('od.addtime asc')
                ->asArray()->all();

        }else{

        //订单商品详情，订单表，商品表
        $query = OrderDetail::find()->alias('od')
            ->leftJoin(['o' => Order::tableName()], 'od.order_id=o.id')
            ->leftJoin(['g' => Goods::tableName()], 'od.goods_id=g.id')
            ->leftJoin(['s'=>GoodsCat::tableName()],'g.id=s.goods_id')
            ->leftJoin(['c'=>Cat::tableName()],'s.cat_id=c.id')
            ->where([
                'g.store_id' =>'1',
                'g.is_delete' => "0",
                'o.is_delete' => "0",
                'od.is_delete' => "0",
                'o.is_send'=>"0",
                'o.is_confirm'=>"0",
                'g.mch_id' => $mchId,
                'o.mch_id' => $mchId,
                'o.type' => Order::ORDER_TYPE_STORE,
                'o.apply_delete'=>"0",
                'o.is_pay' => Order::IS_PAY_TRUE,
                'o.pay_type' =>'1',
                's.is_delete'=>"0",
                'od.is_purchase'=>"0",
                'od.is_true'=>"0",
                'c.parent_id'=>$parent_id,

            ]);
        if ($startTime !== null) {
            $query->andWhere(['>=', 'o.addtime', $startTime]);
        }
        if ($endTime !== null) {
            $query->andWhere(['<=', 'o.addtime', $endTime]);
        }
        return $query->select('o.order_no,od.attr,g.name,od.num,od.id,od.pic,od.total_price as price')
            ->orderBy('od.addtime asc')
            ->asArray()->all();
        }
    }
    /**
     * 按照订单详情id获取商品信息
     */
    public function getOrderDetailInfo($id)
    {
        $query=OrderDetail::find()->alias('od')
            ->leftJoin(['g' => Goods::tableName()], 'od.goods_id=g.id')
            ->where([
                'g.store_id' =>'1',
                'g.is_delete' => "0",
                'od.is_delete' => "0",
                'od.id'=>$id,
            ]);
        return $query->select('od.*,g.name')->asArray()->all();
    }
    /**
     * 删除（逻辑）
     * @param int $id
     */
    public function actionAppGoodsDel($id = 0)
    {
        $id=$_REQUEST['id'];
        $goods = Goods::findOne(['id' => $id, 'is_delete' => 0, 'store_id' => '1']);
        if (!$goods) {
            return [
                'code' => 1,
                'msg' => '商品删除失败或已删除',
            ];
        }
        $goods->is_delete = 1;
        if ($goods->save()) {
            return [
                'code' => 0,
                'msg' => '成功',
            ];
        } else {
            foreach ($goods->errors as $errors) {
                return [
                    'code' => 1,
                    'msg' => $errors[0],
                ];
            }
        }
    }

    /**
     * 商品上下架
     * @param int $id
     * @param string $type
     * @return array
     */
    public function actionAppGoodsUpDown($id = 0, $type = 'down')
    {
        $id=$_REQUEST['id'];
        $type=$_REQUEST['type'];
        if ($type == 'down') {
            $goods = Goods::findOne(['id' => $id, 'is_delete' => 0, 'status' => 1, 'store_id' => '1']);
            if (!$goods) {
                return [
                    'code' => 1,
                    'msg' => '商品已删除或已下架',
                ];
            }
            $goods->status = 0;
        } elseif ($type == 'up') {
            $goods = Goods::findOne(['id' => $id, 'is_delete' => 0, 'status' => 0, 'store_id' => '1']);

            if (!$goods) {
                return [
                    'code' => 1,
                    'msg' => '商品已删除或已上架',
                ];
            }
            if ($goods->cat_id == 0 && count(Goods::getCatList($goods)) == 0) {
                return [
                    'code' => 1,
                    'msg' => '请先选择分类'
                ];
            }
            if (!$goods->getNum() && $goods->mch_id == 0) {
                $return_url = \Yii::$app->urlManager->createUrl([get_plugin_url() . '/goods-edit', 'id' => $goods->id]);
                if (!$goods->use_attr) {
                    $return_url = \Yii::$app->urlManager->createUrl([get_plugin_url() . '/goods-edit', 'id' => $goods->id]) . '#step3';
                }

                return [
                    'code' => 1,
                    'msg' => '商品库存不足，请先完善商品库存',
                    'return_url' => $return_url,
                ];
            }
            $goods->status = 1;
        } elseif ($type == 'start') {
            $goods = Goods::findOne(['id' => $id, 'is_delete' => 0, 'store_id' => '1']);

            if (!$goods) {
                return [
                    'code' => 1,
                    'msg' => '商品已删除或已加入',
                ];
            }
            $goods->quick_purchase = 1;
        } elseif ($type == 'close') {
            $goods = Goods::findOne(['id' => $id, 'is_delete' => 0, 'store_id' => '1']);

            if (!$goods) {
                return [
                    'code' => 1,
                    'msg' => '商品已删除或已关闭',
                ];
            }
            $goods->quick_purchase = 0;
        } else {
            return [
                'code' => 1,
                'msg' => '参数错误',
            ];
        }
        if ($goods->save()) {
            return [
                'code' => 0,
                'msg' => '成功',
            ];
        } else {
            foreach ($goods->errors as $errors) {
                return [
                    'code' => 1,
                    'msg' => $errors[0],
                ];
            }
        }
    }

    /**
     * 查询商品列表
     * @return array
     */
    public function actionAppGoodsSearch()
    {
        $page=$_REQUEST['page'];
        $keyword=$_REQUEST['keyword'];
        $status=$_REQUEST['status'];
        $query = Goods::find()->where([
            'store_id' => 1,
            'is_delete' => 0,
            'status'=>$status
        ]);
        if ($keyword) {
            $query->andWhere(['like', 'name', $keyword]);
        }

        $count = $query->count();
        $pagination = new Pagination(['totalCount' => $count, 'page' => $page - 1, 'pageSize' => $this->limit]);
        $list = $query->asArray()->limit($pagination->limit)->offset($pagination->offset)->orderBy('id DESC')->all();

        return [
            'code' => 0,
            'data' => [
                'row_count' => $count,
                'page_count' => $pagination->pageCount,
                'list' => $list,
            ],
        ];
    }

    /**
     * 打印发货单
     */
    public function actionPrintSendInvoice()
    {
        $order_num = $_REQUEST['order_num'];
        $order_num_array = explode(",", $order_num);
        $OrderDetaillistresponse = array();
        $order_id = "";
        $printer_order = new PinterOrder($this->store->id, $order_id, 'pay', "人工打单[商城订单]");
        if (count($order_num_array )< 2) {
            $query = OrderDetail::find()->alias('od')
                ->leftJoin(['o' => Order::tableName()], 'od.order_id=o.id')
                ->leftJoin(['g' => Goods::tableName()], 'od.goods_id=g.id')
                ->where([
                    'o.order_no' => $order_num,
                    'od.is_delete' => 0
                ]);

            $OrderDetaillistresponse = $query->select('od.*')
                ->orderBy('od.addtime asc')
                ->asArray()->all();
        }else
            {
                $query = OrderDetail::find()->alias('od')
                    ->leftJoin(['o' => Order::tableName()], 'od.order_id=o.id')
                    ->leftJoin(['g' => Goods::tableName()], 'od.goods_id=g.id')
                    ->where([
                        'in','o.order_no',$order_num_array,
                        'od.is_delete' => 0
                    ]);

                $OrderDetaillistresponse= $query->select('od.*')
                    ->orderBy('od.addtime asc')
                    ->asArray()->all();
            }
            for($s=0;$s<count($OrderDetaillistresponse);$s++){
            $res = $printer_order->printer_5($OrderDetaillistresponse[$s]['order_id']);
            }
        return [
            'code' => 0,
            'msg' => '操作成功',
            'data' => $res,
        ];
    }

}