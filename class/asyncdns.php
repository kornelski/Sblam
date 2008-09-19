<?php

require_once "class/asyncsocket.php";
require_once "Net/DNS.php";

abstract class AsyncDNSTask
{
	static function poll($time)
	{
		return AsyncSocket::poll($time);
	}
	
	protected $owner;
	function __construct(AsyncDNS $owner)
	{
		$this->owner = $owner; 
	}
	
	function destroy()
	{
		$this->owner = NULL; // break circular reference
	}
	
	protected function result($resconst,$dat = NULL)
	{
		$this->owner->setResult($resconst, $dat);
	}

	protected $buffer = '';

	function read($buf)
	{
		$this->buffer .= $buf;
		
		$ans = new Net_DNS_Packet();
		if ($ans->parse($this->buffer))
		{
			if ($ans->header->qr != '1') $this->result(AsyncDNS::RES_ERROR, "Not an answer"); 
			else if ($ans->header->id != $this->packet->header->id) $this->result(AsyncDNS::RES_ERROR, "Invalid ID"); 
			if ($ans->header->ancount <= 0) 
			{
				if ($ans->header->rcode === 'FORMERR')
				{
					$this->result(AsyncDNS::RES_ERROR,"FormERR!?");
					return;
				}
				else $this->result(AsyncDNS::RES_NOTFOUND, $ans);
			}
			else $this->result(AsyncDNS::RES_FOUND, $ans);
		}
		// unparseable, but maybe next time?
	}
	
	function ping()
	{
		$this->owner->ping();
	}
}

class AsyncDNSTaskUDP extends AsyncDNSTask
{
	protected $packet, $sock;
	
	function __construct(AsyncDNS $owner, Net_DNS_Packet $packet, $nextping = NULL)
	{
		parent::__construct($owner);
		
		$this->packet = $packet; 

		if (!($sock = $this->connect(AsyncDNS::getNameservers()))) throw new Exception("Nameservers down");
		
		if (!$sock->send($packet->data())) throw new Exception("Send error");
		
		$sock->onRead(array($this,'read'));
		$sock->onError(array($this,'error'));
		$sock->onPing(array($this,'ping'), $nextping);
		$this->sock = $sock; 
	}
	
	function destroy()
	{
		$this->sock->destroy(); 
		$this->sock = NULL;
		$this->packet = NULL;
		parent::destroy();
	}
	
	function error($msg)
	{
		$this->result(AsyncDNS::RES_ERROR,$msg);
	}

	protected function connect(array $nameservers)
	{
		foreach($nameservers as $nameserver)
		{
			try {
				$s = new AsyncSocketUDP($nameserver, 53);
			//	d("Connected to $nameserver");
				return $s;
			} 
			catch(ExAsyncSocket $e) 
				{warn($e,"connection to $nameserver failed");}
		}
		//d($nameservers, "no nameservers available!");
		return NULL;		
	}
}


/** queries number of DNS servers asynchronously 
   this class is used statically as a factory. instances are 'resolvers' holding particular queries.
*/
class AsyncDNS
{	
	const RES_FOUND = 1;
	const RES_NOTFOUND = 2;
	const RES_ERROR = 4;
	
	static function supported()
	{
		return function_exists('socket_create');
	}

	static protected $nameservers = array();
	
	/** set up nameservers' IPs */
	static function init(array $nameservers)
	{
		self::$nameservers = $nameservers;
	}
	
	static function getNameservers()
	{
		shuffle(self::$nameservers);
		return self::$nameservers;
	}
	
	static protected $resolvers = array();
	
	/** query nameserver 
		@return instance of AsyncDNS that will return acutual result
	*/
	static function query($host, $type = 'A', $class = 'IN')
	{
		static $queries;

		if (preg_match('/^(\d+)\.(\d+)\.(\d+)\.(\d+)$/', $host, $regs)) {
			$host = $regs[4].'.'.$regs[3].'.'.$regs[2].'.'.$regs[1].'.in-addr.arpa.';
			$type = 'PTR';
		}
		
		$key = $class . $type . $host;
		
		if (!isset(self::$resolvers[$key]))
		{
			self::$resolvers[$key] = new AsyncDNS($host, $type, $class, $key);
			$queries++; if ($queries%10==0) AsyncSocket::poll(); // prevents queue from getting too large
		}
		return self::$resolvers[$key];
	}
		

	protected $packet;
	protected $tasks = array();
	
	protected $finaltime, $nexttry, $retries = 4;
	
	protected $resolverskey;
	
	/** each logical query can actually consist of more than one network-level query AKA task (because retries don't kill already-open sockets in case late reply comes) */
	function __construct($host, $type, $class, $resolverskey = NULL)
	{
		$this->resolverskey = $resolverskey; 
	  $this->packet = new Net_DNS_Packet();
		$this->packet->buildQuestion($host, $type, $class);
			
		$this->finaltime = microtime(true) + 6;	// when to give up
		$this->nexttry = $this->finaltime + 100; // this is not a mistake, initial nexttry must be impossible to reach
		$this->newTask();
	}
	
	protected function newTask()
	{
		$this->nexttry = microtime(true) + 1.5;
		$this->tasks[] = new AsyncDNSTaskUDP($this, $this->packet, $this->nexttry);
	}
		
	protected $answer;
	protected $answerpositive;
	function setResult($resconst, $dat)
	{
		if ($this->answerpositive) return; // not interested
		
		if ($resconst == self::RES_ERROR) 
		{
			//d($dat,"Reported error");
			if ($this->retries > 0)
			{
				$this->retries--;
				$this->newTask();
			}
			else {$this->answer = false;}
		}
		elseif ($resconst == self::RES_FOUND || $resconst == self::RES_NOTFOUND)
		{
			$this->answerpositive = ($resconst == self::RES_FOUND); 
			$this->answer = $dat;
			foreach($this->tasks as $task)
			{
				$task->destroy();
			}
			$this->tasks = array();
		}
	}
	
	/** @return 0 on not-found, false on error, str or array on success. */
	function getResult($blocking = true)
	{
		$res = $this->getRawResult($blocking);
		
		if ($res) 
		{
			$out = array();
			foreach($res->answer as $rr)
			{
				if ($rr instanceof Net_DNS_RR_A) $out[] = $rr->address;
				elseif ($rr instanceof Net_DNS_RR_PTR) return $rr->ptrdname;
				elseif ($rr instanceof Net_DNS_RR_NS) $out[] = $rr->nsdname;
				elseif ($rr instanceof Net_DNS_RR_CNAME) 
				{
					$temp = gethostbynamel($rr->cname);
					if ($temp) $out = array_merge($out,$temp);
				}
				else
				{
					warn($rr,"Unusable record type");
				}
			}
			
			if ($this->resolverskey) unset(self::$resolvers[$this->resolverskey]);			
			return $out;
		}
		return $res;	
	}
	
	function ping()
	{
		if ($this->answer === NULL && $this->retries > 0 && microtime(true) > $this->nexttry)
		{		
			$this->retries--; 
			$this->newTask();
		}
	}
	
	function getRawResult($blocking)
	{
		if ($this->answer !== NULL) {return $this->answer;}
	
		do 
		{
			$sleeptime = min(($this->retries?$this->nexttry:$this->finaltime),$this->finaltime) - microtime(true);
						
			if (!AsyncDNSTask::poll(max(0,$sleeptime + 0.1))) throw new Exception("Empty socket list!?");
			$this->ping();
		}
		while(($this->answer === NULL) && $blocking && $this->finaltime > microtime(true));

		if (!$blocking || $this->answer !== NULL) return $this->answer;
		
		return $this->answer = false;
	}
	
}
