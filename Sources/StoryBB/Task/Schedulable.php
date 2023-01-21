<?php
/**
 * Interface for every scheduled task.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task;

use StoryBB\Discoverable;

/**
 * Interface for every scheduled task.
 */
interface Schedulable extends Discoverable
{
	/**
	 * Get the human-readable name for this task.
	 * @return string The human readable name.
	 */
	public function get_name(): string;

	/**
	 * Get the human-readable description for this task.
	 * @return string The task description.
	 */
	public function get_description(): string;

	/**
	 * Sets the enabled state of this task.
	 *
	 * @param bool $new_enabled_state Set to true to enable the task, false to disable.
	 */
	public function set_state(bool $new_enabled_state): void;

	/**
	 * The function to actually execute a task
	 * @return bool True on success
	 */
	public function execute(): bool;

	/**
	 * Any additional processing triggered by enabling this task.
	 */
	public function on_enable(): void;

	/**
	 * Any additional processing triggered by disabling this task.
	 */
	public function on_disable(): void;
}
