<?php  namespace Aliyun\DySDKLite;
class SignatureHelper 
{
	public function request($accessKeyId, $accessKeySecret, $domain, $params, $security = false, $method = "POST") 
	{
		$apiParams = array_merge(array( "SignatureMethod" => "HMAC-SHA1", "SignatureNonce" => uniqid(mt_rand(0, 65535), true), "SignatureVersion" => "1.0", "AccessKeyId" => $accessKeyId, "Timestamp" => gmdate("Y-m-d\\TH:i:s\\Z"), "Format" => "JSON" ), $params);
		ksort($apiParams);
		$sortedQueryStringTmp = "";
		foreach( $apiParams as $key => $value ) 
		{
			$sortedQueryStringTmp .= "&" . $this->encode($key) . "=" . $this->encode($value);
		}
		$stringToSign = (string) $method . "&%2F&" . $this->encode(substr($sortedQueryStringTmp, 1));
		$sign = base64_encode(hash_hmac("sha1", $stringToSign, $accessKeySecret . "&", true));
		$signature = $this->encode($sign);
		$url = (($security ? "https" : "http")) . "://" . $domain . "/";
		try 
		{
			$content = $this->fetchContent($url, $method, "Signature=" . $signature . $sortedQueryStringTmp);
			return json_decode($content);
		}
		catch( \Exception $e ) 
		{
			return false;
		}
	}
	private function encode($str) 
	{
		$res = urlencode($str);
		$res = preg_replace("/\\+/", "%20", $res);
		$res = preg_replace("/\\*/", "%2A", $res);
		$res = preg_replace("/%7E/", "~", $res);
		return $res;
	}
	private function fetchContent($url, $method, $body) 
	{
		$ch = curl_init();
		if( $method == "POST" ) 
		{
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		}
		else 
		{
			$url .= "?" . $body;
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array( "x-sdk-client" => "php/2.0.0" ));
		if( substr($url, 0, 5) == "https" ) 
		{
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		}
		$rtn = curl_exec($ch);
		if( $rtn === false ) 
		{
			trigger_error("[CURL_" . curl_errno($ch) . "]: " . curl_error($ch), E_USER_ERROR);
		}
		curl_close($ch);
		return $rtn;
	}
}
?>