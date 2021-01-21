<?php

/**
 * Helper for warnings.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

class Warning
{
	/**
	 * Get the number of warnings a user has. Callback for $listOptions['get_count'] in issueWarning()
	 *
	 * @param int $memID The ID of the user
	 * @return int Total number of warnings for the user
	 */
	public static function list_getUserWarningCount($memID)
	{
		global $smcFunc;

		$request = $smcFunc['db']->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}log_comments
			WHERE id_recipient = {int:selected_member}
				AND comment_type = {literal:warning}',
			[
				'selected_member' => $memID,
			]
		);
		list ($total_warnings) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		return $total_warnings;
	}

	/**
	 * Get the data about a user's warnings. Callback function for the list in issueWarning()
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show on each page
	 * @param string $sort A string indicating how to sort the results
	 * @param int $memID The member ID
	 * @return array An array of information about the user's warnings
	 */
	public static function list_getUserWarnings($start, $items_per_page, $sort, $memID)
	{
		global $smcFunc, $scripturl;

		$request = $smcFunc['db']->query('', '
			SELECT COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lc.member_name) AS member_name,
				lc.log_time, lc.body, lc.counter, lc.id_notice
			FROM {db_prefix}log_comments AS lc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
			WHERE lc.id_recipient = {int:selected_member}
				AND lc.comment_type = {literal:warning}
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:max}',
			[
				'selected_member' => $memID,
				'sort' => $sort,
				'start' => $start,
				'max' => $items_per_page,
			]
		);
		$previous_warnings = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$previous_warnings[] = [
				'issuer' => [
					'id' => $row['id_member'],
					'link' => $row['id_member'] ? ('<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['member_name'] . '</a>') : $row['member_name'],
				],
				'time' => timeformat($row['log_time']),
				'reason' => $row['body'],
				'counter' => $row['counter'] > 0 ? '+' . $row['counter'] : $row['counter'],
				'id_notice' => $row['id_notice'],
			];
		}
		$smcFunc['db']->free_result($request);

		return $previous_warnings;
	}
}
