<?php

/**
 * Handle the data export page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Model\Alert;
use StoryBB\Task;

class ExportData extends AbstractProfileController
{
	public function display_action()
	{
		global $context, $sourcedir, $txt, $modSettings, $scripturl, $smcFunc;

		$memID = $this->params['u'];

		// Users can get to their own, but otherwise, must be admins.
		if ($context['user']['id'] != $memID)
		{
			isAllowedTo('admin_forum');
		}

		// Is the user requesting a download?
		if (isset($_REQUEST['download']))
		{
			$_REQUEST['download'] = (int) $_REQUEST['download'];
			checkSession('get');

			$request = $smcFunc['db']->query('', '
				SELECT a.id_attach, ue.id_member
				FROM {db_prefix}user_exports AS ue
				INNER JOIN {db_prefix}attachments AS a ON (ue.id_attach = a.id_attach)
				WHERE ue.id_export = {int:export}
					AND ue.id_member = {int:member}
					AND ue.requested_on > {int:last_valid}',
				[
					'export' => $_REQUEST['download'],
					'member' => $memID,
					'last_valid' => time() - (86400 * 7),
				]
			);
			if ($row = $smcFunc['db']->fetch_assoc($request))
			{
				require_once($sourcedir . '/ShowAttachments.php');
				showAttachment($row['id_attach']);
			}
			fatal_lang_error('profile_export_data_not_available', false);
		}

		// Did this user potentially get an alert about it? If so, clear up.
		$alerted = Alert::find_alerts([
			'content_type' => 'member',
			'content_id' => $memID,
			'content_action' => 'export_complete',
			'id_member' => $context['user']['id'],
			'is_read' => 0
		]);
		if (!empty($alerted))
		{
			foreach ($alerted as $member => $alerts)
			{
				Alert::change_read($member, $alerts, 1);
			}
		}

		// Is one currently processing for this user?
		$in_process = false;
		$request = $smcFunc['db']->query('', '
			SELECT ue.id_export, a.approved
			FROM {db_prefix}user_exports AS ue
				INNER JOIN {db_prefix}attachments AS a ON (ue.id_attach = a.id_attach)
			WHERE ue.id_member = {int:memID}
			ORDER BY ue.id_export DESC
			LIMIT 1',
			[
				'memID' => $memID,
			]
		);
		if ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (!empty($row['id_export']) && empty($row['approved']))
				$in_process = true;
		}
		$smcFunc['db']->free_result($request);

		// User is requesting an export.
		if (!$in_process && isset($_GET['request']))
		{
			checkSession('get');
			session_flash('success', $txt['profile_export_data_queued']);
			Task::queue_adhoc('StoryBB\\Task\\Adhoc\\ExportData', [
				'id_member' => $memID,
				'id_requester' => $context['user']['id'],
			]);
			redirectexit('action=profile;area=export_data;u=' . $memID);
		}

		$context['page_title'] = $txt['profile_export_data'];
		require_once($sourcedir . '/Subs-List.php');

		$listOptions = [
			'id' => 'list_exports',
			'title' => $txt['profile_export_data'],
			'items_per_page' => $modSettings['defaultMaxListItems'],
			'no_items_label' => $txt['profile_export_data_no_export_available'],
			'base_href' => $scripturl . '?action=profile;area=issue_warning;sa=user;u=' . $memID,
			'default_sort_col' => 'requested_on',
			'get_items' => [
				'function' => function($start, $items_per_page, $sort, $memID) use ($smcFunc)
				{
					$request = $smcFunc['db']->query('', '
						SELECT ue.id_export, ue.id_attach, ue.id_member, mem.real_name, ue.id_requester, a.approved, a.size,
							ue.requested_on
						FROM {db_prefix}user_exports AS ue
							INNER JOIN {db_prefix}attachments AS a ON (ue.id_attach = a.id_attach)
							LEFT JOIN {db_prefix}members AS mem ON (ue.id_requester = mem.id_member)
						WHERE ue.id_member = {int:memID}
						ORDER BY {raw:sort}
						LIMIT {int:start}, {int:limit}',
						[
							'memID' => $memID,
							'sort' => $sort,
							'start' => $start,
							'limit' => $items_per_page,
						]
					);
					$rows = [];
					while ($row = $smcFunc['db']->fetch_assoc($request))
					{
						$rows[] = $row;
					}
					$smcFunc['db']->free_result($request);
					return $rows;
				},
				'params' => [
					$memID,
				],
			],
			'get_count' => [
				'function' => function($memID) use ($smcFunc)
				{
					$request = $smcFunc['db']->query('', '
						SELECT COUNT(ue.id_export)
						FROM {db_prefix}user_exports AS ue
						WHERE ue.id_member = {int:memID}',
						[
							'memID' => $memID,
						]
					);
					list($count) = $smcFunc['db']->fetch_row($request);
					$smcFunc['db']->free_result($request);

					return $count;
				},
				'params' => [
					$memID,
				],
			],
			'columns' => [
				'requested_on' => [
					'header' => [
						'value' => $txt['profile_export_data_requested_on'],
					],
					'data' => [
						'db' => 'requested_on',
						'timeformat' => true,
					],
					'sort' => [
						'default' => 'ue.requested_on DESC',
						'reverse' => 'ue.requested_on',
					],
				],
				'requested_by' => [
					'header' => [
						'value' => $txt['profile_export_data_requested_by'],
					],
					'data' => [
						'function' => function($rowData) use ($scripturl, $txt)
						{
							if (!empty($rowData['real_name']))
							{
								return '<a href="' . $scripturl . '?action=profile;u=' . $rowData['id_requester'] . '">' . $rowData['real_name'] . '</a>';
							}
							return $txt['not_applicable'];
						},
					],
					'sort' => [],
				],
				'download' => [
					'header' => [
						'value' => $txt['profile_export_data_download'],
					],
					'data' => [
						'function' => function($rowData) use ($scripturl, $txt, $context)
						{
							if ($rowData['approved'])
							{
								return '<a href="' . $scripturl . '?action=profile;area=export_data;u=' . $rowData['id_member'] . ';download=' . $rowData['id_export'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '">' . $txt['profile_export_data_download'] . '</a> (' . round($rowData['size'] / 1024, 1) . $txt['kilobyte'] . ')';
							}
							return $txt['profile_export_data_processing'];
						}
					],
					'sort' => [],
				],
				'available_until' => [
					'header' => [
						'value' => $txt['profile_export_data_available_until'],
					],
					'data' => [
						'function' => function($rowData) use ($scripturl, $txt, $context)
						{
							if ($rowData['approved'])
							{
								return timeformat($rowData['requested_on'] + (7 * 86400));
							}
							return $txt['not_applicable'];
						}
					],
					'sort' => [],
				],
			],
			'additional_rows' => [
				[
					'position' => 'bottom_of_list',
					'value' => $in_process ? $txt['profile_export_data_in_process'] : '
						<a href="' . $scripturl . '?action=profile;area=export_data;u=' . $memID . ';request;' . $context['session_var'] . '=' . $context['session_id'] . '" class="button">' . $txt['profile_export_data_go'] . '</a>',
					'class' => 'floatright',
				],
			],
		];
		createList($listOptions);

		$context['default_list'] = 'list_exports';
		$context['sub_template'] = 'generic_list_page';
	}
}
