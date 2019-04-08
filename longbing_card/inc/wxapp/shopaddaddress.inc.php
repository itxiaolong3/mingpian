<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$name = $_GPC["name"];
$sex = $_GPC["sex"];
$phone = $_GPC["phone"];
$address = $_GPC["address"];
$address_detail = $_GPC["address_detail"];
$province = $_GPC["province"];
$city = $_GPC["city"];
$area = $_GPC["area"];
$id = $_GPC["id"];
$time = time();
$data = array( "uniacid" => $uniacid, "user_id" => $uid, "name" => $name, "sex" => $sex, "phone" => $phone, "address" => $address, "address_detail" => $address_detail, "province" => $province, "city" => $city, "area" => $area, "update_time" => $time );
if( !$id ) 
{
	$data["create_time"] = $time;
	$check = pdo_get("longbing_card_shop_address", array( "user_id" => $uid ));
	if( empty($check) ) 
	{
		$data["is_default"] = 1;
	}
	pdo_insert("longbing_card_shop_address", $data);
}
else 
{
	$check = pdo_get("longbing_card_shop_address", array( "user_id" => $uid, "id" => $id ));
	if( empty($check) ) 
	{
		return $this->result(-1, "", array( ));
	}
	pdo_update("longbing_card_shop_address", $data, array( "id" => $id ));
}
return $this->result(0, "", array( ));
?>