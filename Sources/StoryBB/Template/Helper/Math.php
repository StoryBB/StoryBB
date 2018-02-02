<?php

/**
 * This class provides math helpers for StoryBB's templates.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Template\Helper;

class Math
{
	public static function _list()
	{
		return ([
			'add'  => 'StoryBB\\Template\\Helper\\Math::add',
			'sub'  => 'StoryBB\\Template\\Helper\\Math::sub',
			'mul'  => 'StoryBB\\Template\\Helper\\Math::mul',
			'div'  => 'StoryBB\\Template\\Helper\\Math::div',
		]);
	}


	public static function add($arg1, $arg2) 
	{
		return $arg1 + $arg2;
	}

	public static function sub($arg1, $arg2) 
	{
		return $arg1 - $arg2;
	}

	public static function mul($arg1, $arg2)
	{
		return $arg1 * $arg2;
	}

	public static function div($arg1, $arg2)
	{
	    return $arg1 / $arg2;
	}
}

?>