<?php  class Curl_request 
{
	public $url = "";
	public $method = "GET";
	public $post_data = NULL;
	public $headers = NULL;
	public $options = NULL;
	public function __construct($url, $method = "GET", $post_data = NULL, $headers = NULL, $options = NULL) 
	{
		$this->url = $url;
		$this->method = strtoupper($method);
		$this->post_data = $post_data;
		$this->headers = $headers;
		$this->options = $options;
	}
	public function __destruct() 
	{
		unset($this->url);
		unset($this->method);
		unset($this->post_data);
		unset($this->headers);
		unset($this->options);
	}
}
class Curl 
{
	private $size = 5;
	private $timeout = 5;
	private $callback = NULL;
	private $options = NULL;
	private $headers = array( );
	private $requests = array( );
	private $request_map = array( );
	private $errors = array( );
	public function __construct($callback = NULL) 
	{
		$this->callback = $callback;
	}
	public function add($request) 
	{
		$this->requests[] = $request;
		return true;
	}
	public function request($url, $method = "GET", $post_data = NULL, $headers = NULL, $options = NULL) 
	{
		$this->requests[] = new Curl_request($url, $method, $post_data, $headers, $options);
		return true;
	}
	public function get($url, $headers = NULL, $options = NULL) 
	{
		return $this->request($url, "GET", NULL, $headers, $options);
	}
	public function post($url, $post_data = NULL, $headers = NULL, $options = NULL) 
	{
		return $this->request($url, "POST", $post_data, $headers, $options);
	}
	public function execute($size = NULL) 
	{
		if( sizeof($this->requests) == 1 ) 
		{
			return $this->single_curl();
		}
		return $this->rolling_curl($size);
	}
	private function single_curl() 
	{
		$ch = curl_init();
		$request = array_shift($this->requests);
		$options = $this->get_options($request);
		curl_setopt_array($ch, $options);
		$output = curl_exec($ch);
		$info = curl_getinfo($ch);
		if( $this->callback ) 
		{
			$callback = $this->callback;
			if( is_callable($this->callback) ) 
			{
				call_user_func($callback, $output, $info, $request);
			}
			return true;
		}
		return $output;
	}
	private function rolling_curl($size = NULL) 
	{
		if( $size ) 
		{
			$this->size = $size;
		}
		else 
		{
			$this->size = count($this->requests);
		}
		if( sizeof($this->requests) < $this->size ) 
		{
			$this->size = sizeof($this->requests);
		}
		if( $this->size < 2 ) 
		{
			$this->set_error("size must be greater than 1");
		}
		$master = curl_multi_init();
		for( $i = 0; $i < $this->size; $i++ ) 
		{
			$ch = curl_init();
			$options = $this->get_options($this->requests[$i]);
			curl_setopt_array($ch, $options);
			curl_multi_add_handle($master, $ch);
			$key = (string) $ch;
			$this->request_map[$key] = $i;
		}
		$active = $done = NULL;
		while( ($execrun = curl_multi_exec($master, $active)) == CURLM_CALL_MULTI_PERFORM ) 
		{
		}
		if( $execrun != CURLM_OK ) 
		{
			break;
		}
		while( $done = curl_multi_info_read($master) ) 
		{
			$info = curl_getinfo($done["handle"]);
			$output = curl_multi_getcontent($done["handle"]);
			$error = curl_error($done["handle"]);
			$this->set_error($error);
			$callback = $this->callback;
			if( is_callable($callback) ) 
			{
				$key = (string) $done["handle"];
				$request = $this->requests[$this->request_map[$key]];
				unset($this->request_map[$key]);
				call_user_func($callback, $output, $info, $error, $request);
			}
			curl_close($done["handle"]);
			curl_multi_remove_handle($master, $done["handle"]);
		}
		if( $active ) 
		{
			curl_multi_select($master, $this->timeout);
		}
		if( !$active ) 
		{
			curl_multi_close($master);
			return true;
		}
	}
	private function get_options($request) 
	{
		$options = $this->__get("options");
		if( ini_get("safe_mode") == "Off" || !ini_get("safe_mode") ) 
		{
			$options[CURLOPT_FOLLOWLOCATION] = 1;
			$options[CURLOPT_MAXREDIRS] = 5;
		}
		$headers = $this->__get("headers");
		if( $request->options ) 
		{
			$options = $request->options + $options;
		}
		$options[CURLOPT_URL] = $request->url;
		if( $request->post_data && strtolower($request->method) == "post" ) 
		{
			$options[CURLOPT_POST] = 1;
			$options[CURLOPT_POSTFIELDS] = $request->post_data;
		}
		if( $headers ) 
		{
			$options[CURLOPT_HEADER] = 0;
			$options[CURLOPT_HTTPHEADER] = $headers;
		}
		return $options;
	}
	public function set_error($msg) 
	{
		if( !empty($msg) ) 
		{
			$this->errors[] = $msg;
		}
	}
	public function display_errors($open = "<p>", $close = "</p>") 
	{
		$str = "";
		foreach( $this->errors as $val ) 
		{
			$str .= $open . $val . $close;
		}
		return $str;
	}
	public function __set($name, $value) 
	{
		if( $name == "options" || $name == "headers" ) 
		{
			$this->$name = $value + $this->$name;
		}
		else 
		{
			$this->$name = $value;
		}
		return true;
	}
	public function __get($name) 
	{
		return (isset($this->$name) ? $this->$name : NULL);
	}
	public function __destruct() 
	{
		unset($this->size);
		unset($this->timeout);
		unset($this->callback);
		unset($this->options);
		unset($this->headers);
		unset($this->requests);
		unset($this->request_map);
		unset($this->errors);
	}
}
?>