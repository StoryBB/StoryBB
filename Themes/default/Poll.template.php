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
 * A form for creating and/or editing a poll.
 */
function template_main()
{
	global $context, $txt, $scripturl;
	
	$data = [
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl
	];

	$template = file_get_contents(__DIR__ .  "/templates/poll.hbs");
	if (!$template) {
		die('Poll template did not load!');
	}

	$phpStr = LightnCandy::compile($template, [
		'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
		'helpers' => [
			'eq' => 'logichelper_eq',
			'ne' => 'logichelper_ne',
		],
	]);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);

}

?>