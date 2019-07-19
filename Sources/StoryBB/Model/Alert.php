<?php

/**
 * This class handles alerts.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
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


		$request = $smcFunc['db_query']('', '
			SELECT id_alert, id_member
			FROM {db_prefix}user_alerts
			WHERE ' . implode(' AND ', $clauses),
			$criteria
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$alerts[$row['id_member']][] = (int) $row['id_alert'];
		}
		$smcFunc['db_free_result']($request);

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

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}user_alerts
			SET is_read = {int:read}
			WHERE id_alert IN({array_int:toMark})',
			array(
				'read' => $read == 1 ? time() : 0,
				'toMark' => $toMark,
			)
		);

		// Gotta know how many unread alerts are left.
		$count = self::count_for_member($memID, true);

		updateMemberData($memID, array('alerts' => $count));

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
			return false;

		$toDelete = (array) $toDelete;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}user_alerts
			WHERE id_alert IN({array_int:toDelete})',
			array(
				'toDelete' => $toDelete,
			)
		);

		// Gotta know how many unread alerts are left.
		if ($memID)
		{
			$count = self::count_for_member($memID, true);

			updateMemberData($memID, array('alerts' => $count));

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

		$request = $smcFunc['db_query']('', '
			SELECT id_alert
			FROM {db_prefix}user_alerts
			WHERE id_member = {int:id_member}
				'.($unread ? '
				AND is_read = 0' : ''),
			array(
				'id_member' => $memID,
			)
		);

		$count = $smcFunc['db_num_rows']($request);
		$smcFunc['db_free_result']($request);

		// Also update the current member's count if we've just calculated it.
		if ($memID == $user_info['id'])
		{
			$user_info['alerts'] = $count;
		}

		return $count;
	}
}
