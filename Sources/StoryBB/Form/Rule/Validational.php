<?php

/**
 * A base interface for form validation rules to implement.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Form\Rule;

interface Validational
{
	/**
	 * Validates a form value.
	 *
	 * @param mixed $value The raw value as submitted by the user
	 * @return void
	 * @throws RuleException if not valid
	 */
	public function validate($value): void;
}
