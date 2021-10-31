<?php

/**
 * This outcome grants currency to an account on receiving an achievement.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Achievement\Outcome;

use StoryBB\Achievement\AccountOutcome;

/**
 * This outcome grants currency to an account on receiving an achievement.
 */
class AddCurrency implements AccountOutcome
{
	public static function get_label(): string
	{
		global $txt;
		loadLanguage('ManageAchievements');

		return $txt['outcome_add_currency'];
	}

	public function __construct(int $account_id, array $data)
	{

	}
}
