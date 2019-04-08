<?php  namespace Workerman;
require_once(__DIR__ . "/Lib/Constants.php");
class Worker 
{
	public $id = 0;
	public $name = "none";
	public $count = 1;
	public $user = "";
	public $group = "";
	public $reloadable = true;
	public $reusePort = false;
	public $onWorkerStart = NULL;
	public $onConnect = NULL;
	public $onMessage = NULL;
	public $onClose = NULL;
	public $onError = NULL;
	public $onBufferFull = NULL;
	public $onBufferDrain = NULL;
	public $onWorkerStop = NULL;
	public $onWorkerReload = NULL;
	public $transport = "tcp";
	public $connections = array( );
	public $protocol = NULL;
	protected $_autoloadRootPath = "";
	protected $_pauseAccept = true;
	public $stopping = false;
	public static $daemonize = false;
	public static $stdoutFile = "/dev/null";
	public static $pidFile = "";
	public static $logFile = "";
	public static $globalEvent = NULL;
	public static $onMasterReload = NULL;
	public static $onMasterStop = NULL;
	public static $eventLoopClass = "";
	protected static $_masterPid = 0;
	protected $_mainSocket = NULL;
	protected $_socketName = "";
	protected $_context = NULL;
	protected static $_workers = array( );
	protected static $_pidMap = array( );
	protected static $_pidsToRestart = array( );
	protected static $_idMap = array( );
	protected static $_status = self::STATUS_STARTING;
	protected static $_maxWorkerNameLength = 12;
	protected static $_maxSocketNameLength = 12;
	protected static $_maxUserNameLength = 12;
	protected static $_statisticsFile = "";
	protected static $_startFile = "";
	protected static $_OS = OS_TYPE_LINUX;
	protected static $_processForWindows = array( );
	protected static $_globalStatistics = array( "start_timestamp" => 0, "worker_exit_info" => array( ) );
	protected static $_availableEventLoops = array( "libevent" => "\\Workerman\\Events\\Libevent", "event" => "\\Workerman\\Events\\Event", "swoole" => "\\Workerman\\Events\\Swoole" );
	protected static $_builtinTransports = array( "tcp" => "tcp", "udp" => "udp", "unix" => "unix", "ssl" => "tcp" );
	protected static $_gracefulStop = false;
	protected static $_outputStream = NULL;
	protected static $_outputDecorated = NULL;
	const VERSION = "3.5.14";
	const STATUS_STARTING = 1;
	const STATUS_RUNNING = 2;
	const STATUS_SHUTDOWN = 4;
	const STATUS_RELOADING = 8;
	const KILL_WORKER_TIMER_TIME = 2;
	const DEFAULT_BACKLOG = 102400;
	const MAX_UDP_PACKAGE_SIZE = 65535;
	public static function runAll() 
	{
		static::checkSapiEnv();
		static::init();
		static::parseCommand();
		static::daemonize();
		static::initWorkers();
		static::installSignal();
		static::saveMasterPid();
		static::displayUI();
		static::forkWorkers();
		static::resetStd();
		static::monitorWorkers();
	}
	protected static function checkSapiEnv() 
	{
		if( php_sapi_name() != "cli" ) 
		{
			exit( "only run in command line mode \n" );
		}
		if( DIRECTORY_SEPARATOR === "\\" ) 
		{
			self::$_OS = OS_TYPE_WINDOWS;
		}
	}
	protected static function init() 
	{
		set_error_handler(function($code, $msg, $file, $line) 
		{
			Worker::safeEcho((string) $msg . " in file " . $file . " on line " . $line . "\n");
		}
		);
		$backtrace = debug_backtrace();
		static::$_startFile = $backtrace[count($backtrace) - 1]["file"];
		$unique_prefix = str_replace("/", "_", static::$_startFile);
		if( empty($pidFile) ) 
		{
			static::$pidFile = __DIR__ . "/../" . $unique_prefix . ".pid";
		}
		if( empty($logFile) ) 
		{
			static::$logFile = __DIR__ . "/../workerman.log";
		}
		$log_file = (string) static::$logFile;
		if( !is_file($log_file) ) 
		{
			touch($log_file);
			chmod($log_file, 402);
		}
		static::$_status = static::STATUS_STARTING;
		static::$_globalStatistics["start_timestamp"] = time();
		static::$_statisticsFile = sys_get_temp_dir() . "/" . $unique_prefix . ".status";
		static::setProcessTitle("WorkerMan: master process  start_file=" . static::$_startFile);
		static::initId();
		Lib\Timer::init();
	}
	protected static function initWorkers() 
	{
		if( static::$_OS !== OS_TYPE_LINUX ) 
		{
			return NULL;
		}
		foreach( static::$_workers as $worker ) 
		{
			if( empty($worker->name) ) 
			{
				$worker->name = "none";
			}
			$worker_name_length = strlen($worker->name);
			if( static::$_maxWorkerNameLength < $worker_name_length ) 
			{
				static::$_maxWorkerNameLength = $worker_name_length;
			}
			$socket_name_length = strlen($worker->getSocketName());
			if( static::$_maxSocketNameLength < $socket_name_length ) 
			{
				static::$_maxSocketNameLength = $socket_name_length;
			}
			if( empty($worker->user) ) 
			{
				$worker->user = static::getCurrentUser();
			}
			else 
			{
				if( posix_getuid() !== 0 && $worker->user != static::getCurrentUser() ) 
				{
					static::log("Warning: You must have the root privileges to change uid and gid.");
				}
			}
			$user_name_length = strlen($worker->user);
			if( static::$_maxUserNameLength < $user_name_length ) 
			{
				static::$_maxUserNameLength = $user_name_length;
			}
			if( !$worker->reusePort ) 
			{
				$worker->listen();
			}
		}
	}
	public static function getAllWorkers() 
	{
		return static::$_workers;
	}
	public static function getEventLoop() 
	{
		return static::$globalEvent;
	}
	protected static function initId() 
	{
		foreach( static::$_workers as $worker_id => $worker ) 
		{
			$new_id_map = array( );
			$worker->count = ($worker->count <= 0 ? 1 : $worker->count);
			for( $key = 0; $key < $worker->count; $key++ ) 
			{
				$new_id_map[$key] = (isset(static::$_idMap[$worker_id][$key]) ? static::$_idMap[$worker_id][$key] : 0);
			}
			static::$_idMap[$worker_id] = $new_id_map;
		}
	}
	protected static function getCurrentUser() 
	{
		$user_info = posix_getpwuid(posix_getuid());
		return $user_info["name"];
	}
	protected static function displayUI() 
	{
		global $argv;
		if( in_array("-q", $argv) ) 
		{
			return NULL;
		}
		if( static::$_OS !== OS_TYPE_LINUX ) 
		{
			static::safeEcho("----------------------- WORKERMAN -----------------------------\r\n");
			static::safeEcho("Workerman version:" . static::VERSION . "          PHP version:" . PHP_VERSION . "\r\n");
			static::safeEcho("------------------------ WORKERS -------------------------------\r\n");
			static::safeEcho("worker               listen                              processes status\r\n");
		}
		else 
		{
			static::safeEcho("<n>-----------------------<w> WORKERMAN </w>-----------------------------</n>\r\n");
			static::safeEcho("Workerman version:" . static::VERSION . "          PHP version:" . PHP_VERSION . "\r\n");
			static::safeEcho("------------------------<w> WORKERS </w>-------------------------------\r\n");
			static::safeEcho("<w>user</w>" . str_pad("", (static::$_maxUserNameLength + 2) - strlen("user")) . "<w>worker</w>" . str_pad("", (static::$_maxWorkerNameLength + 2) - strlen("worker")) . "<w>listen</w>" . str_pad("", (static::$_maxSocketNameLength + 2) - strlen("listen")) . "<w>processes</w> <w>status</w>\n");
			foreach( static::$_workers as $worker ) 
			{
				static::safeEcho(str_pad($worker->user, static::$_maxUserNameLength + 2) . str_pad($worker->name, static::$_maxWorkerNameLength + 2) . str_pad($worker->getSocketName(), static::$_maxSocketNameLength + 2) . str_pad(" " . $worker->count, 9) . " <g> [OK] </g>\n");
			}
			static::safeEcho("----------------------------------------------------------------\n");
			if( static::$daemonize ) 
			{
				static::safeEcho("Input \"php " . $argv[0] . " stop\" to stop. Start success.\n\n");
			}
			else 
			{
				static::safeEcho("Press Ctrl+C to stop. Start success.\n");
			}
		}
	}
	protected static function parseCommand() 
	{
		if( static::$_OS !== OS_TYPE_LINUX ) 
		{
			return NULL;
		}
		global $argv;
		$start_file = $argv[0];
		$available_commands = array( "start", "stop", "restart", "reload", "status", "connections" );
		$usage = "Usage: php yourfile <command> [mode]\nCommands: \nstart\t\tStart worker in DEBUG mode.\n\t\tUse mode -d to start in DAEMON mode.\nstop\t\tStop worker.\n\t\tUse mode -g to stop gracefully.\nrestart\t\tRestart workers.\n\t\tUse mode -d to start in DAEMON mode.\n\t\tUse mode -g to stop gracefully.\nreload\t\tReload codes.\n\t\tUse mode -g to reload gracefully.\nstatus\t\tGet worker status.\n\t\tUse mode -d to show live status.\nconnections\tGet worker connections.\n";
		if( !isset($argv[1]) || !in_array($argv[1], $available_commands) ) 
		{
			if( isset($argv[1]) ) 
			{
				static::safeEcho("Unknown command: " . $argv[1] . "\n");
			}
			exit( $usage );
		}
		$command = trim($argv[1]);
		$command2 = (isset($argv[2]) ? $argv[2] : "");
		$mode = "";
		if( $command === "start" ) 
		{
			if( $command2 === "-d" || static::$daemonize ) 
			{
				$mode = "in DAEMON mode";
			}
			else 
			{
				$mode = "in DEBUG mode";
			}
		}
		static::log("Workerman[" . $start_file . "] " . $command . " " . $mode);
		$master_pid = (is_file(static::$pidFile) ? file_get_contents(static::$pidFile) : 0);
		$master_is_alive = $master_pid && posix_kill($master_pid, 0) && posix_getpid() != $master_pid;
		if( $master_is_alive ) 
		{
			if( $command === "start" ) 
			{
				static::log("Workerman[" . $start_file . "] already running");
				exit();
			}
		}
		else 
		{
			if( $command !== "start" && $command !== "restart" ) 
			{
				static::log("Workerman[" . $start_file . "] not run");
				exit();
			}
		}
		switch( $command ) 
		{
			case "start": if( $command2 === "-d" ) 
			{
				static::$daemonize = true;
			}
			break;
			case "status": while( 1 ) 
			{
				if( is_file(static::$_statisticsFile) ) 
				{
					@unlink(static::$_statisticsFile);
				}
				posix_kill($master_pid, SIGUSR2);
				sleep(1);
				if( $command2 === "-d" ) 
				{
					static::safeEcho("\x1B[H\x1B[2J\x1B(B\x1B[m", true);
				}
				static::safeEcho(static::formatStatusData());
				if( $command2 !== "-d" ) 
				{
					exit( 0 );
				}
				static::safeEcho("\nPress Ctrl+C to quit.\n\n");
			}
			exit( 0 );
			case "connections": if( is_file(static::$_statisticsFile) && is_writable(static::$_statisticsFile) ) 
			{
				unlink(static::$_statisticsFile);
			}
			posix_kill($master_pid, SIGIO);
			usleep(500000);
			if( is_readable(static::$_statisticsFile) ) 
			{
				readfile(static::$_statisticsFile);
			}
			exit( 0 );
			case "restart": case "stop": if( $command2 === "-g" ) 
			{
				static::$_gracefulStop = true;
				$sig = SIGTERM;
				static::log("Workerman[" . $start_file . "] is gracefully stopping ...");
			}
			else 
			{
				static::$_gracefulStop = false;
				$sig = SIGINT;
				static::log("Workerman[" . $start_file . "] is stopping ...");
			}
			$master_pid and posix_kill($master_pid, $sig);
			$timeout = 5;
			$start_time = time();
			if( 1 ) 
			{
				$master_is_alive = $master_pid && posix_kill($master_pid, 0);
				if( $master_is_alive ) 
				{
					if( !static::$_gracefulStop && $timeout <= time() - $start_time ) 
					{
						static::log("Workerman[" . $start_file . "] stop fail");
						exit();
					}
					usleep(10000);
					continue;
				}
				static::log("Workerman[" . $start_file . "] stop success");
				if( $command === "stop" ) 
				{
					exit( 0 );
				}
				if( $command2 === "-d" ) 
				{
					static::$daemonize = true;
				}
				break;
			}
			break;
			case "reload": if( $command2 === "-g" ) 
			{
				$sig = SIGQUIT;
			}
			else 
			{
				$sig = SIGUSR1;
			}
			posix_kill($master_pid, $sig);
			exit();
			default: if( isset($command) ) 
			{
				static::safeEcho("Unknown command: " . $command . "\n");
			}
			exit( $usage );
		}
	}
	protected static function formatStatusData() 
	{
		static $total_request_cache = array( );
		if( !is_readable(static::$_statisticsFile) ) 
		{
			return "";
		}
		$info = file(static::$_statisticsFile, FILE_IGNORE_NEW_LINES);
		if( !$info ) 
		{
			return "";
		}
		$status_str = "";
		$current_total_request = array( );
		$worker_info = json_decode($info[0], true);
		ksort($worker_info, SORT_NUMERIC);
		unset($info[0]);
		$data_waiting_sort = array( );
		$read_process_status = false;
		$total_requests = 0;
		$total_qps = 0;
		$total_connections = 0;
		$total_fails = 0;
		$total_memory = 0;
		$total_timers = 0;
		$maxLen1 = static::$_maxSocketNameLength;
		$maxLen2 = static::$_maxWorkerNameLength;
		foreach( $info as $key => $value ) 
		{
			if( !$read_process_status ) 
			{
				$status_str .= $value . "\n";
				if( preg_match("/^pid.*?memory.*?listening/", $value) ) 
				{
					$read_process_status = true;
				}
				continue;
			}
			if( preg_match("/^[0-9]+/", $value, $pid_math) ) 
			{
				$pid = $pid_math[0];
				$data_waiting_sort[$pid] = $value;
				if( preg_match("/^\\S+?\\s+?(\\S+?)\\s+?(\\S+?)\\s+?(\\S+?)\\s+?(\\S+?)\\s+?(\\S+?)\\s+?(\\S+?)\\s+?(\\S+?)\\s+?/", $value, $match) ) 
				{
					$total_memory += intval(str_ireplace("M", "", $match[1]));
					$maxLen1 = max($maxLen1, strlen($match[2]));
					$maxLen2 = max($maxLen2, strlen($match[3]));
					$total_connections += intval($match[4]);
					$total_fails += intval($match[5]);
					$total_timers += intval($match[6]);
					$current_total_request[$pid] = $match[7];
					$total_requests += intval($match[7]);
				}
			}
		}
		foreach( $worker_info as $pid => $info ) 
		{
			if( !isset($data_waiting_sort[$pid]) ) 
			{
				$status_str .= (string) $pid . "\t" . str_pad("N/A", 7) . " " . str_pad($info["listen"], static::$_maxSocketNameLength) . " " . str_pad($info["name"], static::$_maxWorkerNameLength) . " " . str_pad("N/A", 11) . " " . str_pad("N/A", 9) . " " . str_pad("N/A", 7) . " " . str_pad("N/A", 13) . " N/A    [busy] \n";
				continue;
			}
			if( !isset($total_request_cache[$pid]) || !isset($current_total_request[$pid]) ) 
			{
				$qps = 0;
			}
			else 
			{
				$qps = $current_total_request[$pid] - $total_request_cache[$pid];
				$total_qps += $qps;
			}
			$status_str .= $data_waiting_sort[$pid] . " " . str_pad($qps, 6) . " [idle]\n";
		}
		$total_request_cache = $current_total_request;
		$status_str .= "----------------------------------------------PROCESS STATUS---------------------------------------------------\n";
		$status_str .= "Summary\t" . str_pad($total_memory . "M", 7) . " " . str_pad("-", $maxLen1) . " " . str_pad("-", $maxLen2) . " " . str_pad($total_connections, 11) . " " . str_pad($total_fails, 9) . " " . str_pad($total_timers, 7) . " " . str_pad($total_requests, 13) . " " . str_pad($total_qps, 6) . " [Summary] \n";
		return $status_str;
	}
	protected static function installSignal() 
	{
		if( static::$_OS !== OS_TYPE_LINUX ) 
		{
			return NULL;
		}
		pcntl_signal(SIGINT, array( "\\Workerman\\Worker", "signalHandler" ), false);
		pcntl_signal(SIGTERM, array( "\\Workerman\\Worker", "signalHandler" ), false);
		pcntl_signal(SIGUSR1, array( "\\Workerman\\Worker", "signalHandler" ), false);
		pcntl_signal(SIGQUIT, array( "\\Workerman\\Worker", "signalHandler" ), false);
		pcntl_signal(SIGUSR2, array( "\\Workerman\\Worker", "signalHandler" ), false);
		pcntl_signal(SIGIO, array( "\\Workerman\\Worker", "signalHandler" ), false);
		pcntl_signal(SIGPIPE, SIG_IGN, false);
	}
	protected static function reinstallSignal() 
	{
		if( static::$_OS !== OS_TYPE_LINUX ) 
		{
			return NULL;
		}
		pcntl_signal(SIGINT, SIG_IGN, false);
		pcntl_signal(SIGTERM, SIG_IGN, false);
		pcntl_signal(SIGUSR1, SIG_IGN, false);
		pcntl_signal(SIGQUIT, SIG_IGN, false);
		pcntl_signal(SIGUSR2, SIG_IGN, false);
		static::$globalEvent->add(SIGINT, Events\EventInterface::EV_SIGNAL, array( "\\Workerman\\Worker", "signalHandler" ));
		static::$globalEvent->add(SIGTERM, Events\EventInterface::EV_SIGNAL, array( "\\Workerman\\Worker", "signalHandler" ));
		static::$globalEvent->add(SIGUSR1, Events\EventInterface::EV_SIGNAL, array( "\\Workerman\\Worker", "signalHandler" ));
		static::$globalEvent->add(SIGQUIT, Events\EventInterface::EV_SIGNAL, array( "\\Workerman\\Worker", "signalHandler" ));
		static::$globalEvent->add(SIGUSR2, Events\EventInterface::EV_SIGNAL, array( "\\Workerman\\Worker", "signalHandler" ));
		static::$globalEvent->add(SIGIO, Events\EventInterface::EV_SIGNAL, array( "\\Workerman\\Worker", "signalHandler" ));
	}
	public static function signalHandler($signal) 
	{
		switch( $signal ) 
		{
			case SIGINT: static::$_gracefulStop = false;
			static::stopAll();
			break;
			case SIGTERM: static::$_gracefulStop = true;
			static::stopAll();
			break;
			case SIGQUIT: case SIGUSR1: if( $signal === SIGQUIT ) 
			{
				static::$_gracefulStop = true;
			}
			else 
			{
				static::$_gracefulStop = false;
			}
			static::$_pidsToRestart = static::getAllWorkerPids();
			static::reload();
			break;
			case SIGUSR2: static::writeStatisticsToStatusFile();
			break;
			case SIGIO: static::writeConnectionsStatisticsToStatusFile();
			break;
		}
	}
	protected static function daemonize() 
	{
		if( !static::$daemonize || static::$_OS !== OS_TYPE_LINUX ) 
		{
			return NULL;
		}
		umask(0);
		$pid = pcntl_fork();
		if( -1 === $pid ) 
		{
			throw new \Exception("fork fail");
		}
		if( 0 < $pid ) 
		{
			exit( 0 );
		}
		if( -1 === posix_setsid() ) 
		{
			throw new \Exception("setsid fail");
		}
		$pid = pcntl_fork();
		if( -1 === $pid ) 
		{
			throw new \Exception("fork fail");
		}
		if( 0 !== $pid ) 
		{
			exit( 0 );
		}
	}
	public static function resetStd() 
	{
		if( !static::$daemonize || static::$_OS !== OS_TYPE_LINUX ) 
		{
			return NULL;
		}
		global $STDOUT;
		global $STDERR;
		$handle = fopen(static::$stdoutFile, "a");
		if( $handle ) 
		{
			unset($handle);
			set_error_handler(function() 
			{
			}
			);
			fclose($STDOUT);
			fclose($STDERR);
			fclose(STDOUT);
			fclose(STDERR);
			$STDOUT = fopen(static::$stdoutFile, "a");
			$STDERR = fopen(static::$stdoutFile, "a");
			static::$_outputStream = null;
			static::outputStream($STDOUT);
			restore_error_handler();
		}
		else 
		{
			throw new \Exception("can not open stdoutFile " . static::$stdoutFile);
		}
	}
	protected static function saveMasterPid() 
	{
		if( static::$_OS !== OS_TYPE_LINUX ) 
		{
			return NULL;
		}
		static::$_masterPid = posix_getpid();
		if( false === file_put_contents(static::$pidFile, static::$_masterPid) ) 
		{
			throw new \Exception("can not save pid to " . static::$pidFile);
		}
	}
	protected static function getEventLoopName() 
	{
		if( static::$eventLoopClass ) 
		{
			return static::$eventLoopClass;
		}
		if( !class_exists("\\Swoole\\Event") ) 
		{
			unset(static::$_availableEventLoops["swoole"]);
		}
		$loop_name = "";
		foreach( static::$_availableEventLoops as $name => $class ) 
		{
			if( extension_loaded($name) ) 
			{
				$loop_name = $name;
				break;
			}
		}
		if( $loop_name ) 
		{
			if( interface_exists("\\React\\EventLoop\\LoopInterface") ) 
			{
				switch( $loop_name ) 
				{
					case "libevent": static::$eventLoopClass = "\\Workerman\\Events\\React\\ExtLibEventLoop";
					break;
					case "event": static::$eventLoopClass = "\\Workerman\\Events\\React\\ExtEventLoop";
					break;
					default: static::$eventLoopClass = "\\Workerman\\Events\\React\\StreamSelectLoop";
					break;
				}
			}
			else 
			{
				static::$eventLoopClass = static::$_availableEventLoops[$loop_name];
			}
		}
		else 
		{
			static::$eventLoopClass = (interface_exists("\\React\\EventLoop\\LoopInterface") ? "\\Workerman\\Events\\React\\StreamSelectLoop" : "\\Workerman\\Events\\Select");
		}
		return static::$eventLoopClass;
	}
	protected static function getAllWorkerPids() 
	{
		$pid_array = array( );
		foreach( static::$_pidMap as $worker_pid_array ) 
		{
			foreach( $worker_pid_array as $worker_pid ) 
			{
				$pid_array[$worker_pid] = $worker_pid;
			}
		}
		return $pid_array;
	}
	protected static function forkWorkers() 
	{
		if( static::$_OS === OS_TYPE_LINUX ) 
		{
			static::forkWorkersForLinux();
		}
		else 
		{
			static::forkWorkersForWindows();
		}
	}
	protected static function forkWorkersForLinux() 
	{
		foreach( static::$_workers as $worker ) 
		{
			if( static::$_status === static::STATUS_STARTING ) 
			{
				if( empty($worker->name) ) 
				{
					$worker->name = $worker->getSocketName();
				}
				$worker_name_length = strlen($worker->name);
				if( static::$_maxWorkerNameLength < $worker_name_length ) 
				{
					static::$_maxWorkerNameLength = $worker_name_length;
				}
			}
			while( count(static::$_pidMap[$worker->workerId]) < $worker->count ) 
			{
				static::forkOneWorkerForLinux($worker);
			}
		}
	}
	protected static function forkWorkersForWindows() 
	{
		$files = static::getStartFilesForWindows();
		global $argv;
		if( in_array("-q", $argv) || count($files) === 1 ) 
		{
			if( 1 < count(static::$_workers) ) 
			{
				static::safeEcho("@@@ Error: multi workers init in one php file are not support @@@\r\n");
				static::safeEcho("@@@ Please visit http://wiki.workerman.net/Multi_woker_for_win @@@\r\n");
			}
			else 
			{
				if( count(static::$_workers) <= 0 ) 
				{
					exit( "@@@no worker inited@@@\r\n\r\n" );
				}
			}
			reset(static::$_workers);
			$worker = current(static::$_workers);
			static::safeEcho(str_pad($worker->name, 21) . str_pad($worker->getSocketName(), 36) . str_pad($worker->count, 10) . "[ok]\n");
			$worker->listen();
			$worker->run();
			exit( "@@@child exit@@@\r\n" );
		}
		static::$globalEvent = new Events\Select();
		Lib\Timer::init(static::$globalEvent);
		foreach( $files as $start_file ) 
		{
			static::forkOneWorkerForWindows($start_file);
		}
	}
	public static function getStartFilesForWindows() 
	{
		global $argv;
		$files = array( );
		foreach( $argv as $file ) 
		{
			if( is_file($file) ) 
			{
				$files[$file] = $file;
			}
		}
		return $files;
	}
	public static function forkOneWorkerForWindows($start_file) 
	{
		$start_file = realpath($start_file);
		$std_file = sys_get_temp_dir() . "/" . str_replace(array( "/", "\\", ":" ), "_", $start_file) . ".out.txt";
		$descriptorspec = array( array( "pipe", "a" ), array( "file", $std_file, "w" ), array( "file", $std_file, "w" ) );
		$pipes = array( );
		$process = proc_open("php \"" . $start_file . "\" -q", $descriptorspec, $pipes);
		$std_handler = fopen($std_file, "a+");
		stream_set_blocking($std_handler, 0);
		if( empty($globalEvent) ) 
		{
			static::$globalEvent = new Events\Select();
			Lib\Timer::init(static::$globalEvent);
		}
		$timer_id = Lib\Timer::add(0.1, function() use ($std_handler) 
		{
			Worker::safeEcho(fread($std_handler, 65535));
		}
		);
		static::$_processForWindows[$start_file] = array( $process, $start_file, $timer_id );
	}
	public static function checkWorkerStatusForWindows() 
	{
		foreach( static::$_processForWindows as $process_data ) 
		{
			list($process, $start_file, $timer_id) = $process_data;
			$status = proc_get_status($process);
			if( isset($status["running"]) ) 
			{
				if( !$status["running"] ) 
				{
					static::safeEcho("process " . $start_file . " terminated and try to restart\n");
					Lib\Timer::del($timer_id);
					proc_close($process);
					static::forkOneWorkerForWindows($start_file);
				}
			}
			else 
			{
				static::safeEcho("proc_get_status fail\n");
			}
		}
	}
	protected static function forkOneWorkerForLinux($worker) 
	{
		$id = static::getId($worker->workerId, 0);
		if( $id === false ) 
		{
			return NULL;
		}
		$pid = pcntl_fork();
		if( 0 < $pid ) 
		{
			static::$_pidMap[$worker->workerId][$pid] = $pid;
			static::$_idMap[$worker->workerId][$id] = $pid;
		}
		else 
		{
			if( 0 === $pid ) 
			{
				if( $worker->reusePort ) 
				{
					$worker->listen();
				}
				if( static::$_status === static::STATUS_STARTING ) 
				{
					static::resetStd();
				}
				static::$_pidMap = array( );
				foreach( static::$_workers as $key => $one_worker ) 
				{
					if( $one_worker->workerId !== $worker->workerId ) 
					{
						$one_worker->unlisten();
						unset(static::$_workers[$key]);
					}
				}
				Lib\Timer::delAll();
				static::setProcessTitle("WorkerMan: worker process  " . $worker->name . " " . $worker->getSocketName());
				$worker->setUserAndGroup();
				$worker->id = $id;
				$worker->run();
				$err = new \Exception("event-loop exited");
				static::log($err);
				exit( 250 );
			}
			else 
			{
				throw new \Exception("forkOneWorker fail");
			}
		}
	}
	protected static function getId($worker_id, $pid) 
	{
		return array_search($pid, static::$_idMap[$worker_id]);
	}
	public function setUserAndGroup() 
	{
		$user_info = posix_getpwnam($this->user);
		if( !$user_info ) 
		{
			static::log("Warning: User " . $this->user . " not exsits");
		}
		else 
		{
			$uid = $user_info["uid"];
			if( $this->group ) 
			{
				$group_info = posix_getgrnam($this->group);
				if( !$group_info ) 
				{
					static::log("Warning: Group " . $this->group . " not exsits");
					return NULL;
				}
				$gid = $group_info["gid"];
			}
			else 
			{
				$gid = $user_info["gid"];
			}
			if( ($uid != posix_getuid() || $gid != posix_getgid()) && (!posix_setgid($gid) || !posix_initgroups($user_info["name"], $gid) || !posix_setuid($uid)) ) 
			{
				static::log("Warning: change gid or uid fail.");
			}
		}
	}
	protected static function setProcessTitle($title) 
	{
		set_error_handler(function() 
		{
		}
		);
		if( function_exists("cli_set_process_title") ) 
		{
			cli_set_process_title($title);
		}
		else 
		{
			if( extension_loaded("proctitle") && function_exists("setproctitle") ) 
			{
				setproctitle($title);
			}
		}
		restore_error_handler();
	}
	protected static function monitorWorkers() 
	{
		if( static::$_OS === OS_TYPE_LINUX ) 
		{
			static::monitorWorkersForLinux();
		}
		else 
		{
			static::monitorWorkersForWindows();
		}
	}
	protected static function monitorWorkersForLinux() 
	{
		static::$_status = static::STATUS_RUNNING;
		while( 1 ) 
		{
			pcntl_signal_dispatch();
			$status = 0;
			$pid = pcntl_wait($status, WUNTRACED);
			pcntl_signal_dispatch();
			if( 0 < $pid ) 
			{
				foreach( static::$_pidMap as $worker_id => $worker_pid_array ) 
				{
					if( isset($worker_pid_array[$pid]) ) 
					{
						$worker = static::$_workers[$worker_id];
						if( $status !== 0 ) 
						{
							static::log("worker[" . $worker->name . ":" . $pid . "] exit with status " . $status);
						}
						if( !isset(static::$_globalStatistics["worker_exit_info"][$worker_id][$status]) ) 
						{
							static::$_globalStatistics["worker_exit_info"][$worker_id][$status] = 0;
						}
						static::$_globalStatistics["worker_exit_info"][$worker_id][$status]++;
						unset(static::$_pidMap[$worker_id][$pid]);
						$id = static::getId($worker_id, $pid);
						static::$_idMap[$worker_id][$id] = 0;
						break;
					}
				}
				if( static::$_status !== static::STATUS_SHUTDOWN ) 
				{
					static::forkWorkers();
					if( isset(static::$_pidsToRestart[$pid]) ) 
					{
						unset(static::$_pidsToRestart[$pid]);
						static::reload();
					}
				}
				else 
				{
					if( !static::getAllWorkerPids() ) 
					{
						static::exitAndClearAll();
					}
				}
			}
			else 
			{
				if( static::$_status === static::STATUS_SHUTDOWN && !static::getAllWorkerPids() ) 
				{
					static::exitAndClearAll();
				}
			}
		}
	}
	protected static function monitorWorkersForWindows() 
	{
		Lib\Timer::add(1, "\\Workerman\\Worker::checkWorkerStatusForWindows");
		static::$globalEvent->loop();
	}
	protected static function exitAndClearAll() 
	{
		foreach( static::$_workers as $worker ) 
		{
			$socket_name = $worker->getSocketName();
			if( $worker->transport === "unix" && $socket_name ) 
			{
				list(, $address) = explode(":", $socket_name, 2);
				@unlink($address);
			}
		}
		@unlink(static::$pidFile);
		static::log("Workerman[" . basename(static::$_startFile) . "] has been stopped");
		if( static::$onMasterStop ) 
		{
			call_user_func(static::$onMasterStop);
		}
		exit( 0 );
	}
	protected static function reload() 
	{
		if( static::$_masterPid === posix_getpid() ) 
		{
			if( static::$_status !== static::STATUS_RELOADING && static::$_status !== static::STATUS_SHUTDOWN ) 
			{
				static::log("Workerman[" . basename(static::$_startFile) . "] reloading");
				static::$_status = static::STATUS_RELOADING;
				if( static::$onMasterReload ) 
				{
					try 
					{
						call_user_func(static::$onMasterReload);
					}
					catch( \Exception $e ) 
					{
						static::log($e);
						exit( 250 );
					}
					catch( \Error $e ) 
					{
						static::log($e);
						exit( 250 );
					}
					static::initId();
				}
			}
			if( static::$_gracefulStop ) 
			{
				$sig = SIGQUIT;
			}
			else 
			{
				$sig = SIGUSR1;
			}
			$reloadable_pid_array = array( );
			foreach( static::$_pidMap as $worker_id => $worker_pid_array ) 
			{
				$worker = static::$_workers[$worker_id];
				if( $worker->reloadable ) 
				{
					foreach( $worker_pid_array as $pid ) 
					{
						$reloadable_pid_array[$pid] = $pid;
					}
				}
				else 
				{
					foreach( $worker_pid_array as $pid ) 
					{
						posix_kill($pid, $sig);
					}
				}
			}
			static::$_pidsToRestart = array_intersect(static::$_pidsToRestart, $reloadable_pid_array);
			if( empty($_pidsToRestart) ) 
			{
				if( static::$_status !== static::STATUS_SHUTDOWN ) 
				{
					static::$_status = static::STATUS_RUNNING;
				}
				return NULL;
			}
			$one_worker_pid = current(static::$_pidsToRestart);
			posix_kill($one_worker_pid, $sig);
			if( !static::$_gracefulStop ) 
			{
				Lib\Timer::add(static::KILL_WORKER_TIMER_TIME, "posix_kill", array( $one_worker_pid, SIGKILL ), false);
			}
		}
		else 
		{
			reset(static::$_workers);
			$worker = current(static::$_workers);
			if( $worker->onWorkerReload ) 
			{
				try 
				{
					call_user_func($worker->onWorkerReload, $worker);
				}
				catch( \Exception $e ) 
				{
					static::log($e);
					exit( 250 );
				}
				catch( \Error $e ) 
				{
					static::log($e);
					exit( 250 );
				}
			}
			if( $worker->reloadable ) 
			{
				static::stopAll();
			}
		}
	}
	public static function stopAll() 
	{
		static::$_status = static::STATUS_SHUTDOWN;
		if( static::$_masterPid === posix_getpid() ) 
		{
			static::log("Workerman[" . basename(static::$_startFile) . "] stopping ...");
			$worker_pid_array = static::getAllWorkerPids();
			if( static::$_gracefulStop ) 
			{
				$sig = SIGTERM;
			}
			else 
			{
				$sig = SIGINT;
			}
			foreach( $worker_pid_array as $worker_pid ) 
			{
				posix_kill($worker_pid, $sig);
				if( !static::$_gracefulStop ) 
				{
					Lib\Timer::add(static::KILL_WORKER_TIMER_TIME, "posix_kill", array( $worker_pid, SIGKILL ), false);
				}
			}
			Lib\Timer::add(1, "\\Workerman\\Worker::checkIfChildRunning");
			if( is_file(static::$_statisticsFile) ) 
			{
				@unlink(static::$_statisticsFile);
			}
		}
		else 
		{
			foreach( static::$_workers as $worker ) 
			{
				if( !$worker->stopping ) 
				{
					$worker->stop();
					$worker->stopping = true;
				}
			}
			if( !static::$_gracefulStop || Connection\ConnectionInterface::$statistics["connection_count"] <= 0 ) 
			{
				static::$_workers = array( );
				if( static::$globalEvent ) 
				{
					static::$globalEvent->destroy();
				}
				exit( 0 );
			}
		}
	}
	public static function checkIfChildRunning() 
	{
		foreach( static::$_pidMap as $worker_id => $worker_pid_array ) 
		{
			foreach( $worker_pid_array as $pid => $worker_pid ) 
			{
				if( !posix_kill($pid, 0) ) 
				{
					unset(static::$_pidMap[$worker_id][$pid]);
				}
			}
		}
	}
	public static function getStatus() 
	{
		return static::$_status;
	}
	public static function getGracefulStop() 
	{
		return static::$_gracefulStop;
	}
	protected static function writeStatisticsToStatusFile() 
	{
		if( static::$_masterPid === posix_getpid() ) 
		{
			$all_worker_info = array( );
			foreach( static::$_pidMap as $worker_id => $pid_array ) 
			{
				$worker = static::$_workers[$worker_id];
				foreach( $pid_array as $pid ) 
				{
					$all_worker_info[$pid] = array( "name" => $worker->name, "listen" => $worker->getSocketName() );
				}
			}
			file_put_contents(static::$_statisticsFile, json_encode($all_worker_info) . "\n", FILE_APPEND);
			$loadavg = (function_exists("sys_getloadavg") ? array_map("round", sys_getloadavg(), array( 2 )) : array( "-", "-", "-" ));
			file_put_contents(static::$_statisticsFile, "----------------------------------------------GLOBAL STATUS----------------------------------------------------\n", FILE_APPEND);
			file_put_contents(static::$_statisticsFile, "Workerman version:" . static::VERSION . "          PHP version:" . PHP_VERSION . "\n", FILE_APPEND);
			file_put_contents(static::$_statisticsFile, "start time:" . date("Y-m-d H:i:s", static::$_globalStatistics["start_timestamp"]) . "   run " . floor((time() - static::$_globalStatistics["start_timestamp"]) / (24 * 60 * 60)) . " days " . floor((time() - static::$_globalStatistics["start_timestamp"]) % (24 * 60 * 60) / (60 * 60)) . " hours   \n", FILE_APPEND);
			$load_str = "load average: " . implode(", ", $loadavg);
			file_put_contents(static::$_statisticsFile, str_pad($load_str, 33) . "event-loop:" . static::getEventLoopName() . "\n", FILE_APPEND);
			file_put_contents(static::$_statisticsFile, count(static::$_pidMap) . " workers       " . count(static::getAllWorkerPids()) . " processes\n", FILE_APPEND);
			file_put_contents(static::$_statisticsFile, str_pad("worker_name", static::$_maxWorkerNameLength) . " exit_status      exit_count\n", FILE_APPEND);
			foreach( static::$_pidMap as $worker_id => $worker_pid_array ) 
			{
				$worker = static::$_workers[$worker_id];
				if( isset(static::$_globalStatistics["worker_exit_info"][$worker_id]) ) 
				{
					foreach( static::$_globalStatistics["worker_exit_info"][$worker_id] as $worker_exit_status => $worker_exit_count ) 
					{
						file_put_contents(static::$_statisticsFile, str_pad($worker->name, static::$_maxWorkerNameLength) . " " . str_pad($worker_exit_status, 16) . " " . $worker_exit_count . "\n", FILE_APPEND);
					}
				}
				else 
				{
					file_put_contents(static::$_statisticsFile, str_pad($worker->name, static::$_maxWorkerNameLength) . " " . str_pad(0, 16) . " 0\n", FILE_APPEND);
				}
			}
			file_put_contents(static::$_statisticsFile, "----------------------------------------------PROCESS STATUS---------------------------------------------------\n", FILE_APPEND);
			file_put_contents(static::$_statisticsFile, "pid\tmemory  " . str_pad("listening", static::$_maxSocketNameLength) . " " . str_pad("worker_name", static::$_maxWorkerNameLength) . " connections " . str_pad("send_fail", 9) . " " . str_pad("timers", 8) . str_pad("total_request", 13) . " qps    status\n", FILE_APPEND);
			chmod(static::$_statisticsFile, 466);
			foreach( static::getAllWorkerPids() as $worker_pid ) 
			{
				posix_kill($worker_pid, SIGUSR2);
			}
			return NULL;
		}
		else 
		{
			reset(static::$_workers);
			$worker = current(static::$_workers);
			$worker_status_str = posix_getpid() . "\t" . str_pad(round(memory_get_usage(true) / (1024 * 1024), 2) . "M", 7) . " " . str_pad($worker->getSocketName(), static::$_maxSocketNameLength) . " " . str_pad(($worker->name === $worker->getSocketName() ? "none" : $worker->name), static::$_maxWorkerNameLength) . " ";
			$worker_status_str .= str_pad(Connection\ConnectionInterface::$statistics["connection_count"], 11) . " " . str_pad(Connection\ConnectionInterface::$statistics["send_fail"], 9) . " " . str_pad(static::$globalEvent->getTimerCount(), 7) . " " . str_pad(Connection\ConnectionInterface::$statistics["total_request"], 13) . "\n";
			file_put_contents(static::$_statisticsFile, $worker_status_str, FILE_APPEND);
		}
	}
	protected static function writeConnectionsStatisticsToStatusFile() 
	{
		if( static::$_masterPid === posix_getpid() ) 
		{
			file_put_contents(static::$_statisticsFile, "--------------------------------------------------------------------- WORKERMAN CONNECTION STATUS --------------------------------------------------------------------------------\n", FILE_APPEND);
			file_put_contents(static::$_statisticsFile, "PID      Worker          CID       Trans   Protocol        ipv4   ipv6   Recv-Q       Send-Q       Bytes-R      Bytes-W       Status         Local Address          Foreign Address\n", FILE_APPEND);
			chmod(static::$_statisticsFile, 466);
			foreach( static::getAllWorkerPids() as $worker_pid ) 
			{
				posix_kill($worker_pid, SIGIO);
			}
			return NULL;
		}
		else 
		{
			$bytes_format = function($bytes) 
			{
				if( 1024 * 1024 * 1024 * 1024 < $bytes ) 
				{
					return round($bytes / (1024 * 1024 * 1024 * 1024), 1) . "TB";
				}
				if( 1024 * 1024 * 1024 < $bytes ) 
				{
					return round($bytes / (1024 * 1024 * 1024), 1) . "GB";
				}
				if( 1024 * 1024 < $bytes ) 
				{
					return round($bytes / (1024 * 1024), 1) . "MB";
				}
				if( 1024 < $bytes ) 
				{
					return round($bytes / 1024, 1) . "KB";
				}
				return $bytes . "B";
			}
			;
			$pid = posix_getpid();
			$str = "";
			reset(static::$_workers);
			$current_worker = current(static::$_workers);
			$default_worker_name = $current_worker->name;
			foreach( Connection\TcpConnection::$connections as $connection ) 
			{
				$transport = $connection->transport;
				$ipv4 = ($connection->isIpV4() ? " 1" : " 0");
				$ipv6 = ($connection->isIpV6() ? " 1" : " 0");
				$recv_q = $bytes_format($connection->getRecvBufferQueueSize());
				$send_q = $bytes_format($connection->getSendBufferQueueSize());
				$local_address = trim($connection->getLocalAddress());
				$remote_address = trim($connection->getRemoteAddress());
				$state = $connection->getStatus(false);
				$bytes_read = $bytes_format($connection->bytesRead);
				$bytes_written = $bytes_format($connection->bytesWritten);
				$id = $connection->id;
				$protocol = ($connection->protocol ? $connection->protocol : $connection->transport);
				$pos = strrpos($protocol, "\\");
				if( $pos ) 
				{
					$protocol = substr($protocol, $pos + 1);
				}
				if( 15 < strlen($protocol) ) 
				{
					$protocol = substr($protocol, 0, 13) . "..";
				}
				$worker_name = (isset($connection->worker) ? $connection->worker->name : $default_worker_name);
				if( 14 < strlen($worker_name) ) 
				{
					$worker_name = substr($worker_name, 0, 12) . "..";
				}
				$str .= str_pad($pid, 9) . str_pad($worker_name, 16) . str_pad($id, 10) . str_pad($transport, 8) . str_pad($protocol, 16) . str_pad($ipv4, 7) . str_pad($ipv6, 7) . str_pad($recv_q, 13) . str_pad($send_q, 13) . str_pad($bytes_read, 13) . str_pad($bytes_written, 13) . " " . str_pad($state, 14) . " " . str_pad($local_address, 22) . " " . str_pad($remote_address, 22) . "\n";
			}
			if( $str ) 
			{
				file_put_contents(static::$_statisticsFile, $str, FILE_APPEND);
			}
		}
	}
	public static function checkErrors() 
	{
		if( static::STATUS_SHUTDOWN != static::$_status ) 
		{
			$error_msg = (static::$_OS === OS_TYPE_LINUX ? "Worker[" . posix_getpid() . "] process terminated" : "Worker process terminated");
			$errors = error_get_last();
			if( $errors && ($errors["type"] === E_ERROR || $errors["type"] === E_PARSE || $errors["type"] === E_CORE_ERROR || $errors["type"] === E_COMPILE_ERROR || $errors["type"] === E_RECOVERABLE_ERROR) ) 
			{
				$error_msg .= " with ERROR: " . static::getErrorType($errors["type"]) . " \"" . $errors["message"] . " in " . $errors["file"] . " on line " . $errors["line"] . "\"";
			}
			static::log($error_msg);
		}
	}
	protected static function getErrorType($type) 
	{
		switch( $type ) 
		{
			case E_ERROR: return "E_ERROR";
			case E_WARNING: return "E_WARNING";
			case E_PARSE: return "E_PARSE";
			case E_NOTICE: return "E_NOTICE";
			case E_CORE_ERROR: return "E_CORE_ERROR";
			case E_CORE_WARNING: return "E_CORE_WARNING";
			case E_COMPILE_ERROR: return "E_COMPILE_ERROR";
			case E_COMPILE_WARNING: return "E_COMPILE_WARNING";
			case E_USER_ERROR: return "E_USER_ERROR";
			case E_USER_WARNING: return "E_USER_WARNING";
			case E_USER_NOTICE: return "E_USER_NOTICE";
			case E_STRICT: return "E_STRICT";
			case E_RECOVERABLE_ERROR: return "E_RECOVERABLE_ERROR";
			case E_DEPRECATED: return "E_DEPRECATED";
			case E_USER_DEPRECATED: return "E_USER_DEPRECATED";
		}
		return "";
	}
	public static function log($msg) 
	{
		$msg = $msg . "\n";
		if( !static::$daemonize ) 
		{
			static::safeEcho($msg);
		}
		file_put_contents((string) static::$logFile, date("Y-m-d H:i:s") . " " . "pid:" . ((static::$_OS === OS_TYPE_LINUX ? posix_getpid() : 1)) . " " . $msg, FILE_APPEND | LOCK_EX);
	}
	public static function safeEcho($msg, $decorated = false) 
	{
		$stream = static::outputStream();
		if( !$stream ) 
		{
			return false;
		}
		if( !$decorated ) 
		{
			$line = $white = $green = $end = "";
			if( static::$_outputDecorated ) 
			{
				$line = "\x1B[1A\n\x1B[K";
				$white = "\x1B[47;30m";
				$green = "\x1B[32;40m";
				$end = "\x1B[0m";
			}
			$msg = str_replace(array( "<n>", "<w>", "<g>" ), array( $line, $white, $green ), $msg);
			$msg = str_replace(array( "</n>", "</w>", "</g>" ), $end, $msg);
		}
		else 
		{
			if( !static::$_outputDecorated ) 
			{
				return false;
			}
		}
		fwrite($stream, $msg);
		fflush($stream);
		return true;
	}
	private static function outputStream($stream = NULL) 
	{
		if( !$stream ) 
		{
			$stream = (static::$_outputStream ? static::$_outputStream : STDOUT);
		}
		if( !$stream || !is_resource($stream) || "stream" !== get_resource_type($stream) ) 
		{
			return false;
		}
		$stat = fstat($stream);
		if( ($stat["mode"] & 61440) === 32768 ) 
		{
			static::$_outputDecorated = false;
		}
		else 
		{
			static::$_outputDecorated = static::$_OS === OS_TYPE_LINUX && function_exists("posix_isatty") && posix_isatty($stream);
		}
		return static::$_outputStream = $stream;
	}
	public function __construct($socket_name = "", $context_option = array( )) 
	{
		$this->workerId = spl_object_hash($this);
		static::$_workers[$this->workerId] = $this;
		static::$_pidMap[$this->workerId] = array( );
		$backtrace = debug_backtrace();
		$this->_autoloadRootPath = dirname($backtrace[0]["file"]);
		if( $socket_name ) 
		{
			$this->_socketName = $socket_name;
			if( !isset($context_option["socket"]["backlog"]) ) 
			{
				$context_option["socket"]["backlog"] = static::DEFAULT_BACKLOG;
			}
			$this->_context = stream_context_create($context_option);
		}
	}
	public function listen() 
	{
		if( !$this->_socketName ) 
		{
			return NULL;
		}
		Autoloader::setRootPath($this->_autoloadRootPath);
		if( !$this->_mainSocket ) 
		{
			list($scheme, $address) = explode(":", $this->_socketName, 2);
			if( !isset(static::$_builtinTransports[$scheme]) ) 
			{
				$scheme = ucfirst($scheme);
				$this->protocol = (substr($scheme, 0, 1) === "\\" ? $scheme : "\\Protocols\\" . $scheme);
				if( !class_exists($this->protocol) ) 
				{
					$this->protocol = "\\Workerman\\Protocols\\" . $scheme;
					if( !class_exists($this->protocol) ) 
					{
						throw new \Exception("class \\Protocols\\" . $scheme . " not exist");
					}
				}
				if( !isset(static::$_builtinTransports[$this->transport]) ) 
				{
					throw new \Exception("Bad worker->transport " . var_export($this->transport, true));
				}
			}
			else 
			{
				$this->transport = $scheme;
			}
			$local_socket = static::$_builtinTransports[$this->transport] . ":" . $address;
			$flags = ($this->transport === "udp" ? STREAM_SERVER_BIND : STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
			$errno = 0;
			$errmsg = "";
			if( $this->reusePort ) 
			{
				stream_context_set_option($this->_context, "socket", "so_reuseport", 1);
			}
			$this->_mainSocket = stream_socket_server($local_socket, $errno, $errmsg, $flags, $this->_context);
			if( !$this->_mainSocket ) 
			{
				throw new \Exception($errmsg);
			}
			if( $this->transport === "ssl" ) 
			{
				stream_socket_enable_crypto($this->_mainSocket, false);
			}
			else 
			{
				if( $this->transport === "unix" ) 
				{
					$socketFile = substr($address, 2);
					if( $this->user ) 
					{
						chown($socketFile, $this->user);
					}
					if( $this->group ) 
					{
						chgrp($socketFile, $this->group);
					}
				}
			}
			if( function_exists("socket_import_stream") && static::$_builtinTransports[$this->transport] === "tcp" ) 
			{
				set_error_handler(function() 
				{
				}
				);
				$socket = socket_import_stream($this->_mainSocket);
				socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
				socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
				restore_error_handler();
			}
			stream_set_blocking($this->_mainSocket, 0);
		}
		$this->resumeAccept();
	}
	public function unlisten() 
	{
		$this->pauseAccept();
		if( $this->_mainSocket ) 
		{
			set_error_handler(function() 
			{
			}
			);
			fclose($this->_mainSocket);
			restore_error_handler();
			$this->_mainSocket = null;
		}
	}
	public function pauseAccept() 
	{
		if( static::$globalEvent && false === $this->_pauseAccept && $this->_mainSocket ) 
		{
			static::$globalEvent->del($this->_mainSocket, Events\EventInterface::EV_READ);
			$this->_pauseAccept = true;
		}
	}
	public function resumeAccept() 
	{
		if( static::$globalEvent && true === $this->_pauseAccept && $this->_mainSocket ) 
		{
			if( $this->transport !== "udp" ) 
			{
				static::$globalEvent->add($this->_mainSocket, Events\EventInterface::EV_READ, array( $this, "acceptConnection" ));
			}
			else 
			{
				static::$globalEvent->add($this->_mainSocket, Events\EventInterface::EV_READ, array( $this, "acceptUdpConnection" ));
			}
			$this->_pauseAccept = false;
		}
	}
	public function getSocketName() 
	{
		return ($this->_socketName ? lcfirst($this->_socketName) : "none");
	}
	public function run() 
	{
		static::$_status = static::STATUS_RUNNING;
		register_shutdown_function(array( "\\Workerman\\Worker", "checkErrors" ));
		Autoloader::setRootPath($this->_autoloadRootPath);
		if( !static::$globalEvent ) 
		{
			$event_loop_class = static::getEventLoopName();
			static::$globalEvent = new $event_loop_class();
			$this->resumeAccept();
		}
		static::reinstallSignal();
		Lib\Timer::init(static::$globalEvent);
		if( empty($this->onMessage) ) 
		{
			$this->onMessage = function() 
			{
			}
			;
		}
		restore_error_handler();
		if( $this->onWorkerStart ) 
		{
			try 
			{
				call_user_func($this->onWorkerStart, $this);
			}
			catch( \Exception $e ) 
			{
				static::log($e);
				sleep(1);
				exit( 250 );
			}
			catch( \Error $e ) 
			{
				static::log($e);
				sleep(1);
				exit( 250 );
			}
		}
		static::$globalEvent->loop();
	}
	public function stop() 
	{
		if( $this->onWorkerStop ) 
		{
			try 
			{
				call_user_func($this->onWorkerStop, $this);
			}
			catch( \Exception $e ) 
			{
				static::log($e);
				exit( 250 );
			}
			catch( \Error $e ) 
			{
				static::log($e);
				exit( 250 );
			}
		}
		$this->unlisten();
		if( !static::$_gracefulStop ) 
		{
			foreach( $this->connections as $connection ) 
			{
				$connection->close();
			}
		}
		$this->onMessage = $this->onClose = $this->onError = $this->onBufferDrain = $this->onBufferFull = null;
	}
	public function acceptConnection($socket) 
	{
		set_error_handler(function() 
		{
		}
		);
		$new_socket = stream_socket_accept($socket, 0, $remote_address);
		restore_error_handler();
		if( !$new_socket ) 
		{
			return NULL;
		}
		$connection = new Connection\TcpConnection($new_socket, $remote_address);
		$this->connections[$connection->id] = $connection;
		$connection->worker = $this;
		$connection->protocol = $this->protocol;
		$connection->transport = $this->transport;
		$connection->onMessage = $this->onMessage;
		$connection->onClose = $this->onClose;
		$connection->onError = $this->onError;
		$connection->onBufferDrain = $this->onBufferDrain;
		$connection->onBufferFull = $this->onBufferFull;
		if( $this->onConnect ) 
		{
			try 
			{
				call_user_func($this->onConnect, $connection);
			}
			catch( \Exception $e ) 
			{
				static::log($e);
				exit( 250 );
			}
			catch( \Error $e ) 
			{
				static::log($e);
				exit( 250 );
			}
		}
	}
	public function acceptUdpConnection($socket) 
	{
		set_error_handler(function() 
		{
		}
		);
		$recv_buffer = stream_socket_recvfrom($socket, static::MAX_UDP_PACKAGE_SIZE, 0, $remote_address);
		restore_error_handler();
		if( false === $recv_buffer || empty($remote_address) ) 
		{
			return false;
		}
		$connection = new Connection\UdpConnection($socket, $remote_address);
		$connection->protocol = $this->protocol;
		if( $this->onMessage ) 
		{
			try 
			{
				if( $this->protocol !== null ) 
				{
					$parser = $this->protocol;
					$recv_buffer = $parser::decode($recv_buffer, $connection);
					if( $recv_buffer === false ) 
					{
						return true;
					}
				}
				Connection\ConnectionInterface::$statistics["total_request"]++;
				call_user_func($this->onMessage, $connection, $recv_buffer);
			}
			catch( \Exception $e ) 
			{
				static::log($e);
				exit( 250 );
			}
			catch( \Error $e ) 
			{
				static::log($e);
				exit( 250 );
			}
		}
		return true;
	}
}
?>