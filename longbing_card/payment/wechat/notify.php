<?php
define('IN_MOBILE', true);
require '../../../../framework/bootstrap.inc.php';
global $_W, $_GPC;
$input = file_get_contents('php://input');
$isxml = true;
if (!empty($input) && empty($_GET['out_trade_no'])) {
    $obj = isimplexml_load_string($input, 'SimpleXMLElement', LIBXML_NOCDATA);
    $res = $data = json_decode(json_encode($obj), true);
    $filename=$_W['attachurl'].'notifyinfo.txt';
    $filename1=$_W['attachurl'].'newpaylog.txt';
    file_put_contents($filename,$data['result_code'].'==result_code'.$data['return_code'].'===return_code'.'时间'.date('Y-m-d H:i:s',time()));

    if (empty($data)) {
        $result = array(
            'return_code' => 'FAIL',
            'return_msg' => ''
        );
        echo array2xml($result);
        exit;
    }
    if ($data['result_code'] != 'SUCCESS' || $data['return_code'] != 'SUCCESS') {
        $result = array(
            'return_code' => 'FAIL',
            'return_msg' => empty($data['return_msg']) ? $data['err_code_des'] : $data['return_msg']
        );
        echo array2xml($result);
        exit;
    }
    $get = $data;
} else {
    $isxml = false;
    $get = $_GET;
}
if($res['return_code'] == 'SUCCESS' && $res['result_code'] == 'SUCCESS' ){
    $logno = trim($res['out_trade_no']);

    if (empty($logno)) {
        exit;
    }
    $str=$_W['siteroot'];
    $n = 0;
    for($i = 1;$i <= 3;$i++) {
        $n = strpos($str, '/', $n);
        $i != 3 && $n++;
    }
    $url=substr($str,0,$n);
    $uidpid=explode('|',$res['attach']);
    $uid=$uidpid[0];
    $pid=$uidpid[1];
    $money=$res['cash_fee']/100;

    $issave=pdo_get("longbing_card_viporder", array( 'ordernum'=> $logno,'uid'=>$uid));
    if (!$issave){
        $getuinfo=pdo_get("longbing_card_user", array( 'id'=>$uid));
        $getpinfo=pdo_get("longbing_card_user", array( 'id'=>$pid));
        $inser=pdo_insert('longbing_card_viporder',array('ordernum'=> $logno, 'uid'=>$uid,'pid'=>$pid,
            'nickname'=>$getuinfo['nickName'],'headerimg'=>$getuinfo['avatarUrl'],'pnickname'=>$getpinfo['nickName'],'pheaderimg'=>$getpinfo['avatarUrl'],
            'addtime'=>date('Y-m-d H:i:s',time()),'payprice'=>$money));
        $oid=pdo_insertid();
        if ($inser){
            //更新用户等级和分佣
            $settinginfo=pdo_get('longbing_card_teamsetting',array("id" => 1));
            //升级等级
            $uplevel=pdo_update('longbing_card_user',array('level'=>1,'jointime'=>date('Y-m-d H:i:s',time())),array('id'=>$uid));
            if ($uplevel){
                //绑定关系
                $issave=pdo_get('longbing_card_relation', array('uid' => $uid));
                if (empty($issave)){
                    pdo_insert('longbing_card_relation',array('uid'=>$uid,'pid'=>$pid,'addtime'=>date('Y-m-d H:i:s',time())));
                }else{
                    if (empty($issave['pid'])){
                        pdo_update('longbing_card_relation',array('pid'=>$pid),array('uid'=>$uid));
                    }
                }
                //分佣，添加收益记录
                if (!empty($pid)){
                    //一级分佣
                    $issaverecord=pdo_get('longbing_card_record', array('uid' => $pid,"cid"=>$uid,"oid"=>$oid));
                    $onemoeny=$settinginfo['onebili']/100;
                    if (empty($issaverecord)){
                        pdo_insert('longbing_card_record',array('uid'=>$pid,'cid'=>$uid,"oid"=>$oid,
                            "ordernum"=>$logno,"money"=>$money*$onemoeny,"comment"=>"一级分佣",'addtime'=>date('Y-m-d H:i:s',time())));
                    }
                    //二级分佣
                    $ppid=pdo_getcolumn('longbing_card_user', array('id' => $pid), 'pid',1);
                    file_put_contents($filename1,'支付金额='.$money.'--一级分佣='.$onemoeny.'===========二级分佣='.$settinginfo['twobili']/100);
                    if ($ppid){
                        $issaverecord2=pdo_get('longbing_card_record', array('uid' => $ppid,"cid"=>$pid,"oid"=>$oid));
                        $twomoeny=$settinginfo['twobili']/100;
                        if (empty($issaverecord2)){
                            pdo_insert('longbing_card_record',array('uid'=>$ppid,'cid'=>$pid,"oid"=>$oid,
                                "ordernum"=>$logno,"money"=>$money*$twomoeny,"comment"=>"二级分佣",'addtime'=>date('Y-m-d H:i:s',time())));
                        }
                    }

                }


            }
            $result = array(
                'return_code' => 'SUCCESS',
                'return_msg' => 'OK'
            );
            echo array2xml($result);
            exit;
        }else{
            $result = array(
                'return_code' => 'FAIL',
                'return_msg' => empty($data['return_msg']) ? $data['err_code_des'] : $data['return_msg']
            );
            echo array2xml($result);
            exit;
        }
    }

    $result = array(
        'return_code' => 'SUCCESS',
        'return_msg' => 'OK'
    );
    echo array2xml($result);
    exit;
    //修改库存量
}else{
    //订单已经处理过了
    $result = array(
        'return_code' => 'SUCCESS',
        'return_msg' => 'OK'
    );
    echo array2xml($result);
    exit;
}