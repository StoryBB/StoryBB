<?php

/**
 * Displays the edit history page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Helper\Parser;

class EditHistory extends AbstractProfileController
{
	public function display_action()
	{
		global $scripturl, $txt, $modSettings, $sourcedir, $context, $smcFunc;

		$memID = $this->params['u'];

		require_once($sourcedir . '/Subs-List.php');

		// Get the names of any custom fields.
		$request = $smcFunc['db']->query('', '
			SELECT col_name, field_name, bbc
			FROM {db_prefix}custom_fields',
			[
			]
		);
		$context['custom_field_titles'] = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$context['custom_field_titles']['customfield_' . $row['col_name']] = [
				'title' => $row['field_name'],
				'parse_bbc' => $row['bbc'],
			];
		$smcFunc['db']->free_result($request);

		// Set the options for the error lists.
		$listOptions = [
			'id' => 'edit_list',
			'title' => $txt['trackEdits'],
			'items_per_page' => $modSettings['defaultMaxListItems'],
			'no_items_label' => $txt['trackEdit_no_edits'],
			'base_href' => $scripturl . '?action=profile;area=tracking;sa=edits;u=' . $memID,
			'default_sort_col' => 'time',
			'get_items' => [
				'function' => function($start, $items_per_page, $sort, $memID)
				{
					global $smcFunc, $txt, $scripturl, $context;

					// Get a list of error messages from this ip (range).
					$request = $smcFunc['db']->query('', '
						SELECT
							id_action, id_member, ip, log_time, action, extra
						FROM {db_prefix}log_actions
						WHERE id_log = {int:log_type}
							AND id_member = {int:owner}
						ORDER BY {raw:sort}
						LIMIT {int:start}, {int:max}',
						[
							'log_type' => 2,
							'owner' => $memID,
							'sort' => $sort,
							'start' => $start,
							'max' => $items_per_page,
						]
					);
					$edits = [];
					$members = [];
					while ($row = $smcFunc['db']->fetch_assoc($request))
					{
						$extra = sbb_json_decode($row['extra'], true);
						if (!empty($extra['applicator']))
							$members[] = $extra['applicator'];

						// Work out what the name of the action is.
						if (isset($txt['trackEdit_action_' . $row['action']]))
							$action_text = $txt['trackEdit_action_' . $row['action']];
						elseif (isset($txt[$row['action']]))
							$action_text = $txt[$row['action']];
						// Custom field?
						elseif (isset($context['custom_field_titles'][$row['action']]))
							$action_text = $context['custom_field_titles'][$row['action']]['title'];
						else
							$action_text = $row['action'];

						// Character related edit?
						if (!empty($extra['id_character']))
						{
							if (!empty($context['member']['characters'][$extra['id_character']]))
								$character_name = $context['member']['characters'][$extra['id_character']]['character_name'];
							else
								$character_name = $extra['character_name'];

							$action_text = sprintf($action_text, $character_name);
						}

						// Parse BBC?
						$parse_bbc = isset($context['custom_field_titles'][$row['action']]) && $context['custom_field_titles'][$row['action']]['parse_bbc'] ? true : false;

						$edits[] = [
							'id' => $row['id_action'],
							'ip' => inet_dtop($row['ip']),
							'id_member' => !empty($extra['applicator']) ? $extra['applicator'] : 0,
							'member_link' => $txt['trackEdit_deleted_member'],
							'action' => $row['action'],
							'action_text' => $action_text,
							'before' => !empty($extra['previous']) ? ($parse_bbc ? Parser::parse_bbc($extra['previous']) : $extra['previous']) : '',
							'after' => !empty($extra['new']) ? ($parse_bbc ? Parser::parse_bbc($extra['new']) : $extra['new']) : '',
							'time' => timeformat($row['log_time']),
						];
					}
					$smcFunc['db']->free_result($request);

					// Get any member names.
					if (!empty($members))
					{
						$request = $smcFunc['db']->query('', '
							SELECT
								id_member, real_name
							FROM {db_prefix}members
							WHERE id_member IN ({array_int:members})',
							[
								'members' => $members,
							]
						);
						$members = [];
						while ($row = $smcFunc['db']->fetch_assoc($request))
							$members[$row['id_member']] = $row['real_name'];
						$smcFunc['db']->free_result($request);

						foreach ($edits as $key => $value)
							if (isset($members[$value['id_member']]))
								$edits[$key]['member_link'] = '<a href="' . $scripturl . '?action=profile;u=' . $value['id_member'] . '">' . $members[$value['id_member']] . '</a>';
					}

					return $edits;
				},
				'params' => [
					$memID,
				],
			],
			'get_count' => [
				'function' => function($memID) use ($smcFunc)
				{
					$request = $smcFunc['db']->query('', '
						SELECT COUNT(*) AS edit_count
						FROM {db_prefix}log_actions
						WHERE id_log = {int:log_type}
							AND id_member = {int:owner}',
						[
							'log_type' => 2,
							'owner' => $memID,
						]
					);
					list ($edit_count) = $smcFunc['db']->fetch_row($request);
					$smcFunc['db']->free_result($request);

					return (int) $edit_count;
				},
				'params' => [
					$memID,
				],
			],
			'columns' => [
				'action' => [
					'header' => [
						'value' => $txt['trackEdit_action'],
					],
					'data' => [
						'db' => 'action_text',
					],
				],
				'before' => [
					'header' => [
						'value' => $txt['trackEdit_before'],
					],
					'data' => [
						'db' => 'before',
					],
				],
				'after' => [
					'header' => [
						'value' => $txt['trackEdit_after'],
					],
					'data' => [
						'db' => 'after',
					],
				],
				'time' => [
					'header' => [
						'value' => $txt['date'],
					],
					'data' => [
						'db' => 'time',
					],
					'sort' => [
						'default' => 'id_action DESC',
						'reverse' => 'id_action',
					],
				],
				'applicator' => [
					'header' => [
						'value' => $txt['trackEdit_applicator'],
					],
					'data' => [
						'db' => 'member_link',
					],
				],
			],
		];

		// Create the error list.
		createList($listOptions);

		$context['sub_template'] = 'generic_list_page';
		$context['default_list'] = 'edit_list';
	}
}
