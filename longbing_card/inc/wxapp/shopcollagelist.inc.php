<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$goods_id = $_GPC["goods_id"];
if( $goods_id ) 
{
	$sql = "SELECT a.id, a.user_id, a.goods_id, a.left_number, a.create_time, b.nickName, b.avatarUrl, c.name, c.cover, c.unit, d.spe_price_id, d.number, d.price, d.people FROM " . tablename("longbing_card_shop_collage_list") . " a LEFT JOIN " . tablename("longbing_card_user") . " b ON a.user_id = b.id LEFT JOIN " . tablename("longbing_card_goods") . " c ON a.goods_id = c.id LEFT JOIN " . tablename("longbing_card_shop_collage") . " d ON a.collage_id = d.id WHERE a.collage_status in (1,2,3) && a.uniacid = " . $uniacid . " && a.goods_id = " . $goods_id;
}
else 
{
	$sql = "SELECT a.id, a.user_id, a.goods_id, a.left_number, a.create_time, b.nickName, b.avatarUrl, c.name, c.cover, c.unit, d.spe_price_id, d.number, d.price, d.people FROM " . tablename("longbing_card_shop_collage_list") . " a LEFT JOIN " . tablename("longbing_card_user") . " b ON a.user_id = b.id LEFT JOIN " . tablename("longbing_card_goods") . " c ON a.goods_id = c.id LEFT JOIN " . tablename("longbing_card_shop_collage") . " d ON a.collage_id = d.id WHERE a.collage_status in (1,2,3) && a.uniacid = " . $uniacid;
}
$list = pdo_fetchall($sql);
$info = pdo_get("longbing_card_config", array( "uniacid" => $_W["uniacid"] ), array( "collage_overtime" ));
$collage_overtime = $info["collage_overtime"];
if( !$collage_overtime ) 
{
	$collage_overtime = 172800;
}
foreach( $list as $k => $v ) 
{
	$spe_price = pdo_get("longbing_card_shop_spe_price", array( "id" => $v["spe_price_id"], "uniacid" => $uniacid ));
	if( empty($spe_price) ) 
	{
		return $this->result(-1, "", array( ));
	}
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
	$left_time = $collage_overtime - (time() - $v["create_time"]);
	$list[$k]["cover_true"] = tomedia($v["cover"]);
	$list[$k]["left_time"] = $left_time;
	$list[$k]["titles"] = $titles;
	$users = pdo_getall("longbing_card_shop_user_collage", array( "collage_id" => $v["id"] ), array( "user_id" ), "", array( "id asc" ));
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
		$sqlOrder = "SELECT id, nickName, `avatarUrl` FROM " . tablename("longbing_card_shop_order") . " WHERE  uniacid = " . $uniacid . " && user_id in " . $ids . " && collage_id = " . $v["id"];
	}
	else 
	{
		$sql = "SELECT id, nickName, `avatarUrl` FROM " . tablename("longbing_card_user") . " WHERE  uniacid = " . $uniacid . " && id = " . $ids;
		$sqlOrder = "SELECT id, nickName, `avatarUrl` FROM " . tablename("longbing_card_shop_order") . " WHERE  uniacid = " . $uniacid . " && user_id = " . $ids . " && collage_id = " . $v["id"];
	}
	$users = pdo_fetchall($sql);
	$orders = pdo_fetchall($sql);
	$list[$k]["orders_info"] = $orders;
	$list[$k]["users"] = array( );
	foreach( $users as $k2 => $v2 ) 
	{
		if( $v2["id"] == $v["user_id"] ) 
		{
			$list[$k]["own"] = $v2;
		}
		else 
		{
			$list[$k]["users"][] = $v2;
		}
	}
}
return $this->result(0, "", $list);
?>