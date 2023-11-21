<?php

/**
 * List of scheduled tasks.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\GenericList\Admin;

use StoryBB\App;
use StoryBB\Dependency\AdminUrlGenerator;
use StoryBB\Dependency\Database;
use StoryBB\Helper\GenericList\Column\RawColumn;
use StoryBB\Helper\GenericList\Column\ToggleColumn;
use StoryBB\Helper\GenericList\Unpaginated;
use StoryBB\Phrase;

class ScheduledTasksList extends Unpaginated
{
	use Database;
	use AdminUrlGenerator;

	public function get_title(): Phrase
	{
		return new Phrase('ManageScheduledTasks:maintain_tasks');
	}

	public function get_items()
	{
		$db = $this->db();

		$request = $db->query('', '
			SELECT id_task, next_time, time_offset, time_regularity, time_unit, disabled, class
			FROM {db_prefix}scheduled_tasks',
			[
			]
		);

		$notapplicable = new Phrase('ManageScheduledTasks:scheduled_tasks_na');

		while ($row = $db->fetch_assoc($request))
		{
			// Find the next for regularity - don't offset as it's always server time!
			$offset = new Phrase('ManageScheduledTasks:scheduled_task_reg_starting', [date('H:i', $row['time_offset'])]);
			$repeating = new Phrase('ManageScheduledTasks:scheduled_task_reg_repeating', [$row['time_regularity'], new Phrase('ManageScheduledTasks:scheduled_task_reg_unit_' . $row['time_unit'])]);

			$task = class_exists($row['class']) ? App::make($row['class']) : false;

			$task_name = $task ? $task->get_name() . '<br><small>' . $task->get_description() . '</small>' : $row['class'];

			yield [
				'id' => $row['id_task'],
				'name' => $task_name,
				'next_time' => $row['disabled'] ? $notapplicable : ($row['next_time'] == 0 ? time() : $row['next_time']),
				'enabled' => empty($row['disabled']),
				'regularity' => $offset . ', ' . $repeating,
			];
		}
		$db->free_result($request);
	}

	public function configure_columns(): void
	{
		$url = $this->adminurlgenerator();

		$this->columns = [
			'name' => new RawColumn(new Phrase('ManageScheduledTasks:scheduled_tasks_name')),
			'regularity' => new RawColumn(new Phrase('ManageScheduledTasks:scheduled_tasks_regularity')),
			'enabled' => (new ToggleColumn(new Phrase('General:enabled')))
							->set_destination($url->generate('system/tasks/scheduled/toggle_enabled'))
							->use_column_as('id', 'task_id'),
		];
	}
}
