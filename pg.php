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

function array_to_pg_switch($field, $array, $default = null, $quoteProperty = true)
{
	$ret = 'CASE';

	foreach($array as $k => $v)
	{
		$ret .= ' WHEN '.($quoteProperty ? pg_quote_property($field) : $field).' = '.pg_quote($k).' THEN '.pg_quote($v);
	}

	$ret .= ' ELSE '.pg_quote($default);
	$ret .= ' END';

	return $ret;
}

function pg_quote($what)
{
	return app('db.pg')->quote($what);
}

function pg_quote_field($what)
{
	return app('db.pg')->quoteName($what);
}

function pg_quote_property($what)
{
	return pg_quote($what);
}
