<?php

/**
 * A base interface for form elements to implement.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Form\Element\Traits;

use StoryBB\Form\Rule\Exception as RuleException;

trait Validatable
{
	protected $validation_rules = [];

	public function validate($data): array
	{
		$errors = [];
		foreach ($this->validation_rules as $rule)
		{
			try
			{
				$rule->validate($this->get_value_from_raw($data));
			}
			catch (RuleException $e)
			{
				$errors[] = $e->getMessage();
			}
		}

		return $errors;
	}

	public function add_validation_rule($rule)
	{
		$this->validation_rules[] = $rule;
		return $this;
	}
}
