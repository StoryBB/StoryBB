<?php

/**
 * This class provides math helpers for StoryBB's templates.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Template\Helper;

/**
 * This class provides math helpers for StoryBB's templates.
 */
class Math
{
	/**
	 * List the different helpers available in this class.
	 * @return array Helpers, assocating name to method
	 */
	public static function _list()
	{
		return ([
			'add'  => 'StoryBB\\Template\\Helper\\Math::add',
			'sub'  => 'StoryBB\\Template\\Helper\\Math::sub',
			'mul'  => 'StoryBB\\Template\\Helper\\Math::mul',
			'div'  => 'StoryBB\\Template\\Helper\\Math::div',
		]);
	}

	/**
	 * Adds arguments
	 * Usage: {{(add arg1 arg2)}}
	 * @param int $arg1 First argument
	 * @param int $arg2 Second argument
	 * @return int Sum of arguments
	 */
	public static function add($arg1, $arg2) 
	{
		return $arg1 + $arg2;
	}

	/**
	 * Subtract arguments
	 * Usage: {{(sub arg1 arg2)}}
	 * @param int $arg1 First argument
	 * @param int $arg2 Second argument
	 * @return int Remainder of $arg1 subtract $arg2
	 */
	public static function sub($arg1, $arg2) 
	{
		return $arg1 - $arg2;
	}

	/**
	 * Multiply arguments
	 * Usage: {{(mul arg1 arg2)}}
	 * @param int $arg1 First argument
	 * @param int $arg2 Second argument
	 * @return int $arg1 times $arg2
	 */
	public static function mul($arg1, $arg2)
	{
		return $arg1 * $arg2;
	}

	/**
	 * Divides arguments
	 * Usage: {{(div arg1 arg2)}}
	 * @param int $arg1 First argument
	 * @param int $arg2 Second argument
	 * @return float $arg1 divided by $arg2
	 */
	public static function div($arg1, $arg2)
	{
		return $arg1 / $arg2;
	}
}
