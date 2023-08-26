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

function get_current_locale()
{
	$locales = setlocale(LC_ALL, null);
	$locale = explode(".", $locales);
	$locale = $locale[0];

	if($locale === 'C')
	{
		$locale = get_default_locale();
	}

	return $locale;
}

function get_default_locale()
{
	return get_config_service()->getDefaultLocale();
}

function get_valid_locale($locale = null)
{
	if(!$locale)
	{
		return get_default_locale();
	}

	$availableLocales = get_config_service()->getAvailableLocales();

	if(!in_array($locale, $availableLocales))
	{
		return get_default_locale();
	}

	return $locale;
}

function set_locale($locale = null)
{
	if($locale === null)
	{
		$locale = get_default_locale();
	}
	else
	{
		$available = get_config_service()->getAvailableLocales();

		if(!in_array($locale, $available))
		{
			trace("Tried to set unavailable locale {}", $locale);

			set_locale0(get_default_locale());
			return false;
		}
	}

	set_locale0($locale);
	return true;
}

function set_locale0($locale)
{
	$systemLocale = null;

	if($locale === 'zh_Hans')
	{
		$systemLocale = setlocale(LC_ALL, "$locale.utf8", "$locale.UTF-8", "$locale", "zh_CN.utf8", "zh_CN.UTF-8");
	}
	else if($locale === 'zh_Hant')
	{
		$systemLocale = setlocale(LC_ALL, "$locale.utf8", "$locale.UTF-8", "$locale", "zh_TW.utf8", "zh_TW.UTF-8");
	}
	else
	{
		$systemLocale = setlocale(LC_ALL, "$locale.utf8", "$locale.UTF-8", "$locale");
	}

	if(!$systemLocale)
	{
		$defaultLocale = get_default_locale();

		if($defaultLocale !== $locale)
		{
			warn("Tried to select invalid locale {}", $locale);

			set_locale0($defaultLocale);
			return;
		}
	}

	putenv("LANG=$systemLocale");
	putenv("LANGUAGE=$systemLocale");
	putenv("LC_ALL=$systemLocale");

	app('translator')->setLocale($locale);
}

function reset_locale()
{
	set_locale0(get_default_locale());
}

function is_rtl($locale)
{
	return in_array(explode('_', $locale, 2)[0], [ 'ar', 'arc', 'dv', 'fa', 'ha', 'he', 'khv', 'ks', 'ku', 'ps', 'ur', 'yi' ]);
}
