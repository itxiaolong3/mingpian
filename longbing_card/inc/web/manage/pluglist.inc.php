<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
if( (!defined("LONGBING_AUTH_FORM") || LONGBING_AUTH_FORM == 0) && (!defined("LONGBING_AUTH_PLUG_AUTH") || LONGBING_AUTH_PLUG_AUTH == 0) ) 
{
	echo "未开通插件，请联系管理员付费开通！";
	exit();
}
if( $_GPC["action"] == "on" ) 
{
	$result = pdo_update("longbing_card_config", array( $_GPC["id"] => 1, "update_time" => time() ), array( "uniacid" => $uniacid ));
	if( $result ) 
	{
		message("编辑成功", $this->createWebUrl("manage/pluglist"), "success");
	}
	message("编辑失败", "", "error");
}
if( $_GPC["action"] == "down" ) 
{
	$result = pdo_update("longbing_card_config", array( $_GPC["id"] => 0, "update_time" => time() ), array( "uniacid" => $uniacid ));
	if( $result ) 
	{
		message("编辑成功", $this->createWebUrl("manage/pluglist"), "success");
	}
	message("编辑失败", "", "error");
}
$info = pdo_get("longbing_card_config", array( "uniacid" => $_W["uniacid"] ));
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$list = array( );
if( defined("LONGBING_AUTH_FORM") || LONGBING_AUTH_FORM != 0 ) 
{
	array_push($list, array( "title" => "首页表单", "desc" => "员工个人名片首页的信息收集表单，关闭后小程序端将不再显示", "switch" => $info["plug_form"], "name" => "plug_form" ));
}
$count = 1;
$perPage = 15;
load()->func("tpl");
include($this->template("manage/plugList"));
?>