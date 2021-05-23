<?php

/**
 * Tables relating to personal message in the StoryBB schema.
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

class PersonalMessages
{
	/**
	 * Returns the description of this group of tables.
	 *
	 * @return string The group description, untranslated.
	 */
	public static function group_description(): string
	{
		return 'Personal Messages';
	}

	/**
	 * Returns the tables in this group of tables.
	 *
	 * @return array An array of Table objects that make up this group of tables.
	 */
	public static function return_tables(): array
	{
		return [
			Table::make('personal_messages',
				[
					'id_pm' => Column::int()->auto_increment(),
					'id_pm_head' => Column::int(),
					'id_member_from' => Column::mediumint(),
					'deleted_by_sender' => Column::tinyint(),
					'from_name' => Column::varchar(255),
					'msgtime' => Column::int(),
					'subject' => Column::varchar(255),
					'body' => Column::text(),
				],
				[
					Index::primary(['id_pm']),
					Index::key(['id_member_from', 'deleted_by_sender']),
					Index::key(['msgtime']),
					Index::key(['id_pm_head']),
				]
			),
			Table::make('pm_labels',
				[
					'id_label' => Column::int()->auto_increment(),
					'id_member' => Column::mediumint(),
					'name' => Column::varchar(30),
				],
				[
					Index::primary(['id_label']),
				],
				[
					Constraint::from('pm_labels.id_member')->to('members.id_member'),
				]
			),
			Table::make('pm_labeled_messages',
				[
					'id_label' => Column::int(),
					'id_pm' => Column::int(),
				],
				[
					Index::primary(['id_label', 'id_pm']),
				],
				[
					Constraint::from('pm_labeled_messages.id_label')->to('pm_labels.id_label'),
					Constraint::from('pm_labeled_messages.id_pm')->to('personal_messages.id_pm'),
				]
			),
			Table::make('pm_recipients',
				[
					'id_pm' => Column::int(),
					'id_member' => Column::mediumint(),
					'bcc' => Column::tinyint(),
					'is_read' => Column::tinyint(),
					'is_new' => Column::tinyint(),
					'deleted' => Column::tinyint(),
					'in_inbox' => Column::tinyint()->default(1),
				],
				[
					Index::primary(['id_pm', 'id_member']),
					Index::unique(['id_member', 'deleted', 'id_pm']),
				]
			),
			Table::make('pm_rules',
				[
					'id_rule' => Column::int()->auto_increment(),
					'id_member' => Column::mediumint(),
					'rule_name' => Column::varchar(60),
					'criteria' => Column::text(),
					'actions' => Column::text(),
					'delete_pm' => Column::tinyint(),
					'is_or' => Column::tinyint(),
				],
				[
					Index::primary(['id_rule']),
					Index::key(['id_member']),
					Index::key(['delete_pm']),
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
			'background' => 'LightSeaGreen',
			'border' => 'SeaGreen',
		];
	}
}
