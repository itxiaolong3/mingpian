<?php  namespace Workerman\Lib;
class Timer 
{
	protected static $_tasks = array( );
	protected static $_event = NULL;
	public static function init($event = NULL) 
	{
		if( $event ) 
		{
			self::$_event = $event;
		}
		else 
		{
			if( function_exists("pcntl_signal") ) 
			{
				pcntl_signal(SIGALRM, array( "\\Workerman\\Lib\\Timer", "signalHandle" ), false);
			}
		}
	}
	public static function signalHandle() 
	{
		if( !self::$_event ) 
		{
			pcntl_alarm(1);
			self::tick();
		}
	}
	public static function add($time_interval, $func, $args = array( ), $persistent = true) 
	{
		if( $time_interval <= 0 ) 
		{
			Worker::safeEcho(new \Exception("bad time_interval"));
			return false;
		}
		if( self::$_event ) 
		{
			return self::$_event->add($time_interval, ($persistent ? \Workerman\Events\EventInterface::EV_TIMER : \Workerman\Events\EventInterface::EV_TIMER_ONCE), $func, $args);
		}
		if( !is_callable($func) ) 
		{
			Worker::safeEcho(new \Exception("not callable"));
			return false;
		}
		if( empty($_tasks) ) 
		{
			pcntl_alarm(1);
		}
		$time_now = time();
		$run_time = $time_now + $time_interval;
		if( !isset(self::$_tasks[$run_time]) ) 
		{
			self::$_tasks[$run_time] = array( );
		}
		self::$_tasks[$run_time][] = array( $func, (array) $args, $persistent, $time_interval );
		return 1;
	}
	public static function tick() 
	{
		if( empty($_tasks) ) 
		{
			pcntl_alarm(0);
		}
		else 
		{
			$time_now = time();
			foreach( self::$_tasks as $run_time => $task_data ) 
			{
				if( $run_time <= $time_now ) 
				{
					foreach( $task_data as $index => $one_task ) 
					{
						list($task_func, $task_args, $persistent, $time_interval) = $one_task;
						try 
						{
							call_user_func_array($task_func, $task_args);
						}
						catch( \Exception $e ) 
						{
							Worker::safeEcho($e);
						}
						if( $persistent ) 
						{
							self::add($time_interval, $task_func, $task_args);
						}
					}
					unset(self::$_tasks[$run_time]);
				}
			}
		}
	}
	public static function del($timer_id) 
	{
		if( self::$_event ) 
		{
			return self::$_event->del($timer_id, \Workerman\Events\EventInterface::EV_TIMER);
		}
		return false;
	}
	public static function delAll() 
	{
		self::$_tasks = array( );
		pcntl_alarm(0);
		if( self::$_event ) 
		{
			self::$_event->clearAllTimer();
		}
	}
}
?>