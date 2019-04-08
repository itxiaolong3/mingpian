<?php  namespace Workerman\Events;
class Event implements EventInterface 
{
	protected $_eventBase = NULL;
	protected $_allEvents = array( );
	protected $_eventSignal = array( );
	protected $_eventTimer = array( );
	protected static $_timerId = 1;
	public function __construct() 
	{
		if( class_exists("\\\\EventBase") ) 
		{
			$class_name = "\\\\EventBase";
		}
		else 
		{
			$class_name = "\\EventBase";
		}
		$this->_eventBase = new $class_name();
	}
	public function add($fd, $flag, $func, $args = array( )) 
	{
		if( class_exists("\\\\Event") ) 
		{
			$class_name = "\\\\Event";
		}
		else 
		{
			$class_name = "\\Event";
		}
		switch( $flag ) 
		{
			case self::EV_SIGNAL: $fd_key = (int) $fd;
			$event = $class_name::signal($this->_eventBase, $fd, $func);
			if( !$event || !$event->add() ) 
			{
				return false;
			}
			$this->_eventSignal[$fd_key] = $event;
			return true;
			case self::EV_TIMER: case self::EV_TIMER_ONCE: $param = array( $func, (array) $args, $flag, $fd, self::$_timerId );
			$event = new $class_name($this->_eventBase, -1, $class_name::TIMEOUT | $class_name::PERSIST, array( $this, "timerCallback" ), $param);
			if( !$event || !$event->addTimer($fd) ) 
			{
				return false;
			}
			$this->_eventTimer[self::$_timerId] = $event;
			return self::$_timerId++;
			default: $fd_key = (int) $fd;
			$real_flag = ($flag === self::EV_READ ? $class_name::READ | $class_name::PERSIST : $class_name::WRITE | $class_name::PERSIST);
			$event = new $class_name($this->_eventBase, $fd, $real_flag, $func, $fd);
			if( !$event || !$event->add() ) 
			{
				return false;
			}
			$this->_allEvents[$fd_key][$flag] = $event;
		}
		return true;
	}
	public function del($fd, $flag) 
	{
		switch( $flag ) 
		{
			case self::EV_READ: case self::EV_WRITE: $fd_key = (int) $fd;
			if( isset($this->_allEvents[$fd_key][$flag]) ) 
			{
				$this->_allEvents[$fd_key][$flag]->del();
				unset($this->_allEvents[$fd_key][$flag]);
			}
			if( empty($this->_allEvents[$fd_key]) ) 
			{
				unset($this->_allEvents[$fd_key]);
			}
			break;
			case self::EV_SIGNAL: $fd_key = (int) $fd;
			if( isset($this->_eventSignal[$fd_key]) ) 
			{
				$this->_eventSignal[$fd_key]->del();
				unset($this->_eventSignal[$fd_key]);
			}
			break;
			case self::EV_TIMER: case self::EV_TIMER_ONCE: if( isset($this->_eventTimer[$fd]) ) 
			{
				$this->_eventTimer[$fd]->del();
				unset($this->_eventTimer[$fd]);
			}
			break;
		}
		return true;
	}
	public function timerCallback($fd, $what, $param) 
	{
		$timer_id = $param[4];
		if( $param[2] === self::EV_TIMER_ONCE ) 
		{
			$this->_eventTimer[$timer_id]->del();
			unset($this->_eventTimer[$timer_id]);
		}
		try 
		{
			call_user_func_array($param[0], $param[1]);
		}
		catch( \Exception $e ) 
		{
			\Workerman\Worker::log($e);
			exit( 250 );
		}
		catch( \Error $e ) 
		{
			\Workerman\Worker::log($e);
			exit( 250 );
		}
	}
	public function clearAllTimer() 
	{
		foreach( $this->_eventTimer as $event ) 
		{
			$event->del();
		}
		$this->_eventTimer = array( );
	}
	public function loop() 
	{
		$this->_eventBase->loop();
	}
	public function destroy() 
	{
		foreach( $this->_eventSignal as $event ) 
		{
			$event->del();
		}
	}
	public function getTimerCount() 
	{
		return count($this->_eventTimer);
	}
}
?>