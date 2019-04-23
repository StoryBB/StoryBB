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

class Group extends AbstractCompletable implements Completable
{
	protected $post_count_groups = true;
	protected $account_groups = true;
	protected $character_groups = true;
	protected $hidden = false;

	protected function get_filters(): string
	{
		$filters = [];
		if (!$this->post_count_groups)
		{
			$filters[] = 'min_posts = -1';
		}
		if (!$this->account_groups)
		{
			$filters[] = 'is_character != 0';
		}
		if (!$this->character_groups)
		{
			$filters[] = 'is_character = 0';
		}
		if (!$hidden)
		{
			$filters[] = 'hidden != 2';
		}
		return !empty($filters) ? ' AND ' . implode(' AND ', $filters) : '';
	}

	public function can_paginate(): bool
	{
		return true;
	}

	public function get_count(): int
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(id_group)
			FROM {db_prefix}membergroups AS mg
			WHERE {raw:group_name} LIKE {string:search}' . $this->get_filters(),
			[
				'group_name' => $smcFunc['db_case_sensitive'] ? 'LOWER(group_name)' : 'group_name',
				'search' => '%' . $this->escape_term($this->term) . '%',
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
			SELECT mg.id_group, mg.group_name, mg.icons
			FROM {db_prefix}membergroups AS mg
			WHERE {raw:group_name} LIKE {string:search}' . $this->get_filters() . '
			LIMIT {int:start}, {int:limit}',
			[
				'group_name' => $smcFunc['db_case_sensitive'] ? 'LOWER(group_name)' : 'group_name',
				'search' => '%' . $this->escape_term($this->term) . '%',
				'start' => $start,
				'limit' => $limit,
			]
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$row['icons'] = explode('#', $row['icons']);
			$row['icons'] = !empty( $row['icons'][0]) && !empty( $row['icons'][1]) ? str_repeat('<img src="' . $settings['images_url'] . '/membericons/' .  $row['icons'][1] . '" alt="*">',  $row['icons'][0]) : '';

			$result[] = [
				'id' => $row['id_group'],
				'text' => $row['group_name'],
				'icons' => $row['icons'],
			];
		}
		$smcFunc['db_free_result']($request);

		return $result;
	}

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
			SELECT id_group, group_name
			FROM {db_prefix}membergroups
			WHERE id_group IN ({array_int:default_value})',
			[
				'default_value' => $default_value,
			]
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$this->default[$row['id_group']] = $row;
		}
		$smcFunc['db_free_result']($request);
	}

	public function get_js(string $target, int $maximum = 1): string
	{
		global $scripturl, $txt;

		$js = '
$("' . $target . '").select2({
	dropdownAutoWidth: true,
	width: "auto",
	placeholder: ' . json_encode($txt['autocomplete_search_group']) . ',
	allowClear: ' . ($maximum == 1 ? 'true' : 'false') . ',' . ($maximum > 1 ? '
	maximumSelectionLength: ' . $maximum . ',' : '') . '
	ajax: {
		url: "' . $scripturl . '",
		data: function (params) {
			var query = {
				action: "autocomplete",
				term: params.term,
				type: "' . $this->get_searchtype() . '"
			}
			query[sbb_session_var] = sbb_session_id;
			return query;
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
