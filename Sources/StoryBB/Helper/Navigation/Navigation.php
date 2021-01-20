<?php

/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Navigation;

use StoryBB\Template;

class Navigation
{
	protected $tabs = [];

	public function __construct()
	{
		Template::add_layer('sidebar_navigation');
	}

	public function add_tab(Tab $tab)
	{
		$this->tabs[] = $tab;
		return $tab;
	}

	public function get_tabs(): array
	{
		$tabs = [];

		foreach ($this->tabs as $tab)
		{
			if ($tab->is_visible())
			{
				$tabs[] = $tab;
			}
		}

		return $tabs;
	}

	public function find_item_by_id($id): ?Item
	{
		foreach ($this->tabs as $tab)
		{
			foreach ($tab->sections as $section)
			{
				foreach ($section->items as $item)
				{
					if ($item->id == $id)
					{
						return $item;
					}
				}
			}
		}

		return null;
	}

	public function dispatch($params): void
	{
		$result = null;
		foreach ($this->tabs as $tab)
		{
			if ($result = $tab->find_by_params($params))
			{
				break;
			}
		}

		if (!$result)
		{
			foreach ($this->tabs as $tab)
			{
				if ($result = $tab->get_first_visible_item())
				{
					break;
				}
			}
		}

		if ($result)
		{
			$instance = $result->instantiate($this, $params);
			if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST' && is_callable([$instance, 'post_action']))
			{
				checkSession();
				$instance->post_action();
			}
			else
			{
				$instance->display_action();
			}
			return;
		}

		fatal_lang_error('no_access', false, 404);
	}

	public function export(array $base_params): array
	{
		$tabs = [];

		foreach ($this->tabs as $tab)
		{
			if (!$tab->is_visible())
			{
				continue;
			}

			$this_tab = [
				'id' => $tab->id,
				'label' => $tab->label,
			];

			if ($tab->active)
			{
				$this_tab['active'] = true;

				$this_tab['sections'] = [];
				foreach ($tab->sections as $section)
				{
					if ($section->is_visible())
					{
						$this_section = [
							'label' => $section->label,
							'links' => [],
						];

						foreach ($section->items as $item)
						{
							if ($item->is_visible())
							{
								$this_section['links'][] = [
									'label' => $item->label,
									'active' => $item->active,
									'url' => $item->get_url($base_params),
								];
							}
						}

						$this_tab['sections'][] = $this_section;
					}
				}
			}
			else
			{
				$this_tab['url'] = $tab->get_tab_url($base_params);
			}

			$tabs[] = $this_tab;
		}

		return $tabs;
	}
}
