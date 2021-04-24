<?php

/**
 * Displays the activity history page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Helper\IP;
use StoryBB\Helper\Parser;

class AccountActivity extends AbstractProfileController
{
	public function display_action()
	{
		global $scripturl, $txt, $modSettings, $sourcedir;
		global $user_profile, $context, $smcFunc;

		// Verify if the user has sufficient permissions.
		isAllowedTo('moderate_forum');

		$memID = $this->params['u'];
		$context['last_ip'] = $user_profile[$memID]['member_ip'];
		if ($context['last_ip'] != $user_profile[$memID]['member_ip2'])
			$context['last_ip2'] = $user_profile[$memID]['member_ip2'];
		$context['member']['name'] = $user_profile[$memID]['real_name'];

		// Set the options for the list component.
		$listOptions = [
			'id' => 'track_user_list',
			'title' => $txt['errors_by'] . ' ' . $context['member']['name'],
			'items_per_page' => $modSettings['defaultMaxListItems'],
			'no_items_label' => $txt['no_errors_from_user'],
			'base_href' => $scripturl . '?action=profile;area=activity;u=' . $memID,
			'default_sort_col' => 'date',
			'get_items' => [
				'function' => ['StoryBB\\Helper\\Profile', 'list_getUserErrors'],
				'params' => [
					'le.id_member = {int:current_member}',
					['current_member' => $memID],
				],
			],
			'get_count' => [
				'function' => ['StoryBB\\Helper\\Profile', 'list_getUserErrorCount'],
				'params' => [
					'id_member = {int:current_member}',
					['current_member' => $memID],
				],
			],
			'columns' => [
				'ip_address' => [
					'header' => [
						'value' => $txt['ip_address'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . $scripturl . '?action=admin;area=logs;sa=ip;searchip=%1$s;u=' . $memID . '">%1$s</a>',
							'params' => [
								'ip' => false,
							],
						],
					],
					'sort' => [
						'default' => 'le.ip',
						'reverse' => 'le.ip DESC',
					],
				],
				'message' => [
					'header' => [
						'value' => $txt['message'],
					],
					'data' => [
						'sprintf' => [
							'format' => '%1$s<br><a href="%2$s">%2$s</a>',
							'params' => [
								'message' => false,
								'url' => false,
							],
						],
					],
				],
				'date' => [
					'header' => [
						'value' => $txt['date'],
					],
					'data' => [
						'db' => 'time',
					],
					'sort' => [
						'default' => 'le.id_error DESC',
						'reverse' => 'le.id_error',
					],
				],
			],
			'additional_rows' => [
				[
					'position' => 'after_title',
					'value' => $txt['errors_desc'],
				],
			],
		];

		// Create the list for viewing.
		require_once($sourcedir . '/Subs-List.php');
		createList($listOptions);

		// @todo cache this
		// If this is a big forum, or a large posting user, let's limit the search.
		if ($modSettings['totalMessages'] > 50000 && $user_profile[$memID]['posts'] > 500)
		{
			$request = $smcFunc['db']->query('', '
				SELECT MAX(id_msg)
				FROM {db_prefix}messages AS m
				WHERE m.id_member = {int:current_member}',
				[
					'current_member' => $memID,
				]
			);
			list ($max_msg_member) = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);

			// There's no point worrying ourselves with messages made yonks ago, just get recent ones!
			$min_msg_member = max(0, $max_msg_member - $user_profile[$memID]['posts'] * 3);
		}

		// Default to at least the ones we know about.
		$ips = [
			$user_profile[$memID]['member_ip'],
			$user_profile[$memID]['member_ip2'],
		];

		// @todo cache this
		// Get all IP addresses this user has used for his messages.
		$request = $smcFunc['db']->query('', '
			SELECT poster_ip
			FROM {db_prefix}messages
			WHERE id_member = {int:current_member}
			' . (isset($min_msg_member) ? '
				AND id_msg >= {int:min_msg_member} AND id_msg <= {int:max_msg_member}' : '') . '
			GROUP BY poster_ip',
			[
				'current_member' => $memID,
				'min_msg_member' => !empty($min_msg_member) ? $min_msg_member : 0,
				'max_msg_member' => !empty($max_msg_member) ? $max_msg_member : 0,
			]
		);
		$context['ips'] = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$context['ips'][] = '<a href="' . $scripturl . '?action=admin;area=logs;sa=ip;searchip=' . IP::format($row['poster_ip']) . ';u=' . $memID . '">' . IP::format($row['poster_ip']) . '</a>';
			$ips[] = IP::format($row['poster_ip']);
		}
		$smcFunc['db']->free_result($request);

		// Now also get the IP addresses from the error messages.
		$request = $smcFunc['db']->query('', '
			SELECT COUNT(*) AS error_count, ip
			FROM {db_prefix}log_errors
			WHERE id_member = {int:current_member}
			GROUP BY ip',
			[
				'current_member' => $memID,
			]
		);
		$context['error_ips'] = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (empty($row['ip']))
			{
				continue;
			}
			$context['error_ips'][] = '<a href="' . $scripturl . '?action=admin;area=logs;sa=ip;searchip=' . IP::format($row['ip']) . ';u=' . $memID . '">' . IP::format($row['ip']) . '</a>';
			$ips[] = IP::format($row['ip']);
		}
		$smcFunc['db']->free_result($request);

		// Find other users that might use the same IP.
		$ips = array_unique($ips);
		$context['members_in_range'] = [];
		if (!empty($ips))
		{
			// Get member ID's which are in messages...
			$request = $smcFunc['db']->query('', '
				SELECT DISTINCT mem.id_member
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				WHERE m.poster_ip IN ({array_inet:ip_list})
					AND mem.id_member != {int:current_member}',
				[
					'current_member' => $memID,
					'ip_list' => $ips,
				]
			);
			$message_members = [];
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$message_members[] = $row['id_member'];
			$smcFunc['db']->free_result($request);

			// Fetch their names, cause of the GROUP BY doesn't like giving us that normally.
			if (!empty($message_members))
			{
				$request = $smcFunc['db']->query('', '
					SELECT id_member, real_name
					FROM {db_prefix}members
					WHERE id_member IN ({array_int:message_members})',
					[
						'message_members' => $message_members,
						'ip_list' => $ips,
					]
				);
				while ($row = $smcFunc['db']->fetch_assoc($request))
					$context['members_in_range'][$row['id_member']] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
				$smcFunc['db']->free_result($request);
			}

			$request = $smcFunc['db']->query('', '
				SELECT id_member, real_name
				FROM {db_prefix}members
				WHERE id_member != {int:current_member}
					AND member_ip IN ({array_inet:ip_list})',
				[
					'current_member' => $memID,
					'ip_list' => $ips,
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$context['members_in_range'][$row['id_member']] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
			$smcFunc['db']->free_result($request);
		}

		$context['sub_template'] = 'profile_track_activity';
	}
}
