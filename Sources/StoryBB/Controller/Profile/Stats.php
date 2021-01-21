<?php

/**
 * Displays the stats page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Template;

class Stats extends AbstractProfileController
{
	public function display_action()
	{
		global $txt, $scripturl, $context, $user_profile, $user_info, $modSettings, $smcFunc;

		$memID = $this->params['u'];

		$context['page_title'] = $txt['statPanel_showStats'] . ' ' . $user_profile[$memID]['real_name'];

		// Is the load average too high to allow searching just now?
		if (!empty($context['load_average']) && !empty($modSettings['loadavg_userstats']) && $context['load_average'] >= $modSettings['loadavg_userstats'])
			fatal_lang_error('loadavg_userstats_disabled', false);

		$context['sub_template'] = 'profile_stats';
		Template::add_helper([
			'inverted_percent' => function($pc)
			{
				return 100 - $pc;
			},
			'pie_percent' => function($pc)
			{
				return round($pc / 5) * 20;
			},
		]);

		// General user statistics.
		$timeDays = floor($user_profile[$memID]['total_time_logged_in'] / 86400);
		$timeHours = floor(($user_profile[$memID]['total_time_logged_in'] % 86400) / 3600);
		$context['time_logged_in'] = ($timeDays > 0 ? $timeDays . $txt['totalTimeLogged2'] : '') . ($timeHours > 0 ? $timeHours . $txt['totalTimeLogged3'] : '') . floor(($user_profile[$memID]['total_time_logged_in'] % 3600) / 60) . $txt['totalTimeLogged4'];
		$context['num_posts'] = comma_format($user_profile[$memID]['posts']);

		// Number of topics started and Number polls started
		$result = $smcFunc['db']->query('', '
			SELECT COUNT(*), COUNT( CASE WHEN id_poll != {int:no_poll} THEN 1 ELSE NULL END )
			FROM {db_prefix}topics
			WHERE id_member_started = {int:current_member}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
				AND id_board != {int:recycle_board}' : ''),
			[
				'current_member' => $memID,
				'recycle_board' => $modSettings['recycle_board'],
				'no_poll' => 0,
			]
		);
		list ($context['num_topics'], $context['num_polls']) = $smcFunc['db']->fetch_row($result);
		$smcFunc['db']->free_result($result);

		// Number polls voted in.
		$result = $smcFunc['db']->query('distinct_poll_votes', '
			SELECT COUNT(DISTINCT id_poll)
			FROM {db_prefix}log_polls
			WHERE id_member = {int:current_member}',
			[
				'current_member' => $memID,
			]
		);
		list ($context['num_votes']) = $smcFunc['db']->fetch_row($result);
		$smcFunc['db']->free_result($result);

		// Format the numbers...
		$context['num_topics'] = comma_format($context['num_topics']);
		$context['num_polls'] = comma_format($context['num_polls']);
		$context['num_votes'] = comma_format($context['num_votes']);

		// Grab the board this member posted in most often.
		$result = $smcFunc['db']->query('', '
			SELECT
				b.id_board, MAX(b.name) AS name, MAX(b.num_posts) AS num_posts, COUNT(*) AS message_count
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE m.id_member = {int:current_member}
				AND b.count_posts = {int:count_enabled}
				AND {query_see_board}
			GROUP BY b.id_board
			ORDER BY message_count DESC
			LIMIT 10',
			[
				'current_member' => $memID,
				'count_enabled' => 0,
			]
		);
		$context['popular_boards'] = [];
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			$context['popular_boards'][$row['id_board']] = [
				'id' => $row['id_board'],
				'posts' => $row['message_count'],
				'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
				'posts_percent' => $user_profile[$memID]['posts'] == 0 ? 0 : ($row['message_count'] * 100) / $user_profile[$memID]['posts'],
				'total_posts' => $row['num_posts'],
				'total_posts_member' => $user_profile[$memID]['posts'],
			];
		}
		$smcFunc['db']->free_result($result);

		// Now get the 10 boards this user has most often participated in.
		$result = $smcFunc['db']->query('profile_board_stats', '
			SELECT
				b.id_board, MAX(b.name) AS name, b.num_posts, COUNT(*) AS message_count,
				CASE WHEN COUNT(*) > MAX(b.num_posts) THEN 1 ELSE COUNT(*) / MAX(b.num_posts) END * 100 AS percentage
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE m.id_member = {int:current_member}
				AND {query_see_board}
			GROUP BY b.id_board, b.num_posts
			ORDER BY percentage DESC
			LIMIT 10',
			[
				'current_member' => $memID,
			]
		);
		$context['board_activity'] = [];
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			$context['board_activity'][$row['id_board']] = [
				'id' => $row['id_board'],
				'posts' => $row['message_count'],
				'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
				'percent' => comma_format((float) $row['percentage'], 2),
				'posts_percent' => (float) $row['percentage'],
				'total_posts' => $row['num_posts'],
			];
		}
		$smcFunc['db']->free_result($result);

		// Posting activity by time.
		$result = $smcFunc['db']->query('user_activity_by_time', '
			SELECT
				HOUR(FROM_UNIXTIME(poster_time + {int:time_offset})) AS hour,
				COUNT(*) AS post_count
			FROM {db_prefix}messages
			WHERE id_member = {int:current_member}' . ($modSettings['totalMessages'] > 100000 ? '
				AND id_topic > {int:top_ten_thousand_topics}' : '') . '
			GROUP BY hour',
			[
				'current_member' => $memID,
				'top_ten_thousand_topics' => $modSettings['totalTopics'] - 10000,
				'time_offset' => (($user_info['time_offset'] + $modSettings['time_offset']) * 3600),
			]
		);
		$maxPosts = $realPosts = 0;
		$context['posts_by_time'] = [];
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			// Cast as an integer to remove the leading 0.
			$row['hour'] = (int) $row['hour'];

			$maxPosts = max($row['post_count'], $maxPosts);
			$realPosts += $row['post_count'];

			$context['posts_by_time'][$row['hour']] = [
				'hour' => $row['hour'],
				'hour_format' => stripos($user_info['time_format'], '%p') === false ? $row['hour'] : date('g a', mktime($row['hour'])),
				'posts' => $row['post_count'],
				'posts_percent' => 0,
				'is_last' => $row['hour'] == 23,
			];
		}
		$smcFunc['db']->free_result($result);

		if ($maxPosts > 0)
			for ($hour = 0; $hour < 24; $hour++)
			{
				if (!isset($context['posts_by_time'][$hour]))
					$context['posts_by_time'][$hour] = [
						'hour' => $hour,
						'hour_format' => stripos($user_info['time_format'], '%p') === false ? $hour : date('g a', mktime($hour)),
						'posts' => 0,
						'posts_percent' => 0,
						'relative_percent' => 0,
						'is_last' => $hour == 23,
					];
				else
				{
					$context['posts_by_time'][$hour]['posts_percent'] = round(($context['posts_by_time'][$hour]['posts'] * 100) / $realPosts);
					$context['posts_by_time'][$hour]['relative_percent'] = round(($context['posts_by_time'][$hour]['posts'] * 100) / $maxPosts);
				}
			}

		// Put it in the right order.
		ksort($context['posts_by_time']);

		// Custom stats (just add a template_layer to add it to the template!)
		call_integration_hook('integrate_profile_stats', [$memID]);
	}
}
