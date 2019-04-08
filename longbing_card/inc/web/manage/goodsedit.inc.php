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
	$type = pdo_get("longbing_card_shop_type", array( "id" => $data["type"] ));
	$data["type_p"] = ($type["pid"] ? $type["pid"] : $type["id"]);
	$id = $_GPC["id"];
	$result = false;
	if( $id ) 
	{
		$result = pdo_update("longbing_card_goods", $data, array( "id" => $id ));
		$destination_folder = ATTACHMENT_ROOT . "/images" . "/longbing_card/" . $_W["uniacid"];
		$image2 = $destination_folder . "/" . $_W["uniacid"] . "-goods-" . $id . ".png";
		@unlink($image2);
	}
	else 
	{
		if( LONGBING_AUTH_GOODS ) 
		{
			$list = pdo_getall("longbing_card_goods", array( "uniacid" => $_W["uniacid"], "status >" => -1 ));
			$count = count($list);
			if( LONGBING_AUTH_GOODS <= $count ) 
			{
				message("添加商品达到上限, 如需增加请购买高级版本", "", "error");
			}
		}
		$data["create_time"] = time();
		$data["uniacid"] = $_W["uniacid"];
		$result = pdo_insert("longbing_card_goods", $data);
	}
	if( $result && !$id ) 
	{
		$goodsId = pdo_insertid();
		pdo_insert("ims_longbing_card_shop_spe", array( "goods_id" => $goodsId, "uniacid" => $_W["uniacid"], "title" => "默认", "create_time" => time(), "update_time" => time() ));
		$pid = pdo_insertid();
		pdo_insert("ims_longbing_card_shop_spe", array( "goods_id" => $goodsId, "uniacid" => $_W["uniacid"], "title" => "默认", "create_time" => time(), "update_time" => time(), "pid" => $pid ));
		$spe_id = pdo_insertid();
		pdo_insert("longbing_card_shop_spe_price", array( "goods_id" => $goodsId, "uniacid" => $_W["uniacid"], "spe_id_1" => $spe_id, "create_time" => time(), "update_time" => time(), "price" => $data["price"], "stock" => $data["stock"] ));
	}
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		if( !$id ) 
		{
			message("操作成功，请前往编辑商品规格", $this->createWebUrl("manage/goods"), "success");
		}
		message("操作成功", $this->createWebUrl("manage/goods"), "success");
	}
	message("操作失败", "", "error");
}
$where = array( "uniacid" => $_W["uniacid"] );
$id = 0;
$info = array( );
if( isset($_GPC["id"]) ) 
{
	$where["id"] = $_GPC["id"];
	$id = $_GPC["id"];
	$info = pdo_get("longbing_card_goods", $where);
	if( $info["images"] ) 
	{
		$info["images"] = explode(",", $info["images"]);
	}
}
$typeList = pdo_getall("longbing_card_shop_type", array( "status" => 1, "uniacid" => $_W["uniacid"] ));
$typeList_tmp = array( );
foreach( $typeList as $k => $v ) 
{
	if( $v["pid"] != 0 ) 
	{
		$typeList[$k]["title"] = "&nbsp;&nbsp;&nbsp;&nbsp;|----&nbsp;&nbsp;" . $v["title"];
	}
}
foreach( $typeList as $k => $v ) 
{
	if( $v["pid"] == 0 ) 
	{
		array_push($typeList_tmp, $v);
		foreach( $typeList as $index => $item ) 
		{
			if( $item["pid"] != 0 && $v["id"] == $item["pid"] ) 
			{
				array_push($typeList_tmp, $item);
			}
		}
	}
}
$typeList = $typeList_tmp;
load()->func("tpl");
include($this->template("manage/goodsEdit"));
?>