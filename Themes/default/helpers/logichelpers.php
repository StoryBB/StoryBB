<?php
/**
 * Logic Helpers for StoryBB
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Each of these evaluations can be "chained" with other evaluations, such as {{#if (not (and arg1 arg2))}} which would return !(arg1 && arg2)
 */

function logichelper_eq($arg1,$arg2) 
{
	/*****
	 * Usage: {{#if (eq arg1 arg2)}}
	 * Returns true if arg1 === arg2, otherwise returns false
	 */
	return $arg1 === $arg2;
}

function logichelper_ne($arg1,$arg2) 
{
	/*****
	 * Usage: {{#if (ne arg1 arg2)}}
	 * Returns true if arg1 !== arg2, otherwise returns false
	 */
	return $arg1 !== $arg2;
}

function logichelper_lt($arg1,$arg2)
{
	/*****
	 * Usage: {{#if (lt arg1 arg2)}}
	 * Returns true if arg1 < arg2, otherwise returns false
	 */
	return $arg1 < $arg2;
}

function logichelper_gt($arg1,$arg2)
{
	/*****
	 * Usage: {{#if (gt arg1 arg2)}}
	 * Returns true if arg1 > arg2, otherwise returns false
	 */
    return $arg1 > $arg2;
}

function logichelper_lte($arg1,$arg2)
{
	/*****
	 * Usage: {{#if (lte arg1 arg2)}}
	 * Returns true if arg1 <= arg2, otherwise returns false
	 */
	return $arg1 <= $arg2;
}

function logichelper_gte($arg1,$arg2)
{
	/*****
	 * Usage: {{#if (ne arg1 arg2)}}
	 * Returns true if arg1 >= arg2, otherwise returns false
	 */
    return $arg1 >= $arg2;
}

function logichelper_not($arg1)
{
	/*****
	 * Usage: {{#if (not arg1)}}
	 * Returns negation of arg1
	 */
	return !$arg1;
}

function logichelper_and() {
	/*****
	 * Usage: {{#if (and arg1 arg2 .. argN)}}
	 * Boolean AND - return true if all arguments evaluate to true
	 */

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

function logichelper_or() 
{
	/*****
	 * Usage: {{#if (or arg1 arg2 .. argN)}}
	 * Boolean OR - return true if any argument evaluates to true
	 */
	 
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

?>
