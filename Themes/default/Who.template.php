<?php
require_once(__DIR__ . '/helpers/logichelpers.php');
require_once(__DIR__ . '/helpers/stringhelpers.php');
use LightnCandy\LightnCandy;
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2017 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 3
 */

/**
 * This handles the Who's Online page
 */
function template_main()
{
	global $context, $settings, $scripturl, $txt;

	$data = [
		'context' => $context,
		'txt' => $txt,
		'settings' => $settings,
		'scripturl' => $scripturl,
	];

	$template = file_get_contents(__DIR__ .  "/templates/whosonline.hbs");
	if (!$template) {
		die('Member template did not load!');
	}

	$phpStr = LightnCandy::compile($template, [
		'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
		'helpers' => [
			'eq' => 'logichelper_eq',
			'ne' => 'logichelper_ne',
			'and' => 'logichelper_and',
		],
	]);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * This displays a nice credits page
 */
function template_credits()
{
	global $context, $txt;

	$data = [
		'context' => $context,
		'txt' => $txt,
	];

	$template = file_get_contents(__DIR__ .  "/templates/credits.hbs");
	if (!$template) {
		die('Member template did not load!');
	}

	$phpStr = LightnCandy::compile($template, [
		'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
		'helpers' => [
			'or' => 'logichelper_or',
			'implode_and' => 'implode_and',
		],
	]);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

?>