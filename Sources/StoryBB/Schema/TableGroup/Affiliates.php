<?php

/**
 * Tables relating to affiliates in the StoryBB schema.
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

class Affiliates
{
	/**
	 * Returns the description of this group of tables.
	 *
	 * @return string The group description, untranslated.
	 */
	public static function group_description(): string
	{
		return 'Affiliates';
	}

	/**
	 * Returns the tables in this group of tables.
	 *
	 * @return array An array of Table objects that make up this group of tables.
	 */
	public static function return_tables(): array
	{
		return [
			Table::make('affiliate',
				[
					'id_affiliate' => Column::smallint()->auto_increment(),
					'id_tier' => Column::tinyint(),
					'affiliate_name' => Column::varchar(255),
					'url' => Column::varchar(500),
					'image_url' => Column::varchar(500),
					'sort_order' => Column::smallint(),
					'enabled' => Column::tinyint(),
					'timecreated' => Column::int(),
					'added_by' => Column::mediumint(),
				],
				[
					Index::primary(['id_affiliate']),
				],
				[
					Constraint::from('affiliate.id_tier')->to('affiliate_tier.id_tier'),
				]
			),
			Table::make('affiliate_tier',
				[
					'id_tier' => Column::tinyint()->auto_increment(),
					'tier_name' => Column::varchar(255),
					'image_width' => Column::smallint(),
					'image_height' => Column::smallint(),
					'sort_order' => Column::smallint(),
					'desaturate' => Column::tinyint(),
				],
				[
					Index::primary(['id_tier']),
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
			'background' => 'PaleTurquoise',
			'border' => 'DarkTurquoise',
		];
	}
}
