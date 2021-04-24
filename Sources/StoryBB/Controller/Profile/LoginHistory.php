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

use StoryBB\Helper\IP;
use StoryBB\Helper\Parser;

class LoginHistory extends AbstractProfileController
{
	public function display_action()
	{
		global $scripturl, $txt, $sourcedir, $context, $smcFunc;

		$memID = $this->params['u'];

		// Gonna want this for the list.
		require_once($sourcedir . '/Subs-List.php');

		$context['base_url'] = $scripturl . '?action=profile;area=logins;u=' . $memID;

		// Start with the user messages.
		$listOptions = [
			'id' => 'track_logins_list',
			'title' => $txt['trackLogins'],
			'no_items_label' => $txt['trackLogins_none_found'],
			'base_href' => $context['base_url'],
			'get_items' => [
				'function' => function($start, $items_per_page, $sort, $where, $where_vars = []) use ($smcFunc)
				{
					$request = $smcFunc['db']->query('', '
						SELECT time, ip, ip2
						FROM {db_prefix}member_logins
						WHERE id_member = {int:id_member}
						ORDER BY time DESC',
						[
							'id_member' => $where_vars['current_member'],
						]
					);
					$logins = [];
					while ($row = $smcFunc['db']->fetch_assoc($request))
						$logins[] = [
							'time' => timeformat($row['time']),
							'ip' => IP::format($row['ip']),
							'ip2' => IP::format($row['ip2']),
						];
					$smcFunc['db']->free_result($request);

					return $logins;
				},
				'params' => [
					'id_member = {int:current_member}',
					['current_member' => $memID],
				],
			],
			'get_count' => [
				'function' => function($where, $where_vars = []) use ($smcFunc)
				{
					$request = $smcFunc['db']->query('', '
						SELECT COUNT(*) AS message_count
						FROM {db_prefix}member_logins
						WHERE id_member = {int:id_member}',
						[
							'id_member' => $where_vars['current_member'],
						]
					);
					list ($count) = $smcFunc['db']->fetch_row($request);
					$smcFunc['db']->free_result($request);

					return (int) $count;
				},
				'params' => [
					'id_member = {int:current_member}',
					['current_member' => $memID],
				],
			],
			'columns' => [
				'time' => [
					'header' => [
						'value' => $txt['date'],
					],
					'data' => [
						'db' => 'time',
					],
				],
				'ip' => [
					'header' => [
						'value' => $txt['ip_address'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . $context['base_url'] . ';searchip=%1$s">%1$s</a> (<a href="' . $context['base_url'] . ';searchip=%2$s">%2$s</a>) ',
							'params' => [
								'ip' => false,
								'ip2' => false
							],
						],
					],
				],
			],
			'additional_rows' => [
				[
					'position' => 'after_title',
					'value' => $txt['trackLogins_desc'],
				],
			],
		];

		// Create the messages list.
		createList($listOptions);

		$context['sub_template'] = 'generic_list_page';
		$context['default_list'] = 'track_logins_list';
	}
}
