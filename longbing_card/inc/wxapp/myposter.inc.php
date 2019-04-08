<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$uniacid = $_W["uniacid"];
$curr = 1;
$limit = array( 1, 15 );
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$list = pdo_getslice("longbing_card_user_poster", array( "uniacid" => $uniacid, "status" => 1, "user_id" => $uid ), $limit, $count, array( "id", "title", "img" ), "", array( "id desc" ));
foreach( $list as $index => $item ) 
{
	$list[$index]["img"] = tomedia($item["img"]);
	$list[$index]["img_2"] = $this->transImage($list[$index]["img"]);
}
$data = array( "page" => $curr, "total_page" => ceil($count / 15), "list" => $list );
return $this->result(0, "suc", $data);
?>