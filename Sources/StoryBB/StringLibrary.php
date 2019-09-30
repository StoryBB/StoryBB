<?php

/**
 * This file does a lot of important stuff.  Mainly, this means it handles
 * the query string, request variables, and session management.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB;

class StringLibrary
{
	/**
	 * Strips out invalid html entities, replaces others with html style &#123; codes
	 *
	 * Callback function used with preg_replace_callback in various places.
	 *
	 * @param array $matches An array of matches (relevant info should be the 3rd item in the array)
	 * @return string The fixed string
	 */
	public static function fix_entities($string)
	{
		if (!isset($matches[2]))
		{
			return '';
		}

		$num = $matches[2][0] === 'x' ? hexdec(substr($matches[2], 1)) : (int) $matches[2];

		// we don't allow control characters, characters out of range, byte markers, etc
		if ($num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num == 0x202D || $num == 0x202E)
		{
			return '';
		}
		else
		{
			return '&#' . $num . ';';
		}
	}

	public static function toLower($string)
	{
		return mb_strtolower($string, 'UTF-8');
	}

	public static function toUpper($string)
	{
		return mb_strtoupper($string, 'UTF-8');
	}
}
