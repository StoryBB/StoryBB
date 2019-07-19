<?php

/**
 * A class for managing tasks being queued for running asynchronously.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB;

/**
 * A class for managing tasks being queued for running asynchronously.
 */
class Task
{
	/**
	 * @var array Stores tasks to be queued when a group of tasks is going to be queued together
	 */
	protected static $pre_batch_queue = [];

	/**
	 * Add an adhoc task to the background task list.
	 *
	 * @param string $class Name of the class (should be autoloadable)
	 * @param array $data The data that the class needs
	 * @return bool True if the class could be added
	 */
	public static function queue_adhoc(string $class, array $data = []): bool
	{
		global $smcFunc;

		if (!class_exists($class)) {
			return false;
		}

		$smcFunc['db_insert']('insert', '{db_prefix}background_tasks',
			['task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'],
			['', $class, !empty($data) ? json_encode($data) : '', 0],
			['id_task']
		);

		return true;
	}

	/**
	 * Batch-queue a background task.
	 *
	 * @param string $class Name of the class (should be autoloadable)
	 * @param array $data The data that the class needs
	 * @return bool True if the class could be added
	 */
	public static function batch_queue_adhoc(string $class, array $data = []): bool
	{
		if (!class_exists($class)) {
			return false;
		}

		self::$pre_batch_queue[] = [$class, $data];
		return true;
	}

	/**
	 * Commit the batch queue to the database.
	 */
	public static function commit_batch_queue()
	{
		global $smcFunc;

		if (!empty(self::$pre_batch_queue))
		{
			$rows = [];
			foreach (self::$pre_batch_queue as $task)
			{
				$rows[] = ['', $task[0], !empty($task[1]) ? json_encode($task[1]) : '', 0];
			}

			$smcFunc['db_insert']('insert', '{db_prefix}background_tasks',
				['task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'],
				$rows,
				['id_task']
			);
		}

		self::$pre_batch_queue = [];
	}
}
