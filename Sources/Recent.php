<?php

/**
 * Find and retrieve information about recently posted topics, messages, and the like.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\App;
use StoryBB\Helper\Parser;
use StoryBB\StringLibrary;
use StoryBB\Model\TopicPrefix;

/**
 * Get the latest post made on the system
 *
 * - respects approved, recycled, and board permissions
 * - @todo is this even used anywhere?
 *
 * @return array An array of information about the last post that you can see
 */
function getLastPost()
{
	global $scripturl, $modSettings, $smcFunc;

	// Find it by the board - better to order by board than sort the entire messages table.
	$request = $smcFunc['db']->query('substring', '
		SELECT ml.poster_time, ml.subject, ml.id_topic, ml.poster_name, SUBSTRING(ml.body, 1, 385) AS body,
			ml.smileys_enabled
		FROM {db_prefix}boards AS b
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = b.id_last_msg)
		WHERE {query_wanna_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
			AND ml.approved = {int:is_approved}
		ORDER BY b.id_msg_updated DESC
		LIMIT 1',
		[
			'recycle_board' => $modSettings['recycle_board'],
			'is_approved' => 1,
		]
	);
	if ($smcFunc['db']->num_rows($request) == 0)
		return [];
	$row = $smcFunc['db']->fetch_assoc($request);
	$smcFunc['db']->free_result($request);

	// Censor the subject and post...
	censorText($row['subject']);
	censorText($row['body']);

	$row['body'] = strip_tags(strtr(Parser::parse_bbc($row['body'], $row['smileys_enabled']), ['<br>' => '&#10;']));
	if (StringLibrary::strpos($row['body']) > 128)
		$row['body'] = StringLibrary::substr($row['body'], 0, 128) . '...';

	// Send the data.
	return [
		'topic' => $row['id_topic'],
		'subject' => $row['subject'],
		'short_subject' => shorten_subject($row['subject'], 24),
		'preview' => $row['body'],
		'time' => timeformat($row['poster_time']),
		'timestamp' => forum_time(true, $row['poster_time']),
		'href' => $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new',
		'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new">' . $row['subject'] . '</a>'
	];
}

/**
 * Find unread topics and replies.
 */
function UnreadTopics()
{
	global $board, $txt, $scripturl, $sourcedir;
	global $user_info, $context, $settings, $modSettings, $smcFunc, $options;

	$url = App::container()->get('urlgenerator');

	// Guests can't have unread things, we don't know anything about them.
	is_not_guest();

	// Prefetching + lots of MySQL work = bad mojo.
	if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
	{
		ob_end_clean();
		header('HTTP/1.1 403 Forbidden');
		die;
	}

	$context['start'] = (int) $_REQUEST['start'];
	$context['topics_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['topics_per_page']) ? $options['topics_per_page'] : $modSettings['defaultMaxTopics'];
	$context['page_title'] = $txt['unread_topics'];

	if (!empty($context['load_average']) && !empty($modSettings['loadavg_unread']) && $context['load_average'] >= $modSettings['loadavg_unread'])
		fatal_lang_error('loadavg_unread_disabled', false);

	// Parameters for the main query.
	$query_parameters = [];

	// Are we specifying any specific board?
	if (isset($_REQUEST['children']) && (!empty($board) || !empty($_REQUEST['boards'])))
	{
		$boards = [];

		if (!empty($_REQUEST['boards']))
		{
			$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
			foreach ($_REQUEST['boards'] as $b)
				$boards[] = (int) $b;
		}

		if (!empty($board))
			$boards[] = (int) $board;

		// The easiest thing is to just get all the boards they can see, but since we've specified the top of tree we ignore some of them
		$request = $smcFunc['db']->query('', '
			SELECT b.id_board, b.id_parent
			FROM {db_prefix}boards AS b
			WHERE {query_wanna_see_board}
				AND b.child_level > {int:no_child}
				AND b.id_board NOT IN ({array_int:boards})
			ORDER BY child_level ASC
			',
			[
				'no_child' => 0,
				'boards' => $boards,
			]
		);

		while ($row = $smcFunc['db']->fetch_assoc($request))
			if (in_array($row['id_parent'], $boards))
				$boards[] = $row['id_board'];

		$smcFunc['db']->free_result($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;
		$context['querystring_board_limits'] = ';boards=' . implode(',', $boards) . ';start=%d';
	}
	elseif (!empty($board))
	{
		$query_this_board = 'id_board = {int:board}';
		$query_parameters['board'] = $board;
		$context['querystring_board_limits'] = ';board=' . $board . '.%1$d';
	}
	elseif (!empty($_REQUEST['boards']))
	{
		$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
		foreach ($_REQUEST['boards'] as $i => $b)
			$_REQUEST['boards'][$i] = (int) $b;

		$request = $smcFunc['db']->query('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}
				AND b.id_board IN ({array_int:board_list})',
			[
				'board_list' => $_REQUEST['boards'],
			]
		);
		$boards = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$boards[] = $row['id_board'];
		$smcFunc['db']->free_result($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;
		$context['querystring_board_limits'] = ';boards=' . implode(',', $boards) . ';start=%1$d';
	}
	elseif (!empty($_REQUEST['c']))
	{
		$_REQUEST['c'] = explode(',', $_REQUEST['c']);
		foreach ($_REQUEST['c'] as $i => $c)
			$_REQUEST['c'][$i] = (int) $c;

		$request = $smcFunc['db']->query('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {query_wanna_see_board}
				AND b.id_cat IN ({array_int:id_cat})',
			[
				'id_cat' => $_REQUEST['c'],
			]
		);
		$boards = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$boards[] = $row['id_board'];
		$smcFunc['db']->free_result($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;
		$context['querystring_board_limits'] = ';c=' . implode(',', $_REQUEST['c']) . ';start=%1$d';
	}
	else
	{
		// Don't bother to show deleted posts!
		$request = $smcFunc['db']->query('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {query_wanna_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
				AND b.id_board != {int:recycle_board}' : ''),
			[
				'recycle_board' => (int) $modSettings['recycle_board'],
			]
		);
		$boards = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$boards[] = $row['id_board'];
		$smcFunc['db']->free_result($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_available', false);

		$query_this_board = 'id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;
		$context['querystring_board_limits'] = ';start=%1$d';
		$context['no_board_limits'] = true;
	}

	$sort_methods = [
		'subject' => 'ms.subject',
		'starter' => 'COALESCE(mems.real_name, ms.poster_name)',
		'replies' => 't.num_replies',
		'views' => 't.num_views',
		'first_post' => 't.id_topic',
		'last_post' => 't.id_last_msg'
	];

	// The default is the most logical: newest first.
	if (!isset($_REQUEST['sort']) || !isset($sort_methods[$_REQUEST['sort']]))
	{
		$context['sort_by'] = 'last_post';
		$_REQUEST['sort'] = 't.id_last_msg';
		$ascending = isset($_REQUEST['asc']);

		$context['querystring_sort_limits'] = $ascending ? ';asc' : '';
	}
	// But, for other methods the default sort is ascending.
	else
	{
		$context['sort_by'] = $_REQUEST['sort'];
		$_REQUEST['sort'] = $sort_methods[$_REQUEST['sort']];
		$ascending = !isset($_REQUEST['desc']);

		$context['querystring_sort_limits'] = ';sort=' . $context['sort_by'] . ($ascending ? '' : ';desc');
	}
	$context['sort_direction'] = $ascending ? 'up' : 'down';

	if (!empty($_REQUEST['c']) && is_array($_REQUEST['c']) && count($_REQUEST['c']) == 1)
	{
		$request = $smcFunc['db']->query('', '
			SELECT name
			FROM {db_prefix}categories
			WHERE id_cat = {int:id_cat}
			LIMIT 1',
			[
				'id_cat' => (int) $_REQUEST['c'][0],
			]
		);
		list ($name) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		$context['linktree'][] = [
			'url' => $scripturl . '#c' . (int) $_REQUEST['c'][0],
			'name' => $name
		];
	}

	$context['linktree'][] = [
		'url' => $scripturl . '?action=' . $_REQUEST['action'] . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'],
		'name' => $txt['unread_topics'],
	];

	$context['sub_template'] = 'unread_posts';

	$is_topics = true;

	// This part is the same for each query.
	$select_clause = '
				ms.subject AS first_subject, ms.poster_time AS first_poster_time, ms.id_topic, t.id_board, b.name AS bname, b.slug AS board_slug,
				t.num_replies, t.num_views, ms.id_member AS id_first_member, ml.id_member AS id_last_member, charsl.avatar, meml.email_address, charss.avatar AS first_poster_avatar, mems.email_address AS first_poster_email, COALESCE(af.id_attach, 0) AS first_poster_id_attach, af.filename AS first_poster_filename, af.attachment_type AS first_poster_attach_type, COALESCE(al.id_attach, 0) AS last_poster_id_attach, al.filename AS last_poster_filename, al.attachment_type AS last_poster_attach_type,
				ml.poster_time AS last_poster_time, COALESCE(charss.character_name, mems.real_name, ms.poster_name) AS first_poster_name,
				COALESCE(charsl.character_name, meml.real_name, ml.poster_name) AS last_poster_name,
				charss.id_character AS first_character_id, charsl.id_character AS last_character_id, ml.subject AS last_subject,
				t.id_poll, t.is_sticky, t.locked, ml.modified_time AS last_modified_time, b.in_character,
				COALESCE(lt.id_msg, lmr.id_msg, -1) + 1 AS new_from, SUBSTRING(ml.body, 1, 385) AS last_body,
				SUBSTRING(ms.body, 1, 385) AS first_body, ml.smileys_enabled AS last_smileys, ms.smileys_enabled AS first_smileys, t.id_first_msg, t.id_last_msg';


	if (!empty($board))
	{
		$request = $smcFunc['db']->query('', '
			SELECT MIN(id_msg)
			FROM {db_prefix}log_mark_read
			WHERE id_member = {int:current_member}
				AND id_board = {int:current_board}',
			[
				'current_board' => $board,
				'current_member' => $user_info['id'],
			]
		);
		list ($earliest_msg) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);
	}
	else
	{
		$request = $smcFunc['db']->query('', '
			SELECT MIN(lmr.id_msg)
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})
			WHERE {query_see_board}',
			[
				'current_member' => $user_info['id'],
			]
		);
		list ($earliest_msg) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);
	}

	// This is needed in case of topics marked unread.
	if (empty($earliest_msg))
		$earliest_msg = 0;
	else
	{
		// Using caching, when possible, to ignore the below slow query.
		if (isset($_SESSION['cached_log_time']) && $_SESSION['cached_log_time'][0] + 45 > time())
			$earliest_msg2 = $_SESSION['cached_log_time'][1];
		else
		{
			// This query is pretty slow, but it's needed to ensure nothing crucial is ignored.
			$request = $smcFunc['db']->query('', '
				SELECT MIN(id_msg)
				FROM {db_prefix}log_topics
				WHERE id_member = {int:current_member}',
				[
					'current_member' => $user_info['id'],
				]
			);
			list ($earliest_msg2) = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);

			// In theory this could be zero, if the first ever post is unread, so fudge it ;)
			if ($earliest_msg2 == 0)
				$earliest_msg2 = -1;

			$_SESSION['cached_log_time'] = [time(), $earliest_msg2];
		}

		$earliest_msg = min($earliest_msg2, $earliest_msg);
	}


	// @todo Add modified_time in for log_time check?

	if ($modSettings['totalMessages'] > 100000)
	{
		$smcFunc['db']->query('', '
			DROP TABLE IF EXISTS {db_prefix}log_topics_unread',
			[]
		);

		// Let's copy things out of the log_topics table, to reduce searching.
		$have_temp_table = $smcFunc['db']->query('', '
			CREATE TEMPORARY TABLE {db_prefix}log_topics_unread (
				PRIMARY KEY (id_topic)
			)
			SELECT lt.id_topic, lt.id_msg
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic)
			WHERE lt.id_member = {int:current_member}
				AND t.' . $query_this_board . (empty($earliest_msg) ? '' : '
				AND t.id_last_msg > {int:earliest_msg}') . '
				AND t.approved = {int:is_approved}
				AND lt.unwatched != 1',
			array_merge($query_parameters, [
				'current_member' => $user_info['id'],
				'earliest_msg' => !empty($earliest_msg) ? $earliest_msg : 0,
				'is_approved' => 1,
				'db_error_skip' => true,
			])
		) !== false;
	}
	else
		$have_temp_table = false;

	if ($have_temp_table)
	{
		$request = $smcFunc['db']->query('', '
			SELECT COUNT(*), MIN(t.id_last_msg)
			FROM {db_prefix}topics AS t
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.' . $query_this_board . (!empty($earliest_msg) ? '
				AND t.id_last_msg > {int:earliest_msg}' : '') . '
				AND COALESCE(lt.id_msg, lmr.id_msg, 0) < t.id_last_msg
				AND t.approved = {int:is_approved}',
			array_merge($query_parameters, [
				'current_member' => $user_info['id'],
				'earliest_msg' => !empty($earliest_msg) ? $earliest_msg : 0,
				'is_approved' => 1,
			])
		);
		list ($num_topics, $min_message) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		// Make sure the starting place makes sense and construct the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=unread' . $context['querystring_board_limits'] . $context['querystring_sort_limits'], $_REQUEST['start'], $num_topics, $context['topics_per_page'], true);
		$context['current_page'] = (int) $_REQUEST['start'] / $context['topics_per_page'];

		$context['links'] = [
			'first' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=unread' . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'] : '',
			'prev' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=unread' . sprintf($context['querystring_board_limits'], $_REQUEST['start'] - $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'next' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=unread' . sprintf($context['querystring_board_limits'], $_REQUEST['start'] + $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'last' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=unread' . sprintf($context['querystring_board_limits'], floor(($num_topics - 1) / $context['topics_per_page']) * $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'up' => $scripturl,
		];
		$context['page_info'] = [
			'current_page' => $_REQUEST['start'] / $context['topics_per_page'] + 1,
			'num_pages' => floor(($num_topics - 1) / $context['topics_per_page']) + 1
		];

		if ($num_topics == 0)
		{
			// Mark the boards as read if there are no unread topics!
			require_once($sourcedir . '/Subs-Boards.php');
			markBoardsRead(empty($boards) ? $board : $boards);

			$context['topics'] = [];
			$context['no_topic_listing'] = true;
			if ($context['querystring_board_limits'] == ';start=%1$d')
				$context['querystring_board_limits'] = '';
			else
				$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
			return;
		}
		else
			$min_message = (int) $min_message;

		$request = $smcFunc['db']->query('substring', '
			SELECT ' . $select_clause . '
			FROM {db_prefix}messages AS ms
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ms.id_topic AND t.id_first_msg = ms.id_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = ms.id_board)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}attachments AS af ON (af.id_character = ms.id_character AND af.attachment_type = 1)
				LEFT JOIN {db_prefix}attachments AS al ON (al.id_character = ml.id_character AND al.attachment_type = 1)
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
				LEFT JOIN {db_prefix}characters AS charss ON (charss.id_character = ms.id_character)
				LEFT JOIN {db_prefix}characters AS charsl ON (charsl.id_character = ml.id_character)
			WHERE b.' . $query_this_board . '
				AND t.id_last_msg >= {int:min_message}
				AND COALESCE(lt.id_msg, lmr.id_msg, 0) < t.id_last_msg
				AND ms.approved = {int:is_approved}
			ORDER BY b.in_character, {raw:sort}
			LIMIT {int:offset}, {int:limit}',
			array_merge($query_parameters, [
				'current_member' => $user_info['id'],
				'min_message' => $min_message,
				'is_approved' => 1,
				'sort' => $_REQUEST['sort'] . ($ascending ? '' : ' DESC'),
				'offset' => $_REQUEST['start'],
				'limit' => $context['topics_per_page'],
			])
		);
	}
	else
	{
		$request = $smcFunc['db']->query('', '
			SELECT COUNT(*), MIN(t.id_last_msg)
			FROM {db_prefix}topics AS t
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member} AND lt.unwatched != 1)
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.' . $query_this_board . (!empty($earliest_msg) ? '
				AND t.id_last_msg > {int:earliest_msg}' : '') . '
				AND COALESCE(lt.id_msg, lmr.id_msg, 0) < t.id_last_msg
				AND t.approved = {int:is_approved}',
			array_merge($query_parameters, [
				'current_member' => $user_info['id'],
				'earliest_msg' => !empty($earliest_msg) ? $earliest_msg : 0,
				'is_approved' => 1,
			])
		);
		list ($num_topics, $min_message) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		// Make sure the starting place makes sense and construct the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=unread' . $context['querystring_board_limits'] . $context['querystring_sort_limits'], $_REQUEST['start'], $num_topics, $context['topics_per_page'], true);
		$context['current_page'] = (int) $_REQUEST['start'] / $context['topics_per_page'];

		$context['links'] = [
			'first' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=unread' . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'] : '',
			'prev' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=unread' . sprintf($context['querystring_board_limits'], $_REQUEST['start'] - $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'next' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=unread' . sprintf($context['querystring_board_limits'], $_REQUEST['start'] + $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'last' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=unread' . sprintf($context['querystring_board_limits'], floor(($num_topics - 1) / $context['topics_per_page']) * $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'up' => $scripturl,
		];
		$context['page_info'] = [
			'current_page' => $_REQUEST['start'] / $context['topics_per_page'] + 1,
			'num_pages' => floor(($num_topics - 1) / $context['topics_per_page']) + 1
		];

		if ($num_topics == 0)
		{
			// Since there are no unread topics, mark the boards as read!
			require_once($sourcedir . '/Subs-Boards.php');
			markBoardsRead(empty($boards) ? $board : $boards);

			$context['topics'] = [];
			$context['no_topic_listing'] = true;
			if ($context['querystring_board_limits'] == ';start=%d')
				$context['querystring_board_limits'] = '';
			else
				$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
			return;
		}
		else
			$min_message = (int) $min_message;

		$request = $smcFunc['db']->query('substring', '
			SELECT ' . $select_clause . '
			FROM {db_prefix}messages AS ms
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ms.id_topic AND t.id_first_msg = ms.id_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}attachments AS af ON (af.id_character = ms.id_character AND af.attachment_type = 1)
				LEFT JOIN {db_prefix}attachments AS al ON (al.id_character = ml.id_character AND al.attachment_type = 1)' . (!empty($have_temp_table) ? '
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member} AND lt.unwatched != 1)') . '
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
				LEFT JOIN {db_prefix}characters AS charss ON (charss.id_character = ms.id_character)
				LEFT JOIN {db_prefix}characters AS charsl ON (charsl.id_character = ml.id_character)
			WHERE t.' . $query_this_board . '
				AND t.id_last_msg >= {int:min_message}
				AND COALESCE(lt.id_msg, lmr.id_msg, 0) < ml.id_msg
				AND ms.approved = {int:is_approved}
			ORDER BY b.in_character, {raw:order}
			LIMIT {int:offset}, {int:limit}',
			array_merge($query_parameters, [
				'current_member' => $user_info['id'],
				'min_message' => $min_message,
				'is_approved' => 1,
				'order' => $_REQUEST['sort'] . ($ascending ? '' : ' DESC'),
				'offset' => $_REQUEST['start'],
				'limit' => $context['topics_per_page'],
			])
		);
	}

	$context['topics'] = [];
	$topic_ids = [];
	$recycle_board = !empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']) ? $modSettings['recycle_board'] : 0;
	$last_in_character = -1;

	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		if ($row['id_poll'] > 0 && $modSettings['pollMode'] == '0')
			continue;

		$topic_ids[] = $row['id_topic'];

		if (!empty($modSettings['preview_characters']))
		{
			// Limit them to 128 characters - do this FIRST because it's a lot of wasted censoring otherwise.
			$row['first_body'] = strip_tags(strtr(Parser::parse_bbc($row['first_body'], $row['first_smileys'], $row['id_first_msg']), ['<br>' => '&#10;']));
			if (StringLibrary::strpos($row['first_body']) > 128)
				$row['first_body'] = StringLibrary::substr($row['first_body'], 0, 128) . '...';
			$row['last_body'] = strip_tags(strtr(Parser::parse_bbc($row['last_body'], $row['last_smileys'], $row['id_last_msg']), ['<br>' => '&#10;']));
			if (StringLibrary::strpos($row['last_body']) > 128)
				$row['last_body'] = StringLibrary::substr($row['last_body'], 0, 128) . '...';

			// Censor the subject and message preview.
			censorText($row['first_subject']);
			censorText($row['first_body']);

			// Don't censor them twice!
			if ($row['id_first_msg'] == $row['id_last_msg'])
			{
				$row['last_subject'] = $row['first_subject'];
				$row['last_body'] = $row['first_body'];
			}
			else
			{
				censorText($row['last_subject']);
				censorText($row['last_body']);
			}
		}
		else
		{
			$row['first_body'] = '';
			$row['last_body'] = '';
			censorText($row['first_subject']);

			if ($row['id_first_msg'] == $row['id_last_msg'])
				$row['last_subject'] = $row['first_subject'];
			else
				censorText($row['last_subject']);
		}

		// Decide how many pages the topic should have.
		$topic_length = $row['num_replies'] + 1;
		$messages_per_page = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];
		if ($topic_length > $messages_per_page)
		{
			$start = -1;
			$pages = constructPageIndex($scripturl . '?topic=' . $row['id_topic'] . '.%1$d', $start, $topic_length, $messages_per_page, true, false);

			// If we can use all, show all.
			if (!empty($modSettings['enableAllMessages']) && $topic_length < $modSettings['enableAllMessages'])
				$pages .= ' &nbsp;<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0;all">' . $txt['all'] . '</a>';
		}

		else
			$pages = '';

		// Reference the main color class.
		$colorClass = 'windowbg';

		// Sticky topics should get a different color, too.
		if ($row['is_sticky'])
			$colorClass .= ' sticky';

		// Locked topics get special treatment as well.
		if ($row['locked'])
			$colorClass .= ' locked';

		// And build the array.
		$context['topics'][$row['id_topic']] = [
			'id' => $row['id_topic'],
			'first_post' => [
				'id' => $row['id_first_msg'],
				'member' => [
					'name' => $row['first_poster_name'],
					'id' => $row['id_first_member'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_first_member'] . (!empty($row['first_character_id']) ? ';area=characters;char=' . $row['first_character_id'] : ''),
					'link' => !empty($row['id_first_member']) ? '<a class="preview" href="' . $scripturl . '?action=profile;u=' . $row['id_first_member'] . (!empty($row['first_character_id']) ? ';area=characters;char=' . $row['first_character_id'] : '') . '" title="' . $txt['profile_of'] . ' ' . $row['first_poster_name'] . '">' . $row['first_poster_name'] . '</a>' : $row['first_poster_name']
				],
				'time' => timeformat($row['first_poster_time']),
				'timestamp' => forum_time(true, $row['first_poster_time']),
				'subject' => $row['first_subject'],
				'preview' => $row['first_body'],
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0;topicseen',
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0;topicseen">' . $row['first_subject'] . '</a>'
			],
			'last_post' => [
				'id' => $row['id_last_msg'],
				'member' => [
					'name' => $row['last_poster_name'],
					'id' => $row['id_last_member'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_last_member'] . (!empty($row['last_character_id']) ? ';area=characters;char=' . $row['last_character_id'] : ''),
					'link' => !empty($row['id_last_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_last_member'] . (!empty($row['last_character_id']) ? ';area=characters;char=' . $row['last_character_id'] : '') . '">' . $row['last_poster_name'] . '</a>' : $row['last_poster_name']
				],
				'time' => timeformat($row['last_poster_time']),
				'timestamp' => forum_time(true, $row['last_poster_time']),
				'subject' => $row['last_subject'],
				'preview' => $row['last_body'],
				'href' => $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . ';topicseen#msg' . $row['id_last_msg'],
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . ';topicseen#msg' . $row['id_last_msg'] . '" rel="nofollow">' . $row['last_subject'] . '</a>'
			],
			'new_from' => $row['new_from'],
			'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . ';topicseen#new',
			'href' => $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['new_from']) . ';topicseen' . ($row['num_replies'] == 0 ? '' : 'new'),
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['new_from']) . ';topicseen#msg' . $row['new_from'] . '" rel="nofollow">' . $row['first_subject'] . '</a>',
			'is_sticky' => !empty($row['is_sticky']),
			'is_locked' => !empty($row['locked']),
			'css_class' => $colorClass,
			'is_poll' => $modSettings['pollMode'] == '1' && $row['id_poll'] > 0,
			'is_posted_in' => false,
			'subject' => $row['first_subject'],
			'pages' => $pages,
			'replies' => comma_format($row['num_replies']),
			'views' => comma_format($row['num_views']),
			'board' => [
				'id' => $row['id_board'],
				'name' => $row['bname'],
				'href' => $url->generate('board', ['board_slug' => $row['board_slug']]),
				'link' => '<a href="' . $url->generate('board', ['board_slug' => $row['board_slug']]) . '">' . $row['bname'] . '</a>'
			],
			'prefixes' => [],
		];

		if ($last_in_character != $row['in_character'])
		{
			$last_in_character = $row['in_character'];
			if (!$row['in_character'])
			{
				$context['topics'][$row['id_topic']]['ooc_divider'] = true;
			}
			else
			{
				$context['topics'][$row['id_topic']]['ic_divider'] = true;
			}
		}

		$context['topics'][$row['id_topic']]['last_post']['member']['avatar'] = set_avatar_data([
			'avatar' => $row['avatar'],
			'email' => $row['email_address'],
			'filename' => $row['last_poster_filename'],
		]);

		$context['topics'][$row['id_topic']]['first_post']['member']['avatar'] = set_avatar_data([
			'avatar' => $row['first_poster_avatar'],
			'email' => $row['first_poster_email'],
			'filename' => $row['first_poster_filename'],
		]);

		$context['topics'][$row['id_topic']]['first_post']['started_by'] = sprintf($txt['topic_started_by'], $context['topics'][$row['id_topic']]['first_post']['member']['link'], $context['topics'][$row['id_topic']]['board']['link']);
	}
	$smcFunc['db']->free_result($request);

	if (!empty($topic_ids))
	{
		$result = $smcFunc['db']->query('', '
			SELECT id_topic
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member = {int:current_member}
			GROUP BY id_topic
			LIMIT {int:limit}',
			[
				'current_member' => $user_info['id'],
				'topic_list' => $topic_ids,
				'limit' => count($topic_ids),
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			if (empty($context['topics'][$row['id_topic']]['is_posted_in']))
				$context['topics'][$row['id_topic']]['is_posted_in'] = true;
		}
		$smcFunc['db']->free_result($result);

		$prefixes = TopicPrefix::get_prefixes_for_topic_list($topic_ids);
		foreach ($prefixes as $id_topic => $topic_prefixes)
		{
			if (isset($context['topics'][$id_topic]))
			{
				$context['topics'][$id_topic]['prefixes'] = $topic_prefixes;
			}
		}
	}

	$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
	$context['topics_to_mark'] = implode('-', $topic_ids);

	// Build the recent button array.
	$context['recent_buttons'] = [
		'markread' => ['text' => !empty($context['no_board_limits']) ? 'mark_as_read' : 'mark_read_short', 'image' => 'markread.png', 'custom' => 'data-confirm="'.  $txt['are_sure_mark_read'] .'"', 'class' => 'you_sure', 'url' => $scripturl . '?action=markasread;sa=' . (!empty($context['no_board_limits']) ? 'all' : 'board' . $context['querystring_board_limits']) . ';' . $context['session_var'] . '=' . $context['session_id']],
	];

	$context['recent_buttons']['markselectread'] = [
		'text' => 'quick_mod_markread',
		'image' => 'markselectedread.png',
		'url' => 'javascript:document.quickModForm.submit();',
	];

	// Allow mods to add additional buttons here
	call_integration_hook('integrate_recent_buttons');

	$context['no_topic_listing'] = empty($context['topics']);

	// Allow helpdesks and bug trackers and what not to add their own unread data (just add a template_layer to show custom stuff in the template!)
	call_integration_hook('integrate_unread_list');
}
