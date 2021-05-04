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

namespace StoryBB\Form\Element\Traits;

use Latte\Engine;

abstract class Base
{
	protected $name = [];
	protected $attrs = [];
	protected $templater = null;

	/**
	 * Constructor for form elements.
	 *
	 * @param string $name The name of the element
	 */
	public function __construct(string $name)
	{
		$this->name = $name;
	}

	/**
	 * Injector for the template engine into the form object.
	 *
	 * @param Latte\Engine $templater The template engine to use.
	 */
	public function accept_templater(Engine $templater)
	{
		$this->templater = $templater;
	}

	/**
	 * Returns the name of this particular element.
	 *
	 * @return string The name of this element (in the form)
	 */
	public function get_name(): string
	{
		return $this->name;
	}

	/**
	 * Return whether this element can have a label.
	 *
	 * @return bool False - by default it cannot have a label.
	 */
	public function labelable(): bool
	{
		return false;
	}

	/**
	 * Return whether this element has a label currently.
	 *
	 * @return bool False - by default it cannot have a label at all.
	 */
	public function has_label(): bool
	{
		return false;
	}

	/**
	 * Given raw data from the form, get a usable logical value.
	 *
	 * @param array $data The raw data in the form.
	 * @return mixed Checkbox returns bool true if ticked, bool false if not
	 */
	public function get_value_from_raw(array $data)
	{
		return $data[$this->name] ?? null;
	}

	/**
	 * Take the current element, and return the formatted HTML.
	 *
	 * @param Latte\Engine $templater The form template render engine
	 * @param array $rawdata The submitted raw data for the format
	 * @return string The final HTML for this element
	 */
	abstract public function render(Engine $templater, array $rawdata): string;
}
