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

namespace StoryBB\Form;

use RuntimeException;
use StoryBB\Dependency\RequestVars;
use StoryBB\Dependency\Session;
use StoryBB\Dependency\TemplateRenderer;
use StoryBB\Helper\Random;
use StoryBB\Form\Rule\Exception as RuleException;
use StoryBB\Phrase;

abstract class Base
{
	use RequestVars;
	use Session;
	use TemplateRenderer;

	protected $action = '';

	protected $sections = [];

	protected $hidden = [];

	protected $data;
	protected $errors = [];

	protected $finalised = false;

	const CSRF_TOKEN_EXPIRY = 10800;

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

		$session = $this->session();
		$this->hidden['session'][$session->get('session_var')] = $session->get('session_value');

		[$token, $expiry] = $this->get_form_token();
		if ($token !== '' && $expiry > time())
		{
			// If the expiry time is still in the future, put the token into the form.
			$this->hidden['csrftoken'] = $token;
		}
		else
		{
			// We either don't have a token, or it's expired.
			$this->create_form_token();
		}

		$this->finalised = true;

		foreach ($this->sections as $section)
		{
			foreach ($section->get_fields() as $field)
			{
				$field_name = $field->get_name();
				if (!empty($this->errors[$field_name]))
				{
					$field->set_errors($this->errors[$field_name]);
				}
			}
		}
	}

	public function validate(array $data): array
	{
		$this->errors = [];

		if (isset($this->hidden['session']))
		{
			$stored_sessionvar = array_keys($this->hidden['session'])[0];
			if (!isset($data[$stored_sessionvar]) || $data[$stored_sessionvar] !== $this->hidden['session'][$stored_sessionvar])
			{
				$this->errors['_form'][] = new Phrase('Errors:session_timeout');
			}
		}

		if (isset($this->hidden['csrftoken']))
		{
			$class = get_class($this);
			[$token, $expiry] = $this->get_form_token();
			if (empty($data['csrftoken']) || $data['csrftoken'] !== $token || $expiry < time())
			{
				$this->errors['_form'][] = new Phrase('Errors:token_verify_fail');
				$this->create_form_token();
			}
		}

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

		$section = new Section($this);
		$this->sections[$name] = $section;
		return $this->sections[$name];
	}

	public function render()
	{
		$templater = $this->templaterenderer();

		$this->finalise();

		foreach ($this->sections as $section)
		{
			$section->inject_templater($templater, $this->data);
		}

		// We store the hidden items in a slightly different way, let's flatten that out.
		$hidden = [];
		foreach ($this->hidden as $key => $value)
		{
			if (is_bool($value))
			{
				$hidden[$key] = $value ? 1 : 0;
				continue;
			}

			if (is_scalar($value))
			{
				$hidden[$key] = $value;
				continue;
			}

			if (is_array($value))
			{
				foreach ($value as $subkey => $subvalue)
				{
					$hidden[$subkey] = $subvalue;
				}
				continue;
			}

			throw new RuntimeException('Hidden value ' . $key . ' of unsupported type');
		}
		$rendercontext = [
			'hidden' => $hidden,
			'action' => $this->action,
			'sections' => $this->sections,
			'errors' => $this->errors,
		];
		return ($this->templaterenderer()->load('@partials/form.twig'))->render($rendercontext);
	}

	protected function get_form_token()
	{
		$session = $this->session();
		$class = get_class($this);

		return $session->get('formtokens/' . $class, ['', 0]);
	}

	protected function create_form_token()
	{
		$session = $this->session();
		$this->clean_expired_form_tokens();

		$class = get_class($this);
		// Creates a token and sets an expiry for three hours (by default) in the future.
		$this->hidden['csrftoken'] = bin2hex(Random::get_random_bytes(32));
		$session->set('formtokens/' . $class, [$this->hidden['csrftoken'], time() + static::CSRF_TOKEN_EXPIRY]);
	}

	protected function clean_expired_form_tokens()
	{
		$session = $this->session();
		$formtokens = $session->get('formtokens');

		if (empty($formtokens))
		{
			return;
		}

		foreach ($formtokens as $key => $token)
		{
			[$token, $expiry] = $token;
			if ($expiry < time())
			{
				$session->remove('formtokens/' . $key);
			}
		}
	}
}
