<?php

/**
 * Displays the watched boards page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

class WatchedBoards extends AbstractProfileController
{
	protected function get_token_name()
	{
		return str_replace('%u', $this->params['u'], 'profile-nt%u');
	}

	public function display_action()
	{
		global $txt, $scripturl, $context, $modSettings, $sourcedir, $smcFunc;

		$context['sub_template'] = 'profile_alerts_watchedboards';

		$memID = $this->params['u'];

		// Now set up for the token check.
		$context['token_check'] = $this->get_token_name();
		createToken($context['token_check'], 'post');

		// Gonna want this for the list.
		require_once($sourcedir . '/Subs-List.php');

		// Do the topic notifications.
		$listOptions = [
			'id' => 'board_notification_list',
			'width' => '100%',
			'no_items_label' => $txt['notifications_boards_none'] . '<br><br>' . $txt['notifications_boards_howto'],
			'no_items_align' => 'left',
			'base_href' => $scripturl . '?action=profile;u=' . $memID . ';area=watched_boards',
			'default_sort_col' => 'board_name',
			'get_items' => [
				'function' => function($start, $items_per_page, $sort, $memID)
				{
					global $smcFunc, $scripturl, $user_info, $sourcedir;

					require_once($sourcedir . '/Subs-Notify.php');
					$prefs = getNotifyPrefs($memID);
					$prefs = isset($prefs[$memID]) ? $prefs[$memID] : [];

					$request = $smcFunc['db']->query('', '
						SELECT b.id_board, b.name, COALESCE(lb.id_msg, 0) AS board_read, b.id_msg_updated
						FROM {db_prefix}log_notify AS ln
							INNER JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board)
							LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
						WHERE ln.id_member = {int:selected_member}
							AND {query_see_board}
						ORDER BY {raw:sort}',
						[
							'current_member' => $user_info['id'],
							'selected_member' => $memID,
							'sort' => $sort,
						]
					);
					$notification_boards = [];
					while ($row = $smcFunc['db']->fetch_assoc($request))
						$notification_boards[] = [
							'id' => $row['id_board'],
							'name' => $row['name'],
							'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
							'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
							'new' => $row['board_read'] < $row['id_msg_updated'],
							'notify_pref' => isset($prefs['board_notify_' . $row['id_board']]) ? $prefs['board_notify_' . $row['id_board']] : (!empty($prefs['board_notify']) ? $prefs['board_notify'] : 0),
						];
					$smcFunc['db']->free_result($request);

					return $notification_boards;
				},
				'params' => [
					$memID,
				],
			],
			'columns' => [
				'board_name' => [
					'header' => [
						'value' => $txt['notifications_boards'],
						'class' => 'lefttext',
					],
					'data' => [
						'function' => function($board) use ($txt)
						{
							$link = $board['link'];

							if ($board['new'])
								$link .= '&nbsp; <a href="' . $board['href'] . '" class="new_posts"></a>';

							return $link;
						},
					],
					'sort' => [
						'default' => 'name',
						'reverse' => 'name DESC',
					],
				],
				'alert' => [
					'header' => [
						'value' => $txt['notify_what_how'],
						'class' => 'lefttext',
					],
					'data' => [
						'function' => function($board) use ($txt)
						{
							$pref = $board['notify_pref'];
							$mode = $pref & 0x02 ? 3 : ($pref & 0x01 ? 2 : 1);
							return $txt['notify_board_' . $mode];
						},
					],
				],
				'delete' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'style' => 'width: 4%;',
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="notify_boards[]" value="%1$d">',
							'params' => [
								'id' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => $scripturl . '?action=profile;area=watched_boards',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => [
					'u' => $memID,
					$context['session_var'] => $context['session_id'],
				],
				'token' => $context['token_check'],
			],
			'additional_rows' => [
				[
					'position' => 'bottom_of_list',
					'value' => '<button type="submit" name="edit_notify_boards" value="edit" class="button">' . $txt['notifications_update'] . '</button>
								<button type="submit" name="remove_notify_boards" value="remove" class="button">' . $txt['notification_remove_pref'] . '</button>',
					'class' => 'floatright',
				],
			],
		];

		// Create the notification list.
		createList($listOptions);
	}

	public function post_action()
	{
		global $sourcedir, $txt, $context, $cur_profile, $smcFunc;

		$memID = $this->params['u'];
		require_once($sourcedir . '/Subs-Notify.php');

		// Because of the way this stuff works, we want to do this ourselves.
		if (isset($_POST['edit_notify_boards']) && isset($_POST['notify_boards']))
		{
			validateToken($this->get_token_name(), 'post');

			// Make sure only integers are deleted.
			foreach ($_POST['notify_boards'] as $index => $id)
				$_POST['notify_boards'][$index] = (int) $id;

			// id_board = 0 is reserved for topic notifications.
			$_POST['notify_boards'] = array_diff($_POST['notify_boards'], [0]);

			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}log_notify
				WHERE id_board IN ({array_int:board_list})
					AND id_member = {int:selected_member}',
				[
					'board_list' => $_POST['notify_boards'],
					'selected_member' => $memID,
				]
			);

			session_flash('success', $context['user']['is_owner'] ? $txt['profile_updated_own'] : sprintf($txt['profile_updated_else'], $cur_profile['member_name']));
		}

		if (isset($_POST['remove_notify_boards']) && !empty($_POST['notify_boards']))
		{
			validateToken($this->get_token_name(), 'post');

			$prefs = [];
			foreach ($_POST['notify_boards'] as $board)
				$prefs[] = 'board_notify_' . $board;
			deleteNotifyPrefs($memID, $prefs);

			session_flash('success', $context['user']['is_owner'] ? $txt['profile_updated_own'] : sprintf($txt['profile_updated_else'], $cur_profile['member_name']));
		}

		redirectexit('action=profile;area=watched_boards;u=' . $memID);
	}
}
