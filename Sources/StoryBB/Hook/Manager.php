<?php

/**
 * This hook abstract forms the basis of a read-only hook.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
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

	/**
	 * Add a hook to the list of things to be called in the current execution context.
	 *
	 * @param string $hook The hook class to register for (e.g. StoryBB\Hook\Observable\Post\Created)
	 * @param int $priority Relative priority of this hook; lower number runs earlier
	 * @param mixed $function A callable that can be called at the time the hook executes
	 * @return void
	 */
	public static function register(string $hook, int $priority, $function): void
	{
		static::$hooks[$hook][$priority][] = $function;
		ksort(static::$hooks[$hook]);
	}

	/**
	 * For a given hook, look up all the functions referencing it and call them.
	 *
	 * @param Hookable $hook The data carrier for the hook.
	 * @return void
	 */
	public static function execute(Hookable $hook): void
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

		foreach (static::$hooks[$classname] as $priorityhooks)
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
