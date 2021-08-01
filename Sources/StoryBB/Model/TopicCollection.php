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

use StoryBB\Database\DatabaseAdapter;

/**
 * This class handles topic collections.
 */
class TopicCollection
{
	const INVITE_PENDING = 0;
	const INVITE_REFUSED = 1;

	public static function get_participants_for_topic_list(array $topic_ids, bool $include_moderated = false): array
	{
		global $smcFunc, $txt;

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

		$topic_invites = static::get_invites_for_topic_list($topic_ids);
		foreach ($topic_invites as $id_topic => $invited_characters)
		{
			foreach ($invited_characters as $id_character => $invite)
			{
				[$character_name, $status] = $invite;
				if ($status == static::INVITE_REFUSED)
				{
					unset($topic_invites[$id_topic][$id_character]);
					continue;
				}
				if (!isset($characters[$id_character]))
				{
					$characters[$id_character] = false;
				}
			}

			if (empty($topic_invites[$id_topic]))
			{
				unset ($topic_invites[$id_topic]);
			}
		}

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

			if (!empty($topic_invites[$id_topic]))
			{
				foreach ($topic_invites[$id_topic] as $id_character => $invite)
				{
					if (!isset($topics[$id_topic][$id_character]))
					{
						[$character_name] = $invite;
						$topics[$id_topic][$id_character] = [
							'name' => sprintf($txt['invited_character'], $character_name),
							'avatar' => $characters[$id_character] ?? $characters[0],
							'invite' => true,
						];
					}
				}
			}
		}

		return $topics;
	}

	public static function get_invites_for_topic_list(array $topic_ids): array
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

		$invites = [];
		$result = $smcFunc['db']->query('', '
			SELECT ti.id_topic, ti.id_character, chars.character_name, ti.invite_status, ti.invite_time
			FROM {db_prefix}topic_invites AS ti
				INNER JOIN {db_prefix}characters AS chars ON (ti.id_character = chars.id_character)
			WHERE id_topic IN ({array_int:topic_ids})
			ORDER BY id_topic, id_character, invite_time',
			[
				'topic_ids' => $topic_ids,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			$invites[$row['id_topic']][$row['id_character']] = [$row['character_name'], $row['invite_status'], $row['invite_time']];
		}
		$smcFunc['db']->free_result($result);

		return $invites;
	}

	public static function invite_to_topic(int $topic, array $characters): void
	{
		global $smcFunc, $user_info;

		if (empty($characters))
		{
			return;
		}

		// First insert the invites.
		$insert = [];
		foreach ($characters as $char)
		{
			$insert[] = [$topic, $char, static::INVITE_PENDING, time()];
		}
		if (!empty($insert))
		{
			$smcFunc['db']->insert(DatabaseAdapter::INSERT_INSERT,
				'{db_prefix}topic_invites',
				['id_topic' => 'int', 'id_character' => 'int', 'invite_status' => 'int', 'invite_time' => 'int'],
				$insert,
				['id_invite']
			);
		}

		// We're going to need the member details to send out alerts.
		$alert_rows = [];
		$result = $smcFunc['db']->query('', '
			SELECT id_member, id_character
			FROM {db_prefix}characters
			WHERE id_character IN ({array_int:characters})',
			[
				'characters' => $characters,
			]
		);
		$members = [];
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			$alert_rows = [
				'alert_time' => time(),
				'id_member' => $row['id_member'],
				'id_member_started' => $user_info['id'],
				'member_name' => $user_info['character_name'],
				'chars_src' => $user_info['id_character'],
				'chars_dest' => $row['id_character'],
				'content_type' => 'topic',
				'content_id' => $topic,
				'content_action' => 'invite',
				'is_read' => 0,
				'extra' => json_encode([
					'topic' => $topic,
				]),
			];
			
			$members[] = $row['id_member'];
		}

		if (!empty($members))
		{
			$smcFunc['db']->insert(DatabaseAdapter::INSERT_INSERT,
				'{db_prefix}user_alerts',
				['alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string', 'chars_src' => 'int', 'chars_dest' => 'int',
					'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'],
				$alert_rows,
				[]
			);

			$members = array_unique($members);
			updateMemberData($members, ['alerts' => '+']);
		}
	}
}
