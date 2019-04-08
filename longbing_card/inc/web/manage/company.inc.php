<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/" . $_W["current_module"]["name"] . "/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$companyEdit = $this->createWebUrl("manage/companyedit");
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
if( $_GPC["action"] == "down" ) 
{
	$item = pdo_get("longbing_card_company", array( "id" => $_GPC["id"] ));
	if( !$item || empty($item) ) 
	{
		message("未找到该数据", "", "error");
	}
	$result = pdo_update("longbing_card_company", array( "status" => 0, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("下架成功", $this->createWebUrl("manage/company"), "success");
	}
	message("下架失败", "", "error");
}
if( $_GPC["action"] == "up" ) 
{
	$item = pdo_get("longbing_card_company", array( "id" => $_GPC["id"] ));
	if( !$item || empty($item) ) 
	{
		message("未找到该数据", "", "error");
	}
	$result = pdo_update("longbing_card_company", array( "status" => 1, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("上架成功", $this->createWebUrl("manage/company"), "success");
	}
	message("上架失败", "", "error");
}
if( $_GPC["action"] == "delete" ) 
{
	$id = $_GPC["id"];
	$info = pdo_get("longbing_card_company", array( "id" => $_GPC["id"] ));
	if( !$info || empty($info) ) 
	{
		message("未找到数据", "", "error");
	}
	$result = pdo_update("longbing_card_company", array( "status" => -1, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("删除成功", $this->createWebUrl("manage/company"), "success");
	}
	message("删除失败", "", "error");
}
if( $_GPC["action"] == "edit" ) 
{
	$time = time();
	foreach( $_GPC["formData"] as $k => $v ) 
	{
		if( strpos($k, "ulture") ) 
		{
			$data["culture"][] = $v;
		}
		else 
		{
			$data[$k] = $v;
		}
	}
	if( isset($data["culture"]) ) 
	{
		$data["culture"] = implode(",", $data["culture"]);
	}
	else 
	{
		$data["culture"] = "";
	}
	$data["culture"] = trim($data["culture"], ",");
	$data["update_time"] = $time;
	$id = $_GPC["id"];
	$result = false;
	if( $id ) 
	{
		$result = pdo_update("longbing_card_company", $data, array( "id" => $id ));
	}
	else 
	{
		$data["create_time"] = $time;
		$data["uniacid"] = $_W["uniacid"];
		$result = pdo_insert("longbing_card_company", $data);
	}
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("编辑成功", $this->createWebUrl("manage/company"), "success");
	}
	message("编辑失败", "", "error");
}
$limit = array( 1, 15 );
$where = array( "uniacid" => $_W["uniacid"], "status >" => -1 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
if( isset($_GPC["keyword"]) ) 
{
	$keyword = $_GPC["keyword"];
	$where["name like"] = "%" . $_GPC["keyword"] . "%";
}
$company = pdo_getslice("longbing_card_company", $where, $limit, $count, array( ), "", array( "top desc" ));
foreach( $company as $k => $v ) 
{
	$company[$k]["logo"] = tomedia($v["logo"]);
}
$perPage = 15;
load()->func("tpl");
include($this->template("manage/company"));
?>