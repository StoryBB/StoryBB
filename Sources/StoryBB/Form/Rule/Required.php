<?php

/**
 * A validation rule that a value must be provided.
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
 * A validation rule that a value must be provided.
 */
class Required implements Validational
{
	/**
	 * Validates a form value as being provided.
	 *
	 * @param mixed $value The raw value as submitted by the user
	 * @return void
	 * @throws RuleException if the supplied value is not supplied.
	 */
	public function validate($value): void
	{
		if (is_numeric($value))
		{
			if ($value == 0)
			{
				throw new RuleException('This must be more than zero.');
			}
			return;
		}

		if (is_string($value))
		{
			if (trim($value) === '')
			{
				throw new RuleException('This cannot be empty.');
			}
			return;
		}

		throw new RuleException('Unexpected form input.');
	}
}
