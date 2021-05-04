<?php

/**
 * Validates that a given input matches a set list of choices.
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
 * Validates that a given input matches a set list of choices.
 */
class ValidChoices implements Validational
{
	/**
	 * @var array $choices The list of legal choices.
	 */
	private $choices;

	/**
	 * Constructor. Accepts the list of possible legal values for the element.
	 *
	 * @param array $choices An array of valid choices for this element.
	 */
	public function __construct(array $choices)
	{
		$this->choices = $choices;
	}

	/**
	 * Validates a form value as being a valid username.
	 *
	 * @param mixed $value The raw value as submitted by the user
	 * @return void
	 * @throws RuleException if the supplied value is not an item in the list of possible choices.
	 */
	public function validate($value): void
	{
		if (!in_array($value, $this->choices))
		{
			throw new RuleException('Please select from the choices given');
		}
	}
}
