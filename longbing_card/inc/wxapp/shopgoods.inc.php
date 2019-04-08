<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$type_id = $_GPC["type_id"];
$type = pdo_get("longbing_card_shop_type", array( "id" => $type_id ));
if( empty($type) && $type != 0 ) 
{
	return $this->result(-1, "", array( ));
}
$uniacid = $_W["uniacid"];
$limit = array( 1, $this->limit );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$where = array( "uniacid" => $uniacid, "status" => 1 );
$check_myshop = $this->checkMyShop($uid);
if( $check_myshop != false ) 
{
	$where["id in"] = $check_myshop;
}
if( $type == 0 ) 
{
	$list = pdo_getslice("longbing_card_goods", $where, $limit, $count, array( "id", "name", "cover", "top", "recommend", "price", "is_collage", "unit" ), "", array( "recommend desc", "top desc", "id desc" ));
}
else 
{
	if( $type["pid"] == 0 ) 
	{
		$where["type_p"] = $type_id;
		$list = pdo_getslice("longbing_card_goods", $where, $limit, $count, array( "id", "name", "cover", "top", "recommend", "price", "is_collage", "unit" ), "", array( "recommend desc", "top desc", "id desc" ));
	}
	else 
	{
		$where["type"] = $type_id;
		$list = pdo_getslice("longbing_card_goods", $where, $limit, $count, array( "id", "name", "cover", "top", "recommend", "price", "is_collage", "unit" ), "", array( "recommend desc", "top desc", "id desc" ));
	}
}
foreach( $list as $k => $v ) 
{
	$list[$k]["trueCover"] = tomedia($v["cover"]);
}
$data = array( "page" => $curr, "total_page" => ceil($count / $this->limit), "list" => $list );
return $this->result(0, "", $data);
?>