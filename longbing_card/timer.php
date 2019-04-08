<?php  $url = "https://" . $_SERVER["HTTP_HOST"] . "/app/index.php?i=1&c=entry&do=sendmsg&m=longbing_card&min_uid=" . $to_uid . "&min_uniacid=" . $_W["uniacid"] . "&min_tmppid=" . $config["min_tmppid"];
$config = file("../../data/config.php");
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
}
$dbms = "mysql";
$host = $host;
$dbName = $database;
$user = $username;
$pass = $password;
$dsn = (string) $dbms . ":host=" . $host . ";dbname=" . $dbName;
$db = new PDO($dsn, $user, $pass, array( PDO::ATTR_PERSISTENT => true ));
$list = $db->query("SELECT * FROM " . $tablepre . "longbing_card_shop_order WHERE pay_status = 0 && order_status != 1");
$config2 = $db->query("SELECT uniacid, order_overtime, collage_overtime FROM " . $tablepre . "longbing_card_config");
foreach( $config2 as $k => $v ) 
{
	$configs[$v["uniacid"]] = $v;
}
$time = time();
foreach( $list as $k => $v ) 
{
	$order_overtime = $configs[$v["uniacid"]]["order_overtime"];
	if( !$order_overtime ) 
	{
		$order_overtime = 1800;
	}
	if( !$collage_overtime ) 
	{
		$collage_overtime = 172800;
	}
	if( $order_overtime < $time - $v["create_time"] ) 
	{
		$sql = "UPDATE " . $tablepre . "longbing_card_shop_order SET order_status = 1 WHERE id = " . $v["id"];
		$db->query($sql);
		if( $v["type"] == 1 ) 
		{
			$collage_id = $v["collage_id"];
			$collage = $db->query("SELECT * FROM " . $tablepre . "longbing_card_shop_collage_list WHERE id = " . $collage_id);
			foreach( $collage as $k2 => $v2 ) 
			{
				if( $v2["user_id"] == $v["user_id"] ) 
				{
					$sql = "UPDATE " . $tablepre . "longbing_card_shop_collage_list SET collage_status = 4 WHERE id = " . $v2["id"];
					$db->query($sql);
				}
				else 
				{
					$sql = "UPDATE " . $tablepre . "longbing_card_shop_collage_list SET left_number = " . $v2["left_number"] . " + 1 WHERE id = " . $v2["id"];
					$db->query($sql);
				}
			}
		}
	}
}
$list_collage = $db->query("SELECT * FROM " . $tablepre . "longbing_card_shop_collage_list WHERE collage_status = 1");
foreach( $list_collage as $k => $v ) 
{
	$collage_overtime = $configs[$v["uniacid"]]["collage_overtime"];
	if( !$collage_overtime ) 
	{
		$collage_overtime = 172800;
	}
	if( $collage_overtime < $time - $v["create_time"] ) 
	{
		$sql = "UPDATE " . $tablepre . "longbing_card_shop_collage_list SET collage_status = 4 WHERE id = " . $v["id"];
		$db->query($sql);
		$orders = $db->query("SELECT * FROM " . $tablepre . "longbing_card_shop_order WHERE `type` = 1 && collage_id = " . $v["id"]);
		foreach( $orders as $k2 => $v2 ) 
		{
			$sql = "UPDATE " . $tablepre . "longbing_card_shop_order SET order_status = 1 WHERE id = " . $v2["id"];
			$db->query($sql);
		}
	}
}
function pp($data) 
{
	echo "<pre>";
	var_dump($data);
	echo "</pre>";
	exit();
}
?>