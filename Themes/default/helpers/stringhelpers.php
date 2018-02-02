<?php
/**
 * String Helpers for StoryBB
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Given an array of strings, combine them with commas, e.g. "X, Y, Z".
 *
 * @param $array Array of strings to implode with commas
 * @return string Combined string
 */
function implode_comma($array)
{
	return StoryBB\Template\Helper\Text::join($array, ', ');
}

function implode_sep($array, $sep)
{
	return StoryBB\Template\Helper\Text::join($array, $sep);
}

function array2js($array) {
	return StoryBB\Template\Helper\Arrays::array2js($array);
}

?>