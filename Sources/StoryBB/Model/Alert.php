<?php

/**
 * This class handles alerts.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Model;

/**
 * This class handles alerts.
 */
class Alert
{
	/**
	 * Marks alerts read for members based on criteria.
	 *
	 * @param array $criteria An array of criteria, can contain the following keys:
	 *           - content_type
	 *           - content_id
	 *           - content_action
	 *           - id_member
	 *           - id_member_started
	 *			 - id_alert
	 * @return array List of member IDs => alerts for them which meet the criteria
	 */
	public static function find_alerts(array $criteria): array
	{
		global $smcFunc;

		$alerts = [];
		$array_int = ['content_id', 'id_member', 'id_member_started', 'id_alert', 'is_read'];

		foreach ($criteria as $k => $v)
		{
			if (!in_array($k, ['content_type', 'content_id', 'content_action', 'id_member', 'id_member_started', 'id_alert', 'is_read']))
			{
				unset ($criteria[$k]);
				continue;
			}
			$v = (array) $v;
			if (in_array($k, $array_int))
			{
				$v = array_map('intval', $v);

				if ($k != 'is_read')
					$v = array_diff($v, [0]);
			}
			if (empty($v)) {
				unset ($criteria[$k]);
				continue;
			}
			$criteria[$k] = $v;
		}

		if (empty($criteria))
		{
			return [];
		}


		$clauses = [];
		foreach ($criteria as $k => $v)
		{
			if (in_array($k, $array_int))
			{
				$clauses[] = $k . ' IN ({array_int:' . $k . '})';
			}
			else
			{
				$clauses[] = $k . ' IN ({array_string:' . $k . '})';
			}
		}


		$request = $smcFunc['db']->query('', '
			SELECT id_alert, id_member
			FROM {db_prefix}user_alerts
			WHERE ' . implode(' AND ', $clauses),
			$criteria
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$alerts[$row['id_member']][] = (int) $row['id_alert'];
		}
		$smcFunc['db']->free_result($request);

		return $alerts;
	}

	/**
	 * Marks a group of alerts as un/read
	 *
	 * @param int $memID The user ID.
	 * @param array|integer $toMark The ID of a single alert or an array of IDs. The function will convert single integers to arrays for better handling.
	 * @param integer $read To mark as read or unread, 1 for read, 0 or any other value different than 1 for unread.
	 * @return integer How many alerts remain unread
	 */
	public static function change_read($memID, $toMark, $read = 0)
	{
		global $smcFunc;

		if (empty($toMark) || empty($memID))
			return false;

		$toMark = (array) $toMark;

		$smcFunc['db']->query('', '
			UPDATE {db_prefix}user_alerts
			SET is_read = {int:read}
			WHERE id_alert IN ({array_int:toMark})
				AND id_member = {int:memID}',
			[
				'read' => $read == 1 ? time() : 0,
				'toMark' => $toMark,
				'memID' => $memID,
			]
		);

		// Gotta know how many unread alerts are left.
		$count = self::count_for_member($memID, true);

		updateMemberData($memID, ['alerts' => $count]);

		// Might want to know this.
		return $count;
	}

	/**
	 * Deletes a single or a group of alerts by ID
	 *
	 * @param int|array The ID of a single alert to delete or an array containing the IDs of multiple alerts. The function will convert integers into an array for better handling.
	 * @param bool|int $memID The user ID. Used to update the user unread alerts count.
	 * @return void|int If the $memID param is set, returns the new amount of unread alerts.
	 */
	public static function delete($toDelete, $memID = false)
	{
		global $smcFunc;

		if (empty($toDelete))
		{
			return false;
		}

		$toDelete = (array) $toDelete;

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}user_alerts
			WHERE id_alert IN({array_int:toDelete})' . (!empty($memID) ? '
				AND id_member = {int:memID}' : ''),
			[
				'toDelete' => $toDelete,
				'memID' => (int) $memID,
			]
		);

		// Gotta know how many unread alerts are left.
		if ($memID)
		{
			$count = self::count_for_member($memID, true);

			updateMemberData($memID, ['alerts' => $count]);

			// Might want to know this.
			return $count;
		}
	}

	/**
	 * Counts how many alerts a user has - either unread or all depending on $unread
	 *
	 * @param int $memID The user ID.
	 * @param bool $unread Whether to only count unread alerts.
	 * @return int The number of requested alerts
	 */
	public static function count_for_member($memID, $unread = false)
	{
		global $smcFunc, $user_info;

		if (empty($memID))
			return false;

		$request = $smcFunc['db']->query('', '
			SELECT id_alert
			FROM {db_prefix}user_alerts
			WHERE id_member = {int:id_member}
				'.($unread ? '
				AND is_read = 0' : ''),
			[
				'id_member' => $memID,
			]
		);

		$count = $smcFunc['db']->num_rows($request);
		$smcFunc['db']->free_result($request);

		// Also update the current member's count if we've just calculated it.
		if ($memID == $user_info['id'])
		{
			$user_info['alerts'] = $count;
		}

		return $count;
	}

	/**
	 * Fetch the alerts a user currently has.
	 *
	 * @param int $memID The ID of the member
	 * @param bool $all Whether to fetch all alerts or just unread ones
	 * @param int $counter How many alerts to display (0 if displaying all or using pagination)
	 * @param array $pagination An array containing info for handling pagination. Should have 'start' and 'maxIndex'
	 * @param bool $withSender With $memberContext from sender
	 * @return array An array of information about the fetched alerts
	 */
	public static function fetch_alerts($memID, $all = false, $counter = 0, $pagination = [], $withSender = true)
	{
		global $smcFunc, $txt, $scripturl, $memberContext;

		$query_see_board = build_query_board($memID);
		$query_see_board = $query_see_board['query_see_board'];

		$alerts = [];
		$request = $smcFunc['db']->query('', '
			SELECT id_alert, alert_time, mem.id_member AS sender_id, COALESCE(mem.real_name, ua.member_name) AS sender_name,
				chars_src, chars_dest, content_type, content_id, content_action, is_read, extra
			FROM {db_prefix}user_alerts AS ua
				LEFT JOIN {db_prefix}members AS mem ON (ua.id_member_started = mem.id_member)
			WHERE ua.id_member = {int:id_member}' . (!$all ? '
				AND is_read = 0' : '') . '
			ORDER BY id_alert DESC' . (!empty($counter) && empty($pagination) ? '
			LIMIT {int:counter}' : '') . (!empty($pagination) && empty($counter) ? '
			LIMIT {int:start}, {int:maxIndex}' : ''),
			[
				'id_member' => $memID,
				'counter' => $counter,
				'start' => !empty($pagination['start']) ? $pagination['start'] : 0,
				'maxIndex' => !empty($pagination['maxIndex']) ? $pagination['maxIndex'] : 0,
			]
		);

		$senders = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$id_alert = array_shift($row);
			$row['time'] = timeformat($row['alert_time']);
			$row['extra'] = !empty($row['extra']) ? sbb_json_decode($row['extra'], true) : [];
			$alerts[$id_alert] = $row;

			if (!empty($row['sender_id']))
				$senders[] = $row['sender_id'];
		}
		$smcFunc['db']->free_result($request);

		if($withSender)
		{
			$senders = loadMemberData($senders);
			foreach ($senders as $member)
				loadMemberContext($member);
		}

		// Now go through and actually make with the text.
		loadLanguage('Alerts');

		// Hooks might want to do something snazzy around their own content types - including enforcing permissions if appropriate.
		call_integration_hook('integrate_fetch_alerts', [&$alerts]);

		// For anything that wants us to check board or topic access, let's do that.
		$boards = [];
		$topics = [];
		$msgs = [];
		$chars = [];
		$char_accounts = [];
		foreach ($alerts as $id_alert => $alert)
		{
			if (!empty($alert['chars_src']))
				$chars[$alert['chars_src']] = $txt['char_unknown'];
			if (!empty($alert['chars_dest']))
				$chars[$alert['chars_dest']] = $txt['char_unknown'];
			if (isset($alert['extra']['board']))
				$boards[$alert['extra']['board']] = $txt['board_na'];
			if (isset($alert['extra']['topic']))
				$topics[$alert['extra']['topic']] = $txt['topic_na'];
			if ($alert['content_type'] == 'msg')
				$msgs[$alert['content_id']] = $txt['topic_na'];
		}

		// Having figured out what boards etc. there are, let's now get the names of them if we can see them. If not, there's already a fallback set up.
		if (!empty($boards))
		{
			$request = $smcFunc['db']->query('', '
				SELECT id_board, name
				FROM {db_prefix}boards AS b
				WHERE ' . $query_see_board . '
					AND id_board IN ({array_int:boards})',
				[
					'boards' => array_keys($boards),
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$boards[$row['id_board']] = '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>';
		}
		if (!empty($topics))
		{
			$request = $smcFunc['db']->query('', '
				SELECT t.id_topic, m.subject
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
					INNER JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
				WHERE ' . $query_see_board . '
					AND t.id_topic IN ({array_int:topics})',
				[
					'topics' => array_keys($topics),
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$topics[$row['id_topic']] = '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>';
		}
		if (!empty($msgs))
		{
			$request = $smcFunc['db']->query('', '
				SELECT m.id_msg, t.id_topic, m.subject
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
					INNER JOIN {db_prefix}boards AS b ON (m.id_board = b.id_board)
				WHERE ' . $query_see_board . '
					AND m.id_msg IN ({array_int:msgs})',
				[
					'msgs' => array_keys($msgs),
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$msgs[$row['id_msg']] = '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>';
		}

		// Now to handle characters
		if (!empty($chars))
		{
			$request = $smcFunc['db']->query('', '
				SELECT chars.id_character, chars.id_member, chars.character_name
				FROM {db_prefix}characters AS chars
				WHERE id_character IN ({array_int:chars})',
				[
					'chars' => array_keys($chars),
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				$chars[$row['id_character']] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . ';area=characters;char=' . $row['id_character'] . '">' . $row['character_name'] . '</a>';
				$chars_sheets[$row['id_character']] = $scripturl . '?action=profile;u=' . $row['id_member'] . ';area=characters;char=' . $row['id_character'] . ';sa=sheet';
			}
			$smcFunc['db']->free_result($request);
		}

		// Now to go back through the alerts, reattach this extra information and then try to build the string out of it (if a hook didn't already)
		foreach ($alerts as $id_alert => $alert)
		{
			if (!empty($alert['text']))
				continue;
			if (isset($alert['extra']['board']))
				$alerts[$id_alert]['extra']['board_msg'] = $boards[$alert['extra']['board']];
			if (isset($alert['extra']['topic']))
				$alerts[$id_alert]['extra']['topic_msg'] = $topics[$alert['extra']['topic']];
			if ($alert['content_type'] == 'msg')
				$alerts[$id_alert]['extra']['msg_msg'] = $msgs[$alert['content_id']];
			if ($alert['content_type'] == 'profile')
				$alerts[$id_alert]['extra']['profile_msg'] = '<a href="' . $scripturl . '?action=profile;u=' . $alerts[$id_alert]['content_id'] . '">' . $alerts[$id_alert]['extra']['user_name'] . '</a>';

			// Use the sender details first if we have them.
			if (!empty($memberContext[$alert['sender_id']]))
				$alerts[$id_alert]['sender'] = $memberContext[$alert['sender_id']];

			// And additionally if we have a character, use that.
			if (!empty($alert['chars_src']))
			{
				if (!empty($memberContext[$alert['sender_id']]['characters'][$alert['chars_src']]))
				{
					$alerts[$id_alert]['sender']['avatar']['image'] = $memberContext[$alert['sender_id']]['characters'][$alert['chars_src']]['avatar'];
					$alerts[$id_alert]['sender']['avatar']['image'] = '<img class="avatar" src="' . $alerts[$id_alert]['sender']['avatar']['image'] . '" alt="">';
				}
			}

			$string = 'alert_' . $alert['content_type'] . '_' . $alert['content_action'];
			if (!empty($alert['chars_dest']))
				$string .= 'chr';

			if (isset($txt[$string]))
			{
				$extra = $alerts[$id_alert]['extra'];
				$search = ['{member_link}', '{scripturl}'];
				$repl = [!empty($alert['sender_id']) ? '<a href="' . $scripturl . '?action=profile;u=' . $alert['sender_id'] . '">' . $alert['sender_name'] . '</a>' : $alert['sender_name'], $scripturl];
				if (!empty($alert['chars_src']))
				{
					$search[] = '{char_link}';
					$repl[] = $chars[$alert['chars_src']];
					if (!empty($chars_sheets[$alert['chars_src']]))
					{
						$search[] = '#{char_sheet_link}';
						$repl[] = $chars_sheets[$alert['chars_src']];
					}
				}
				if (!empty($alert['chars_dest']))
				{
					$search[] = '{your_chr}';
					$repl[] = $chars[$alert['chars_dest']];
				}
				foreach ($extra as $k => $v)
				{
					$search[] = '{' . $k . '}';
					$repl[] = $v;
				}
				$alerts[$id_alert]['text'] = str_replace($search, $repl, $txt[$string]);
			}
		}

		return $alerts;
	}
}
