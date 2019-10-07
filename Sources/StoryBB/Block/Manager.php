<?php

/**
 * This class manages blocks being loaded etc.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Block;

/**
 * Manages blocks.
 */
class Manager
{
	static protected $block_instances = null;

	public static function load_blocks($current_context = true)
	{
		global $smcFunc, $user_info, $context;

		if (static::$block_instances === null)
		{
			static::$block_instances = [];

			$result = $smcFunc['db']->query('', '
				SELECT id_instance, class, visibility, configuration, region, position, active
				FROM {db_prefix}block_instances
			');

			while ($row = $smcFunc['db_fetch_assoc']($result))
			{
				$row['object'] = null;
				if (class_exists($row['class']))
				{
					$config = !empty($row['configuration']) ? json_decode($row['configuration'], true) : [];
					$row['object'] = new $row['class']($config);
				}
				static::$block_instances[$row['region']][$row['id_instance']] = $row;
			}
			$smcFunc['db']->free_result($result);

			foreach (static::$block_instances as $region => $instances)
			{
				uasort($instances, function($a, $b) { return $a['position'] <=> $b['position']; });
				static::$block_instances[$region] = $instances;
			}
		}

		if ($current_context)
		{
			$block_instances = [];
			foreach (static::$block_instances as $region => $instances)
			{
				foreach ($instances as $instance_id => $instance)
				{
					if (empty($instance['active']) || empty($instance['object']))
					{
						continue;
					}

					// Apply visibility.
					if (!empty($instance['visibility']))
					{
						// Unbundle the JSON. If we can't unbundle it, assume it's not visible.
						$visibility = @json_decode($instance['visibility'], true);
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
					if (!empty($visibility['action']) && !in_array($context['current_action'], $visibility['action']))
					{
						continue;
					}

					$block_instances[$region][$instance_id] = $instance['object'];
				}
			}

			return $block_instances;
		}

		return static::$block_instances;
	}
}
