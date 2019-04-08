<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$user_id = $_GPC["user_id"];
$uniacid = $_W["uniacid"];
$type = $_GPC["type"];
if( $type != 0 && $type != 1 ) 
{
	$type = 0;
}
if( !$user_id ) 
{
	return $this->result_self(-1, "", array( ));
}
$limit = array( 1, 15 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$where = array( "uniacid" => $uniacid, "user_id" => $user_id, "status" => 1 );
if( $type == 0 ) 
{
	$where["status"] = 0;
}
$list = pdo_getslice("longbing_card_selling_cash_water", $where, $limit, $count, array( "id", "user_id", "account", "status", "money", "create_time", "cash_no" ), "", array( "id desc" ));
foreach( $list as $index => $item ) 
{
	$list[$index]["create_time2"] = date("Y-m-d H:i:s", $item["create_time"]);
	$list[$index]["create_time3"] = date("Y-m-d H:i", $item["create_time"]);
	$list[$index]["create_time4"] = date("Y-m-d", $item["create_time"]);
}
$profit = pdo_get("longbing_card_selling_profit", array( "user_id" => $user_id ));
if( !$profit ) 
{
	$total_postal = 0;
}
else 
{
	$total_postal = $profit["total_postal"];
}
$data = array( "page" => $curr, "total_page" => ceil($count / 15), "list" => $list, "total_postal" => $total_postal );
return $this->result(0, "", $data);
?>