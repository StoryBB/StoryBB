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
 * Choose which type of report to run?
 */
function template_report_type()
{
	global $context, $scripturl, $txt;
	
	$data = [
		'context' => $context,
		'scripturl' => $scripturl,
		'txt' => $txt
	];

	$template = file_get_contents(__DIR__ .  "/templates/report_type.hbs");
	if (!$template) {
		die('Select report type template did not load!');
	}

	$phpStr = LightnCandy::compile($template, [
		'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
		'helpers' => [],
	]);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * This is the standard template for showing reports.
 */
function template_main()
{
	global $context, $txt;
	
	$data = [
		'context' => $context,
		'txt' => $txt
	];

	$template = file_get_contents(__DIR__ .  "/templates/report.hbs");
	if (!$template) {
		die('Report template did not load!');
	}

	$phpStr = LightnCandy::compile($template, [
		'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
	    'partials' => Array(
	    	'button_strip' => file_get_contents(__DIR__ .  "/partials/button_strip.hbs")
	    ),
		'helpers' => [
			'eq' => 'logichelper_eq',
			'and' => 'logichelper_and',
		],
	]);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

?>