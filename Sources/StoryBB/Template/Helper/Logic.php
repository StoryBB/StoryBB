<?php

/**
 * This class provides logic helpers for StoryBB's templates.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Template\Helper;

/**
 * This class provides logic helpers for StoryBB's templates.
 */
class Logic
{
	/**
	 * List the different helpers available in this class.
	 * @return array Helpers, assocating name to method
	 */
	public static function _list()
	{
		return ([
			'eq'  => 'StoryBB\\Template\\Helper\\Logic::eq',
			'eq_coerce'  => 'StoryBB\\Template\\Helper\\Logic::eq_coerce',
			'neq' => 'StoryBB\\Template\\Helper\\Logic::ne',
			'lt'  => 'StoryBB\\Template\\Helper\\Logic::lt',
			'gt'  => 'StoryBB\\Template\\Helper\\Logic::gt',
			'lte' => 'StoryBB\\Template\\Helper\\Logic::lte',
			'gte' => 'StoryBB\\Template\\Helper\\Logic::gte',
			'not' => 'StoryBB\\Template\\Helper\\Logic::not',
			'and' => 'StoryBB\\Template\\Helper\\Logic::op_and',
			'or'  => 'StoryBB\\Template\\Helper\\Logic::op_or',
		]);
	}

	/**
	 * Returns true if arg1 === arg2, otherwise returns false
	 * Usage: {{#if (eq arg1 arg2)}}
	 * @param mixed $arg1 First argument
	 * @param mixed $arg2 Second argument
	 * @return bool True if the two are identically valued (=== comparison)
	 */
	public static function eq($arg1, $arg2) 
	{
		return $arg1 === $arg2;
	}
	
	/**
	 * Returns true if arg1 == arg2, otherwise returns false
	 * Usage: {{#if (eq arg1 arg2)}}
	 * @param mixed $arg1 First argument
	 * @param mixed $arg2 Second argument
	 * @return bool True if the two are loosely equal valued (== comparison)
	 */
	public static function eq_coerce($arg1, $arg2) 
	{
		return $arg1 == $arg2;
	}

	/**
	 * Returns true if arg1 !== arg2, otherwise returns false
	 * Usage: {{#if (ne arg1 arg2)}}
	 * @param mixed $arg1 First argument
	 * @param mixed $arg2 Second argument
	 * @return bool True if the two are not identically valued (!== comparison)
	 */
	public static function ne($arg1, $arg2) 
	{
		return $arg1 !== $arg2;
	}

	/**
	 * Returns true if arg1 < arg2, otherwise returns false
	 * Usage: {{#if (lt arg1 arg2)}}
	 * @param mixed $arg1 First argument
	 * @param mixed $arg2 Second argument
	 * @return bool True if $arg1 is less than $arg2
	 */
	public static function lt($arg1, $arg2)
	{
		return $arg1 < $arg2;
	}

	/**
	 * Returns true if arg1 > arg2, otherwise returns false
	 * Usage: {{#if (gt arg1 arg2)}}
	 * @param mixed $arg1 First argument
	 * @param mixed $arg2 Second argument
	 * @return bool True if $arg1 is greater than $arg2
	 */
	public static function gt($arg1, $arg2)
	{
		return $arg1 > $arg2;
	}

	/**
	 * Returns true if arg1 <= arg2, otherwise returns false
	 * Usage: {{#if (lte arg1 arg2)}}
	 * @param mixed $arg1 First argument
	 * @param mixed $arg2 Second argument
	 * @return bool True if $arg1 is less than or equal to $arg2
	 */
	public static function lte($arg1, $arg2)
	{
		return $arg1 <= $arg2;
	}

	/**
	 * Returns true if arg1 >= arg2, otherwise returns false
	 * Usage: {{#if (ne arg1 arg2)}}
	 * @param mixed $arg1 First argument
	 * @param mixed $arg2 Second argument
	 * @return bool True if $arg1 is greater than or equal to $arg2
	 */
	public static function gte($arg1, $arg2)
	{
		return $arg1 >= $arg2;
	}

	/**
	 * Returns negation of arg1
	 * Usage: {{#if (not arg1)}}
	 * @param mixed $arg1 First argument
	 * @return bool True if $arg1 is falsy/empty
	 */
	public static function not($arg1)
	{
		return !$arg1;
	}

	/**
	 * Boolean AND - return true if all arguments evaluate to true
	 * Usage: {{#if (and arg1 arg2 .. argN)}}
	 * @param mixed $args An array of arguments to compare, last argument is context supplied by Lightncandy
	 * @return bool True if all arguments (except Lnc context) evaluate to true
	 */
	public static function op_and(...$args)
	{
		$context = array_pop($args);
		
		foreach ($args as $arg) 
		{
			if ($arg == false) 
			{
				// Once one argument returns false, return false and don't compare other arguments
				return false;
			}
		}
		// No argument returned false (all evaluated to true) - return true
		return true;
	}

	/**
	 * Boolean OR - return true if any argument evaluates to true
	 * Usage: {{#if (or arg1 arg2 .. argN)}}
	 * @param mixed $args An array of arguments to compare, last argument is context supplied by Lightncandy
	 * @return bool True if any arguments (except Lnc context) evaluate to true
	 */
	public static function op_or(...$args) 
	{
		$context = array_pop($args);
		
		foreach ($args as $arg) 
		{
			if ($arg == true) 
			{
				// Once one argument returns true, return true and don't compare other arguments
				return true;
			}
		}
		// No argument returned true (all evaluated to false) - return false
		return false;
	}
}
