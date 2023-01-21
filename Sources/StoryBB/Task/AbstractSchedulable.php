<?php

/**
 * Base class for schedulable tasks which can also functions as a sort of interface as well.
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
 * Base class for schedulable tasks which can also functions as a sort of interface as well.
 */
abstract class AbstractSchedulable
{
	/**
	 * The function to actually execute a task
	 * @return mixed
	 */
	abstract public function execute();

	/**
	 * Sets the enabled state of this task.
	 *
	 * @param bool $new_enabled_state Set to true to enable the task, false to disable.
	 */
	public function set_state(bool $new_enabled_state): void
	{
		$db = App::container()->get('database');

		$db->query('', '
			UPDATE {db_prefix}scheduled_tasks
			SET disabled = {int:new_state}
			WHERE class = {string:class}',
			[
				'new_state' => $new_enabled_state ? 0 : 1,
				'class' => static::class,
			]
		);
		if ($new_enabled_state)
		{
			$this->on_enable();
		}
		else
		{
			$this->on_disable();
		}
	}

	/**
	 * Any additional processing triggered by enabling this task.
	 */
	public function on_enable(): void
	{

	}

	/**
	 * Any additional processing triggered by disabling this task.
	 */
	public function on_disable(): void
	{

	}
}
