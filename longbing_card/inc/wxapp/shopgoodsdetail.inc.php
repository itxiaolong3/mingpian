<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$to_uid = $_GPC["to_uid"];
$goods_id = $_GPC["goods_id"];
if( !$to_uid ) 
{
	return $this->result(-1, "", array( ));
}
$goods = pdo_get("longbing_card_goods", array( "id" => $goods_id ), array( "id", "name", "cover", "images", "price", "view_count", "sale_count", "desc", "content", "collage_count", "is_collage", "freight", "recommend", "stock", "image_url", "unit", "is_self" ));
if( empty($goods) ) 
{
	return $this->result(-1, "", array( ));
}
$goods["cover_true"] = tomedia($goods["cover"]);
$goods["images_true"] = array( );
$images = $goods["images"];
$images = explode(",", $images);
foreach( $images as $k => $v ) 
{
	array_push($goods["images_true"], tomedia($v));
}
$goods["content"] = $this->toWXml($goods["content"]);
$spe = pdo_getall("longbing_card_shop_spe", array( "goods_id" => $goods_id, "status" => 1, "uniacid" => $uniacid ), array( "id", "title", "pid" ));
$speList = array( );
foreach( $spe as $k => $v ) 
{
	if( $v["pid"] == 0 ) 
	{
		$v["sec"] = array( );
		array_push($speList, $v);
	}
}
foreach( $speList as $k => $v ) 
{
	foreach( $spe as $k2 => $v2 ) 
	{
		if( $v["id"] == $v2["pid"] ) 
		{
			array_push($speList[$k]["sec"], $v2);
		}
	}
}
$goods["spe_list"] = $speList;
$spe_price = pdo_getall("longbing_card_shop_spe_price", array( "goods_id" => $goods_id, "status" => 1, "uniacid" => $uniacid ), array( "id", "spe_id_1", "price", "stock" ));
$goods["spe_price"] = $spe_price;
$ids = "";
foreach( $spe_price as $k => $v ) 
{
	$ids .= "," . $v["id"];
}
$ids = trim($ids, ",");
if( 1 < count($spe_price) ) 
{
	$ids = "(" . $ids . ")";
	$sql = "SELECT a.id, a.spe_price_id, a.number as `number`, a.price, a.people, b.spe_id_1 FROM " . tablename("longbing_card_shop_collage") . " a LEFT JOIN " . tablename("longbing_card_shop_spe_price") . " b ON a.spe_price_id = b.id WHERE a.goods_id = " . $goods_id . " && a.status = 1 && a.uniacid = " . $uniacid . " && a.spe_price_id in " . $ids;
	$collage = pdo_fetchall($sql);
}
else 
{
	if( count($spe_price) == 1 ) 
	{
		$sql = "SELECT a.id, a.spe_price_id, a.number as `number`, a.price, a.people, b.spe_id_1 FROM " . tablename("longbing_card_shop_collage") . " a LEFT JOIN " . tablename("longbing_card_shop_spe_price") . " b ON a.spe_price_id = b.id WHERE a.goods_id = " . $goods_id . " && a.status = 1 && a.uniacid = " . $uniacid . " && a.spe_price_id = " . $ids;
		$collage = pdo_fetchall($sql);
	}
	else 
	{
		$collage = array( );
	}
}
foreach( $collage as $k => $v ) 
{
	$arr = explode("-", $v["spe_id_1"]);
	$str = implode(",", $arr);
	if( strpos($str, ",") ) 
	{
		$str = "(" . $str . ")";
		$sql = "SELECT * FROM " . tablename("longbing_card_shop_spe") . " WHERE id IN " . $str;
	}
	else 
	{
		$sql = "SELECT * FROM " . tablename("longbing_card_shop_spe") . " WHERE id = " . $str;
	}
	$speList = pdo_fetchall($sql);
	$titles = "";
	foreach( $speList as $k2 => $v2 ) 
	{
		$titles .= "-" . $v2["title"];
	}
	$titles = trim($titles, "-");
	$collage[$k]["titles"] = $titles;
}
foreach( $collage as $index => $item ) 
{
	foreach( $goods["spe_price"] as $key => $value ) 
	{
		if( $item["spe_id_1"] == $value["spe_id_1"] ) 
		{
			$collage[$index]["spe_price_stock"] = $value["stock"];
			$collage[$index]["spe_price_price"] = $value["price"];
		}
	}
}
$goods["collage"] = $collage;
$destination_folder = "/images" . "/longbing_card/" . $_W["uniacid"];
$image = $destination_folder . "/" . $_W["uniacid"] . "-goods-" . $goods_id . "-" . $to_uid . "qr.png";
$image2 = $destination_folder . "/" . $_W["uniacid"] . "-goods-" . $goods_id . ".png";
$url = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $image;
$urlCover = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $image2;
$goods["qr"] = $url;
$goods["cover2"] = $urlCover;
$goods["qr"] = str_replace("ttp://", "ttps://", $goods["qr"]);
if( !strstr($goods["qr"], "ttps://") ) 
{
	$goods["qr"] = "https://" . $goods["qr"];
}
$goods["cover2"] = str_replace("ttp://", "ttps://", $goods["cover2"]);
if( !strstr($goods["cover2"], "ttps://") ) 
{
	$goods["cover2"] = "https://" . $goods["cover2"];
}
echo json_encode(array( "errno" => 0, "message" => "", "data" => $goods ), JSON_UNESCAPED_UNICODE);
$res = $this->insertView($uid, $to_uid, 2, $uniacid, $goods_id);
if( function_exists("fastcgi_finish_request") ) 
{
	@fastcgi_finish_request();
}
$this->insertView($uid, $to_uid, 2, $uniacid, $goods_id);
if( file_exists(ATTACHMENT_ROOT . $image) ) 
{
	$size = @filesize(ATTACHMENT_ROOT . $image);
	if( $size < 51220 ) 
	{
		$path = "longbing_card/pages/shop/detail/detail?id=" . $goods_id . "&to_uid=" . $to_uid;
		$res = createQr2(ATTACHMENT_ROOT . $image, $path, $_W);
		if( is_array($res) && isset($res["errcode"]) ) 
		{
			$appid = $_W["account"]["key"];
			$appidMd5 = md5($appid);
			if( is_file(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt") ) 
			{
				@unlink(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt");
			}
		}
	}
}
else 
{
	$path = "longbing_card/pages/shop/detail/detail?id=" . $goods_id . "&to_uid=" . $to_uid;
	$res = createQr2(ATTACHMENT_ROOT . $image, $path, $_W);
	if( is_array($res) && isset($res["errcode"]) ) 
	{
		$appid = $_W["account"]["key"];
		$appidMd5 = md5($appid);
		if( is_file(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt") ) 
		{
			unlink(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt");
		}
	}
}
if( file_exists(ATTACHMENT_ROOT . $image2) ) 
{
	$size2 = @filesize(ATTACHMENT_ROOT . $image2);
	if( $size < 51220 ) 
	{
		$path = tomedia($goods["cover"]);
		$files = http_request($path);
		file_put_contents(ATTACHMENT_ROOT . $image2, $files);
	}
}
else 
{
	$path = tomedia($goods["cover"]);
	$files = http_request($path);
	file_put_contents(ATTACHMENT_ROOT . $image2, $files);
}
function http_request($url, $data = NULL) 
{
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	if( !empty($data) ) 
	{
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	}
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	$info = curl_exec($curl);
	curl_close($curl);
	return $info;
}
function createQr2($image, $path, $_W) 
{
	$access_token = getAccessToken2();
	if( !$access_token ) 
	{
		return false;
	}
	$url = "https://api.weixin.qq.com/wxa/getwxacode?access_token=" . $access_token;
	$postData = array( "path" => $path );
	$postData = json_encode($postData);
	$response = ihttp_post($url, $postData);
	$content = $response["content"];
	if( is_array($content) && isset($content["errcode"]) ) 
	{
		return $content;
	}
	if( 200 < strlen($content) ) 
	{
		$res = file_put_contents($image, $content);
		return true;
	}
	return false;
}
function getAccessToken2() 
{
	global $_GPC;
	global $_W;
	$appid = $_W["account"]["key"];
	$appsecret = $_W["account"]["secret"];
	$appidMd5 = md5($appid);
	if( !is_file(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt") && is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/") ) 
	{
		$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $appsecret;
		$data = ihttp_get($url);
		$data = json_decode($data["content"], true);
		if( !isset($data["access_token"]) ) 
		{
			return false;
		}
		$access_token = $data["access_token"];
		file_put_contents(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt", json_encode(array( "at" => $access_token, "time" => time() + 6200 )));
		return $access_token;
	}
	if( is_file(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt") ) 
	{
		$fileInfo = file_get_contents(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt");
		if( !$fileInfo ) 
		{
			$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $appsecret;
			$data = ihttp_get($url);
			$data = json_decode($data["content"], true);
			if( !isset($data["access_token"]) ) 
			{
				return false;
			}
			$access_token = $data["access_token"];
			file_put_contents(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt", json_encode(array( "at" => $access_token, "time" => time() + 6200 )));
			return $access_token;
		}
		$fileInfo = json_decode($fileInfo, true);
		if( $fileInfo["time"] < time() ) 
		{
			$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $appsecret;
			$data = ihttp_get($url);
			$data = json_decode($data["content"], true);
			if( !isset($data["access_token"]) ) 
			{
				return false;
			}
			$access_token = $data["access_token"];
			file_put_contents(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt", json_encode(array( "at" => $access_token, "time" => time() + 6200 )));
			return $access_token;
		}
		return $fileInfo["at"];
	}
	return false;
}
function curlPostTmp($url, $data) 
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_TIMEOUT, 1);
	$output = curl_exec($ch);
	curl_close($ch);
	return $output;
}
?>