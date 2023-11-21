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

/**
 * Tables relating to achievements in the StoryBB schema.
 */
class Skills
{
	/**
	 * Returns the description of this group of tables.
	 *
	 * @return string The group description, untranslated.
	 */
	public static function group_description(): string
	{
		return 'Skills';
	}

	/**
	 * Returns the tables in this group of tables.
	 *
	 * @return array An array of Table objects that make up this group of tables.
	 */
	public static function return_tables(): array
	{
		return [
			Table::make('skillsets', 
				[
					'id_skillset' => Column::smallint()->auto_increment(),
					'skillset_name' => Column::varchar(255),
					'active' => Column::tinyint(),
				],
				[
					Index::primary(['id_skillset']),
				]
			),
			Table::make('skill_branches',
				[
					'id_branch' => Column::mediumint()->auto_increment(),
					'id_skillset' => Column::smallint(),
					'skill_branch_name' => Column::varchar(100),
					'branch_order' => Column::mediumint(),
					'active' => Column::tinyint(),
				],
				[
					Index::primary(['id_branch']),
				],
				[
					Constraint::from('skill_branches.id_skillset')->to('skillsets.id_skillset'),
				]
			),
			Table::make('skills',
				[
					'id_skill' => Column::int()->auto_increment(),
					'id_branch' => Column::smallint(),
					'skill_name' => Column::varchar(100),
					'skill_link' => Column::varchar(255),
					'skill_order' => Column::int(),
				],
				[
					Index::primary(['id_skill']),
				],
				[
					Constraint::from('skills.id_branch')->to('skill_brancesh.id_branch'),
				]
			),
			Table::make('character_skills',
				[
					'id_character_skill' => Column::int()->auto_increment(),
					'id_character' => Column::int(),
					'id_skill' => Column::int(),
				],
				[
					Index::primary(['id_character_skill']),
					Index::unique(['id_character', 'id_skill']),
				],
				[
					Constraint::from('character_skills.id_character')->to('characters.id_character'),
					Constraint::from('character_skills.id_skill')->to('skills.id_skill'),
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
