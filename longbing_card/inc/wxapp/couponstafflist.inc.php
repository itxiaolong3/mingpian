<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$uniacid = $_W["uniacid"];
$time = time();
@pdo_update("longbing_card_coupon_record", array( "status" => 3 ), array( "end_time <" => $time ));
$limit = array( 1, 15 );
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$coupon = pdo_getslice("longbing_card_coupon", array( "uniacid" => $uniacid ), $limit, $count2, array( ), "", array( "top desc", "id desc" ));
foreach( $coupon as $index => $item ) 
{
	$coupon[$index]["is_end"] = 0;
	$coupon[$index]["create_time2"] = date("Y-m-d", $item["create_time"]);
	$coupon[$index]["end_time2"] = date("Y-m-d", $item["end_time"]);
	if( $item["end_time"] < $time ) 
	{
		$coupon[$index]["is_end"] = 1;
	}
	$check = pdo_getall("longbing_card_coupon_record", array( "uniacid" => $uniacid, "staff_id" => $uid, "coupon_id" => $item["id"] ), array( ), "", array( "id desc" ));
	$count = count($check);
	$coupon[$index]["is_over"] = 0;
	$coupon[$index]["get_number"] = $count;
	if( $item["number"] <= $count ) 
	{
		$coupon[$index]["is_over"] = 1;
	}
	$coupon[$index]["users"] = array( );
	foreach( $check as $index2 => $item2 ) 
	{
		$user_info = pdo_get("longbing_card_user", array( "id" => $item2["user_id"] ));
		if( $user_info ) 
		{
			array_push($coupon[$index]["users"], $user_info["avatarUrl"]);
		}
	}
}
$data = array( "page" => $limit[0], "total_page" => ceil($count2 / 15), "list" => $coupon );
return $this->result(0, "", $data);
?>