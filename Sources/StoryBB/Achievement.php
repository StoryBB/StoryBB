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

namespace StoryBB;

use StoryBB\Model\Achievement as AchievementModel;
use StoryBB\ClassManager;

/**
 * This class handles the main achievement core for StoryBB.
 */
class Achievement
{
	protected static $criteria = null;

	/**
	 * Lists the possible criteria that can be used for achievements.
	 *
	 * @return array An array of criteria-id -> criteria class entries.
	 */
	public function get_possible_criteria(): void
	{
		if (static::$criteria === null)
		{
			$criteria = [];

			foreach (ClassManager::get_classes_implementing('StoryBB\\Achievement\\AccountAchievement') as $class)
			{
				$classname = substr(strrchr($class, '\\'), 1);
				static::$criteria[$classname] = $class;
			}
			foreach (ClassManager::get_classes_implementing('StoryBB\\Achievement\\CharacterAchievement') as $class)
			{
				$classname = substr(strrchr($class, '\\'), 1);
				static::$criteria[$classname] = $class;
			}
		}
	}

	protected function is_valid_criteria_type(string $criteria_type): bool
	{
		$this->get_possible_criteria();

		return isset(static::$criteria[$criteria_type]);
	}

	/**
	 * Receives events, essentially, about events that can trigger awarding an achievement.
	 *
	 * @param string $criteria Which trigger has been fired, e.g. 'character-birthday', so that only achievements with that criteria are considered.
	 * @param bool $retroactive Whether to check retroactively if this criteria was previously met, or simply met off the back of whatever triggered this achievement.
	 * @param int $account_id The account ID relating to the event (i.e. the account that could earn the achievement).
	 * @param int $character_id The character ID relating to the event (i.e. the character that could the achievement)..
	 */
	public function trigger_award_achievement(string $criteria, int $account_id = null, int $character_id = null)
	{
		if (!$this->is_valid_criteria_type($criteria))
		{
			return;
		}

		// Find achievements with this trigger.
		$achievements = AchievementModel::get_by_criteria($criteria);
		foreach ($this->match_current_criteria($achievements, $account_id, $character_id) as $match)
		{
			[$achievement_id, $account_id, $character_id] = $match;

			[$can_receive_multiple, $instances_received] = AchievementModel::get_awarded_status($achievement_id, $account_id, $character_id);
			if (!$instances_received || $can_receive_multiple)
			{
				AchievementModel::issue_achievement($achievement_id, $account_id, $character_id);
			}
		}

		$this->trigger_unlock_achievement($criteria, $account_id, $character_id);
	}

	public function trigger_unlock_achievement(string $criteria, int $account_id = null, int $character_id = null)
	{
		if (!$this->is_valid_criteria_type($criteria))
		{
			return;
		}

		$unlocks = AchievementModel::get_unlocks_by_criteria($criteria);
		foreach ($this->match_current_criteria($unlocks, $account_id, $character_id) as $match)
		{
			[$achievement_id, $account_id, $character_id] = $match;

			[$can_receive_multiple, $instances_received, $already_unlocked] = AchievementModel::get_unlocked_status($achievement_id, $account_id, $character_id);
			if (!$already_unlocked)
			{
				// @todo Add in reunlocking for multiple achievement?
				AchievementModel::unlock_achievement($achievement_id, $account_id, $character_id);
			}
		}
	}

	/**
	 * Given a list of achievements, and possible account/characters, see who is eligible for them.
	 */
	protected function match_current_criteria(array $achievements, int $account_id = null, int $character_id = null)
	{
		$this->get_possible_criteria();

		$possible_members = [];

		// For each achievement, for each ruleset, for each rule, fetch who could match.
		// Then collate how many rules in a ruleset are met by a given account/character.
		// If the right number are matched, yield that combination.
		foreach ($achievements as $achievement_id => $rulesets)
		{
			foreach ($rulesets as $ruleset => $rules)
			{
				foreach ($rules as $rule_id => $rule)
				{
					[$criteria_type, $criteria] = $rule;
					if (!$this->is_valid_criteria_type($criteria_type))
					{
						continue 2; // We can't evaluate this criteria, so abort this ruleset.
					}

					$class = static::$criteria[$criteria_type];
					$instance = new $class;

					foreach ($instance->get_current_criteria_members($criteria, $account_id, $character_id) as $fitting_this_criteria)
					{
						if (!isset($possible_members[$fitting_this_criteria][$achievement_id][$ruleset]))
						{
							$possible_members[$fitting_this_criteria][$achievement_id][$ruleset] = 0;
						}
						
						$possible_members[$fitting_this_criteria][$achievement_id][$ruleset]++;
					}
				}
			}
		}

		foreach ($possible_members as $account_character => $achievement_list)
		{
			foreach ($achievement_list as $achievement_id => $rulesets)
			{
				foreach ($rulesets as $ruleset => $rules_matched)
				{
					if (count($achievements[$achievement_id][$ruleset]) == $rules_matched)
					{
						unset($possible_members[$account_character][$achievement_id]);

						[$account, $character] = explode('_', $account_character);
						yield [$achievement_id, $account, $character];
						continue 2; // Nothing else to do on this achievement.
					}
				}
			}
		}
	}

	/**
	 * Receives events, essentially, about events that can trigger awarding an achievement.
	 *
	 * @param string $criteria Which trigger has been fired, e.g. 'character-birthday', so that only achievements with that criteria are considered.
	 * @param bool $retroactive Whether to check retroactively if this criteria was previously met, or simply met off the back of whatever triggered this achievement.
	 * @param int $account_id The account ID relating to the event (i.e. the account that could earn the achievement).
	 * @param int $character_id The character ID relating to the event (i.e. the character that could the achievement)..
	 */
	public function trigger_retroactive_achievement(string $criteria, int $account_id = null, int $character_id = null)
	{
		if (!$this->is_valid_criteria_type($criteria))
		{
			return;
		}

		// Find achievements with this trigger.
		$achievements = AchievementModel::get_by_criteria($criteria);
		foreach ($this->match_retroactive_criteria($achievements, $retroactive, $account_id, $character_id) as $match)
		{
			[$achievement_id, $account_id, $character_id] = $match;

			[$can_receive_multiple, $instances_received] = AchievementModel::get_awarded_status($achievement_id, $account_id, $character_id);
			if (!$instances_received || $can_receive_multiple)
			{
				AchievementModel::issue_achievement($achievement_id, $account_id, $character_id);
			}
		}

		$unlocks = AchievementModel::get_unlocks_by_criteria($criteria);
		foreach ($this->match_retroactive_criteria($achievements, $account_id, $character_id) as $match)
		{
			[$achievement_id, $account_id, $character_id] = $match;

			[$can_receive_multiple, $instances_received, $already_unlocked] = AchievementModel::get_unlocked_status($achievement_id, $account_id, $character_id);
			if (!$already_unlocked)
			{
				// @todo Add in reunlocking for multiple achievement?
				AchievementModel::unlock_achievement($achievement_id, $account_id, $character_id);
			}
		}
	}

	/**
	 * Given a list of achievements, and possible account/characters, see who is eligible for them.
	 */
	protected function match_retroactive_criteria(array $achievements, int $account_id = null, int $character_id = null)
	{
		$this->get_possible_criteria();

		$possible_members = [];

		foreach ($achievements as $achievement_id => $rulesets)
		{
			foreach ($rulesets as $ruleset => $rules)
			{
				foreach ($rules as $rule_id => $rule)
				{
					[$criteria_type, $criteria] = $rule;
					if (!$this->is_valid_criteria_type($criteria_type))
					{
						continue 2; // We can't evaluate this criteria, so abort this ruleset.
					}

					$class = static::$criteria[$criteria_type];
					$instance = new $class;

					foreach ($instance->get_retroactive_criteria_members($criteria, $account_id, $character_id) as $fitting_this_criteria)
					{
						if (!isset($possible_members[$fitting_this_criteria][$achievement_id][$ruleset]))
						{
							$possible_members[$fitting_this_criteria][$achievement_id][$ruleset] = 0;
						}
						
						$possible_members[$fitting_this_criteria][$achievement_id][$ruleset]++;
					}
				}

				// Whereas the current criteria is not overly likely to match many, this potentially could match every member or character on the site.
				// So try to cull earlier rather than later.
				foreach ($possible_members as $account_character => $matching_achievements)
				{
					if (empty($matching_achievements[$achievement_id][$ruleset]) || $matching_achievements[$achievement_id][$ruleset] != count($rules))
					{
						unset ($possible_members[$account_character][$achievement_id][$ruleset]);
						continue;
					}
				}
			}

			foreach ($possible_members as $account_character => $matching_achievements)
			{
				if (!empty($matching_achievements[$achievement_id]))
				{
					[$account, $character] = explode('_', $account_character);
					yield [$achievement_id, $account, $character];
				}
			}
		}
	}
}
