<?php  namespace Workerman\Connection;
class AsyncTcpConnection extends TcpConnection 
{
	public $onConnect = NULL;
	public $transport = "tcp";
	protected $_status = self::STATUS_INITIAL;
	protected $_remoteHost = "";
	protected $_remotePort = 80;
	protected $_connectStartTime = 0;
	protected $_remoteURI = "";
	protected $_contextOption = NULL;
	protected $_reconnectTimer = NULL;
	protected static $_builtinTransports = array( "tcp" => "tcp", "udp" => "udp", "unix" => "unix", "ssl" => "ssl", "sslv2" => "sslv2", "sslv3" => "sslv3", "tls" => "tls" );
	public function __construct($remote_address, $context_option = NULL) 
	{
		$address_info = parse_url($remote_address);
		if( !$address_info ) 
		{
			list($scheme, $this->_remoteAddress) = explode(":", $remote_address, 2);
			if( !$this->_remoteAddress ) 
			{
				\Workerman\Worker::safeEcho(new \Exception("bad remote_address"));
			}
		}
		else 
		{
			if( !isset($address_info["port"]) ) 
			{
				$address_info["port"] = 80;
			}
			if( !isset($address_info["path"]) ) 
			{
				$address_info["path"] = "/";
			}
			if( !isset($address_info["query"]) ) 
			{
				$address_info["query"] = "";
			}
			else 
			{
				$address_info["query"] = "?" . $address_info["query"];
			}
			$this->_remoteAddress = (string) $address_info["host"] . ":" . $address_info["port"];
			$this->_remoteHost = $address_info["host"];
			$this->_remotePort = $address_info["port"];
			$this->_remoteURI = (string) $address_info["path"] . $address_info["query"];
			$scheme = (isset($address_info["scheme"]) ? $address_info["scheme"] : "tcp");
		}
		$this->id = $this->_id = self::$_idRecorder++;
		if( PHP_INT_MAX === self::$_idRecorder ) 
		{
			self::$_idRecorder = 0;
		}
		if( !isset(self::$_builtinTransports[$scheme]) ) 
		{
			$scheme = ucfirst($scheme);
			$this->protocol = "\\Protocols\\" . $scheme;
			if( !class_exists($this->protocol) ) 
			{
				$this->protocol = "\\Workerman\\Protocols\\" . $scheme;
				if( !class_exists($this->protocol) ) 
				{
					throw new \Exception("class \\Protocols\\" . $scheme . " not exist");
				}
			}
		}
		else 
		{
			$this->transport = self::$_builtinTransports[$scheme];
		}
		self::$statistics["connection_count"]++;
		$this->maxSendBufferSize = self::$defaultMaxSendBufferSize;
		$this->_contextOption = $context_option;
		static::$connections[$this->_id] = $this;
	}
	public function connect() 
	{
		if( $this->_status !== self::STATUS_INITIAL && $this->_status !== self::STATUS_CLOSING && $this->_status !== self::STATUS_CLOSED ) 
		{
			return NULL;
		}
		$this->_status = self::STATUS_CONNECTING;
		$this->_connectStartTime = microtime(true);
		if( $this->transport !== "unix" ) 
		{
			if( $this->_contextOption ) 
			{
				$context = stream_context_create($this->_contextOption);
				$this->_socket = stream_socket_client("tcp://" . $this->_remoteHost . ":" . $this->_remotePort, $errno, $errstr, 0, STREAM_CLIENT_ASYNC_CONNECT, $context);
			}
			else 
			{
				$this->_socket = stream_socket_client("tcp://" . $this->_remoteHost . ":" . $this->_remotePort, $errno, $errstr, 0, STREAM_CLIENT_ASYNC_CONNECT);
			}
		}
		else 
		{
			$this->_socket = stream_socket_client((string) $this->transport . "://" . $this->_remoteAddress, $errno, $errstr, 0, STREAM_CLIENT_ASYNC_CONNECT);
		}
		if( !$this->_socket ) 
		{
			$this->emitError(WORKERMAN_CONNECT_FAIL, $errstr);
			if( $this->_status === self::STATUS_CLOSING ) 
			{
				$this->destroy();
			}
			if( $this->_status === self::STATUS_CLOSED ) 
			{
				$this->onConnect = null;
			}
		}
		else 
		{
			\Workerman\Worker::$globalEvent->add($this->_socket, \Workerman\Events\EventInterface::EV_WRITE, array( $this, "checkConnection" ));
			if( DIRECTORY_SEPARATOR === "\\" ) 
			{
				\Workerman\Worker::$globalEvent->add($this->_socket, \Workerman\Events\EventInterface::EV_EXCEPT, array( $this, "checkConnection" ));
			}
		}
	}
	public function reconnect($after = 0) 
	{
		$this->_status = self::STATUS_INITIAL;
		static::$connections[$this->_id] = $this;
		if( $this->_reconnectTimer ) 
		{
			\Workerman\Lib\Timer::del($this->_reconnectTimer);
		}
		if( 0 < $after ) 
		{
			$this->_reconnectTimer = \Workerman\Lib\Timer::add($after, array( $this, "connect" ), null, false);
		}
		else 
		{
			$this->connect();
		}
	}
	public function cancelReconnect() 
	{
		if( $this->_reconnectTimer ) 
		{
			\Workerman\Lib\Timer::del($this->_reconnectTimer);
		}
	}
	public function getRemoteHost() 
	{
		return $this->_remoteHost;
	}
	public function getRemoteURI() 
	{
		return $this->_remoteURI;
	}
	protected function emitError($code, $msg) 
	{
		$this->_status = self::STATUS_CLOSING;
		if( $this->onError ) 
		{
			try 
			{
				call_user_func($this->onError, $this, $code, $msg);
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
	}
	public function checkConnection() 
	{
		if( $this->_status != self::STATUS_CONNECTING ) 
		{
			return NULL;
		}
		if( DIRECTORY_SEPARATOR === "\\" ) 
		{
			\Workerman\Worker::$globalEvent->del($this->_socket, \Workerman\Events\EventInterface::EV_EXCEPT);
		}
		if( $address = stream_socket_get_name($this->_socket, true) ) 
		{
			stream_set_blocking($this->_socket, 0);
			if( function_exists("stream_set_read_buffer") ) 
			{
				stream_set_read_buffer($this->_socket, 0);
			}
			if( function_exists("socket_import_stream") && $this->transport === "tcp" ) 
			{
				$raw_socket = socket_import_stream($this->_socket);
				socket_set_option($raw_socket, SOL_SOCKET, SO_KEEPALIVE, 1);
				socket_set_option($raw_socket, SOL_TCP, TCP_NODELAY, 1);
			}
			\Workerman\Worker::$globalEvent->del($this->_socket, \Workerman\Events\EventInterface::EV_WRITE);
			if( $this->transport === "ssl" ) 
			{
				$this->_sslHandshakeCompleted = $this->doSslHandshake($this->_socket);
			}
			else 
			{
				if( $this->_sendBuffer ) 
				{
					\Workerman\Worker::$globalEvent->add($this->_socket, \Workerman\Events\EventInterface::EV_WRITE, array( $this, "baseWrite" ));
				}
			}
			\Workerman\Worker::$globalEvent->add($this->_socket, \Workerman\Events\EventInterface::EV_READ, array( $this, "baseRead" ));
			$this->_status = self::STATUS_ESTABLISHED;
			$this->_remoteAddress = $address;
			if( $this->onConnect ) 
			{
				try 
				{
					call_user_func($this->onConnect, $this);
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
			if( method_exists($this->protocol, "onConnect") ) 
			{
				try 
				{
					call_user_func(array( $this->protocol, "onConnect" ), $this);
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
		}
		else 
		{
			$this->emitError(WORKERMAN_CONNECT_FAIL, "connect " . $this->_remoteAddress . " fail after " . round(microtime(true) - $this->_connectStartTime, 4) . " seconds");
			if( $this->_status === self::STATUS_CLOSING ) 
			{
				$this->destroy();
			}
			if( $this->_status === self::STATUS_CLOSED ) 
			{
				$this->onConnect = null;
			}
		}
	}
}
?>