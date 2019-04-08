<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$modular = $this->createWebUrl("manage/modular");
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
$limit = array( 1, 15 );
$where = array( "uniacid" => $_W["uniacid"] );
$company = pdo_get("longbing_card_company", $where);
if( !$company ) 
{
	message("请先编辑公司信息", $this->createWebUrl("manage/company"), "error");
}
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$where["status >"] = -1;
if( $_GPC["action"] == "edit" ) 
{
	$id = 0;
	if( !isset($_GPC["id"]) ) 
	{
		load()->func("tpl");
		include($this->template("manage/modularEdit"));
		return false;
	}
	$id = $_GPC["id"];
	$where["id"] = $id;
	$info = pdo_get("longbing_card_modular", $where);
	load()->func("tpl");
	include($this->template("manage/modularEdit"));
	return false;
}
if( $_GPC["action"] == "editSub" ) 
{
	$time = time();
	$data = $_GPC["formData"];
	$data["update_time"] = $time;
	$data["uniacid"] = $_W["uniacid"];
	$result = false;
	if( !isset($_GPC["id"]) ) 
	{
		$data["create_time"] = $time;
		$data["identification"] = uniqid();
		switch( $data["type"] ) 
		{
			case 1: $data["table_name"] = "longbing_card_articles";
			break;
			case 2: $data["table_name"] = "longbing_card_desc";
			break;
			case 3: $data["table_name"] = "longbing_card_jobs";
			break;
			case 4: $data["table_name"] = "longbing_card_contact";
			break;
			case 5: $data["table_name"] = "longbing_card_staffs";
			break;
			case 6: $data["table_name"] = "longbing_card_call";
			break;
			case 7: $data["table_name"] = "longbing_card_video";
			break;
			case 8: $data["table_name"] = "longbing_card_form";
			break;
			case 9: $data["table_name"] = "longbing_card_web";
			break;
			case 10: $data["table_name"] = "longbing_card_mini";
			break;
		}
		$result = pdo_insert("longbing_card_modular", $data);
		if( $result ) 
		{
			if( $redis_sup_v3 ) 
			{
				$redis_server_v3->flushDB();
			}
			message("添加成功", $this->createWebUrl("manage/modular"), "success");
		}
		message("添加失败", "", "error");
	}
	else 
	{
		$result = pdo_update("longbing_card_modular", $data, array( "id" => $_GPC["id"] ));
		if( $result ) 
		{
			message("修改成功", $this->createWebUrl("manage/modular"), "success");
		}
		message("修改失败", "", "error");
	}
}
if( $_GPC["action"] == "change" ) 
{
	$data["update_time"] = time();
	$data["status"] = 0;
	$info = pdo_get("longbing_card_modular", array( "id" => $_GPC["id"] ));
	if( !$info || empty($info) ) 
	{
		message("未找到该数据", "", "error");
	}
	if( $info["status"] == 0 ) 
	{
		$data["status"] = 1;
	}
	$result = pdo_update("longbing_card_modular", $data, array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("操作成功", $this->createWebUrl("manage/modular"), "success");
	}
	message("操作失败", "", "error");
}
if( $_GPC["action"] == "manage" ) 
{
	$id = $_GPC["id"];
	$infoM = pdo_get("longbing_card_modular", array( "id" => $id ));
	if( !$infoM || empty($infoM) ) 
	{
		message("未找到该数据", "", "error");
	}
	$where["modular_id"] = $id;
	switch( $infoM["type"] ) 
	{
		case 1: $keyword = "";
		if( isset($_GPC["keyword"]) ) 
		{
			$where["title like"] = "%" . $_GPC["keyword"] . "%";
			$keyword = $_GPC["keyword"];
		}
		$articles = pdo_getslice($infoM["table_name"], $where, $limit, $count, array( ), "", "top desc");
		foreach( $articles as $k => $v ) 
		{
			$articles[$k]["trueCover"] = tomedia($v["cover"]);
		}
		$perPage = 15;
		load()->func("tpl");
		include($this->template("manage/mudularArticle"));
		break;
		case 2: $info = pdo_get($infoM["table_name"], $where);
		load()->func("tpl");
		include($this->template("manage/mudularDesc"));
		break;
		case 3: $keyword = "";
		if( isset($_GPC["keyword"]) ) 
		{
			$where["title like"] = "%" . $_GPC["keyword"] . "%";
			$keyword = $_GPC["keyword"];
		}
		$jobs = pdo_getslice($infoM["table_name"], $where, $limit, $count, array( ), "", "top desc");
		$perPage = 15;
		load()->func("tpl");
		include($this->template("manage/modularJob"));
		break;
		case 4: $info = pdo_get($infoM["table_name"], $where);
		load()->func("tpl");
		include($this->template("manage/modularContact"));
		break;
		case 5: $keyword = "";
		if( isset($_GPC["keyword"]) ) 
		{
			$where["name like"] = "%" . $_GPC["keyword"] . "%";
			$keyword = $_GPC["keyword"];
		}
		$staffs = pdo_getslice($infoM["table_name"], $where, $limit, $count, array( ), "", "top desc");
		foreach( $staffs as $k => $v ) 
		{
			$staffs[$k]["trueCover"] = tomedia($v["cover"]);
		}
		$perPage = 15;
		load()->func("tpl");
		include($this->template("manage/modularStaff"));
		break;
		case 6: $info = pdo_get($infoM["table_name"], $where);
		load()->func("tpl");
		include($this->template("manage/modularCall"));
		break;
		case 7: $keyword = "";
		if( isset($_GPC["keyword"]) ) 
		{
			$where["title like"] = "%" . $_GPC["keyword"] . "%";
			$keyword = $_GPC["keyword"];
		}
		$info = pdo_getslice($infoM["table_name"], $where, $limit, $count, array( ), "", "top desc");
		foreach( $info as $k => $v ) 
		{
			$info[$k]["trueCover"] = tomedia($v["cover"]);
		}
		$perPage = 15;
		load()->func("tpl");
		include($this->template("manage/modularVideo"));
		break;
		case 8: $info = pdo_getslice($infoM["table_name"], $where, $limit, $count, array( ), "", "id desc");
		$perPage = 15;
		load()->func("tpl");
		include($this->template("manage/modularForm"));
		break;
		case 9: $info = pdo_get($infoM["table_name"], $where);
		load()->func("tpl");
		include($this->template("manage/modularWeb"));
		break;
		case 10: $info = pdo_get($infoM["table_name"], $where);
		load()->func("tpl");
		include($this->template("manage/modularMini"));
		break;
		default: message("类型错误", "", "error");
	}
	return false;
}
if( $_GPC["action"] == "manageSub" ) 
{
	$table_name = false;
	$id = false;
	$data = array( );
	foreach( $_GPC["formData"] as $k => $v ) 
	{
		if( $k == "table_name" ) 
		{
			$table_name = $v;
		}
		else 
		{
			if( $k == "id" ) 
			{
				$id = $v;
			}
			else 
			{
				$data[$k] = $v;
			}
		}
	}
	if( !$table_name ) 
	{
		message("提交失败", "", "error");
	}
	$result = false;
	$data["update_time"] = time();
	if( $id ) 
	{
		$result = pdo_update($table_name, $data, array( "id" => $id ));
	}
	else 
	{
		$data["create_time"] = time();
		$data["uniacid"] = $_W["uniacid"];
		$result = pdo_insert($table_name, $data);
	}
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("编辑成功", $this->createWebUrl("manage/modular"), "success");
	}
	message("编辑失败", "", "error");
}
if( $_GPC["action"] == "delete" ) 
{
	$id = $_GPC["id"];
	$info = pdo_get("longbing_card_modular", array( "id" => $_GPC["id"] ));
	if( !$info || empty($info) ) 
	{
		message("未找到数据", "", "error");
	}
	$result = pdo_delete("longbing_card_modular", array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		$table = tablename($info["table_name"]);
		@pdo_delete($table, array( "modular_id" => $id ));
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("删除成功", $this->createWebUrl("manage/modular"), "success");
	}
	message("删除失败", "", "error");
}
$keyword = "";
if( isset($_GPC["keyword"]) ) 
{
	$where["name like"] = "%" . $_GPC["keyword"] . "%";
	$keyword = $_GPC["keyword"];
}
$modular = pdo_getslice("longbing_card_modular", $where, $limit, $count, array( ), "", "top desc");
foreach( $modular as $k => $v ) 
{
	$modular[$k]["trueCover"] = tomedia($v["cover"]);
}
$perPage = 15;
load()->func("tpl");
include($this->template("manage/modular"));
?>