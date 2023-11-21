<?php

/**
 * A class for managing tasks being queued for scheduled running.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task;

use StoryBB\App;

/**
 * A class for managing tasks being queued for scheduled running.
 */
class Scheduler
{
	public static function log_completed(int $task_id, float $time_taken)
	{
		global $smcFunc;

		$smcFunc['db']->insert('',
			'{db_prefix}log_scheduled_tasks',
			['id_task' => 'int', 'time_run' => 'int', 'time_taken' => 'float'],
			[$task_id, time(), $time_taken],
			['id_task']
		);
	}

	public static function set_enabled_state(string $class, bool $enabled_state)
	{
		if (class_exists($class))
		{
			App::make($class)->set_state($enabled_state);
		}
	}
}
