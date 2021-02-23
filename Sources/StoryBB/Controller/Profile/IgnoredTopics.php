<?php

/**
 * Displays the unwatched topics page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Model\TopicPrefix;

class IgnoredTopics extends AbstractProfileController
{
	public function display_action()
	{
		global $txt, $user_info, $scripturl, $modSettings;
		global $context, $user_profile, $sourcedir, $smcFunc, $board;

		require_once($sourcedir . '/Subs-List.php');

		// Some initial context.
		$memID = $this->params['u'];
		$context['start'] = (int) $_REQUEST['start'];
		$context['current_member'] = $memID;

		$context['page_title'] = $txt['showUnwatched'] . ' - ' . $user_profile[$memID]['real_name'];

		check_load_avg('show_posts');

		// And here they are: the topics you don't like
		$listOptions = [
			'id' => 'unwatched_topics',
			'width' => '100%',
			'items_per_page' => (empty($modSettings['disableCustomPerPage']) && !empty($options['topics_per_page'])) ? $options['topics_per_page'] : $modSettings['defaultMaxTopics'],
			'no_items_label' => $txt['unwatched_topics_none'],
			'base_href' => $scripturl . '?action=profile;area=ignored_topics;u=' . $memID,
			'default_sort_col' => 'started_on',
			'get_items' => [
				'function' => function($start, $items_per_page, $sort, $memID) use ($smcFunc)
				{
					// Get the list of topics we can see
					$request = $smcFunc['db']->query('', '
						SELECT lt.id_topic
						FROM {db_prefix}log_topics as lt
							LEFT JOIN {db_prefix}topics as t ON (lt.id_topic = t.id_topic)
							LEFT JOIN {db_prefix}boards as b ON (t.id_board = b.id_board)
							LEFT JOIN {db_prefix}messages as m ON (t.id_first_msg = m.id_msg)' . (in_array($sort, ['mem.real_name', 'mem.real_name DESC', 'mem.poster_time', 'mem.poster_time DESC']) ? '
							LEFT JOIN {db_prefix}members as mem ON (m.id_member = mem.id_member)' : '') . '
						WHERE lt.id_member = {int:current_member}
							AND unwatched = 1
							AND {query_see_board}
						ORDER BY {raw:sort}
						LIMIT {int:offset}, {int:limit}',
						[
							'current_member' => $memID,
							'sort' => $sort,
							'offset' => $start,
							'limit' => $items_per_page,
						]
					);

					$topics = [];
					while ($row = $smcFunc['db']->fetch_assoc($request))
						$topics[] = $row['id_topic'];

					$smcFunc['db']->free_result($request);

					// Any topics found?
					$topicsInfo = [];
					if (!empty($topics))
					{
						$request = $smcFunc['db']->query('', '
							SELECT mf.subject, mf.poster_time as started_on, COALESCE(memf.real_name, mf.poster_name) as started_by, ml.poster_time as last_post_on, COALESCE(meml.real_name, ml.poster_name) as last_post_by, t.id_topic
							FROM {db_prefix}topics AS t
								INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
								INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
								LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
								LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
							WHERE t.id_topic IN ({array_int:topics})',
							[
								'topics' => $topics,
							]
						);
						while ($row = $smcFunc['db']->fetch_assoc($request))
							$topicsInfo[] = $row;
						$smcFunc['db']->free_result($request);

						$prefixes = TopicPrefix::get_prefixes_for_topic_list($topics);
						foreach ($topicsInfo as $key => $ignored_topic)
						{
							$topicsInfo[$key]['prefixes'] = [];
							if (isset($prefixes[$ignored_topic['id_topic']]))
							{
								$topicsInfo[$key]['prefixes'] = $prefixes[$ignored_topic['id_topic']];
							}
						}
					}

					return $topicsInfo;
				},
				'params' => [
					$memID,
				],
			],
			'get_count' => [
				'function' => function($memID) use ($smcFunc)
				{
					// Get the total number of attachments they have posted.
					$request = $smcFunc['db']->query('', '
						SELECT COUNT(*)
						FROM {db_prefix}log_topics as lt
						LEFT JOIN {db_prefix}topics as t ON (lt.id_topic = t.id_topic)
						LEFT JOIN {db_prefix}boards as b ON (t.id_board = b.id_board)
						WHERE id_member = {int:current_member}
							AND unwatched = 1
							AND {query_see_board}',
						[
							'current_member' => $memID,
						]
					);
					list ($unwatchedCount) = $smcFunc['db']->fetch_row($request);
					$smcFunc['db']->free_result($request);

					return $unwatchedCount;
				},
				'params' => [
					$memID,
				],
			],
			'columns' => [
				'subject' => [
					'header' => [
						'value' => $txt['subject'],
						'class' => 'lefttext',
						'style' => 'width: 30%;',
					],
					'data' => [
						'function' => function($topic) use ($txt, $scripturl)
						{
							$link = '<a href="' . $scripturl . '?topic=' . $topic['id_topic'] . '.0">';
							foreach ($topic['prefixes'] as $prefix)
							{
								$link .= '<span class="' . $prefix['css_class'] . '">' . $prefix['name'] . '</span>';
							}
							$link .= $topic['subject'];
							$link .= '</a>';

							return $link;
						},
					],
					'sort' => [
						'default' => 'm.subject',
						'reverse' => 'm.subject DESC',
					],
				],
				'started_by' => [
					'header' => [
						'value' => $txt['started_by'],
						'style' => 'width: 15%;',
					],
					'data' => [
						'db' => 'started_by',
					],
					'sort' => [
						'default' => 'mem.real_name',
						'reverse' => 'mem.real_name DESC',
					],
				],
				'started_on' => [
					'header' => [
						'value' => $txt['on'],
						'class' => 'lefttext',
						'style' => 'width: 20%;',
					],
					'data' => [
						'db' => 'started_on',
						'timeformat' => true,
					],
					'sort' => [
						'default' => 'm.poster_time',
						'reverse' => 'm.poster_time DESC',
					],
				],
				'last_post_by' => [
					'header' => [
						'value' => $txt['last_post'],
						'style' => 'width: 15%;',
					],
					'data' => [
						'db' => 'last_post_by',
					],
					'sort' => [
						'default' => 'mem.real_name',
						'reverse' => 'mem.real_name DESC',
					],
				],
				'last_post_on' => [
					'header' => [
						'value' => $txt['on'],
						'class' => 'lefttext',
						'style' => 'width: 20%;',
					],
					'data' => [
						'db' => 'last_post_on',
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

		$context['sub_template'] = 'generic_list_page';
		$context['default_list'] = 'unwatched_topics';
	}
}
