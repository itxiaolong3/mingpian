<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$ids = $_GPC["ids"];
$uniacid = $_W["uniacid"];
$ids = trim($ids, ",");
$ids = explode(",", $ids);
if( !$ids || !$uid || empty($ids) ) 
{
	return $this->result(-1, "require", array( ));
}
$time = time();
$sql = "";
foreach( $ids as $index => $item ) 
{
	$sql .= "INSERT INTO " . tablename("longbing_card_user_shop") . " (`user_id`, `goods_id`, `uniacid`, `status`, `create_time`, `update_time`) \r\n        VALUES (" . $uid . ", " . $item . ", " . $uniacid . ", 1, " . $time . ", " . $time . ");";
}
$result = pdo_query($sql);
if( $result ) 
{
	return $this->result(0, "suc", array( ));
}
return $this->result(-1, "fail", array( ));
?>