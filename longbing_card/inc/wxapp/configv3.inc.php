<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
if( $this->redis_sup_v3 ) 
{
	$redis_key = "longbing_card_configv3_" . $_W["uniacid"];
	$data = $this->redis_server_v3->get($redis_key);
	if( $data ) 
	{
		$data = json_decode($data, true);
		$data["config"]["from_redis"] = 1;
		return $this->result(0, "suc", $data);
	}
}
$info = pdo_get("longbing_card_config", array( "uniacid" => $_W["uniacid"] ));
if( !$info ) 
{
	$dataConfig = array( "uniacid" => $_W["uniacid"], "status" => 1, "create_time" => $time, "update_time" => $time );
	@pdo_insert("longbing_card_config", $dataConfig);
	$info = pdo_get("longbing_card_config", array( "uniacid" => $_W["uniacid"] ));
}
if( !$info ) 
{
	return $this->result(-1, "need config", $data);
}
$info["default_video"] = tomedia($info["default_video"]);
$info["default_voice"] = tomedia($info["default_voice"]);
if( isset($info["order_pwd"]) ) 
{
	unset($info["order_pwd"]);
}
$info["copyright"] = tomedia($info["copyright"]);
if( !LONGBING_AUTH_COPYRIGHT ) 
{
	$info["copyright"] = "https://retail.xiaochengxucms.com/images/12/2018/11/crDXyl3TyBRLUBch6ToqXL6e9D96hY.jpg";
}
$info["form"] = 0;
if( defined("LONGBING_AUTH_FORM") && LONGBING_AUTH_FORM ) 
{
	$info["form"] = 1;
}
$checkExists = pdo_tableexists("longbing_cardauth2_config");
if( $checkExists ) 
{
	$auth_info = pdo_get("longbing_cardauth2_config", array( "modular_id" => $_W["uniacid"] ));
	if( $auth_info && $auth_info["copyright_id"] ) 
	{
		$copyright = pdo_get("longbing_cardauth2_copyright", array( "id" => $auth_info["copyright_id"] ));
		if( $copyright ) 
		{
			$info["copyright"] = tomedia($copyright["image"]);
			$info["logo_text"] = $copyright["text"];
			$info["logo_phone"] = $copyright["phone"];
			$info["logo_switch"] = 2;
		}
	}
}
$data["config"] = $info;
$company = pdo_getall("longbing_card_company", array( "uniacid" => $_W["uniacid"], "status" => 1 ));
foreach( $company as $k => $v ) 
{
	$company[$k]["desc"] = tomedia($v["desc"]);
	$company[$k]["logo"] = $this->transImage($v["logo"]);
	$images = $v["culture"];
	$images = trim($images, ",");
	$images = explode(",", $images);
	$tmp = array( );
	foreach( $images as $k2 => $v2 ) 
	{
		$src = tomedia($v2);
		array_push($tmp, $src);
	}
	$company[$k]["culture"] = $tmp;
}
$data["company_list"] = $company;
$user = pdo_get("longbing_card_user_info", array( "fans_id" => $uid, "is_staff" => 1, "status" => 1 ));
if( $user ) 
{
	if( $user["company_id"] ) 
	{
		$company2 = pdo_get("longbing_card_company", array( "uniacid" => $_W["uniacid"], "id" => $user["company_id"] ));
		if( $company2 ) 
		{
			$company2["logo"] = $this->transImage($company2["logo"]);
			$data["my_company"] = $company2;
		}
		else 
		{
			$data["my_company"] = $company[0];
		}
	}
	else 
	{
		$data["my_company"] = $company[0];
	}
}
else 
{
	$data["my_company"] = $company[0];
}
$info = pdo_get("longbing_card_tabbar", array( "uniacid" => $_W["uniacid"] ));
if( empty($info) ) 
{
	$time = time();
	$dataConfig = array( "uniacid" => $_W["uniacid"], "status" => 1, "create_time" => $time, "update_time" => $time );
	$res = pdo_insert("longbing_card_tabbar", $dataConfig);
	if( $res ) 
	{
		$info = pdo_get("longbing_card_tabbar", array( "uniacid" => $_W["uniacid"] ));
	}
	else 
	{
		$data["tabBar"] = array( );
	}
}
if( $info ) 
{
	$i = 0;
	foreach( $info as $k => $v ) 
	{
		if( $i < 5 && 0 < $i ) 
		{
			$data["tabBar"]["menu_name"][] = $v;
		}
		else 
		{
			if( $i < 9 && 0 < $i ) 
			{
				$data["tabBar"]["menu_is_hide"][] = $v;
			}
			else 
			{
				if( $i < 13 && 0 < $i ) 
				{
					$data["tabBar"]["menu_url"][] = $v;
				}
				else 
				{
					if( $i < 17 && 0 < $i ) 
					{
						$data["tabBar"]["menu_url_out"][] = $v;
					}
					else 
					{
						if( $i < 21 && 0 < $i ) 
						{
							$data["tabBar"]["menu_url_jump_way"][] = $v;
						}
						else 
						{
							$data["tabBar"][$k] = $v;
						}
					}
				}
			}
		}
		$i++;
	}
}
if( $this->redis_sup_v3 ) 
{
	$redis_key = "longbing_card_configv3_" . $_W["uniacid"];
	$this->redis_server_v3->set($redis_key, json_encode($data));
	$this->redis_server_v3->EXPIRE($redis_key, 60 * 120);
}
return $this->result(0, "suc", $data);
?>