<?php

/**
 * Provide an autocomplete handler to match membergroups. This version by default matches all groups.
 * Subclasses may exist to be more specific for convenience purposes.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Autocomplete;

use StoryBB\Dependency\Database;

/**
 * Provide an autocomplete handler to match membergroups. This version by default matches all groups.
 * Subclasses may exist to be more specific for convenience purposes.
 */
class Group extends AbstractCompletable implements Completable
{
	use Database;

	/** @var bool $account_groups Sets out whether account groups should be included as possible group matches */
	protected $account_groups = true;

	/** @var bool $character_groups Sets out whether character groups should be included as possible group matches */
	protected $character_groups = true;

	/** @var bool $hidden_groups Sets out whether hidden groups should be included as possible group matches */
	protected $hidden_groups = false;

	/**
	 * Based on the filters set by the different properties (post count groups etc.) build the relevant SQL needed
	 * to successfully filter out groups to return to the user.
	 *
	 * @return string A clause for SQL that filters out different types of groups as the class expects to do
	 */
	protected function get_filters(): string
	{
		$filters = [];
		if (!$this->account_groups)
		{
			$filters[] = 'is_character != 0';
		}
		if (!$this->character_groups)
		{
			$filters[] = 'is_character = 0';
		}
		if (!$this->hidden_groups)
		{
			$filters[] = 'hidden != 2';
		}
		return !empty($filters) ? ' AND ' . implode(' AND ', $filters) : '';
	}

	/**
	 * Whether the results will be paginated on return.
	 *
	 * @return bool True if can be paginated.
	 */
	public function can_paginate(): bool
	{
		return true;
	}

	/**
	 * Returns the number of results that match the search term.
	 *
	 * @return int Number of matching results
	 */
	public function get_count(): int
	{
		$db = $this->db();

		$request = $db->query('', '
			SELECT COUNT(id_group)
			FROM {db_prefix}membergroups AS mg
			WHERE {raw:group_name} LIKE {string:search}' . $this->get_filters(),
			[
				'group_name' => $db->is_case_sensitive() ? 'LOWER(group_name)' : 'group_name',
				'search' => '%' . $this->escape_term($this->term) . '%',
			]
		);
		list ($count) = $db->fetch_row($request);
		$db->free_result($request);

		return (int) $count;
	}

	/**
	 * Returns the actual results based on paginated through the filters search results.
	 * Each result will contain id (group id), text (group name) and icons (group badge)
	 *
	 * @param int $start Where to start through the results list
	 * @param int $limit How many to retrieve
	 * @return array Array of results matching the search term
	 */
	public function get_results(int $start = null, int $limit = null): array
	{
		global $settings;

		$db = $this->db();

		if (empty($this->term))
			return [];

		if (empty($start))
		{
			$start = 0;
		}

		if (empty($limit))
		{
			$limit = 10;
		}

		$result = [];

		$request = $db->query('', '
			SELECT mg.id_group, mg.group_name, mg.icons
			FROM {db_prefix}membergroups AS mg
			WHERE {raw:group_name} LIKE {string:search}' . $this->get_filters() . '
			LIMIT {int:start}, {int:limit}',
			[
				'group_name' => $db->is_case_sensitive() ? 'LOWER(group_name)' : 'group_name',
				'search' => '%' . $this->escape_term($this->term) . '%',
				'start' => $start,
				'limit' => $limit,
			]
		);
		while ($row = $db->fetch_assoc($request))
		{
			$row['icons'] = explode('#', $row['icons']);
			$row['icons'] = !empty($row['icons'][0]) && !empty($row['icons'][1]) ? str_repeat('<img src="' . $settings['images_url'] . '/membericons/' .  $row['icons'][1] . '" alt="*">', $row['icons'][0]) : '';

			$result[] = [
				'id' => $row['id_group'],
				'text' => $row['group_name'],
				'icons' => $row['icons'],
			];
		}
		$db->free_result($request);

		return $result;
	}

	/**
	 * Sets existing values for populating a group autocomplete when editing a form.
	 *
	 * @param array $default_value An array of group ids to look up and populate into the autocomplete.
	 */
	public function set_values(array $default_value)
	{
		$db = $this->db();

		$default_value = array_map('intval', $default_value);
		$default_value = array_filter($default_value, function($x) {
			return !empty($x);
		});
		if (empty($default_value))
			return;

		$this->default = [];
		$request = $db->query('', '
			SELECT id_group, group_name
			FROM {db_prefix}membergroups
			WHERE id_group IN ({array_int:default_value})',
			[
				'default_value' => $default_value,
			]
		);
		while ($row = $db->fetch_assoc($request))
		{
			$this->default[$row['id_group']] = $row;
		}
		$db->free_result($request);
	}

	/**
	 * Provides the JavaScript to be embedded into the page to successfully initialise this widget.
	 *
	 * @param string $target The jQuery/JavaScript selector this should be applied to, e.g. #myselect
	 * @param int $maximum The expected maximum of allowed entries; 0 for no limit.
	 * @return string The JavaScript to initialise this widget.
	 */
	public function get_js(string $target, int $maximum = 1): string
	{
		global $txt;

		$js = '
$("' . $target . '").select2({
	dropdownAutoWidth: true,
	width: "auto",
	placeholder: ' . json_encode($txt['autocomplete_search_group']) . ',
	allowClear: ' . ($maximum == 1 ? 'true' : 'false') . ',' . ($maximum > 1 ? '
	maximumSelectionLength: ' . $maximum . ',' : '') . '
	ajax: {
		url: "' . $this->get_url() . '",
		data: function (params) {
			return {
				term: params.term
			};
		}
	},
	delay: 150,
	templateResult: function(group) {
		if (!group.icons)
			return group.text;

		var $group = $("<div class=\"autocomplete\"><div class=\"autocomplete-container-group\"><div class=\"autocomplete-group\">" + group.text + "</div><div class=\"autocomplete-group-icons\">" + group.icons + "</div></div></div>");
		return $group;
	}
});';

		if (!empty($this->default))
		{
			foreach ($this->default as $default)
			{
				$js .= '
$("' . $target . '").append(new Option(' . json_encode($default['group_name']) . ', ' . $default['id_group'] . ', false, false));';
			}
			$js .= '
$("' . $target . '").val(' . json_encode(array_keys($this->default)) . ').trigger("change");';
		}

		return $js;
	}
}
