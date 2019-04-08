<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$redis_sup_v3 = false;
$redis_server_v3 = false;
include_once($_SERVER["DOCUMENT_ROOT"] . "/addons/longbing_card/images/phpqrcode/func_longbing.php");
if( function_exists("longbing_check_redis") ) 
{
	$config = $_W["config"]["setting"]["redis"];
	$password = "";
	if( $config && isset($config["requirepass"]) && $config["requirepass"] ) 
	{
		$password = $config["requirepass"];
	}
	if( $config && isset($config["server"]) && $config["server"] && isset($config["port"]) && $config["port"] ) 
	{
		list($redis_sup_v3, $redis_server_v3) = longbing_check_redis($config["server"], $config["port"], $password);
	}
}
if( $_GPC["action"] == "edit" ) 
{

    $data = $_GPC["formData"];
    $savedata['onebili']=$data['onebili'];
    $savedata['twobili']=$data['twobili'];
    $savedata['yellowgold']=$data['yellowgold'];
    $savedata['bogold']=$data['bogold'];
    $savedata['zuangold']=$data['zuangold'];
    $savedata['yearvip']=$data['yearvip'];
    $savedata['yellowgoldnum']=$data['yellowgoldnum'];
    $savedata['bogoldnum']=$data['bogoldnum'];
    $savedata['zuangoldnum']=$data['zuangoldnum'];
    $id = $_GPC["id"];
    $result = pdo_update("longbing_card_teamsetting", $savedata, array( "id" => $id ));
    if ($data['yellowgoldnum']<=0||$data['bogoldnum']<=0||$data['zuangoldnum']<=0){
        message("会员升级所需数不必须大于0", $this->createWebUrl("manage/teamsetting"), "error");
    }
    if( $result === 0 )
    {
        message("未做任何修改", $this->createWebUrl("manage/teamsetting"), "success");
    }
    if( $result )
    {
        message("编辑成功", $this->createWebUrl("manage/teamsetting"), "success");
    }

	message("编辑失败", "", "error");
}
$where = array( "uniacid" => $_W["uniacid"] );
$info = pdo_get("longbing_card_teamsetting", $where);
if( !$info || empty($info) ) 
{
	pdo_insert("longbing_card_teamsetting", array( "uniacid" => $_W["uniacid"]));
	$info = pdo_get("longbing_card_teamsetting", $where);
}
$id = $info["id"];

load()->func("tpl");
include($this->template("manage/teamsetting"));
?>