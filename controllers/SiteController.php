<?php
/**
 * Created by IntelliJ IDEA.
 * User: luwei
 * Date: 2017/10/25
 * Time: 11:13
 */

namespace app\controllers;
use Yii;
use yii\web\Controller;
use app\models\EntryForm;

use app\models\PtNoticeSender;
use app\models\User;

class SiteController extends Controller
{
    public function actionIndex()
    {

        $install_lock_file = \Yii::$app->basePath . '/install.lock.php';
        if (!file_exists($install_lock_file)) {
            $this->redirect(\Yii::$app->urlManager->createUrl(['install']))->send();
        } else {
            $this->redirect(\Yii::$app->urlManager->createUrl(['admin']))->send();
        }
    }

    public function actionTest()
    {
        $tpl = new PtNoticeSender(null,1);
        $tpl->sendSuccessNotice(102);
    }
    public function actionSay($message='Man, amakuru yawe?') {
        return $this->render('say',['ubutumwa'=>$message]);
    }


    public function actionEntry()
    {
        $model = new EntryForm();//load the model

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            // valid data received in $model

            // do something meaningful here about $model ...

            return $this->render('entry-confirm', ['model' => $model]);
        } else {
            // either the page is initially displayed or there is some validation error
            return $this->render('entry', ['model' => $model]);
        }
    }

}
