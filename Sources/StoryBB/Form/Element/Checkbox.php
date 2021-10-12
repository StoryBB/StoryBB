<?php

/**
 * Represents a checkbox in a form.
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
 * Represents a checkbox in a form.
 */
class Checkbox extends Traits\Base implements Inputtable
{
	use Traits\Labelable;
	use Traits\Requirable;

	/**
	 * Take the current element, and return the formatted HTML.
	 *
	 * @return string The final HTML for this element
	 */
	public function render(): string
	{
		$rendercontext = [
			'name' => $this->name,
			'required' => $this->required,
			'checked' => !empty($this->rawdata[$this->name]),
		];

		return ($this->templater->load('@partials/form/checkbox.twig'))->render($rendercontext);
	}

	/**
	 * Given raw data from the form, get a usable logical value.
	 *
	 * @param array $data The raw data in the form.
	 * @return mixed Checkbox returns bool true if ticked, bool false if not
	 */
	public function get_value_from_raw(array $data)
	{
		return !empty($data[$this->name]);
	}
}
