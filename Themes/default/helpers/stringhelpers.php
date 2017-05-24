<?php
/**
 * String Helpers for StoryBB
 *
 * @package StoryBB
 * @author 
 * @copyright 2017 Simple Machines and individual contributors
 * @license 
 *
 * @version 3.0
 */

/**
 * Given an array of strings, combine them in a 'more readable' way,
 * e.g. "X and Y", "X, Y and Z"
 *
 * @param $array Array of strings to nicely implode
 * @return string Combined string
 */
function implode_and($array)
{
	global $txt;
	loadLanguage('Who');

	if (count($array) <= 2)
	{
		return implode(' ' . $txt['credits_and'] . ' ', $array);
	}
	else
	{
		$last = array_pop($array);
		// And this should have an Oxford comma because @RaceProUK said so.
		return implode(', ', $array) . ', ' . $txt['credits_and'] . ' ' . $last;
	}
}