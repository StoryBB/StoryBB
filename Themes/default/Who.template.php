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

?>