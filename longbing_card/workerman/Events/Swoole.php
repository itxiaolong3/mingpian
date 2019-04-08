<?php  namespace Workerman\Events;
class Swoole implements EventInterface 
{
	protected $_timer = array( );
	protected $_timerOnceMap = array( );
	protected $_fd = array( );
	public static $signalDispatchInterval = 200;
	protected $_hasSignal = false;
	public function add($fd, $flag, $func, $args = NULL) 
	{
		if( !isset($args) ) 
		{
			$args = array( );
		}
		switch( $flag ) 
		{
			case self::EV_SIGNAL: $res = pcntl_signal($fd, $func, false);
			if( !$this->_hasSignal && $res ) 
			{
				\Swoole\Timer::tick(static::$signalDispatchInterval, function() 
				{
					pcntl_signal_dispatch();
				}
				);
				$this->_hasSignal = true;
			}
			return $res;
			case self::EV_TIMER: case self::EV_TIMER_ONCE: $method = (self::EV_TIMER == $flag ? "tick" : "after");
			$mapId = count($this->_timerOnceMap);
			$timer_id = \Swoole\Timer::$method($fd * 1000, function($timer_id = NULL) use ($func, $args, $mapId) 
			{
				call_user_func_array($func, $args);
				if( !isset($timer_id) && array_key_exists($mapId, $this->_timerOnceMap) ) 
				{
					$timer_id = $this->_timerOnceMap[$mapId];
					unset($this->_timer[$timer_id]);
					unset($this->_timerOnceMap[$mapId]);
				}
			}
			);
			if( $flag == self::EV_TIMER_ONCE ) 
			{
				$this->_timerOnceMap[$mapId] = $timer_id;
				$this->_timer[$timer_id] = $mapId;
			}
			else 
			{
				$this->_timer[$timer_id] = null;
			}
			return $timer_id;
			case self::EV_READ: case self::EV_WRITE: $fd_key = (int) $fd;
			if( !isset($this->_fd[$fd_key]) ) 
			{
				if( $flag == self::EV_READ ) 
				{
					$res = \Swoole\Event::add($fd, $func, null, SWOOLE_EVENT_READ);
					$fd_type = SWOOLE_EVENT_READ;
				}
				else 
				{
					$res = \Swoole\Event::add($fd, null, $func, SWOOLE_EVENT_WRITE);
					$fd_type = SWOOLE_EVENT_WRITE;
				}
				if( $res ) 
				{
					$this->_fd[$fd_key] = $fd_type;
				}
			}
			else 
			{
				$fd_val = $this->_fd[$fd_key];
				$res = true;
				if( $flag == self::EV_READ ) 
				{
					if( ($fd_val & SWOOLE_EVENT_READ) != SWOOLE_EVENT_READ ) 
					{
						$res = \Swoole\Event::set($fd, $func, null, SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE);
						$this->_fd[$fd_key] |= SWOOLE_EVENT_READ;
					}
				}
				else 
				{
					if( ($fd_val & SWOOLE_EVENT_WRITE) != SWOOLE_EVENT_WRITE ) 
					{
						$res = \Swoole\Event::set($fd, null, $func, SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE);
						$this->_fd[$fd_key] |= SWOOLE_EVENT_WRITE;
					}
				}
			}
			return $res;
		}
	}
	public function del($fd, $flag) 
	{
		switch( $flag ) 
		{
			case self::EV_SIGNAL: return pcntl_signal($fd, SIG_IGN, false);
			case self::EV_TIMER: case self::EV_TIMER_ONCE: if( !array_key_exists($fd, $this->_timer) ) 
			{
				return true;
			}
			$res = \Swoole\Timer::clear($fd);
			if( $res ) 
			{
				$mapId = $this->_timer[$fd];
				if( isset($mapId) ) 
				{
					unset($this->_timerOnceMap[$mapId]);
				}
				unset($this->_timer[$fd]);
			}
			return $res;
			case self::EV_READ: case self::EV_WRITE: $fd_key = (int) $fd;
			if( isset($this->_fd[$fd_key]) ) 
			{
				$fd_val = $this->_fd[$fd_key];
				if( $flag == self::EV_READ ) 
				{
					$flag_remove = ~SWOOLE_EVENT_READ;
				}
				else 
				{
					$flag_remove = ~SWOOLE_EVENT_WRITE;
				}
				$fd_val &= $flag_remove;
				if( 0 === $fd_val ) 
				{
					$res = \Swoole\Event::del($fd);
					if( $res ) 
					{
						unset($this->_fd[$fd_key]);
					}
				}
				else 
				{
					$res = \Swoole\Event::set($fd, null, null, $fd_val);
					if( $res ) 
					{
						$this->_fd[$fd_key] = $fd_val;
					}
				}
			}
			else 
			{
				$res = true;
			}
			return $res;
		}
	}
	public function clearAllTimer() 
	{
		foreach( array_keys($this->_timer) as $v ) 
		{
			\Swoole\Timer::clear($v);
		}
		$this->_timer = array( );
		$this->_timerOnceMap = array( );
	}
	public function loop() 
	{
		\Swoole\Event::wait();
	}
	public function destroy() 
	{
	}
	public function getTimerCount() 
	{
		return count($this->_timer);
	}
}
?>