<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$limit = array( 1, 15 );
$where = array( "uniacid" => $_W["uniacid"] );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$list = pdo_getslice("longbing_card_selling_profit", $where, $limit, $count, array( ), "", array( "total_profit desc", "id desc" ));
$perPage = 15;
foreach( $list as $index => $item ) 
{
	$list[$index]["user_info"] = array( );
	$list[$index]["staff_info"] = array( );
	$user_info = pdo_get("longbing_card_user", array( "id" => $item["user_id"] ), array( "nickName", "is_staff" ));
	if( $user_info ) 
	{
		$list[$index]["user_info"] = $user_info;
		if( $user_info["is_staff"] == 1 ) 
		{
			$staff_info = pdo_get("longbing_card_user_info", array( "fans_id" => $item["user_id"] ), array( "name" ));
			if( $staff_info ) 
			{
				$list[$index]["staff_info"] = $staff_info;
			}
		}
	}
}
load()->func("tpl");
include($this->template("manage/profit"));
?>