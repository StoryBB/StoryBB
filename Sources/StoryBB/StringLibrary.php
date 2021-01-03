<?php

/**
 * This file does a lot of important stuff.  Mainly, this means it handles
 * the query string, request variables, and session management.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB;

class StringLibrary
{
	const ENTITY_LIST = '&(?:#\d{1,7}|quot|amp|lt|gt|nbsp);';
	const SPACE_CHARS = '\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}';

	/**
	 * Strips out invalid html entities, replaces others with html style &#123; codes
	 *
	 * Callback function used with preg_replace_callback in various places.
	 *
	 * @param array $matches An array of matches (relevant info should be the 3rd item in the array)
	 * @return string The fixed string
	 */
	public static function fix_entities($matches)
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

	public static function entity_check($string)
	{
		return preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', ['StoryBB\\StringLibrary', 'fix_entities'], $string);
	}

	public static function toLower($string)
	{
		return mb_strtolower($string, 'UTF-8');
	}

	public static function toUpper($string)
	{
		return mb_strtoupper($string, 'UTF-8');
	}

	public static function substr($string, $start, $length = null)
	{
		$ent_arr = preg_split('~(&#\d{1,7};|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', self::entity_check($string), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		return $length === null ? implode('', array_slice($ent_arr, $start)) : implode('', array_slice($ent_arr, $start, $length));
	}

	public static function ucfirst($string)
	{
		return self::toUpper(self::substr($string, 0, 1)) . self::substr($string, 1);
	}

	public static function ucwords($string)
	{
		$words = preg_split('~([\s\r\n\t]+)~', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
		for ($i = 0, $n = count($words); $i < $n; $i += 2)
		{
			$words[$i] = self::ucfirst($words[$i]);
		}
		return implode('', $words);
	}

	public static function strlen($string)
	{
		return strlen(preg_replace('~' . self::ENTITY_LIST . '|.~u', '_', self::entity_check($string)));
	}

	public static function strpos($haystack, $needle, $offset = 0)
	{
		$haystack_arr = preg_split('~(&#\d{1,7};|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', self::entity_check($haystack), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		if (strlen($needle) === 1)
		{
			$result = array_search($needle, array_slice($haystack_arr, $offset));
			return is_int($result) ? $result + $offset : false;
		}
		else
		{
			$needle_arr = preg_split('~(&#\d{1,7};|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', self::entity_check($needle), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			$needle_size = count($needle_arr);

			$result = array_search($needle_arr[0], array_slice($haystack_arr, $offset));
			while ((int) $result === $result)
			{
				$offset += $result;
				if (array_slice($haystack_arr, $offset, $needle_size) === $needle_arr)
				{
					return $offset;
				}
				$result = array_search($needle_arr[0], array_slice($haystack_arr, ++$offset));
			}
			return false;
		}
	}

	public static function truncate($string, $length)
	{
		$string = self::entity_check($string);
		preg_match('~^(' . self::ENTITY_LIST . '|.){' . self::strlen(substr($string, 0, $length)) . '}~u', $string, $matches);
		$string = $matches[0];
		while (strlen($string) > $length)
		{
			$string = preg_replace('~(?:' . self::ENTITY_LIST . '|.)$~u', '', $string);
		}
		return $string;
	}

	public static function htmltrim($string)
	{
		return preg_replace('~^(?:[ \t\n\r\x0B\x00' . self::SPACE_CHARS . ']|&nbsp;)+|(?:[ \t\n\r\x0B\x00' . self::SPACE_CHARS . ']|&nbsp;)+$~u', '', self::entity_check($string));
	}

	public static function escape($string, $quote_style = ENT_COMPAT, $charset = 'UTF-8')
	{
		return self::entity_check(htmlspecialchars($string, $quote_style, $charset));
	}
}
