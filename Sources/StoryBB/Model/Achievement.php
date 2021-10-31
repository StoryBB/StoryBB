<?php

/**
 * This class handles the main achievement core for StoryBB.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Model;

class Achievement
{
	const ACHIEVEMENT_TYPE_ACCOUNT = 0;
	const ACHIEVEMENT_TYPE_CHARACTER = 1;

	public static function get_by_id(int $achievement_id): array
	{
		global $smcFunc;

		$result = $smcFunc['db']->query('', '
			SELECT id_achieve, achievement_name, achievement_desc, achievement_type
			FROM {db_prefix}achieve
			WHERE id_achieve = {int:achievement_id}',
			[
				'achievement_id' => $achievement_id,
			]
		);
		$row = $smcFunc['db']->fetch_assoc($result);
		$smcFunc['db']->free_result($result);

		if (empty($row))
		{
			throw new \RuntimeException('Achievement ' . $achievement_id . ' does not exist');
		}

		return $row;
	}

	public static function get_by_criteria(string $criteria): array
	{
		global $smcFunc;

		$achievements = [];

		// Find the achievements that contain this ruleset.
		$achievement_ids = [];
		$result = $smcFunc['db']->query('', '
			SELECT DISTINCT id_achieve
			FROM {db_prefix}achieve_rule
			WHERE criteria_type = {string:criteria}',
			[
				'criteria' => $criteria,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			$achievement_ids[] = $row['id_achieve'];
		}
		$smcFunc['db']->free_result($result);

		if (empty($achievement_ids))
		{
			return [];
		}

		$result = $smcFunc['db']->query('', '
			SELECT id_achieve, ruleset, rule, criteria_type, criteria
			FROM {db_prefix}achieve_rule
			WHERE id_achieve IN ({array_int:achievement_ids})',
			[
				'achievement_ids' => $achievement_ids,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			$achievements[$row['id_achieve']][$row['ruleset']][$row['rule']] = [$row['criteria_type'], $row['criteria']];
		}
		$smcFunc['db']->free_result($result);

		return $achievements;
	}

	public static function get_unlocks_by_criteria(string $criteria): array
	{
		global $smcFunc;

		$achievements = [];

		// Find the achievements that contain this ruleset.
		$achievement_ids = [];
		$result = $smcFunc['db']->query('', '
			SELECT DISTINCT id_achieve
			FROM {db_prefix}achieve_rule_unlock
			WHERE criteria_type = {string:criteria}',
			[
				'criteria' => $criteria,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			$achievement_ids[] = $row['id_achieve'];
		}
		$smcFunc['db']->free_result($result);

		if (empty($achievement_ids))
		{
			return [];
		}

		$result = $smcFunc['db']->query('', '
			SELECT id_achieve, ruleset, rule, criteria_type, criteria
			FROM {db_prefix}achieve_rule_unlock
			WHERE id_achieve IN ({array_int:achievement_ids})',
			[
				'achievement_ids' => $achievement_ids,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			$achievements[$row['id_achieve']][$row['ruleset']][$row['rule']] = [$row['criteria_type'], $row['criteria']];
		}
		$smcFunc['db']->free_result($result);

		return $achievements;
	}

	/**
	 * Gets the status of whether a given achievement has already been awarded to an acconut/character.
	 *
	 * @param int $achievement_id The achievement ID
	 * @param int $account_id The account ID
	 * @param int $character_id The character ID - for account achievements, this is less important.
	 * @return array An array containing: [can be awarded multiple times, number of times currently awarded]
	 */
	public static function get_awarded_status(int $achievement_id, int $account_id, ?int $character_id): array
	{
		global $smcFunc;

		$achievement = static::get_by_id($achievement_id);

		if ($achievement['achievement_type'] == static::ACHIEVEMENT_TYPE_ACCOUNT)
		{
			$result = $smcFunc['db']->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}achieve_user
				WHERE id_member = {int:id_member}
					AND id_achieve = {int:id_achieve}',
				[
					'id_achieve' => $achievement_id,
					'id_member' => $account_id,
				]
			);
			list($times_awarded) = $smcFunc['db']->fetch_row($result);
			$smcFunc['db']->free_result($result);

			return [false, $times_awarded];
		}
		else
		{
			$result = $smcFunc['db']->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}achieve_user
				WHERE id_member = {int:id_member}
					AND id_character = {int:id_character}
					AND id_achieve = {int:id_achieve}',
				[
					'id_achieve' => $achievement_id,
					'id_member' => $account_id,
					'id_character' => $character_id,
				]
			);
			list($times_awarded) = $smcFunc['db']->fetch_row($result);
			$smcFunc['db']->free_result($result);

			return [false, $times_awarded];
		}
	}

	/**
	 * Gets the status of whether a given achievement has already been awarded to an acconut/character.
	 *
	 * @param int $achievement_id The achievement ID
	 * @param int $account_id The account ID
	 * @param int $character_id The character ID - for account achievements, this is less important.
	 * @return array An array containing: [can be awarded multiple times, number of times currently awarded]
	 */
	public static function get_unlocked_status(int $achievement_id, int $account_id, ?int $character_id): array
	{
		global $smcFunc;

		[$can_receive_multiple, $instances_received] = static::get_awarded_status($achievement_id, $account_id, $character_id);

		if ($instances_received && !$can_receive_multiple)
		{
			return [$can_receive_multiple, $instances_received, true];
		}

		$achievement = static::get_by_id($achievement_id);

		if ($achievement['achievement_type'] == static::ACHIEVEMENT_TYPE_ACCOUNT)
		{
			$result = $smcFunc['db']->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}achieve_user_unlock
				WHERE id_member = {int:id_member}
					AND id_achieve = {int:id_achieve}',
				[
					'id_achieve' => $achievement_id,
					'id_member' => $account_id,
				]
			);
			list($times_unlocked) = $smcFunc['db']->fetch_row($result);
			$smcFunc['db']->free_result($result);

			return [$can_receive_multiple, $instances_received, $times_unlocked];
		}
		else
		{
			$result = $smcFunc['db']->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}achieve_user_unlock
				WHERE id_member = {int:id_member}
					AND id_character = {int:id_character}
					AND id_achieve = {int:id_achieve}',
				[
					'id_achieve' => $achievement_id,
					'id_member' => $account_id,
					'id_character' => $character_id,
				]
			);
			list($times_unlocked) = $smcFunc['db']->fetch_row($result);
			$smcFunc['db']->free_result($result);

			return [$can_receive_multiple, $instances_received, $times_unlocked];
		}
	}

	/**
	 * Issues an an achievement.
	 *
	 * Adds the achievement record, sends a notification, and triggers checking for achievement-based achievements.
	 *
	 * @param int $achievement_id The achievement ID
	 * @param int $account_id The recipient account ID
	 * @param int $character_id The recipient character ID (OOC id should be given if an account achievement)
	 * @param int $awarded_by The account ID of the user who issued it if manually issued
	 */
	public static function issue_achievement(int $achievement_id, int $account_id, int $character_id, int $awarded_by = null)
	{
		global $smcFunc, $modSettings;

		$smcFunc['db']->insert('insert',
			'{db_prefix}achieve_user',
			['id_achieve' => 'int', 'id_member' => 'int', 'id_character' => 'int', 'awarded_time' => 'int', 'awarded_by' => 'int'],
			[$achievement_id, $account_id, $character_id, time(), !empty($awarded_by) ? $awarded_by : 0],
			['id_achieve_award']
		);

		// @todo move to Alerts model sometime?
		$smcFunc['db']->insert('insert',
			'{db_prefix}user_alerts',
			['alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
				'chars_src' => 'int', 'chars_dest' => 'int', 'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string',
				'is_read' => 'int', 'extra' => 'string'
				],
			[time(), $account_id, $account_id, '',
				$character_id, $character_id, 'achieve', $achievement_id, 'award',
				0, ''],
			['id_alert']
		);

		updateMemberData($account_id, ['alerts' => '+']);

		// If we haven't ever done this before, flag that we now have to enable the profile area.
		if (empty($modSettings['achievements_issued']))
		{
			updateSettings(['achievements_issued' => 1]);
		}

		$achievement = new \StoryBB\Achievement;
		$achievement->trigger_award_achievement('AccountMetaAchievement', $account_id, $character_id);
		$achievement->trigger_award_achievement('CharacterMetaAchievement', $account_id, $character_id);
		$achievement->trigger_award_achievement('MetaAchievement', $account_id, $character_id);
	}

	/**
	 * Issues an an achievement.
	 *
	 * Adds the achievement record, sends a notification, and triggers checking for achievement-based achievements.
	 *
	 * @param int $achievement_id The achievement ID
	 * @param int $account_id The recipient account ID
	 * @param int $character_id The recipient character ID (OOC id should be given if an account achievement)
	 * @param int $awarded_by The account ID of the user who issued it if manually issued
	 */
	public static function unlock_achievement(int $achievement_id, int $account_id, int $character_id)
	{
		global $smcFunc, $modSettings;

		$smcFunc['db']->insert('insert',
			'{db_prefix}achieve_user_unlock',
			['id_achieve' => 'int', 'id_member' => 'int', 'id_character' => 'int', 'unlock_time' => 'int'],
			[$achievement_id, $account_id, $character_id, time()],
			['id_achieve_award']
		);

		// @todo move to Alerts model sometime?
		$smcFunc['db']->insert('insert',
			'{db_prefix}user_alerts',
			['alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
				'chars_src' => 'int', 'chars_dest' => 'int', 'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string',
				'is_read' => 'int', 'extra' => 'string'
				],
			[time(), $account_id, $account_id, '',
				$character_id, $character_id, 'achieve', $achievement_id, 'unlock',
				0, ''],
			['id_alert']
		);

		updateMemberData($account_id, ['alerts' => '+']);

		// If we haven't ever done this before, flag that we now have to enable the profile area.
		if (empty($modSettings['achievements_issued']))
		{
			updateSettings(['achievements_issued' => 1]);
		}

		$achievement = new \StoryBB\Achievement;
		$achievement->trigger_unlock_achievement('AccountMetaAchievement', $account_id, $character_id);
		$achievement->trigger_unlock_achievement('CharacterMetaAchievement', $account_id, $character_id);
		$achievement->trigger_unlock_achievement('MetaAchievement', $account_id, $character_id);
	}
}
