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

class Member extends AbstractCompletable implements Completable
{
	public function can_paginate(): bool
	{
		return true;
	}

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
			$this_result = [
				'id' => $row['id_member'],
				'text' => $row['real_name'],
			];

			if (!empty($modSettings['gravatarOverride']) || (!empty($modSettings['gravatarEnabled']) && stristr($row['avatar'], 'gravatar://')))
			{
				if (!empty($modSettings['gravatarAllowExtraEmail']) && stristr($row['avatar'], 'gravatar://') && strlen($row['avatar']) > 11)
					$this_result['avatar'] = get_gravatar_url($smcFunc['substr']($row['avatar'], 11));
				else
					$this_result['avatar'] = get_gravatar_url($row['email_address']);
			}
			else
			{
				// So it's stored in the member table?
				if (!empty($row['avatar']))
				{
					$this_result['avatar'] = (stristr($row['avatar'], 'http://') || stristr($row['avatar'], 'https://')) ? $row['avatar'] : '';
				}
				elseif (!empty($row['filename']))
					$this_result['avatar'] = $modSettings['custom_avatar_url'] . '/' . $row['filename'];
				// Right... no avatar...use the default one
				else
					$this_result['avatar'] = $settings['images_url'] . '/default.png';
			}

			$result[] = $this_result;
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
			SELECT id_member, real_name
			FROM {db_prefix}member
			WHERE id_member = {int:default_value}',
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
	placeholder: ' . json_encode($txt['autocomplete_search_member']) . ',
	allowClear: ' . ($maximum == 1 ? 'true' : 'false') . ',
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
			$js .= '
var newOption = new Option(' . json_encode($this->default['real_name']) . ', ' . $this->default['id_member'] . ', false, false);
$("' . $target . '").append(newOption).val(' . $this->default['id_member'] . ').trigger("change");
';
		}

		return $js;
	}
}
