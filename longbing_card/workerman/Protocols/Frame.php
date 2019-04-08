<?php  namespace Workerman\Protocols;
class Frame 
{
	public static function input($buffer, \Workerman\Connection\TcpConnection $connection) 
	{
		if( strlen($buffer) < 4 ) 
		{
			return 0;
		}
		$unpack_data = unpack("Ntotal_length", $buffer);
		return $unpack_data["total_length"];
	}
	public static function decode($buffer) 
	{
		return substr($buffer, 4);
	}
	public static function encode($buffer) 
	{
		$total_length = 4 + strlen($buffer);
		return pack("N", $total_length) . $buffer;
	}
}
?>