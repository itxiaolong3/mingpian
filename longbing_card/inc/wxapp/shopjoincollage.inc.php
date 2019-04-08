<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$collage_id = $_GPC["collage_id"];
$goods_id = $_GPC["goods_id"];
$address_id = $_GPC["address_id"];
$number = $_GPC["number"];
$to_uid = $_GPC["to_uid"];
$number = intval($number);
if( !$address_id || !$number ) 
{
	return $this->result(-1, "", array( ));
}
$goods = pdo_get("longbing_card_goods", array( "id" => $goods_id ), array( "id", "name", "cover", "price", "freight" ));
if( empty($goods) ) 
{
	return $this->result(-1, "fail goods not found", array( ));
}
if( $address_id != -1 ) 
{
	$address = pdo_get("longbing_card_shop_address", array( "id" => $address_id, "user_id" => $uid ));
	if( empty($address) ) 
	{
		return $this->result(-1, "fail address nor found", array( ));
	}
}
else 
{
	$user_info_add = pdo_get("longbing_card_user", array( "id" => $uid ));
	$address["name"] = ($user_info_add["nickName"] ? $user_info_add["nickName"] : "");
	$address["sex"] = "无";
	$address["phone"] = 0;
	$address["address"] = "自提";
	$address["address_detail"] = "自提";
	$address["province"] = "自提";
	$address["city"] = "自提";
	$address["area"] = "自提";
}
$collage = pdo_get("longbing_card_shop_collage_list", array( "id" => $collage_id ));
if( empty($collage) ) 
{
	return $this->result(-1, "fail collage", array( ));
}
$collage2 = pdo_get("longbing_card_shop_collage", array( "id" => $collage["collage_id"] ));
if( !$collage2 || $number < $collage2["number"] ) 
{
	return $this->result(-1, "fail number", array( ));
}
if( $collage2["limit"] ) 
{
	$check_limit = pdo_get("longbing_card_shop_collage_list", array( "uniacid" => $uniacid, "user_id" => $uid, "collage_id" => $collage["collage_id"], "collage_status !=" => 4 ));
	if( $check_limit ) 
	{
		return $this->result(-1, "已经参加过此拼团, 不能重复参加", array( ));
	}
}
$checkOrder = pdo_get("longbing_card_shop_order", array( "collage_id" => $collage_id, "user_id" => $uid, "type" => 1 ));
if( $checkOrder ) 
{
	return $this->result(-1, "fail in", array( ));
}
if( $collage["left_number"] < 1 ) 
{
	return $this->result(-1, "fail full", array( ));
}
$time = time();
$price = sprintf("%.2f", $number * $collage["price"]);
$insertOrder = array( "user_id" => $uid, "address_id" => $address_id, "freight" => $goods["freight"], "price" => $price, "total_price" => sprintf("%.2f", $goods["freight"] + $price), "uniacid" => $uniacid, "name" => $address["name"], "sex" => $address["sex"], "phone" => $address["phone"], "address" => $address["address"], "address_detail" => $address["address_detail"], "province" => $address["province"], "city" => $address["city"], "area" => $address["area"], "type" => 1, "collage_id" => $collage_id, "to_uid" => $to_uid, "create_time" => $time, "update_time" => $time );
$result = pdo_insert("longbing_card_shop_order", $insertOrder);
$order_id = pdo_insertid();
if( !$result || !$collage2 ) 
{
	return $this->result(-1, "fail", array( ));
}
$spe_price = pdo_get("longbing_card_shop_spe_price", array( "id" => $collage2["spe_price_id"], "uniacid" => $uniacid ));
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
foreach( $speList as $k2 => $v2 ) 
{
	$titles .= "-" . $v2["title"];
}
$titles = trim($titles, "-");
$insertItem = array( "order_id" => $order_id, "goods_id" => $goods_id, "name" => $goods["name"], "cover" => $goods["cover"], "spe_price_id" => $collage2["spe_price_id"], "content" => $titles, "number" => $number, "price" => $price, "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time );
$result = pdo_insert("longbing_card_shop_order_item", $insertItem);
if( !$result ) 
{
	pdo_delete("longbing_card_shop_order", array( "id" => $order_id ));
	return $this->result(-1, "", array( ));
}
pdo_update("longbing_card_shop_collage_list", array( "left_number -=" => 1 ), array( "id" => $collage_id ));
pdo_insert("longbing_card_shop_user_collage", array( "user_id" => $uid, "collage_id" => $collage_id, "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time ));
return $this->result(0, "", array( "order_id" => $order_id ));
?>