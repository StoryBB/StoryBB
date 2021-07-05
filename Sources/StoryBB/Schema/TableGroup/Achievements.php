<?php

/**
 * Tables relating to achievements in the StoryBB schema.
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

class Achievements
{
	/**
	 * Returns the description of this group of tables.
	 *
	 * @return string The group description, untranslated.
	 */
	public static function group_description(): string
	{
		return 'Achievements';
	}

	/**
	 * Returns the tables in this group of tables.
	 *
	 * @return array An array of Table objects that make up this group of tables.
	 */
	public static function return_tables(): array
	{
		return [
			Table::make('achieve', 
				[
					'id_achieve' => Column::smallint()->auto_increment(),
					'achievement_name' => Column::varchar(255),
					'achievement_desc' => Column::text(),
					'achievement_type' => Column::tinyint(),
					'manually_awardable' => Column::tinyint(),
					'active' => Column::tinyint(),
					'hidden' => Column::tinyint(),
				],
				[
					Index::primary(['id_achieve']),
				]
			),
			Table::make('achieve_outcome',
				[
					'id_outcome' => Column::mediumint()->auto_increment(),
					'id_achieve' => Column::smallint(),
					'outcome_type' => Column::varchar(100),
					'outcome_data' => Column::text(),
				],
				[
					Index::primary(['id_outcome']),
				],
				[
					Constraint::from('achieve_outcome.id_achieve')->to('achieve.id_achieve'),
				]
			),
			Table::make('achieve_rule',
				[
					'id_achieve_rule' => Column::int()->auto_increment(),
					'id_achieve' => Column::smallint(),
					'ruleset' => Column::tinyint(),
					'rule' => Column::tinyint(),
					'criteria_type' => Column::varchar(100),
					'criteria' => Column::text(),
				],
				[
					Index::primary(['id_achieve_rule']),
					Index::unique(['id_achieve', 'ruleset', 'rule']),
				],
				[
					Constraint::from('achieve_rule.id_achieve')->to('achieve.id_achieve'),
				]
			),
			Table::make('achieve_rule_unlock',
				[
					'id_achieve_rule_unlock' => Column::int()->auto_increment(),
					'id_achieve' => Column::smallint(),
					'ruleset' => Column::tinyint(),
					'rule' => Column::tinyint(),
					'criteria_type' => Column::varchar(100),
					'criteria' => Column::text(),
				],
				[
					Index::primary(['id_achieve_rule_unlock']),
					Index::unique(['id_achieve', 'ruleset', 'rule']),
				],
				[
					Constraint::from('achieve_rule_unlock.id_achieve')->to('achieve.id_achieve'),
				]
			),
			Table::make('achieve_user',
				[
					'id_achieve_award' => Column::int()->auto_increment(),
					'id_achieve' => Column::smallint(),
					'id_member' => Column::mediumint(),
					'id_character' => Column::int(),
					'awarded_time' => Column::int(),
					'awarded_by' => Column::mediumint(),
				],
				[
					Index::primary(['id_achieve_award']),
					Index::key(['id_member', 'id_character']),
				],
				[
					Constraint::from('achieve_user.id_achieve')->to('achieve.id_achieve'),
					Constraint::from('achieve_user.id_member')->to('members.id_member'),
					Constraint::from('achieve_user.id_character')->to('characters.id_character')
				]
			),
			Table::make('achieve_user_unlock',
				[
					'id_achieve_unlock' => Column::int()->auto_increment(),
					'id_achieve' => Column::smallint(),
					'id_member' => Column::mediumint(),
					'id_character' => Column::int(),
					'unlock_time' => Column::int(),
				],
				[
					Index::primary(['id_achieve_unlock']),
					Index::key(['id_member', 'id_character']),
				],
				[
					Constraint::from('achieve_user_unlock.id_achieve')->to('achieve.id_achieve'),
					Constraint::from('achieve_user_unlock.id_member')->to('members.id_member'),
					Constraint::from('achieve_user_unlock.id_character')->to('characters.id_character')
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
			'background' => 'Gold',
			'border' => 'GoldenRod',
		];
	}
}
