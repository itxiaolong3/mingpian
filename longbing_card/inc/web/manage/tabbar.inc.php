<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$redis_sup_v3 = false;
$redis_server_v3 = false;
include_once($_SERVER["DOCUMENT_ROOT"] . "/addons/longbing_card/images/phpqrcode/func_longbing.php");
if( function_exists("longbing_check_redis") ) 
{
	$config = $_W["config"]["setting"]["redis"];
	$password = "";
	if( $config && isset($config["requirepass"]) && $config["requirepass"] ) 
	{
		$password = $config["requirepass"];
	}
	if( $config && isset($config["server"]) && $config["server"] && isset($config["port"]) && $config["port"] ) 
	{
		list($redis_sup_v3, $redis_server_v3) = longbing_check_redis($config["server"], $config["port"], $password);
	}
}
if( $_GPC["action"] == "edit" ) 
{
	$time = time();
	$data["update_time"] = $time;
	$id = $_GPC["id"];
	$result = false;
	$result = pdo_update("longbing_card_tabbar", $_GPC["formData"], array( "id" => $id ));
	if( $result === 0 ) 
	{
		message("未做任何修改", $this->createWebUrl("manage/tabBar"), "success");
	}
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("编辑成功", $this->createWebUrl("manage/tabBar"), "success");
	}
	message("编辑失败", "", "error");
}
$where = array( "uniacid" => $_W["uniacid"] );
$info = pdo_get("longbing_card_tabbar", $where);
if( !$info || empty($info) ) 
{
	pdo_insert("longbing_card_tabbar", array( "uniacid" => $_W["uniacid"], "create_time" => time(), "update_time" => time() ));
	$info = pdo_get("longbing_card_tabbar", $where);
}
$id = $info["id"];
$pages = pdo_getall("longbing_card_pages");
load()->func("tpl");
include($this->template("manage/tabBar"));
?>