<?php

/**
 * A base interface for form elements to implement.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Form\Rule;

use StoryBB\Form\Rule\Exception as RuleException;

class IntegerComparison
{
	private $operator;
	private $value;

	public function __construct(string $operator, int $value)
	{
		if (!in_array($operator, ['<=', '<', '=', '!=', '>', '>=']))
		{
			throw new Exception('Invalid comparison operator');
		}

		$this->operator = $operator;
		$this->value = $value;
	}

	public function validate($value)
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
