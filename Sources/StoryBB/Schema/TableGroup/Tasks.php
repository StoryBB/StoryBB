<?php

/**
 * Tables relating to tasks in the StoryBB schema.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Schema\TableGroup;

use StoryBB\Schema\Table;
use StoryBB\Schema\Column;
use StoryBB\Schema\Index;
use StoryBB\Schema\Constraint;

class Tasks
{
	public static function group_description(): string
	{
		return 'Tasks';
	}

	public static function return_tables(): array
	{
		return [
			Table::make('adhoc_tasks',
				[
					'id_task' => Column::int()->auto_increment(),
					'task_file' => Column::varchar(255),
					'task_class' => Column::varchar(255),
					'task_data' => Column::mediumtext(),
					'claimed_time' => Column::int(),
				],
				[
					Index::primary(['id_task']),
				]
			),
			Table::make('log_scheduled_tasks',
				[
					'id_log' => Column::mediumint()->auto_increment(),
					'id_task' => Column::smallint(),
					'time_run' => Column::int(),
					'time_taken' => Column::float(),
				],
				[
					Index::primary(['id_log']),
				],
				[
					Constraint::from('log_scheduled_tasks.id_task')->to('scheduled_tasks.id_task'),
				]
			),
			Table::make('scheduled_tasks',
				[
					'id_task' => Column::smallint()->auto_increment(),
					'next_time' => Column::int(),
					'time_offset' => Column::int(),
					'time_regularity' => Column::smallint(),
					'time_unit' => Column::varchar(1)->default('h'),
					'disabled' => Column::tinyint(),
					'class' => Column::varchar(255),
				],
				[
					Index::primary(['id_task']),
					Index::key(['next_time']),
					Index::key(['disabled']),
				]
			),
		];
	}

	/**
	 * Return the colour scheme that the UML builder should use.
	 *
	 * @return array Array of named or hex colours for PlantUML.
	 */
	public static function plantuml_colour_scheme(): array
	{
		return [
			'background' => 'GhostWhite',
			'border' => 'Gainsboro',
		];
	}
}
