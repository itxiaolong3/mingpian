<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$typeEdit = $this->createWebUrl("manage/typeedit");
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
if( $_GPC["action"] == "delete" ) 
{
	$id = $_GPC["id"];
	$info = pdo_get("longbing_card_shop_type", array( "id" => $_GPC["id"] ));
	if( !$info || empty($info) ) 
	{
		message("未找到数据", "", "error");
	}
	$result = pdo_update("longbing_card_shop_type", array( "status" => -1, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	pdo_update("longbing_card_shop_type", array( "status" => -1, "update_time" => time() ), array( "pid" => $_GPC["id"] ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("删除成功", $this->createWebUrl("manage/type"), "success");
	}
	message("删除失败", "", "error");
}
if( $_GPC["action"] == "disable" ) 
{
	$job = pdo_get("longbing_card_shop_type", array( "id" => $_GPC["id"] ));
	if( !$job || empty($job) ) 
	{
		message("未找到数据", "", "error");
	}
	$result = pdo_update("longbing_card_shop_type", array( "status" => 0, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	pdo_update("longbing_card_shop_type", array( "status" => 0, "update_time" => time() ), array( "pid" => $_GPC["id"] ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("禁用成功", $this->createWebUrl("manage/type"), "success");
	}
	message("禁用失败", "", "error");
}
if( $_GPC["action"] == "enable" ) 
{
	$job = pdo_get("longbing_card_shop_type", array( "id" => $_GPC["id"] ));
	if( !$job || empty($job) ) 
	{
		message("未找到数据", "", "error");
	}
	$result = pdo_update("longbing_card_shop_type", array( "status" => 1, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("启用成功", $this->createWebUrl("manage/type"), "success");
	}
	message("启用失败", "", "error");
}
$limit = array( 1, 15 );
$where = array( "uniacid" => $_W["uniacid"], "status >" => -1, "pid" => 0 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$keyword = "";
if( isset($_GPC["keyword"]) ) 
{
	$where["title like"] = "%" . $_GPC["keyword"] . "%";
	$keyword = $_GPC["keyword"];
}
$typeP = pdo_getall("longbing_card_shop_type", $where);
$type = array( );
foreach( $typeP as $k => $v ) 
{
	$v["class"] = "顶级分类";
	$v["trueCover"] = tomedia($v["cover"]);
	$v["status"] = ($v["status"] ? "上架中" : "已下架");
	array_push($type, $v);
	$typeS = pdo_getall("longbing_card_shop_type", array( "pid" => $v["id"], "status >" => -1, "uniacid" => $_W["uniacid"] ));
	foreach( $typeS as $k2 => $v2 ) 
	{
		$typeS[$k2]["class"] = "--" . $v["title"];
		$typeS[$k2]["trueCover"] = tomedia($v2["cover"]);
		$typeS[$k2]["status"] = ($v2["status"] ? "上架中" : "已下架");
	}
	$type = array_merge($type, $typeS);
}
$perPage = 999;
$count = count($type);
$typeJson = json_encode($type);
load()->func("tpl");
include($this->template("manage/typeNew"));
?>