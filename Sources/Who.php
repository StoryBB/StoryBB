<?php

/**
 * This file is mainly concerned with the Who's Online list.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Helper\IP;
use StoryBB\StringLibrary;

/**
 * Who's online, and what are they doing?
 * This function prepares the who's online data for the Who template.
 * It requires the who_view permission.
 * It is enabled with the who_enabled setting.
 * It is accessed via ?action=who.
 *
 * @uses Who template, main sub-template
 * @uses Who language file.
 */
function Who()
{
	global $context, $scripturl, $txt, $modSettings, $memberContext, $smcFunc;

	// Permissions, permissions, permissions.
	isAllowedTo('who_view');

	// You can't do anything if this is off.
	if (empty($modSettings['who_enabled']))
		fatal_lang_error('who_off', false);

	// Load the 'Who' template.
	$context['sub_template'] = 'whosonline';
	loadLanguage('Who');

	// Sort out... the column sorting.
	$sort_methods = [
		'user' => 'mem.real_name',
		'time' => 'lo.log_time'
	];

	$show_methods = [
		'members' => '(lo.id_member != 0)',
		'guests' => '(lo.id_member = 0 AND lo.robot_name = {empty})',
		'all' => '1=1',
		'robots' => '(lo.robot_name != {empty})',
	];

	// Store the sort methods and the show types for use in the template.
	$context['sort_methods'] = [
		'user' => $txt['who_user'],
		'time' => $txt['who_time'],
	];
	$context['show_methods'] = [
		'all' => $txt['who_show_all'],
		'members' => $txt['who_show_members_only'],
		'guests' => $txt['who_show_guests_only'],
		'robots' => $txt['who_show_robots_only'],
	];

	// Does the user prefer a different sort direction?
	if (isset($_REQUEST['sort']) && isset($sort_methods[$_REQUEST['sort']]))
	{
		$context['sort_by'] = $_SESSION['who_online_sort_by'] = $_REQUEST['sort'];
		$sort_method = $sort_methods[$_REQUEST['sort']];
	}
	// Did we set a preferred sort order earlier in the session?
	elseif (isset($_SESSION['who_online_sort_by']))
	{
		$context['sort_by'] = $_SESSION['who_online_sort_by'];
		$sort_method = $sort_methods[$_SESSION['who_online_sort_by']];
	}
	// Default to last time online.
	else
	{
		$context['sort_by'] = $_SESSION['who_online_sort_by'] = 'time';
		$sort_method = 'lo.log_time';
	}

	$context['sort_direction'] = isset($_REQUEST['asc']) || (isset($_REQUEST['sort_dir']) && $_REQUEST['sort_dir'] == 'asc') ? 'up' : 'down';

	$conditions = [];
	if (!allowedTo('moderate_forum'))
		$conditions[] = '(COALESCE(mem.show_online, 1) = 1)';

	// Does the user wish to apply a filter?
	if (isset($_REQUEST['show']) && isset($show_methods[$_REQUEST['show']]))
		$context['show_by'] = $_SESSION['who_online_filter'] = $_REQUEST['show'];
	// Perhaps we saved a filter earlier in the session?
	elseif (isset($_SESSION['who_online_filter']))
		$context['show_by'] = $_SESSION['who_online_filter'];
	else
		$context['show_by'] = 'members';

	$context['navigation_tabs'] = [];
	foreach ($context['show_methods'] as $key => $label)
	{
		$context['navigation_tabs'][] = [
			'label' => $label,
			'active' => $key == $context['show_by'],
			'url' => $key == $context['show_by'] ? '' : $scripturl . '?action=who;show=' . $key,
		];
	}

	$conditions[] = $show_methods[$context['show_by']];

	// Get the total amount of members online.
	$request = $smcFunc['db']->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_online AS lo
			LEFT JOIN {db_prefix}members AS mem ON (lo.id_member = mem.id_member)' . (!empty($conditions) ? '
		WHERE ' . implode(' AND ', $conditions) : ''),
		[
		]
	);
	list ($totalMembers) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	// Prepare some page index variables.
	$context['page_index'] = constructPageIndex($scripturl . '?action=who;sort=' . $context['sort_by'] . ($context['sort_direction'] == 'up' ? ';asc' : '') . ';show=' . $context['show_by'], $_REQUEST['start'], $totalMembers, $modSettings['defaultMaxMembers']);
	$context['start'] = $_REQUEST['start'];

	// Look for people online, provided they don't mind if you see they are.
	$request = $smcFunc['db']->query('', '
		SELECT
			lo.log_time, lo.id_member, lo.url, lo.ip AS ip, lo.id_character, COALESCE(chars.character_name, mem.real_name) AS real_name,
			lo.session, IF(chars.is_main, mg.online_color, cg.online_color) AS online_color, COALESCE(mem.show_online, 1) AS show_online,
			lo.robot_name, lo.route, lo.routeparams
		FROM {db_prefix}log_online AS lo
			LEFT JOIN {db_prefix}members AS mem ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)
			LEFT JOIN {db_prefix}characters AS chars ON (lo.id_character = chars.id_character)
			LEFT JOIN {db_prefix}membergroups AS cg ON (chars.main_char_group = cg.id_group)' . (!empty($conditions) ? '
		WHERE ' . implode(' AND ', $conditions) : '') . '
		ORDER BY {raw:sort_method} {raw:sort_direction}
		LIMIT {int:offset}, {int:limit}',
		[
			'regular_member' => 0,
			'sort_method' => $sort_method,
			'sort_direction' => $context['sort_direction'] == 'up' ? 'ASC' : 'DESC',
			'offset' => $context['start'],
			'limit' => $modSettings['defaultMaxMembers'],
		]
	);
	$context['members'] = [];
	$member_ids = [];
	$url_data = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$actions = sbb_json_decode($row['url'], true);
		if ($actions === false)
			continue;

		// Send the information to the template.
		$context['members'][$row['session']] = [
			'id' => $row['id_member'],
			'id_character' => $row['id_character'],
			'ip' => allowedTo('moderate_forum') ? IP::format($row['ip']) : '',
			// It is *going* to be today or yesterday, so why keep that information in there?
			'time' => strtr(timeformat($row['log_time']), [$txt['today'] => '', $txt['yesterday'] => '']),
			'timestamp' => forum_time(true, $row['log_time']),
			'query' => $actions,
			'is_hidden' => $row['show_online'] == 0,
			'robot_name' => $row['robot_name'],
			'color' => empty($row['online_color']) ? '' : $row['online_color'],
			'user_agent' => !empty($actions['USER_AGENT']) ? $actions['USER_AGENT'] : '',
		];

		$url_data[$row['session']] = [$row['url'], $row['id_member'], $row['robot_name'], $row['route'], $row['routeparams']];
		$member_ids[] = $row['id_member'];
	}
	$smcFunc['db']->free_result($request);

	// Load the user data for these members.
	loadMemberData($member_ids);

	// Load up the guest user.
	$memberContext[0] = [
		'id' => 0,
		'name' => $txt['guest_title'],
		'group' => $txt['guest_title'],
		'href' => '',
		'link' => $txt['guest_title'],
		'email' => $txt['guest_title'],
		'is_guest' => true
	];

	$url_data = determineActions($url_data);

	// Setup the linktree and page title (do it down here because the language files are now loaded..)
	$context['page_title'] = $txt['who_title'];
	$context['linktree'][] = [
		'url' => $scripturl . '?action=who',
		'name' => $txt['who_title']
	];

	// Put it in the context variables.
	$robot = new \StoryBB\Model\Robot;
	foreach ($context['members'] as $i => $member)
	{
		if ($member['id'] != 0)
			$member['id'] = loadMemberContext($member['id']) ? $member['id'] : 0;

		// Keep the IP that came from the database.
		$memberContext[$member['id']]['ip'] = $member['ip'];
		$context['members'][$i]['action'] = isset($url_data[$i]) ? $url_data[$i] : $txt['who_hidden'];

		if ($member['id'] == 0 && !empty($member['robot_name']))
		{
			$robot_details = $robot->get_robot_info($member['robot_name']);
			if (!empty($robot_details))
			{
				$context['members'][$i] += [
					'id' => 0,
					'name' => $robot_details['title'],
					'group' => $txt['robots'],
					'href' => isset($robot_details['link']) && allowedTo('admin_forum') ? $robot_details['link'] : '',
					'link' => isset($robot_details['link']) && allowedTo('admin_forum') ? '<a href="' . $robot_details['link'] . '" target="_blank" rel="noopener">' . $robot_details['title'] . '</a>' : $robot_details['title'],
					'email' => $robot_details['title'],
					'is_guest' => true,
				];
				continue;
			}
		}
		elseif ($member['id'] == 0)
		{
			if (allowedTo('admin_forum'))
			{
				$context['members'][$i]['title'] = StringLibrary::escape($member['user_agent'], ENT_QUOTES);
			}
		}

		$context['members'][$i] += $memberContext[$member['id']];

		if (!empty($member['id_character']) && !empty($context['members'][$i]['characters'][$member['id_character']]))
		{
			// Need to 'fix' a few things.
			$character = $context['members'][$i]['characters'][$member['id_character']];
			$context['members'][$i]['name'] = $character['character_name'];
			$context['members'][$i]['href'] .= ';area=characters;char=' . $member['id_character'];
		}
	}

	// Some people can't send personal messages...
	$context['can_send_pm'] = allowedTo('pm_send');

	// any profile fields disabled?
	$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : [];

}

/**
 * This function determines the actions of the members passed in urls.
 *
 * Adding actions to the Who's Online list:
 * Adding actions to this list is actually relatively easy...
 *  - for actions anyone should be able to see, just add a string named whoall_ACTION.
 *    (where ACTION is the action used in index.php.)
 *  - for actions that have a subaction which should be represented differently, use whoall_ACTION_SUBACTION.
 *  - for actions that include a topic, and should be restricted, use whotopic_ACTION.
 *  - for actions that use a message, by msg or quote, use whopost_ACTION.
 *  - for administrator-only actions, use whoadmin_ACTION.
 *  - for actions that should be viewable only with certain permissions,
 *    use whoallow_ACTION and add a list of possible permissions to the
 *    $allowedActions array, using ACTION as the key.
 *
 * @param mixed $urls  a single url (string) or an array of arrays, each inner array being (JSON-encoded request data, id_member)
 * @param string $preferred_prefix = false
 * @return array, an array of descriptions if you passed an array, otherwise the string describing their current location.
 */
function determineActions($urls, $preferred_prefix = false)
{
	global $txt, $user_info, $smcFunc, $scripturl;

	if (!allowedTo('who_view'))
		return [];
	loadLanguage('Who');

	// Actions that require a specific permission level.
	$allowedActions = [
		'admin' => ['moderate_forum', 'manage_membergroups', 'manage_bans', 'admin_forum', 'manage_permissions', 'send_mail', 'manage_attachments', 'manage_smileys', 'manage_boards'],
		'ban' => ['manage_bans'],
		'boardrecount' => ['admin_forum'],
		'mailing' => ['send_mail'],
		'maintain' => ['admin_forum'],
		'manageattachments' => ['manage_attachments'],
		'manageboards' => ['manage_boards'],
		'moderate' => ['access_mod_center', 'moderate_forum', 'manage_membergroups'],
		'optimizetables' => ['admin_forum'],
		'repairboards' => ['admin_forum'],
		'search' => ['search_posts'],
		'search2' => ['search_posts'],
		'setcensor' => ['moderate_forum'],
		'setreserve' => ['moderate_forum'],
		'stats' => ['view_stats'],
		'viewErrorLog' => ['admin_forum'],
		'viewmembers' => ['moderate_forum'],
	];
	call_integration_hook('who_allowed', [&$allowedActions]);

	if (!is_array($urls))
		$url_list = [[$urls, $user_info['id']]];
	else
		$url_list = $urls;

	// These are done to later query these in large chunks. (instead of one by one.)
	$topic_ids = [];
	$profile_ids = [];
	$board_ids = [];
	$page_ids = [];

	$data = [];
	$errors = [];
	foreach ($url_list as $k => $url)
	{
		// Check for new-style routes if that's a thing.
		if (!empty($url[3]))
		{
			$params = json_decode($url[4], true);
			if (empty($params))
			{
				$params = [];
			}

			if (isset($txt['whoroute_' . $url[3]]))
			{
				$data[$k] = $txt['whoroute_' . $url[3]];
				continue;
			}
		}

		// Get the request parameters..
		$actions = sbb_json_decode($url[0], true);
		if ($actions === false)
			continue;

		// If it's the admin or moderation center, and there is an area set, use that instead.
		if (isset($actions['action']) && ($actions['action'] == 'admin' || $actions['action'] == 'moderate') && isset($actions['area']))
			$actions['action'] = $actions['area'];

		// Check if there was no action or the action is display.
		if (!isset($actions['action']) || $actions['action'] == 'display')
		{
			// It's a topic!  Must be!
			if (isset($actions['topic']))
			{
				// Assume they can't view it, and queue it up for later.
				$data[$k] = $txt['who_hidden'];
				$topic_ids[(int) $actions['topic']][$k] = $txt['who_topic'];
			}
			// It's a board!
			elseif (isset($actions['board']))
			{
				// Hide first, show later.
				$data[$k] = $txt['who_hidden'];
				$board_ids[$actions['board']][$k] = $txt['who_board'];
			}
			// It's the board index!!  It must be!
			else
				$data[$k] = $txt['who_index'];
		}
		// Probably an error or some goon?
		elseif ($actions['action'] == '')
			$data[$k] = $txt['who_index'];
		// Some other normal action...?
		else
		{
			// Robot actions.
			if (!empty($url[2]) && isset($txt['whorobot_' . $actions['action']]))
			{
				$data[$k] = $txt['whorobot_' . $actions['action']];
			}
			elseif ($actions['action'] == '.xml')
			{
				if (isset($actions['sa']) && isset($txt['whoall_' . $actions['action'] . '_' . $actions['sa']]))
				{
					$data[$k] = $preferred_prefix && isset($txt[$preferred_prefix . $actions['action'] . '_' . $actions['sa']]) ? $txt[$preferred_prefix . $actions['action'] . '_' . $actions['sa']] : $txt['whoall_' . $actions['action'] . '_' . $actions['sa']];
				}
				else
				{
					$data[$k] = ($preferred_prefix && isset($txt[$preferred_prefix . $actions['action']]) ? $txt[$preferred_prefix . $actions['action']] : $txt['whoall_' . $actions['action']]) . (isset($actions['sa']) ? ' (' . StringLibrary::escape($actions['sa']) . ')' : '');
				}
			}
			// Viewing/editing a profile.
			elseif ($actions['action'] == 'profile')
			{
				// Whose?  Their own?
				if (empty($actions['u']))
					$actions['u'] = $url[1];

				$data[$k] = $txt['who_hidden'];
				$profile_ids[(int) $actions['u']][$k] = $actions['u'] == $url[1] ? $txt['who_viewownprofile'] : $txt['who_viewprofile'];
			}
			// Viewable if they can see the page in question.
			elseif ($actions['action'] == 'pages')
			{
				$data[$k] = $txt['who_hidden'];
				if (!empty($actions['page']))
				{
					$page_ids[$actions['page']][$k] = $txt['who_page'];
				}
			}
			elseif (($actions['action'] == 'post' || $actions['action'] == 'post2') && empty($actions['topic']) && isset($actions['board']))
			{
				$data[$k] = $txt['who_hidden'];
				$board_ids[(int) $actions['board']][$k] = isset($actions['poll']) ? $txt['who_poll'] : $txt['who_post'];
			}
			// A subaction anyone can view... if the language string is there, show it.
			elseif (isset($actions['sa']) && isset($txt['whoall_' . $actions['action'] . '_' . $actions['sa']]))
				$data[$k] = $preferred_prefix && isset($txt[$preferred_prefix . $actions['action'] . '_' . $actions['sa']]) ? $txt[$preferred_prefix . $actions['action'] . '_' . $actions['sa']] : $txt['whoall_' . $actions['action'] . '_' . $actions['sa']];
			// An action any old fellow can look at. (if ['whoall_' . $action] exists, we know everyone can see it.)
			elseif (isset($txt['whoall_' . $actions['action']]))
				$data[$k] = $preferred_prefix && isset($txt[$preferred_prefix . $actions['action']]) ? $txt[$preferred_prefix . $actions['action']] : $txt['whoall_' . $actions['action']];
			// Viewable if and only if they can see the board...
			elseif (isset($txt['whotopic_' . $actions['action']]))
			{
				// Find out what topic they are accessing.
				$topic = (int) (isset($actions['topic']) ? $actions['topic'] : (isset($actions['from']) ? $actions['from'] : 0));

				$data[$k] = $txt['who_hidden'];
				$topic_ids[$topic][$k] = $txt['whotopic_' . $actions['action']];
			}
			elseif (isset($txt['whopost_' . $actions['action']]))
			{
				// Find out what message they are accessing.
				$msgid = (int) (isset($actions['msg']) ? $actions['msg'] : (isset($actions['quote']) ? $actions['quote'] : 0));

				$result = $smcFunc['db']->query('', '
					SELECT m.id_topic, m.subject
					FROM {db_prefix}messages AS m
						INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
						INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic AND t.approved = {int:is_approved})
					WHERE m.id_msg = {int:id_msg}
						AND {query_see_board}
						AND m.approved = {int:is_approved}
					LIMIT 1',
					[
						'is_approved' => 1,
						'id_msg' => $msgid,
					]
				);
				list ($id_topic, $subject) = $smcFunc['db']->fetch_row($result);
				$data[$k] = sprintf($txt['whopost_' . $actions['action']], $id_topic, $subject);
				$smcFunc['db']->free_result($result);

				if (empty($id_topic))
					$data[$k] = $txt['who_hidden'];
			}
			// Viewable only by administrators.. (if it starts with whoadmin, it's admin only!)
			elseif (allowedTo('moderate_forum') && isset($txt['whoadmin_' . $actions['action']]))
				$data[$k] = $txt['whoadmin_' . $actions['action']];
			// Viewable by permission level.
			elseif (isset($allowedActions[$actions['action']]))
			{
				if (allowedTo($allowedActions[$actions['action']]))
					$data[$k] = $txt['whoallow_' . $actions['action']];
				elseif (in_array('moderate_forum', $allowedActions[$actions['action']]))
					$data[$k] = $txt['who_moderate'];
				elseif (in_array('admin_forum', $allowedActions[$actions['action']]))
					$data[$k] = $txt['who_admin'];
				else
					$data[$k] = $txt['who_hidden'];
			}
			elseif (!empty($actions['action']))
				$data[$k] = $txt['who_generic'] . ' ' . $actions['action'];
			else
				$data[$k] = $txt['who_unknown'];
		}

		if (isset($actions['error']))
		{
			$errors[$k] = [
				'error' => $actions['error'],
				'who_error_params' => $actions['who_error_params'] ?? [],
			];
		}

		// Maybe the action is integrated into another system?
		if (count($integrate_actions = call_integration_hook('integrate_whos_online', [$actions])) > 0)
		{
			foreach ($integrate_actions as $integrate_action)
			{
				if (!empty($integrate_action))
				{
					$data[$k] = $integrate_action;
					if (isset($actions['topic']) && isset($topic_ids[(int) $actions['topic']][$k]))
						$topic_ids[(int) $actions['topic']][$k] = $integrate_action;
					if (isset($actions['board']) && isset($board_ids[(int) $actions['board']][$k]))
						$board_ids[(int) $actions['board']][$k] = $integrate_action;
					if (isset($actions['u']) && isset($profile_ids[(int) $actions['u']][$k]))
						$profile_ids[(int) $actions['u']][$k] = $integrate_action;
					break;
				}
			}
		}
	}

	// Load topic names.
	if (!empty($topic_ids))
	{
		$result = $smcFunc['db']->query('', '
			SELECT t.id_topic, m.subject
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE {query_see_board}
				AND t.id_topic IN ({array_int:topic_list})
				AND t.approved = {int:is_approved}
			LIMIT {int:limit}',
			[
				'topic_list' => array_keys($topic_ids),
				'is_approved' => 1,
				'limit' => count($topic_ids),
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			// Show the topic's subject for each of the actions.
			foreach ($topic_ids[$row['id_topic']] as $k => $session_text)
				$data[$k] = sprintf($session_text, $row['id_topic'], censorText($row['subject']));
		}
		$smcFunc['db']->free_result($result);
	}

	// Load board names.
	if (!empty($board_ids))
	{
		$result = $smcFunc['db']->query('', '
			SELECT b.id_board, b.name
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}
				AND b.id_board IN ({array_int:board_list})
			LIMIT {int:limit}',
			[
				'board_list' => array_keys($board_ids),
				'limit' => count($board_ids),
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			// Put the board name into the string for each member...
			foreach ($board_ids[$row['id_board']] as $k => $session_text)
				$data[$k] = sprintf($session_text, $row['id_board'], $row['name']);
		}
		$smcFunc['db']->free_result($result);
	}

	if (!empty($page_ids))
	{
		$request = $smcFunc['db']->query('', '
			SELECT p.id_page, p.page_name, p.page_title, COALESCE(pa.allow_deny, -1) AS allow_deny
			FROM {db_prefix}page AS p
			LEFT JOIN {db_prefix}page_access AS pa ON (p.id_page = pa.id_page AND pa.id_group IN ({array_int:groups}))
			WHERE p.page_name IN ({array_string:page_name})',
			[
				'page_name' => array_keys($page_ids),
				'groups' => array_values($user_info['groups']),
			]
		);

		$pages = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$row['allow_deny'] = (int) $row['allow_deny'];
			if (!isset($pages[$row['id_page']]))
			{
				$pages[$row['id_page']] = $row;
			}
			// Possible values: 1 (deny), 0 (allow), -1 (disallow); higher values override lower ones.
			if ($row['allow_deny'] > $pages[$row['id_page']]['allow_deny'])
			{
				$pages[$row['id_page']]['allow_deny'] = $row['allow_deny'];
			}
		}
		$smcFunc['db']->free_result($request);

		foreach ($pages as $page)
		{
			if ($page['allow_deny'] != 0 && !$user_info['is_admin'])
			{
				continue;
			}

			foreach ($page_ids[$page['page_name']] as $k => $page_text)
			{
				$data[$k] = sprintf($page_text, $page['page_name'], $page['page_title']);
			}
		}
	}

	// Load member names for the profile. (is_not_guest permission for viewing their own profile)
	$allow_view_own = allowedTo('is_not_guest');
	$allow_view_any = allowedTo('profile_view');
	if (!empty($profile_ids) && ($allow_view_any || $allow_view_own))
	{
		$result = $smcFunc['db']->query('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:member_list})
			LIMIT ' . count($profile_ids),
			[
				'member_list' => array_keys($profile_ids),
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			// If they aren't allowed to view this person's profile, skip it.
			if (!$allow_view_any && ($user_info['id'] != $row['id_member']))
				continue;

			// Set their action on each - session/text to sprintf.
			foreach ($profile_ids[$row['id_member']] as $k => $session_text)
			{
				$data[$k] = sprintf($session_text, $row['id_member'], $row['real_name']);
			}
		}
		$smcFunc['db']->free_result($result);
	}

	foreach ($data as $k => $v)
	{
		$data[$k] = str_replace('{scripturl}', $scripturl, $v);

		if (isset($errors[$k]))
		{
			loadLanguage('Errors');
			$actions = $errors[$k];
			if (isset($txt[$errors[$k]['error']]))
				$error_message = str_replace('"', '&quot;', empty($actions['who_error_params']) ? $txt[$actions['error']] : vsprintf($txt[$actions['error']], $actions['who_error_params']));
			elseif ($actions['error'] == 'guest_login')
				$error_message = str_replace('"', '&quot;', $txt['who_guest_login']);
			else
				$error_message = str_replace('"', '&quot;', $actions['error']);

			if (!empty($error_message))
				$data[$k] .= ' <span class="main_icons error" title="' . sprintf($txt['who_user_received_error'], $error_message) . '"></span>';
		}
	}

	call_integration_hook('whos_online_after', [&$urls, &$data]);

	if (!is_array($urls))
		return isset($data[0]) ? $data[0] : false;
	else
		return $data;
}
