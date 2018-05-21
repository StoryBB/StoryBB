<?php

/**
 * This class provides array helpers for StoryBB's templates.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Template\Helper;

class Arrays
{
	public static function _list()
	{
		return ([
			'join' => 'StoryBB\\Template\\Helper\\Arrays::join',
			'in_array' => 'StoryBB\\Template\\Helper\\Arrays::in_array',
			'is_array' => 'StoryBB\\Template\\Helper\\Arrays::is_array',
			'getNumItems' => 'StoryBB\\Template\\Helper\\Arrays::count',
			'count' => 'StoryBB\\Template\\Helper\\Arrays::count',
			'keys' => 'StoryBB\\Template\\Helper\\Arrays::keys',
			'array2js' => 'StoryBB\\Template\\Helper\\Arrays::array2js',
		]);
	}

	public static function join($array, $sep = '')
	{
		return implode($sep, $array);
	}

	public static function in_array($item, $array)
	{
		return in_array($item, $array);
	}

	public static function is_array($item)
	{
		return is_array($item);
	}

	public static function count($array)
	{
		return count($array);
	}

	public static function array2js($array)
	{
		return new \LightnCandy\SafeString(json_encode(array_values($array)));
	}

	public static function keys($array)
	{
		return array_keys($array);
	}
}

?>