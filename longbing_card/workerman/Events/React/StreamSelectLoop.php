<?php  namespace Workerman\Events\React;
class StreamSelectLoop extends Base 
{
	public function __construct() 
	{
		$this->_eventLoop = new \React\EventLoop\StreamSelectLoop();
	}
}
?>