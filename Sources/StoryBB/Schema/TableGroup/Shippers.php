<?php

/**
 * Tables relating to specific functionality for shippers.
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
 * Tables relating to specific functionality for shippers.
 */
class Shippers
{
	/**
	 * Returns the description of this group of tables.
	 *
	 * @return string The group description, untranslated.
	 */
	public static function group_description(): string
	{
		return 'Shippers';
	}

	/**
	 * Returns the tables in this group of tables.
	 *
	 * @return array An array of Table objects that make up this group of tables.
	 */
	public static function return_tables(): array
	{
		return [
			Table::make('shipper',
				[
					'id_ship' => Column::smallint()->auto_increment(),
					'first_character' => Column::int(),
					'second_character' => Column::int(),
					'ship_name' => Column::varchar(50),
					'ship_slug' => Column::varchar(50),
					'hidden' => Column::tinyint(),
					'shipper' => Column::mediumtext(),
				],
				[
					Index::primary(['id_ship']),
					Index::unique(['first_character', 'second_character']),
				],
				[
					Constraint::from('ships.first_character')->to('characters.id_character'),
					Constraint::from('ships.second_character')->to('characters.id_character'),
				]
			),
			Table::make('shipper_timeline',
				[
					'id_ship' => Column::smallint(),
					'id_topic' => Column::mediumint(),
					'position' => Column::smallint(),
				],
				[
					Index::primary(['id_ship', 'id_topic']),
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
			'background' => 'LightSkyBlue',
			'border' => 'RoyalBlue',
		];
	}
}
