<?php

/**
 * Tables relating to polls in the StoryBB schema.
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
 * Tables relating to polls in the StoryBB schema.
 */
class Polls
{
	/**
	 * Returns the description of this group of tables.
	 *
	 * @return string The group description, untranslated.
	 */
	public static function group_description(): string
	{
		return 'Polls';
	}

	/**
	 * Returns the tables in this group of tables.
	 *
	 * @return array An array of Table objects that make up this group of tables.
	 */
	public static function return_tables(): array
	{
		return [
			Table::make('log_polls',
				[
					'id_poll' => Column::mediumint(),
					'id_member' => Column::mediumint(),
					'id_choice' => Column::tinyint(),
				],
				[
					Index::key(['id_poll', 'id_member', 'id_choice']),
				]
			),
			Table::make('polls',
				[
					'id_poll' => Column::mediumint()->auto_increment(),
					'question' => Column::varchar(255),
					'voting_locked' => Column::tinyint(),
					'max_votes' => Column::tinyint()->default(1),
					'expire_time' => Column::int(),
					'hide_results' => Column::tinyint(),
					'change_vote' => Column::tinyint(),
					'guest_vote' => Column::tinyint(),
					'num_guest_voters' => Column::int(),
					'reset_poll' => Column::int(),
					'id_member' => Column::mediumint(),
					'poster_name' => Column::varchar(255),
				],
				[
					Index::primary(['id_poll']),
				]
			),
			Table::make('poll_choices',
				[
					'id_poll' => Column::mediumint(),
					'id_choice' => Column::tinyint(),
					'label' => Column::varchar(255),
					'votes' => Column::smallint(),
				],
				[
					Index::primary(['id_poll', 'id_choice']),
				],
				[
					Constraint::from('poll_choices.id_poll')->to('polls.id_poll'),
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
