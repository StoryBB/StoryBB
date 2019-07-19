<?php

/**
 * Autcompleting for characters when we don't want to differentiate between IC/OOC, e.g. search.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Autocomplete;

/**
 * Autcompleting for characters when we don't want to differentiate between IC/OOC, e.g. search.
 */
class RawCharacter extends AbstractCompletable implements Completable
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
			SELECT COUNT(id_character)
			FROM {db_prefix}characters AS chars
			INNER JOIN {db_prefix}members AS mem ON (chars.id_member = mem.id_member)
			WHERE {raw:character_name} LIKE {string:search}
				AND is_activated IN (1, 11)',
			[
				'character_name' => $smcFunc['db_case_sensitive'] ? 'LOWER(character_name)' : 'character_name',
				'search' => $this->escape_term($this->term),
			]
		);
		list ($count) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return (int) $count;
	}

	/**
	 * Returns the actual results based on paginated through the filters search results.
	 * Each result will contain id (character id), text and char_name (character name),
	 * and avatar (URL for character avatar)
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
			SELECT chars.id_character, chars.character_name, mem.email_address, a.filename, chars.avatar
			FROM {db_prefix}members AS mem
				INNER JOIN {db_prefix}characters AS chars ON (chars.id_member = mem.id_member)
				LEFT JOIN {db_prefix}attachments AS a ON (a.id_character = chars.id_character AND a.attachment_type = 1)
			WHERE {raw:character_name} LIKE {string:search}
				AND is_activated IN (1, 11)
			LIMIT {int:start}, {int:limit}',
			[
				'character_name' => $smcFunc['db_case_sensitive'] ? 'LOWER(character_name)' : 'character_name',
				'search' => $this->escape_term($this->term),
				'start' => $start,
				'limit' => $limit,
			]
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$result[] = [
				'id' => $row['id_character'],
				'text' => $row['character_name'],
				'char_name' => $row['character_name'],
				'avatar' => set_avatar_data($row)['url'],
			];
		}
		$smcFunc['db_free_result']($request);

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
		$request = $smcFunc['db_query']('', '
			SELECT id_character, character_name
			FROM {db_prefix}characters
			WHERE id_character IN ({array_int:default_value})',
			[
				'default_value' => $default_value,
			]
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$this->default[$row['id_character']] = $row;
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
	placeholder: ' . json_encode($txt['autocomplete_search_character']) . ',
	allowClear: ' . ($maximum == 1 ? 'true' : 'false') . ',' . ($maximum > 1 ? '
	maximumSelectionLength: ' . $maximum . ',' : '') . '
	ajax: {
		url: "' . $scripturl . '",
		data: function (params) {
			var query = {
				action: "autocomplete",
				term: params.term,
				type: "rawcharacter"
			}
			query[sbb_session_var] = sbb_session_id;
			return query;
		}
	},
	delay: 150,
	templateResult: function(character) {
		if (!character.avatar)
			return character.text;

		var $mem = $("<div class=\"autocomplete\"><div style=\"background-image:url(" + character.avatar + ")\" class=\"autocomplete-avatar\"></div><span class=\"autocomplete-member\">" + character.text + "</span></div>");
		return $mem;
	}
});';
		if (!empty($this->default))
		{
			foreach ($this->default as $default)
			{
				$js .= '
$("' . $target . '").append(new Option(' . json_encode($default['character_name']) . ', ' . $default['id_character'] . ', false, false));';
			}
			$js .= '
$("' . $target . '").val(' . json_encode(array_keys($this->default)) . ').trigger("change");';
		}

		return $js;
	}
}
