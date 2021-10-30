<?php

/**
 * Provide an autocomplete handler to match member characters.
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
 * Provide an autocomplete handler to match member characters (not OOC characters)
 */
class Character extends AbstractCompletable implements Completable
{
	use Database;

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
			SELECT COUNT(id_character)
			FROM {db_prefix}characters AS chars
			INNER JOIN {db_prefix}members AS mem ON (chars.id_member = mem.id_member)
			WHERE {raw:character_name} LIKE {string:search}
				AND is_main = 0
				AND is_activated IN (1, 11)',
			[
				'character_name' => $db->is_case_sensitive() ? 'LOWER(character_name)' : 'character_name',
				'search' => $this->escape_term($this->term),
			]
		);
		list ($count) = $db->fetch_row($request);
		$db->free_result($request);

		return (int) $count;
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
			SELECT chars.id_character, chars.character_name, mem.real_name, mem.email_address, a.filename, chars.avatar
			FROM {db_prefix}members AS mem
				INNER JOIN {db_prefix}characters AS chars ON (chars.id_member = mem.id_member)
				LEFT JOIN {db_prefix}attachments AS a ON (a.id_character = chars.id_character AND a.attachment_type = 1)
			WHERE {raw:character_name} LIKE {string:search}
				AND is_main = 0
				AND is_activated IN (1, 11)
			LIMIT {int:start}, {int:limit}',
			[
				'character_name' => $db->is_case_sensitive() ? 'LOWER(character_name)' : 'character_name',
				'search' => $this->escape_term($this->term),
				'start' => $start,
				'limit' => $limit,
			]
		);
		while ($row = $db->fetch_assoc($request))
		{
			$result[] = [
				'id' => $row['id_character'],
				'text' => $row['character_name'],
				'char_name' => $row['character_name'],
				'account_name' => $row['real_name'],
				'avatar' => set_avatar_data($row)['url'],
			];
		}
		$db->free_result($request);

		return $result;
	}

	/**
	 * Sets existing values for populating a character autocomplete when editing a form.
	 *
	 * @param array $default_value An array of character ids to look up and populate into the autocomplete.
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
			SELECT id_character, character_name
			FROM {db_prefix}characters
			WHERE id_character IN ({array_int:default_value})
				AND is_main = 0',
			[
				'default_value' => $default_value,
			]
		);
		while ($row = $db->fetch_assoc($request))
		{
			$this->default[$row['id_character']] = $row;
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
	placeholder: ' . json_encode($txt['autocomplete_search_character']) . ',
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
	templateResult: function(character) {
		if (!character.avatar)
			return character.text;

		var str = ' . json_encode($txt['autocomplete_search_character_account']) . ';

		var $char = $("<div class=\"autocomplete\"><div style=\"background-image:url(" + character.avatar + ")\" class=\"autocomplete-avatar-lg\"></div><div class=\"autocomplete-container\"><div class=\"autocomplete-character\">" + character.text + "</div><div class=\"autocomplete-character-account\">" + str.replace("%s", character.account_name) + "</div></div></div>");
		return $char;
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
