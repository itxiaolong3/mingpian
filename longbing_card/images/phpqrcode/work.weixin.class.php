<?php  class work 
{
	protected $appid = NULL;
	protected $appsecret = NULL;
	public function __construct($appid = "", $appsecret = "") 
	{
		$this->appid = $appid;
		$this->appsecret = $appsecret;
		if( !is_dir($_SERVER["DOCUMENT_ROOT"] . "/data") ) 
		{
			mkdir($_SERVER["DOCUMENT_ROOT"] . "/data");
		}
		if( !is_dir($_SERVER["DOCUMENT_ROOT"] . "/data/tpl") ) 
		{
			mkdir($_SERVER["DOCUMENT_ROOT"] . "/data/tpl");
		}
		if( !is_dir($_SERVER["DOCUMENT_ROOT"] . "/data/tpl/web") ) 
		{
			mkdir($_SERVER["DOCUMENT_ROOT"] . "/data/tpl/web");
		}
	}
	public function send(array $data) 
	{
		if( is_array($data) ) 
		{
			$data = json_encode($data, JSON_UNESCAPED_UNICODE);
		}
		$accessTokenWW = $this->getAccessTokenWW();
		if( is_array($accessTokenWW) ) 
		{
			return $accessTokenWW;
		}
		$url = "https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token=" . $accessTokenWW;
		$res = $this->curlPost($url, $data);
		return $res;
	}
	public function send_multi(array $data) 
	{
		$accessTokenWW = $this->getAccessTokenWW();
		if( is_array($accessTokenWW) ) 
		{
			return $accessTokenWW;
		}
		$url = "https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token=" . $accessTokenWW;
		foreach( $data as $index => $item ) 
		{
			$data[$index]["url"] = $url;
		}
		$res = $this->curl_multi($data);
		return $res;
	}
	protected function getAccessTokenWW() 
	{
		$appidMd5 = md5($this->appid);
		if( !is_file($_SERVER["DOCUMENT_ROOT"] . "/data/tpl/web/" . $appidMd5 . ".txt") ) 
		{
			$url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=" . $this->appid . "&corpsecret=" . $this->appsecret;
			$data = $this->curlPost($url);
			$data = json_decode($data, true);
			if( !isset($data["access_token"]) ) 
			{
				return $data;
			}
			$access_token = $data["access_token"];
			file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/data/tpl/web/" . $appidMd5 . ".txt", json_encode(array( "at" => $access_token, "time" => time() + 6200 )));
			return $access_token;
		}
		if( is_file($_SERVER["DOCUMENT_ROOT"] . "/data/tpl/web/" . $appidMd5 . ".txt") ) 
		{
			$fileInfo = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/data/tpl/web/" . $appidMd5 . ".txt");
			if( !$fileInfo ) 
			{
				$url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=" . $this->appid . "&corpsecret=" . $this->appsecret;
				$data = $this->curlPost($url);
				$data = json_decode($data, true);
				if( !isset($data["access_token"]) ) 
				{
					return $data;
				}
				$access_token = $data["access_token"];
				file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/data/tpl/web/" . $appidMd5 . ".txt", json_encode(array( "at" => $access_token, "time" => time() + 6200 )));
				return $access_token;
			}
			$fileInfo = json_decode($fileInfo, true);
			if( $fileInfo["time"] < time() ) 
			{
				$url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=" . $this->appid . "&corpsecret=" . $this->appsecret;
				$data = $this->curlPost($url);
				$data = json_decode($data, true);
				if( !isset($data["access_token"]) ) 
				{
					return $data;
				}
				$access_token = $data["access_token"];
				file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/data/tpl/web/" . $appidMd5 . ".txt", json_encode(array( "at" => $access_token, "time" => time() + 6200 )));
				return $access_token;
			}
			return $fileInfo["at"];
		}
		return false;
	}
	protected function curlPost($url, $data = "", $time = 20) 
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, $time);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}
	protected function curl_multi($array) 
	{
		global $_GPC;
		global $_W;
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
}
?>