<?php

/**
 * Displays the login history page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

class GroupRequests extends AbstractProfileController
{
	public function display_action()
	{
		global $scripturl, $txt, $modSettings, $sourcedir, $context, $smcFunc;

		$memID = $this->params['u'];
		require_once($sourcedir . '/Subs-List.php');

		// Set the options for the error lists.
		$listOptions = [
			'id' => 'request_list',
			'title' => sprintf($txt['trackGroupRequests_title'], $context['member']['name']),
			'items_per_page' => $modSettings['defaultMaxListItems'],
			'no_items_label' => $txt['requested_none'],
			'base_href' => $scripturl . '?action=profile;area=tracking;sa=groupreq;u=' . $memID,
			'default_sort_col' => 'time_applied',
			'get_items' => [
				'function' => function($start, $items_per_page, $sort, $memID)
				{
					global $smcFunc, $txt, $scripturl, $user_info;

					$groupreq = [];

					$request = $smcFunc['db']->query('', '
						SELECT
							lgr.id_group, mg.group_name, mg.online_color, lgr.time_applied, lgr.reason, lgr.status,
							ma.id_member AS id_member_acted, COALESCE(ma.member_name, lgr.member_name_acted) AS act_name, lgr.time_acted, lgr.act_reason
						FROM {db_prefix}log_group_requests AS lgr
							LEFT JOIN {db_prefix}members AS ma ON (lgr.id_member_acted = ma.id_member)
							INNER JOIN {db_prefix}membergroups AS mg ON (lgr.id_group = mg.id_group)
						WHERE lgr.id_member = {int:memID}
							AND ' . ($user_info['mod_cache']['gq'] == '1=1' ? $user_info['mod_cache']['gq'] : 'lgr.' . $user_info['mod_cache']['gq']) . '
						ORDER BY {raw:sort}
						LIMIT {int:start}, {int:max}',
						[
							'memID' => $memID,
							'sort' => $sort,
							'start' => $start,
							'max' => $items_per_page,
						]
					);
					while ($row = $smcFunc['db']->fetch_assoc($request))
					{
						$this_req = [
							'group_name' => empty($row['online_color']) ? $row['group_name'] : '<span style="color:' . $row['online_color'] . '">' . $row['group_name'] . '</span>',
							'group_reason' => $row['reason'],
							'time_applied' => $row['time_applied'],
						];
						switch ($row['status'])
						{
							case 0:
								$this_req['outcome'] = $txt['outcome_pending'];
								break;
							case 1:
								$member_link = empty($row['id_member_acted']) ? $row['act_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_acted'] . '">' . $row['act_name'] . '</a>';
								$this_req['outcome'] = sprintf($txt['outcome_approved'], $member_link, timeformat($row['time_acted']));
								break;
							case 2:
								$member_link = empty($row['id_member_acted']) ? $row['act_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_acted'] . '">' . $row['act_name'] . '</a>';
								$this_req['outcome'] = sprintf(!empty($row['act_reason']) ? $txt['outcome_refused_reason'] : $txt['outcome_refused'], $member_link, timeformat($row['time_acted']), $row['act_reason']);
								break;
						}

						$groupreq[] = $this_req;
					}
					$smcFunc['db']->free_result($request);

					return $groupreq;
				},
				'params' => [
					$memID,
				],
			],
			'get_count' => [
				'function' => function($memID) use ($smcFunc)
				{
					global $smcFunc, $user_info;

					$request = $smcFunc['db']->query('', '
						SELECT COUNT(*) AS req_count
						FROM {db_prefix}log_group_requests AS lgr
						WHERE id_member = {int:memID}
							AND ' . ($user_info['mod_cache']['gq'] == '1=1' ? $user_info['mod_cache']['gq'] : 'lgr.' . $user_info['mod_cache']['gq']),
						[
							'memID' => $memID,
						]
					);
					list ($report_count) = $smcFunc['db']->fetch_row($request);
					$smcFunc['db']->free_result($request);

					return (int) $report_count;
				},
				'params' => [
					$memID,
				],
			],
			'columns' => [
				'group' => [
					'header' => [
						'value' => $txt['requested_group'],
					],
					'data' => [
						'db' => 'group_name',
					],
				],
				'group_reason' => [
					'header' => [
						'value' => $txt['requested_group_reason'],
					],
					'data' => [
						'db' => 'group_reason',
					],
				],
				'time_applied' => [
					'header' => [
						'value' => $txt['requested_group_time'],
					],
					'data' => [
						'db' => 'time_applied',
						'timeformat' => true,
					],
					'sort' => [
						'default' => 'time_applied DESC',
						'reverse' => 'time_applied',
					],
				],
				'outcome' => [
					'header' => [
						'value' => $txt['requested_group_outcome'],
					],
					'data' => [
						'db' => 'outcome',
					],
				],
			],
		];

		// Create the error list.
		createList($listOptions);

		$context['sub_template'] = 'generic_list_page';
		$context['default_list'] = 'request_list';
	}
}
