<?php

/**
 * This class provides logic helpers for StoryBB's templates.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Template\Helper;

class Logic
{
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
			'and' => 'StoryBB\\Template\\Helper\\Logic::and',
			'or'  => 'StoryBB\\Template\\Helper\\Logic::or',
		]);
	}

	/*****
	 * Usage: {{#if (eq arg1 arg2)}}
	 * Returns true if arg1 === arg2, otherwise returns false
	 */
	public static function eq($arg1,$arg2) 
	{
		return $arg1 === $arg2;
	}
	
	/*****
	 * Usage: {{#if (eq arg1 arg2)}}
	 * Returns true if arg1 === arg2, otherwise returns false
	 */
	public static function eq_coerce($arg1,$arg2) 
	{
		return $arg1 == $arg2;
	}

	/*****
	 * Usage: {{#if (ne arg1 arg2)}}
	 * Returns true if arg1 !== arg2, otherwise returns false
	 */
	public static function ne($arg1,$arg2) 
	{
		return $arg1 !== $arg2;
	}

	/*****
	 * Usage: {{#if (lt arg1 arg2)}}
	 * Returns true if arg1 < arg2, otherwise returns false
	 */
	public static function lt($arg1,$arg2)
	{
		return $arg1 < $arg2;
	}

	/*****
	 * Usage: {{#if (gt arg1 arg2)}}
	 * Returns true if arg1 > arg2, otherwise returns false
	 */
	public static function gt($arg1,$arg2)
	{
	    return $arg1 > $arg2;
	}

	/*****
	 * Usage: {{#if (lte arg1 arg2)}}
	 * Returns true if arg1 <= arg2, otherwise returns false
	 */
	public static function lte($arg1,$arg2)
	{
		return $arg1 <= $arg2;
	}

	/*****
	 * Usage: {{#if (ne arg1 arg2)}}
	 * Returns true if arg1 >= arg2, otherwise returns false
	 */
	public static function gte($arg1,$arg2)
	{
	    return $arg1 >= $arg2;
	}

	/*****
	 * Usage: {{#if (not arg1)}}
	 * Returns negation of arg1
	 */
	public static function not($arg1)
	{
		return !$arg1;
	}

	/*****
	 * Usage: {{#if (and arg1 arg2 .. argN)}}
	 * Boolean AND - return true if all arguments evaluate to true
	 */
	public static function and() {

		$args = func_get_args();
		// Last element on function arguments is context - not an argument, remove before evaluating
		$context = array_pop($args);
		
		foreach ( $args as $arg ) 
		{
			if ( $arg == FALSE ) 
			{
				// Once one argument returns false, return false and don't compare other arguments
				return FALSE;
			}
		}
		// No argument returned false (all evaluated to true) - return true
		return TRUE;
	}

	/*****
	 * Usage: {{#if (or arg1 arg2 .. argN)}}
	 * Boolean OR - return true if any argument evaluates to true
	 */
	public static function or() 
	{
		 
		$args = func_get_args();
		// Last element on function arguments is context - not an argument, remove before evaluating
		$context = array_pop($args);
		
		foreach ( $args as $arg ) 
		{
			if ( $arg == TRUE ) 
			{
				// Once one argument returns true, return true and don't compare other arguments
				return TRUE;
			}
		}
		// No argument returned true (all evaluated to false) - return false
		return FALSE;
	}
}

?>