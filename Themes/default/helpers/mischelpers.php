<?php
/**
 * Miscellaneous Helpers for StoryBB
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

function isSelected($current_val, $val) 
{
	return StoryBB\Template\Helper\Text::isSelected($current_val, $val);
}

?>