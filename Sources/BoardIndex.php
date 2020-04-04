<?php

/**
 * The single function this file contains is used to display the main
 * board index.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

/**
 * This function shows the board index.
 * It uses the BoardIndex template, and main sub template.
 * It updates the most online statistics.
 * It is accessed by ?action=boardindex.
 */
function BoardIndex()
{
	global $txt, $sourcedir, $modSettings, $context, $scripturl;

	// Set a canonical URL for this page.
	$context['canonical_url'] = $scripturl;

	// Do not let search engines index anything if there is a random thing in $_GET.
	if (!empty($_GET))
		$context['robot_no_index'] = true;

	// Retrieve the categories and boards.
	require_once($sourcedir . '/Subs-BoardIndex.php');
	$boardIndexOptions = [
		'include_categories' => true,
		'base_level' => 0,
		'parent_id' => 0,
		'set_latest_post' => true,
		'countChildPosts' => !empty($modSettings['countChildPosts']),
	];
	$context['categories'] = getBoardIndex($boardIndexOptions);

	// And back to normality.
	$context['page_title'] = sprintf($txt['forum_index'], $context['forum_name']);

	// Mark read button
	$context['mark_read_button'] = [
		'markread' => ['text' => 'mark_as_read', 'image' => 'markread.png', 'custom' => 'data-confirm="' . $txt['are_sure_mark_read'] . '"', 'class' => 'you_sure', 'url' => $scripturl . '?action=markasread;sa=all;' . $context['session_var'] . '=' . $context['session_id']],
	];

	$context['sub_template'] = 'board_main';

	// Allow mods to add additional buttons here
	call_integration_hook('integrate_mark_read_button');
}
