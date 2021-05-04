<?php

/**
 * Defines that this element is choosable from a set list.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Form\Element\Traits;

use StoryBB\Form\Element\Inputtable;
use StoryBB\Form\Element\Traits;
use StoryBB\Form\Rule;

/**
 * Defines that this element is choosable from a set list.
 */
trait Chooseable
{
	use Traits\Validatable;

	/** @var array $choices The keys/values for this element. */
	protected $choices = [];

	/**
	 * Set the choices that are choosable in this element.
	 *
	 * @param array $choices The choices that are valid for this element (key/value pairs)
	 * @return Inputtable The original element, returned for chaining.
	 */
	public function choices(array $choices): Inputtable
	{
		$this->choices = $choices;
		$this->add_validation_rule(new Rule\ValidChoices(array_keys($choices)));
		return $this;
	}
}
