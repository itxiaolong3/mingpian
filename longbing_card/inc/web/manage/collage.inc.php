<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$editCollage = $this->createWebUrl("manage/collageEdit");
if( $_GPC["action"] == "deleteCollage" ) 
{
	$result = pdo_update("longbing_card_shop_collage", array( "status" => -1, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	$check = pdo_get("longbing_card_shop_collage", array( "status" => 1, "goods_id" => $_GPC["goodsId"] ));
	if( empty($check) ) 
	{
		pdo_update("longbing_card_goods", array( "is_collage" => 0, "update_time" => time() ), array( "id" => $_GPC["goodsId"] ));
	}
	if( $result ) 
	{
		message("删除成功", "", "success");
	}
	message("删除失败", "", "error");
}
if( !isset($_GPC["id"]) ) 
{
	return false;
}
$id = $_GPC["id"];
$goods = pdo_get("longbing_card_goods", array( "id" => $id ));
if( empty($goods) || !$goods ) 
{
	message("未找到商品", $this->createWebUrl("goods"), "error");
}
$goods["cover"] = tomedia($goods["cover"]);
$data = array( );
$list = pdo_getall("longbing_card_shop_spe_price", array( "goods_id" => $id, "uniacid" => $_W["uniacid"], "status" => 1 ));
foreach( $list as $k => $v ) 
{
	$spe_id_1 = $v["spe_id_1"];
	$arr = explode("-", $spe_id_1);
	$str = implode(",", $arr);
	if( strpos($str, ",") ) 
	{
		$str = "(" . $str . ")";
		$sql = "SELECT * FROM " . tablename("longbing_card_shop_spe") . " WHERE id IN " . $str;
	}
	else 
	{
		$sql = "SELECT * FROM " . tablename("longbing_card_shop_spe") . " WHERE id = " . $str;
	}
	$speList = pdo_fetchall($sql);
	$titles = "";
	foreach( $speList as $k2 => $v2 ) 
	{
		$titles .= "-" . $v2["title"];
	}
	$titles = trim($titles, "-");
	$tmp = array( "id" => $v["id"], "title" => $titles . ": ￥" . $v["price"] );
	array_push($data, $tmp);
}
$collage = pdo_getall("longbing_card_shop_collage", array( "goods_id" => $id, "status" => 1 ));
foreach( $collage as $k => $v ) 
{
	foreach( $data as $k2 => $v2 ) 
	{
		if( $v["spe_price_id"] == $v2["id"] ) 
		{
			$collage[$k]["info"] = $v2;
		}
	}
}
load()->func("tpl");
include($this->template("manage/collage"));
?>