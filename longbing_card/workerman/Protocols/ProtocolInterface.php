<?php  namespace Workerman\Protocols;
interface ProtocolInterface 
{
	public static function input($recv_buffer, \Workerman\Connection\ConnectionInterface $connection);
	public static function decode($recv_buffer, \Workerman\Connection\ConnectionInterface $connection);
	public static function encode($data, \Workerman\Connection\ConnectionInterface $connection);
}
?>