<?php  require_once(__DIR__ . "/Autoloader.php");
require_once(__DIR__ . "/mysql/src/Connection.php");
define("HEARTBEAT_TIME", 55);
define("IN_IA", "tmp");
include_once("../../../data/config.php");
$workman_server = "0.0.0.0";
$workman_port = 2345;
if( !empty($config) && isset($config["setting"]) && isset($config["setting"]["workerman"]) && isset($config["setting"]["workerman"]["server"]) && $config["setting"]["workerman"]["server"] ) 
{
	$workman_server = $config["setting"]["workerman"]["server"];
}
if( !empty($config) && isset($config["setting"]) && isset($config["setting"]["workerman"]) && isset($config["setting"]["workerman"]["port"]) && $config["setting"]["workerman"]["port"] ) 
{
	$workman_port = $config["setting"]["workerman"]["port"];
}
$worker = new Workerman\Worker("websocket://0.0.0.0:2345");
$worker->count = 1;
$worker->uidConnections = array( );
$worker->onWorkerStart = function($worker) 
{
	global $db;
	$config = file("../../../data/config.php");
	$host = "";
	$username = "";
	$password = "";
	$port = "";
	$database = "";
	$tablepre = "";
	if( !empty($config) ) 
	{
		foreach( $config as $k => $v ) 
		{
			if( strpos($v, "db") && strpos($v, "master") && strpos($v, "host") ) 
			{
				$arr = explode("'", $v);
				$host = $arr[count($arr) - 2];
			}
			if( strpos($v, "db") && strpos($v, "master") && strpos($v, "username") ) 
			{
				$arr = explode("'", $v);
				$username = $arr[count($arr) - 2];
			}
			if( strpos($v, "db") && strpos($v, "master") && strpos($v, "password") ) 
			{
				$arr = explode("'", $v);
				$password = $arr[count($arr) - 2];
			}
			if( strpos($v, "db") && strpos($v, "master") && strpos($v, "port") ) 
			{
				$arr = explode("'", $v);
				$port = $arr[count($arr) - 2];
			}
			if( strpos($v, "db") && strpos($v, "master") && strpos($v, "database") ) 
			{
				$arr = explode("'", $v);
				$database = $arr[count($arr) - 2];
			}
			if( strpos($v, "db") && strpos($v, "master") && strpos($v, "tablepre") ) 
			{
				$arr = explode("'", $v);
				$tablepre = $arr[count($arr) - 2];
			}
		}
		if( $host == "" ) 
		{
			foreach( $config as $k => $v ) 
			{
				if( strpos($v, "db") && strpos($v, "host") ) 
				{
					$arr = explode("'", $v);
					$host = $arr[count($arr) - 2];
				}
				if( strpos($v, "db") && strpos($v, "username") ) 
				{
					$arr = explode("'", $v);
					$username = $arr[count($arr) - 2];
				}
				if( strpos($v, "db") && strpos($v, "password") ) 
				{
					$arr = explode("'", $v);
					$password = $arr[count($arr) - 2];
				}
				if( strpos($v, "db") && strpos($v, "port") ) 
				{
					$arr = explode("'", $v);
					$port = $arr[count($arr) - 2];
				}
				if( strpos($v, "db") && strpos($v, "database") ) 
				{
					$arr = explode("'", $v);
					$database = $arr[count($arr) - 2];
				}
				if( strpos($v, "db") && strpos($v, "tablepre") ) 
				{
					$arr = explode("'", $v);
					$tablepre = $arr[count($arr) - 2];
				}
			}
		}
	}
	$db = new Workerman\MySQL\Connection($host, $port, $username, $password, $database, "utf8mb4");
	Workerman\Lib\Timer::add(1, function() use ($worker) 
	{
		$time_now = time();
		foreach( $worker->connections as $connection ) 
		{
			if( empty($connection->lastMessageTime) ) 
			{
				$connection->lastMessageTime = $time_now;
				continue;
			}
			if( HEARTBEAT_TIME < $time_now - $connection->lastMessageTime ) 
			{
				$connection->close();
			}
		}
	}
	);
}
;
$worker->onConnect = function($connection) 
{
	$connection->onWebSocketConnect = function($connection) 
	{
		$connection->realIP = $_SERVER["HTTP_X_REAL_IP"];
	}
	;
}
;
$worker->onMessage = function($connection, $data) 
{
	global $worker;
	global $db;
	$connection->lastMessageTime = time();
	$config = file("../../../data/config.php");
	$tablepre = "";
	if( !empty($config) ) 
	{
		foreach( $config as $k => $v ) 
		{
			if( strpos($v, "db") && strpos($v, "master") && strpos($v, "tablepre") ) 
			{
				$arr = explode("'", $v);
				$tablepre = $arr[count($arr) - 2];
			}
		}
		if( $tablepre == "" ) 
		{
			foreach( $config as $k => $v ) 
			{
				if( strpos($v, "db") && strpos($v, "tablepre") ) 
				{
					$arr = explode("'", $v);
					$tablepre = $arr[count($arr) - 2];
				}
			}
		}
	}
	$data2 = $data;
	$data = json_decode($data, true);
	if( isset($data["ping"]) ) 
	{
		$connection->send("pong");
		$list = $db->query("SELECT * FROM `" . $tablepre . "longbing_card_message` WHERE target_id=" . $data["user_id"] . " && user_id = " . $data["target_id"] . " && status = 1");
		if( is_array($list) && !empty($list) ) 
		{
			foreach( $list as $k => $v ) 
			{
				$msg = array( "errno" => 0, "message" => "接收成功_1", "data" => $v["content"], "type" => $v["message_type"], "data2" => $v );
				$msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
				$connection->send($msg);
				$db->query("UPDATE `" . $tablepre . "longbing_card_message` SET status = 2 WHERE id = " . $v["id"]);
			}
		}
		return false;
	}
	else 
	{
		$data2 = array( );
		if( isset($data["goods_id"]) ) 
		{
			$goods_info = $db->query("SELECT * FROM `" . $tablepre . "longbing_card_goods` WHERE id=" . $data["goods_id"]);
			$data["content"] = "您好！我想咨询下商品：" . $goods_info[0]["name"] . "的相关信息。";
			$data["type"] = "text";
		}
		if( !isset($data["user_id"]) || !isset($data["target_id"]) || !isset($data["content"]) || !isset($data["uniacid"]) ) 
		{
			$msg = array( "errno" => -1, "message" => $data2, "data" => array( ) );
			$msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
			$connection->send($msg);
			return false;
		}
		$user_id = $data["user_id"];
		$target_id = $data["target_id"];
		$content = $data["content"];
		$uniacid = $data["uniacid"];
		$type = $data["type"];
		$check1 = $db->query("SELECT * FROM `" . $tablepre . "longbing_card_chat` WHERE user_id=" . $user_id . " && target_id = " . $target_id);
		if( empty($check1) ) 
		{
			$check2 = $db->query("SELECT * FROM `" . $tablepre . "longbing_card_chat` WHERE user_id=" . $target_id . " && target_id = " . $user_id);
			if( empty($check2) ) 
			{
				$insert_id = $db->insert($tablepre . "longbing_card_chat")->cols(array( "user_id" => $user_id, "target_id" => $target_id, "uniacid" => $uniacid, "create_time" => time(), "update_time" => time() ))->query();
				$chat_id = $insert_id;
			}
			else 
			{
				$chat_id = $check2[0]["id"];
			}
		}
		else 
		{
			$chat_id = $check1[0]["id"];
		}
		if( !$chat_id ) 
		{
			$msg = array( "errno" => -1, "message" => "系统错误", "data" => array( ) );
			$msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
			$connection->send($msg);
			return false;
		}
		$install_data = array( "chat_id" => $chat_id, "user_id" => $user_id, "target_id" => $target_id, "content" => $content, "uniacid" => $uniacid, "message_type" => $type, "create_time" => time(), "update_time" => time() );
		$insert_id = $db->insert($tablepre . "longbing_card_message")->cols($install_data)->query();
		if( !$insert_id ) 
		{
			$msg = array( "errno" => -1, "message" => "系统错误!", "data" => array( ) );
			$msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
			$connection->send($msg);
			return false;
		}
		$install_data["id"] = $insert_id;
		if( !isset($connection->uid) ) 
		{
			$connection->uid = $user_id;
			$worker->uidConnections[$connection->uid] = $connection;
		}
		sendMessageByUid($data["target_id"], $content, $connection, $uniacid, $insert_id, $tablepre, $install_data);
		return false;
	}
}
;
$worker->onClose = function($connection) 
{
	global $worker;
	if( isset($connection->uid) ) 
	{
		unset($worker->uidConnections[$connection->uid]);
	}
}
;
Workerman\Worker::runAll();
function broadcast($message) 
{
	global $worker;
	foreach( $worker->uidConnections as $connection ) 
	{
		$connection->send($message);
	}
}
function sendMessageByUid($uid, $message, $con, $uniacid = 0, $insert_id = 0, $tablepre = "", $data2 = array( )) 
{
	global $worker;
	global $db;
	if( isset($worker->uidConnections[$uid]) ) 
	{
		$connection = $worker->uidConnections[$uid];
		$msg = array( "errno" => 0, "message" => "接收成功_2", "data" => $message, "type" => $data2["message_type"], "data2" => $data2 );
		$msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
		$connection->send($msg);
		$msg = array( "errno" => 0, "message" => "发送成功_2", "data" => array( ) );
		$msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
		$con->send($msg);
		if( $insert_id ) 
		{
			$db->query("UPDATE `" . $tablepre . "longbing_card_message` SET status = 2 WHERE id = " . $insert_id);
		}
		return false;
	}
	$msg = array( "errno" => 0, "message" => "发送成功_1", "data" => array( ) );
	$msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
	$con->send($msg);
}
function mark($uid, $target_id, $uniacid) 
{
	global $worker;
	global $db;
	$check_user = $db->query("SELECT * FROM `ims_longbing_card_user` WHERE id=" . $uid);
	$check_user_tar = $db->query("SELECT * FROM `ims_longbing_card_user` WHERE id=" . $target_id);
	if( empty($check_user) || empty($check_user_tar) ) 
	{
		return false;
	}
	if( $check_user["is_staff"] ) 
	{
		$staff_id = $check_user["id"];
		$user_id = $check_user_tar["id"];
	}
	else 
	{
		$staff_id = $check_user_tar["id"];
		$user_id = $check_user["id"];
	}
	$check = $db->select("*")->from("ims_longbing_card_user_mark")->where("user_id= :user_id AND staff_id= :staff_id")->bindValues(array( "user_id" => $user_id, "staff_id" => $staff_id ))->row();
	$check = $db->query("SELECT * FROM `ims_longbing_card_user_mark` WHERE user_id=" . $user_id . " && staff_id = " . $staff_id);
	if( empty($check) ) 
	{
		$insert_id = $db->insert("ims_longbing_card_user_mark")->cols(array( "user_id" => $user_id, "staff_id" => $staff_id, "uniacid" => $uniacid, "mark" => 1, "create_time" => time(), "update_time" => time() ))->query();
	}
	return true;
}
?>