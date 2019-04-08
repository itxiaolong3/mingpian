<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
include_once($_SERVER["DOCUMENT_ROOT"] . "/addons/longbing_card/images/phpqrcode/func_longbing.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$timelineEdit = $this->createWebUrl("manage/timelineedit");
$timelineEditUrl = $this->createWebUrl("manage/timelineEditUrl");
$timelineEditVideo = $this->createWebUrl("manage/timelineEditVideo");
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
		include($this->template("manage/timelineEdit"));
		return false;
	}
	$id = $_GPC["id"];
	$where["id"] = $id;
	$info = pdo_get("longbing_card_timeline", $where);
	$info["cover"] = explode(",", $info["cover"]);
	$info["create_time"] = date("Y-m-d H:i:s", $info["create_time"]);
	load()->func("tpl");
	if( $info["type"] == 0 ) 
	{
		include($this->template("manage/timelineEdit"));
	}
	else 
	{
		$pages = pdo_getall("longbing_card_pages");
		include($this->template("manage/timelineEditUrl"));
	}
	return false;
}
if( $_GPC["action"] == "editUrl" ) 
{
	$id = 0;
	$pages = pdo_getall("longbing_card_pages");
	load()->func("tpl");
	include($this->template("company/timelineEditUrl"));
	return false;
}
if( $_GPC["action"] == "editSub" ) 
{
	$time = time();
	$tmp = array( );
	foreach( $_GPC["formData"] as $k => $v ) 
	{
		if( strpos($k, "over") ) 
		{
			$tmp[] = $v;
		}
		else 
		{
			$data[$k] = $v;
		}
	}
	if( !empty($tmp) ) 
	{
		$tmp = implode(",", $tmp);
	}
	else 
	{
		$tmp = "";
	}
	$tmp = trim($tmp, ",");
	$data["cover"] = $tmp;
	$data["update_time"] = $time;
	$data["uniacid"] = $_W["uniacid"];
	$result = false;
	if( !isset($_GPC["id"]) ) 
	{
		if( $data["create_time"] ) 
		{
			$data["create_time"] = strtotime($data["create_time"]);
		}
		else 
		{
			$data["create_time"] = $time;
		}
		if( LONGBING_AUTH_TIMELINE ) 
		{
			$list = pdo_getall("longbing_card_timeline", array( "uniacid" => $_W["uniacid"], "status >" => -1 ));
			$count = count($list);
			if( LONGBING_AUTH_TIMELINE <= $count ) 
			{
				message("添加动态达到上限, 如需增加请购买高级版本", "", "error");
			}
		}
		$result = pdo_insert("longbing_card_timeline", $data);
		if( $result ) 
		{
			if( function_exists("longbing_redis_flushDB") ) 
			{
				$config = $_W["config"]["setting"]["redis"];
				$password = "";
				if( $config && isset($config["requirepass"]) && $config["requirepass"] ) 
				{
					$password = $config["requirepass"];
				}
				longbing_redis_flushDB($config["server"], $config["port"], $password);
			}
			message("添加成功", $this->createWebUrl("manage/timeline"), "success");
		}
		message("添加失败", "", "error");
	}
	else 
	{
		$data["create_time"] = strtotime($data["create_time"]);
		$result = pdo_update("longbing_card_timeline", $data, array( "id" => $_GPC["id"] ));
		if( $result ) 
		{
			if( function_exists("longbing_redis_flushDB") ) 
			{
				$config = $_W["config"]["setting"]["redis"];
				$password = "";
				if( $config && isset($config["requirepass"]) && $config["requirepass"] ) 
				{
					$password = $config["requirepass"];
				}
				longbing_redis_flushDB($config["server"], $config["port"], $password);
			}
			message("修改成功", $this->createWebUrl("manage/timeline"), "success");
		}
		message("修改失败", "", "error");
	}
}
if( $_GPC["action"] == "editSubUrl" ) 
{
	$time = time();
	$tmp = array( );
	foreach( $_GPC["formData"] as $k => $v ) 
	{
		if( strpos($k, "over") ) 
		{
			$tmp[] = $v;
		}
		else 
		{
			$data[$k] = $v;
		}
	}
	$data["type"] = 2;
	if( !empty($tmp) ) 
	{
		$tmp = implode(",", $tmp);
	}
	else 
	{
		$tmp = "";
	}
	$tmp = trim($tmp, ",");
	$data["cover"] = $tmp;
	$data["update_time"] = $time;
	$data["uniacid"] = $_W["uniacid"];
	$result = false;
	if( !isset($_GPC["id"]) ) 
	{
		if( $data["create_time"] ) 
		{
			$data["create_time"] = strtotime($data["create_time"]);
		}
		else 
		{
			$data["create_time"] = $time;
		}
		if( LONGBING_AUTH_TIMELINE ) 
		{
			$list = pdo_getall("longbing_card_timeline", array( "uniacid" => $_W["uniacid"], "status >" => -1 ));
			$count = count($list);
			if( LONGBING_AUTH_TIMELINE <= $count ) 
			{
				message("添加动态达到上限: " . ", 如需增加请购买高级版本", "", "error");
			}
		}
		$result = pdo_insert("longbing_card_timeline", $data);
		if( $result ) 
		{
			if( function_exists("longbing_redis_flushDB") ) 
			{
				$config = $_W["config"]["setting"]["redis"];
				$password = "";
				if( $config && isset($config["requirepass"]) && $config["requirepass"] ) 
				{
					$password = $config["requirepass"];
				}
				longbing_redis_flushDB($config["server"], $config["port"], $password);
			}
			message("添加成功", $this->createWebUrl("manage/timeline"), "success");
		}
		message("添加失败", "", "error");
	}
	else 
	{
		$data["create_time"] = strtotime($data["create_time"]);
		$result = pdo_update("longbing_card_timeline", $data, array( "id" => $_GPC["id"] ));
		if( $result ) 
		{
			if( function_exists("longbing_redis_flushDB") ) 
			{
				$config = $_W["config"]["setting"]["redis"];
				$password = "";
				if( $config && isset($config["requirepass"]) && $config["requirepass"] ) 
				{
					$password = $config["requirepass"];
				}
				longbing_redis_flushDB($config["server"], $config["port"], $password);
			}
			message("修改成功", $this->createWebUrl("manage/timeline"), "success");
		}
		message("修改失败", "", "error");
	}
}
if( $_GPC["action"] == "editSubVideo" ) 
{
	$time = time();
	$tmp = array( );
	foreach( $_GPC["formData"] as $k => $v ) 
	{
		if( strpos($k, "over") ) 
		{
			$tmp[] = $v;
		}
		else 
		{
			$data[$k] = $v;
		}
	}
	$data["type"] = 1;
	if( !empty($tmp) ) 
	{
		$tmp = implode(",", $tmp);
	}
	else 
	{
		$tmp = "";
	}
	$tmp = trim($tmp, ",");
	$data["cover"] = $tmp;
	$data["update_time"] = $time;
	$data["uniacid"] = $_W["uniacid"];
	$result = false;
	if( !isset($_GPC["id"]) ) 
	{
		if( $data["create_time"] ) 
		{
			$data["create_time"] = strtotime($data["create_time"]);
		}
		else 
		{
			$data["create_time"] = $time;
		}
		if( LONGBING_AUTH_TIMELINE ) 
		{
			$list = pdo_getall("longbing_card_timeline", array( "uniacid" => $_W["uniacid"], "status >" => -1 ));
			$count = count($list);
			if( LONGBING_AUTH_TIMELINE <= $count ) 
			{
				message("添加动态达到上限" . ", 如需增加请购买高级版本", "", "error");
			}
		}
		$result = pdo_insert("longbing_card_timeline", $data);
		if( $result ) 
		{
			if( function_exists("longbing_redis_flushDB") ) 
			{
				$config = $_W["config"]["setting"]["redis"];
				$password = "";
				if( $config && isset($config["requirepass"]) && $config["requirepass"] ) 
				{
					$password = $config["requirepass"];
				}
				longbing_redis_flushDB($config["server"], $config["port"], $password);
			}
			message("添加成功", $this->createWebUrl("manage/timeline"), "success");
		}
		message("添加失败", "", "error");
	}
	else 
	{
		$data["create_time"] = strtotime($data["create_time"]);
		$result = pdo_update("longbing_card_timeline", $data, array( "id" => $_GPC["id"] ));
		if( $result ) 
		{
			if( function_exists("longbing_redis_flushDB") ) 
			{
				$config = $_W["config"]["setting"]["redis"];
				$password = "";
				if( $config && isset($config["requirepass"]) && $config["requirepass"] ) 
				{
					$password = $config["requirepass"];
				}
				longbing_redis_flushDB($config["server"], $config["port"], $password);
			}
			message("修改成功", $this->createWebUrl("manage/timeline"), "success");
		}
		message("修改失败", "", "error");
	}
}
if( $_GPC["action"] == "change" ) 
{
	$data["update_time"] = time();
	$data["status"] = 0;
	$info = pdo_get("longbing_card_timeline", array( "id" => $_GPC["id"] ));
	if( !$info || empty($info) ) 
	{
		message("未找到该数据", "", "error");
	}
	if( $info["status"] == 0 ) 
	{
		$data["status"] = 1;
	}
	$result = pdo_update("longbing_card_timeline", $data, array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		if( function_exists("longbing_redis_flushDB") ) 
		{
			$config = $_W["config"]["setting"]["redis"];
			$password = "";
			if( $config && isset($config["requirepass"]) && $config["requirepass"] ) 
			{
				$password = $config["requirepass"];
			}
			longbing_redis_flushDB($config["server"], $config["port"], $password);
		}
		message("操作成功", $this->createWebUrl("manage/timeline"), "success");
	}
	message("操作失败", "", "error");
}
if( $_GPC["action"] == "delete" ) 
{
	$id = $_GPC["id"];
	$info = pdo_get("longbing_card_timeline", array( "id" => $_GPC["id"] ));
	if( !$info || empty($info) ) 
	{
		message("未找到数据", "", "error");
	}
	$result = pdo_update("longbing_card_timeline", array( "status" => -1, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		if( function_exists("longbing_redis_flushDB") ) 
		{
			$config = $_W["config"]["setting"]["redis"];
			$password = "";
			if( $config && isset($config["requirepass"]) && $config["requirepass"] ) 
			{
				$password = $config["requirepass"];
			}
			longbing_redis_flushDB($config["server"], $config["port"], $password);
		}
		message("删除成功", $this->createWebUrl("manage/timeline"), "success");
	}
	message("删除失败", "", "error");
}
$keyword = "";
if( isset($_GPC["keyword"]) ) 
{
	$where["title like"] = "%" . $_GPC["keyword"] . "%";
	$keyword = $_GPC["keyword"];
}
$timeline = pdo_getslice("longbing_card_timeline", $where, $limit, $count, array( ), "", array( "top desc", "id desc" ));
foreach( $timeline as $k => $v ) 
{
	$arr = explode(",", $v["cover"]);
	$timeline[$k]["trueCover"] = tomedia($arr[0]);
	$timeline[$k]["re_name"] = "公司发布";
	if( $v["user_id"] ) 
	{
		$user = pdo_get("longbing_card_user_info", array( "fans_id" => $v["user_id"] ));
		$timeline[$k]["re_name"] = $user["name"];
	}
}
$perPage = 15;
load()->func("tpl");
include($this->template("manage/timeline"));
?>