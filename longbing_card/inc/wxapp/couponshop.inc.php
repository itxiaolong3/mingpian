<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$to_uid = $_GPC["to_uid"];
$money = $_GPC["money"];
$uniacid = $_W["uniacid"];
@pdo_update("longbing_card_coupon_record", array( "status" => 3 ), array( "end_time <" => $time ));
$where = array( "uniacid" => $uniacid, "status" => 1, "user_id" => $uid, "staff_id" => $to_uid );
$list = array( );
$listTmp = pdo_getall("longbing_card_coupon_record", $where);
$time = time();
foreach( $listTmp as $index => $item ) 
{
	if( $item["full"] <= $money ) 
	{
		$item["create_time2"] = date("Y-m-d", $item["create_time"]);
		$item["end_time2"] = date("Y-m-d", $item["end_time"]);
		$item["left_days"] = 0;
		if( $time < $item["end_time"] ) 
		{
			$item["left_days"] = ceil(($item["end_time"] - $time) / (24 * 60 * 60));
		}
		$user_info = pdo_get("longbing_card_user_info", array( "fans_id" => $item["staff_id"] ));
		if( $user_info ) 
		{
			$user_info["avatar_true"] = tomedia($user_info["avatar"]);
			$item["user"] = $user_info;
		}
		$list[] = $item;
	}
}
return $this->result(0, "", array( "list" => $list ));
?>