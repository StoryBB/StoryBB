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
use StoryBB\Form\Rule\Validational;

trait Validatable
{
	/** @var array $validation_rules An array of validation rules attached to this element. */
	protected $validation_rules = [];

	/**
	 * Apply validation to this form data.
	 *
	 * @param array $data The raw form data
	 * @return array An array of error messages generated, if any
	 */
	public function validate(array $data): array
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

	/**
	 * Add a validation rule to the current element.
	 *
	 * @param Validational $rule A validation rule object
	 * @return Inputtable This object, returned for the purposes of chaining.
	 */
	public function add_validation_rule(Validational $rule)
	{
		$this->validation_rules[] = $rule;
		return $this;
	}
}
