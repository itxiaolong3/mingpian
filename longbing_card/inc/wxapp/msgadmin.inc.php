<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$limit = array( 1, 15 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$list = pdo_getslice("longbing_card_group_sending", array( "staff_id" => 0, "uniacid" => $_W["uniacid"] ), $limit, $count, array( "id", "content", "create_time" ), "", "id desc");
$data = array( "page" => $curr, "total_page" => ceil($count / 15), "list" => $list, "total_count" => $count );
return $this->result(0, "", $data);
?>