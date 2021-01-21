<?php

/**
 * Displays the profile attachments page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

class ShowAttachments extends AbstractProfileController
{
	public function display_action()
	{
		global $txt, $user_info, $scripturl, $modSettings;
		global $context, $user_profile, $sourcedir, $smcFunc, $board;

		// Some initial context.
		$memID = $this->params['u'];
		$context['start'] = (int) $_REQUEST['start'];
		$context['current_member'] = $memID;

		$context['page_title'] = $txt['showAttachments'] . ' - ' . $user_profile[$memID]['real_name'];

		// Is the load average too high to allow searching just now?
		check_load_avg('show_posts');

		// OBEY permissions!
		$boardsAllowed = boardsAllowedTo('view_attachments');

		// Make sure we can't actually see anything...
		if (empty($boardsAllowed))
			$boardsAllowed = [-1];

		require_once($sourcedir . '/Subs-List.php');
		$context['sub_template'] = 'profile_show_attachments';

		// This is all the information required to list attachments.
		$listOptions = [
			'id' => 'attachments',
			'width' => '100%',
			'items_per_page' => $modSettings['defaultMaxListItems'],
			'no_items_label' => $txt['show_attachments_none'],
			'base_href' => $scripturl . '?action=profile;area=showposts;sa=attach;u=' . $memID,
			'default_sort_col' => 'filename',
			'get_items' => [
				'function' => function($start, $items_per_page, $sort, $boardsAllowed, $memID) use ($smcFunc, $board, $modSettings, $context)
				{
					// Retrieve some attachments.
					$request = $smcFunc['db']->query('', '
						SELECT a.id_attach, a.id_msg, a.filename, a.downloads, a.approved, m.id_msg, m.id_topic,
							m.id_board, m.poster_time, m.subject, b.name
						FROM {db_prefix}attachments AS a
							INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
							INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
						WHERE a.attachment_type = {int:attachment_type}
							AND a.id_msg != {int:no_message}
							AND m.id_member = {int:current_member}' . (!empty($board) ? '
							AND b.id_board = {int:board}' : '') . (!in_array(0, $boardsAllowed) ? '
							AND b.id_board IN ({array_int:boards_list})' : '') . ($context['user']['is_owner'] ? '' : '
							AND m.approved = {int:is_approved}') . '
						ORDER BY {raw:sort}
						LIMIT {int:offset}, {int:limit}',
						[
							'boards_list' => $boardsAllowed,
							'attachment_type' => 0,
							'no_message' => 0,
							'current_member' => $memID,
							'is_approved' => 1,
							'board' => $board,
							'sort' => $sort,
							'offset' => $start,
							'limit' => $items_per_page,
						]
					);
					$attachments = [];
					while ($row = $smcFunc['db']->fetch_assoc($request))
						$attachments[] = [
							'id' => $row['id_attach'],
							'filename' => $row['filename'],
							'downloads' => $row['downloads'],
							'subject' => censorText($row['subject']),
							'posted' => $row['poster_time'],
							'msg' => $row['id_msg'],
							'topic' => $row['id_topic'],
							'board' => $row['id_board'],
							'board_name' => $row['name'],
							'approved' => $row['approved'],
						];

					$smcFunc['db']->free_result($request);

					return $attachments;
				},
				'params' => [
					$boardsAllowed,
					$memID,
				],
			],
			'get_count' => [
				'function' => function($boardsAllowed, $memID) use ($board, $smcFunc, $modSettings, $context)
				{
					// Get the total number of attachments they have posted.
					$request = $smcFunc['db']->query('', '
						SELECT COUNT(*)
						FROM {db_prefix}attachments AS a
							INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
							INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
						WHERE a.attachment_type = {int:attachment_type}
							AND a.id_msg != {int:no_message}
							AND m.id_member = {int:current_member}' . (!empty($board) ? '
							AND b.id_board = {int:board}' : '') . (!in_array(0, $boardsAllowed) ? '
							AND b.id_board IN ({array_int:boards_list})' : '') . ($context['user']['is_owner'] ? '' : '
							AND m.approved = {int:is_approved}'),
						[
							'boards_list' => $boardsAllowed,
							'attachment_type' => 0,
							'no_message' => 0,
							'current_member' => $memID,
							'is_approved' => 1,
							'board' => $board,
						]
					);
					list ($attachCount) = $smcFunc['db']->fetch_row($request);
					$smcFunc['db']->free_result($request);

					return $attachCount;
				},
				'params' => [
					$boardsAllowed,
					$memID,
				],
			],
			'data_check' => [
				'class' => function($data)
				{
					return $data['approved'] ? '' : 'approvebg';
				}
			],
			'columns' => [
				'filename' => [
					'header' => [
						'value' => $txt['show_attach_filename'],
						'class' => 'lefttext',
						'style' => 'width: 25%;',
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . $scripturl . '?action=dlattach;topic=%1$d.0;attach=%2$d">%3$s</a>',
							'params' => [
								'topic' => true,
								'id' => true,
								'filename' => false,
							],
						],
					],
					'sort' => [
						'default' => 'a.filename',
						'reverse' => 'a.filename DESC',
					],
				],
				'downloads' => [
					'header' => [
						'value' => $txt['show_attach_downloads'],
						'style' => 'width: 12%;',
					],
					'data' => [
						'db' => 'downloads',
						'comma_format' => true,
					],
					'sort' => [
						'default' => 'a.downloads',
						'reverse' => 'a.downloads DESC',
					],
				],
				'subject' => [
					'header' => [
						'value' => $txt['message'],
						'class' => 'lefttext',
						'style' => 'width: 30%;',
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . $scripturl . '?msg=%1$d">%2$s</a>',
							'params' => [
								'msg' => true,
								'subject' => false,
							],
						],
					],
					'sort' => [
						'default' => 'm.subject',
						'reverse' => 'm.subject DESC',
					],
				],
				'posted' => [
					'header' => [
						'value' => $txt['show_attach_posted'],
						'class' => 'lefttext',
					],
					'data' => [
						'db' => 'posted',
						'timeformat' => true,
					],
					'sort' => [
						'default' => 'm.poster_time',
						'reverse' => 'm.poster_time DESC',
					],
				],
			],
		];

		// Create the request list.
		createList($listOptions);
	}
}
