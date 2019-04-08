<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$record_id = $_GPC["record_id"];
$uniacid = $_W["uniacid"];
if( !$uid || !$record_id ) 
{
	return $this->result(-1, "", array( ));
}
$time = time();
$coupon_record_check = pdo_get("longbing_card_coupon_record", array( "staff_id" => $uid, "id" => $record_id ));
if( !$coupon_record_check ) 
{
	return $this->result(-2, "未找到该福包领取记录!" . $uid . "-" . $record_id, array( ));
}
if( $coupon_record_check["type"] != 2 ) 
{
	return $this->result(-2, "只有线下福包才能核销!", array( ));
}
if( $coupon_record_check["end_time"] < $time ) 
{
	return $this->result(-2, "福包已过期!", array( ));
}
if( $coupon_record_check["status"] != 1 ) 
{
	return $this->result(-2, "只有待使用的福包才能核销!", array( ));
}
$result = pdo_update("longbing_card_coupon_record", array( "status" => 2, "update_time" => $time ), array( "staff_id" => $uid, "id" => $record_id ));
if( $result ) 
{
	return $this->result(0, "", array( ));
}
return $this->result(-2, "核销失败", array( ));
?>