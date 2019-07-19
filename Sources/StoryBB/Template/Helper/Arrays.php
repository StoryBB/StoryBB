<?php

/**
 * This class provides array helpers for StoryBB's templates.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Template\Helper;

/**
 * This class provides array helpers for StoryBB's templates.
 */
class Arrays
{
	/**
	 * List the different helpers available in this class.
	 * @return array Helpers, assocating name to method
	 */
	public static function _list()
	{
		return ([
			'join' => 'StoryBB\\Template\\Helper\\Arrays::join',
			'in_array' => 'StoryBB\\Template\\Helper\\Arrays::in_array',
			'is_array' => 'StoryBB\\Template\\Helper\\Arrays::is_array',
			'array_key_exists' => 'StoryBB\\Template\\Helper\\Arrays::array_key_exists',
			'getNumItems' => 'StoryBB\\Template\\Helper\\Arrays::count',
			'count' => 'StoryBB\\Template\\Helper\\Arrays::count',
			'keys' => 'StoryBB\\Template\\Helper\\Arrays::keys',
			'array2js' => 'StoryBB\\Template\\Helper\\Arrays::array2js',
		]);
	}

	/**
	 * Accepts an array of items, return a string of them joined together for template purposes.
	 * @param array $array An item of things to join together
	 * @param string $sep A string to glue array items together
	 * @return string The imploded string
	 */
	public static function join($array, $sep = '')
	{
		return implode($sep, $array);
	}

	/**
	 * Identifies if a given item is in a given array for template purposes.
	 * @param mixed $item The item to match in an array
	 * @param array $array The array to match inside
	 * @return bool True if the item is in the array
	 */
	public static function in_array($item, $array)
	{
		return in_array($item, $array);
	}

	/**
	 * Identifies if the item supplied is an array for template purposes.
	 * @param mixed $item An item to type-check
	 * @return bool True if $item is an array
	 */
	public static function is_array($item)
	{
		return is_array($item);
	}

	/**
	 * Identifies if the 'needle' item exists as a key in the 'haystack'.
	 *
	 * @param array $haystack An array to look for an item
	 * @param string|int $needle An item to look for as a key
	 * @return bool True if $needle is a key in $haystack
	 */
	public static function array_key_exists($haystack, $needle): bool
	{
		if (!is_array($haystack) || (!is_int($needle) && (!is_string($needle))))
		{
			return false;
		}

		return isset($haystack[$needle]);
	}

	/**
	 * Returns the number of items in an array for template purposes.
	 * @param array $array An array
	 * @return int The number of items in the array
	 */
	public static function count($array)
	{
		return count($array);
	}

	/**
	 * Export an array's values for JS purposes in templates.
	 * @param array $array An array to export
	 * @return SafeString A string containing the array in JSON, in a form that won't be escaped by the template
	 */
	public static function array2js($array)
	{
		return new \LightnCandy\SafeString(json_encode(array_values($array)));
	}

	/**
	 * Export an array's keys for template purposes.
	 * @param array $array An array whose keys should be exported
	 * @return array An array of $array's keys
	 */
	public static function keys($array)
	{
		return array_keys($array);
	}
}
