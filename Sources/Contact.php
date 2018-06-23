<?php

/**
 * A contact form.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Display and process the contact form.
 * @return void
 */
function Contact()
{
	global $context, $txt;

	$context['page_title'] = $txt['contact_us'];
	$context['sub_template' ] = 'contact_form';
}
