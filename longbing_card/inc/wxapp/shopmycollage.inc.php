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
$where = array( "user_id" => $uid, "uniacid" => $uniacid );
switch( $type ) 
{
	case 1: $where["collage_status"] = 1;
	break;
	case 2: $where["collage_status in"] = array( 2, 3 );
	break;
	case 3: $where["collage_status"] = 4;
	break;
}
$list = pdo_getslice("longbing_card_shop_user_collage", $where, $limit, $count, array( "user_id", "collage_id", "collage_status" ), "", array( "id desc" ));
foreach( $list as $k => $v ) 
{
	$sql = "SELECT a.id, a.user_id, a.goods_id, a.left_number, a.create_time, c.name, c.cover, c.unit, d.number, d.people, d.price, d.spe_price_id FROM " . tablename("longbing_card_shop_collage_list") . " a LEFT JOIN " . tablename("longbing_card_goods") . " c ON a.goods_id = c.id LEFT JOIN " . tablename("longbing_card_shop_collage") . " d ON a.collage_id = d.id WHERE a.uniacid = " . $uniacid . " && a.id = " . $v["collage_id"];
	$collage_info = pdo_fetch($sql);
	$collage_info["cover_true"] = tomedia($collage_info["cover"]);
	$spe = $collage_info["spe_price_id"];
	$spe_price = pdo_get("longbing_card_shop_spe_price", array( "id" => $spe, "uniacid" => $uniacid ));
	$spe_id_1 = $spe_price["spe_id_1"];
	$arr = explode("-", $spe_id_1);
	$str = implode(",", $arr);
	if( strpos($str, ",") ) 
	{
		$str = "(" . $str . ")";
		$sql = "SELECT * FROM " . tablename("longbing_card_shop_spe") . " WHERE id IN " . $str;
	}
	else 
	{
		$sql = "SELECT * FROM " . tablename("longbing_card_shop_spe") . " WHERE id = " . $str;
	}
	$speList = pdo_fetchall($sql);
	$titles = "";
	foreach( $speList as $k2 => $v2 ) 
	{
		$titles .= "-" . $v2["title"];
	}
	$titles = trim($titles, "-");
	$collage_info["titles"] = $titles;
	$list[$k]["collage_info"] = $collage_info;
	$list[$k]["unit"] = $collage_info["unit"];
	$order_info = pdo_getall("longbing_card_shop_order", array( "type" => 1, "collage_id" => $v["collage_id"] ));
	$list[$k]["order_info"] = $order_info;
	$users = pdo_getall("longbing_card_shop_user_collage", array( "collage_id" => $v["collage_id"] ), array( "user_id" ), "", array( "id asc" ));
	$ids = "";
	foreach( $users as $k2 => $v2 ) 
	{
		$ids .= "," . $v2["user_id"];
	}
	$ids = trim($ids, ",");
	if( 1 < count($users) ) 
	{
		$ids = "(" . $ids . ")";
		$sql = "SELECT id, nickName, `avatarUrl` FROM " . tablename("longbing_card_user") . " WHERE  uniacid = " . $uniacid . " && id in " . $ids;
	}
	else 
	{
		$sql = "SELECT id, nickName, `avatarUrl` FROM " . tablename("longbing_card_user") . " WHERE  uniacid = " . $uniacid . " && id = " . $ids;
	}
	$users = pdo_fetchall($sql);
	$list[$k]["users"] = array( );
	foreach( $users as $k2 => $v2 ) 
	{
		if( $v2["id"] == $collage_info["user_id"] ) 
		{
			$list[$k]["own"] = $v2;
		}
		else 
		{
			array_push($list[$k]["users"], $v2);
		}
	}
}
return $this->result(0, "", $list);
?>