<?php

/**
 * Tables relating to permissions in the StoryBB schema.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Schema\TableGroup;

use StoryBB\Schema\Table;
use StoryBB\Schema\Column;
use StoryBB\Schema\Index;
use StoryBB\Schema\Constraint;

class Permissions
{
	public static function group_description(): string
	{
		return 'Permissions';
	}

	public static function return_tables(): array
	{
		return [
			Table::make('board_permissions',
				[
					'id_group' => Column::smallint()->signed(),
					'id_profile' => Column::smallint(),
					'permission' => Column::varchar(30),
					'add_deny' => Column::tinyint()->default(1),
				],
				[
					Index::primary(['id_group', 'id_profile', 'permission']),
				],
				[
					Constraint::from('board_permissions.id_group')->to('membergroups.id_group'),
					Constraint::from('board_permissions.id_profile')->to('permission_profiles.id_profile'),
				]
			),
			Table::make('permission_profiles',
				[
					'id_profile' => Column::smallint()->auto_increment(),
					'profile_name' => Column::varchar(255),
				],
				[
					Index::primary(['id_profile']),
				]
			),
			Table::make('permissions',
				[
					'id_group' => Column::smallint()->signed(),
					'permission' => Column::varchar(30),
					'add_deny' => Column::tinyint()->default(1),
				],
				[
					Index::primary(['id_group', 'permission']),
				],
				[
					Constraint::from('permissions.id_group')->to('membergroups.id_group'),
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
			'background' => 'LightBlue',
			'border' => 'MediumBlue',
		];
	}
}
