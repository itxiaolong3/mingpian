<?php  namespace Workerman\Events\React;
class ExtEventLoop extends Base 
{
	public function __construct() 
	{
		$this->_eventLoop = new \React\EventLoop\ExtEventLoop();
	}
}
?>