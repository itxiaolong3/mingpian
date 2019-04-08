<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
if( $_GPC["action"] == "edit" ) 
{
	$data = $_GPC["formData"];
	$data["update_time"] = time();
	$id = $_GPC["id"];
	$goods_id = $data["goods_id"];
	$result = false;
	if( $id ) 
	{
		$result = pdo_update("longbing_card_shop_collage", $data, array( "id" => $id ));
	}
	else 
	{
		$data["create_time"] = time();
		$data["uniacid"] = $_W["uniacid"];
		$result = pdo_insert("longbing_card_shop_collage", $data);
	}
	$goodsId = $_GPC["goods_id"];
	pdo_update("longbing_card_goods", array( "is_collage" => 1, "update_time" => time() ), array( "id" => $goodsId ));
	if( $result ) 
	{
		message("操作成功", $this->createWebUrl("manage/collage") . "&id=" . $goods_id, "success");
	}
	message("操作失败", "", "error");
}
$id = $_GPC["id"];
$goods_id = $_GPC["goods_id"];
$info = array( );
if( $id ) 
{
	$info = pdo_get("longbing_card_shop_collage", array( "id" => $id ));
	$goods_id = $info["goods_id"];
}
$data = array( );
$list = pdo_getall("longbing_card_shop_spe_price", array( "goods_id" => $goods_id, "uniacid" => $_W["uniacid"], "status" => 1 ));
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
load()->func("tpl");
include($this->template("manage/collageEdit"));
?>