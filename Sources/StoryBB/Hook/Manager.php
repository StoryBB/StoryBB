<?php

/**
 * This hook abstract forms the basis of a read-only hook.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook;

use InvalidArgumentException;
use StoryBB\Hook\Hook;

/**
 * This hook abstract forms the basis of a read-only hook.
 */
class Manager
{
	protected static $hooks = [];

	public static function register(string $hook, int $priority, $function)
	{
		static::$hooks[$hook][$priority][] = $function;
		ksort(static::$hooks[$hook]);
	}

	public static function execute(Hookable $hook)
	{
		global $context, $db_show_debug;

		$classname = get_class($hook);

		if ($db_show_debug === true)
		{
			$context['debug']['hooks'][] = str_replace('StoryBB\\Hook\\', '', $classname);
		}

		if (!isset(static::$hooks[$classname]))
		{
			return;
		}

		foreach (static::$hooks[$classname] as $priority => $priorityhooks)
		{
			foreach ($priorityhooks as $hookpoint)
			{
				if (is_callable($hookpoint))
				{
					call_user_func($hookpoint, $hook);
				}
			}
		}
	}
}
