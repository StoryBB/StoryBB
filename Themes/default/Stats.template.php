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
 * The stats page.
 */
function template_main()
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'settings' => $settings,
		'modSettings' => $modSettings
	);
	
	$template = loadTemplateFile('stats_main');

	$phpStr = compileTemplate($template, [
		'helpers' => [
			'getBlockText' => function ($name) {
				global $txt;
				return $txt['top_' . $name];
			}
		]
	]);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);

}

?>