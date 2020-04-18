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

trait Labelable
{
	protected $label = '';

	public function labelable(): bool
	{
		return true;
	}

	public function has_label(): bool
	{
		return !empty($this->label);
	}

	public function label(string $label)
	{
		$this->label = $label;
		return $this;
	}

	public function get_label(): string
	{
		return $this->label;
	}
}
