<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$address_id = $_GPC["address_id"];
$to_uid = $_GPC["to_uid"];
$record_id = $_GPC["record_id"];
$number = $_GPC["number"];
$goods_id = $_GPC["goods_id"];
$spe_id = $_GPC["spe_price_id"];
if( !$address_id || !$number || !$goods_id ) 
{
	return $this->result(-1, "", array( ));
}
$goods_info = pdo_get("longbing_card_goods", array( "id" => $goods_id ));
$spe_info = pdo_get("longbing_card_shop_spe_price", array( "id" => $spe_id ));
if( !$goods_info || !$spe_info ) 
{
	return $this->result(-1, "", array( ));
}
$arr = explode("-", $spe_info["spe_id_1"]);
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
if( $address_id != -1 ) 
{
	$address = pdo_get("longbing_card_shop_address", array( "id" => $address_id, "user_id" => $uid ));
	if( empty($address) ) 
	{
		return $this->result(-1, "", array( ));
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
$time = time();
$total_freight = sprintf("%.2f", $goods_info["freight"]);
$price = sprintf("%.2f", $spe_info["price"] * $number);
$insertOrder = array( "user_id" => $uid, "address_id" => $address_id, "freight" => $total_freight, "price" => $price, "total_price" => $total_freight + $price, "uniacid" => $uniacid, "name" => $address["name"], "sex" => $address["sex"], "phone" => $address["phone"], "address" => $address["address"], "address_detail" => $address["address_detail"], "province" => $address["province"], "city" => $address["city"], "area" => $address["area"], "to_uid" => $to_uid, "create_time" => $time, "update_time" => $time );
if( $record_id ) 
{
	$sign = true;
	$coupon_record_check = pdo_get("longbing_card_coupon_record", array( "user_id" => $uid, "id" => $record_id ));
	if( !$coupon_record_check ) 
	{
		$sign = false;
	}
	if( $coupon_record_check["type"] != 1 ) 
	{
		$sign = false;
	}
	if( $coupon_record_check["end_time"] < $time ) 
	{
		$sign = false;
	}
	if( $coupon_record_check["status"] != 1 ) 
	{
		$sign = false;
	}
	if( $sign ) 
	{
		$insertOrder["record_id"] = $record_id;
		if( $coupon_record_check["reduce"] < $insertOrder["total_price"] ) 
		{
			$insertOrder["total_price"] = sprintf("%.2f", $insertOrder["total_price"] - $coupon_record_check["reduce"]);
			$insertOrder["record_money"] = $coupon_record_check["reduce"];
		}
		else 
		{
			$insertOrder["total_price"] = 0;
			$insertOrder["record_money"] = sprintf("%.2f", $insertOrder["total_price"]);
		}
		$result = pdo_update("longbing_card_coupon_record", array( "status" => 2, "update_time" => $time ), array( "user_id" => $uid, "id" => $record_id ));
	}
}
$result = pdo_insert("longbing_card_shop_order", $insertOrder);
if( !empty($result) ) 
{
	$order_id = pdo_insertid();
	$insertItem = array( "order_id" => $order_id, "goods_id" => $goods_id, "name" => $goods_info["name"], "cover" => $goods_info["cover"], "spe_price_id" => $spe_id, "content" => $titles, "number" => $number, "price" => sprintf("%.2f", $spe_info["price"] * $number), "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time );
	$result = pdo_insert("longbing_card_shop_order_item", $insertItem);
	return $this->result(0, "", array( "order_id" => $order_id ));
}
return $this->result(-1, "", array( ));
?>