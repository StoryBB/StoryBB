<?php

/**
 * A validation to verify that an input is an integer.
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
 * A validation to verify that an input is an integer.
 */
class IsInteger implements Validational
{
	/**
	 * Validates a form value as being an integer.
	 *
	 * @param mixed $value The raw value as submitted by the user
	 * @return void
	 * @throws RuleException if not an integer
	 */
	public function validate($value): void
	{
		if (!filter_var($value, FILTER_VALIDATE_INT))
		{
			throw new RuleException('Not a valid integer');
		}
	}
}
