<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$goods_id = $_GPC["goods_id"];
$spe_price_id = $_GPC["spe_price_id"];
$number = $_GPC["number"];
$number = intval($number);
$goods = pdo_get("longbing_card_goods", array( "id" => $goods_id ), array( "id", "name", "cover", "price", "freight" ));
if( empty($goods) ) 
{
	return $this->result(-1, "", array( ));
}
$spe_price = pdo_get("longbing_card_shop_spe_price", array( "id" => $spe_price_id, "uniacid" => $uniacid ));
if( empty($spe_price) ) 
{
	return $this->result(-1, "", array( ));
}
$spe_id_1 = $spe_price["spe_id_1"];
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
foreach( $speList as $k => $v ) 
{
	$titles .= "-" . $v["title"];
}
$titles = trim($titles, "-");
$check = pdo_get("longbing_card_shop_trolley", array( "goods_id" => $goods_id, "spe_price_id" => $spe_price_id, "user_id" => $uid, "status" => 1 ));
$time = time();
$result = false;
$price = sprintf("%.2f", $number * $spe_price["price"]);
if( !$check ) 
{
	$insertData = array( "goods_id" => $goods_id, "user_id" => $uid, "name" => $goods["name"], "cover" => $goods["cover"], "spe_price_id" => $spe_price_id, "content" => $titles, "number" => $number, "uniacid" => $uniacid, "price" => $price, "freight" => $goods["freight"], "create_time" => $time, "update_time" => $time );
	$result = pdo_insert("longbing_card_shop_trolley", $insertData);
	$insert_id = pdo_insertid();
}
else 
{
	$updateData = array( "name" => $goods["name"], "cover" => $goods["cover"], "spe_price_id" => $spe_price_id, "content" => $titles, "price" => sprintf("%.2f", $check["price"] + $price), "number" => sprintf("%.2f", $check["number"] + $number), "freight" => $goods["freight"], "update_time" => $time );
	$insert_id = $check["id"];
	$result = pdo_update("longbing_card_shop_trolley", $updateData, array( "id" => $check["id"] ));
}
if( $result ) 
{
	return $this->result(0, "", array( "id" => $insert_id ));
}
return $this->result(-1, "", array( ));
?>