<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/" . $_W["current_module"]["name"] . "/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$id = $_GPC["id"];
$company = array( );
if( $id ) 
{
	$company = pdo_get("longbing_card_company", array( "uniacid" => $_W["uniacid"], "id" => $id ));
	if( !$company || empty($company) ) 
	{
		$id = 0;
	}
	$company["culture"] = explode(",", $company["culture"]);
}
load()->func("tpl");
include($this->template("manage/companyEdit"));
?>