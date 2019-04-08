<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$addGoods = $this->createWebUrl("manage/goodsEdit");
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
if( $_GPC["action"] == "downGoods" ) 
{
	$goods = pdo_get("longbing_card_goods", array( "id" => $_GPC["id"] ));
	if( !$goods || empty($goods) ) 
	{
		message("未找到该商品", "", "error");
	}
	if( $goods["status"] == 0 ) 
	{
		message("该商品已下架", "", "error");
	}
	$result = pdo_update("longbing_card_goods", array( "status" => 0, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("下架成功", $this->createWebUrl("manage/goods"), "success");
	}
	message("下架失败", "", "error");
}
if( $_GPC["action"] == "upGoods" ) 
{
	$goods = pdo_get("longbing_card_goods", array( "id" => $_GPC["id"] ));
	if( !$goods || empty($goods) ) 
	{
		message("未找到该商品", "", "error");
	}
	if( $goods["status"] == 1 ) 
	{
		message("该商品已上架", "", "error");
	}
	$result = pdo_update("longbing_card_goods", array( "status" => 1, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("上架成功", $this->createWebUrl("manage/goods"), "success");
	}
	message("上架失败", "", "error");
}
if( $_GPC["action"] == "delete" ) 
{
	$id = $_GPC["id"];
	$info = pdo_get("longbing_card_goods", array( "id" => $_GPC["id"] ));
	if( !$info || empty($info) ) 
	{
		message("未找到数据", "", "error");
	}
	$result = pdo_update("longbing_card_goods", array( "status" => -1, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("删除成功", $this->createWebUrl("manage/goods"), "success");
	}
	message("删除失败", "", "error");
}
if( $_GPC["action"] == "clear" ) 
{
	$staff = pdo_getall("longbing_card_user_info", array( "uniacid" => $uniacid ));
	$goods = pdo_getall("longbing_card_goods", array( "uniacid" => $uniacid ));
	foreach( $goods as $index => $item ) 
	{
		foreach( $staff as $k => $v ) 
		{
			$destination_folder = "/images" . "/longbing_card/" . $uniacid;
			$file = $destination_folder . "/" . $uniacid . "-goods-" . $item["id"] . "-" . $v["fans_id"] . "qr.png";
			$file = ATTACHMENT_ROOT . $file;
			if( file_exists($file) ) 
			{
				@unlink($file);
			}
		}
	}
	message("清除成功", $this->createWebUrl("manage/goods"), "success");
}
if( $_GPC["action"] == "edit" ) 
{
	$time = time();
	if( !LONGBING_AUTH_COPYRIGHT ) 
	{
		$_GPC["formData"]["copyright"] = "https://retail.xiaochengxucms.com/images/12/2018/11/crDXyl3TyBRLUBch6ToqXL6e9D96hY.jpg";
	}
	if( isset($_GPC["formData"]) && isset($_GPC["formData"]["is_mini"]) ) 
	{
		$appid = $_GPC["formData"]["appid"];
		$appsecret = $_GPC["formData"]["appsecret"];
		if( !$appid || !$appsecret ) 
		{
			message("编辑失败，appid和appsecret为必填写", "", "error");
		}
		$result = pdo_update("account_wxapp", array( "key" => $appid, "secret" => $appsecret ), array( "uniacid" => $_W["uniacid"] ));
		if( $result === 0 ) 
		{
			message("未做任何修改", $this->createWebUrl("manage/config"), "success");
		}
		if( $result ) 
		{
			message("编辑成功", $this->createWebUrl("manage/config"), "success");
		}
		message("编辑失败", "", "error");
	}
	$checkExists = pdo_tableexists("longbing_cardauth2_config");
	if( $checkExists ) 
	{
		$auth_info = pdo_get("longbing_cardauth2_config", array( "modular_id" => $_W["uniacid"] ));
		if( $auth_info && $auth_info["copyright_id"] ) 
		{
			$copyright = pdo_get("longbing_cardauth2_copyright", array( "id" => $auth_info["copyright_id"] ));
			if( $copyright ) 
			{
				$_GPC["formData"]["copyright"] = tomedia($copyright["image"]);
				$_GPC["formData"]["logo_text"] = $copyright["text"];
				$_GPC["formData"]["logo_phone"] = $copyright["phone"];
				$_GPC["formData"]["logo_switch"] = 2;
			}
		}
	}
	$data = $_GPC["formData"];
	$data["logo_text"] = str_replace(" ", "&nbsp;", $data["logo_text"]);
	if( $data["order_overtime"] ) 
	{
		$data["order_overtime"] = intval($data["order_overtime"]);
		if( $data["order_overtime"] < 1800 || !$data["order_overtime"] ) 
		{
			$data["order_overtime"] = 1800;
		}
	}
	if( $data["collage_overtime"] ) 
	{
		$data["collage_overtime"] = intval($data["collage_overtime"]);
		if( $data["collage_overtime"] < 1800 || !$data["collage_overtime"] ) 
		{
			$data["collage_overtime"] = 1800;
		}
	}
	if( $data["staff_extract"] ) 
	{
		$data["staff_extract"] = intval($data["staff_extract"]);
		if( $data["staff_extract"] < 0 || 100 < $data["staff_extract"] ) 
		{
			$data["staff_extract"] = 0;
		}
	}
	if( $data["first_extract"] ) 
	{
		$data["first_extract"] = intval($data["first_extract"]);
		if( $data["first_extract"] < 0 || 100 < $data["first_extract"] ) 
		{
			$data["first_extract"] = 0;
		}
	}
	if( $data["sec_extract"] ) 
	{
		$data["sec_extract"] = intval($data["sec_extract"]);
		if( $data["sec_extract"] < 0 || 100 < $data["sec_extract"] ) 
		{
			$data["sec_extract"] = 0;
		}
	}
	if( $data["receiving"] ) 
	{
		$data["receiving"] = intval($data["receiving"]);
		if( $data["receiving"] < 5 ) 
		{
			$data["receiving"] = 5;
		}
	}
	$data["update_time"] = $time;
	$id = $_GPC["id"];
	$result = false;
	$result = pdo_update("longbing_card_config", $data, array( "id" => $id ));
	if( $result === 0 ) 
	{
		message("未做任何修改", $this->createWebUrl("manage/goods"), "success");
	}
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("编辑成功", $this->createWebUrl("manage/goods"), "success");
	}
	message("编辑失败", "", "error");
}
$limit = array( 1, $this->limit );
$where = array( "uniacid" => $_W["uniacid"], "status >" => -1 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$type_value = "";
if( isset($_GPC["type"]) && $_GPC["type"] ) 
{
	$type_value = $_GPC["type"];
}
$keyword = "";
if( isset($_GPC["keyword"]) && $_GPC["keyword"] ) 
{
	$where["name like"] = "%" . $_GPC["keyword"] . "%";
	$keyword = $_GPC["keyword"];
	$keyword2 = "%" . $_GPC["keyword"] . "%";
	if( $type_value ) 
	{
		$goods = pdo_fetchall("SELECT a.id,a.name,a.cover,a.price,a.sale_count,a.status,a.create_time,a.recommend,a.top,b.title,b.pid FROM " . tablename("longbing_card_goods") . " a LEFT JOIN " . tablename("longbing_card_shop_type") . " b ON a.type = b.id where a.uniacid = " . $_W["uniacid"] . " && a.status > -1 && a.name LIKE '" . $keyword2 . "' && a.type = " . $type_value . " ORDER BY a.recommend DESC, a.top DESC, a.id DESC");
	}
	else 
	{
		$goods = pdo_fetchall("SELECT a.id,a.name,a.cover,a.price,a.sale_count,a.status,a.create_time,a.recommend,a.top,b.title,b.pid FROM " . tablename("longbing_card_goods") . " a LEFT JOIN " . tablename("longbing_card_shop_type") . " b ON a.type = b.id where a.uniacid = " . $_W["uniacid"] . " && a.status > -1 && a.name LIKE '" . $keyword2 . "' ORDER BY a.recommend DESC, a.top DESC, a.id DESC");
	}
}
else 
{
	if( $type_value ) 
	{
		$goods = pdo_fetchall("SELECT a.id,a.name,a.cover,a.price,a.sale_count,a.status,a.create_time,a.recommend,a.top,b.title,b.pid FROM " . tablename("longbing_card_goods") . " a LEFT JOIN " . tablename("longbing_card_shop_type") . " b ON a.type = b.id where a.uniacid = " . $_W["uniacid"] . " && a.status > -1 && a.type = " . $type_value . " ORDER BY a.recommend DESC, a.top DESC, a.id DESC");
	}
	else 
	{
		$goods = pdo_fetchall("SELECT a.id,a.name,a.cover,a.price,a.sale_count,a.status,a.create_time,a.recommend,a.top,b.title,b.pid FROM " . tablename("longbing_card_goods") . " a LEFT JOIN " . tablename("longbing_card_shop_type") . " b ON a.type = b.id where a.uniacid = " . $_W["uniacid"] . " && a.status > -1 ORDER BY a.recommend DESC, a.top DESC, a.id DESC");
	}
}
$count = pdo_getall("longbing_card_goods", $where);
$count = count($count);
foreach( $goods as $k => $v ) 
{
	$goods[$k]["trueCover"] = tomedia($v["cover"]);
}
$offset = ($curr - 1) * 15;
$goods = array_slice($goods, $offset, 15);
$perPage = 15;
$type_list = pdo_getall("longbing_card_shop_type", array( "uniacid" => $_W["uniacid"], "status" => 1 ));
$config_info = pdo_get("longbing_card_config", array( "uniacid" => $_W["uniacid"] ));
$config_id = $config_info["id"];
load()->func("tpl");
include($this->template("manage/goods"));
?>