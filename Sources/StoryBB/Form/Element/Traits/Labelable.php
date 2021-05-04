<?php

/**
 * A trait to indicate that a given element can have a label.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Form\Element\Traits;

/**
 * A trait to indicate that a given element can have a label.
 */
trait Labelable
{
	/**
	 * @var string $label The label this element has
	 */
	protected $label = '';

	/**
	 * Return whether this element can have a label.
	 *
	 * @return bool True - by default it can have a label if it has this trait.
	 */
	public function labelable(): bool
	{
		return true;
	}

	/**
	 * Return whether this element has a current label.
	 *
	 * @return bool True if the element currently has a defined label.
	 */
	public function has_label(): bool
	{
		return !empty($this->label);
	}

	/**
	 * Sets the current label for this element.
	 *
	 * @return Inputtable Returns itself for the purposes of being chainable.
	 */
	public function label(string $label)
	{
		$this->label = $label;
		return $this;
	}

	/**
	 * Gets the current label for this element.
	 *
	 * @return string The current label.
	 */
	public function get_label(): string
	{
		return $this->label;
	}
}
