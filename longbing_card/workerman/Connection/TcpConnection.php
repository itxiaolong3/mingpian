<?php  namespace Workerman\Connection;
class TcpConnection extends ConnectionInterface 
{
	public $onMessage = NULL;
	public $onClose = NULL;
	public $onError = NULL;
	public $onBufferFull = NULL;
	public $onBufferDrain = NULL;
	public $protocol = NULL;
	public $transport = "tcp";
	public $worker = NULL;
	public $bytesRead = 0;
	public $bytesWritten = 0;
	public $id = 0;
	protected $_id = 0;
	public $maxSendBufferSize = 1048576;
	public static $defaultMaxSendBufferSize = 1048576;
	public static $maxPackageSize = 10485760;
	protected static $_idRecorder = 1;
	protected $_socket = NULL;
	protected $_sendBuffer = "";
	protected $_recvBuffer = "";
	protected $_currentPackageLength = 0;
	protected $_status = self::STATUS_ESTABLISHED;
	protected $_remoteAddress = "";
	protected $_isPaused = false;
	protected $_sslHandshakeCompleted = false;
	public static $connections = array( );
	public static $_statusToString = NULL;
	const READ_BUFFER_SIZE = 65535;
	const STATUS_INITIAL = 0;
	const STATUS_CONNECTING = 1;
	const STATUS_ESTABLISHED = 2;
	const STATUS_CLOSING = 4;
	const STATUS_CLOSED = 8;
	public function __call($name, $arguments) 
	{
		if( method_exists($this->protocol, $name) ) 
		{
			try 
			{
				return call_user_func(array( $this->protocol, $name ), $this, $arguments);
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
	public function __construct($socket, $remote_address = "") 
	{
		self::$statistics["connection_count"]++;
		$this->id = $this->_id = self::$_idRecorder++;
		if( self::$_idRecorder === PHP_INT_MAX ) 
		{
			self::$_idRecorder = 0;
		}
		$this->_socket = $socket;
		stream_set_blocking($this->_socket, 0);
		if( function_exists("stream_set_read_buffer") ) 
		{
			stream_set_read_buffer($this->_socket, 0);
		}
		\Workerman\Worker::$globalEvent->add($this->_socket, \Workerman\Events\EventInterface::EV_READ, array( $this, "baseRead" ));
		$this->maxSendBufferSize = self::$defaultMaxSendBufferSize;
		$this->_remoteAddress = $remote_address;
		static::$connections[$this->id] = $this;
	}
	public function getStatus($raw_output = true) 
	{
		if( $raw_output ) 
		{
			return $this->_status;
		}
		return self::$_statusToString[$this->_status];
	}
	public function send($send_buffer, $raw = false) 
	{
		if( $this->_status === self::STATUS_CLOSING || $this->_status === self::STATUS_CLOSED ) 
		{
			return false;
		}
		if( false === $raw && $this->protocol !== null ) 
		{
			$parser = $this->protocol;
			$send_buffer = $parser::encode($send_buffer, $this);
			if( $send_buffer === "" ) 
			{
				return null;
			}
		}
		if( $this->_status !== self::STATUS_ESTABLISHED || $this->transport === "ssl" && $this->_sslHandshakeCompleted !== true ) 
		{
			if( $this->_sendBuffer && $this->bufferIsFull() ) 
			{
				self::$statistics["send_fail"]++;
				return false;
			}
			$this->_sendBuffer .= $send_buffer;
			$this->checkBufferWillFull();
			return null;
		}
		if( $this->_sendBuffer === "" ) 
		{
			if( $this->transport === "ssl" ) 
			{
				\Workerman\Worker::$globalEvent->add($this->_socket, \Workerman\Events\EventInterface::EV_WRITE, array( $this, "baseWrite" ));
				$this->_sendBuffer = $send_buffer;
				$this->checkBufferWillFull();
				return null;
			}
			set_error_handler(function() 
			{
			}
			);
			$len = fwrite($this->_socket, $send_buffer);
			restore_error_handler();
			if( $len === strlen($send_buffer) ) 
			{
				$this->bytesWritten += $len;
				return true;
			}
			if( 0 < $len ) 
			{
				$this->_sendBuffer = substr($send_buffer, $len);
				$this->bytesWritten += $len;
			}
			else 
			{
				if( !is_resource($this->_socket) || feof($this->_socket) ) 
				{
					self::$statistics["send_fail"]++;
					if( $this->onError ) 
					{
						try 
						{
							call_user_func($this->onError, $this, WORKERMAN_SEND_FAIL, "client closed");
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
					$this->destroy();
					return false;
				}
				$this->_sendBuffer = $send_buffer;
			}
			\Workerman\Worker::$globalEvent->add($this->_socket, \Workerman\Events\EventInterface::EV_WRITE, array( $this, "baseWrite" ));
			$this->checkBufferWillFull();
			return null;
		}
		if( $this->bufferIsFull() ) 
		{
			self::$statistics["send_fail"]++;
			return false;
		}
		$this->_sendBuffer .= $send_buffer;
		$this->checkBufferWillFull();
	}
	public function getRemoteIp() 
	{
		$pos = strrpos($this->_remoteAddress, ":");
		if( $pos ) 
		{
			return substr($this->_remoteAddress, 0, $pos);
		}
		return "";
	}
	public function getRemotePort() 
	{
		if( $this->_remoteAddress ) 
		{
			return (int) substr(strrchr($this->_remoteAddress, ":"), 1);
		}
		return 0;
	}
	public function getRemoteAddress() 
	{
		return $this->_remoteAddress;
	}
	public function getLocalIp() 
	{
		$address = $this->getLocalAddress();
		$pos = strrpos($address, ":");
		if( !$pos ) 
		{
			return "";
		}
		return substr($address, 0, $pos);
	}
	public function getLocalPort() 
	{
		$address = $this->getLocalAddress();
		$pos = strrpos($address, ":");
		if( !$pos ) 
		{
			return 0;
		}
		return (int) substr(strrchr($address, ":"), 1);
	}
	public function getLocalAddress() 
	{
		return (string) @stream_socket_get_name($this->_socket, false);
	}
	public function getSendBufferQueueSize() 
	{
		return strlen($this->_sendBuffer);
	}
	public function getRecvBufferQueueSize() 
	{
		return strlen($this->_recvBuffer);
	}
	public function isIpV4() 
	{
		if( $this->transport === "unix" ) 
		{
			return false;
		}
		return strpos($this->getRemoteIp(), ":") === false;
	}
	public function isIpV6() 
	{
		if( $this->transport === "unix" ) 
		{
			return false;
		}
		return strpos($this->getRemoteIp(), ":") !== false;
	}
	public function pauseRecv() 
	{
		\Workerman\Worker::$globalEvent->del($this->_socket, \Workerman\Events\EventInterface::EV_READ);
		$this->_isPaused = true;
	}
	public function resumeRecv() 
	{
		if( $this->_isPaused === true ) 
		{
			\Workerman\Worker::$globalEvent->add($this->_socket, \Workerman\Events\EventInterface::EV_READ, array( $this, "baseRead" ));
			$this->_isPaused = false;
			$this->baseRead($this->_socket, false);
		}
	}
	public function baseRead($socket, $check_eof = true) 
	{
		if( $this->transport === "ssl" && $this->_sslHandshakeCompleted !== true ) 
		{
			if( $this->doSslHandshake($socket) ) 
			{
				$this->_sslHandshakeCompleted = true;
				if( $this->_sendBuffer ) 
				{
					\Workerman\Worker::$globalEvent->add($socket, \Workerman\Events\EventInterface::EV_WRITE, array( $this, "baseWrite" ));
				}
			}
			else 
			{
				return NULL;
			}
		}
		set_error_handler(function() 
		{
		}
		);
		$buffer = fread($socket, self::READ_BUFFER_SIZE);
		restore_error_handler();
		if( $buffer === "" || $buffer === false ) 
		{
			if( $check_eof && (feof($socket) || !is_resource($socket) || $buffer === false) ) 
			{
				$this->destroy();
				return NULL;
			}
		}
		else 
		{
			$this->bytesRead += strlen($buffer);
			$this->_recvBuffer .= $buffer;
		}
		if( $this->protocol !== null ) 
		{
			$parser = $this->protocol;
			while( $this->_recvBuffer !== "" && !$this->_isPaused ) 
			{
				if( $this->_currentPackageLength ) 
				{
					if( strlen($this->_recvBuffer) < $this->_currentPackageLength ) 
					{
						break;
					}
				}
				else 
				{
					set_error_handler(function($code, $msg, $file, $line) 
					{
						\Workerman\Worker::safeEcho((string) $msg . " in file " . $file . " on line " . $line . "\n");
					}
					);
					$this->_currentPackageLength = $parser::input($this->_recvBuffer, $this);
					restore_error_handler();
					if( $this->_currentPackageLength === 0 ) 
					{
						break;
					}
					if( 0 < $this->_currentPackageLength && $this->_currentPackageLength <= static::$maxPackageSize ) 
					{
						if( strlen($this->_recvBuffer) < $this->_currentPackageLength ) 
						{
							break;
						}
					}
					else 
					{
						\Workerman\Worker::safeEcho("error package. package_length=" . var_export($this->_currentPackageLength, true));
						$this->destroy();
						return NULL;
					}
				}
				self::$statistics["total_request"]++;
				if( strlen($this->_recvBuffer) === $this->_currentPackageLength ) 
				{
					$one_request_buffer = $this->_recvBuffer;
					$this->_recvBuffer = "";
				}
				else 
				{
					$one_request_buffer = substr($this->_recvBuffer, 0, $this->_currentPackageLength);
					$this->_recvBuffer = substr($this->_recvBuffer, $this->_currentPackageLength);
				}
				$this->_currentPackageLength = 0;
				if( !$this->onMessage ) 
				{
					continue;
				}
				try 
				{
					call_user_func($this->onMessage, $this, $parser::decode($one_request_buffer, $this));
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
			if( $this->_recvBuffer === "" || $this->_isPaused ) 
			{
				return NULL;
			}
			self::$statistics["total_request"]++;
			if( !$this->onMessage ) 
			{
				$this->_recvBuffer = "";
			}
			else 
			{
				try 
				{
					call_user_func($this->onMessage, $this, $this->_recvBuffer);
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
				$this->_recvBuffer = "";
			}
		}
	}
	public function baseWrite() 
	{
		set_error_handler(function() 
		{
		}
		);
		if( $this->transport === "ssl" ) 
		{
			$len = fwrite($this->_socket, $this->_sendBuffer, 8192);
		}
		else 
		{
			$len = fwrite($this->_socket, $this->_sendBuffer);
		}
		restore_error_handler();
		if( $len === strlen($this->_sendBuffer) ) 
		{
			$this->bytesWritten += $len;
			\Workerman\Worker::$globalEvent->del($this->_socket, \Workerman\Events\EventInterface::EV_WRITE);
			$this->_sendBuffer = "";
			if( $this->onBufferDrain ) 
			{
				try 
				{
					call_user_func($this->onBufferDrain, $this);
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
			if( $this->_status === self::STATUS_CLOSING ) 
			{
				$this->destroy();
			}
			return true;
		}
		if( 0 < $len ) 
		{
			$this->bytesWritten += $len;
			$this->_sendBuffer = substr($this->_sendBuffer, $len);
		}
		else 
		{
			self::$statistics["send_fail"]++;
			$this->destroy();
		}
	}
	public function doSslHandshake($socket) 
	{
		if( feof($socket) ) 
		{
			$this->destroy();
			return false;
		}
		$async = $this instanceof AsyncTcpConnection;
		if( $async ) 
		{
			$type = STREAM_CRYPTO_METHOD_SSLv2_CLIENT | STREAM_CRYPTO_METHOD_SSLv23_CLIENT;
		}
		else 
		{
			$type = STREAM_CRYPTO_METHOD_SSLv2_SERVER | STREAM_CRYPTO_METHOD_SSLv23_SERVER;
		}
		set_error_handler(function($errno, $errstr, $file) 
		{
			if( !\Workerman\Worker::$daemonize ) 
			{
				\Workerman\Worker::safeEcho("SSL handshake error: " . $errstr . " \n");
			}
		}
		);
		$ret = stream_socket_enable_crypto($socket, true, $type);
		restore_error_handler();
		if( false === $ret ) 
		{
			$this->destroy();
			return false;
		}
		if( 0 === $ret ) 
		{
			return false;
		}
		if( isset($this->onSslHandshake) ) 
		{
			try 
			{
				call_user_func($this->onSslHandshake, $this);
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
		return true;
	}
	public function pipe($dest) 
	{
		$source = $this;
		$this->onMessage = function($source, $data) use ($dest) 
		{
			$dest->send($data);
		}
		;
		$this->onClose = function($source) use ($dest) 
		{
			$dest->destroy();
		}
		;
		$dest->onBufferFull = function($dest) use ($source) 
		{
			$source->pauseRecv();
		}
		;
		$dest->onBufferDrain = function($dest) use ($source) 
		{
			$source->resumeRecv();
		}
		;
	}
	public function consumeRecvBuffer($length) 
	{
		$this->_recvBuffer = substr($this->_recvBuffer, $length);
	}
	public function close($data = NULL, $raw = false) 
	{
		if( $this->_status === self::STATUS_CLOSING || $this->_status === self::STATUS_CLOSED ) 
		{
			return NULL;
		}
		if( $data !== null ) 
		{
			$this->send($data, $raw);
		}
		$this->_status = self::STATUS_CLOSING;
		if( $this->_sendBuffer === "" ) 
		{
			$this->destroy();
		}
	}
	public function getSocket() 
	{
		return $this->_socket;
	}
	protected function checkBufferWillFull() 
	{
		if( $this->maxSendBufferSize <= strlen($this->_sendBuffer) && $this->onBufferFull ) 
		{
			try 
			{
				call_user_func($this->onBufferFull, $this);
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
	protected function bufferIsFull() 
	{
		if( $this->maxSendBufferSize <= strlen($this->_sendBuffer) ) 
		{
			if( $this->onError ) 
			{
				try 
				{
					call_user_func($this->onError, $this, WORKERMAN_SEND_FAIL, "send buffer full and drop package");
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
			return true;
		}
		return false;
	}
	public function bufferIsEmpty() 
	{
		return empty($this->_sendBuffer);
	}
	public function destroy() 
	{
		if( $this->_status === self::STATUS_CLOSED ) 
		{
			return NULL;
		}
		\Workerman\Worker::$globalEvent->del($this->_socket, \Workerman\Events\EventInterface::EV_READ);
		\Workerman\Worker::$globalEvent->del($this->_socket, \Workerman\Events\EventInterface::EV_WRITE);
		set_error_handler(function() 
		{
		}
		);
		fclose($this->_socket);
		restore_error_handler();
		$this->_status = self::STATUS_CLOSED;
		if( $this->onClose ) 
		{
			try 
			{
				call_user_func($this->onClose, $this);
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
		if( $this->protocol && method_exists($this->protocol, "onClose") ) 
		{
			try 
			{
				call_user_func(array( $this->protocol, "onClose" ), $this);
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
		if( $this->_status === self::STATUS_CLOSED ) 
		{
			$this->onMessage = $this->onClose = $this->onError = $this->onBufferFull = $this->onBufferDrain = null;
			if( $this->worker ) 
			{
				unset($this->worker->connections[$this->_id]);
			}
			unset(static::$connections[$this->_id]);
		}
	}
	public function __destruct() 
	{
		static $mod = NULL;
		self::$statistics["connection_count"]--;
		if( \Workerman\Worker::getGracefulStop() ) 
		{
			if( !isset($mod) ) 
			{
				$mod = ceil((self::$statistics["connection_count"] + 1) / 3);
			}
			if( 0 === self::$statistics["connection_count"] % $mod ) 
			{
				\Workerman\Worker::log("worker[" . posix_getpid() . "] remains " . self::$statistics["connection_count"] . " connection(s)");
			}
			if( 0 === self::$statistics["connection_count"] ) 
			{
				\Workerman\Worker::stopAll();
			}
		}
	}
}
?>