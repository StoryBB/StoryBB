<?php

/**
 * This file contains a couple of functions for the latests posts on forum.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\App;
use StoryBB\Helper\Parser;
use StoryBB\StringLibrary;
use StoryBB\Model\TopicPrefix;

/**
 * Get the latest posts of a forum.
 *
 * @param array $latestPostOptions
 * @return array
 */
function getLastPosts($latestPostOptions)
{
	global $scripturl, $modSettings, $smcFunc;

	$url = App::container()->get('urlgenerator');

	// Find all the posts.  Newer ones will have higher IDs.  (assuming the last 20 * number are accessible...)
	// @todo SLOW This query is now slow, NEEDS to be fixed.  Maybe break into two?
	$request = $smcFunc['db']->query('substring', '
		SELECT
			m.poster_time, m.subject, m.id_topic, m.id_member, m.id_msg,
			COALESCE(chars.character_name, mem.real_name, m.poster_name) AS poster_name, t.id_board, b.name AS board_name, b.slug AS board_slug,
			SUBSTRING(m.body, 1, 385) AS body, m.smileys_enabled, chars.is_main, chars.id_character
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}characters AS chars ON (chars.id_character = m.id_character)
		WHERE m.id_msg >= {int:likely_max_msg}' .
			(!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
			AND {query_wanna_see_board}
			AND t.approved = {int:is_approved}
			AND m.approved = {int:is_approved}
		ORDER BY m.id_msg DESC
		LIMIT ' . $latestPostOptions['number_posts'],
		[
			'likely_max_msg' => max(0, $modSettings['maxMsgID'] - 50 * $latestPostOptions['number_posts']),
			'recycle_board' => $modSettings['recycle_board'],
			'is_approved' => 1,
		]
	);
	$posts = [];
	$topics = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// Censor the subject and post for the preview ;).
		censorText($row['subject']);
		censorText($row['body']);

		$row['body'] = strip_tags(strtr(Parser::parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']), ['<br>' => '&#10;']));
		if (StringLibrary::strlen($row['body']) > 128)
			$row['body'] = StringLibrary::substr($row['body'], 0, 128) . '...';

		// Build the array.
		$posts[] = [
			'board' => [
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'href' => $url->generate('board', ['board_slug' => $row['board_slug']]),
				'link' => '<a href="' . $url->generate('board', ['board_slug' => $row['board_slug']]) . '">' . $row['board_name'] . '</a>'
			],
			'topic' => $row['id_topic'],
			'poster' => [
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'] . (empty($row['is_main']) && !empty($row['id_character']) ? ';area=characters;char=' . $row['id_character'] : ''),
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . (empty($row['is_main']) && !empty($row['id_character']) ? ';area=characters;char=' . $row['id_character'] : '') . '">' . $row['poster_name'] . '</a>'
			],
			'subject' => $row['subject'],
			'short_subject' => shorten_subject($row['subject'], 24),
			'preview' => $row['body'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'raw_timestamp' => $row['poster_time'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#msg' . $row['id_msg'],
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>',
			'prefixes' => [],
		];

		$topics[] = $row['id_topic'];
	}
	$smcFunc['db']->free_result($request);

	if (!empty($topics))
	{
		$prefixes = TopicPrefix::get_prefixes_for_topic_list($topics);
		if (!empty($prefixes))
		{
			foreach ($posts as $key => $post)
			{
				if (isset($prefixes[$post['topic']]))
				{
					$posts[$key]['prefixes'] = $prefixes[$post['topic']];
				}
			}
		}
	}

	return $posts;
}
