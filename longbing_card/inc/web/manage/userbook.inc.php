<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$info = pdo_get("longbing_card_teamsetting", array( "uniacid" => $_W["uniacid"] ));
if ($_GPC['action']=='editSub'){
    $getdata=$_GPC['formData'];
    $result = pdo_update("longbing_card_teamsetting", array('userbook'=>$getdata['userbook']), array( "uniacid" => $_W["uniacid"] ));
    if( $result ) {
        message("修改成功", $this->createWebUrl("manage/userbook"), "success");
    }
    message("修改失败", "", "error");
}
load()->func("tpl");
include($this->template("manage/userbook"));
?>