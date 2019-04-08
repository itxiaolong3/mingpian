<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$to_uid = $_GPC["to_uid"];
$coupon_id = $_GPC["coupon_id"];
$uniacid = $_W["uniacid"];
if( !$uid || !$to_uid || !$coupon_id ) 
{
	return $this->result(-1, "请传入参数", array( ));
}
if( $uid == $to_uid ) 
{
	return $this->result(-2, "自己不能领取自己的福包!", array( ));
}
$coupon = pdo_get("longbing_card_coupon", array( "id" => $coupon_id ));
if( !$coupon ) 
{
	return $this->result(-2, "没有找到你想要的福包!", array( ));
}
$coupon_record_check = pdo_get("longbing_card_coupon_record", array( "staff_id" => $to_uid, "coupon_id" => $coupon_id, "user_id" => $uid ));
if( $coupon_record_check ) 
{
	return $this->result(-2, "您已经领取过该福包啦!", array( ));
}
$coupon_record = pdo_getall("longbing_card_coupon_record", array( "staff_id" => $to_uid, "coupon_id" => $coupon_id ));
$count = count($coupon_record);
if( $coupon["number"] <= $count ) 
{
	return $this->result(-2, "该福包以领取完啦!", array( ));
}
$time = time();
$insert_data = array( "user_id" => $uid, "staff_id" => $to_uid, "coupon_id" => $coupon_id, "title" => $coupon["title"], "type" => $coupon["type"], "full" => $coupon["full"], "reduce" => $coupon["reduce"], "end_time" => $coupon["end_time"], "desc_coupon" => $coupon["desc_coupon"], "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time );
$result = pdo_insert("longbing_card_coupon_record", $insert_data);
if( $result ) 
{
	return $this->result(0, "", array( ));
}
return $this->result(-2, "领取失败", array( ));
?>