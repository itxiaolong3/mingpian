<?php  namespace Workerman\Events\React;
class ExtLibEventLoop extends Base 
{
	public function __construct() 
	{
		$this->_eventLoop = new \React\EventLoop\ExtLibeventLoop();
	}
}
?>