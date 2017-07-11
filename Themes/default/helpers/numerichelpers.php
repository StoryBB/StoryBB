<?php
/**
 * Numeric Helpers for StoryBB
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Each of these evaluations can be "chained" with other evaluations, such as {{(add arg1 (sub arg2 arg3))}} which would return arg1 + (arg2 - arg3)
 */

function numerichelper_add($arg1, $arg2) 
{
	/*****
	 * Usage: {{(add arg1 arg2)}}
	 * Add two numbers
	 */
	return $arg1 + $arg2;
}

function numerichelper_sub($arg1, $arg2) 
{
	/*****
	 * Usage: {{(sub arg1 arg2)}}
	 * Subtract two numbers
	 */
	return $arg1 - $arg2;
}

function numericshelper_mul($arg1, $arg2) 
{
	/*****
	 * Usage: {{(mul arg1 arg2)}}
	 * Multiply two numbers
	 */
	return $arg1 * $arg2;
}

function numericshelper_div($arg1, $arg2) 
{
	/*****
	 * Usage: {{(div arg1 arg2)}}
	 * Divide two numbers
	 */
	return $arg1 / $arg2;
}

?>