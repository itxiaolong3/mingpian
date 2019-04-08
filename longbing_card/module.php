<?php  defined("IN_IA") or exit( "Access Denied" );
class Longbing_cardModule extends WeModule 
{
	public $module_name = NULL;
	public $mini_name = NULL;
	public $redis_sup_v3 = false;
	public $redis_server_v3 = false;
	public function __construct() 
	{
		global $_GPC;
		global $_W;
		$module_name = $_W["current_module"]["name"];
		$mini_name = $_W["account"]["name"];
		$this->module_name = $module_name;
		$this->mini_name = $mini_name;
		$check_load = "redis";
		if( extension_loaded($check_load) ) 
		{
			try 
			{
				$config = $_W["config"]["setting"]["redis"];
				$redis_server = new Redis();
				$res = $redis_server->connect($config["server"], $config["port"]);
				if( $res ) 
				{
					$this->redis_sup_v3 = true;
					$this->redis_server_v3 = $redis_server;
				}
				else 
				{
					$this->redis_sup_v3 = false;
					$this->redis_server_v3 = false;
				}
				if( $config && isset($config["requirepass"]) && $config["requirepass"] ) 
				{
					$pas_res = $redis_server->auth($config["requirepass"]);
					if( !$pas_res ) 
					{
						$this->redis_sup_v3 = false;
						$this->redis_server_v3 = false;
					}
					else 
					{
						$this->redis_sup_v3 = true;
						$this->redis_server_v3 = $redis_server;
					}
				}
			}
			catch( Exception $e ) 
			{
				$this->redis_sup_v3 = false;
				$this->redis_server_v3 = false;
			}
		}
		else 
		{
			$this->redis_sup_v3 = false;
			$this->redis_server_v3 = false;
		}
	}
	public function welcomeDisplay($menus = array( )) 
	{
		global $_GPC;
		global $_W;
		$module_name = $this->module_name;
		$mini_name = $this->mini_name;
		$redis_sup_v3 = $this->redis_sup_v3;
		$ser = $_SERVER["HTTP_HOST"];
		$overview = $this->createWebUrl("manage/overview");
		$companyList = $this->createWebUrl("manage/company");
		$companyEdit = $this->createWebUrl("manage/companyedit");
		$dutiesList = $this->createWebUrl("manage/duties");
		$usersList = $this->createWebUrl("manage/users");
		$typeList = $this->createWebUrl("manage/type");
		$goodsList = $this->createWebUrl("manage/goods");
		$addGoods = $this->createWebUrl("manage/goodsEdit");
		$orderList = $this->createWebUrl("manage/orders");
		$timelineList = $this->createWebUrl("manage/timeline");
		$timelineEdit = $this->createWebUrl("manage/timelineedit");
		$commentList = $this->createWebUrl("manage/comment");
		$modularList = $this->createWebUrl("manage/modular");
		$message = $this->createWebUrl("manage/message");
		$config = $this->createWebUrl("manage/config");
		$userCollage = $this->createWebUrl("manage/usercollage");
		$tabBar = $this->createWebUrl("manage/tabBar");
		$replyType = $this->createWebUrl("manage/replytype");
		$reply = $this->createWebUrl("manage/reply");
		$clientList = $this->createWebUrl("manage/client");
		$posterType = $this->createWebUrl("manage/postertype");
		$poster = $this->createWebUrl("manage/poster");
		$bossexplain = $this->createWebUrl("manage/bossexplain");
		$staffexplain = $this->createWebUrl("manage/staffexplain");
		$couponList = $this->createWebUrl("manage/coupon");
		$groupSending = $this->createWebUrl("manage/groupsending");
		$handover = $this->createWebUrl("manage/handover");
		$profitList = $this->createWebUrl("manage/profit");
		$waterList = $this->createWebUrl("manage/water");
		$cashList = $this->createWebUrl("manage/cash");
		$plugFormList = $this->createWebUrl("manage/plugform");
		$plugList = $this->createWebUrl("manage/pluglist");
		$tags = $this->createWebUrl("manage/tags");
		$relationship = $this->createWebUrl("manage/relationship");
		$textGroup = $this->createWebUrl("manage/textgroup");
        //我的数据开始
        //邀请码列表
        $randcodeList = $this->createWebUrl("manage/randcodelist");
        $teamsetting = $this->createWebUrl("manage/teamsetting");
        $userbook = $this->createWebUrl("manage/userbook");
        //我的数据结束
		$show_plug = 0;
		$plug_list = array( array( "url" => $plugList, "title" => "插件列表", "sign" => "plugList" ), array( "url" => $plugFormList, "title" => "首页表单", "sign" => "form" ) );
		is_file(__DIR__ . "/inc/we7.php") or exit( "Access Denied Longbing" );
		require_once(__DIR__ . "/inc/we7.php");
		if( defined("LONGBING_AUTH_PLUG_AUTH") && LONGBING_AUTH_PLUG_AUTH != 0 ) 
		{
			$show_plug = 1;
		}
		if( defined("LONGBING_AUTH_FORM") && LONGBING_AUTH_FORM != 0 ) 
		{
			$show_plug = 1;
		}
		$res = false;
		$auth = 0;
		$domainMd5 = md5($_SERVER["HTTP_HOST"]);
		if( is_file(IA_ROOT . "/data/tpl/web/" . $domainMd5 . "tplAuth.txt") ) 
		{
			$fileInfo = file_get_contents(IA_ROOT . "/data/tpl/web/" . $domainMd5 . "tplAuth.txt");
			if( !$fileInfo ) 
			{
				$res = $this->checkExists($_W, $domainMd5);
			}
			else 
			{
				$fileInfo = date("Y-m-d", $fileInfo);
				if( $fileInfo != date("Y-m-d") ) 
				{
					$res = $this->checkExists($_W, $domainMd5);
				}
			}
		}
		else 
		{
			$res = $this->checkExists($_W, $domainMd5);
		}
		if( $res ) 
		{
			$overview = "javascript:;";
			$companyList = "javascript:;";
			$companyEdit = "javascript:;";
			$dutiesList = "javascript:;";
			$usersList = "javascript:;";
			$typeList = "javascript:;";
			$goodsList = "javascript:;";
			$addGoods = "javascript:;";
			$orderList = "javascript:;";
			$timelineList = "javascript:;";
			$timelineEdit = "javascript:;";
			$commentList = "javascript:;";
			$modularList = "javascript:;";
			$message = "javascript:;";
			$config = "javascript:;";
			$userCollage = "javascript:;";
			$tabBar = "javascript:;";
			$replyType = "javascript:;";
			$reply = "javascript:;";
			$clientList = "javascript:;";
			$posterType = "javascript:;";
			$poster = "javascript:;";
			$auth = 1;
		}
		load()->func("tpl");
        $userid=$_W['uid'];//当前登录用户id
        $username=$_W['username'];//当前登录用户
        //权限管理判断
        $permiuser=pdo_get('users_permission',array('uid'=>$userid));

		include($this->template("manage/index"));
	}
	protected function checkExists($_W, $domainMd5) 
	{
		file_put_contents(IA_ROOT . "/data/tpl/web/" . $domainMd5 . "tplAuth.txt", time());
		$checkExists = pdo_tableexists("longbing_cardauth2_config");
		if( $checkExists ) 
		{
			$auth_info = pdo_get("longbing_cardauth2_config", array( "modular_id" => $_W["uniacid"] ));
			$time = time();
			if( $auth_info && $auth_info["end_time"] < $time ) 
			{
				return true;
			}
		}
		return false;
	}
	public function pp($data) 
	{
		echo "<pre>";
		var_dump($data);
		echo "</pre>";
		exit();
	}
}
?>