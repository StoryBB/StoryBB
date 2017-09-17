<?php

use LightnCandy\LightnCandy;
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

function child_boards($board) {
	// Sort the links into an array with new boards bold so it can be imploded.
	$children = array();
	/* Each child in each board's children has:
			id, name, description, new (is it new?), topics (#), posts (#), href, link, and last_post. */
	foreach ($board['children'] as $child)
	{
		if (!$child['is_redirect'])
			$child['link'] = '<a href="' . $child['href'] . '" ' . ($child['new'] ? 'class="board_new_posts" ' : '') . 'title="' . ($child['new'] ? $txt['new_posts'] : $txt['old_posts']) . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')">' . $child['name'] . ($child['new'] ? '</a> <a  ' . ($child['new'] ? 'class="new_posts" ' : '') . 'href="' . $scripturl . '?action=unread;board=' . $child['id'] . '" title="' . $txt['new_posts'] . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')"><span class="new_posts">' . $txt['new'] . '</span>' : '') . '</a>';
		else
			$child['link'] = '<a href="' . $child['href'] . '" title="' . comma_format($child['posts']) . ' ' . $txt['redirects'] . '">' . $child['name'] . '</a>';

		// Has it posts awaiting approval?
		if ($child['can_approve_posts'] && ($child['unapproved_posts'] | $child['unapproved_topics']))
			$child['link'] .= ' <a href="' . $scripturl . '?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link">(!)</a>';

		$children[] = $child['new'] ? '<strong>' . $child['link'] . '</strong>' : $child['link'];
	}
	
	return implode(',', $children);
}
/**
 * The main messageindex.
 */
function template_main()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;
	

	// They can only mark read if they are logged in and it's enabled!
	if (!$context['user']['is_logged'])
		unset($context['normal_buttons']['markread']);
	
	$data = [
		'context' => $context,
		'settings' => $settings,
		'options' => $options,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'modSettings' => $modSettings
	];

	$template = loadTemplateFile('msgIndex_main');

	$phpStr = compileTemplate($template, [
		'helpers' => [
			'implode_comma' => 'implode_comma',
			'qmod_option' => function($action) {
				global $context, $txt;
				if (!empty($context['can_' . $action]))
					return '<option value="' . $action . '">' . $txt['quick_mod_' . $action] . '</option>';
			}
		],
	]);
	
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * Shows a legend for topic icons.
 */
function template_topic_legend()
{
	global $context, $settings, $txt, $modSettings;

}

?>