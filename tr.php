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

function render_variables($msg, $vars = null)
{
	if(empty($vars))
	{
		return $msg;
	}

	return preg_replace_callback('/{\s*([a-zA-Z0-9_\-]+?)\s*}/', fn($a) => array_key_exists($a[1], $vars) ? $vars[ $a[1] ] : $a[0], $msg);
}

function tr($string, $vars = null)
{
	if($string === null)
	{
		return null;
	}

	$string = _($string);

	if($vars === null)
	{
		return $string;
	}

	return MessageFormatter::formatMessage(get_current_locale(), $string, $vars);
}

function trh($msg, $vars = null)
{
	$data = [];

	$html = $msg;

	$translatable = tr2translatable($msg, $data);

	if($translatable !== false)
	{
		$translated = tr($translatable);

		if($translated !== $translatable)
		{
			$html = translatable2tr($translated, $data);

			if($html === false)
			{
				$html = $msg;
			}
		}
	}

	if(!empty($vars))
	{
		$html = render_variables($html, $vars);
	}

	return $html;
}

function tr2translatable($msg, &$data = null)
{
	$d = new \DOMDocument();
	$d->loadHTML($msg);

	$ret = '';
	$counter = 0;

	tr2translatable0($counter, $d->lastChild->lastChild->childNodes, $ret, $data);

	return $ret;
}

function tr2translatable0(&$idx, $nodes, &$ret, &$data)
{
	foreach($nodes as $a)
	{
		if(isset($a->tagName))
		{
			$curIdx = ++$idx;

			if($data !== null)
			{
				$data[$curIdx] =
				[
					'tagName' => $a->tagName,
					'attributes' => $a->attributes,
				];
			}

			if(empty($a->childNodes) || $a->childNodes->count() === 0)
			{
				$ret .= '<e'.$curIdx.'/>';
			}
			else
			{
				$ret .= '<e'.$curIdx.'>';

				tr2translatable0($idx, $a->childNodes, $ret, $data);

				$ret .= '</e'.$curIdx.'>';
			}
		}
		else
		{
			$ret .= $a->wholeText;
		}
	}
}

function translatable2tr($msg, $data)
{
	$d = new \DOMDocument();
	@$d->loadHTML($msg);

	$ret = '';

	if(translatable2tr0($d->lastChild->lastChild->childNodes, $ret, $data) === false)
	{
		return false;
	}

	return $ret;
}

function translatable2tr0($nodes, &$ret, $data)
{
	foreach($nodes as $a)
	{
		if(isset($a->tagName))
		{
			$idx = (int)substr($a->tagName, 1);

			if(empty($data[$idx]))
			{
				return false;
			}

			$d = $data[$idx];

			$ret .= '<'.$d['tagName'];

			if(!empty($d['attributes']))
			{
				foreach($d['attributes'] as $name => $attr)
				{
					$ret .= ' '.$name.(!empty($attr->textContent) ? '="'.$attr->textContent.'"' : '');
				}
			}

			if(empty($a->childNodes) || $a->childNodes->count() === 0)
			{
				$ret .= '/>';
			}
			else
			{
				$ret .= '>';

				translatable2tr0($a->childNodes, $ret, $data);

				$ret .= '</'.$d['tagName'].'>';
			}
		}
		else
		{
			$ret .= $a->wholeText;
		}
	}
}
