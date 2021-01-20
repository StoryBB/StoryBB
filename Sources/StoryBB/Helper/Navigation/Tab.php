<?php

/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Navigation;

use StoryBB\Helper\Navigation\Section;
use StoryBB\Helper\Navigation\Item;

class Tab
{
	protected $id;
	protected $label;
	protected $sections = [];
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

	public function add_section(Section $section)
	{
		$this->sections[] = $section;
		return $section;
	}

	public function is_visible(): bool
	{
		return $this->count_visible_sections() > 0;
	}

	public function count_visible_sections(): int
	{
		$count = 0;
		foreach ($this->sections as $section)
		{
			if ($section->is_visible())
			{
				$count++;
			}
		}

		return $count;
	}

	public function get_tab_url(array $base_params): ?string
	{
		foreach ($this->sections as $section)
		{
			if ($section->is_visible())
			{
				foreach ($section->items as $item)
				{
					if ($item->is_visible())
					{
						return $item->get_url($base_params);
					}
				}
			}
		}

		return null;
	}

	public function get_first_visible_item(): ?Item
	{
		$this->active = false;
		foreach ($this->sections as $section)
		{
			if ($section->is_visible() && ($item = $section->get_first_visible_item()))
			{
				$this->active = true;
				return $item;
			}
		}

		return null;
	}

	public function find_by_params(array $params): ?Item
	{
		$this->active = false;

		foreach ($this->sections as $section)
		{
			if ($section->is_visible() && ($item = $section->find_by_params($params)))
			{
				$this->active = true;
				return $item;
			}
		}

		return null;
	}
}
