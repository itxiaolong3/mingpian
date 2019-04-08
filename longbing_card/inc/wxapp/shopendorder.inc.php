<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$id = $_GPC["id"];
if( !$uid || !$id ) 
{
	return $this->result(-1, "", array( ));
}
$where = array( "uniacid" => $uniacid, "user_id" => $uid, "id" => $id );
$info = pdo_get("longbing_card_shop_order", $where);
if( !$info ) 
{
	return $this->result(-1, "", array( ));
}
if( $info["order_status"] != 2 ) 
{
	return $this->result(-1, "", array( ));
}
$result = pdo_update("longbing_card_shop_order", array( "order_status" => 3 ), $where);
if( $info["type"] == 1 ) 
{
	$infos = pdo_getall("longbing_card_shop_order", array( "collage_id" => $info["collage_id"], "order_status >" => 2 ));
	$collage = pdo_get("longbing_card_shop_collage_list", array( "id" => $info["collage_id"] ));
	if( $collage["number"] && $collage["number"] == count($infos) ) 
	{
		pdo_update("longbing_card_shop_collage_list", array( "collage_status" => 3 ), array( "id" => $info["collage_id"] ));
	}
}
if( $result ) 
{
	changeWater($id);
	return $this->result(0, "", array( ));
}
return $this->result(-1, "", array( ));
function changeWater($id) 
{
	$time = time();
	$list = pdo_getall("longbing_card_selling_water", array( "order_id" => $id, "waiting" => 1 ));
	pdo_update("longbing_card_selling_water", array( "waiting" => 2, "update_time" => $time ), array( "order_id" => $id, "waiting" => 1 ));
	foreach( $list as $index => $item ) 
	{
		$money = ($item["price"] * $item["extract"]) / 100;
		$money = sprintf("%.2f", $money);
		$profit = pdo_get("longbing_card_selling_profit", array( "user_id" => $item["user_id"] ));
		if( $profit ) 
		{
			if( $money <= $profit["waiting"] ) 
			{
				$waiting = $profit["waiting"] - $money;
				$total_profit = $profit["total_profit"] + $money;
				$profit_money = $profit["profit"] + $money;
				$waiting = sprintf("%.2f", $waiting);
				$total_profit = sprintf("%.2f", $total_profit);
				$profit_money = sprintf("%.2f", $profit_money);
			}
			else 
			{
				$waiting = 0;
				$money = $profit["waiting"];
				$total_profit = $profit["total_profit"] + $profit["waiting"];
				$profit_money = $profit["profit"] + $profit["waiting"];
				$total_profit = sprintf("%.2f", $total_profit);
				$profit_money = sprintf("%.2f", $profit_money);
			}
			pdo_update("longbing_card_selling_profit", array( "waiting" => $waiting, "total_profit" => $total_profit, "profit" => $profit_money ), array( "id" => $profit["id"] ));
			$user = pdo_get("longbing_card_user", array( "id" => $item["source_id"] ));
			if( $user ) 
			{
				$create_money = $user["create_money"] + $money;
				$create_money = sprintf("%.2f", $create_money);
				pdo_update("longbing_card_user", array( "create_money" => $create_money, "update_time" => $time ), array( "id" => $user["id"] ));
			}
		}
	}
}
?>