<?php

/**
 * A base interface for form elements to implement.
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

class Buttons extends Traits\Base implements Inputtable
{
	use Traits\Labelable;
	use Traits\Chooseable;

	/**
	 * Take the current element, and return the formatted HTML.
	 *
	 * @return string The final HTML for this element
	 */
	public function render(): string
	{
		$rendercontext = [
			'name' => $this->name,
			'buttons' => $this->choices,
		];

		return ($this->templater->load('@partials/form/buttons.twig'))->render($rendercontext);
	}
}
