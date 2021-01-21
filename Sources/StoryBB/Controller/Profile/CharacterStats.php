<?php

/**
 * Displays the character stats page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Template;

class CharacterStats extends AbstractProfileController
{
	use CharacterTrait;

	public function display_action()
	{
		global $txt, $scripturl, $context, $user_info, $modSettings, $smcFunc;

		$this->init_character();

		$context['page_title'] = $txt['statPanel_showStats'] . ' ' . $context['character']['character_name'];
		$context['sub_template'] = 'profile_character_stats';

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

		// Is the load average too high to allow searching just now?
		check_load_avg('userstats');

		$context['num_posts'] = comma_format($context['character']['posts']);

		$context['linktree'][] = [
			'name' => $txt['char_stats'],
			'url' => $scripturl . '?action=profile;area=character_stats;char=' . $context['character']['id_character'] . ';u=' . $context['id_member'],
		];

		// Number of topics started.
		$result = $smcFunc['db']->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
			WHERE m.id_character = {int:id_character}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
				AND t.id_board != {int:recycle_board}' : ''),
			[
				'id_character' => $context['character']['id_character'],
				'recycle_board' => $modSettings['recycle_board'],
			]
		);
		list ($context['num_topics']) = $smcFunc['db']->fetch_row($result);
		$smcFunc['db']->free_result($result);
		$context['num_topics'] = comma_format($context['num_topics']);

		// Grab the board this character posted in most often.
		$result = $smcFunc['db']->query('', '
			SELECT
				b.id_board, MAX(b.name) AS name, MAX(b.num_posts) AS num_posts, COUNT(*) AS message_count
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE m.id_character = {int:id_character}
				AND b.count_posts = {int:count_enabled}
				AND {query_see_board}
			GROUP BY b.id_board
			ORDER BY message_count DESC
			LIMIT 10',
			[
				'id_character' => $context['character']['id_character'],
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
				'posts_percent' => $context['character']['posts'] == 0 ? 0 : ($row['message_count'] * 100) / $context['character']['posts'],
				'total_posts' => $row['num_posts'],
				'total_posts_char' => $context['character']['posts'],
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
			WHERE m.id_character = {int:id_character}
				AND {query_see_board}
			GROUP BY b.id_board, b.num_posts
			ORDER BY percentage DESC
			LIMIT 10',
			[
				'id_character' => $context['character']['id_character'],
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
			WHERE id_character = {int:id_character}' . ($modSettings['totalMessages'] > 100000 ? '
				AND id_topic > {int:top_ten_thousand_topics}' : '') . '
			GROUP BY hour',
			[
				'id_character' => $context['character']['id_character'],
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
	}
}
