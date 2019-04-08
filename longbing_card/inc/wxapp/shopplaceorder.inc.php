<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$address_id = $_GPC["address_id"];
$trolley_ids = $_GPC["trolley_ids"];
$to_uid = $_GPC["to_uid"];
$trolley_ids = trim($trolley_ids, ",");
$record_id = $_GPC["record_id"];
$direct = $_GPC["direct"];
$number = $_GPC["number"];
$trolley_arr = explode(",", $trolley_ids);
if( !$address_id || empty($trolley_arr) ) 
{
	return $this->result(-1, "", array( ));
}
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
$trolley_in = "(" . $trolley_ids . ")";
if( 1 < count($trolley_arr) ) 
{
	$sql = "SELECT a.id,a.spe_price_id,a.number,a.goods_id,b.name,b.cover,b.freight FROM " . tablename("longbing_card_shop_trolley") . " a LEFT JOIN " . tablename("longbing_card_goods") . " b ON a.goods_id = b.id WHERE a.user_id = " . $uid . " && a.status = 1 && a.id in " . $trolley_in;
}
else 
{
	$sql = "SELECT a.id,a.spe_price_id,a.number,a.goods_id,b.name,b.cover,b.freight FROM " . tablename("longbing_card_shop_trolley") . " a LEFT JOIN " . tablename("longbing_card_goods") . " b ON a.goods_id = b.id WHERE a.user_id = " . $uid . " && a.status = 1 && a.id = " . $trolley_ids;
}
$list = pdo_fetchall($sql);
$total_freight = 0;
$price = 0;
foreach( $list as $k => $v ) 
{
	$spe_price = pdo_get("longbing_card_shop_spe_price", array( "id" => $v["spe_price_id"], "uniacid" => $uniacid ));
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
	$list[$k]["spe"] = $titles;
	$list[$k]["price"] = $spe_price["price"] * $v["number"];
	$total_freight += $v["freight"];
	$price += $spe_price["price"] * $v["number"];
}
$time = time();
$total_freight = sprintf("%.2f", $total_freight);
$price = sprintf("%.2f", $price);
$insertOrder = array( "user_id" => $uid, "address_id" => $address_id, "freight" => $total_freight, "price" => $price, "total_price" => sprintf("%.2f", $total_freight + $price), "uniacid" => $uniacid, "name" => $address["name"], "sex" => $address["sex"], "phone" => $address["phone"], "address" => $address["address"], "address_detail" => $address["address_detail"], "province" => $address["province"], "city" => $address["city"], "area" => $address["area"], "to_uid" => $to_uid, "create_time" => $time, "update_time" => $time );
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
	foreach( $list as $k => $v ) 
	{
		$insertItem = array( "order_id" => $order_id, "goods_id" => $v["goods_id"], "name" => $v["name"], "cover" => $v["cover"], "spe_price_id" => $v["spe_price_id"], "content" => $v["spe"], "number" => $v["number"], "price" => $v["price"], "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time );
		$result = pdo_insert("longbing_card_shop_order_item", $insertItem);
	}
	return $this->result(0, "", array( "order_id" => $order_id ));
}
else 
{
	return $this->result(-1, "", array( ));
}
?>