<?php

class ResParse {
	private $data;
	private $caret = 0;
	private $length;

	public function __construct($str) {
		$this->data = $str;
		$this->length = strlen($str);
	}

	private function eat($count) {
		$begin = $this->caret;
		$this->caret += $count;
		return substr($this->data, $begin, $count);
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
		$msg = $this->data();
		return $this->result("status",trim($msg));
	}

	private function parseError() {
		$msg = $this->data();
		return $this->result("error",trim($msg));
	}

	private function parseNum() {
		$msg = $this->data();
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
		return $ret;
	}

	private function result($type,$data) {
		return compact("type","data");
	}

	private function assertNumber($a) {
		if (!is_numeric($a)) {
			throw new Exception("it should be a number: $a");
		}
	}

	private function data() {
		return substr($this->data,$this->caret);
	}

	private function getLine() {
		$ret = '';

		for ($i = $this->caret; $i < $this->length; $i++) { 
			$c = $this->eat(1);
			if (ord($c)==10) {
				break;
			}
			$ret .= $c;
		}

		return trim($ret);
	}
}

class myRedis {
	private $socket;

	public function toRedisData(array $args)
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

	public function __call($method,$args) {
		$method = strtoupper($method);
		array_unshift($args, $method);
		$data = $this->toRedisData($args);
		echo $data;
	}

	public function parseRedis($str) {
		$parse = new ResParse($str);
		return $parse->parse();
	}

	private function connect($host, $port) {
		$this->socket = fsockopen($host, $port);
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
// echo $parse->toRedisData(["set","key","this is value of key"]);
// $redis->set("key1","10");
$data = $redis->parseRedis("*4\r\n$3\r\nfoo\r\n$3\r\nbar\r\n$5\r\nHello\r\n$5\r\nWorld\r\n");
print_r($data);
