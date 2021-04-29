<?php

/**
 * A recent posts block.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Block;

use StoryBB\Model\Group;

/**
 * The recent posts block.
 */
class WhosOnline extends AbstractBlock implements Block
{
	protected $config;
	protected $content;

	public function __construct($config = [])
	{
		$this->config = $config;
	}

	public function get_name(): string
	{
		global $txt;
		return $txt['online_users'];
	}

	public function get_default_title(): string
	{
		return 'txt.online_users';
	}

	public function get_block_content(): string
	{
		global $txt, $scripturl, $modSettings;

		if ($this->content !== null)
		{
			return $this->content;
		}
		elseif ($this->content === null)
		{
			$this->content = '';
		}

		$stats = $this->get_online_numbers([
			'sort' => 'log_time',
			'reverse_sort' => true,
			'show_hidden' => allowedTo('moderate_forum'),
		]);

		$membergroups = [];
		if (!empty($this->config['show_group_key']))
		{
			$membergroups = cache_quick_get('membergroup_list', null, [$this, 'cache_membergroup_key'], []);
		}

		if (!empty($modSettings['trackStats']))
		{
			$this->track_online($stats['num_guests'] + $stats['num_robots'] + $stats['num_users_online']);
		}
		

		$this->content = $this->render('block_whos_online', [
			'users_online' => $stats['users_online'],
			'list_users_online' => $stats['list_users_online'],
			'see_online' => allowedTo('who_view') && !empty($modSettings['who_enabled']),
			'num_guests' => numeric_context('num_guests', $stats['num_guests']),
			'num_users_online' => numeric_context('num_users_online', $stats['num_users_online']),
			'num_robots' => numeric_context('num_robots', $stats['num_robots']),
			'num_hidden' => !empty($stats['num_hidden']) ? numeric_context('num_hidden', $stats['num_users_hidden']) : '',
			'membergroups' => $membergroups,
			'txt' => $txt,
			'scripturl' => $scripturl,
			'modSettings' => $modSettings,
		]);
		return $this->content;
	}

	/**
	 * Retrieve a list and several other statistics of the users currently online.
	 * Used by the board index.
	 * Also returns the membergroups of the users that are currently online.
	 * (optionally) hides members that chose to hide their online presence.
	 * @param array $membersOnlineOptions An array of options for the list
	 * @return array An array of information about the online users
	 */
	public function get_online_numbers($membersOnlineOptions)
	{
		global $smcFunc, $scripturl, $user_info, $txt;

		// The list can be sorted in several ways.
		$allowed_sort_options = [
			'', // No sorting.
			'log_time',
			'real_name',
			'show_online',
			'online_color',
			'group_name',
		];
		// Default the sorting method to 'most recent online members first'.
		if (!isset($membersOnlineOptions['sort']))
		{
			$membersOnlineOptions['sort'] = 'log_time';
			$membersOnlineOptions['reverse_sort'] = true;
		}

		// Not allowed sort method? Bang! Error!
		elseif (!in_array($membersOnlineOptions['sort'], $allowed_sort_options))
			trigger_error('Sort method for WhosOnline::get_online_numbers() function is not allowed', E_USER_NOTICE);

		// Initialize the array that'll be returned later on.
		$membersOnlineStats = [
			'users_online' => [],
			'list_users_online' => [],
			'online_groups' => [],
			'num_guests' => 0,
			'num_robots' => 0,
			'num_buddies' => 0,
			'num_users_hidden' => 0,
			'num_users_online' => 0,
		];

		// Load the users online right now.
		$robot = new \StoryBB\Model\Robot;
		$robot_finds = [];

		$request = $smcFunc['db']->query('', '
			SELECT
				lo.id_member, lo.log_time, lo.robot_name, chars.id_character, IFNULL(chars.character_name, mem.real_name) AS real_name, mem.member_name, mem.show_online,
				IF(chars.is_main, mg.online_color, cg.online_color) AS online_color, mg.id_group, mg.group_name,
				mg.hidden, mg.group_type, mg.id_parent
			FROM {db_prefix}log_online AS lo
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lo.id_member)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)
				LEFT JOIN {db_prefix}characters AS chars ON (lo.id_character = chars.id_character)
				LEFT JOIN {db_prefix}membergroups AS cg ON (cg.id_group = chars.main_char_group)',
			[
				'reg_mem_group' => 0,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
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
			$membersOnlineStats['users_online'][$row[$membersOnlineOptions['sort']] . '_' . $row['member_name']] = [
				'id' => $row['id_member'],
				'username' => $row['member_name'],
				'name' => $row['real_name'],
				'group' => $row['id_group'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => $link,
				'is_buddy' => $is_buddy,
				'hidden' => empty($row['show_online']),
				'is_last' => false,
			];

			// This is the compact version, simply implode it to show.
			$membersOnlineStats['list_users_online'][$row[$membersOnlineOptions['sort']] . '_' . $row['member_name']] = empty($row['show_online']) ? '<em>' . $link . '</em>' : $link;

			// Store all distinct (primary) membergroups that are shown.
			if (!isset($membersOnlineStats['online_groups'][$row['id_group']]))
				$membersOnlineStats['online_groups'][$row['id_group']] = [
					'id' => $row['id_group'],
					'name' => $row['group_name'],
					'color' => $row['online_color'],
					'hidden' => $row['hidden'],
					'type' => $row['group_type'],
					'parent' => $row['id_parent'],
				];
		}
		$smcFunc['db']->free_result($request);

		// If there are robots only and we're showing the detail, add them to the online list - at the bottom.
		if (!empty($robot_finds))
		{
			$sort = $membersOnlineOptions['sort'] === 'log_time' && $membersOnlineOptions['reverse_sort'] ? 0 : 'zzz_';
			foreach ($robot_finds as $id => $this_robot)
			{
				$link = $this_robot['title'] . ($this_robot['count'] > 1 ? ' (' . $this_robot['count'] . ')' : '');
				$membersOnlineStats['users_online'][$sort . '_' . $id] = [
					'id' => 0,
					'username' => $id,
					'name' => $this_robot['title'],
					'group' => $txt['robots'],
					'href' => '',
					'link' => $link,
					'is_buddy' => false,
					'hidden' => false,
					'is_last' => false,
				];
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
	public function track_online($total_users_online)
	{
		global $modSettings, $smcFunc;

		$settingsToUpdate = [];

		// More members on now than ever were?  Update it!
		if (!isset($modSettings['mostOnline']) || $total_users_online >= $modSettings['mostOnline'])
			$settingsToUpdate = [
				'mostOnline' => $total_users_online,
				'mostDate' => time()
			];

		$date = dateformat_ymd(forum_time(false));

		// No entry exists for today yet?
		if (!isset($modSettings['mostOnlineUpdated']) || $modSettings['mostOnlineUpdated'] != $date)
		{
			$request = $smcFunc['db']->query('', '
				SELECT most_on
				FROM {db_prefix}log_activity
				WHERE date = {date:date}
				LIMIT 1',
				[
					'date' => $date,
				]
			);

			// The log_activity hasn't got an entry for today?
			if ($smcFunc['db']->num_rows($request) === 0)
			{
				$smcFunc['db']->insert('ignore',
					'{db_prefix}log_activity',
					['date' => 'date', 'most_on' => 'int'],
					[$date, $total_users_online],
					['date']
				);
			}
			// There's an entry in log_activity on today...
			else
			{
				list ($modSettings['mostOnlineToday']) = $smcFunc['db']->fetch_row($request);

				if ($total_users_online > $modSettings['mostOnlineToday'])
					trackStats(['most_on' => $total_users_online]);

				$total_users_online = max($total_users_online, $modSettings['mostOnlineToday']);
			}
			$smcFunc['db']->free_result($request);

			$settingsToUpdate['mostOnlineUpdated'] = $date;
			$settingsToUpdate['mostOnlineToday'] = $total_users_online;
		}

		// Highest number of users online today?
		elseif ($total_users_online > $modSettings['mostOnlineToday'])
		{
			trackStats(['most_on' => $total_users_online]);
			$settingsToUpdate['mostOnlineToday'] = $total_users_online;
		}

		if (!empty($settingsToUpdate))
			updateSettings($settingsToUpdate);
	}

	public function cache_membergroup_key(): array
	{
		global $scripturl, $smcFunc;

		$request = $smcFunc['db']->query('', '
			SELECT id_group, group_name, online_color
			FROM {db_prefix}membergroups
			WHERE hidden = {int:not_hidden}
				AND id_group != {int:mod_group}
			ORDER BY group_name',
			[
				'not_hidden' => 0,
				'mod_group' => Group::BOARD_MODERATOR,
			]
		);
		$groupCache = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$groupCache[] = '<a href="' . $scripturl . '?action=groups;sa=members;group=' . $row['id_group'] . '" ' . ($row['online_color'] ? 'style="color: ' . $row['online_color'] . '"' : '') . '>' . $row['group_name'] . '</a>';
		}
		$smcFunc['db']->free_result($request);

		return [
			'data' => $groupCache,
			'expires' => time() + 3600,
			'refresh_eval' => 'return $GLOBALS[\'modSettings\'][\'settings_updated\'] > ' . time() . ';',
		];
	}
}
