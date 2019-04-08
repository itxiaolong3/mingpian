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
	foreach( $_GPC["formData"] as $k => $v ) 
	{
		if( strpos($k, "mages") ) 
		{
			$data["images"][] = $v;
		}
		else 
		{
			$data[$k] = $v;
		}
	}
	if( isset($data["images"]) ) 
	{
		$data["images"] = implode(",", $data["images"]);
	}
	else 
	{
		$data["images"] = "";
	}
	$data["images"] = trim($data["images"], ",");
	$data["update_time"] = time();
	$data["desc"] = str_replace(" ", "&nbsp;", $data["desc"]);
	$id = $_GPC["id"];
	$info = pdo_get("longbing_card_user_info", array( "id" => $id ));
	if( !$info || empty($info) ) 
	{
		message("未找到该名片信息", "", "error");
	}
	if( !$info["create_time"] ) 
	{
		$data["create_time"] = time();
	}
	$result = pdo_update("longbing_card_user_info", $data, array( "id" => $id ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("编辑成功", $this->createWebUrl("manage/users"), "success");
	}
	message("编辑失败", "", "error");
}
$limit = array( 1, $this->limit );
if( isset($_GPC["fanid"]) ) 
{
	$where = array( "uniacid" => $_W["uniacid"], "fans_id" => $_GPC["fanid"] );
}
else 
{
	$where = array( "uniacid" => $_W["uniacid"], "id" => $_GPC["id"] );
}
$id = $_GPC["id"];
$info = pdo_get("longbing_card_user_info", $where);
if( isset($_GPC["fanid"]) ) 
{
	$id = $info["id"];
}
$jobs = pdo_getall("longbing_card_job", array( "uniacid" => $_W["uniacid"], "status" => 1 ));
if( $info["images"] ) 
{
	$info["images"] = explode(",", $info["images"]);
}
$company = pdo_getall("longbing_card_company", array( "uniacid" => $_W["uniacid"], "status" => 1 ));
load()->func("tpl");
include($this->template("manage/cardEdit"));
?>