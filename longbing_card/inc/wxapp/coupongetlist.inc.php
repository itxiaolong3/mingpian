<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$coupon_id = $_GPC["coupon_id"];
$uniacid = $_W["uniacid"];
$time = time();
$coupon = pdo_get("longbing_card_coupon", array( "id" => $coupon_id ));
if( !$coupon ) 
{
	return $this->result(-2, "未找到福包信息", array( ));
}
$record = pdo_getall("longbing_card_coupon_record", array( "staff_id" => $uid, "coupon_id" => $coupon_id ), array( ), "", array( "id desc" ));
$coupon["get_number"] = count($record);
$coupon["used_number"] = 0;
foreach( $record as $index => $item ) 
{
	$record[$index]["user_info"] = array( );
	if( $item["status"] == 2 ) 
	{
		$coupon["used_number"] += 1;
	}
	$user_info = pdo_get("longbing_card_user", array( "id" => $item["user_id"] ));
	if( $user_info ) 
	{
		$user_phone = pdo_get("longbing_card_user_phone", array( "user_id" => $item["user_id"] ));
		$user_info["phone"] = "";
		if( $user_phone ) 
		{
			$user_info["phone"] = $user_phone["phone"];
		}
		$record[$index]["user_info"] = $user_info;
	}
}
$coupon["record"] = $record;
return $this->result(0, "请求成功", $coupon);
?>