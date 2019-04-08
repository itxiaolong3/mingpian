<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$limit = array( 1, 15 );
$where = array( "uniacid" => $_W["uniacid"] );
$curr = 1;
if( isset($_GPC["page"]) )
{
    $limit[0] = $_GPC["page"];
    $curr = $_GPC["page"];
}
$module_name = $_W["current_module"]["name"];
$keyword = "";
if( isset($_GPC["keyword"]) )
{
    $where["randcode like"] = "%" . $_GPC["keyword"] . "%";
    $keyword = $_GPC["keyword"];
}
$info = pdo_getslice("longbing_card_randcode", $where, $limit, $count, array( ), "", array("id desc" ));
load()->func("tpl");
include($this->template("manage/randcodelist"));
?>