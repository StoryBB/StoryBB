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

namespace StoryBB\Form\Rule;

use StoryBB\Form\Rule\Exception as RuleException;

class ValidChoices
{
	private $choices;

	public function __construct(array $choices)
	{
		$this->choices = $choices;
	}

	public function validate($value)
	{
		if (!in_array($value, $this->choices))
		{
			throw new RuleException('Please select from the choices given');
		}
	}
}
