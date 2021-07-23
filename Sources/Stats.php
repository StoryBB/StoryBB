<?php

/**
 * Provide a display for forum statistics
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

/**
 * Display some useful/interesting board statistics.
 *
 * gets all the statistics in order and puts them in.
 * uses the Stats template and language file. (and main sub template.)
 * requires the view_stats permission.
 * accessed from ?action=stats.
 */
function DisplayStats()
{
	global $txt, $scripturl, $modSettings, $context, $smcFunc;

	isAllowedTo('view_stats');
	// Page disabled - redirect them out
	if (empty($modSettings['trackStats']))
		fatal_lang_error('feature_disabled', true);

	if (!empty($_REQUEST['expand']))
	{
		$context['robot_no_index'] = true;

		$month = (int) substr($_REQUEST['expand'], 4);
		$year = (int) substr($_REQUEST['expand'], 0, 4);
		if ($year > 1900 && $year < 2200 && $month >= 1 && $month <= 12)
			$_SESSION['expanded_stats'][$year][] = $month;
	}
	elseif (!empty($_REQUEST['collapse']))
	{
		$context['robot_no_index'] = true;

		$month = (int) substr($_REQUEST['collapse'], 4);
		$year = (int) substr($_REQUEST['collapse'], 0, 4);
		if (!empty($_SESSION['expanded_stats'][$year]))
			$_SESSION['expanded_stats'][$year] = array_diff($_SESSION['expanded_stats'][$year], [$month]);
	}

	// Handle the XMLHttpRequest.
	if (isset($_REQUEST['xml']))
	{
		// Collapsing stats only needs adjustments of the session variables.
		if (!empty($_REQUEST['collapse']))
			obExit(false);

		$context['sub_template'] = 'xml_stats';
		$context['yearly'] = [];

		if (empty($month) || empty($year))
			return;

		getDailyStats('YEAR(date) = {int:year} AND MONTH(date) = {int:month}', ['year' => $year, 'month' => $month]);
		$context['yearly'][$year]['months'][$month]['date'] = [
			'month' => sprintf('%02d', $month),
			'year' => $year,
		];
		return;
	}

	loadLanguage('Stats');
	$context['sub_template'] = 'stats_main';
	loadJavaScriptFile('stats.js', ['default_theme' => true, 'defer' => false], 'sbb_stats');

	// Build the link tree......
	$context['linktree'][] = [
		'url' => $scripturl . '?action=stats',
		'name' => $txt['stats_center']
	];
	$context['page_title'] = $context['forum_name'] . ' - ' . $txt['stats_center'];

	$context['show_member_list'] = allowedTo('view_mlist');

	// Get averages...
	$result = $smcFunc['db']->query('', '
		SELECT
			SUM(posts) AS posts, SUM(topics) AS topics, SUM(registers) AS registers,
			SUM(most_on) AS most_on, MIN(date) AS date, SUM(hits) AS hits
		FROM {db_prefix}log_activity',
		[
		]
	);
	$row = $smcFunc['db']->fetch_assoc($result);
	$smcFunc['db']->free_result($result);

	// This would be the amount of time the forum has been up... in days...
	$total_days_up = ceil((time() - strtotime($row['date'])) / (60 * 60 * 24));

	$context['average_posts'] = comma_format(round($row['posts'] / $total_days_up, 2));
	$context['average_topics'] = comma_format(round($row['topics'] / $total_days_up, 2));
	$context['average_members'] = comma_format(round($row['registers'] / $total_days_up, 2));
	$context['average_online'] = comma_format(round($row['most_on'] / $total_days_up, 2));
	$context['average_hits'] = comma_format(round($row['hits'] / $total_days_up, 2));

	$context['num_hits'] = comma_format($row['hits'], 0);

	// How many users are online now.
	$result = $smcFunc['db']->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_online',
		[
		]
	);
	list ($context['users_online']) = $smcFunc['db']->fetch_row($result);
	$smcFunc['db']->free_result($result);

	// Statistics such as number of boards, categories, etc.
	$result = $smcFunc['db']->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}boards AS b
		WHERE b.redirect = {string:blank_redirect}',
		[
			'blank_redirect' => '',
		]
	);
	list ($context['num_boards']) = $smcFunc['db']->fetch_row($result);
	$smcFunc['db']->free_result($result);

	$result = $smcFunc['db']->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}categories AS c',
		[
		]
	);
	list ($context['num_categories']) = $smcFunc['db']->fetch_row($result);
	$smcFunc['db']->free_result($result);

	// Format the numbers nicely.
	$context['users_online'] = comma_format($context['users_online']);
	$context['num_boards'] = comma_format($context['num_boards']);
	$context['num_categories'] = comma_format($context['num_categories']);

	$context['num_members'] = comma_format($modSettings['totalMembers']);
	$context['num_posts'] = comma_format($modSettings['totalMessages']);
	$context['num_topics'] = comma_format($modSettings['totalTopics']);
	$context['most_members_online'] = [
		'number' => comma_format($modSettings['mostOnline']),
		'date' => timeformat($modSettings['mostDate'])
	];
	$context['latest_member'] = &$context['common_stats']['latest_member'];

	$date = dateformat_ymd(forum_time(false));

	// Members online so far today.
	$result = $smcFunc['db']->query('', '
		SELECT most_on
		FROM {db_prefix}log_activity
		WHERE date = {date:today_date}
		LIMIT 1',
		[
			'today_date' => $date,
		]
	);
	list ($context['online_today']) = $smcFunc['db']->fetch_row($result);
	$smcFunc['db']->free_result($result);

	$context['online_today'] = comma_format((int) $context['online_today']);

	$context['stats_blocks'] = [];

	// Poster top 10.
	$members_result = $smcFunc['db']->query('', '
		SELECT id_member, real_name, posts
		FROM {db_prefix}members
		WHERE posts > {int:no_posts}
		ORDER BY posts DESC
		LIMIT 10',
		[
			'no_posts' => 0,
		]
	);
	$context['stats_blocks']['posters'] = [
		'icon' => 'posters',
		'title' => $txt['top_posters'],
		'data' => [],
	];
	$max_num_posts = 1;
	while ($row_members = $smcFunc['db']->fetch_assoc($members_result))
	{
		$context['stats_blocks']['posters']['data'][] = [
			'name' => $row_members['real_name'],
			'id' => $row_members['id_member'],
			'num' => $row_members['posts'],
			'href' => $scripturl . '?action=profile;u=' . $row_members['id_member'],
			'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row_members['id_member'] . '">' . $row_members['real_name'] . '</a>'
		];

		if ($max_num_posts < $row_members['posts'])
			$max_num_posts = $row_members['posts'];
	}
	$smcFunc['db']->free_result($members_result);

	foreach ($context['stats_blocks']['posters']['data'] as $i => $poster)
	{
		$context['stats_blocks']['posters']['data'][$i]['percent'] = round(($poster['num'] * 100) / $max_num_posts);
		$context['stats_blocks']['posters']['data'][$i]['num'] = comma_format($context['stats_blocks']['posters']['data'][$i]['num']);
	}

	// Board top 10.
	$boards_result = $smcFunc['db']->query('', '
		SELECT id_board, name, num_posts
		FROM {db_prefix}boards AS b
		WHERE {query_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
			AND b.redirect = {string:blank_redirect}
		ORDER BY num_posts DESC
		LIMIT 10',
		[
			'recycle_board' => $modSettings['recycle_board'],
			'blank_redirect' => '',
		]
	);
	$context['stats_blocks']['boards'] = [
		'icon' => 'boards',
		'title' => $txt['top_boards'],
		'data' => [],
	];
	$max_num_posts = 1;
	while ($row_board = $smcFunc['db']->fetch_assoc($boards_result))
	{
		$context['stats_blocks']['boards']['data'][] = [
			'id' => $row_board['id_board'],
			'name' => $row_board['name'],
			'num' => $row_board['num_posts'],
			'href' => $scripturl . '?board=' . $row_board['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row_board['id_board'] . '.0">' . $row_board['name'] . '</a>'
		];

		if ($max_num_posts < $row_board['num_posts'])
			$max_num_posts = $row_board['num_posts'];
	}
	$smcFunc['db']->free_result($boards_result);

	foreach ($context['stats_blocks']['boards']['data'] as $i => $board)
	{
		$context['stats_blocks']['boards']['data'][$i]['percent'] = round(($board['num'] * 100) / $max_num_posts);
		$context['stats_blocks']['boards']['data'][$i]['num'] = comma_format($context['stats_blocks']['boards']['data'][$i]['num']);
	}

	// Are you on a larger forum?  If so, let's try to limit the number of topics we search through.
	if ($modSettings['totalMessages'] > 100000)
	{
		$request = $smcFunc['db']->query('', '
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE num_replies != {int:no_replies}
				AND approved = {int:is_approved}
			ORDER BY num_replies DESC
			LIMIT 100',
			[
				'no_replies' => 0,
				'is_approved' => 1,
			]
		);
		$topic_ids = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$topic_ids[] = $row['id_topic'];
		$smcFunc['db']->free_result($request);
	}
	else
		$topic_ids = [];

	// Topic replies top 10.
	$topic_reply_result = $smcFunc['db']->query('', '
		SELECT m.subject, t.num_replies, t.id_board, t.id_topic, b.name
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . ')
		WHERE {query_see_board}' . (!empty($topic_ids) ? '
			AND t.id_topic IN ({array_int:topic_list})
			AND t.approved = {int:is_approved}' : '') . '
		ORDER BY t.num_replies DESC
		LIMIT 10',
		[
			'topic_list' => $topic_ids,
			'recycle_board' => $modSettings['recycle_board'],
			'is_approved' => 1,
		]
	);
	$context['stats_blocks']['topics_replies'] = [
		'icon' => 'topics_replies',
		'title' => $txt['top_topics_replies'],
		'data' => [],
	];
	$max_num_replies = 1;
	while ($row_topic_reply = $smcFunc['db']->fetch_assoc($topic_reply_result))
	{
		censorText($row_topic_reply['subject']);

		$context['stats_blocks']['topics_replies']['data'][] = [
			'id' => $row_topic_reply['id_topic'],
			'board' => [
				'id' => $row_topic_reply['id_board'],
				'name' => $row_topic_reply['name'],
				'href' => $scripturl . '?board=' . $row_topic_reply['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row_topic_reply['id_board'] . '.0">' . $row_topic_reply['name'] . '</a>'
			],
			'subject' => $row_topic_reply['subject'],
			'num' => $row_topic_reply['num_replies'],
			'href' => $scripturl . '?topic=' . $row_topic_reply['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $row_topic_reply['id_topic'] . '.0">' . $row_topic_reply['subject'] . '</a>'
		];

		if ($max_num_replies < $row_topic_reply['num_replies'])
			$max_num_replies = $row_topic_reply['num_replies'];
	}
	$smcFunc['db']->free_result($topic_reply_result);

	foreach ($context['stats_blocks']['topics_replies']['data'] as $i => $topic)
	{
		$context['stats_blocks']['topics_replies']['data'][$i]['percent'] = round(($topic['num'] * 100) / $max_num_replies);
		$context['stats_blocks']['topics_replies']['data'][$i]['num'] = comma_format($context['stats_blocks']['topics_replies']['data'][$i]['num']);
	}

	// Large forums may need a bit more prodding...
	if ($modSettings['totalMessages'] > 100000)
	{
		$request = $smcFunc['db']->query('', '
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE num_views != {int:no_views}
			ORDER BY num_views DESC
			LIMIT 100',
			[
				'no_views' => 0,
			]
		);
		$topic_ids = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$topic_ids[] = $row['id_topic'];
		$smcFunc['db']->free_result($request);
	}
	else
		$topic_ids = [];

	// Topic views top 10.
	$topic_view_result = $smcFunc['db']->query('', '
		SELECT m.subject, t.num_views, t.id_board, t.id_topic, b.name
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . ')
		WHERE {query_see_board}' . (!empty($topic_ids) ? '
			AND t.id_topic IN ({array_int:topic_list})' : '') . '
			AND t.approved = {int:is_approved}
		ORDER BY t.num_views DESC
		LIMIT 10',
		[
			'topic_list' => $topic_ids,
			'recycle_board' => $modSettings['recycle_board'],
			'is_approved' => 1,
		]
	);
	$context['stats_blocks']['topics_views'] = [
		'icon' => 'topics_views',
		'title' => $txt['top_topics_views'],
		'data' => [],
	];
	$max_num = 1;
	while ($row_topic_views = $smcFunc['db']->fetch_assoc($topic_view_result))
	{
		censorText($row_topic_views['subject']);

		$context['stats_blocks']['topics_views']['data'][] = [
			'id' => $row_topic_views['id_topic'],
			'board' => [
				'id' => $row_topic_views['id_board'],
				'name' => $row_topic_views['name'],
				'href' => $scripturl . '?board=' . $row_topic_views['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row_topic_views['id_board'] . '.0">' . $row_topic_views['name'] . '</a>'
			],
			'subject' => $row_topic_views['subject'],
			'num' => $row_topic_views['num_views'],
			'href' => $scripturl . '?topic=' . $row_topic_views['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $row_topic_views['id_topic'] . '.0">' . $row_topic_views['subject'] . '</a>'
		];

		if ($max_num < $row_topic_views['num_views'])
			$max_num = $row_topic_views['num_views'];
	}
	$smcFunc['db']->free_result($topic_view_result);

	foreach ($context['stats_blocks']['topics_views']['data'] as $i => $topic)
	{
		$context['stats_blocks']['topics_views']['data'][$i]['percent'] = round(($topic['num'] * 100) / $max_num);
		$context['stats_blocks']['topics_views']['data'][$i]['num'] = comma_format($context['stats_blocks']['topics_views']['data'][$i]['num']);
	}

	// Try to cache this when possible, because it's a little unavoidably slow.
	if (($members = cache_get_data('stats_top_starters', 360)) === null)
	{
		$request = $smcFunc['db']->query('', '
			SELECT id_member_started, COUNT(*) AS hits
			FROM {db_prefix}topics' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			WHERE id_board != {int:recycle_board}' : '') . '
			GROUP BY id_member_started
			ORDER BY hits DESC
			LIMIT 20',
			[
				'recycle_board' => $modSettings['recycle_board'],
			]
		);
		$members = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$members[$row['id_member_started']] = $row['hits'];
		$smcFunc['db']->free_result($request);

		cache_put_data('stats_top_starters', $members, 360);
	}

	if (empty($members))
		$members = [0 => 0];

	// Topic poster top 10.
	$members_result = $smcFunc['db']->query('', '
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:member_list})',
		[
			'member_list' => array_keys($members),
		]
	);
	$context['stats_blocks']['starters'] = [
		'icon' => 'starters',
		'title' => $txt['top_starters'],
		'data' => [],
	];
	$max_num = 1;
	while ($row_members = $smcFunc['db']->fetch_assoc($members_result))
	{
		$i = array_search($row_members['id_member'], array_keys($members));
		$context['stats_blocks']['starters']['data'][$i] = [
			'name' => $row_members['real_name'],
			'id' => $row_members['id_member'],
			'num' => $members[$row_members['id_member']],
			'href' => $scripturl . '?action=profile;u=' . $row_members['id_member'],
			'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row_members['id_member'] . '">' . $row_members['real_name'] . '</a>'
		];

		if ($max_num < $members[$row_members['id_member']])
			$max_num = $members[$row_members['id_member']];
	}
	$smcFunc['db']->free_result($members_result);
	uasort($context['stats_blocks']['starters']['data'], function($a, $b) {
		return $b['num'] <=> $a['num'];
	});
	if (count($context['stats_blocks']['starters']['data']) > 10)
	{
		$context['stats_blocks']['starters']['data'] = array_slice($context['stats_blocks']['starters']['data'], 0, 10, true);
	}

	foreach ($context['stats_blocks']['starters']['data'] as $i => $topic)
	{
		$context['stats_blocks']['starters']['data'][$i]['percent'] = round(($topic['num'] * 100) / $max_num);
		$context['stats_blocks']['starters']['data'][$i]['num'] = comma_format($context['stats_blocks']['starters']['data'][$i]['num']);
	}

	// Time online top 10.
	$temp = cache_get_data('stats_total_time_members', 600);
	$members_result = $smcFunc['db']->query('', '
		SELECT id_member, real_name, total_time_logged_in
		FROM {db_prefix}members' . (!empty($temp) ? '
		WHERE id_member IN ({array_int:member_list_cached})' : '') . '
		ORDER BY total_time_logged_in DESC
		LIMIT 20',
		[
			'member_list_cached' => $temp,
		]
	);
	$context['stats_blocks']['time_online'] = [
		'icon' => 'time_online',
		'title' => $txt['top_time_online'],
		'data' => [],
	];
	$temp2 = [];
	$max_time_online = 1;
	while ($row_members = $smcFunc['db']->fetch_assoc($members_result))
	{
		$temp2[] = (int) $row_members['id_member'];
		if (count($context['stats_blocks']['time_online']['data']) >= 10)
			continue;

		// Figure out the days, hours and minutes.
		$timeDays = floor($row_members['total_time_logged_in'] / 86400);
		$timeHours = floor(($row_members['total_time_logged_in'] % 86400) / 3600);

		// Figure out which things to show... (days, hours, minutes, etc.)
		$timelogged = '';
		if ($timeDays > 0)
			$timelogged .= $timeDays . $txt['totalTimeLogged5'];
		if ($timeHours > 0)
			$timelogged .= $timeHours . $txt['totalTimeLogged6'];
		$timelogged .= floor(($row_members['total_time_logged_in'] % 3600) / 60) . $txt['totalTimeLogged7'];

		$context['stats_blocks']['time_online']['data'][] = [
			'id' => $row_members['id_member'],
			'name' => $row_members['real_name'],
			'num' => $timelogged,
			'seconds_online' => $row_members['total_time_logged_in'],
			'href' => $scripturl . '?action=profile;u=' . $row_members['id_member'],
			'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row_members['id_member'] . '">' . $row_members['real_name'] . '</a>'
		];

		if ($max_time_online < $row_members['total_time_logged_in'])
			$max_time_online = $row_members['total_time_logged_in'];
	}
	$smcFunc['db']->free_result($members_result);

	foreach ($context['stats_blocks']['time_online']['data'] as $i => $member)
		$context['stats_blocks']['time_online']['data'][$i]['percent'] = round(($member['seconds_online'] * 100) / $max_time_online);

	// Cache the ones we found for a bit, just so we don't have to look again.
	if ($temp !== $temp2)
		cache_put_data('stats_total_time_members', $temp2, 480);

	// Likes.
	if (!empty($modSettings['enable_likes']))
	{
		// Liked messages top 10.
		$context['stats_blocks']['liked_messages'] = [
			'icon' => 'liked_messages',
			'title' => $txt['top_liked_messages'],
			'data' => [],
		];
		$max_liked_message = 1;
		$liked_messages = $smcFunc['db']->query('', '
			SELECT m.id_msg, m.subject, m.likes, m.id_board, m.id_topic, t.approved
			FROM {db_prefix}messages as m
				INNER JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . ')
			WHERE {query_see_board}
				AND t.approved = {int:is_approved}
			ORDER BY m.likes DESC
			LIMIT 10',
			[
				'recycle_board' => $modSettings['recycle_board'],
				'is_approved' => 1,
			]
		);

		while ($row_liked_message = $smcFunc['db']->fetch_assoc($liked_messages))
		{
			censorText($row_liked_message['subject']);

			$context['stats_blocks']['liked_messages']['data'][] = [
				'id' => $row_liked_message['id_topic'],
				'subject' => $row_liked_message['subject'],
				'num' => $row_liked_message['likes'],
				'href' => $scripturl . '?msg=' . $row_liked_message['id_msg'],
				'link' => '<a href="' . $scripturl . '?msg=' . $row_liked_message['id_msg'] .'">' . $row_liked_message['subject'] . '</a>'
			];

			if ($max_liked_message < $row_liked_message['likes'])
				$max_liked_message = $row_liked_message['likes'];
		}
		$smcFunc['db']->free_result($liked_messages);

		foreach ($context['stats_blocks']['liked_messages']['data'] as $i => $liked_messages)
			$context['stats_blocks']['liked_messages']['data'][$i]['percent'] = round(($liked_messages['num'] * 100) / $max_liked_message);

		// Liked users top 10.
		$context['stats_blocks']['liked_users'] = [
			'icon' => 'liked_users',
			'title' => $txt['top_liked_users'],
			'data' => [],
		];
		$max_liked_users = 1;
		$liked_users = $smcFunc['db']->query('', '
			SELECT m.id_member AS liked_user, COUNT(l.content_id) AS count, mem.real_name
			FROM {db_prefix}user_likes AS l
				INNER JOIN {db_prefix}messages AS m ON (l.content_id = m.id_msg)
				INNER JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)
			WHERE content_type = {literal:msg}
				AND m.id_member > {int:zero}
			GROUP BY m.id_member, mem.real_name
			ORDER BY count DESC
			LIMIT 10',
			[
				'no_posts' => 0,
				'zero' => 0,
			]
		);

		while ($row_liked_users = $smcFunc['db']->fetch_assoc($liked_users))
		{
			$context['stats_blocks']['liked_users']['data'][] = [
				'id' => $row_liked_users['liked_user'],
				'num' => $row_liked_users['count'],
				'href' => $scripturl . '?action=profile;u=' . $row_liked_users['liked_user'],
				'name' => $row_liked_users['real_name'],
				'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row_liked_users['liked_user'] . '">' . $row_liked_users['real_name'] . '</a>',
			];

			if ($max_liked_users < $row_liked_users['count'])
				$max_liked_users = $row_liked_users['count'];
		}

		$smcFunc['db']->free_result($liked_users);

		foreach ($context['stats_blocks']['liked_users']['data'] as $i => $liked_users)
			$context['stats_blocks']['liked_users']['data'][$i]['percent'] = round(($liked_users['num'] * 100) / $max_liked_users);
	}

	// Activity by month.
	$months_result = $smcFunc['db']->query('', '
		SELECT
			YEAR(date) AS stats_year, MONTH(date) AS stats_month, SUM(hits) AS hits, SUM(registers) AS registers, SUM(chars) AS chars, SUM(topics) AS topics, SUM(posts) AS posts, MAX(most_on) AS most_on, COUNT(*) AS num_days
		FROM {db_prefix}log_activity
		GROUP BY stats_year, stats_month',
		[]
	);

	$context['yearly'] = [];
	while ($row_months = $smcFunc['db']->fetch_assoc($months_result))
	{
		$ID_MONTH = $row_months['stats_year'] . sprintf('%02d', $row_months['stats_month']);
		$expanded = !empty($_SESSION['expanded_stats'][$row_months['stats_year']]) && in_array($row_months['stats_month'], $_SESSION['expanded_stats'][$row_months['stats_year']]);

		if (!isset($context['yearly'][$row_months['stats_year']]))
			$context['yearly'][$row_months['stats_year']] = [
				'year' => $row_months['stats_year'],
				'new_topics' => 0,
				'new_posts' => 0,
				'new_members' => 0,
				'new_chars' => 0,
				'most_members_online' => 0,
				'hits' => 0,
				'num_months' => 0,
				'months' => [],
				'expanded' => false,
				'current_year' => $row_months['stats_year'] == date('Y'),
			];

		$context['yearly'][$row_months['stats_year']]['months'][(int) $row_months['stats_month']] = [
			'id' => $ID_MONTH,
			'date' => [
				'month' => sprintf('%02d', $row_months['stats_month']),
				'year' => $row_months['stats_year']
			],
			'href' => $scripturl . '?action=stats;' . ($expanded ? 'collapse' : 'expand') . '=' . $ID_MONTH . '#m' . $ID_MONTH,
			'link' => '<a href="' . $scripturl . '?action=stats;' . ($expanded ? 'collapse' : 'expand') . '=' . $ID_MONTH . '#m' . $ID_MONTH . '">' . $txt['months'][(int) $row_months['stats_month']] . ' ' . $row_months['stats_year'] . '</a>',
			'month' => $txt['months'][(int) $row_months['stats_month']],
			'year' => $row_months['stats_year'],
			'new_topics' => comma_format($row_months['topics']),
			'new_posts' => comma_format($row_months['posts']),
			'new_members' => comma_format($row_months['registers']),
			'new_chars' => comma_format($row_months['chars']),
			'most_members_online' => comma_format($row_months['most_on']),
			'hits' => comma_format($row_months['hits']),
			'num_days' => $row_months['num_days'],
			'days' => [],
			'expanded' => $expanded
		];

		$context['yearly'][$row_months['stats_year']]['new_topics'] += $row_months['topics'];
		$context['yearly'][$row_months['stats_year']]['new_posts'] += $row_months['posts'];
		$context['yearly'][$row_months['stats_year']]['new_members'] += $row_months['registers'];
		$context['yearly'][$row_months['stats_year']]['new_chars'] += $row_months['chars'];
		$context['yearly'][$row_months['stats_year']]['hits'] += $row_months['hits'];
		$context['yearly'][$row_months['stats_year']]['num_months']++;
		$context['yearly'][$row_months['stats_year']]['expanded'] |= $expanded;
		$context['yearly'][$row_months['stats_year']]['most_members_online'] = max($context['yearly'][$row_months['stats_year']]['most_members_online'], $row_months['most_on']);
	}

	krsort($context['yearly']);

	$context['collapsed_years'] = [];
	foreach ($context['yearly'] as $year => $data)
	{
		// This gets rid of the filesort on the query ;).
		krsort($context['yearly'][$year]['months']);

		$context['yearly'][$year]['new_topics'] = comma_format($data['new_topics']);
		$context['yearly'][$year]['new_posts'] = comma_format($data['new_posts']);
		$context['yearly'][$year]['new_members'] = comma_format($data['new_members']);
		$context['yearly'][$year]['new_chars'] = comma_format($data['new_chars']);
		$context['yearly'][$year]['most_members_online'] = comma_format($data['most_members_online']);
		$context['yearly'][$year]['hits'] = comma_format($data['hits']);

		// Keep a list of collapsed years.
		if (!$data['expanded'] && !$data['current_year'])
			$context['collapsed_years'][] = $year;
	}

	if (empty($_SESSION['expanded_stats']))
		return;

	$condition_text = [];
	$condition_params = [];
	foreach ($_SESSION['expanded_stats'] as $year => $months)
		if (!empty($months))
		{
			$condition_text[] = 'YEAR(date) = {int:year_' . $year . '} AND MONTH(date) IN ({array_int:months_' . $year . '})';
			$condition_params['year_' . $year] = $year;
			$condition_params['months_' . $year] = $months;
		}

	// No daily stats to even look at?
	if (empty($condition_text))
		return;

	getDailyStats(implode(' OR ', $condition_text), $condition_params);

	// Custom stats (just add a template_layer to add it to the template!)
	call_integration_hook('integrate_forum_stats');
}

/**
 * Loads the statistics on a daily basis in $context.
 * called by DisplayStats().
 * @param string $condition_string An SQL condition string
 * @param array $condition_parameters Parameters for $condition_string
 */
function getDailyStats($condition_string, $condition_parameters = [])
{
	global $context, $smcFunc;

	// Activity by day.
	$days_result = $smcFunc['db']->query('', '
		SELECT YEAR(date) AS stats_year, MONTH(date) AS stats_month, DAYOFMONTH(date) AS stats_day, topics, posts, chars, registers, most_on, hits
		FROM {db_prefix}log_activity
		WHERE ' . $condition_string . '
		ORDER BY stats_day ASC',
		$condition_parameters
	);
	while ($row_days = $smcFunc['db']->fetch_assoc($days_result))
		$context['yearly'][$row_days['stats_year']]['months'][(int) $row_days['stats_month']]['days'][] = [
			'day' => sprintf('%02d', $row_days['stats_day']),
			'month' => sprintf('%02d', $row_days['stats_month']),
			'year' => $row_days['stats_year'],
			'new_topics' => comma_format($row_days['topics']),
			'new_posts' => comma_format($row_days['posts']),
			'new_chars' => comma_format($row_days['chars']),
			'new_members' => comma_format($row_days['registers']),
			'most_members_online' => comma_format($row_days['most_on']),
			'hits' => comma_format($row_days['hits'])
		];
	$smcFunc['db']->free_result($days_result);
}
