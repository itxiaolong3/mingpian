<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$ids = $_GPC["lable_id"];
$content = $_GPC["content"];
$uniacid = $_W["uniacid"];
$beginTime = mktime(0, 0, 0, date("m"), date("d") - 6, date("Y"));
pdo_delete("longbing_card_formId", array( "create_time <" => $beginTime ));
if( !$uid || !$ids || !$content ) 
{
	return $this->result(-1, "require", array( ));
}
$ids = trim($ids, ",");
$ids_arr = explode(",", $ids);
if( count($ids_arr) == 0 ) 
{
	return $this->result(0, "", array( ));
}
if( count($ids_arr) == 1 ) 
{
	$list = pdo_fetchall("SELECT a.*,b.id as bid,b.formId,c.openid FROM " . tablename("longbing_card_user_label") . " a LEFT JOIN " . tablename("longbing_card_formId") . " b ON a.user_id = b.user_id LEFT JOIN " . tablename("longbing_card_user") . " c ON a.user_id=c.id WHERE a.uniacid = " . $uniacid . " && a.staff_id = " . $uid . " && \r\n        a.lable_id = " . $ids . " GROUP BY b.user_id");
}
else 
{
	$ids = "(" . $ids . ")";
	$list = pdo_fetchall("SELECT a.*,b.id as bid,b.formId,c.openid FROM " . tablename("longbing_card_user_label") . " a LEFT JOIN " . tablename("longbing_card_formId") . " b ON a.user_id = b.user_id LEFT JOIN " . tablename("longbing_card_user") . " c ON a.user_id=c.id WHERE a.uniacid = " . $uniacid . " && a.staff_id = " . $uid . " && \r\n        a.lable_id in " . $ids . " GROUP BY b.user_id");
}
$time = time();
pdo_insert("longbing_card_group_sending", array( "content" => $content, "type" => 1, "staff_id" => $uid, "remark" => $_GPC["lable_id"], "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time ));
if( !$list ) 
{
	return $this->result(0, "", array( ));
}
$config = pdo_get("longbing_card_config", array( "uniacid" => $uniacid ));
$date = date("Y-m-d H:i");
$dataSend = array( );
$access_token = $this->getAccessToken();
if( !$access_token ) 
{
	return $this->result(-1, "ac", array( ));
}
foreach( $list as $index => $item ) 
{
	$form = $item["formId"];
	if( !$form ) 
	{
		continue;
	}
	if( !$config["mini_template_id"] ) 
	{
		continue;
	}
	@pdo_delete("longbing_card_formId", array( "id" => $item["bid"] ));
	$postData = array( "touser" => $item["openid"], "template_id" => $config["mini_template_id"], "page" => "longbing_card/pages/index/index", "form_id" => $form, "data" => array( "keyword1" => array( "value" => "通知" ), "keyword2" => array( "value" => $content ), "keyword3" => array( "value" => $date ) ) );
	$postData["page"] = "longbing_card/pages/index/index";
	$postData = json_encode($postData);
	if( !$config["mini_template_id"] ) 
	{
		continue;
	}
	$url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
	$tmp = array( "url" => $url, "data" => $postData );
	array_push($dataSend, $tmp);
}
if( !empty($dataSend) ) 
{
	curl_m($dataSend);
	return $this->result(0, "", array( ));
}
return $this->result(-1, "fail", array( ));
function curl_m($array) 
{
	$mh = curl_multi_init();
	$curls = array( );
	foreach( $array as $index => $item ) 
	{
		$tmp = curl_init();
		curl_setopt($tmp, CURLOPT_URL, $item["url"]);
		curl_setopt($tmp, CURLOPT_HEADER, 0);
		curl_setopt($tmp, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($tmp, CURLOPT_POST, 1);
		curl_setopt($tmp, CURLOPT_POSTFIELDS, $item["data"]);
		curl_setopt($tmp, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($tmp, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($tmp, CURLOPT_HEADER, 0);
		curl_setopt($tmp, CURLOPT_TIMEOUT, 100);
		array_push($curls, $tmp);
		curl_multi_add_handle($mh, $tmp);
	}
	$running = NULL;
	do 
	{
		$mrc = curl_multi_exec($mh, $running);
	}
	while( $mrc == CURLM_CALL_MULTI_PERFORM );
	while( $running && $mrc == CURLM_OK ) 
	{
		if( curl_multi_select($mh) != -1 ) 
		{
			do 
			{
				$mrc = curl_multi_exec($mh, $running);
			}
			while( $mrc == CURLM_CALL_MULTI_PERFORM );
		}
	}
	foreach( $curls as $index => $item ) 
	{
		curl_multi_remove_handle($mh, $item);
	}
	curl_multi_close($mh);
	return true;
}
?>