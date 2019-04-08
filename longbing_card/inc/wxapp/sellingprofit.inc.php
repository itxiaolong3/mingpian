<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$user_id = $_GPC["user_id"];
$uniacid = $_W["uniacid"];
if( !$user_id ) 
{
	return $this->result(-1, "", array( ));
}
$profit = pdo_get("longbing_card_selling_profit", array( "user_id" => $user_id ));
if( !$profit ) 
{
	$time = time();
	$res = pdo_insert("longbing_card_selling_profit", array( "user_id" => $user_id, "total_profit" => 0, "total_postal" => 0, "postaling" => 0, "waiting" => 0, "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time ));
	if( !$res ) 
	{
		return $this->result(0, "", array( ));
	}
	$profit = pdo_get("longbing_card_selling_profit", array( "user_id" => $user_id ));
	if( !$profit ) 
	{
		return $this->result(0, "", array( ));
	}
}
$beginTime = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
$list = pdo_getall("longbing_card_selling_water", array( "waiting" => 2, "update_time >" => $beginTime, "user_id" => $user_id ));
$today_profit = 0;
foreach( $list as $index => $item ) 
{
	$today_profit += ($item["price"] * $item["extract"]) / 100;
	$today_profit = sprintf("%.2f", $today_profit);
}
$profit["today_profit"] = $today_profit;
$partner = pdo_getall("longbing_card_user", array( "pid" => $user_id, "uniacid" => $uniacid ));
$profit["partner"] = count($partner);
$profit["today_partner"] = 0;
foreach( $partner as $index => $item ) 
{
	if( $beginTime < $item["create_time"] ) 
	{
		$profit["today_partner"] += 1;
	}
}
$where = array( "user_id" => $user_id, "status" => 1 );
$list = pdo_getslice("longbing_card_selling_water", $where, array( 1, 2 ), $count, array( ), "", array( "id desc" ));
foreach( $list as $index => $item ) 
{
	$list[$index]["img"] = tomedia($item["img"]);
	$tmp = $item["price"] / $item["buy_number"];
	$list[$index]["single_price"] = sprintf("%.2f", $tmp);
	$user_info = pdo_get("longbing_card_user", array( "id" => $item["source_id"] ), array( "nickName", "avatarUrl" ));
	$list[$index]["user_info"] = $user_info;
	$list[$index]["extract_money"] = ($item["extract"] * $item["price"]) / 100;
	$list[$index]["extract_money"] = sprintf("%.2f", $list[$index]["extract_money"]);
	$list[$index]["extract_money_single"] = ($item["extract"] * $item["price"]) / 100 / $item["buy_number"];
	$list[$index]["extract_money_single"] = sprintf("%.2f", $list[$index]["extract_money_single"]);
}
$profit["water"] = $list;
$profit["cash_mini"] = 0;
$config_info = pdo_get("longbing_card_config", array( "uniacid" => $_W["uniacid"] ));
if( $config_info && isset($config_info["cash_mini"]) && $config_info["cash_mini"] ) 
{
	$profit["cash_mini"] = $config_info["cash_mini"];
}
return $this->result(0, "", $profit);
?>