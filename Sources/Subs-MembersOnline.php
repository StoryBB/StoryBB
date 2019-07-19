<?php

/**
 * Handle online users
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

/**
 * Retrieve a list and several other statistics of the users currently online.
 * Used by the board index and SSI.
 * Also returns the membergroups of the users that are currently online.
 * (optionally) hides members that chose to hide their online presence.
 * @param array $membersOnlineOptions An array of options for the list
 * @return array An array of information about the online users
 */
function getMembersOnlineStats($membersOnlineOptions)
{
	global $smcFunc, $scripturl, $user_info, $modSettings, $txt;

	// The list can be sorted in several ways.
	$allowed_sort_options = array(
		'', // No sorting.
		'log_time',
		'real_name',
		'show_online',
		'online_color',
		'group_name',
	);
	// Default the sorting method to 'most recent online members first'.
	if (!isset($membersOnlineOptions['sort']))
	{
		$membersOnlineOptions['sort'] = 'log_time';
		$membersOnlineOptions['reverse_sort'] = true;
	}

	// Not allowed sort method? Bang! Error!
	elseif (!in_array($membersOnlineOptions['sort'], $allowed_sort_options))
		trigger_error('Sort method for getMembersOnlineStats() function is not allowed', E_USER_NOTICE);

	// Initialize the array that'll be returned later on.
	$membersOnlineStats = array(
		'users_online' => [],
		'list_users_online' => [],
		'online_groups' => [],
		'num_guests' => 0,
		'num_robots' => 0,
		'num_buddies' => 0,
		'num_users_hidden' => 0,
		'num_users_online' => 0,
	);

	// Load the users online right now.
	$robot = new \StoryBB\Model\Robot;
	$robot_finds = [];

	$request = $smcFunc['db_query']('', '
		SELECT
			lo.id_member, lo.log_time, lo.robot_name, chars.id_character, IFNULL(chars.character_name, mem.real_name) AS real_name, mem.member_name, mem.show_online,
			IF(chars.is_main, mg.online_color, cg.online_color) AS online_color, mg.id_group, mg.group_name,
			mg.hidden, mg.group_type, mg.id_parent
		FROM {db_prefix}log_online AS lo
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lo.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)
			LEFT JOIN {db_prefix}characters AS chars ON (lo.id_character = chars.id_character)
			LEFT JOIN {db_prefix}membergroups AS cg ON (cg.id_group = chars.main_char_group)',
		array(
			'reg_mem_group' => 0,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (empty($row['real_name']))
		{
			// Do we think it's a robot?
			if (!empty($row['robot_name']))
			{
				$robot_details = $robot->get_robot_info($row['robot_name']);
				if (!empty($robot_details))
				{
					if (!isset($robot_finds[$row['robot_name']]))
					{
						$robot_finds[$row['robot_name']] = $robot_details;
						$robot_finds[$row['robot_name']]['count'] = 0;
					}
					$robot_finds[$row['robot_name']]['count']++;
					$membersOnlineStats['num_robots']++;
					continue;
				}
			}

			// Guests are only nice for statistics.
			$membersOnlineStats['num_guests']++;

			continue;
		}

		elseif (empty($row['show_online']) && empty($membersOnlineOptions['show_hidden']))
		{
			// Just increase the stats and don't add this hidden user to any list.
			$membersOnlineStats['num_users_hidden']++;
			continue;
		}

		// Some basic color coding...
		if (!empty($row['online_color']))
			$link = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . (!empty($row['id_character']) ? ';area=characters;char=' . $row['id_character'] : '') . '" style="color: ' . $row['online_color'] . ';">' . $row['real_name'] . '</a>';
		else
			$link = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . (!empty($row['id_character']) ? ';area=characters;char=' . $row['id_character'] : '') . '">' . $row['real_name'] . '</a>';

		// Buddies get counted and highlighted.
		$is_buddy = in_array($row['id_member'], $user_info['buddies']);
		if ($is_buddy)
		{
			$membersOnlineStats['num_buddies']++;
			$link = '<strong>' . $link . '</strong>';
		}

		// A lot of useful information for each member.
		$membersOnlineStats['users_online'][$row[$membersOnlineOptions['sort']] . '_' . $row['member_name']] = array(
			'id' => $row['id_member'],
			'username' => $row['member_name'],
			'name' => $row['real_name'],
			'group' => $row['id_group'],
			'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
			'link' => $link,
			'is_buddy' => $is_buddy,
			'hidden' => empty($row['show_online']),
			'is_last' => false,
		);

		// This is the compact version, simply implode it to show.
		$membersOnlineStats['list_users_online'][$row[$membersOnlineOptions['sort']] . '_' . $row['member_name']] = empty($row['show_online']) ? '<em>' . $link . '</em>' : $link;

		// Store all distinct (primary) membergroups that are shown.
		if (!isset($membersOnlineStats['online_groups'][$row['id_group']]))
			$membersOnlineStats['online_groups'][$row['id_group']] = array(
				'id' => $row['id_group'],
				'name' => $row['group_name'],
				'color' => $row['online_color'],
				'hidden' => $row['hidden'],
				'type' => $row['group_type'],
				'parent' => $row['id_parent'],
			);
	}
	$smcFunc['db_free_result']($request);

	// If there are robots only and we're showing the detail, add them to the online list - at the bottom.
	if (!empty($robot_finds))
	{
		$sort = $membersOnlineOptions['sort'] === 'log_time' && $membersOnlineOptions['reverse_sort'] ? 0 : 'zzz_';
		foreach ($robot_finds as $id => $this_robot)
		{
			$link = $this_robot['title'] . ($this_robot['count'] > 1 ? ' (' . $this_robot['count'] . ')' : '');
			$membersOnlineStats['users_online'][$sort . '_' . $id] = array(
				'id' => 0,
				'username' => $id,
				'name' => $this_robot['title'],
				'group' => $txt['robots'],
				'href' => '',
				'link' => $link,
				'is_buddy' => false,
				'hidden' => false,
				'is_last' => false,
			);
			$membersOnlineStats['list_users_online'][$sort . '_' . $id] = $link;
		}
	}

	// Time to sort the list a bit.
	if (!empty($membersOnlineStats['users_online']))
	{
		// Determine the sort direction.
		$sortFunction = empty($membersOnlineOptions['reverse_sort']) ? 'ksort' : 'krsort';

		// Sort the two lists.
		$sortFunction($membersOnlineStats['users_online']);
		$sortFunction($membersOnlineStats['list_users_online']);

		// Mark the last list item as 'is_last'.
		$userKeys = array_keys($membersOnlineStats['users_online']);
		$membersOnlineStats['users_online'][end($userKeys)]['is_last'] = true;
	}

	// Also sort the membergroups.
	ksort($membersOnlineStats['online_groups']);

	// Hidden and non-hidden members make up all online members.
	$membersOnlineStats['num_users_online'] = count($membersOnlineStats['users_online']) + $membersOnlineStats['num_users_hidden'] - count($robot_finds);

	return $membersOnlineStats;
}

/**
 * Check if the number of users online is a record and store it.
 * @param int $total_users_online The total number of members online
 */
function trackStatsUsersOnline($total_users_online)
{
	global $modSettings, $smcFunc;

	$settingsToUpdate = [];

	// More members on now than ever were?  Update it!
	if (!isset($modSettings['mostOnline']) || $total_users_online >= $modSettings['mostOnline'])
		$settingsToUpdate = array(
			'mostOnline' => $total_users_online,
			'mostDate' => time()
		);

	$date = strftime('%Y-%m-%d', forum_time(false));

	// No entry exists for today yet?
	if (!isset($modSettings['mostOnlineUpdated']) || $modSettings['mostOnlineUpdated'] != $date)
	{
		$request = $smcFunc['db_query']('', '
			SELECT most_on
			FROM {db_prefix}log_activity
			WHERE date = {date:date}
			LIMIT 1',
			array(
				'date' => $date,
			)
		);

		// The log_activity hasn't got an entry for today?
		if ($smcFunc['db_num_rows']($request) === 0)
		{
			$smcFunc['db_insert']('ignore',
				'{db_prefix}log_activity',
				array('date' => 'date', 'most_on' => 'int'),
				array($date, $total_users_online),
				array('date')
			);
		}
		// There's an entry in log_activity on today...
		else
		{
			list ($modSettings['mostOnlineToday']) = $smcFunc['db_fetch_row']($request);

			if ($total_users_online > $modSettings['mostOnlineToday'])
				trackStats(array('most_on' => $total_users_online));

			$total_users_online = max($total_users_online, $modSettings['mostOnlineToday']);
		}
		$smcFunc['db_free_result']($request);

		$settingsToUpdate['mostOnlineUpdated'] = $date;
		$settingsToUpdate['mostOnlineToday'] = $total_users_online;
	}

	// Highest number of users online today?
	elseif ($total_users_online > $modSettings['mostOnlineToday'])
	{
		trackStats(array('most_on' => $total_users_online));
		$settingsToUpdate['mostOnlineToday'] = $total_users_online;
	}

	if (!empty($settingsToUpdate))
		updateSettings($settingsToUpdate);
}
