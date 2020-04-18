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

use Latte\Engine;

abstract class Base
{
	protected $name = [];
	protected $attrs = [];
	protected $templater = null;

	public function __construct(string $name)
	{
		$this->name = $name;
	}

	public function accept_templater($templater)
	{
		$this->templater = $templater;
	}

	public function get_name(): string
	{
		return $this->name;
	}

	public function labelable(): bool
	{
		return false;
	}

	public function has_label(): bool
	{
		return false;
	}

	public function get_value_from_raw($data)
	{
		return $data[$this->name] ?? null;
	}

	abstract function render(Engine $templater, array $rawdata): string;
}