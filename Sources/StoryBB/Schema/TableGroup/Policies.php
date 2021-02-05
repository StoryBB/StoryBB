<?php

/**
 * Tables relating to policies in the StoryBB schema.
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

class Policies
{
	public static function group_description(): string
	{
		return 'Policies';
	}

	public static function return_tables(): array
	{
		return [
			Table::make('policy',
				[
					'id_policy' => Column::smallint()->auto_increment(),
					'policy_type' => Column::tinyint(),
					'language' => Column::varchar(20),
					'title' => Column::varchar(100),
					'description' => Column::varchar(200),
					'last_revision' => Column::int(),
				],
				[
					Index::primary(['id_policy']),
				],
				[
					Constraint::from('policy.policy_type')->to('policy_types.id_policy_type'),
				]
			),
			Table::make('policy_acceptance',
				[
					'id_policy' => Column::smallint()->auto_increment(),
					'id_member' => Column::mediumint(),
					'id_revision' => Column::int(),
					'acceptance_time' => Column::int(),
				],
				[
					Index::primary(['id_policy', 'id_member', 'id_revision']),
				],
				[
					Constraint::from('policy_acceptance.id_policy')->to('policy.id_policy'),
					Constraint::from('policy_acceptance.id_member')->to('members.id_member'),
				]
			),
			Table::make('policy_revision',
				[
					'id_revision' => Column::int()->auto_increment(),
					'id_policy' => Column::smallint(),
					'last_change' => Column::int(),
					'short_revision_note' => Column::text(),
					'revision_text' => Column::text(),
					'edit_id_member' => Column::mediumint(),
					'edit_member_name' => Column::varchar(50),
				],
				[
					Index::primary(['id_revision']),
					Index::key(['id_policy']),
				],
				[
					Constraint::from('policy_revision.id_policy')->to('policy.id_policy'),
				]
			),
			Table::make('policy_types',
				[
					'id_policy_type' => Column::tinyint()->auto_increment(),
					'policy_type' => Column::varchar(50),
					'require_acceptance' => Column::tinyint(),
					'show_footer' => Column::tinyint(),
					'show_reg' => Column::tinyint(),
					'show_help' => Column::tinyint(),
				],
				[
					Index::primary(['id_policy_type']),
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
			'background' => 'Wheat',
			'border' => 'Tan',
		];
	}
}
