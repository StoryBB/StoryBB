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
	if (!empty($context['previous_posts']))
	{
		foreach ($context['previous_posts'] as $post)
		{
			$ignoring = false;
			if (!empty($post['is_ignored']))
				$ignored_posts[] = $ignoring = $post['id'];
		}
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
		'editor_context' => &$context['controls']['richedit'][$context['post_box_name']],
		'verify_context' => !empty($context['visual_verification_id']) ? $context['controls']['verification'][$context['visual_verification_id']] : false,
	];

	$template = loadTemplateFile('post_main');

	$phpStr = compileTemplate($template, [
		'helpers' => [
			'browser' => 'isBrowser',
			'jsEscape' => 'JavaScriptEscape',
			'numeric' => function($x) { return is_numeric($x);},
			'formatKb' => function($size) {
				return comma_format(round(max($size, 1024) / 1024), 0);
			},
			'sizeLimit' => function() { global $modSettings; return $modSettings['attachmentSizeLimit'] * 1024; },
			'implode_sep' => 'implode_sep'
		]
	]);

	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

?>