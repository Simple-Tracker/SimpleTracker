<?php
function isAssoc(array $arr): bool {
    if (array() === $arr) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
}
function GenerateSingleBencode($key, $ele): string {
	$str = '';
	if (!is_integer($key)) {
		$str .= GenerateSingleBencode(0, $key);
	}
	if (is_string($ele)) {
		$str .= strlen($ele) . ":{$ele}";
	} else if (is_integer($ele)) {
		$str .= "i{$ele}e";
	} else if (is_array($ele)) {
		$f = true;
		foreach ($ele as $key2 => $value2) {
			if (!is_integer($key2)) {
				if ($f) {
					$f = false;
					$str .= 'd';
				}
				$str .= GenerateSingleBencode(0, $key2);
			}
			if ($f) {
				$f = false;
				$str .= 'l';
			}
			if (!is_array($value2)) {
				$str .= GenerateSingleBencode(0, $value2);
				continue;
			}
			$str .= (!isAssoc($value2)) ? 'l' : 'd';
			foreach ($value2 as $key3 => $value3) {
				$str .= GenerateSingleBencode($key3, $value3);
			}
			$str .= 'e';
		}
		if ($f) {
			$str .= 'le';
		}
		$str .= 'e';
	}
	return $str;
}
function GenerateBencode(array $arr): string {
	$str = 'd';
	foreach ($arr as $key => $value) {
		$str .= GenerateSingleBencode($key, $value);
	}
	$str .= 'e';
	return "{$str}\n";
}
?>
