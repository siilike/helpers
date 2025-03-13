<?php
/*
 * Copyright 2015-present, Lauri Keel
 * All rights reserved.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

use CommonX\DuplicateKeyException;
use CommonX\IllegalArgumentException;

/******************************************************************************/

define('SEC_MINUTE', 60);
define('SEC_HOUR', SEC_MINUTE*60);
define('SEC_DAY', SEC_HOUR*24);
define('SEC_WEEK', SEC_DAY*7);
define('SEC_MONTH', SEC_DAY*30);
define('SEC_YEAR', SEC_DAY*365);

define('MSEC_MINUTE', SEC_MINUTE*1000);
define('MSEC_HOUR', SEC_HOUR*1000);
define('MSEC_DAY', SEC_DAY*1000);
define('MSEC_WEEK', SEC_WEEK*1000);
define('MSEC_MONTH', SEC_MONTH*1000);
define('MSEC_YEAR', SEC_YEAR*1000);

/******************************************************************************/

function random_string($length)
{
	return substr(str_replace([ "/", "+", "=" ], '1', base64_encode(random_bytes($length))), 0, $length);
}

function random_string_url($length, $userFriendly = false)
{
	static $boundaryCharsFull = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
	static $allCharsFull = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_-.";

	static $boundaryCharsFriendly = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";
	static $allCharsFriendly = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz_-.";

	$boundaryChars = $userFriendly ? $boundaryCharsFriendly : $boundaryCharsFull;
	$allChars = $userFriendly ? $allCharsFriendly : $allCharsFull;

	if($length < 3)
	{
		throw new \CommonX\IllegalArgumentException();
	}

	$ret = $boundaryChars[random_int(0, strlen($boundaryChars)-1)];

	for($i = 0, $max = $length-2; $i < $max; $i++)
	{
		$ret .= $allChars[random_int(0, strlen($allChars)-1)];
	}

	$ret .= $boundaryChars[random_int(0, strlen($boundaryChars)-1)];

	return $ret;
}

/******************************************************************************/

function escape_shell_arg($a): string
{
	if(is_numeric($a))
	{
		$a = strval($a);
	}
	else if(!is_string($a))
	{
		throw new \CommonX\IllegalStateException("Shell argument is not a string");
	}

	if($a[0] === '-')
	{
		throw new \CommonX\IllegalStateException("Shell argument starting with a dash");
	}

	return "'" . str_replace("'", "'\\''", $a) . "'";
}

function exec2($cmd, $cwd = '.', $stdin = null, $env = [])
{
	if(is_array($cmd))
	{
		$cmd = join(" ", array_map('escape_shell_arg', $cmd));
	}

	if(!$cwd)
	{
		$cwd = storage_path('temp');
	}

	trace("Executing {} in {}", $cmd, $cwd);

	$start = microtime(true);

	$cmd = 'bash -c -l -- '.escape_shell_arg($cmd);

	$proc = proc_open($cmd,
	[
		0 => [ "pipe", "r" ],
		1 => [ "pipe", "w" ],
		2 => [ "pipe", "w" ],
	], $pipes, $cwd, $env);

	if(is_resource($proc))
	{
		if($stdin != null)
		{
			fwrite($pipes[0], $stdin);
		}

		fclose($pipes[0]);

		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);

		$code = -1;
		$stdout = '';
		$stderr = '';

		$write = [];
		$except = [];

		while(true)
		{
			$status = proc_get_status($proc);

			if(empty($status['running']))
			{
				$code = $status['exitcode'];
				break;
			}

			$read =
			[
				$pipes[1],
				$pipes[2],
			];

			\stream_select($read, $write, $except, 0, 200000);

			$stdout .= stream_get_contents($pipes[1]);
			$stderr .= stream_get_contents($pipes[2]);
		}

		fclose($pipes[1]);
		fclose($pipes[2]);

		proc_close($proc);

		$ret =
		[
			'cmd' => $cmd,
			'stdout' => $stdout,
			'stderr' => $stderr,
			'code' => $code,
		];
	}
	else
	{
		$ret =
		[
			'cmd' => $cmd,
			'code' => -1
		];
	}

	$time = microtime(true) - $start;

	debug("Executed {} with result {} in {}ms", $cmd, $ret['code'], round($time*1000));

	if(!empty($ret['stderr']))
	{
		trace("Stderr is {}", $ret['stderr']);
	}
	else
	{
		trace0("Stdout is {}", $ret['stdout']);
		trace0("Stderr is {}", $ret['stderr']);
	}

	return $ret;
}

/******************************************************************************/

function array_merge_recursive2(...$arrays)
{
	$ret = [];

	foreach($arrays as $a)
	{
		if(!is_array($a))
		{
			throw new IllegalArgumentException("Argument is not an array");
		}

		if(empty($a))
		{
			continue;
		}
		else if(array_is_list($a))
		{
			if(!empty($ret) && !array_is_list($ret))
			{
				throw new IllegalArgumentException("Target is not a list");
			}

			$ret = array_merge($ret, $a);
		}
		else
		{
			if(!empty($ret) && array_is_list($ret))
			{
				throw new IllegalArgumentException("Target is not an associative array");
			}

			foreach($a as $b => $c)
			{
				if(array_key_exists($b, $ret))
				{
					if(is_array($c) && $ret[$b] !== null)
					{
						$ret[$b] = array_merge_recursive2($ret[$b], $c);
					}
					else
					{
						$ret[$b] = $c;
					}
				}
				else
				{
					$ret[$b] = $c;
				}
			}
		}
	}

	return $ret;
}

/*
 * $a = [];
 * $b = [ '123' => 5 ];
 * var_dump(array_merge($a, $b));
 * array(1) {
 *  [0]=>
 *  int(5)
 * }
 */
function array_merge_assoc(...$arrays)
{
	$ret = [];

	foreach($arrays as $a)
	{
		foreach($a as $b => $c)
		{
			$ret[$b] = $c;
		}
	}

	return $ret;
}

function array_merge_exclusive(...$arrays)
{
	$ret = [];

	foreach($arrays as $a)
	{
		if($a === null)
		{
			continue;
		}

		foreach($a as $b => $c)
		{
			if(array_key_exists($b, $ret))
			{
				throw new IllegalArgumentException("Duplicate key: $b");
			}

			$ret[$b] = $c;
		}
	}

	return $ret;
}

function array_unique_strict($a, $strict = true): array
{
	return array_values(array_filter($a, fn($v, $k) => array_search($v, $a, $strict) === $k, ARRAY_FILTER_USE_BOTH));
}

if(!function_exists('array_find'))
{
	function array_find($a, $fn)
	{
		foreach($a as $k => $b)
		{
			if(call_user_func($fn, $b, $k) === true)
			{
				return $b;
			}
		}

		return null;
	}
}

function array_find_index($a, $fn): string|int|null
{
	foreach($a as $k => $b)
	{
		if(call_user_func($fn, $b, $k) === true)
		{
			return $k;
		}
	}

	return null;
}

function array_entries($a): array
{
	$ret = [];

	foreach($a as $k => $v)
	{
		$ret[] = [ $k, $v ];
	}

	return $ret;
}

function array_from_entries($a, $allowDuplicates = false): array
{
	$ret = [];

	foreach($a as $b)
	{
		if(!$allowDuplicates && array_key_exists($b[0], $ret))
		{
			throw new DuplicateKeyException($b[0]);
		}

		$ret[$b[0]] = $b[1];
	}

	return $ret;
}

function array_filter_nulls($a): array
{
	return array_filter($a, fn($a) => $a !== null);
}

function in_array_any(array $needles, array $haystack): bool
{
	return !empty(array_intersect($haystack, $needles));
}

function in_array2(array $needle, array $haystack): bool
{
	foreach($haystack as $a)
	{
		if(in_array($needle, $a))
		{
			return true;
		}
	}

	return false;
}

function in_array_any2(array $needles, array $haystack): bool
{
	foreach($haystack as $a)
	{
		if(in_array_any($needles, $a))
		{
			return true;
		}
	}

	return false;
}

function array_map_keys($fn, array $a): array
{
	$ret = [];

	foreach($a as $b => $c)
	{
		$ret[call_user_func($fn, $b)] = $c;
	}

	return $ret;
}

function array_map_values($fn, array $a): array
{
	return array_values(array_map($fn, $a));
}

function array_reindex($fn, array $a, $allowDuplicates = true): array
{
	$ret = [];

	$ignored = [];
	foreach($a as $b => $c)
	{
		$k = call_user_func($fn, $c);

		if(array_key_exists($k, $ret))
		{
			if($allowDuplicates === false)
			{
				throw new DuplicateKeyException($k);
			}
			else if($allowDuplicates === null)
			{
				$ignored[] = $k;
			}
		}

		$ret[$k] = $c;
	}

	if(!empty($ignored))
	{
		foreach($ignored as $k)
		{
			unset($ret[$k]);
		}
	}

	return $ret;
}

function array_reindex2($fn, array $a): array
{
	$ret = [];

	foreach($a as $b => $c)
	{
		$k = call_user_func($fn, $c);

		$ret[$k][] = $c;
	}

	return $ret;
}

function iterator_map($fn, \Traversable $a): array
{
	$ret = [];

	foreach($a as $b)
	{
		$ret[] = $fn($b);
	}

	return $ret;
}

function trim_recursive($input, $emptyToNull = true)
{
	if(!is_array($input))
	{
		if(is_string($input))
		{
			$ret = trim((string) $input);

			if($emptyToNull && $ret === '')
			{
				$ret = null;
			}

			return $ret;
		}

		return $input;
	}

	return array_map(fn($a) => trim_recursive($a, $emptyToNull), $input);
}

/******************************************************************************/

function mtime(): int
{
	return (int)(microtime(true)*1000);
}

function mdate($format, $time = null)
{
	return date($format, (int)(($time ?? 0)/1000));
}

function ms_to_datetime($ms, $timezone = null): DateTime
{
	if($timezone === null)
	{
		$timezone = new \DateTimeZone('UTC');
	}
	else if(!$timezone instanceof \DateTimeZone)
	{
		$timezone = new \DateTimeZone($timezone);
	}

	return new \DateTime(mdate('Y-m-d H:i:s', $ms), $timezone);
}

/******************************************************************************/

function maybe_shorten($input, $maxLength = 50)
{
	if($input === null)
	{
		return null;
	}

	if(mb_strlen($input) > $maxLength)
	{
		return mb_substr($input, 0, $maxLength-3) . '...';
	}

	return $input;
}

function ensure_prefix($prefix, $str)
{
	if(strpos($str, $prefix) === 0)
	{
		return $str;
	}

	return $prefix . $str;
}

function transliterate($input)
{
	static $tr;

	if(!$tr)
	{
		$tr = Transliterator::createFromRules(':: Any-Latin; :: Latin-ASCII; :: NFD; :: [[:Nonspacing Mark:][:separator:][:punctuation:]] Remove; :: Lower(); :: NFC;', Transliterator::FORWARD);
	}

	return preg_replace('#[^[:alnum:]]+#', '', $tr->transliterate($input));
}
