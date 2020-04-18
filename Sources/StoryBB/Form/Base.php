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

namespace StoryBB\Form;

use RuntimeException;
use StoryBB\Dependency\RequestVars;
use StoryBB\Dependency\Session;
use StoryBB\Dependency\Templater;
use StoryBB\Form\Rule\Exception as RuleException;

abstract class Base
{
	use RequestVars;
	use Session;
	use Templater;

	protected $action = '';

	protected $sections = [];

	protected $data;
	protected $errors = [];

	protected $finalised = false;

	public function __construct(string $action)
	{
		$this->action = $action;

		$this->define_form();
	}

	abstract public function define_form();

	public function secondary_definition()
	{

	}

	public function get_data(): array
	{
		$this->finalise();

		if ($errors = $this->validate($this->data))
		{
			$this->errors = $errors;
			return [];
		}

		foreach ($this->sections as $section)
		{
			foreach ($section->get_fields() as $field)
			{
				$data[$field->get_name()] = $field->get_value_from_raw($this->data);
			}
		}
		return $data;
	}

	protected function populate_raw_data()
	{
		if ($this->data === null)
		{
			$this->data = $this->requestvars()->request->all();
		}
	}

	public function set_data($name, $value)
	{
		if ($this->finalised)
		{
			throw new RuntimeException('Form has already been finalised, cannot change');
		}
		$this->populate_raw_data();
		$this->data[$name] = $value;
	}

	protected function finalise()
	{
		$this->populate_raw_data();
		$this->secondary_definition();
		$this->finalised = true;
	}

	public function validate(array $data): array
	{
		$this->errors = [];
		foreach ($this->sections as $section)
		{
			foreach ($section->get_fields() as $field)
			{
				if (is_callable([$field, 'validate']))
				{
					$possible_errors = $field->validate($data);
					if ($possible_errors)
					{
						$this->errors[$field->get_name()] = $possible_errors;
					}
				}
			}
		}

		return $this->errors;
	}

	public function add_section($name)
	{
		if (isset($this->sections[$name]))
		{
			throw new RuntimeException('Section ' . $name . ' already exists');
		}

		$section = new Section;
		$this->sections[$name] = $section;
		return $this->sections[$name];
	}

	public function render()
	{
		$templater = $this->templater();

		$this->finalise();

		foreach ($this->sections as $section)
		{
			$section->inject_templater($templater, $this->data);
		}
		$rendercontext = [
			'action' => $this->action,
			'sections' => $this->sections,
			'errors' => $this->errors,
		];
		return $this->templater()->renderToString('form/form.latte', $rendercontext);
	}
}
