<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$to_uid = $_GPC["to_uid"];
if( !$uid || !$to_uid && $to_uid != 0 ) 
{
	return $this->result(-1, "", array( ));
}
if( $this->redis_sup_v3 ) 
{
	$redis_key = "longbing_card_modular_" . $to_uid . "_" . $uniacid;
	$data = $this->redis_server->get($redis_key);
	if( $data ) 
	{
		$data = json_decode($data, true);
		$data["from_redis"] = 1;
		$this->insertView($uid, $to_uid, 6, $uniacid);
		return $this->result(0, "", $data);
	}
}
$where = array( "uniacid" => $uniacid, "status" => 1 );
$modular = pdo_getall("longbing_card_modular", $where, array( ), "", "top desc");
foreach( $modular as $k => $v ) 
{
	$modular[$k]["cover"] = tomedia($v["cover"]);
	$where["modular_id"] = $v["id"];
	if( $v["type"] == 2 || $v["type"] == 4 ) 
	{
		$info = pdo_get($v["table_name"], $where);
		$info["content"] = $this->toWXml($info["content"]);
		$info["introduction"] = $this->toWXml($info["introduction"]);
		$modular[$k]["info"] = $info;
	}
	else 
	{
		if( $v["type"] == 7 ) 
		{
			$list = pdo_getall($v["table_name"], $where, array( ), "", array( "top desc" ), array( 1, 3 ));
			foreach( $list as $k2 => $v2 ) 
			{
				$list[$k2]["cover"] = tomedia($v2["cover"]);
				$list[$k2]["video"] = tomedia($v2["video"]);
				$list[$k2]["create_time2"] = date("Y-m-d", $v2["create_time"]);
			}
			$modular[$k]["list"] = $list;
		}
		else 
		{
			if( $v["type"] == 1 || $v["type"] == 3 || $v["type"] == 5 ) 
			{
				$list = pdo_getall($v["table_name"], $where, array( ), "", array( "top desc" ), array( 1, 3 ));
				foreach( $list as $k2 => $v2 ) 
				{
					$list[$k2]["cover"] = tomedia($v2["cover"]);
					$list[$k2]["create_time2"] = date("Y-m-d", $v2["create_time"]);
				}
				$modular[$k]["list"] = $list;
			}
			else 
			{
				$list = pdo_getall($v["table_name"], $where, array( ), "", array( "top desc" ), array( 1, 3 ));
				foreach( $list as $k2 => $v2 ) 
				{
					$list[$k2]["cover"] = tomedia($v2["cover"]);
				}
				$modular[$k]["list"] = $list;
			}
		}
	}
}
$this->insertView($uid, $to_uid, 6, $uniacid);
$data = $modular;
if( $this->redis_sup_v3 ) 
{
	$redis_key = "longbing_card_modular_" . $to_uid . "_" . $uniacid;
	$this->redis_server->set($redis_key, json_encode($data));
	$this->redis_server->EXPIRE($redis_key, 30 * 60);
}
return $this->result(0, "", $data);
?>