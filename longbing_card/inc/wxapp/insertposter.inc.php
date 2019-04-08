<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$img = $_GPC["img"];
$title = ($_GPC["title"] ? $_GPC["title"] : "");
$uniacid = $_W["uniacid"];
$img = tomedia($img);
$img = $this->transImage($img);
$time = time();
$data = array( "title" => $title, "img" => $img, "user_id" => $uid, "top" => 0, "create_time" => $time, "update_time" => $time, "uniacid" => $uniacid );
$result = pdo_insert("longbing_card_user_poster", $data);
if( $result ) 
{
	return $this->result(0, "suc", array( "img" => $img ));
}
return $this->result(-1, "fail", array( "img" => $img ));
?>