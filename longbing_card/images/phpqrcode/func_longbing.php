<?php  if( !function_exists("longbing_check_redis") ) 
{
	function longbing_check_redis($server = "127.0.0.1", $port = 6379, $password = "") 
	{
		$check_load = "redis";
		$redis_sup = false;
		$redis_server = false;
		if( extension_loaded($check_load) ) 
		{
			try 
			{
				$redis_server = new Redis();
				$res = $redis_server->connect($server, $port);
				if( $password ) 
				{
					$pas_res = $redis_server->auth($password);
					if( !$pas_res ) 
					{
						$redis_sup = false;
						$redis_server = false;
						return array( $redis_sup, $redis_server );
					}
				}
				if( $res ) 
				{
					$redis_sup = true;
				}
				else 
				{
					$redis_sup = false;
					$redis_server = false;
				}
			}
			catch( Exception $e ) 
			{
				$redis_sup = false;
				$redis_server = false;
			}
		}
		else 
		{
			$redis_sup = false;
			$redis_server = false;
		}
		return array( $redis_sup, $redis_server );
	}
}
if( !function_exists("longbing_redis_flushDB") ) 
{
	function longbing_redis_flushDB($server = "127.0.0.1", $port = 6379, $password = "") 
	{
		$check_load = "redis";
		if( extension_loaded($check_load) ) 
		{
			try 
			{
				$redis_server = new Redis();
				$res = $redis_server->connect($server, $port);
				if( $password ) 
				{
					$pas_res = $redis_server->auth($password);
					if( !$pas_res ) 
					{
						$redis_sup = false;
						$redis_server = false;
						return array( $redis_sup, $redis_server );
					}
				}
				if( $res ) 
				{
					$redis_server->flushDB();
					return true;
				}
				return false;
			}
			catch( Exception $e ) 
			{
				return false;
			}
		}
		else 
		{
			return false;
		}
	}
}
?>