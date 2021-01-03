<?php

/**
 * This class handles bookmarks.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Model;

/**
 * This class handles bookmarks.
 */
class Bookmark
{
	public static function is_bookmarked(int $userid, int $topicid): bool
	{
		global $smcFunc;

		$request = $smcFunc['db']->query('', '
			SELECT id_bookmark
			FROM {db_prefix}bookmark
			WHERE id_member = {int:userid}
				AND id_topic = {int:topicid}',
			[
				'userid' => $userid,
				'topicid' => $topicid,
			]
		);
		$row = $smcFunc['db']->fetch_row($request);
		$request->free_result();

		return !empty($row);
	}

	protected static function sanitise_ids(array $ids): array
	{
		$ids = array_map(function ($x)
			{
				return (int) $x;
			}, $ids
		);
		return array_filter($ids);
	}

	public static function bookmark_topics(int $userid, array $topicids): void
	{
		global $smcFunc;

		$topicids = static::sanitise_ids($topicids);

		if (empty($topicids))
		{
			return;
		}

		$insert = [];
		foreach ($topicids as $topicid)
		{
			$insert[] = [$userid, $topicid];
		}

		$smcFunc['db']->insert('ignore',
			'{db_prefix}bookmark',
			['id_member' => 'int', 'id_topic' => 'int'],
			$insert,
			['id_bookmark']
		);
	}

	public static function unbookmark_topics(int $userid, array $topicids): void
	{
		global $smcFunc;

		$topicids = static::sanitise_ids($topicids);

		if (empty($topicids))
		{
			return;
		}

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}bookmark
			WHERE id_member = {int:userid}
				AND id_topic IN({array_int:topicids})',
			[
				'userid' => $userid,
				'topicids' => $topicids,
			]
		);
	}

	public static function get_bookmarks($start, $items_per_page, $sort, int $userid, int $in_character): array
	{
		global $smcFunc, $scripturl, $user_info, $sourcedir;

		// All the topics with notification on...
		$request = $smcFunc['db']->query('', '
			SELECT
				COALESCE(lt.id_msg, COALESCE(lmr.id_msg, -1)) + 1 AS new_from, b.id_board, b.name,
				t.id_topic, ms.subject, ms.id_member, COALESCE(mem.real_name, ms.poster_name) AS real_name_col,
				ml.id_msg_modified, ml.poster_time, ml.id_member AS id_member_updated,
				COALESCE(mem2.real_name, ml.poster_name) AS last_real_name,
				lt.unwatched
			FROM {db_prefix}bookmark AS bm
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = bm.id_topic AND t.approved = {int:is_approved})
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = ml.id_member)
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})
			WHERE bm.id_member = {int:current_member}
				AND b.in_character = {int:in_character}
			ORDER BY {raw:sort}',
			[
				'in_character' => $in_character,
				'current_member' => $userid,
				'is_approved' => 1,
				'sort' => $sort,
				'offset' => $start,
				'items_per_page' => $items_per_page,
			]
		);
		$bookmarks = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			censorText($row['subject']);

			$bookmarks[] = [
				'id' => $row['id_topic'],
				'poster_link' => empty($row['id_member']) ? $row['real_name_col'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name_col'] . '</a>',
				'poster_updated_link' => empty($row['id_member_updated']) ? $row['last_real_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_updated'] . '">' . $row['last_real_name'] . '</a>',
				'subject' => $row['subject'],
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>',
				'new' => $row['new_from'] <= $row['id_msg_modified'],
				'new_from' => $row['new_from'],
				'updated' => timeformat($row['poster_time']),
				'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
				'new_link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new">' . $row['subject'] . '</a>',
				'board_link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
				'notify_pref' => isset($prefs['topic_notify_' . $row['id_topic']]) ? $prefs['topic_notify_' . $row['id_topic']] : (!empty($prefs['topic_notify']) ? $prefs['topic_notify'] : 0),
				'unwatched' => $row['unwatched'],
			];
		}
		$smcFunc['db']->free_result($request);

		return $bookmarks;
	}
}
