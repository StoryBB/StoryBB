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
 * THis displays a fatal error message
 */
function template_fatal_error()
{
	global $context, $txt;
	
	$data = [
		'context' => $context,
		'txt' => $txt
	];

	$template = loadTemplateFile('error_fatal');

	$phpStr = compileTemplate($template);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * This template shows a snippet of code from a file and highlights which line caused the error.
 */
function template_show_file()
{
	global $context, $settings, $modSettings;
	
	$data = [
		'context' => $context,
		'settings' => $settings,
		'modSettings' => $modSettings
	];

	$template = loadTemplateFile('error_show_file');

	$phpStr = compileTemplate($template, [
		'helpers' => [
			'add' => 'numerichelper_add',
		],
	]);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

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