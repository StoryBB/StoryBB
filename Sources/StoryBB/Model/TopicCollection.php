<?php

/**
 * This class handles topic collections.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Model;

/**
 * This class handles topic collections.
 */
class TopicCollection
{
	public static function get_participants_for_topic_list(array $topic_ids, bool $include_moderated = false): array
	{
		global $smcFunc;

		$topic_ids = array_filter($topic_ids, function($x) {
			$x = (int) $x;
			return $x > 0;
		});
		if (empty($topic_ids))
		{
			return [];
		}

		$topics = [];
		$characters = [
			0 => set_avatar_data(['avatar' => false]),
		];

		// First, we get the list of participants in the order of their participation in topic.
		// Owing to the group by on this, we don't want to ram getting avatars in here.
		$result = $smcFunc['db']->query('', '
			SELECT t.id_topic, MIN(m.id_msg) AS earliest_msg, COALESCE(chars.id_character, 0) AS char_id, COALESCE(chars.character_name, m.poster_name) AS char_name
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}characters AS chars ON (m.id_character = chars.id_character)
			WHERE t.id_topic IN ({array_int:topic_ids})' . (!$include_moderated ? '
				AND m.approved = 1' : '') . '
			GROUP BY t.id_topic, char_id, char_name
			ORDER BY t.id_topic, earliest_msg',
			[
				'topic_ids' => $topic_ids,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			$topics[$row['id_topic']][$row['char_id']] = [
				'name' => $row['char_name'],
			];
			if (!isset($characters[$row['char_id']]))
			{
				$characters[$row['char_id']] = false;
			}
		}
		$smcFunc['db']->free_result($result);

		// Now we fetch the characters' avatars.
		if (count($characters) > 1)
		{
			$result = $smcFunc['db']->query('', '
				SELECT chars.id_character, chars.avatar, COALESCE(a.id_attach, 0) AS id_attach, a.filename
				FROM {db_prefix}characters AS chars
					LEFT JOIN {db_prefix}attachments AS a ON (a.id_character = chars.id_character AND a.attachment_type = 1)
				WHERE chars.id_character IN ({array_int:characters})',
				[
					'characters' => array_keys($characters),
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($result))
			{
				$characters[$row['id_character']] = set_avatar_data($row);
			}
			$smcFunc['db']->free_result($result);
		}

		foreach ($topics as $id_topic => $characters_in_topic)
		{
			foreach ($characters_in_topic as $id_character => $character)
			{
				if (empty($character['avatar']))
				{
					$topics[$id_topic][$id_character]['avatar'] = $characters[$id_character] ?? $characters[0];
				}
			}
		}

		return $topics;
	}
}
