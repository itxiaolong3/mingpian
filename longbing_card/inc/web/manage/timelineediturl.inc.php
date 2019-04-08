<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$id = $_GPC["id"];
$info = array( );
if( $id ) 
{
	$info = pdo_get("longbing_card_timeline", array( "uniacid" => $_W["uniacid"], "id" => $id ));
	$info["create_time"] = date("Y-m-d H:i:s", $info["create_time"]);
	if( !$info || empty($info) ) 
	{
		$id = 0;
	}
}
load()->func("tpl");
include($this->template("manage/timelineEditUrl"));
?>