<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$type = intval($_GPC["type"]);
$uniacid = $_W["uniacid"];
$time = time();
@pdo_update("longbing_card_coupon_record", array( "status" => 3 ), array( "end_time <" => $time ));
if( $type != 1 && $type != 2 ) 
{
	$type = 0;
}
if( !$uid ) 
{
	return $this->result(-1, "", array( ));
}
$where = array( "uniacid" => $uniacid, "status" => 1, "user_id" => $uid );
switch( $type ) 
{
	case 1: $where["status"] = 2;
	break;
	case 2: $where["status"] = 3;
	break;
}
$limit = array( 1, 10 );
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$list = pdo_getslice("longbing_card_coupon_record", $where, $limit, $count, array( ), "", array( "id desc" ));
foreach( $list as $index => $item ) 
{
	$list[$index]["create_time2"] = date("Y-m-d", $item["create_time"]);
	$list[$index]["end_time2"] = date("Y-m-d", $item["end_time"]);
	$list[$index]["left_days"] = 0;
	if( $time < $item["end_time"] ) 
	{
		$list[$index]["left_days"] = ceil(($item["end_time"] - $time) / (24 * 60 * 60));
	}
	$user_info = pdo_get("longbing_card_user_info", array( "fans_id" => $item["staff_id"] ));
	if( $user_info ) 
	{
		$user_info["avatar_true"] = tomedia($user_info["avatar"]);
		$list[$index]["user"] = $user_info;
	}
}
$dataAll = array( "page" => $limit[0], "total_page" => ceil($count / 10), "list" => $list );
return $this->result(0, "", $dataAll);
?>