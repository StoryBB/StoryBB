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
 * The main template for the post page.
 */
function template_main()
{
	global $context, $options, $txt, $scripturl, $modSettings, $counter, $settings;
	
		$ignored_posts = array();
		foreach ($context['previous_posts'] as $post)
		{
			$ignoring = false;
			if (!empty($post['is_ignored']))
				$ignored_posts[] = $ignoring = $post['id'];
		}
				
	
	$data = [
		'context' => $context,
		'options' => $options,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'modSettings' => $modSettings,
		'settings' => $settings,
		'ignored_posts' => $ignored_posts,
		'counter' =>  empty($counter) ? 0 : $counter,
		'editor_context' => &$context['controls']['richedit'][context.post_box_name],
		'verify_context' => &$context['controls']['verification'][context.visual_verification_id]
	];

	$template = file_get_contents(__DIR__ .  "/templates/post_main.hbs");
	if (!$template) {
		die('Display main template did not load!');
	}

	$phpStr = LightnCandy::compile($template, [
		'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
		'helpers' => [
			'browser' => 'isBrowser',
			'jsEscape' => 'JavaScriptEscape',
			'textTemplate' => 'textTemplate',
			'concat' => 'concat',
			'numeric' => function($x) { return is_numeric($x);},
			'neq' => 'logichelper_ne',
			'eq' => 'logichelper_eq',
			'or' => 'logichelper_or',
			'and' => 'logichelper_and',
			'gt' => 'logichelper_gt',
			'not' => 'logichelper_not',
			'formatKb' => function($size) {
				return comma_format(round(max($size, 1028) / 1028), 0);
			},
			'sizeLimit' => function() { return $modSettings.attachmentSizeLimit * 1028; },
			'getNumItems' => 'getNumItems',
			'implode_sep' => 'implode_sep'
		],
		'partials' => [
			'control_richedit' => file_get_contents(__DIR__ .  "/partials/control_richedit.hbs"),
			'control_visual_verification' => file_get_contents(__DIR__ .  "/partials/control_visual_verification.hbs"),
			'control_richedit_buttons' => file_get_contents(__DIR__ .  "/partials/control_richedit_buttons.hbs")
		]
	]);
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * The template for the AJAX quote feature
 */
function template_quotefast()
{
	global $context, $settings, $txt, $modSettings;

		$data = [
		'context' => $context,
		'txt' => $txt,
		'modSettings' => $modSettings,
		'settings' => $settings
	];

	$template = file_get_contents(__DIR__ .  "/templates/post_quotefast.hbs");
	if (!$template) {
		die('Display main template did not load!');
	}

	$phpStr = LightnCandy::compile($template, [
		'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
		'helpers' => [
		],
		'partials' => [
		]
	]);
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * The form for sending out an announcement
 */
function template_announce()
{
	global $context, $txt, $scripturl;
	$data = [
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl
	];

	$template = file_get_contents(__DIR__ .  "/templates/post_announce.hbs");
	if (!$template) {
		die('Post announce template did not load!');
	}

	$phpStr = LightnCandy::compile($template, [
		'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
		'helpers' => [
		],
		'partials' => [
		]
	]);
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * The confirmation/progress page, displayed after the admin has clicked the button to send the announcement.
 */
function template_announcement_send()
{
	global $context, $txt, $scripturl;
		$data = [
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl
	];

	$template = file_get_contents(__DIR__ .  "/templates/post_announce_send.hbs");
	if (!$template) {
		die('template did not load!');
	}

	$phpStr = LightnCandy::compile($template, [
		'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
		'helpers' => [
		],
		'partials' => [
		]
	]);
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

?>
