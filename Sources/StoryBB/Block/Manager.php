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

/**
 * Manages blocks.
 */
class Manager
{
	protected static $show_blocks = true;
	protected static $rendered = false;

	public static function load_current_blocks()
	{
		global $smcFunc, $user_info, $context;

		$result = $smcFunc['db']->query('', '
			SELECT id_instance, class, visibility, configuration, region, position, active
			FROM {db_prefix}block_instances
			WHERE active = {int:active}
			ORDER BY region, position',
			[
				'active' => 1,
			]
		);

		$instances = [];

		while ($row = $smcFunc['db']->fetch_assoc($result))
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

			$row['object'] = null;
			if (!class_exists($row['class']))
			{
				continue;
			}
			$config = !empty($row['configuration']) ? json_decode($row['configuration'], true) : [];
			$row['object'] = new $row['class']($config);

			// Force a preload of the content.
			$row['object']->get_block_content();

			$instances[$row['region']][$row['id_instance']] = $row;
		}
		$smcFunc['db']->free_result($result);

		return $instances;
	}

	public static function render_region(string $region)
	{
		global $context;

		if (empty($context['page_blocks'][$region]))
		{
			return '';
		}

		static::$rendered = true;

		if (!static::$show_blocks)
		{
			return '';
		}

		$block_context = [
			'region' => $region,
			'instances' => [],
		];

		$template_cache = [];
		$compiled_cache = [];

		foreach ($context['page_blocks'][$region] as $instance_id => $instance_details)
		{
			$instance = $instance_details['object'];
			$partial_name = $instance->get_render_template();

			if (!isset($template_cache[$partial_name]))
			{
				$template_cache[$partial_name] = \StoryBB\Template::load_partial($partial_name);
			}
			$template = $template_cache[$partial_name];

			if (!isset($compiled_cache[$partial_name]))
			{
				$compiled_cache[$partial_name] = \StoryBB\Template::compile($template, [], $partial_name . \StoryBB\Template::get_theme_id('partials', $partial_name));
			}
			$phpStr = $compiled_cache[$partial_name];

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

			$block_context['instances'][] = \StoryBB\Template::prepare($phpStr, [
				'instance' => $instance_id,
				'title' => new \LightnCandy\SafeString($instance->get_block_title()),
				'content' => new \LightnCandy\SafeString($instance->get_block_content()),
				'blocktype' => self::get_blocktype($instance),
				'icon' => isset($block_config['icon']) ? $block_config['icon'] : '',
				'fa-icon' => isset($block_config['fa-icon']) ? $block_config['fa-icon'] : '',
				'collapsible' => !empty($toggle),
				'collapsed' => $toggle && $toggle->currently_collapsed(),
			]);
		}

		$template_region = \StoryBB\Template::load_partial('block_region');
		$phpStr = \StoryBB\Template::compile($template_region, [], 'block_region' . \StoryBB\Template::get_theme_id('partials', 'block_region'));

		return new \LightnCandy\SafeString(\StoryBB\Template::prepare($phpStr, [
			'region' => $region,
			'instances' => $block_context['instances'],
		]));
	}

	public static function get_blocktype(Block $instance): string
	{
		$classname = get_class($instance);
		return strtolower(substr(strrchr($classname, '\\'), 1));
	}

	/**
	 * Sets whether blocks should be shown/hidden on this page (on by default)
	 *
	 * @param bool $visible True to show blocks on the current page, false to hide all blocks.
	 * @throws RuntimeException if blocks have already been rendered prior to this change being called
	 */
	public static function set_overall_block_visibility(bool $visible): void
	{
		if (static::$rendered)
		{
			throw new RuntimeException('Cannot alter overall block visibility as blocks have already been rendered.');
		}

		static::$show_blocks = $visible;
	}
}
