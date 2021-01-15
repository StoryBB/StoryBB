<?php

/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Navigation;

use StoryBB\Helper\Navigation\Item;

class Section
{
	protected $id;
	protected $label;
	protected $items = [];
	protected $active = false;

	public function __construct(string $id, string $label)
	{
		$this->id = $id;
		$this->label = $label;
	}

	public function __get($key)
	{
		if (isset($this->$key))
		{
			return $this->$key;
		}

		return null;
	}

	public function add_item(Item $item)
	{
		$this->items[] = $item;
	}

	public function is_visible(): bool
	{
		foreach ($this->items as $item)
		{
			if ($item->is_visible())
			{
				return true;
			}
		}

		return false;
	}

	public function get_first_visible_item(): ?Item
	{
		$this->active = false;
		foreach ($this->items as $item)
		{
			if ($item->is_visible())
			{
				$this->active = true;
				return $item->set_active(true);
			}
		}

		return null;
	}

	public function find_by_params(array $params): ?Item
	{
		$this->active = false;

		foreach ($this->items as $item)
		{
			if ($item->is_visible() && $item->matches_params($params))
			{
				$this->active = true;
				return $item;
			}
		}

		return null;
	}
}
