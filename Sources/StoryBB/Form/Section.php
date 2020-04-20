<?php

/**
 * A section for form elements to live in.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Form;

use StoryBB\Form\Base;
use StoryBB\Form\Element\Inputtable;
use StoryBB\Form\Element\Traits;
use StoryBB\Form\Exception as FormException;

class Section
{
	use Traits\Labelable;

	protected $fields = [];

	protected $expanded = false;

	protected $label = '';

	protected $templater = null;
	protected $rawdata = null;

	protected $form;

	public function __construct(Base $form)
	{
		$this->form = $form;
	}

	public function expanded(): Section
	{
		$this->expanded = true;
		return $this;
	}

	public function collapsed(): Section
	{
		$this->expanded = false;
		return $this;
	}

	public function add(Inputtable $field): Inputtable
	{
		$fieldname = $field->get_name();
		if (isset($this->fields[$fieldname]))
		{
			throw new FormException($fieldname . ' already declared');
		}

		$this->fields[$fieldname] = $field;
		return $field;
	}

	public function is_expanded(): bool
	{
		return $this->expanded;
	}

	public function get_fields(): array
	{
		return $this->fields;
	}

	public function inject_templater($templater, $rawdata, $replace = false)
	{
		if (empty($this->templater) || $replace)
		{
			$this->templater = $templater;
			$this->rawdata = $rawdata;
		}
	}

	public function render($field)
	{
		return $field->render($this->templater, $this->rawdata);
	}
}
