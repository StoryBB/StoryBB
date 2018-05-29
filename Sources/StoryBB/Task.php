<?php

/**
 * Task management.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB;

class Task
{
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
}

?>