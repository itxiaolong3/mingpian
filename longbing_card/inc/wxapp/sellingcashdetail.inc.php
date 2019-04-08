<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$user_id = $_GPC["user_id"];
$uniacid = $_W["uniacid"];
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
$list = pdo_getslice("longbing_card_user", array( "pid" => $user_id, "uniacid" => $uniacid ), $limit, $count, array( "id", "nickName", "avatarUrl", "create_time", "create_money" ), "", array( "create_money desc", "id desc" ));
$total_money = 0;
foreach( $list as $index => $item ) 
{
	$total_money += $item["create_money"];
	$list[$index]["create_time2"] = date("Y-m-d H:i:s", $item["create_time"]);
	$list[$index]["create_time3"] = date("Y-m-d H:i", $item["create_time"]);
	$list[$index]["create_time4"] = date("Y-m-d", $item["create_time"]);
}
$data = array( "page" => $curr, "total_page" => ceil($count / 15), "list" => $list, "total_money" => $total_money );
$data["total_profit"] = 0;
$profit_info = pdo_get("longbing_card_selling_profit", array( "user_id" => $user_id ));
if( $profit_info ) 
{
	$data["total_profit"] = $profit_info["total_profit"];
}
return $this->result(0, "", $data);
?>