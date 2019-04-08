<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$type = $_GPC["type"];
$this->checkOrderTime();
if( !$type ) 
{
	$type = 0;
}
$limit = array( 1, 5 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$where = array( "uniacid" => $uniacid, "status !=" => -1, "user_id" => $uid );
$order_overtime = 1800;
$info = pdo_get("longbing_card_config", array( "uniacid" => $_W["uniacid"] ), array( "order_overtime" ));
$order_overtime = $info["order_overtime"];
if( !$order_overtime ) 
{
	$order_overtime = 1800;
}
switch( $type ) 
{
	case 1: $where["pay_status"] = 0;
	$where["order_status"] = 0;
	break;
	case 2: $where["pay_status"] = 1;
	$where["order_status"] = 0;
	break;
	case 3: $where["pay_status"] = 1;
	$where["order_status"] = 2;
	break;
	case 4: $where["pay_status"] = 1;
	$where["order_status >"] = 2;
	break;
}
$list = pdo_getslice("longbing_card_shop_order", $where, $limit, $count, array( ), "", array( "id desc" ));
foreach( $list as $k => $v ) 
{
	$sql = "SELECT a.id,a.order_id,a.goods_id,a.name,a.cover,a.number,a.price,a.content,b.id,b.unit,b.is_self FROM " . tablename("longbing_card_shop_order_item") . " a LEFT JOIN " . tablename("longbing_card_goods") . " b ON a.goods_id = b.id WHERE a.order_id = " . $v["id"] . " && a.uniacid = " . $uniacid;
	$goods_info = pdo_fetchall($sql);
	foreach( $goods_info as $k2 => $v2 ) 
	{
		$goods_info[$k2]["cover_true"] = tomedia($v2["cover"]);
	}
	$list[$k]["goods_info"] = $goods_info;
	if( $v["pay_status"] == 0 ) 
	{
		$left_time = $order_overtime - (time() - $v["create_time"]);
		$list[$k]["left_time"] = $left_time;
	}
	$list[$k]["write_off"] = 0;
	if( $v["address"] == "自提" ) 
	{
		$list[$k]["write_off"] = 1;
	}
}
$data = array( "page" => $curr, "total_page" => ceil($count / 5), "list" => $list );
return $this->result(0, "", $data);
?>