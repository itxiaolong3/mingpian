<?php  namespace Workerman\Connection;
class AsyncUdpConnection extends UdpConnection 
{
	public $onConnect = NULL;
	public $onClose = NULL;
	protected $connected = false;
	protected $_contextOption = NULL;
	public function __construct($remote_address, $context_option = NULL) 
	{
		list($scheme, $address) = explode(":", $remote_address, 2);
		if( $scheme !== "udp" ) 
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
		$this->_remoteAddress = substr($address, 2);
		$this->_contextOption = $context_option;
	}
	public function baseRead($socket) 
	{
		$recv_buffer = stream_socket_recvfrom($socket, \Workerman\Worker::MAX_UDP_PACKAGE_SIZE, 0, $remote_address);
		if( false === $recv_buffer || empty($remote_address) ) 
		{
			return false;
		}
		if( $this->onMessage ) 
		{
			if( $this->protocol ) 
			{
				$parser = $this->protocol;
				$recv_buffer = $parser::decode($recv_buffer, $this);
			}
			ConnectionInterface::$statistics["total_request"]++;
			try 
			{
				call_user_func($this->onMessage, $this, $recv_buffer);
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
	public function send($send_buffer, $raw = false) 
	{
		if( false === $raw && $this->protocol ) 
		{
			$parser = $this->protocol;
			$send_buffer = $parser::encode($send_buffer, $this);
			if( $send_buffer === "" ) 
			{
				return null;
			}
		}
		if( $this->connected === false ) 
		{
			$this->connect();
		}
		return strlen($send_buffer) === stream_socket_sendto($this->_socket, $send_buffer, 0);
	}
	public function close($data = NULL, $raw = false) 
	{
		if( $data !== null ) 
		{
			$this->send($data, $raw);
		}
		\Workerman\Worker::$globalEvent->del($this->_socket, \Workerman\Events\EventInterface::EV_READ);
		fclose($this->_socket);
		$this->connected = false;
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
		$this->onConnect = $this->onMessage = $this->onClose = null;
		return true;
	}
	public function connect() 
	{
		if( $this->connected === true ) 
		{
			return NULL;
		}
		if( $this->_contextOption ) 
		{
			$context = stream_context_create($this->_contextOption);
			$this->_socket = stream_socket_client("udp://" . $this->_remoteAddress, $errno, $errmsg, 30, STREAM_CLIENT_CONNECT, $context);
		}
		else 
		{
			$this->_socket = stream_socket_client("udp://" . $this->_remoteAddress, $errno, $errmsg);
		}
		if( !$this->_socket ) 
		{
			\Workerman\Worker::safeEcho(new \Exception($errmsg));
		}
		else 
		{
			if( $this->onMessage ) 
			{
				\Workerman\Worker::$globalEvent->add($this->_socket, \Workerman\Events\EventInterface::EV_READ, array( $this, "baseRead" ));
			}
			$this->connected = true;
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
		}
	}
}
?>