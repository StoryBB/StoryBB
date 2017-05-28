<?php
use LightnCandy\LightnCandy;
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

function getBlockText($name) {
	return $txt['top_' . $name];
}
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
	
	$template = file_get_contents(__DIR__ .  "/templates/stats_main.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'helpers' => Array(
	    )
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);

}

?>