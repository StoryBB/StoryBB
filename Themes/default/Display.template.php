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
 * This tempate handles displaying a topic
 */
function template_main()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;
	
	$viewing = '';
	if (!empty($settings['display_who_viewing']))
	{
		$viewing = $settings['display_who_viewing'] == 1 ? 
				count($context['view_members']) . ' ' . count($context['view_members']) == 1 ? $txt['who_member'] : $txt['members']
			:
				empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . ((empty($context['view_num_hidden']) || $context['can_moderate_forum']) ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');
	}
	$context['messages'] = [];
	$context['ignoredMsgs'] = [];
	$context['removableMessageIDs'] = [];
	while($message = $context['get_message']()) {
		$context['messages'][] = $message;
		if (!empty($message['is_ignored'])) $context['ignoredMsgs'][] = $message['id'];
		if ($message['can_remove']) $context['removableMessageIDs'][] = $message['id'];
	}
	
	$data = [
		'context' => $context,
		'settings' => $settings,
		'options' => $options,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'modSettings' => $modSettings,
		'viewing' => $viewing
	];
	$template = loadTemplateFile('display_main');
	$phpStr = compileTemplate($template, [
		'helpers' => [
			'implode' => 'implode_comma',
			'JSEscape' => 'JSEScape',
			'get_text' => 'get_text',
			'breakRow' => 'breakRow',
			'getLikeText' => 'getLikeText'
		]
	]);
	
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}
//This is a helper for the like text
function getLikeText($count) {
	global $txt, $context;
	
	$base = 'likes_';
	if ($message['likes']['you'])
	{
		$base = 'you_' . $base;
		$count--;
	}
	$base .= (isset($txt[$base . $count])) ? $count : 'n';
	return sprintf($txt[$base], $scripturl . '?action=likes;sa=view;ltype=msg;like=' . $id . ';' . $context['session_var'] . '=' . $context['session_id'], comma_format($count));
}

?>