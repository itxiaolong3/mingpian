<?php  if( !is_dir($_SERVER["DOCUMENT_ROOT"] . "/data/tpl") ) 
{
	@mkdir($_SERVER["DOCUMENT_ROOT"] . "/data/tpl");
}
if( !is_dir($_SERVER["DOCUMENT_ROOT"] . "/data/tpl/web") ) 
{
	@mkdir($_SERVER["DOCUMENT_ROOT"] . "/data/tpl/web");
}
$is_check = true;
$default_card = 0;
$default_goods = 0;
$default_timeline = 0;
$default_message = 0;
$default_custom_qr = 0;
$default_copyright = 1;
$default_mini = 0;
$default_form = 0;
$default_plug_auth = 0;
$version = 0;
$agent = 1;
$version = intval($version);
if( !is_numeric($version) ) 
{
	$version = 0;
}
if( $version == 0 ) 
{
	$default_card = 5;
	$default_goods = 5;
	$default_timeline = 5;
	$default_message = 5;
	$default_custom_qr = 5;
	$default_copyright = 1;
	$default_mini = 2;
	$default_form = 0;
	$default_plug_auth = 0;
}
else 
{
	$default_mini = $version;
}
if( $agent == 1 ) 
{
	$default_card = 0;
	$default_goods = 0;
	$default_timeline = 0;
	$default_message = 0;
	$default_custom_qr = 0;
	$default_copyright = 1;
	$default_mini = 0;
	$default_form = 0;
	$default_plug_auth = 0;
}
if( !$is_check ) 
{
	define("LONGBING_AUTH_CARD", $default_card);
	define("LONGBING_AUTH_GOODS", $default_goods);
	define("LONGBING_AUTH_TIMELINE", $default_timeline);
	define("LONGBING_AUTH_MESSAGE", $default_message);
	define("LONGBING_AUTH_CUSTOM_QR", $default_custom_qr);
	define("LONGBING_AUTH_COPYRIGHT", $default_copyright);
	define("LONGBING_AUTH_MINI", $default_mini);
	define("LONGBING_AUTH_FORM", $default_form);
	define("LONGBING_AUTH_PLUG_AUTH", $default_plug_auth);
	define("LONGBING_AUTH_WAY", 1);
}
else 
{
	$chars_array = array( "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z" );
	$str = "";
	for( $i = 0; $i < 4; $i++ ) 
	{
		$tmp = rand(0, 25);
		$str .= $chars_array[$tmp];
	}
	$domain = $_SERVER["HTTP_HOST"];
	$domainMd5 = md5($_SERVER["HTTP_HOST"]);
	$url = "https://auth.xiaochengxucms.com/index.php/longbing_auth/api/domain";
	$dataCheck = array( "domain" => $domain, "key" => $str );
	if( is_file($_SERVER["DOCUMENT_ROOT"] . "/data/tpl/web/" . $domainMd5 . "tpl.txt") ) 
	{
		$file = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/data/tpl/web/" . $domainMd5 . "tpl.txt");
		$arr = explode("--", $file);
		$res = json_decode($arr[0], true);
		if( $res["code"] == 200 ) 
		{
			$e = encrypt($res["data"], "D", $arr[1]);
			$e = json_decode($e, true);
		}
		else 
		{
			$e = $res["data"];
		}
		foreach( $e as $k => $v ) 
		{
			foreach( $v as $k2 => $v2 ) 
			{
				if( $k2 == "date" ) 
				{
					if( 86400 < time() - $v2 ) 
					{
						$res = curlpost($url, $dataCheck);
						file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/data/tpl/web/" . $domainMd5 . "tpl.txt", $res . "--" . $str);
						$res = json_decode($res, true);
					}
					else 
					{
						$str = $arr[1];
					}
				}
			}
		}
	}
	else 
	{
		$res = curlpost($url, $dataCheck);
		file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/data/tpl/web/" . $domainMd5 . "tpl.txt", $res . "--" . $str);
		$res = json_decode($res, true);
	}
	if( !is_array($res) ) 
	{
		define("LONGBING_AUTH_CARD", $default_card);
		define("LONGBING_AUTH_GOODS", $default_goods);
		define("LONGBING_AUTH_TIMELINE", $default_timeline);
		define("LONGBING_AUTH_MESSAGE", $default_message);
		define("LONGBING_AUTH_CUSTOM_QR", $default_custom_qr);
		define("LONGBING_AUTH_COPYRIGHT", $default_copyright);
		define("LONGBING_AUTH_MINI", $default_mini);
		define("LONGBING_AUTH_FORM", $default_form);
		define("LONGBING_AUTH_PLUG_AUTH", $default_plug_auth);
		define("LONGBING_AUTH_WAY", 2);
	}
	else 
	{
		if( isset($res["code"]) && $res["code"] == 200 ) 
		{
			$res = encrypt($res["data"], "D", $str);
			$res = json_decode($res, true);
			foreach( $res as $k => $v ) 
			{
				foreach( $v as $k2 => $v2 ) 
				{
					if( $k2 == "date" ) 
					{
						continue;
					}
					if( defined($k2) ) 
					{
					}
					else 
					{
						define($k2, $v2);
					}
				}
			}
		}
		else 
		{
			define("LONGBING_AUTH_CARD", $default_card);
			define("LONGBING_AUTH_GOODS", $default_goods);
			define("LONGBING_AUTH_TIMELINE", $default_timeline);
			define("LONGBING_AUTH_MESSAGE", $default_message);
			define("LONGBING_AUTH_CUSTOM_QR", $default_custom_qr);
			define("LONGBING_AUTH_COPYRIGHT", $default_copyright);
			define("LONGBING_AUTH_MINI", $default_mini);
			define("LONGBING_AUTH_FORM", $default_form);
			define("LONGBING_AUTH_PLUG_AUTH", $default_plug_auth);
			define("LONGBING_AUTH_WAY", 3);
		}
	}
}
if( !defined("LONGBING_AUTH_WAY") && defined("LONGBING_AUTH_MINI") ) 
{
	define("LONGBING_AUTH_WAY", 4);
}
if( !defined("LONGBING_AUTH_WAY") && !defined("LONGBING_AUTH_MINI") ) 
{
	define("LONGBING_AUTH_WAY", 5);
}
if( !defined("LONGBING_AUTH_CARD") ) 
{
	define("LONGBING_AUTH_CARD", $default_card);
}
if( !defined("LONGBING_AUTH_GOODS") ) 
{
	define("LONGBING_AUTH_GOODS", $default_goods);
}
if( !defined("LONGBING_AUTH_TIMELINE") ) 
{
	define("LONGBING_AUTH_TIMELINE", $default_timeline);
}
if( !defined("LONGBING_AUTH_MESSAGE") ) 
{
	define("LONGBING_AUTH_MESSAGE", $default_message);
}
if( !defined("LONGBING_AUTH_CUSTOM_QR") ) 
{
	define("LONGBING_AUTH_CUSTOM_QR", $default_custom_qr);
}
if( !defined("LONGBING_AUTH_COPYRIGHT") ) 
{
	define("LONGBING_AUTH_COPYRIGHT", $default_copyright);
}
if( !defined("LONGBING_AUTH_MINI") ) 
{
	define("LONGBING_AUTH_MINI", $default_mini);
}
if( !defined("LONGBING_AUTH_FORM") ) 
{
	define("LONGBING_AUTH_FORM", $default_form);
}
if( !defined("LONGBING_AUTH_PLUG_AUTH") ) 
{
	define("LONGBING_AUTH_PLUG_AUTH", $default_plug_auth);
}
function curlPost($url, $data) 
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	$output = curl_exec($ch);
	curl_close($ch);
	return $output;
}
function pp($data) 
{
	$data = json_decode(json_encode($data), true);
	echo "<pre>";
	print_r($data);
	echo "</pre>";
	exit();
}
function pp2($data) 
{
	$data = json_decode(json_encode($data), true);
	echo "<pre>";
	print_r($data);
	echo "</pre>";
}
function encrypt($string, $operation, $key = "") 
{
	$key = md5($key);
	$key_length = strlen($key);
	$string = ($operation == "D" ? base64_decode($string) : substr(md5($string . $key), 0, 8) . $string);
	$string_length = strlen($string);
	$rndkey = $box = array( );
	$result = "";
	for( $i = 0; $i <= 255; $i++ ) 
	{
		$rndkey[$i] = ord($key[$i % $key_length]);
		$box[$i] = $i;
	}
	for( $j = $i = 0; $i < 256; $i++ ) 
	{
		$j = ($j + $box[$i] + $rndkey[$i]) % 256;
		$tmp = $box[$i];
		$box[$i] = $box[$j];
		$box[$j] = $tmp;
	}
	for( $a = $j = $i = 0; $i < $string_length; $i++ ) 
	{
		$a = ($a + 1) % 256;
		$j = ($j + $box[$a]) % 256;
		$tmp = $box[$a];
		$box[$a] = $box[$j];
		$box[$j] = $tmp;
		$result .= chr(ord($string[$i]) ^ $box[($box[$a] + $box[$j]) % 256]);
	}
	if( $operation == "D" ) 
	{
		if( substr($result, 0, 8) == substr(md5(substr($result, 8) . $key), 0, 8) ) 
		{
			return substr($result, 8);
		}
		return "";
	}
	return str_replace("=", "", base64_encode($result));
}
?>