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

use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\HeaderUtils;

function get_disposition($fileName): string
{
	return HeaderUtils::makeDisposition('attachment', preg_replace('#[/\\\]+#', '_', $fileName), preg_replace('#[^a-zA-Z0-9\._ ]+#', '_', $fileName));
}

function streamed_file_response($filePath, $args = null)
{
	return new \Symfony\Component\HttpFoundation\StreamedResponse(function() use($filePath, $args)
	{
		$out = fopen('php://output', 'w');
		$fh = fopen($filePath, 'r');

		stream_copy_to_stream($fh, $out);

		fclose($fh);
		fclose($out);

		if(@$args['deleteAfter'])
		{
			unlink($filePath);
		}
	}, 200, array_merge(
	[
		'Content-Length' => filesize($filePath),
		'Content-Type' => @$args['contentType'] ?? 'application/octet-stream',
		'Content-Disposition' => get_disposition(@$args['name'] ?? 'file'),
	], @$args['headers'] ?? []));
}

function streamed_pdf_response($filePath, $args = null)
{
	return streamed_file_response($filePath, array_merge(
	[
		'contentType' => 'application/pdf',
		'name' => 'file.pdf',
	], @$args ?? []));
}

/******************************************************************************/

// @deprecated
function closureValidator($c)
{
	return new \Illuminate\Validation\ClosureValidationRule($c);
}

class IlluminateUtf8Validator implements \Illuminate\Contracts\Validation\Rule
{
	public function passes($attribute, $value)
	{
		return mb_check_encoding($value, 'UTF-8');
	}

	public function message()
	{
		return ':attribute must be valid UTF-8.';
	}
}

function validate($input, $rules, $messages = [], $customAttributes = [], $keepEmptyValues = false, $trimInput = true)
{
	if($trimInput)
	{
		$input = trim_recursive($input, !$keepEmptyValues);
	}

	$validator = \Illuminate\Support\Facades\Validator::make($input, $rules, $messages, $customAttributes);

	if($validator->fails())
	{
		$error = $validator->errors()->first();

		throw new \CommonX\IllegalArgumentException($error);
	}

	return validated_data($validator);
}

function validated_data($validator)
{
	$data = $validator->getData();
	$rules = $validator->getRules();

	ksort($rules);

	$rules0 = [];
	foreach(array_keys($rules) as $a)
	{
		Arr::set($rules0, $a, true);
	}

	validated_data0($data, $rules0);

	return $data;
}

function validated_data0(&$data, $rules)
{
	foreach($data as $k => &$v)
	{
		$hasRules = array_key_exists($k, $rules);

		if(!$hasRules)
		{
			unset($data[$k]);
			continue;
		}

		if(is_array($v) && is_array($rules[$k]))
		{
			validated_data0($v, $rules[$k]);
		}
	} unset($v);
}
