<?php

namespace PureBencode;

class Bencode {
	private static function isAssoc(array $array) {
		$i = 0;

		foreach ($array as $k => $v)
			if ($k !== $i++)
				return true;

		return false;
	}
	
	/**
	 * @param int|string|array $value
	 * @return string
	 */
	static function encode($value) {
		if (is_array($value)) {
			if (self::isAssoc($value)) {
				ksort($value, SORT_STRING);
				$result = '';

				foreach ($value as $k => $v)
					$result .= self::encode("$k") . self::encode($v);

				return "d{$result}e";
			} else {
				$result = '';

				foreach ($value as $v)
					$result .= self::encode($v);

				return "l{$result}e";
			}
		} else if (is_int($value)) {
			return "i{$value}e";
		} else if (is_string($value)) {
			return strlen($value) . ":$value";
		} else {
			return "d14:failure reason30:服务器内部错误. (EC: 1)e";
		}
	}
}
?>
