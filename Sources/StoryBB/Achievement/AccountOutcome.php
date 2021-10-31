<?php

/**
 * Represents that a given achievement has an outcome for an account beyond just awarding an achievement.
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
 * Represents that a given achievement has an outcome for an account beyond just awarding an achievement.
 */
interface AccountOutcome extends Discoverable
{
	public static function get_label(): string;

	public function __construct(int $account_id, array $data);
}
