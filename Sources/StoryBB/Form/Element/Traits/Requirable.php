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

use StoryBB\Form\Rule;
use StoryBB\Form\Element\Inputtable;
use StoryBB\Form\Element\Traits;

trait Requirable
{
	use Traits\Validatable;

	protected $required = false;

	public function required(): Inputtable
	{
		$this->required = true;
		return $this->add_validation_rule(new Rule\Required);
	}
}
