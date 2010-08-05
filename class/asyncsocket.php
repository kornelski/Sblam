<?php

class ExAsyncSocket extends Exception {}

abstract class AsyncSocket
{
	protected $sock;

	function __destruct()
	{
		$this->destroy();
	}

	function destroy()
	{
		if ($this->sock)
		{
			self::unregister($this->sock);
			socket_close($this->sock);
			$this->sock = NULL;
		}
		$this->onError = $this->onRead = $this->onPing = NULL;
	}

	static protected $sockets = array();
	static protected $handlers = array();
	static protected function register($sock, AsyncSocket $handler)
	{
		self::$sockets[ "$sock" ] = $sock;
		self::$handlers[ "$sock" ] = $handler;
	}

	static protected function unregister($sock)
	{
		unset(self::$sockets[ "$sock" ]);
		unset(self::$handlers[ "$sock" ]);
	}

	static $nextPing;
	static function nextPing($microtime)
	{
		if (!$microtime) return;
		self::$nextPing = min(self::$nextPing, $microtime);
	}

	/** @return false if there is nothing to poll */
	static function poll($timeout = 2)
	{
		if (!count(self::$sockets)) return false;

		if (self::$nextPing) $timeout = min(max(0.1,self::$nextPing - microtime(true)), $timeout); // break early, to ping

		$queuecopy = self::$sockets; $null = NULL;
		if (!($res = socket_select($queuecopy, $null, $null, floor($timeout), ceil($timeout*1000000))))
		{
			if ($res === false)
			{
				throw new ExAsyncSocket("select() failure: ".socket_strerror(socket_last_error()));
			}
			usleep(8000);
			//d("Nothing changed during select()");
			return true;
		}

		foreach($queuecopy as $sock)
		{
			//d("$sock Socket read!");
			if (!isset(self::$handlers[ "$sock" ])) continue; // might have unregistered in the meantime!
			$handler = self::$handlers[ "$sock" ];
			$handler->read();
		}

		self::$nextPing = microtime(true) + 4;
		foreach(self::$handlers as $id => $handler)
		{
			$handler->ping();
		}
		return true;
	}

	abstract function send($data);
	abstract protected function read();
}

class AsyncSocketUDP extends AsyncSocket
{
	function __construct($ip, $port)
	{
		if (!($sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)))
		{
			warn("Failed to create socket");
			usleep(100000);
			AsyncSocket::poll(0.5);
			if (!($sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)))
			{
				throw new ExAsyncSocket("Unable to create UDP socket");
			}
		}

		if (socket_connect($sock, $ip, $port))
		{
			socket_set_nonblock($sock);
			$this->sock = $sock;
		}
		else
		{
			warn('sockets problem');
			throw new ExAsyncSocket("Unable to connect to UDP $ip:$port");
		}
	}

	function send($data)
	{
		$len = strlen($data);
		while($len > 0)
		{
			$res = socket_write($this->sock, $data);
			if (!$res) return false;
			$len -= $res;
			if ($len) $data = substr($data,-$len);
		}
		return true;
	}

	protected $onError;
	function onError($callback) {$this->onError = $callback;}

	protected $onPing;
	function onPing($callback, $nexttime)
	{
		$this->onPing = $callback;
		AsyncSocket::nextPing($nexttime);
	}

	function ping()
	{
		if ($this->onPing) call_user_func($this->onPing);
	}

	protected function fail($msg)
	{
		if ($this->onError) call_user_func($this->onError, $msg);
		$this->destroy();
	}

	protected function read()
	{
		if ($err = socket_last_error($this->sock))
		{
			$this->fail(socket_strerror($err));
			return;
		}

		if (socket_get_option($this->sock, SOL_SOCKET, SO_ERROR) === SOCKET_ECONNREFUSED)
		{
			$this->fail("Connection refused");
			return;
		}

		$buf = socket_read($this->sock, 100000);

		if ($buf === false) {$this->fail("Error while reading"); return;}
		if ($buf === "") {$this->fail("End of stream"); return;}

		if ($this->onRead) call_user_func($this->onRead, $buf);
	}

	protected $onRead;
	function onRead($callback)
	{
		$this->onRead = $callback;
		self::register($this->sock, $this);
	}
}
