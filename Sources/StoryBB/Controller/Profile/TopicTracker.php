<?php

/**
 * Displays the character topic tracker page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Model\TopicPrefix;

class TopicTracker
{
	public function display_action()
	{
		global $context, $txt, $smcFunc, $scripturl;

		$context['sub_template'] = 'profile_topic_tracker';

		$character_ids = [];

		foreach ($context['member']['characters'] as $character)
		{
			if (empty($character['is_main']))
			{
				$character_ids[] = $character['id_character'];
			}
		}

		// They shouldn't be able to get here if they have no non-OOC characters but just in case...
		if (empty($character_ids))
		{
			fatal_lang_error('no_access', false);
		}

		// First, step through and set it up.
		foreach ($context['member']['characters'] as $id_character => $character)
		{
			if (!empty($character['is_main']))
			{
				continue;
			}

			$context['member']['characters'][$id_character]['topics'] = [];
		}

		// Now get all the base topic information.
		$topic_ids = [];

		$request = $smcFunc['db']->query('', '
			SELECT t.id_topic, chars.id_character
			FROM {db_prefix}characters AS chars
			INNER JOIN {db_prefix}messages AS m ON (m.id_character = chars.id_character)
			INNER JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
			WHERE chars.id_character IN ({array_int:characters})
			GROUP BY chars.id_character, t.id_topic
			ORDER BY chars.id_character, t.id_topic',
			[
				'characters' => $character_ids,
			]
		);

		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$topic_ids[$row['id_topic']] = $row['id_topic'];
			censorText($row['subject']);
			$context['member']['characters'][$row['id_character']]['topics'][$row['id_topic']] = [
				'id_topic' => $row['id_topic'],
			];
		}

		$smcFunc['db']->free_result($request);

		// If there are no topic ids, there's nothing else to fetch.
		if (empty($topic_ids))
		{
			return;
		}

		// We also need to get all the prefixes.
		$prefixes = TopicPrefix::get_prefixes_for_topic_list(array_keys($topic_ids));

		// And things like the board these topics are in, plus first/last poster, and whether there are new posts in them.
		$topic_data = [];
		$request = $smcFunc['db']->query('', '
			SELECT
				COALESCE(lt.id_msg, COALESCE(lmr.id_msg, -1)) + 1 AS new_from, b.id_board, b.name, t.locked,
				t.id_topic, ms.subject, ms.id_member, COALESCE(chars.character_name, ms.poster_name) AS real_name_col,
				ml.id_msg_modified, ml.poster_time, ml.id_member AS id_member_updated,
				COALESCE(chars2.character_name, ml.poster_name) AS last_real_name,
				lt.unwatched, chars.is_main AS started_ooc, chars2.is_main AS updated_ooc,
				chars.id_character AS started_char, chars2.id_character AS updated_char,
				chars.avatar AS first_member_avatar, af.filename AS first_member_filename,
				chars2.avatar AS last_member_avatar, al.filename AS last_member_filename
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ms.id_member)
				LEFT JOIN {db_prefix}characters AS chars ON (ms.id_character = chars.id_character AND chars.id_member = mem.id_member)
				LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = ml.id_member)
				LEFT JOIN {db_prefix}characters AS chars2 ON (chars2.id_character = ml.id_character AND chars2.id_member = mem2.id_member)
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})
				LEFT JOIN {db_prefix}attachments AS af ON (af.id_character = ms.id_character AND af.attachment_type = 1)
				LEFT JOIN {db_prefix}attachments AS al ON (al.id_character = ml.id_character AND al.attachment_type = 1)
			WHERE t.id_topic IN ({array_int:topic_ids})
				AND b.in_character = {int:in_character}',
			[
				'current_member' => $context['user']['is_owner'] ? $context['user']['id'] : 0,
				'is_approved' => 1,
				'topic_ids' => $topic_ids,
				'in_character' => 1,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			censorText($row['subject']);
			$topic_data[$row['id_topic']] = [
				'board' => [
					'id' => $row['id_board'],
					'name' => $row['name'],
					'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				],
				'subject' => $row['subject'],
				'topic_href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				'new' => $row['new_from'] <= $row['id_msg_modified'],
				'new_from' => $row['new_from'],
				'locked' => !empty($row['locked']),
				'updated' => timeformat($row['poster_time']),
				'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
				'new_link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new">' . $row['subject'] . '</a>',
				'poster_link' => empty($row['id_member']) ? $row['real_name_col'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . (empty($row['started_ooc']) && !empty($row['started_char']) ? ';area=characters;char=' . $row['started_char'] : '') . '">' . $row['real_name_col'] . '</a>',
				'poster_updated_link' => empty($row['id_member_updated']) ? $row['last_real_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_updated'] . (empty($row['updated_ooc']) && !empty($row['updated_char']) ? ';area=characters;char=' . $row['updated_char'] : '') . '">' . $row['last_real_name'] . '</a>',
				'prefixes' => [],
				'starter_avatar' => set_avatar_data([
					'avatar' => $row['first_member_avatar'],
					'filename' => !empty($row['first_member_filename']) ? $row['first_member_filename'] : '',
				]),
				'updated_avatar' => set_avatar_data([
					'avatar' => $row['last_member_avatar'],
					'filename' => !empty($row['last_member_filename']) ? $row['last_member_filename'] : '',
				]),
			];
		}
		$smcFunc['db']->free_result($request);

		// Now to join it all together.
		foreach ($context['member']['characters'] as $id_character => $character)
		{
			if (!isset($character['topics']))
			{
				continue;
			}

			foreach ($character['topics'] as $character_topic_id => $character_topic)
			{
				if (!isset($topic_data[$character_topic_id]))
				{
					unset($context['member']['characters'][$id_character]['topics'][$character_topic_id]);
					continue;
				}

				$context['member']['characters'][$id_character]['topics'][$character_topic_id] = $topic_data[$character_topic_id];

				if (isset($prefixes[$character_topic_id]))
				{
					$context['member']['characters'][$id_character]['topics'][$character_topic_id]['prefixes'] = $prefixes[$character_topic_id];
				}
			}
		}
	}
}
