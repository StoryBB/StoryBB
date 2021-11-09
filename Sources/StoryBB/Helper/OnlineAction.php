<?php

/**
 * This file works out what the user is doing from their online log entry.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

use StoryBB\App;
use StoryBB\Dependency\CurrentUser;
use StoryBB\Dependency\Database;
use StoryBB\Dependency\UrlGenerator;
use StoryBB\Phrase;
use StoryBB\StringLibrary;

class OnlineAction
{
	use CurrentUser;
	use Database;
	use UrlGenerator;

	protected $unresolved = [
		'topic_ids' => [],
		'profile_ids' => [],
		'board_ids' => [],
		'page_ids' => [],
	];

	protected $data;

	public function get_allowed_actions(): array
	{
		return [
			'admin' => ['moderate_forum', 'manage_membergroups', 'manage_bans', 'admin_forum', 'manage_permissions', 'manage_attachments', 'manage_smileys', 'manage_boards'],
			'ban' => ['manage_bans'],
			'boardrecount' => ['admin_forum'],
			'maintain' => ['admin_forum'],
			'manageattachments' => ['manage_attachments'],
			'manageboards' => ['manage_boards'],
			'moderate' => ['access_mod_center', 'moderate_forum', 'manage_membergroups'],
			'repairboards' => ['admin_forum'],
			'search' => ['search_posts'],
			'search2' => ['search_posts'],
			'setcensor' => ['moderate_forum'],
			'setreserve' => ['moderate_forum'],
			'stats' => ['view_stats'],
			'viewErrorLog' => ['admin_forum'],
			'viewmembers' => ['moderate_forum'],
		];
	}

	public function parameter_resolution_rules(): array
	{
		// Primary key is the route, params = [route parameter, entry in unresolved]
		return [
			'pages' => [
				'params' => ['page', 'page_ids'],
				'phrase' => 'Who:whoroute_pages',
			],
		];
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
	 * @param array $url_list An array of arrays, each inner array being (JSON-encoded request data, id_member)
	 * @return array, an array of descriptions if you passed an array, otherwise the string describing their current location.
	 */
	public function determine(array $url_list): array
	{
		$db = $this->db();
		$url = $this->urlgenerator();

		// Actions that require a specific permission level.
		$allowedActions = $this->get_allowed_actions();

		$this->data = [];
		$errors = [];

		$route_resolution = $this->parameter_resolution_rules();

		foreach ($url_list as $k => $url)
		{
			// Check for new-style routes if that's a thing.
			if (!empty($url['route']))
			{
				// Start with a generic outcome.
				$this->data[$k] = new Phrase('Who:who_hidden');

				$params = json_decode($url['routeparams'], true);
				if (empty($params))
				{
					$params = [];
				}

				foreach ($route_resolution as $route => $routerules)
				{
					$paramrules = $routerules['params'];
					if ($url['route'] == $route)
					{
						if (!empty($params[$paramrules[0]]))
						{
							$this->unresolved[$paramrules[1]][$params[$paramrules[0]]][$k] = new Phrase($routerules['phrase']);
						}
						continue;
					}
				}

				$this->data[$k] = new Phrase('Who:whoroute_' . $url['route']);
				continue;
			}

			// Get the request parameters..
			$actions = sbb_json_decode($url['url'], true);
			if ($actions === false)
			{
				continue;
			}

			// Start with a generic outcome.
			$this->data[$k] = new Phrase('Who:who_hidden');

			// If it's the admin or moderation center, and there is an area set, use that instead.
			if (isset($actions['action']) && ($actions['action'] == 'admin' || $actions['action'] == 'moderate') && isset($actions['area']))
			{
				$actions['action'] = $actions['area'];
			}

			// Check if there was no action or the action is display.
			if (!isset($actions['action']) || $actions['action'] == 'display')
			{
				// It's a topic!  Must be!
				if (isset($actions['topic']))
				{
					// Assume they can't view it, and queue it up for later.
					$this->unresolved['topic_ids'][(int) $actions['topic']][$k] = new Phrase('Who:who_topic');
				}
				// It's a board!
				elseif (isset($actions['board']))
				{
					// Hide first, show later.
					$this->unresolved['board_ids'][$actions['board']][$k] = new Phrase('Who:who_board');
				}
				// It's the board index!!  It must be!
				else
				{
					$this->data[$k] = new Phrase('Who:who_index');
				}
			}
			// Probably an error or some goon?
			elseif ($actions['action'] == '')
			{
				$this->data[$k] = new Phrase('Who:who_index');
			}
			// Some other normal action...?
			else
			{
				if ($actions['action'] == '.xml')
				{
					$this->data[$k] = new Phrase('Who:whoall_' . $actions['action']) . (isset($actions['sa']) ? ' (' . StringLibrary::escape($actions['sa']) . ')' : '');
				}
				// Viewing/editing a profile.
				elseif ($actions['action'] == 'profile')
				{
					// Whose?  Their own?
					if (empty($actions['u']))
					{
						$actions['u'] = $url['id_member'];
					}

					$this->unresolved['profile_ids'][(int) $actions['u']][$k] = ($actions['u'] == $url['id_member']) ? new Phrase('Who:who_viewownprofile') : new Phrase('Who:who_viewprofile');
				}
				elseif (($actions['action'] == 'post' || $actions['action'] == 'post2') && empty($actions['topic']) && isset($actions['board']))
				{
					$this->unresolved['board_ids'][(int) $actions['board']][$k] = new Phrase('Who:who_post');
				}
				// A subaction anyone can view... if the language string is there, show it.
				elseif (isset($actions['sa']))
				{
					$this->data[$k] = new Phrase('Who:whoall_' . $actions['action'] . '_' . $actions['sa']);
				}
				// An action any old fellow can look at. (if ['whoall_' . $action] exists, we know everyone can see it.)
				elseif (isset($txt['whoall_' . $actions['action']]))
				{
					$this->data[$k] = new Phrase('Who:whoall_' . $actions['action']);
				}
				// Viewable if and only if they can see the board...
				elseif (isset($txt['whotopic_' . $actions['action']]))
				{
					// Find out what topic they are accessing.
					$topic = (int) (isset($actions['topic']) ? $actions['topic'] : (isset($actions['from']) ? $actions['from'] : 0));

					$this->unresolved['topic_ids'][$topic][$k] = new Phrase('Who:whotopic_' . $actions['action']);
				}
				elseif (isset($txt['whopost_' . $actions['action']]))
				{
					// Find out what message they are accessing.
					$msgid = (int) (isset($actions['msg']) ? $actions['msg'] : (isset($actions['quote']) ? $actions['quote'] : 0));

					$result = $db->query('', '
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
					list ($id_topic, $subject) = $db->fetch_row($result);
					if ($id_topic)
					{
						$this->data[$k] = new Phrase('Who:whopost_' . $actions['action'], [$id_topic, $subject]);
					}
					$db->free_result($result);
				}
				// Viewable only by administrators.. (if it starts with whoadmin, it's admin only!)
				elseif (allowedTo('moderate_forum') && isset($txt['whoadmin_' . $actions['action']]))
				{
					$this->data[$k] = new Phrase('Who:whoadmin_' . $actions['action']);
				}
				// Viewable by permission level.
				elseif (isset($allowedActions[$actions['action']]))
				{
					if (allowedTo($allowedActions[$actions['action']]))
					{
						$this->data[$k] = new Phrase('Who:whoallow_' . $actions['action']);
					}
					elseif (in_array('moderate_forum', $allowedActions[$actions['action']]))
					{
						$this->data[$k] = new Phrase('Who:who_moderate');
					}
					elseif (in_array('admin_forum', $allowedActions[$actions['action']]))
					{
						$this->data[$k] = new Phrase('Who:who_admin');
					}
				}
				elseif (!empty($actions['action']))
				{
					$this->data[$k] = ((string) new Phrase('Who:who_generic')) . ' ' . $actions['action'];
				}
				else
				{
					$this->data[$k] = new Phrase('Who:who_unknown');
				}
			}

			if (isset($actions['error']))
			{
				if ($actions['error'] == 'guest_login')
				{
					$errors[$k] = new Phrase('Who:who_guest_login');
				}
				else
				{
					$errors[$k] = new Phrase('Errors:' . $actions['error'], $actions['who_error_params'] ?? []);
				}
			}
		}

		// Handle any unresolved variables that we have here.
		foreach ($this->unresolved as $unresolved_type => $unresolved_vars)
		{
			$method = 'resolve_' . $unresolved_type;
			if (empty($unresolved_vars) || !is_callable($this, $method))
			{
				continue;
			}

			$this->$method();
		}

		foreach ($this->data as $k => $v)
		{
			// @todo remove once all the actions are replaced out.
			$this->data[$k] = str_replace('{scripturl}', App::get_global_config_item('boardurl') . '/index.php', $v);

			if (isset($errors[$k]))
			{
				$error_message = str_replace('"', '&quot;', $errors[$k]);
				$this->data[$k] .= ' <span class="main_icons error" title="' . (new Phrase('Who:who_user_received_error', [$error_message])) . '"></span>';
			}
		}

		return $this->data;
	}

	protected function resolve_board_ids(): void
	{
		if (empty($this->unresolved['board_ids']))
		{
			return;
		}

		$db = $this->db();

		$result = $db->query('', '
			SELECT b.id_board, b.name
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}
				AND b.id_board IN ({array_int:board_list})
			LIMIT {int:limit}',
			[
				'board_list' => array_keys($this->unresolved['board_ids']),
				'limit' => count($this->unresolved['board_ids']),
			]
		);
		while ($row = $db->fetch_assoc($result))
		{
			// Put the board name into the string for each member...
			foreach ($this->unresolved['board_ids'][$row['id_board']] as $k => $session_text)
			{
				$this->data[$k] = sprintf($session_text, $row['id_board'], $row['name']);
			}
		}
		$db->free_result($result);
	}

	protected function resolve_topic_ids()
	{
		if (empty($this->unresolved['topic_ids']))
		{
			return;
		}

		$db = $this->db();

		$result = $db->query('', '
			SELECT t.id_topic, m.subject
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE {query_see_board}
				AND t.id_topic IN ({array_int:topic_list})
				AND t.approved = {int:is_approved}
			LIMIT {int:limit}',
			[
				'topic_list' => array_keys($this->unresolved['topic_ids']),
				'is_approved' => 1,
				'limit' => count($this->unresolved['topic_ids']),
			]
		);
		while ($row = $db->fetch_assoc($result))
		{
			// Show the topic's subject for each of the actions.
			foreach ($this->unresolved['topic_ids'][$row['id_topic']] as $k => $session_text)
				$this->data[$k] = sprintf($session_text, $row['id_topic'], censorText($row['subject']));
		}
		$db->free_result($result);
	}

	protected function resolve_page_ids(): void
	{
		if (empty($this->unresolved['page_ids']))
		{
			return;
		}

		$currentuser = $this->currentuser();
		$db = $this->db();
		$url = $this->urlgenerator();

		$request = $db->query('', '
			SELECT p.id_page, p.page_name, p.page_title, COALESCE(pa.allow_deny, -1) AS allow_deny
			FROM {db_prefix}page AS p
			LEFT JOIN {db_prefix}page_access AS pa ON (p.id_page = pa.id_page AND pa.id_group IN ({array_int:groups}))
			WHERE p.page_name IN ({array_string:page_name})',
			[
				'page_name' => array_keys($this->unresolved['page_ids']),
				'groups' => array_values($currentuser->get_groups()),
			]
		);

		$pages = [];
		while ($row = $db->fetch_assoc($request))
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
		$db->free_result($request);

		foreach ($pages as $page)
		{
			if ($page['allow_deny'] != 0 && !$current_user->is_site_admin())
			{
				continue;
			}

			foreach ($this->unresolved['page_ids'][$page['page_name']] as $k => $page_text)
			{
				$this->data[$k] = sprintf((string) $page_text, $url->generate('pages', ['page' => $page['page_name']]), $page['page_title']);
			}
		}
	}

	protected function resolve_profile_ids(): void
	{
		if (empty($this->unresolved['profile_ids']))
		{
			return;
		}

		$currentuser = $this->currentuser();
		$db = $this->db();

		$allow_view_own = $currentuser->is_authenticated();
		$allow_view_any = $currentuser->can('profile_view');
		if ($allow_view_any || $allow_view_own)
		{
			$result = $db->query('', '
				SELECT id_member, real_name
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:member_list})
				LIMIT ' . count($profile_ids),
				[
					'member_list' => array_keys($this->unresolved['profile_ids']),
				]
			);
			while ($row = $db->fetch_assoc($result))
			{
				// If they aren't allowed to view this person's profile, skip it.
				if (!$allow_view_any && ($currentuser->get_id() != $row['id_member']))
					continue;

				// Set their action on each - session/text to sprintf.
				foreach ($this->unresolved['profile_ids'][$row['id_member']] as $k => $session_text)
				{
					$this->data[$k] = sprintf($session_text, $row['id_member'], $row['real_name']);
				}
			}
			$db->free_result($result);
		}
	}
}
