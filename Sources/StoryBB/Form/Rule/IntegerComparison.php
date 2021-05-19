<?php

/**
 * Validates a given integer is comparable successfully to another integer.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Form\Rule;

use StoryBB\Form\Rule\Exception as RuleException;
use StoryBB\Form\Rule\Validational;

/**
 * Validates a given integer is comparable successfully to another integer.
 */
class IntegerComparison implements Validational
{
	/**
	 * @var string $operator The operator to use for the comparison.
	 */
	private $operator;

	/**
	 * @var int $value The integer value to compare against.
	 */
	private $value;

	/**
	 * Constructor, accepts the details of the comparison that can be made for this rule.
	 *
	 * @param string $operator The comparison operator for this validation rule.
	 * @param int $value The value for this validation rule.
	 * @throws RuleException if the comparison is not a valid operator.
	 */
	public function __construct(string $operator, int $value)
	{
		if (!in_array($operator, ['<=', '<', '=', '!=', '>', '>=']))
		{
			throw new RuleException('Invalid comparison operator');
		}

		$this->operator = $operator;
		$this->value = $value;
	}

	/**
	 * Performs the comparison for validation purposes.
	 *
	 * @param mixed $value The raw value as submitted by the user
	 * @return void
	 * @throws RuleException if the comparison doesn't work out (e.g. the rule is int < 5 but the supplied value is 10)
	 */
	public function validate($value): void
	{
		if ($this->operator == '<' && $value >= $this->value)
		{
			throw new RuleException('The value must be lower than ' . $this->value);
		}

		if ($this->operator == '<=' && $value > $this->value)
		{
			throw new RuleException('The maximum value is ' . $this->value);
		}

		if ($this->operator == '=' && $value != $this->value)
		{
			throw new RuleException('The value is not set to ' . $this->value);
		}

		if ($this->operator == '!=' && $value == $this->value)
		{
			throw new RuleException('The value is set to ' . $this->value);
		}

		if ($this->operator == '>=' && $value < $this->value)
		{
			throw new RuleException('The minimum value is ' . $this->value);
		}

		if ($this->operator == '>' && $value <= $this->value)
		{
			throw new RuleException('The value must be higher than ' . $this->value);
		}
	}
}
