<?php

/**
 * This class handles underlying functionality for all achievement criteria..
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Achievement\Criteria;

use RuntimeException;

/**
 * This class handles identifying whether a character has a birthday or not.
 */
abstract class AbstractCriteria
{
	abstract public static function parameters(): array;

	abstract public static function get_label(): string;

	public static function get_template_partial(): string
	{
		return 'admin_achievement_criteria_' . strtolower(substr(strrchr(static::class, '\\'), 1));
	}

	public static function assignable_to()
	{
		return [
			'account' => is_a(static::class, 'StoryBB\\Achievement\\AccountAchievement', true),
			'character' => is_a(static::class, 'StoryBB\\Achievement\\CharacterAchievement', true),
		];
	}

	public static function is_unlockable(): bool
	{
		return is_a(static::class, 'StoryBB\\Achievement\\UnlockableAchievement', true);
	}

	public static function validate_parameters(string $criteria): array
	{
		$criteria = json_decode($criteria, true);
		$valid_criteria = static::parameters();

		foreach ($valid_criteria as $criterion_name => $criterion_type)
		{
			if (!$criterion_type['optional'] && !isset($criteria[$criterion_name]))
			{
				throw new RuntimeException('Criteria type ' . $criterion_name . ' is missing but required');
			}
		}

		foreach ($criteria as $criterion_name => $criterion_requirements)
		{
			if (!isset($valid_criteria[$criterion_name]))
			{
				throw new RuntimeException('Criteria type ' . $criterion_name . ' is not valid for this criteria');
			}
			$this_criterion = $valid_criteria[$criterion_name];
			switch ($this_criterion['type'])
			{
				case 'int':
					if (!is_int($criterion_requirements) && (string) (int) $criterion_requirements != $criterion_requirements)
					{
						throw new RuntimeException('Criteria type ' . $criterion_name . ' is not of type int');
					}
					if (isset($this_criterion['min']) && $criterion_requirements < $this_criterion['min'])
					{
						throw new RuntimeException('Criteria type ' . $criterion_name . ' is lower than legal mininum ' . $this_criterion['min']);
					}
					if (isset($this_criterion['max']) && $criterion_requirements > $this_criterion['max'])
					{
						throw new RuntimeException('Criteria type ' . $criterion_name . ' is higher than legal maxinum ' . $this_criterion['max']);
					}
					$criterion_requirements = (int) $criterion_requirements;
					break;

				case 'array_int':
					if (!is_array($criterion_requirements))
					{
						throw new RuntimeException('Criteria type ' . $criterion_name . ' is not of type int');
					}
					foreach ($criterion_requirements as $k => $v)
					{
						if (!is_int($v) && (string) (int) $v != $v)
						{
							throw new RuntimeException('Criteria type ' . $criterion_name . ', key ' . $k . ', value ' . $v . ' is not an int');
						}
						if (isset($this_criterion['min']) && $v < $this_criterion['min'])
						{
							throw new RuntimeException('Criteria type ' . $criterion_name . ' has value ' . $v . ' lower than legal mininum ' . $this_criterion['min']);
						}
						if (isset($this_criterion['max']) && $v > $this_criterion['max'])
						{
							throw new RuntimeException('Criteria type ' . $criterion_name . ' has value ' . $v . ' higher than legal maxinum ' . $this_criterion['max']);
						}
						$criterion_requirements[$k] = (int) $v;
					}
					break;

				case 'boards':
					break;

				default:
					throw new RuntimeException('Unknown criteria type ' . $this_criterion['type']);
					break;
			}

			$criteria[$criterion_name] = $criterion_requirements;
		}

		return $criteria;
	}

	abstract public function get_current_criteria_members($criteria, $account_id = null, $character_id = null);

	public function get_retroactive_criteria_members($criteria, $account_id = null, $character_id = null)
	{
		return $this->get_current_criteria_members($criteria, $account_id, $character_id);
	}
}
