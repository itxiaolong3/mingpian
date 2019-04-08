<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$user_id = $_GPC["user_id"];
$type = intval($_GPC["type"]);
$uniacid = $_W["uniacid"];
if( !$user_id ) 
{
	return $this->result(-1, "", array( ));
}
if( $type != 1 && $type != 2 && $type != 3 ) 
{
	$type = 1;
}
$where = array( "status" => 1, "user_id" => $user_id, "uniacid" => $uniacid );
if( $type == 2 ) 
{
	$where["waiting"] = 1;
}
if( $type == 3 ) 
{
	$where["waiting"] = 2;
}
$limit = array( 1, 10 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$list = pdo_getslice("longbing_card_selling_water", $where, $limit, $count, array( ), "", array( "id desc" ));
foreach( $list as $index => $item ) 
{
	$list[$index]["img"] = tomedia($item["img"]);
	$list[$index]["create_time2"] = date("Y-m-d H:i:s", $item["create_time"]);
	$list[$index]["create_time3"] = date("Y-m-d H:i", $item["create_time"]);
	$list[$index]["create_time4"] = date("Y-m-d", $item["create_time"]);
	$user_info = pdo_get("longbing_card_user", array( "id" => $item["source_id"] ), array( "nickName", "avatarUrl" ));
	$list[$index]["user_info"] = $user_info;
	$list[$index]["extract_money"] = ($item["extract"] * $item["price"]) / 100;
	$list[$index]["extract_money"] = sprintf("%.2f", $list[$index]["extract_money"]);
	$list[$index]["extract_money_single"] = ($item["extract"] * $item["price"]) / 100 / $item["buy_number"];
	$list[$index]["extract_money_single"] = sprintf("%.2f", $list[$index]["extract_money_single"]);
}
$data = array( "page" => $curr, "total_page" => intval(ceil($count / 10)), "list" => $list );
return $this->result(0, "", $data);
?>