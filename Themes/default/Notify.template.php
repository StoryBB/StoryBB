<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * The main notification bar.
 */
function template_main()
{
	global $context, $txt, $scripturl;
	
	$data = [
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl
	];

	$template = loadTemplateFile('notify_main');

	$phpStr = compileTemplate($template);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * Board notification bar.
 */
function template_notify_board()
{
	global $context, $txt, $scripturl;
	
	$data = [
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl
	];

	$template = loadTemplateFile('notify_board');

	$phpStr = compileTemplate($template);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

?>