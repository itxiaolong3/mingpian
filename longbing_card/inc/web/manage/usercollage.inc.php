<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/" . $_W["current_module"]["name"] . "/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$goodsList = $this->createWebUrl("manage/goods");
$limit = array( 1, 15 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$where = array( "uniacid" => $uniacid );
$where["collage_status in"] = array( 1, 2, 3 );
$list = pdo_getslice("longbing_card_shop_collage_list", $where, $limit, $count, array( ), "", array( "id desc" ));
foreach( $list as $k => $v ) 
{
}
$perPage = 15;
load()->func("tpl");
include($this->template("manage/userCollage"));
?>