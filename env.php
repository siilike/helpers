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

use Dotenv\Environment\Adapter\EnvConstAdapter;
use Dotenv\Environment\Adapter\PutenvAdapter;
use Dotenv\Environment\DotenvFactory;
use Dotenv\Repository\RepositoryBuilder;

function feature_enabled($which, $default = null)
{
	$disableKey = "DISABLE_".strtoupper($which);
	$disabled = env($disableKey);

	$enableKey = "ENABLE_".strtoupper($which);
	$enabled = env($enableKey);

	$result = array_filter_nulls(
	[
		$enabled !== null ? !empty($enabled) : null,
		$disabled !== null ? empty($disabled) : null,
	]);

	if(empty($result))
	{
		return $default;
	}
	else if(count($result) === 1)
	{
		return current($result);
	}
	else if($result[0] !== $result[1])
	{
		error("Both {} and {} defined and do not match!", $disableKey, $enableKey);
		return false;
	}
	else
	{
		warn("Both {} and {} defined!", $disableKey, $enableKey);
		return $result[0];
	}
}

function env($key, $default = null)
{
	static $variables;

	if($variables === null)
	{
		$builder = RepositoryBuilder::createWithDefaultAdapters();
		$builder = $builder->addAdapter(\Dotenv\Repository\Adapter\EnvConstAdapter::class);
		$builder = $builder->addAdapter(\Dotenv\Repository\Adapter\PutenvAdapter::class);

		$variables = $builder->immutable()->make();
	}

	return \PhpOption\Option::fromValue($variables->get($key))->map(function($value)
	{
		switch(strtolower($value))
		{
			case 'true':
				return true;
			case 'false':
				return false;
			case 'null':
				return null;
		}

		return $value;
	})->getOrCall(fn() => value($default));
}
