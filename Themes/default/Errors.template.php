<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

use LightnCandy\LightnCandy;


/**
 * This template handles showing attachment-related errors
 */
function template_attachment_errors()
{
	global $context, $scripturl, $txt;
	
	$data = [
		'context' => $context,
		'scripturl' => $scripturl,
		'txt' => $txt
	];

	$template = loadTemplateFile('error_attachment');

	$phpStr = compileTemplate($template);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

?>