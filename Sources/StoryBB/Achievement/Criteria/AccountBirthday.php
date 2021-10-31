<?php

/**
 * This class handles identifying whether an account has a birthday or not.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Achievement\Criteria;

use StoryBB\Achievement\AccountAchievement;

/**
 * This class handles identifying whether an account has a birthday or not.
 */
class AccountBirthday extends AbstractCriteria implements AccountAchievement
{
	public static function get_label(): string
	{
		global $txt;
		loadLanguage('ManageAchievements');

		return $txt['criteria_account_birthday'];
	}

	public static function parameters(): array
	{
		return [
			'years' => [
				'type' => 'int',
				'min' => '1',
				'max' => '30',
				'optional' => false,
			],
		];
	}

	public function get_current_criteria_members($criteria, $account_id = null, $character_id = null)
	{
		global $smcFunc;

		$criteria = static::validate_parameters($criteria);

		$result = $smcFunc['db']->query('', '
			SELECT mem.id_member, c.id_character, mem.date_registered
			FROM {db_prefix}members AS mem
				INNER JOIN {db_prefix}characters AS c ON (c.id_member = mem.id_member AND c.is_main = {int:is_main})
			WHERE date_registered > {int:birthday_timestamp_start} AND date_registered <= {int:birthday_timestamp_end}',
			[
				'is_main' => 1,
				'birthday_timestamp_start' => strtotime('midnight -' . $criteria['years'] . ' years'),
				'birthday_timestamp_end' => strtotime('midnight -' . $criteria['years'] . ' years + 1 day'),
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			yield $row['id_member'] . '_' . $row['id_character'];
		}
		$smcFunc['db']->free_result($result);
	}

	public function get_retroactive_criteria_members($criteria, $account_id = null, $character_id = null)
	{
		global $smcFunc;

		$criteria = static::validate_parameters($criteria);

		$result = $smcFunc['db']->query('', '
			SELECT mem.id_member, c.id_character, mem.date_registered
			FROM {db_prefix}members AS mem
				INNER JOIN {db_prefix}characters AS c ON (c.id_member = mem.id_member AND c.is_main = {int:is_main})
			WHERE date_registered < {int:birthday_timestamp}',
			[
				'is_main' => 1,
				'birthday_timestamp' => $birthday_timestamp,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			yield $row['id_member'] . '_' . $row['id_character'];
		}
		$smcFunc['db']->free_result($result);
	}
}
