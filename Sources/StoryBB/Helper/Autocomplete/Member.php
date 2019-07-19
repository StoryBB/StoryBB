<?php

/**
 * Provide an autocomplete handler to match member accounts (not characters)
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Autocomplete;

/**
 * Provide an autocomplete handler to match member accounts (not characters)
 */
class Member extends AbstractCompletable implements Completable
{
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
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(id_member)
			FROM {db_prefix}members
			WHERE {raw:real_name} LIKE {string:search}
				AND is_activated IN (1, 11)',
			[
				'real_name' => $smcFunc['db_case_sensitive'] ? 'LOWER(real_name)' : 'real_name',
				'search' => $this->escape_term($this->term),
			]
		);
		list ($count) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return (int) $count;
	}

	/**
	 * Returns the actual results based on paginated through the filters search results.
	 * Each result will contain id (member id), text (member name) and avatar (URL for avatar)
	 *
	 * @param int $start Where to start through the results list
	 * @param int $limit How many to retrieve
	 * @return array Array of results matching the search term
	 */
	public function get_results(int $start = null, int $limit = null): array
	{
		global $smcFunc, $modSettings, $settings;

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

		$request = $smcFunc['db_query']('', '
			SELECT mem.id_member, real_name, email_address, a.filename, mainchar.avatar
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}characters AS mainchar ON (mainchar.id_member = mem.id_member AND mainchar.is_main = 1)
				LEFT JOIN {db_prefix}attachments AS a ON (a.id_character = mainchar.id_character AND a.attachment_type = 1)
			WHERE {raw:real_name} LIKE {string:search}
				AND is_activated IN (1, 11)
			LIMIT {int:start}, {int:limit}',
			[
				'real_name' => $smcFunc['db_case_sensitive'] ? 'LOWER(real_name)' : 'real_name',
				'search' => $this->escape_term($this->term),
				'start' => $start,
				'limit' => $limit,
			]
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$result[] = [
				'id' => $row['id_member'],
				'text' => $row['real_name'],
				'avatar' => set_avatar_data($row)['url'],
			];
		}
		$smcFunc['db_free_result']($request);

		return $result;
	}

	/**
	 * Sets existing values for populating a member autocomplete when editing a form.
	 *
	 * @param array $default_value An array of member ids to look up and populate into the autocomplete.
	 */
	public function set_values(array $default_value)
	{
		global $smcFunc;

		$default_value = array_map('intval', $default_value);
		$default_value = array_filter($default_value, function($x) {
			return !empty($x);
		});
		if (empty($default_value))
			return;

		$this->default = [];
		$request = $smcFunc['db_query']('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:default_value})',
			[
				'default_value' => $default_value,
			]
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$this->default[$row['id_member']] = $row;
		}
		$smcFunc['db_free_result']($request);
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
		global $scripturl, $txt;

		$js = '
$("' . $target . '").select2({
	dropdownAutoWidth: true,
	width: "auto",
	placeholder: ' . json_encode($txt['autocomplete_search_member']) . ',
	allowClear: ' . ($maximum == 1 ? 'true' : 'false') . ',' . ($maximum > 1 ? '
	maximumSelectionLength: ' . $maximum . ',' : '') . '
	ajax: {
		url: "' . $scripturl . '",
		data: function (params) {
			var query = {
				action: "autocomplete",
				term: params.term,
				type: "member"
			}
			query[sbb_session_var] = sbb_session_id;
			return query;
		}
	},
	delay: 150,
	templateResult: function(member) {
		if (!member.avatar)
			return member.text;

		var $mem = $("<div class=\"autocomplete\"><div style=\"background-image:url(" + member.avatar + ")\" class=\"autocomplete-avatar\"></div><span class=\"autocomplete-member\">" + member.text + "</span></div>");
		return $mem;
	}
});';

		if (!empty($this->default))
		{
			foreach ($this->default as $default)
			{
			$js .= '
$("' . $target . '").append(new Option(' . json_encode($default['real_name']) . ', ' . $default['id_member'] . ', false, false));';
			}
			$js .= '
$("' . $target . '").val(' . json_encode(array_keys($this->default)) . ').trigger("change");';
		}

		return $js;
	}
}
