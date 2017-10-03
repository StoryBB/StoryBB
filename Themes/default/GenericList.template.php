<?php

use LightnCandy\LightnCandy;

/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * This template handles displaying a list
 *
 * @param string $list_id The list ID. If null, uses $context['default_list'].
 */
function template_show_list($list_id = null)
{
	return generic_list_helper($list_id);
}

?>