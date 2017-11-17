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

function hasKey($arr, $key) {
	return in_array($arr, $key);
}

?>