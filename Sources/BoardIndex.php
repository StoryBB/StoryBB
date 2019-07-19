<?php

/**
 * The single function this file contains is used to display the main
 * board index.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
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
	global $txt, $user_info, $sourcedir, $modSettings, $context, $settings, $scripturl;

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

	// Now set up for the info center.
	$context['info_center'] = [];

	// Retrieve the latest posts if the theme settings require it.
	if (!empty($settings['number_recent_posts']))
	{
		if ($settings['number_recent_posts'] > 1)
		{
			$latestPostOptions = [
				'number_posts' => $settings['number_recent_posts'],
			];
			$context['latest_posts'] = cache_quick_get('boardindex-latest_posts:' . md5($user_info['query_wanna_see_board'] . $user_info['language']), 'Subs-Recent.php', 'cache_getLastPosts', [$latestPostOptions]);
		}

		if (!empty($context['latest_posts']) || !empty($context['latest_post']))
		{
			$context['info_center'][] = 'board_ic_recent';
			$settings['number_recent_posts'] = (int) $settings['number_recent_posts'];
		}
	}

	// And stats.
	$context['show_stats'] = allowedTo('view_stats') && !empty($modSettings['trackStats']);
	if ($settings['show_stats_index'])
		$context['info_center'][] = 'board_ic_stats';

	// Now the online stuff
	require_once($sourcedir . '/Subs-MembersOnline.php');
	$membersOnlineOptions = [
		'show_hidden' => allowedTo('moderate_forum'),
		'sort' => 'log_time',
		'reverse_sort' => true,
	];
	$context += getMembersOnlineStats($membersOnlineOptions);
	$context['show_buddies'] = !empty($user_info['buddies']);
	$context['show_who'] = allowedTo('who_view') && !empty($modSettings['who_enabled']);
	$context['info_center'][] = 'board_ic_online';

	// Track most online statistics? (Subs-MembersOnline.php)
	if (!empty($modSettings['trackStats']))
		trackStatsUsersOnline($context['num_guests'] + $context['num_robots'] + $context['num_users_online']);

	// Are we showing all membergroups on the board index?
	if (!empty($settings['show_group_key']))
		$context['membergroups'] = cache_quick_get('membergroup_list', 'Subs-Membergroups.php', 'cache_getMembergroupList', []);

	// And back to normality.
	$context['page_title'] = sprintf($txt['forum_index'], $context['forum_name']);

	// Mark read button
	$context['mark_read_button'] = [
		'markread' => ['text' => 'mark_as_read', 'image' => 'markread.png', 'custom' => 'data-confirm="' . $txt['are_sure_mark_read'] . '"', 'class' => 'you_sure', 'url' => $scripturl . '?action=markasread;sa=all;' . $context['session_var'] . '=' . $context['session_id']],
	];

	$context['sub_template'] = 'board_main';

	// Allow mods to add additional buttons here
	call_integration_hook('integrate_mark_read_button');

	if (!empty($settings['show_newsfader']))
	{
		loadJavaScriptFile('slippry.min.js', [], 'sbb_jquery_slippry');
		loadCSSFile('slider.min.css', [], 'sbb_jquery_slider');
	}
}
