<?php

/**
 * Any autocomplete handlers must implement this interface.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Helper\Autocomplete;

class Character extends AbstractCompletable implements Completable
{
	public function can_paginate(): bool
	{
		return true;
	}

	public function get_count(): int
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(id_character)
			FROM {db_prefix}characters AS chars
			INNER JOIN {db_prefix}members AS mem ON (chars.id_member = mem.id_member)
			WHERE {raw:character_name} LIKE {string:search}
				AND is_main = 0
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
			SELECT chars.id_character, chars.character_name, mem.real_name, mem.email_address, a.filename, chars.avatar
			FROM {db_prefix}members AS mem
				INNER JOIN {db_prefix}characters AS chars ON (chars.id_member = mem.id_member)
				LEFT JOIN {db_prefix}attachments AS a ON (a.id_character = chars.id_character AND a.attachment_type = 1)
			WHERE {raw:character_name} LIKE {string:search}
				AND is_main = 0
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
				'account_name' => $row['real_name'],
				'avatar' => set_avatar_data($row)['url'],
			];
		}
		$smcFunc['db_free_result']($request);

		return $result;
	}

	public function set_value($default_value)
	{
		global $smcFunc;

		$default_value = (int) $default_value;
		if (empty($default_value))
			return;

		$request = $smcFunc['db_query']('', '
			SELECT id_character, character_name
			FROM {db_prefix}characters
				WHERE is_main = 0
			WHERE id_character = {int:default_value}',
			[
				'default_value' => $default_value,
			]
		);
		$this->default = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);
	}

	public function get_js(string $target, int $maximum = 1): string
	{
		global $scripturl, $txt;

		$js = '
$("' . $target . '").select2({
	dropdownAutoWidth: true,
	width: "auto",
	placeholder: ' . json_encode($txt['autocomplete_search_character']) . ',
	allowClear: ' . ($maximum == 1 ? 'true' : 'false') . ',
	ajax: {
		url: "' . $scripturl . '",
		data: function (params) {
			var query = {
				action: "autocomplete",
				term: params.term,
				type: "character"
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

		var $char = $("<div class=\"autocomplete\"><div style=\"background-image:url(" + character.avatar + ")\" class=\"autocomplete-avatar-lg\"></div><div class=\"autocomplete-container\"><div class=\"autocomplete-character\">" + character.text + "</div><div class=\"autocomplete-character-account\">" + str.replace("%s", character.account_name) + "</div></div></div>");
		return $char;
	}
});';

		if (!empty($this->default))
		{
			$js .= '
var newOption = new Option(' . json_encode($this->default['character_name']) . ', ' . $this->default['id_character'] . ', false, false);
$("' . $target . '").append(newOption).val(' . $this->default['id_character'] . ').trigger("change");
';
		}

		return $js;
	}
}
