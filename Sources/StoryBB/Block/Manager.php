<?php

/**
 * This class manages blocks being loaded etc.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Block;

use RuntimeException;
use StoryBB\App;
use StoryBB\Dependency\Database;
use StoryBB\Dependency\SiteSettings;
use StoryBB\Dependency\TemplateRenderer;

/**
 * Manages blocks.
 */
class Manager
{
	use Database;
	use SiteSettings;
	use TemplateRenderer;

	protected $page_blocks = [];

	protected $show_blocks = true;
	protected $rendered = false;

	public function load_current_blocks()
	{
		global $user_info, $context;

		$db = $this->db();

		$result = $db->query('', '
			SELECT id_instance, class, visibility, configuration, region, position, active
			FROM {db_prefix}block_instances
			WHERE active = {int:active}
			ORDER BY region, position',
			[
				'active' => 1,
			]
		);

		$this->page_blocks = [];

		while ($row = $db->fetch_assoc($result))
		{
			// Apply visibility.
			if (!empty($row['visibility']))
			{
				// Unbundle the JSON. If we can't unbundle it, assume it's not visible.
				$visibility = @json_decode($row['visibility'], true);
				if (empty($visibility))
				{
					continue;
				}

				// Does this block require groups? Do you have any of those groups?
				if (!empty($visibility['groups_include']) && count(array_intersect($visibility['groups_include'], $user_info['groups'])) === 0)
				{
					continue;
				}

				// Does this block exclude any groups? Do you have any of those?
				if (!empty($visibility['groups_exclude']) && count(array_intersect($visibility['groups_exclude'], $user_info['groups'])) > 0)
				{
					continue;
				}
			}

			// Apply filtering to current setup.
			// Is this filtered to a (legacy) action? If so, check it's on the list.
			if (empty($context['current_action']) || (!empty($visibility['action']) && !in_array($context['current_action'], $visibility['action'])))
			{
				continue;
			}

			if (!empty($visibility['routes']))
			{
				if (!$this->match_route($context['routing'] ?? null, $visibility['routes']))
				{
					continue;
				}
			}

			$row['object'] = null;
			if (!class_exists($row['class']))
			{
				continue;
			}
			$config = !empty($row['configuration']) ? json_decode($row['configuration'], true) : [];
			$row['object'] = App::make($row['class'], $config);

			$this->page_blocks[$row['region']][$row['id_instance']] = $row;
		}
		$db->free_result($result);
	}

	protected function match_route(array $routing, array $visibility): bool
	{
		if (empty($routing['_route']))
		{
			return false;
		}

		foreach ($visibility as $possible_route)
		{
			if (empty($possible_route['route_params']))
			{
				// If there's no route parameters, we're just matching the route, that's easy.
				if ($possible_route['route'] == $routing['_route'])
				{
					return true;
				}
			}
			else
			{
				// Otherwise we're matching the route then all the parameters.
				if ($possible_route['route'] == $routing['_route'])
				{
					foreach ($possible_route['route_params'] as $param => $value)
					{
						if (!isset($routing[$param]) || $routing[$param] != $value)
						{
							continue;
						}

						return true;
					}
				}
			}
		}

		return false;
	}

	public function render_region(string $region)
	{
		if (empty($this->page_blocks[$region]))
		{
			return '';
		}

		$this->rendered = true;

		if (!$this->show_blocks)
		{
			return '';
		}

		$block_context = [
			'region' => $region,
			'instances' => [],
		];

		$template_cache = [];
		$compiled_cache = [];

		foreach ($this->page_blocks[$region] as $instance_id => $instance_details)
		{
			$instance = $instance_details['object'];
			$partial_name = '@partials/block_containers/' . $instance->get_render_template() . '.twig';

			$block_config = $instance->get_configuration();

			$toggle = false;
			if (!empty($block_config['collapsible']))
			{
				$toggle = new \StoryBB\Helper\Toggleable($instance->get_block_title());
				$toggle->addCollapsible('#block_' . $instance_id . ' .block_content');
				$toggle->addImageToggle('#block_' . $instance_id . ' .img_toggle');
				$toggle->addLinkToggle('#block_' . $instance_id . ' .block_title a');
				$toggle->userOption('collapse_block_' . $instance_id);
				$toggle->cookieName('cb_' . $instance_id);
				$toggle->attach();
			}

			$block_context['instances'][] = $this->templaterenderer()->render($partial_name, [
				'instance' => $instance_id,
				'title' => new \LightnCandy\SafeString($instance->get_block_title()),
				'content' => new \LightnCandy\SafeString($instance->get_block_content()),
				'blocktype' => $instance->get_blocktype(),
				'icon' => isset($block_config['icon']) ? $block_config['icon'] : '',
				'fa_icon' => isset($block_config['fa-icon']) ? $block_config['fa-icon'] : '',
				'collapsible' => !empty($toggle),
				'collapsed' => $toggle && $toggle->currently_collapsed(),
			]);
		}

		return $this->templaterenderer()->render('@partials/block_region.twig', [
			'region' => $region,
			'instances' => $block_context['instances'],
		]);
	}

	/**
	 * Sets whether blocks should be shown/hidden on this page (on by default)
	 *
	 * @param bool $visible True to show blocks on the current page, false to hide all blocks.
	 * @throws RuntimeException if blocks have already been rendered prior to this change being called
	 */
	public function set_overall_block_visibility(bool $visible): void
	{
		if ($this->rendered)
		{
			throw new RuntimeException('Cannot alter overall block visibility as blocks have already been rendered.');
		}

		$this->show_blocks = $visible;
	}
}
