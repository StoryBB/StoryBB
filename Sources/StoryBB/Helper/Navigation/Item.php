<?php

/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Navigation;

class Item
{
	protected $id;
	protected $label = '';
	protected $route = [];
	protected $controller = '';
	protected $permissions = [];
	protected $icon = '';

	protected $active = false;

	protected $enabled = true;

	public function __construct(string $id, string $label, array $route, string $controller, array $permissions, string $icon = '')
	{
		$this->id = $id;
		$this->label = $label;
		$this->route = $route;
		$this->controller = $controller;
		$this->permissions = $permissions;
		$this->icon = $icon;
	}

	public function is_enabled(callable $function): Item
	{
		$this->enabled = $function();
		return $this;
	}

	public function __get($key)
	{
		if (isset($this->$key))
		{
			return $this->$key;
		}

		return null;
	}

	public function is_visible(): bool
	{
		if (!$this->enabled || empty($this->permissions))
		{
			return false;
		}

		return allowedTo($this->permissions);
	}

	public function get_url(array $base_params): string
	{
		global $scripturl;

		foreach ($this->route as $key => $value)
		{
			$base_params[$key] = $value;
		}

		$params = [];
		foreach ($base_params as $key => $value)
		{
			$params[] = $key .= '=' . $value;
		}

		return $scripturl . '?' . implode(';', $params);
	}

	public function matches_params(array $params): bool
	{
		$this->active = false;
		foreach ($this->route as $key => $value)
		{
			if (!isset($params[$key]) || $params[$key] != $value)
			{
				return false;
			}
		}

		$this->active = true;
		return true;
	}

	public function set_active(bool $active): Item
	{
		$this->active = $active;
		return $this;
	}

	public function instantiate(Navigation $nav, array $params)
	{
		return new $this->controller($nav, $params);
	}
}
