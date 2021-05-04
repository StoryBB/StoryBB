<?php

/**
 * A trait to allow elements to become requirable.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Form\Element\Traits;

use StoryBB\Form\Rule;
use StoryBB\Form\Element\Inputtable;
use StoryBB\Form\Element\Traits;

/**
 * A trait to allow elements to become requirable.
 */
trait Requirable
{
	use Traits\Validatable;

	/**
	 * @var bool $required Whether the current field is required or not.
	 */
	protected $required = false;

	/**
	 * Marks the current object as requiring input.
	 *
	 * @return Inputtable Returns itself once the validation rule has been added
	 */
	public function required(): Inputtable
	{
		$this->required = true;
		return $this->add_validation_rule(new Rule\Required);
	}
}
