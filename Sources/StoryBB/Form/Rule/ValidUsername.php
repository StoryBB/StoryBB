<?php

/**
 * Validates a form value as being a valid username.
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
use StoryBB\StringLibrary;

/**
 * Validates a form value as being a valid username.
 */
class ValidUsername implements Validational
{
	/**
	 * Validates a form value as being a valid username.
	 *
	 * @param mixed $value The raw value as submitted by the user
	 * @return void
	 * @throws RuleException if not a theoretically valid username.
	 */
	public function validate($value): void
	{
		if (StringLibrary::strlen($value) > 80)
		{
			throw new RuleException('Error:username_too_long');
		}
		if (preg_match('~[<>&"\'=\\\]~', preg_replace('~(&#(\\d{1,7}|x[0-9a-fA-F]{1,6});)~', '', $value)))
		{
			throw new RuleException('Error:error_invalid_characters_username');
		}
	}
}
