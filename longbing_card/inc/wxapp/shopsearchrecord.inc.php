<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$record = pdo_getall("longbing_card_shop_search", array( "uniacid" => $uniacid, "user_id" => $uid ), array( "keyword" ), "", array( "id desc" ), array( 1, 10 ));
$hot = pdo_getall("longbing_card_shop_search", array( "uniacid" => $uniacid ), array( "keyword" ), "", array( "number desc", "update_time desc" ), array( 1, 10 ));
$data = array( "record" => $record, "hot" => $hot );
return $this->result(0, "", $data);
?>