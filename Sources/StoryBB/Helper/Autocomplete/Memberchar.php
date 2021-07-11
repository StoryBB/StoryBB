<?php

/**
 * Provide an autocomplete handler to match members and their characters and funnel back to the account, e.g. PMs.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Autocomplete;

/**
 * Provide an autocomplete handler to match member characters (not OOC characters)
 */
class Memberchar extends AbstractCompletable implements Completable
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
	 * Find a list of matches solely off member name.
	 * 
	 * @return array An array of member ID matches for the search term.
	 */
	protected function match_members(): array
	{
		global $smcFunc;

		$members = [];
		$request = $smcFunc['db']->query('', '
			SELECT chars.id_member
			FROM {db_prefix}members AS mem
				INNER JOIN {db_prefix}characters AS chars ON (chars.id_member = mem.id_member)
			WHERE {raw:character_name} LIKE {string:search}
				AND chars.is_main = 1
				AND mem.is_activated IN (1, 11)',
			[
				'character_name' => $smcFunc['db']->is_case_sensitive() ? 'LOWER(character_name)' : 'character_name',
				'search' => $this->escape_term($this->term),
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$members[$row['id_member']] = $row['id_member'];
		}
		$smcFunc['db']->free_result($request);

		return $members;
	}

	/**
	 * Returns the number of results that match the search term.
	 *
	 * @return int Number of matching results
	 */
	public function get_count(): int
	{
		global $smcFunc;

		// First find all the members who match.
		$members = $this->match_members();

		// Now find all the characters who are not the matched members.
		$request = $smcFunc['db']->query('', '
			SELECT chars.id_member
			FROM {db_prefix}members AS mem
				INNER JOIN {db_prefix}characters AS chars ON (chars.id_member = mem.id_member)
			WHERE {raw:character_name} LIKE {string:search}
				AND chars.is_main = 0
				AND chars.id_member NOT IN ({array_int:members})
				AND mem.is_activated IN (1, 11)',
			[
				'character_name' => $smcFunc['db']->is_case_sensitive() ? 'LOWER(character_name)' : 'character_name',
				'search' => $this->escape_term($this->term),
				'members' => array_merge([0], array_values($members)),
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$members[$row['id_member']] = $row['id_member'];
		}
		$smcFunc['db']->free_result($request);

		return count($members);
	}

	/**
	 * Returns the actual results based on paginated through the filters search results.
	 * Each result will contain id (character id), text and char_name (character name),
	 * account_name (the underlying account name) and avatar (URL for character avatar)
	 *
	 * @param int $start Where to start through the results list
	 * @param int $limit How many to retrieve
	 * @return array Array of results matching the search term
	 */
	public function get_results(int $start = null, int $limit = null): array
	{
		global $smcFunc;

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

		// First match the members.
		$members = $this->match_members();

		$request = $smcFunc['db']->query('', '
			SELECT mem.id_member, mem.real_name, mem.real_name AS character_name, a.filename, chars.avatar
			FROM {db_prefix}members AS mem
				INNER JOIN {db_prefix}characters AS chars ON (chars.id_member = mem.id_member AND chars.is_main = 1)
				LEFT JOIN {db_prefix}attachments AS a ON (a.id_character = chars.id_character AND a.attachment_type = 1)
			WHERE mem.id_member IN ({array_int:members})
		UNION
			SELECT mem.id_member, mem.real_name, chars.character_name, a.filename, chars.avatar
			FROM {db_prefix}members AS mem
				INNER JOIN {db_prefix}characters AS chars ON (chars.id_member = mem.id_member AND chars.is_main = 0)
				LEFT JOIN {db_prefix}attachments AS a ON (a.id_character = chars.id_character AND a.attachment_type = 1)
			WHERE {raw:character_name} LIKE {string:search}
				AND mem.id_member NOT IN ({array_int:members})
				AND mem.is_activated IN (1, 11)
			LIMIT {int:start}, {int:limit}',
			[
				'character_name' => $smcFunc['db']->is_case_sensitive() ? 'LOWER(character_name)' : 'character_name',
				'search' => $this->escape_term($this->term),
				'start' => $start,
				'limit' => $limit,
				'members' => array_merge([0], array_values($members)),
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$result[] = [
				'id' => $row['id_member'],
				'text' => $row['real_name'],
				'char_name' => $row['character_name'],
				'account_name' => $row['real_name'],
				'avatar' => set_avatar_data($row)['url'],
			];
		}
		$smcFunc['db']->free_result($request);

		return $result;
	}

	/**
	 * Sets existing values for populating a character autocomplete when editing a form.
	 *
	 * @param array $default_value An array of character ids to look up and populate into the autocomplete.
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
		$request = $smcFunc['db']->query('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:default_value})',
			[
				'default_value' => $default_value,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$this->default[$row['id_member']] = $row;
		}
		$smcFunc['db']->free_result($request);
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
				type: "memberchar"
			}
			query[sbb_session_var] = sbb_session_id;
			return query;
		}
	},
	delay: 150,
	templateResult: function(character) {
		if (!character.avatar)
			return character.text;

		var str = ' . json_encode($txt['autocomplete_search_character_account']) . ';

		var $char = $("<div class=\"autocomplete\"><div style=\"background-image:url(" + character.avatar + ")\" class=\"autocomplete-avatar-lg\"></div><div class=\"autocomplete-container\"><div class=\"autocomplete-character\">" + character.account_name + "</div>" + (character.char_name != character.account_name ? "<div class=\"autocomplete-character-account\">(" + character.char_name + ")</div>" : "") + "</div></div>");
		return $char;
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
