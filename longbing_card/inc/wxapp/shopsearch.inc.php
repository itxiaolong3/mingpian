<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$keyword = $_GPC["keyword"];
if( !$keyword ) 
{
	return $this->result(0, "", array( ));
}
$check = pdo_get("longbing_card_shop_search", array( "user_id" => $uid, "keyword" => $keyword ));
$time = time();
if( empty($check) ) 
{
	$insertData = array( "user_id" => $uid, "keyword" => $keyword, "number" => 1, "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time );
	pdo_insert("longbing_card_shop_search", $insertData);
}
else 
{
	$updateData = array( "number" => $check["number"] + 1, "update_time" => $time );
	pdo_update("longbing_card_shop_search", $updateData, array( "id" => $check["id"] ));
}
$keyword = "%" . $keyword . "%";
$list = pdo_getall("longbing_card_goods", array( "uniacid" => $uniacid, "status" => 1, "name like" => $keyword ), array( "id", "name", "cover", "top", "recommend", "price", "is_collage" ), "", array( "recommend desc", "top desc", "id desc" ));
foreach( $list as $k => $v ) 
{
	$list[$k]["cover_true"] = tomedia($v["cover"]);
	$list[$k]["trueCover"] = $list[$k]["cover_true"];
}
return $this->result(0, "", $list);
?>