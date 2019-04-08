<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
if( isset($_GPC["action"]) && $_GPC["action"] == "search" ) 
{
	$searchText = $_GPC["searchText"];
	$searchTextLike = "%" . $searchText . "%";
	if( is_numeric($searchText) ) 
	{
		$user = pdo_fetch("SELECT a.*, b.total_profit, b.total_postal FROM " . tablename("longbing_card_user") . " a LEFT JOIN " . tablename("longbing_card_selling_profit") . " b ON a.id = b.user_id WHERE \r\n        a.uniacid = " . $_W["uniacid"] . " && a.status = 1 and a.level=1 && a.id = '" . $searchText . "'");
		if( !$user ) 
		{
			$user = pdo_fetch("SELECT a.*, b.total_profit, b.total_postal FROM " . tablename("longbing_card_user") . " a LEFT JOIN " . tablename("longbing_card_selling_profit") . " b ON a.id = b.user_id WHERE a.uniacid = " . $_W["uniacid"] . " && a.status = 1 and a.level=1 && a.nickName LIKE '" . $searchTextLike . "' OR \r\n        a.uniacid = " . $_W["uniacid"] . " && a.status = 1 && a.id = '" . $searchText . "'");
		}
	}
	else 
	{
		$user = pdo_fetch("SELECT a.*, b.total_profit, b.total_postal FROM " . tablename("longbing_card_user") . " a LEFT JOIN " . tablename("longbing_card_selling_profit") . " b ON a.id = b.user_id WHERE a.uniacid = " . $_W["uniacid"] . " && a.status = 1 and a.level=1 && a.nickName LIKE '" . $searchTextLike . "' OR \r\n        a.uniacid = " . $_W["uniacid"] . " && a.status = 1 && a.id = '" . $searchText . "'");
	}
	$user_show = 0;
	$user_p_show = 0;
	$user_list_show = 0;
	$user_p = array( );
	$user_list = array( );
	if( $user["pid"] ) 
	{
		$user_p = pdo_fetch("SELECT a.*, b.total_profit, b.total_postal FROM " . tablename("longbing_card_user") . " a LEFT JOIN " . tablename("longbing_card_selling_profit") . " b ON a.id = b.user_id WHERE a.uniacid = " . $_W["uniacid"] . " && a.status = 1 and a.level=1 && a.id = " . $user["pid"]);
	}
	$user_list = pdo_fetchall("SELECT a.*, b.total_profit, b.total_postal FROM " . tablename("longbing_card_user") . " a LEFT JOIN " . tablename("longbing_card_selling_profit") . " b ON a.id = b.user_id WHERE a.uniacid = " . $_W["uniacid"] . " && a.status = 1 and a.level=1 && a.pid = " . $user["id"]);
	if( $user ) 
	{
		$user_show = 1;
	}
	if( $user_p ) 
	{
		$user_p_show = 1;
	}
	if( $user_list ) 
	{
		$user_list_show = 1;
	}
}
$limit = array( 1, 15 );
$where = array( "uniacid" => $_W["uniacid"], "pid >" => 0,'level'=>1 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$offset = ($curr - 1) * 15;
$users = pdo_fetchall("SELECT a.*, b.id as p_id, b.nickName as p_nickName, b.avatarUrl as p_avatarUrl, c.total_profit, c.total_postal FROM " . tablename("longbing_card_user") . " a LEFT JOIN " . tablename("longbing_card_user") . " b ON a.pid = b.id LEFT JOIN " . tablename("longbing_card_selling_profit") . " c ON a.id = c.user_id where a.uniacid = " . $_W["uniacid"] . " && a.status = 1 and a.level=1 && a.pid != 0 ORDER BY a.leveltype DESC LIMIT " . $offset . ", 15");
$count = pdo_getall("longbing_card_user", $where);
$count = count($count);
$perPage = 15;
load()->func("tpl");
include($this->template("manage/relationship"));
?>