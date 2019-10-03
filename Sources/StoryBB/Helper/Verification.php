<?php

/**
 * The base class to invoke the verification module (aka CAPTCHA).
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

use StoryBB\ClassManager;
use StoryBB\Helper\Verifiable\UnverifiableException;

class Verification
{
	private static $cache = [];
	private $id;
	private $components = [];
	private $options = [
		'max_errors' => 3,
		'max_before_refresh' => 3,
	];

	public static function get($id)
	{
		if (!isset(static::$cache[$id]))
		{
			static::$cache[$id] = new static($id);
		}
		return static::$cache[$id];
	}

	protected function __construct(string $id)
	{
		$this->id = $id;
		$this->components = [];

		foreach (ClassManager::get_classes_implementing('StoryBB\\Helper\\Verifiable\\Verifiable') as $class)
		{
			$verifiable = new $class($id);
			if ($verifiable->is_available())
			{
				$this->components[] = $verifiable;
			}
		}

		// First time?
		if (empty($_SESSION[$this->id . '_vv']))
		{
			$this->reset_verification();
		}
		// Too many invocations?
		elseif (!empty($_SESSION[$this->id . '_vv']['count']) && $_SESSION[$this->id . '_vv']['count'] > $this->options['max_before_refresh'])
		{
			$this->reset_verification();
		}
		// Previously completed?
		elseif (!empty($_SESSION[$this->id . '_vv']['did_pass']))
		{
			$this->reset_verification();
		}

		$_SESSION[$this->id . '_vv']['count']++;
	}

	public function id(): string
	{
		return $this->id;
	}

	protected function reset_verification()
	{
		// First reset the verification.
		$_SESSION[$this->id . '_vv']['count'] = 0;
		$_SESSION[$this->id . '_vv']['did_pass'] = false;
		foreach ($this->components as $component)
		{
			$component->reset();
		}
	}

	public function verify(): array
	{
		loadLanguage('Errors');
		$errors = [];

		foreach ($this->components as $component)
		{
			try {
				$component->verify();
			}
			catch (UnverifiableException $e)
			{
				$errors[] = $e->getMessage();
			}
		}

		if (count($errors) > $this->options['max_errors'])
		{
			$this->reset_verification();
		}

		if (empty($errors))
		{
			$_SESSION[$this->id . '_vv']['did_pass'] = true;
		}

		return $errors;
	}

	public function get_renders(): array
	{
		$renders = [];
		foreach ($this->components as $component)
		{
			$renders[] = $component->render();
		}
		return $renders;
	}
}
