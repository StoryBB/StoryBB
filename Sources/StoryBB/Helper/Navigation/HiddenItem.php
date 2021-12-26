<?php

/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Navigation;

class HiddenItem extends Item
{
	protected $id;
	protected $label = '';
	protected $route = [];
	protected $controller = '';
	protected $permissions = [];
	protected $icon = '';
	protected $match_as = [];

	protected $active = false;

	protected $enabled = true;

	public function __construct(string $id, string $label, array $route, string $controller, array $permissions, string $icon = '', array $match_as = [])
	{
		$this->id = $id;
		$this->label = $label;
		$this->route = $route;
		$this->controller = $controller;
		$this->permissions = $permissions;
		$this->icon = $icon;
		$this->match_as = $match_as;
	}

	public function is_displayable(): bool
	{
		return false;
	}

	public function display_routing(): array
	{
		return $this->match_as;
	}
}
