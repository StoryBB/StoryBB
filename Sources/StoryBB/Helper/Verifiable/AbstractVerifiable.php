<?php

/**
 * The base class to invoke the verification module (aka CAPTCHA).
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Verifiable;

abstract class AbstractVerifiable
{
	protected $id;

	public function __construct(string $id)
	{
		$this->id = $id;
	}

	public function is_available(): bool
	{
		return false;
	}

	public function render()
	{
		return '';
	}

	public function get_settings(): array
	{
		return [];
	}

	public function put_settings(&$save_vars)
	{
		// Most of the time you won't need to do anything because they're regular settings,
		// that the regular framework takes care of. But if you need to do something, here's where.
		return;
	}
}
