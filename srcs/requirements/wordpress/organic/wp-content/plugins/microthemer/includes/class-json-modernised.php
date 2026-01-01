<?php

// Defines for JSON tokens and locations
define('JSON_BOOL', 1);
define('JSON_INT', 2);
define('JSON_STR', 3);
define('JSON_FLOAT', 4);
define('JSON_NULL', 5);
define('JSON_START_OBJ', 6);
define('JSON_END_OBJ', 7);
define('JSON_START_ARRAY', 8);
define('JSON_END_ARRAY', 9);
define('JSON_KEY', 10);
define('JSON_SKIP', 11);

define('JSON_IN_ARRAY', 30);
define('JSON_IN_OBJECT', 40);
define('JSON_IN_BETWEEN', 50);

class Moxiecode_JSONReader {
	private $_data;
	private $_len;
	private $_pos = -1;
	private $_value;
	private $_token;
	private $_location = JSON_IN_BETWEEN;
	private $_lastLocations = array();
	private $_needProp = false;

	public function __construct($data) {
		$this->_data = $data;
		$this->_len = strlen($data);
	}

	public function getToken() {
		return $this->_token;
	}

	public function getLocation() {
		return $this->_location;
	}

	public function getTokenName() {
		switch ($this->_token) {
			case JSON_BOOL:
				return 'JSON_BOOL';
			case JSON_INT:
				return 'JSON_INT';
			case JSON_STR:
				return 'JSON_STR';
			case JSON_FLOAT:
				return 'JSON_FLOAT';
			case JSON_NULL:
				return 'JSON_NULL';
			case JSON_START_OBJ:
				return 'JSON_START_OBJ';
			case JSON_END_OBJ:
				return 'JSON_END_OBJ';
			case JSON_START_ARRAY:
				return 'JSON_START_ARRAY';
			case JSON_END_ARRAY:
				return 'JSON_END_ARRAY';
			case JSON_KEY:
				return 'JSON_KEY';
			default:
				return 'UNKNOWN';
		}
	}

	public function getValue() {
		return $this->_value;
	}

	public function readToken() {
		$chr = $this->read();
		if ($chr !== null) {
			switch ($chr) {
				case '[':
					return $this->startArray();
				case ']':
					return $this->endArray();
				case '{':
					return $this->startObject();
				case '}':
					return $this->endObject();
				case '"':
				case '\'':
					return $this->readString($chr);
				case 'n':
					return $this->readNull();
				case 't':
				case 'f':
					return $this->readBool($chr);
				default:
					if (is_numeric($chr) || $chr === '-' || $chr === '.') {
						return $this->readNumber($chr);
					}
					return true;
			}
		}
		return false;
	}

	private function startArray() {
		$this->_lastLocations[] = $this->_location;
		$this->_location = JSON_IN_ARRAY;
		$this->_token = JSON_START_ARRAY;
		$this->_value = null;
		$this->readAway();
		return true;
	}

	private function endArray() {
		$this->_location = array_pop($this->_lastLocations);
		$this->_token = JSON_END_ARRAY;
		$this->_value = null;
		$this->readAway();
		if ($this->_location === JSON_IN_OBJECT) {
			$this->_needProp = true;
		}
		return true;
	}

	private function startObject() {
		$this->_lastLocations[] = $this->_location;
		$this->_location = JSON_IN_OBJECT;
		$this->_needProp = true;
		$this->_token = JSON_START_OBJ;
		$this->_value = null;
		$this->readAway();
		return true;
	}

	private function endObject() {
		$this->_location = array_pop($this->_lastLocations);
		$this->_token = JSON_END_OBJ;
		$this->_value = null;
		$this->readAway();
		if ($this->_location === JSON_IN_OBJECT) {
			$this->_needProp = true;
		}
		return true;
	}

	private function readBool($chr) {
		$this->_token = JSON_BOOL;
		$this->_value = $chr === 't';
		$this->skip($chr === 't' ? 3 : 4); // Skip "rue" or "alse"
		$this->readAway();
		if ($this->_location === JSON_IN_OBJECT && !$this->_needProp) {
			$this->_needProp = true;
		}
		return true;
	}

	private function readNull() {
		$this->_token = JSON_NULL;
		$this->_value = null;
		$this->skip(3); // Skip "ull"
		$this->readAway();
		if ($this->_location === JSON_IN_OBJECT && !$this->_needProp) {
			$this->_needProp = true;
		}
		return true;
	}

	private function readString($quote) {
		$output = '';
		$this->_token = JSON_STR;
		$endString = false;

		while (($chr = $this->peek()) !== null) {
			if ($chr === '\\') {
				$this->read(); // Escape
				$output .= $this->readEscape();
			} elseif ($chr === $quote) {
				$endString = true;
				$this->read();
				break;
			} else {
				$output .= $this->read();
			}
		}

		$this->readAway();
		$this->_value = $output;

		if ($this->_needProp) {
			$this->_token = JSON_KEY;
			$this->_needProp = false;
		} elseif ($this->_location === JSON_IN_OBJECT && !$this->_needProp) {
			$this->_needProp = true;
		}
		return true;
	}

	private function readEscape() {
		$chr = $this->read();
		switch ($chr) {
			case 't': return "\t";
			case 'b': return "\b";
			case 'f': return "\f";
			case 'r': return "\r";
			case 'n': return "\n";
			case 'u': return $this->decodeUnicodeEscape();
			default: return $chr;
		}
	}

	private function decodeUnicodeEscape() {
		$hex = $this->read(4);
		return mb_convert_encoding(pack('H*', $hex), 'UTF-8', 'UTF-16BE');
	}

	private function readNumber($start) {
		$value = $start;
		$isFloat = false;

		while (($chr = $this->peek()) !== null && (is_numeric($chr) || $chr === '-' || $chr === '.')) {
			if ($chr === '.') {
				$isFloat = true;
			}
			$value .= $this->read();
		}

		$this->readAway();
		$this->_token = $isFloat ? JSON_FLOAT : JSON_INT;
		$this->_value = $isFloat ? (float)$value : (int)$value;

		if ($this->_location === JSON_IN_OBJECT && !$this->_needProp) {
			$this->_needProp = true;
		}
		return true;
	}

	private function readAway() {
		while (($chr = $this->peek()) !== null && ($chr === ':' || $chr === ',' || ctype_space($chr))) {
			$this->read();
		}
	}

	private function read($len = 1) {
		if ($this->_pos < $this->_len - 1) {
			if ($len > 1) {
				$str = substr($this->_data, $this->_pos + 1, $len);
				$this->_pos += $len;
				return $str;
			}
			return $this->_data[++$this->_pos];
		}
		return null;
	}

	private function skip($len) {
		$this->_pos += $len;
	}

	private function peek() {
		return $this->_pos < $this->_len - 1 ? $this->_data[$this->_pos + 1] : null;
	}
}

//
class Moxiecode_JSON {

	/** @var array */
	private $data = array();

	/** @var array */
	private $parents = array();

	/** @var mixed */
	private $cur = null;

	public function __construct() {}

	/** @return mixed */
	public function decode($input) {
		$reader = new Moxiecode_JSONReader($input);
		return $this->readValue($reader);
	}

	/** @return mixed */
	private function readValue($reader) {
		$this->data = array();
		$this->parents = array();
		$this->cur = &$this->data;
		$key = null;
		$loc = JSON_IN_ARRAY;

		while ($reader->readToken()) {
			switch ($reader->getToken()) {
				case JSON_STR:
				case JSON_INT:
				case JSON_BOOL:
				case JSON_FLOAT:
				case JSON_NULL:
					switch ($reader->getLocation()) {
						case JSON_IN_OBJECT:
							$this->cur[$key] = $reader->getValue();
							break;
						case JSON_IN_ARRAY:
							$this->cur[] = $reader->getValue();
							break;
						default:
							$reader->getValue(); // Ignored
					}
					break;

				case JSON_KEY:
					$key = $reader->getValue();
					break;

				case JSON_START_OBJ:
				case JSON_START_ARRAY:
					if ($loc === JSON_IN_OBJECT) {
						$this->addArray($key);
					} else {
						$this->addArray(null);
					}
					$loc = $reader->getLocation();
					break;

				case JSON_END_OBJ:
				case JSON_END_ARRAY:
					$loc = $reader->getLocation();
					if (count($this->parents) > 0) {
						$this->cur = &$this->parents[array_key_last($this->parents)];
						array_pop($this->parents);
					}
					break;
			}
		}

		return isset($this->data[0]) ? $this->data[0] : null;
	}

	private function addArray($key) {
		$this->parents[] = &$this->cur;
		$array = array();

		if ($key !== null) {
			$this->cur[$key] = &$array;
		} else {
			$this->cur[] = &$array;
		}

		$this->cur = &$array;
	}

	public function encode($input) {
		switch (gettype($input)) {
			case 'boolean':
				return $input ? 'true' : 'false';
			case 'integer':
				return (string)$input;
			case 'double':
			case 'float':
				return (string)$input;
			case 'NULL':
				return 'null';
			case 'string':
				return $this->encodeString($input);
			case 'array':
				return $this->_encodeArray($input);
			case 'object':
				return $this->_encodeArray(get_object_vars($input));
			default:
				return '';
		}
	}

	private function encodeString($input) {
		$output = preg_replace_callback('/[\b\t\n\f\r"\'\\\\]/', function ($matches) {
			switch ($matches[0]) {
				case "\b": return "\\b";
				case "\t": return "\\t";
				case "\n": return "\\n";
				case "\f": return "\\f";
				case "\r": return "\\r";
				case '"': return '\"';
				case '\'': return "\\'";
				case '\\': return '\\\\';
			}
		}, $input);

		$output = preg_replace_callback('/[^\x20-\x7E]/u', function ($matches) {
			$char = $matches[0];
			$utf16 = mb_convert_encoding($char, 'UTF-16BE', 'UTF-8');
			$hex = strtoupper(bin2hex($utf16));
			return "\\u" . str_pad($hex, 4, '0', STR_PAD_LEFT);
		}, $output);

		return '"' . $output . '"';
	}

	private function _encodeArray($input) {
		$isIndexed = array_keys($input) === range(0, count($input) - 1);
		$elements = array();

		foreach ($input as $key => $value) {
			if ($isIndexed) {
				$elements[] = $this->encode($value);
			} else {
				$elements[] = $this->encodeString((string)$key) . ':' . $this->encode($value);
			}
		}

		return $isIndexed ? '[' . implode(',', $elements) . ']' : '{' . implode(',', $elements) . '}';
	}
}
