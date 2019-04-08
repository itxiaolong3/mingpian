<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$uniacid = $_W["uniacid"];
$my_tags = pdo_getall("longbing_card_tags", array( "user_id" => $uid, "uniacid" => $uniacid ));
$sys_tags = pdo_getall("longbing_card_tags", array( "user_id" => 0, "uniacid" => $uniacid ));
$data["my_tags"] = $my_tags;
$data["sys_tags"] = $sys_tags;
return $this->result(0, "suc", $data);
?>