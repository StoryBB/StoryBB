<?php
/**
 * Base class for every scheduled task which also functions as a sort of interface as well.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task;

/**
 * Base class for every adhoc task which also functions as a sort of interface as well.
 */
abstract class Schedulable
{
	/**
	 * Get the human-readable name for this task.
	 * @return string The human readable name.
	 */
	abstract public function get_name(): string;

	/**
	 * Get the human-readable description for this task.
	 * @return string The task description.
	 */
	abstract public function get_description(): string;

	/**
	 * The function to actually execute a task
	 * @return bool True on success
	 */
	abstract public function execute(): bool;
}
