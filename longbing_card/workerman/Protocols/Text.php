<?php  namespace Workerman\Protocols;
class Text 
{
	public static function input($buffer, \Workerman\Connection\TcpConnection $connection) 
	{
		if( $connection::$maxPackageSize <= strlen($buffer) ) 
		{
			$connection->close();
			return 0;
		}
		$pos = strpos($buffer, "\n");
		if( $pos === false ) 
		{
			return 0;
		}
		return $pos + 1;
	}
	public static function encode($buffer) 
	{
		return $buffer . "\n";
	}
	public static function decode($buffer) 
	{
		return trim($buffer);
	}
}
?>