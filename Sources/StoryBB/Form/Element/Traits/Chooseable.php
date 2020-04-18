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

namespace StoryBB\Form\Element\Traits;

use StoryBB\Form\Element\Inputtable;
use StoryBB\Form\Element\Traits;
use StoryBB\Form\Rule;

trait Chooseable
{
	use Traits\Validatable;

	protected $choices = [];

	public function choices(array $choices): Inputtable
	{
		$this->choices = $choices;
		$this->add_validation_rule(new Rule\ValidChoices(array_keys($choices)));
		return $this;
	}
}
