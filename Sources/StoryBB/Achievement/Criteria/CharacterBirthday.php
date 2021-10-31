<?php

/**
 * This class handles identifying whether a character has a birthday or not.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Achievement\Criteria;

use StoryBB\Achievement\CharacterAchievement;

/**
 * This class handles identifying whether a character has a birthday or not.
 */
class CharacterBirthday extends AbstractCriteria implements CharacterAchievement
{
	public static function get_label(): string
	{
		global $txt;
		loadLanguage('ManageAchievements');

		return $txt['criteria_character_birthday'];
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
			SELECT id_member, id_character, date_created
			FROM {db_prefix}characters
			WHERE date_created > {int:birthday_timestamp_start} AND date_created <= {int:birthday_timestamp_end}
				AND is_main = {int:not_main}',
			[
				'birthday_timestamp_start' => strtotime('midnight -' . $criteria['years'] . ' years'),
				'birthday_timestamp_end' => strtotime('midnight -' . $criteria['years'] . ' years + 1 day'),
				'not_main' => 0,
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

		$birthday_timestamp = strtotime('-' . $criteria['years'] . ' years');

		$result = $smcFunc['db']->query('', '
			SELECT id_member, id_character, date_created
			FROM {db_prefix}characters
			WHERE date_created < {int:birthday_timestamp}
				AND is_main = {int:not_main}',
			[
				'birthday_timestamp' => $birthday_timestamp,
				'not_main' => 0,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			yield $row['id_member'] . '_' . $row['id_character'];
		}
		$smcFunc['db']->free_result($result);
	}
}
