<?php  namespace Workerman\Events\React;
class Base implements \React\EventLoop\LoopInterface 
{
	protected $_timerIdMap = array( );
	protected $_timerIdIndex = 0;
	protected $_signalHandlerMap = array( );
	protected $_eventLoop = NULL;
	public function __construct() 
	{
		$this->_eventLoop = new \React\EventLoop\StreamSelectLoop();
	}
	public function add($fd, $flag, $func, $args = array( )) 
	{
		$args = (array) $args;
		switch( $flag ) 
		{
			case \Workerman\Events\EventInterface::EV_READ: return $this->addReadStream($fd, $func);
			case \Workerman\Events\EventInterface::EV_WRITE: return $this->addWriteStream($fd, $func);
			case \Workerman\Events\EventInterface::EV_SIGNAL: if( isset($this->_signalHandlerMap[$fd]) ) 
			{
				$this->removeSignal($fd, $this->_signalHandlerMap[$fd]);
			}
			$this->_signalHandlerMap[$fd] = $func;
			return $this->addSignal($fd, $func);
			case \Workerman\Events\EventInterface::EV_TIMER: $timer_obj = $this->addPeriodicTimer($fd, function() use ($func, $args) 
			{
				call_user_func_array($func, $args);
			}
			);
			$this->_timerIdMap[++$this->_timerIdIndex] = $timer_obj;
			return $this->_timerIdIndex;
			case \Workerman\Events\EventInterface::EV_TIMER_ONCE: $index = ++$this->_timerIdIndex;
			$timer_obj = $this->addTimer($fd, function() use ($func, $args, $index) 
			{
				$this->del($index, \Workerman\Events\EventInterface::EV_TIMER_ONCE);
				call_user_func_array($func, $args);
			}
			);
			$this->_timerIdMap[$index] = $timer_obj;
			return $this->_timerIdIndex;
		}
		return false;
	}
	public function del($fd, $flag) 
	{
		switch( $flag ) 
		{
			case \Workerman\Events\EventInterface::EV_READ: return $this->removeReadStream($fd);
			case \Workerman\Events\EventInterface::EV_WRITE: return $this->removeWriteStream($fd);
			case \Workerman\Events\EventInterface::EV_SIGNAL: if( !isset($this->_eventLoop[$fd]) ) 
			{
				return false;
			}
			$func = $this->_eventLoop[$fd];
			unset($this->_eventLoop[$fd]);
			return $this->removeSignal($fd, $func);
			case \Workerman\Events\EventInterface::EV_TIMER: case \Workerman\Events\EventInterface::EV_TIMER_ONCE: if( isset($this->_timerIdMap[$fd]) ) 
			{
				$timer_obj = $this->_timerIdMap[$fd];
				unset($this->_timerIdMap[$fd]);
				$this->cancelTimer($timer_obj);
				return true;
			}
		}
		return false;
	}
	public function loop() 
	{
		$this->run();
	}
	public function destroy() 
	{
	}
	public function getTimerCount() 
	{
		return count($this->_timerIdMap);
	}
	public function addReadStream($stream, $listener) 
	{
		return $this->_eventLoop->addReadStream($stream, $listener);
	}
	public function addWriteStream($stream, $listener) 
	{
		return $this->_eventLoop->addWriteStream($stream, $listener);
	}
	public function removeReadStream($stream) 
	{
		return $this->_eventLoop->removeReadStream($stream);
	}
	public function removeWriteStream($stream) 
	{
		return $this->_eventLoop->removeWriteStream($stream);
	}
	public function addTimer($interval, $callback) 
	{
		return $this->_eventLoop->addTimer($interval, $callback);
	}
	public function addPeriodicTimer($interval, $callback) 
	{
		return $this->_eventLoop->addPeriodicTimer($interval, $callback);
	}
	public function cancelTimer(\React\EventLoop\TimerInterface $timer) 
	{
		return $this->_eventLoop->cancelTimer($timer);
	}
	public function futureTick($listener) 
	{
		return $this->_eventLoop->futureTick($listener);
	}
	public function addSignal($signal, $listener) 
	{
		return $this->_eventLoop->addSignal($signal, $listener);
	}
	public function removeSignal($signal, $listener) 
	{
		return $this->_eventLoop->removeSignal($signal, $listener);
	}
	public function run() 
	{
		return $this->_eventLoop->run();
	}
	public function stop() 
	{
		return $this->_eventLoop->stop();
	}
}
?>