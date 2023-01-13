<?php

/**
 * Represents an element just with raw HTML in it.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Form\Element;

use StoryBB\Form\Element\Inputtable;
use StoryBB\Form\Element\Traits;

/**
 * Represents an element just with raw HTML in it.
 */
class StaticHtml extends Traits\Base implements Inputtable
{
	use Traits\Labelable;

	protected $content = '';

	/**
	 * Accept the content to display in the form.
	 *
	 * @param string $content The content to display, unfiltered.
	 * @return Inputtable This element for fluent interfacing.
	 */
	public function content(string $content): Inputtable
	{
		$this->content = $content;
		return $this;
	}

	/**
	 * Take the current element, and return the formatted HTML.
	 *
	 * @return string The final HTML for this element
	 */
	public function render(): string
	{
		return $this->content;
	}
}
