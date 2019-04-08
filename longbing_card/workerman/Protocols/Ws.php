<?php  namespace Workerman\Protocols;
class Ws 
{
	const BINARY_TYPE_BLOB = "?";
	const BINARY_TYPE_ARRAYBUFFER = "?";
	public static function input($buffer, $connection) 
	{
		if( empty($connection->handshakeStep) ) 
		{
			\Workerman\Worker::safeEcho("recv data before handshake. Buffer:" . bin2hex($buffer) . "\n");
			return false;
		}
		if( $connection->handshakeStep === 1 ) 
		{
			return self::dealHandshake($buffer, $connection);
		}
		$recv_len = strlen($buffer);
		if( $recv_len < 2 ) 
		{
			return 0;
		}
		if( $connection->websocketCurrentFrameLength ) 
		{
			if( $recv_len < $connection->websocketCurrentFrameLength ) 
			{
				return 0;
			}
		}
		else 
		{
			$firstbyte = ord($buffer[0]);
			$secondbyte = ord($buffer[1]);
			$data_len = $secondbyte & 127;
			$is_fin_frame = $firstbyte >> 7;
			$masked = $secondbyte >> 7;
			if( $masked ) 
			{
				\Workerman\Worker::safeEcho("frame masked so close the connection\n");
				$connection->close();
				return 0;
			}
			$opcode = $firstbyte & 15;
			switch( $opcode ) 
			{
				case 0: break;
				case 1: break;
				case 2: break;
				case 8: if( isset($connection->onWebSocketClose) ) 
				{
					try 
					{
						call_user_func($connection->onWebSocketClose, $connection);
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
				else 
				{
					$connection->close();
				}
				return 0;
				case 9: break;
				case 10: break;
				default: \Workerman\Worker::safeEcho("error opcode " . $opcode . " and close websocket connection. Buffer:" . $buffer . "\n");
				$connection->close();
				return 0;
			}
			if( $data_len === 126 ) 
			{
				if( strlen($buffer) < 4 ) 
				{
					return 0;
				}
				$pack = unpack("nn/ntotal_len", $buffer);
				$current_frame_length = $pack["total_len"] + 4;
			}
			else 
			{
				if( $data_len === 127 ) 
				{
					if( strlen($buffer) < 10 ) 
					{
						return 0;
					}
					$arr = unpack("n/N2c", $buffer);
					$current_frame_length = $arr["c1"] * 4294967296 + $arr["c2"] + 10;
				}
				else 
				{
					$current_frame_length = $data_len + 2;
				}
			}
			$total_package_size = strlen($connection->websocketDataBuffer) + $current_frame_length;
			if( $connection::$maxPackageSize < $total_package_size ) 
			{
				\Workerman\Worker::safeEcho("error package. package_length=" . $total_package_size . "\n");
				$connection->close();
				return 0;
			}
			if( $is_fin_frame ) 
			{
				if( $opcode === 9 ) 
				{
					if( $current_frame_length <= $recv_len ) 
					{
						$ping_data = static::decode(substr($buffer, 0, $current_frame_length), $connection);
						$connection->consumeRecvBuffer($current_frame_length);
						$tmp_connection_type = (isset($connection->websocketType) ? $connection->websocketType : static::BINARY_TYPE_BLOB);
						$connection->websocketType = "?";
						if( isset($connection->onWebSocketPing) ) 
						{
							try 
							{
								call_user_func($connection->onWebSocketPing, $connection, $ping_data);
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
						else 
						{
							$connection->send($ping_data);
						}
						$connection->websocketType = $tmp_connection_type;
						if( $current_frame_length < $recv_len ) 
						{
							return static::input(substr($buffer, $current_frame_length), $connection);
						}
					}
					return 0;
				}
				if( $opcode === 10 ) 
				{
					if( $current_frame_length <= $recv_len ) 
					{
						$pong_data = static::decode(substr($buffer, 0, $current_frame_length), $connection);
						$connection->consumeRecvBuffer($current_frame_length);
						$tmp_connection_type = (isset($connection->websocketType) ? $connection->websocketType : static::BINARY_TYPE_BLOB);
						$connection->websocketType = "?";
						if( isset($connection->onWebSocketPong) ) 
						{
							try 
							{
								call_user_func($connection->onWebSocketPong, $connection, $pong_data);
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
						$connection->websocketType = $tmp_connection_type;
						if( $current_frame_length < $recv_len ) 
						{
							return static::input(substr($buffer, $current_frame_length), $connection);
						}
					}
					return 0;
				}
				return $current_frame_length;
			}
			$connection->websocketCurrentFrameLength = $current_frame_length;
		}
		if( $connection->websocketCurrentFrameLength === $recv_len ) 
		{
			self::decode($buffer, $connection);
			$connection->consumeRecvBuffer($connection->websocketCurrentFrameLength);
			$connection->websocketCurrentFrameLength = 0;
			return 0;
		}
		if( $connection->websocketCurrentFrameLength < $recv_len ) 
		{
			self::decode(substr($buffer, 0, $connection->websocketCurrentFrameLength), $connection);
			$connection->consumeRecvBuffer($connection->websocketCurrentFrameLength);
			$current_frame_length = $connection->websocketCurrentFrameLength;
			$connection->websocketCurrentFrameLength = 0;
			return self::input(substr($buffer, $current_frame_length), $connection);
		}
		return 0;
	}
	public static function encode($payload, $connection) 
	{
		if( empty($connection->websocketType) ) 
		{
			$connection->websocketType = self::BINARY_TYPE_BLOB;
		}
		$payload = (string) $payload;
		if( empty($connection->handshakeStep) ) 
		{
			self::sendHandshake($connection);
		}
		$mask = 1;
		$mask_key = "";
		$pack = "";
		$length = $length_flag = strlen($payload);
		if( 65535 < $length ) 
		{
			$pack = pack("NN", ($length & 1.84467440694146E+19) >> 32, $length & 4294967295);
			$length_flag = 127;
		}
		else 
		{
			if( 125 < $length ) 
			{
				$pack = pack("n*", $length);
				$length_flag = 126;
			}
		}
		$head = $mask << 7 | $length_flag;
		$head = $connection->websocketType . chr($head) . $pack;
		$frame = $head . $mask_key;
		for( $i = 0; $i < $length; $i++ ) 
		{
			$frame .= $payload[$i] ^ $mask_key[$i % 4];
		}
		if( $connection->handshakeStep === 1 ) 
		{
			if( $connection->maxSendBufferSize < strlen($connection->tmpWebsocketData) ) 
			{
				if( $connection->onError ) 
				{
					try 
					{
						call_user_func($connection->onError, $connection, WORKERMAN_SEND_FAIL, "send buffer full and drop package");
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
				return "";
			}
			$connection->tmpWebsocketData = $connection->tmpWebsocketData . $frame;
			if( $connection->maxSendBufferSize <= strlen($connection->tmpWebsocketData) && $connection->onBufferFull ) 
			{
				try 
				{
					call_user_func($connection->onBufferFull, $connection);
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
			return "";
		}
		return $frame;
	}
	public static function decode($bytes, $connection) 
	{
		$data_length = ord($bytes[1]);
		if( $data_length === 126 ) 
		{
			$decoded_data = substr($bytes, 4);
		}
		else 
		{
			if( $data_length === 127 ) 
			{
				$decoded_data = substr($bytes, 10);
			}
			else 
			{
				$decoded_data = substr($bytes, 2);
			}
		}
		if( $connection->websocketCurrentFrameLength ) 
		{
			$connection->websocketDataBuffer .= $decoded_data;
			return $connection->websocketDataBuffer;
		}
		if( $connection->websocketDataBuffer !== "" ) 
		{
			$decoded_data = $connection->websocketDataBuffer . $decoded_data;
			$connection->websocketDataBuffer = "";
		}
		return $decoded_data;
	}
	public static function onConnect($connection) 
	{
		self::sendHandshake($connection);
	}
	public static function onClose($connection) 
	{
		$connection->handshakeStep = null;
		$connection->websocketCurrentFrameLength = 0;
		$connection->tmpWebsocketData = "";
		$connection->websocketDataBuffer = "";
		if( !empty($connection->websocketPingTimer) ) 
		{
			\Workerman\Lib\Timer::del($connection->websocketPingTimer);
			$connection->websocketPingTimer = null;
		}
	}
	public static function sendHandshake($connection) 
	{
		if( !empty($connection->handshakeStep) ) 
		{
			return NULL;
		}
		$port = $connection->getRemotePort();
		$host = ($port === 80 ? $connection->getRemoteHost() : $connection->getRemoteHost() . ":" . $port);
		$connection->websocketSecKey = base64_encode(md5(mt_rand(), true));
		$userHeader = "";
		if( !empty($connection->wsHttpHeader) ) 
		{
			if( is_array($connection->wsHttpHeader) ) 
			{
				foreach( $connection->wsHttpHeader as $k => $v ) 
				{
					$userHeader .= (string) $k . ": " . $v . "\r\n";
				}
			}
			else 
			{
				$userHeader .= $connection->wsHttpHeader;
			}
			$userHeader = "\r\n" . trim($userHeader);
		}
		$header = "GET " . $connection->getRemoteURI() . " HTTP/1.1\r\n" . "Host: " . $host . "\r\n" . "Connection: Upgrade\r\n" . "Upgrade: websocket\r\n" . "Origin: " . ((isset($connection->websocketOrigin) ? $connection->websocketOrigin : "*")) . "\r\n" . ((isset($connection->WSClientProtocol) ? "Sec-WebSocket-Protocol: " . $connection->WSClientProtocol . "\r\n" : "")) . "Sec-WebSocket-Version: 13\r\n" . "Sec-WebSocket-Key: " . $connection->websocketSecKey . $userHeader . "\r\n\r\n";
		$connection->send($header, true);
		$connection->handshakeStep = 1;
		$connection->websocketCurrentFrameLength = 0;
		$connection->websocketDataBuffer = "";
		$connection->tmpWebsocketData = "";
	}
	public static function dealHandshake($buffer, $connection) 
	{
		$pos = strpos($buffer, "\r\n\r\n");
		if( $pos ) 
		{
			if( preg_match("/Sec-WebSocket-Accept: *(.*?)\r\n/i", $buffer, $match) ) 
			{
				if( $match[1] !== base64_encode(sha1($connection->websocketSecKey . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true)) ) 
				{
					\Workerman\Worker::safeEcho("Sec-WebSocket-Accept not match. Header:\n" . substr($buffer, 0, $pos) . "\n");
					$connection->close();
					return 0;
				}
				if( preg_match("/Sec-WebSocket-Protocol: *(.*?)\r\n/i", $buffer, $match) ) 
				{
					$connection->WSServerProtocol = trim($match[1]);
				}
				$connection->handshakeStep = 2;
				$handshake_response_length = $pos + 4;
				if( isset($connection->onWebSocketConnect) ) 
				{
					try 
					{
						call_user_func($connection->onWebSocketConnect, $connection, substr($buffer, 0, $handshake_response_length));
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
				if( !empty($connection->websocketPingInterval) ) 
				{
					$connection->websocketPingTimer = \Workerman\Lib\Timer::add($connection->websocketPingInterval, function() use ($connection) 
					{
						if( false === $connection->send(pack("H*", "898000000000"), true) ) 
						{
							\Workerman\Lib\Timer::del($connection->websocketPingTimer);
							$connection->websocketPingTimer = null;
						}
					}
					);
				}
				$connection->consumeRecvBuffer($handshake_response_length);
				if( !empty($connection->tmpWebsocketData) ) 
				{
					$connection->send($connection->tmpWebsocketData, true);
					$connection->tmpWebsocketData = "";
				}
				if( $handshake_response_length < strlen($buffer) ) 
				{
					return self::input(substr($buffer, $handshake_response_length), $connection);
				}
			}
			else 
			{
				\Workerman\Worker::safeEcho("Sec-WebSocket-Accept not found. Header:\n" . substr($buffer, 0, $pos) . "\n");
				$connection->close();
				return 0;
			}
		}
		return 0;
	}
	public static function WSSetProtocol($connection, $params) 
	{
		$connection->WSClientProtocol = $params[0];
	}
	public static function WSGetServerProtocol($connection) 
	{
		return (property_exists($connection, "WSServerProtocol") ? $connection->WSServerProtocol : null);
	}
}
?>