<?php
/**
 * Miscellaneous Helpers for StoryBB
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

function isSelected($current_val, $val) 
{
	
    return new \LightnCandy\SafeString($current_val == $val ? 'selected="selected' : '');
}


function get_text(...$key) {
	global $txt;
	if (is_array($key)) {
	    $key = implode($key);
	}
	return $txt[$key];
}

function textTemplate($template, ...$args) {
	// Strip the last item off the array, it's the calling context.
	array_pop($args);
	return  new \LightnCandy\SafeString(sprintf($template, ...$args));
}

function breakRow($index, $perRow, $sep) {
	if ($index % $perRow === 0) return $sep;
	return "";
}

function getNumItems($items) {
	return count($items);
}

function dump($item) {
	return var_dump($item);
}

function hasKey($arr, $key) {
	return in_array($arr, $key);
}

?>