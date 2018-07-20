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

		$request = $smcFunc['db_query']('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
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
			];
		}
		$smcFunc['db_free_result']($request);

		return $result;
	}

	public function get_js(string $target, int $maximum = 1): string
	{
		global $scripturl, $txt;

		return '
$("' . $target . '").select2({
	dropdownAutoWidth: true,
	width: "auto",
	placeholder: "' . $txt['autocomplete_search_member'] . '",
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
	}
});';
	}
}
