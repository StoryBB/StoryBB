<?php

/**
 * Represents that a given achievement (or criteria) can be applied to characters.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Achievement;

use StoryBB\Discoverable;

/**
 * Represents that a given achievement (or criteria) can be applied to characters.
 */
interface CharacterAchievement extends Discoverable
{
	public static function get_label(): string;

	public static function get_template_partial(): string;

	public static function is_unlockable(): bool;

	public static function validate_parameters(string $criteria): array;

	public function get_current_criteria_members($criteria, $account_id = null, $character_id = null);

	public function get_retroactive_criteria_members($criteria, $account_id = null, $character_id = null);
}
