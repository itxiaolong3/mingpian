<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
if( $_GPC["action"] == "send" ) 
{
	$item = pdo_get("longbing_card_shop_order", array( "id" => $_GPC["id"] ));
	if( !$item || empty($item) ) 
	{
		message("未找到该数据", "", "error");
	}
	$result = pdo_update("longbing_card_shop_order", array( "order_status" => 2, "courier_number" => $_GPC["courier_number"], "express_company" => $_GPC["express_company"], "express_phone" => $_GPC["express_phone"], "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		sendmsg($item);
		message("编辑成功", $this->createWebUrl("manage/orders"), "success");
	}
	message("编辑失败", "", "error");
}
if( $_GPC["action"] == "self" ) 
{
	$item = pdo_get("longbing_card_shop_order", array( "id" => $_GPC["id"] ));
	if( !$item || empty($item) ) 
	{
		message("未找到该数据", "", "error");
	}
	$result = pdo_update("longbing_card_shop_order", array( "order_status" => 3, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		changewater($_GPC["id"]);
		sendmsg($item);
		message("编辑成功", $this->createWebUrl("manage/orders"), "success");
	}
	message("编辑失败", "", "error");
}
$limit = array( 1, 15 );
$where = array( "uniacid" => $_W["uniacid"], "order_status !=" => 1 );
$curr = 1;
if( isset($_GPC["keyword"]) ) 
{
	$keyword = $_GPC["keyword"];
	$keyword2 = "%" . $_GPC["keyword"] . "%";
	$goods = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_goods") . "  where uniacid = " . $_W["uniacid"] . " && status > -1 && `name` LIKE '" . $keyword2 . "' ORDER BY recommend DESC, top DESC, id DESC");
	$item_arr = array( );
	if( $goods ) 
	{
		foreach( $goods as $index => $item ) 
		{
			array_push($item_arr, $item["id"]);
		}
		$items = pdo_getall("longbing_card_shop_order_item", array( "uniacid" => $_W["uniacid"], "goods_id in" => $item_arr ), array( ), "", array( "order_id" ));
		if( $items ) 
		{
			$where["id in"] = array( );
			foreach( $items as $index => $item ) 
			{
				array_push($where["id in"], $item["order_id"]);
			}
		}
		else 
		{
			$where["id in"] = NULL;
		}
	}
	else 
	{
		$where["id in"] = NULL;
	}
}
if( isset($_GPC["transaction_id"]) ) 
{
	$transaction_id = $_GPC["transaction_id"];
	$transaction_id2 = "%" . $_GPC["transaction_id"] . "%";
	$where["transaction_id like"] = $_GPC["transaction_id"];
}
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$type = 0;
$statusArr = array( "全部订单", "未支付", "待发货", "已发货", "已完成" );
if( isset($_GPC["type"]) && $_GPC["type"] ) 
{
	$type = $_GPC["type"];
	switch( $type ) 
	{
		case 1: $where["pay_status"] = 0;
		$where["order_status"] = 0;
		break;
		case 2: $where["pay_status"] = 1;
		$where["order_status"] = 0;
		break;
		case 3: $where["pay_status"] = 1;
		$where["order_status"] = 2;
		break;
		case 4: $where["pay_status"] = 1;
		$where["order_status >"] = 2;
		break;
	}
}
$list = pdo_getslice("longbing_card_shop_order", $where, $limit, $count, array( ), "", array( "id desc" ));
foreach( $list as $k => $v ) 
{
	$items = pdo_getall("longbing_card_shop_order_item", array( "order_id" => $v["id"] ));
	$list[$k]["items"] = $items;
	$names = "";
	$list[$k]["is_self"] = 0;
	foreach( $items as $k2 => $v2 ) 
	{
		$names .= "; " . $v2["name"] . ": " . $v2["content"] . "，数量：" . $v2["number"];
		$goods = pdo_get("longbing_card_goods", array( "id" => $v2["goods_id"] ));
		if( $goods && $goods["is_self"] ) 
		{
			$list[$k]["is_self"] = 1;
		}
	}
	$names = trim($names, "; ");
	$list[$k]["names"] = $names;
	$list[$k]["user_info"] = pdo_get("longbing_card_user", array( "id" => $v["user_id"] ));
	if( $v["to_uid"] ) 
	{
		$list[$k]["staff_info"] = pdo_get("longbing_card_user_info", array( "fans_id" => $v["to_uid"] ));
	}
	else 
	{
		$list[$k]["staff_info"] = array( );
	}
	$list[$k]["collage_check"] = 1;
	if( $v["type"] == 1 ) 
	{
		$list[$k]["collage_info"] = pdo_get("longbing_card_shop_collage_list", array( "id" => $v["collage_id"] ));
		if( $list[$k]["collage_info"] && $list[$k]["collage_info"]["left_number"] != 0 ) 
		{
			$list[$k]["collage_check"] = 0;
		}
	}
	$list[$k]["write_off_user"] = "";
	if( $v["write_off_id"] ) 
	{
		$list[$k]["write_off_user"] = pdo_get("longbing_card_user", array( "id" => $v["write_off_id"] ));
	}
}
$perPage = 15;
load()->func("tpl");
include($this->template("manage/orders"));
function getFormId($to_uid) 
{
	$beginTime = mktime(0, 0, 0, date("m"), date("d") - 6, date("Y"));
	pdo_delete("longbing_card_formId", array( "create_time <" => $beginTime ));
	$formId = pdo_get("longbing_card_formId", array( "user_id" => $to_uid ), array( ), "", "id asc");
	if( !$formId ) 
	{
		return false;
	}
	if( $formId["create_time"] < $beginTime ) 
	{
		pdo_delete("longbing_card_formId", array( "id" => $formId["id"] ));
		getFormId($to_uid);
	}
	else 
	{
		pdo_delete("longbing_card_formId", array( "id" => $formId["id"] ));
		return $formId["formId"];
	}
}
function getAccessToken() 
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
function sendMsg($item) 
{
	global $_GPC;
	global $_W;
	$uid = $item["user_id"];
	if( !$uid ) 
	{
		return false;
	}
	$appid = $_W["account"]["key"];
	$appsecret = $_W["account"]["secret"];
	$client = pdo_get("longbing_card_user", array( "id" => $uid ));
	if( !$client ) 
	{
		return false;
	}
	$openid = $client["openid"];
	$name = $client["nickName"];
	$date = date("Y-m-d H:i");
	$config = pdo_get("longbing_card_config", array( "uniacid" => $_W["uniacid"] ), array( "mini_template_id", "notice_switch", "notice_i", "min_tmppid" ));
	if( $config["notice_switch"] == 1 && false ) 
	{
	}
	else 
	{
		if( !$config["mini_template_id"] ) 
		{
			return false;
		}
		$form = getformid($uid);
		if( !$form ) 
		{
			return false;
		}
		$access_token = getaccesstoken();
		if( !$access_token ) 
		{
			return false;
		}
		$url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
		$page = "longbing_card/pages/uCenter/order/orderList/orderList?currentTab=3";
		if( $item["type"] === 1 ) 
		{
			$items = pdo_get("longbing_card_shop_order_item", array( "order_id" => $item["id"] ));
			$page = "longbing_card/pages/shop/releaseCollage/releaseCollage?id=" . $items["goods_id"] . "&status=toShare&to_uid=" . $item["to_uid"] . "&collage_id=";
		}
		$postData = array( "touser" => $openid, "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array( "keyword1" => array( "value" => $name ), "keyword2" => array( "value" => "您的订单已发货" ), "keyword3" => array( "value" => $date ) ) );
		$postData = json_encode($postData);
		load()->func("communication");
		$response = ihttp_post($url, $postData);
	}
	return true;
}
function changeWater($id) 
{
	$time = time();
	$list = pdo_getall("longbing_card_selling_water", array( "order_id" => $id, "waiting" => 1 ));
	pdo_update("longbing_card_selling_water", array( "waiting" => 2, "update_time" => $time ), array( "order_id" => $id, "waiting" => 1 ));
	foreach( $list as $index => $item ) 
	{
		$money = ($item["price"] * $item["extract"]) / 100;
		$money = sprintf("%.2f", $money);
		$profit = pdo_get("longbing_card_selling_profit", array( "user_id" => $item["user_id"] ));
		if( $profit ) 
		{
			if( $money <= $profit["waiting"] ) 
			{
				$waiting = $profit["waiting"] - $money;
				$total_profit = $profit["total_profit"] + $money;
				$profit_money = $profit["profit"] + $money;
				$waiting = sprintf("%.2f", $waiting);
				$total_profit = sprintf("%.2f", $total_profit);
				$profit_money = sprintf("%.2f", $profit_money);
			}
			else 
			{
				$waiting = 0;
				$money = $profit["waiting"];
				$total_profit = $profit["total_profit"] + $profit["waiting"];
				$profit_money = $profit["profit"] + $profit["waiting"];
				$total_profit = sprintf("%.2f", $total_profit);
				$profit_money = sprintf("%.2f", $profit_money);
			}
			pdo_update("longbing_card_selling_profit", array( "waiting" => $waiting, "total_profit" => $total_profit, "profit" => $profit_money ), array( "id" => $profit["id"] ));
			$user = pdo_get("longbing_card_user", array( "id" => $item["source_id"] ));
			if( $user ) 
			{
				$create_money = $user["create_money"] + $money;
				$create_money = sprintf("%.2f", $create_money);
				pdo_update("longbing_card_user", array( "create_money" => $create_money, "update_time" => $time ), array( "id" => $user["id"] ));
			}
		}
	}
	return true;
}
?>