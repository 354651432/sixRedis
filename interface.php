<?php

class ResParse {
	private $handle;
	private $caret = 0;
	// private $length;

	public function __construct($handle) {
		$this->handle = $handle;
		// $this->length = strlen($str);
	}

	private function eat($count) {
		// $begin = $this->caret;
		// $this->caret += $count;
		// return substr($this->data, $begin, $count);
		if (feof($this->handle)) {
			return null;
		}
		return fread($this->handle, $count);
	}

	public function parse() {
		$first = $this->eat(1);
		switch($first) {
			case '+':
				return $this->parseStatus();
			case '-':
				return $this->parseError();
			case ':':
				return $this->parseNum();
			case '$':
				return $this->parseBat();
			case '*':
				return $this->parseMBat();
		}
		return null;
	}

	private function parseStatus() {
		$msg = fgets($this->handle);
		return $this->result("status",trim($msg));
	}

	private function parseError() {
		$msg = fgets($this->handle);
		return $this->result("error",trim($msg));
	}

	private function parseNum() {
		$msg = fgets($this->handle);
		$msg = trim($msg);
		$this->assertNumber($msg);
		return $this->result("number", $msg);
	}

	private function parseBat() {
		$length = $this->getLine();
		$this->assertNumber($length);
		if ($length <= 0) {
			return $this->result("string","");
		}
		$ret = $this->result("string", $this->eat($length));
		$this->eat(2);

		return $ret;
	}

	private function parseMBat() {
		$length = $this->getLine();
		$this->assertNumber($length);

		$ret = [];
		for ($i=0; $i < $length; $i++) { 
			$this->eat(1);
			$ret []= $this->parseBat();
		}
		return $this->result("array", $ret);
	}

	private function result($type,$data) {
		return compact("type","data");
	}

	private function assertNumber($a) {
		if (!is_numeric($a)) {
			throw new Exception("it should be a number: $a");
		}
	}

	// 会产生死循环
	private function data() {
		$ret = '';
		while(!feof($this->handle)) {
			$ret .= fgets($this->handle, 128);
		}
		return trim($ret);
	}

	private function getLine() {
		$ret = '';

		for (;;) { 
			$c = $this->eat(1);
			if (is_null($c) || ord($c)==10) {
				break;
			}
			$ret .= $c;
		}

		return trim($ret);
	}
}

class myRedis {
	private $socket;

	public function __construct($host = "127.0.0.1",$port = 6379) {
		$this->socket = fsockopen($host, $port);
	}

	private function toRedisData(array $args)
	{
		$len = count($args);
		$ret = "*$len\r\n";
		foreach ($args as $key => $value) {
			$size = strlen($value);
			$ret .= "\$$size\r\n";
			$ret .= "$value\r\n";
		}
		return $ret;
	}

	public function __call($method, $args) {
		$method = strtoupper($method);
		array_unshift($args, $method);
		$data = $this->toRedisData($args);
		fwrite($this->socket, $data);

		$ret = $this->parseRedis();

		return $this->redisData2PhpData($ret);
	}

	private function parseRedis() {
		$parse = new ResParse($this->socket);
		return $parse->parse();
	}

	private function redisData2PhpData($data) {
		switch($data["type"]) {
			case "error":
				throw new Exception($data["data"]);
			case "array":
				$ret = [];
				foreach($data["data"] as $item) {
					$ret []= $item["data"];
				}
				return $ret;
			default:
				return $data["data"];
		}
	}

	public function close() {
		if ($this->socket) {
			fclose($this->socket);
		}
	}

	public function __destructor() {
		$this->close();
	}
}


$redis = new myRedis;
// $redis->lpush("list1", "fuck");
// $redis->lpush("list1", "you");
// $redis->lpush("list1", "one");
// $redis->lpush("list1", "by");
// $redis->lpush("list1", "one");

print_r($redis->lrange("list1",0,-1));