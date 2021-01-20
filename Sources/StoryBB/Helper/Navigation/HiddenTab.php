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

class HiddenTab extends Tab
{
	public function __construct(string $id, string $label = null)
	{
		$this->id = $id;
		$this->label = $label;
	}

	public function is_visible(): bool
	{
		return false;
	}

	public function count_visible_sections(): int
	{
		return 0;
	}

	public function get_tab_url(array $base_params): ?string
	{
		return null;
	}

	public function get_first_visible_item(): ?Item
	{
		return null;
	}

	public function find_by_params(array $params): ?Item
	{
		foreach ($this->sections as $section)
		{
			if ($section->is_visible() && ($item = $section->find_by_params($params)))
			{
				return $item;
			}
		}

		return null;
	}
}
