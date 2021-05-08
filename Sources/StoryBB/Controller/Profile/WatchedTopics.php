<?php

/**
 * Displays the watched topics page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Model\TopicPrefix;

class WatchedTopics extends AbstractProfileController
{
	protected function get_token_name()
	{
		return str_replace('%u', $this->params['u'], 'profile-nt%u');
	}

	public function display_action()
	{
		global $txt, $scripturl, $context, $modSettings, $sourcedir, $smcFunc;

		$context['sub_template'] = 'profile_alerts_watchedtopics';

		$memID = $this->params['u'];

		// Now set up for the token check.
		$context['token_check'] = $this->get_token_name();
		createToken($context['token_check'], 'post');

		// Gonna want this for the list.
		require_once($sourcedir . '/Subs-List.php');

		// Do the topic notifications.
		$listOptions = [
			'id' => 'topic_notification_list',
			'width' => '100%',
			'items_per_page' => $modSettings['defaultMaxListItems'],
			'no_items_label' => $txt['notifications_topics_none'] . '<br><br>' . $txt['notifications_topics_howto'],
			'no_items_align' => 'left',
			'base_href' => $scripturl . '?action=profile;u=' . $memID . ';area=watched_topics',
			'default_sort_col' => 'last_post',
			'get_items' => [
				'function' => function($start, $items_per_page, $sort, $memID)
				{
					global $smcFunc, $scripturl, $user_info, $sourcedir;

					require_once($sourcedir . '/Subs-Notify.php');
					$prefs = getNotifyPrefs($memID);
					$prefs = isset($prefs[$memID]) ? $prefs[$memID] : [];

					// All the topics with notification on...
					$request = $smcFunc['db']->query('', '
						SELECT
							COALESCE(lt.id_msg, COALESCE(lmr.id_msg, -1)) + 1 AS new_from, b.id_board, b.name,
							t.id_topic, ms.subject, ms.id_member, COALESCE(mem.real_name, ms.poster_name) AS real_name_col,
							ml.id_msg_modified, ml.poster_time, ml.id_member AS id_member_updated,
							COALESCE(mem2.real_name, ml.poster_name) AS last_real_name,
							lt.unwatched
						FROM {db_prefix}log_notify AS ln
							INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic AND t.approved = {int:is_approved})
							INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})
							INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
							INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
							LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ms.id_member)
							LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = ml.id_member)
							LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
							LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})
						WHERE ln.id_member = {int:selected_member}
						ORDER BY {raw:sort}
						LIMIT {int:offset}, {int:items_per_page}',
						[
							'current_member' => $user_info['id'],
							'is_approved' => 1,
							'selected_member' => $memID,
							'sort' => $sort,
							'offset' => $start,
							'items_per_page' => $items_per_page,
						]
					);
					$notification_topics = [];
					$topic_ids = [];
					while ($row = $smcFunc['db']->fetch_assoc($request))
					{
						censorText($row['subject']);

						$topic_ids[$row['id_topic']] = $row['id_topic'];

						$notification_topics[] = [
							'id' => $row['id_topic'],
							'poster_link' => empty($row['id_member']) ? $row['real_name_col'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name_col'] . '</a>',
							'poster_updated_link' => empty($row['id_member_updated']) ? $row['last_real_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_updated'] . '">' . $row['last_real_name'] . '</a>',
							'subject' => $row['subject'],
							'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
							'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>',
							'new' => $row['new_from'] <= $row['id_msg_modified'],
							'new_from' => $row['new_from'],
							'updated' => timeformat($row['poster_time']),
							'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
							'new_link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new">' . $row['subject'] . '</a>',
							'board_link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
							'notify_pref' => isset($prefs['topic_notify_' . $row['id_topic']]) ? $prefs['topic_notify_' . $row['id_topic']] : (!empty($prefs['topic_notify']) ? $prefs['topic_notify'] : 0),
							'unwatched' => $row['unwatched'],
							'prefixes' => [],
						];
					}
					$smcFunc['db']->free_result($request);

					if (!empty($topic_ids))
					{
						$prefixes = TopicPrefix::get_prefixes_for_topic_list($topic_ids);
						foreach ($notification_topics as $key => $notification)
						{
							if (isset($prefixes[$notification['id']]))
							{
								$notification_topics[$key]['prefixes'] = $prefixes[$notification['id']];
							}
						}
					}

					return $notification_topics;
				},
				'params' => [
					$memID,
				],
			],
			'get_count' => [
				'function' => function($memID) use ($smcFunc)
				{
					$request = $smcFunc['db']->query('', '
						SELECT COUNT(*)
						FROM {db_prefix}log_notify AS ln
							INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)
							INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
						WHERE ln.id_member = {int:selected_member}
							AND {query_see_board}
							AND t.approved = {int:is_approved}',
						[
							'selected_member' => $memID,
							'is_approved' => 1,
						]
					);
					list ($totalNotifications) = $smcFunc['db']->fetch_row($request);
					$smcFunc['db']->free_result($request);

					return (int) $totalNotifications;
				},
				'params' => [
					$memID,
				],
			],
			'columns' => [
				'subject' => [
					'header' => [
						'value' => $txt['notifications_topics'],
						'class' => 'lefttext',
					],
					'data' => [
						'function' => function($topic) use ($txt)
						{
							if (!empty($topic['prefixes']))
							{
								$link = '<a href="' . $topic['href'] . '">';
								foreach ($topic['prefixes'] as $prefix)
								{
									$link .= '<span class="' . $prefix['css_class'] . '">' . $prefix['name'] . '</span>';
								}
								$link .= $topic['subject'];
								$link .= '</a>';
							}
							else
							{
								$link = $topic['link'];
							}

							if ($topic['new'])
								$link .= '&nbsp; <a href="' . $topic['new_href'] . '" class="new_posts"></a>';

							$link .= '<br><span class="smalltext"><em>' . $txt['in'] . ' ' . $topic['board_link'] . '</em></span>';

							return $link;
						},
					],
					'sort' => [
						'default' => 'ms.subject',
						'reverse' => 'ms.subject DESC',
					],
				],
				'started_by' => [
					'header' => [
						'value' => $txt['started_by'],
						'class' => 'lefttext',
					],
					'data' => [
						'db' => 'poster_link',
					],
					'sort' => [
						'default' => 'real_name_col',
						'reverse' => 'real_name_col DESC',
					],
				],
				'last_post' => [
					'header' => [
						'value' => $txt['last_post'],
						'class' => 'lefttext',
					],
					'data' => [
						'sprintf' => [
							'format' => '<span class="smalltext">%1$s<br>' . $txt['by'] . ' %2$s</span>',
							'params' => [
								'updated' => false,
								'poster_updated_link' => false,
							],
						],
					],
					'sort' => [
						'default' => 'ml.id_msg DESC',
						'reverse' => 'ml.id_msg',
					],
				],
				'alert' => [
					'header' => [
						'value' => $txt['notify_what_how'],
						'class' => 'lefttext',
					],
					'data' => [
						'function' => function($topic) use ($txt)
						{
							$pref = $topic['notify_pref'];
							$mode = !empty($topic['unwatched']) ? 0 : ($pref & 0x02 ? 3 : ($pref & 0x01 ? 2 : 1));
							return $txt['notify_topic_' . $mode];
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
							'format' => '<input type="checkbox" name="notify_topics[]" value="%1$d">',
							'params' => [
								'id' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => $scripturl . '?action=profile;area=watched_topics',
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
					'value' => '<button type="submit" name="edit_notify_topics" value="edit" class="button">' . $txt['notifications_update'] . '</button>
								<button type="submit" name="remove_notify_topics" value="remove" class="button">' . $txt['notification_remove_pref'] . '</button>',
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
		if (isset($_POST['edit_notify_topics']) && isset($_POST['notify_topics']))
		{
			validateToken($this->get_token_name(), 'post');

			foreach ($_POST['notify_topics'] as $index => $id)
			{
				$_POST['notify_topics'][$index] = (int) $id;
			}

			// Make sure there are no zeros left.
			$_POST['notify_topics'] = array_diff($_POST['notify_topics'], [0]);

			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}log_notify
				WHERE id_topic IN ({array_int:topic_list})
					AND id_member = {int:selected_member}',
				[
					'topic_list' => $_POST['notify_topics'],
					'selected_member' => $memID,
				]
			);
			foreach ($_POST['notify_topics'] as $topic)
			{
				setNotifyPrefs($memID, ['topic_notify_' . $topic => 0]);
			}

			session_flash('success', $context['user']['is_owner'] ? $txt['profile_updated_own'] : sprintf($txt['profile_updated_else'], $cur_profile['member_name']));
		}

		if (isset($_POST['remove_notify_topics']) && !empty($_POST['notify_topics']))
		{
			validateToken($this->get_token_name(), 'post');

			$prefs = [];
			foreach ($_POST['notify_topics'] as $topic)
				$prefs[] = 'topic_notify_' . $topic;
			deleteNotifyPrefs($memID, $prefs);

			session_flash('success', $context['user']['is_owner'] ? $txt['profile_updated_own'] : sprintf($txt['profile_updated_else'], $cur_profile['member_name']));
		}

		redirectexit('action=profile;area=watched_topics;u=' . $memID);
	}
}
