<?php

/**
 * This class handles behaviours for Behat tests within StoryBB.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Behat;

use StoryBB\Behat;
use StoryBB\Behat\Helper;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\DriverException;
use WebDriver\Exception\NoSuchElement;
use WebDriver\Exception\StaleElementReference;
use Behat\MinkExtension\Context\RawMinkContext;

/**
 * Defines application features from the specific context.
 */
class General extends RawMinkContext implements Context
{
	/**
	 * Wait for a few seconds in the test
	 * @When I wait for :length second(s)
	 * @param int $length How many seconds to wait for.
	 */
	public function iWaitForSecond($length)
	{
		sleep($length);
	}

	/**
	 * Creates data in the database, e.g. users or boards
	 * @Given the following :type exist(s):
	 * @param string $type A type of data to create in the database
	 * @param TableNode $table The data to insert
	 */
	public function theFollowingExist($type, TableNode $table)
	{
		$types = [
			'user' => 'create_users',
			'users' => 'create_users',
			'character' => 'create_characters',
			'characters' => 'create_characters',
			'character group member' => 'add_characters_to_group',
			'character group members' => 'add_characters_to_group',
			'group member' => 'add_users_to_group',
			'group members' => 'add_users_to_group',
			'board' => 'create_boards',
			'boards' => 'create_boards',
			'group' => 'create_groups',
			'groups' => 'create_groups',
		];
		if (!isset($types[$type]))
		{
			throw new ExpectationException('Unknown type "' . $type . '" to add to instance.', $this->getSession());
		}

		$method = $types[$type];
		$this->$method($table);

		updateSettings(['settings_updated' => time()]);
	}

	/**
	 * Creates users in the database.
	 * @param TableNode $table The users to be added
	 * @throws ExpectationException if the list of users cannot be added
	 */
	private function create_users(TableNode $table)
	{
		global $user_info, $sourcedir, $smcFunc, $context, $mtitle;
		// We need to fudge the details to be able to call registerMember - but this won't affect running state.
		// This only applies inside the test runner - not in any of the things prodded by the tests themselves.
		$user_info['is_guest'] = false;
		$user_info['groups'] = [1];
		$user_info['permissions'] = ['moderate_forum', 'admin_forum'];
		$user_info['is_admin'] = true;
		$user_info['id'] = 0;
		$user_info['ip'] = '127.0.0.1';
		$user_info['query_see_board'] = '1=1';
		$user_info['query_wanna_see_board'] = '1=1';
		require_once($sourcedir . '/Subs-Members.php');
		$context['forum_name_html_safe'] = $mtitle;

		foreach ($table->getHash() as $user_to_register)
		{
			if (empty($user_to_register['username']))
			{
				throw new ExpectationException('Username was not supplied', $this->getSession());
			}
			$regOptions = [
				'interface' => 'admin',
				'require' => 'nothing',
				'username' => $user_to_register['username'],
				'email' => isset($user_to_register['email']) ? $user_to_register['email'] : $user_to_register['username'] . '@example.com',
				'password' => 'password',
				'send_welcome_email' => false,
			];
			$regOptions['password_check'] = $regOptions['password'];

			if (isset($user_to_register['primary group']))
			{
				if (strtolower($user_to_register['primary group']) == 'regular members')
				{
					$regOptions['memberGroup'] = 0;
				}
				else
				{
					$possible_groups = [];
					$request = $smcFunc['db_query']('', '
                        SELECT id_group, group_name
                        FROM {db_prefix}membergroups',
						[]
					);
					while ($row = $smcFunc['db_fetch_assoc']($request))
					{
						$row['group_name'] = strtolower($row['group_name']);
						if (isset($possible_groups[$row['group_name']]))
						{
							throw new ExpectationException('Group "' . $row['group_name'] . '"" to be assigned to user "' . $user_to_register['username'] . '" is ambiguous.', $this->getSession());
						}
						$possible_groups[$row['group_name']] = $row['id_group'];
					}
					$smcFunc['db_free_result']($request);

					$group_name = strtolower($user_to_register['primary group']);
					if (!isset($possible_groups[$group_name]))
					{
						throw new ExpectationException('Group "' . $group_name . '" to be assigned to user "' . $user_to_register['username'] . '" does not exist.', $this->getSession());
					}

					$regOptions['memberGroup'] = (int) $possible_groups[$group_name];
				}
			}

			$new_user_id = registerMember($regOptions);
			if (empty($new_user_id) || is_array($new_user_id))
			{
				throw new ExpectationException('Error creating user "' . $user_to_register['username'] . '"', $this->getSession());
			}
		}
	}

	/**
	 * Creates characters in the database.
	 * @param TableNode $table The characters to be added
	 * @throws ExpectationException if the list of characters cannot be added
	 */
	private function create_characters(TableNode $table)
	{
		global $smcFunc;

		// First get all the users we're going to add for.
		$users = [];
		$character_list = $table->getHash();
		foreach ($character_list as $line => $character_to_create)
		{
			if (empty($character_to_create['character_name']))
			{
				throw new ExpectationException('Missing character name on row ' . $line, $this->getSession());
			}
			if (empty($character_to_create['username']))
			{
				throw new ExpectationException('Could not create character "' . $character_to_create['character_name'] . '", user not specified', $this->getSession());
			}
			$users[] = $character_to_create['username'];
		}
		if (empty($users))
		{
			throw new ExpectationException('No characters to create.', $this->getSession());
		}

		$userids = $this->get_user_ids($users);

		// Check that we matched everyone.
		foreach ($userids as $username => $id)
		{
			if (empty($id))
			{
				throw new ExpectationException('User "' . $username . '" could not be found.', $this->getSession());
			}
		}

		// And prepare a bulk insert.
		$rows_to_insert = [];
		$now = time();
		foreach ($character_list as $character_to_create)
		{
			$rows_to_insert[] = [
				$userids[$character_to_create['username']], $character_to_create['character_name'], '',
				'', 0, 0,
				'', $now, $now,
				0, 0, '',
				0, 0
			];
		}

		$smcFunc['db_insert']('insert',
			'{db_prefix}characters',
			['id_member' => 'int', 'character_name' => 'string', 'avatar' => 'string',
				'signature' => 'string', 'id_theme' => 'int', 'posts' => 'int',
				'age' => 'string', 'date_created' => 'int', 'last_active' => 'int',
				'is_main' => 'int', 'main_char_group' => 'int', 'char_groups' => 'string',
				'char_sheet' => 'int', 'retired' => 'int'],
			$rows_to_insert,
			['id_character']
		);
	}

	/**
	 * Finds users in the database by username
	 * @param array $usernames Array of strings listing usernames to be found
	 * @return array Returns a key/value array of names to user ids
	 */
	private function get_user_ids(array $usernames): array
	{
		global $smcFunc;

		$userids = [];
		foreach ($usernames as $username)
		{
			$userids[$username] = 0;
		}
		if (empty($userids))
		{
			return [];
		}

		$request = $smcFunc['db_query']('', '
            SELECT id_member, member_name
            FROM {db_prefix}members
            WHERE member_name IN ({array_string:usernames})',
			[
				'usernames' => array_keys($userids)
			]
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$userids[$row['member_name']] = $row['id_member'];
		}
		$smcFunc['db_free_result']($request);

		return $userids;
	}

	/**
	 * Finds characters in the database by character name
	 * @param array $charnames Array of strings listing characters names to be found
	 * @return array Returns a key/value array of names to character ids
	 */
	private function get_character_ids(array $charnames): array
	{
		global $smcFunc;

		$charids = [];
		foreach ($charnames as $charname)
		{
			$charids[$charname] = 0;
		}
		if (empty($charids))
		{
			return [];
		}

		$request = $smcFunc['db_query']('', '
            SELECT id_character, character_name
            FROM {db_prefix}characters
            WHERE character_name IN ({array_string:charnames})',
			[
				'charnames' => array_keys($charids)
			]
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$charids[$row['character_name']] = $row['id_character'];
		}
		$smcFunc['db_free_result']($request);

		return $charids;
	}

	/**
	 * Finds groups in the database by group name
	 * @param array $groupnames Array of strings listing group names to be found
	 * @return array Returns a key/value array of names to group ids
	 */
	private function get_group_ids(array $groupnames): array
	{
		global $smcFunc;

		$group_ids = [];
		foreach ($groupnames as $group_name)
		{
			$group_ids[$group_name] = 0;
		}
		if (empty($group_ids))
		{
			return [];
		}

		$request = $smcFunc['db_query']('', '
            SELECT id_group, group_name
            FROM {db_prefix}membergroups
            WHERE group_name IN ({array_string:groupnames})',
			[
				'groupnames' => array_keys($group_ids)
			]
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$group_ids[$row['group_name']] = $row['id_group'];
		}
		$smcFunc['db_free_result']($request);

		return $group_ids;
	}

	/**
	 * Creates boards in the database.
	 * @param TableNode $table The boards to be added
	 * @throws ExpectationException if the list of boards cannot be added
	 */
	private function create_boards(TableNode $table)
	{
		global $smcFunc, $sourcedir, $boards, $cat_tree;
		require_once($sourcedir . '/Subs-Boards.php');
		/*
				And the following "boards" exist:
			| board name          | board parent | can see                                | cannot see |
			| Common Rooms        | none         | Regular Members, Gryffindor, Slytherin |            |
		*/
		foreach ($table->getHash() as $line => $board_to_create)
		{
			if (empty($board_to_create['board name']))
			{
				throw new ExpectationException('No board name given on line ' . $line, $this->getSession());
			}
			if (empty($board_to_create['board parent']))
			{
				throw new ExpectationException('No board parent given on line ' . $line, $this->getSession());
			}

			$boardOptions = [
				'board_name' => $smcFunc['htmlspecialchars']($board_to_create['board name'], ENT_QUOTES),
				'access_groups' => [],
				'deny_groups' => [],
				'in_character' => 0,
			];

			// Work out where we're putting this.
			if ($board_to_create['board parent'] == 'none')
			{
				// We're adding it to the end of the first category, which we need to identify.
				$request = $smcFunc['db_query']('', '
                    SELECT id_cat
                    FROM {db_prefix}categories
                    ORDER BY cat_order
                    LIMIT 1');
				if ($smcFunc['db_num_rows']($request))
				{
					list($boardOptions['target_category']) = $smcFunc['db_fetch_row']($request);
				}
				$smcFunc['db_free_result']($request);
				if (empty($boardOptions['target_category']))
				{
					throw new ExpectationException('No category exists to add board "' . $board_to_create['board name'] . '" to.', $this->getSession());
				}

				$boardOptions['move_to'] = 'bottom'; // Place it at the bottom of the category.
			}
			else
			{
				// We've asked to find a board and attach to that, let's find its details.
				$request = $smcFunc['db_query']('', '
                    SELECT id_board, id_cat
                    FROM {db_prefix}boards
                    WHERE name = {string:board_name}
                    LIMIT 1',
					[
						'board_name' => $board_to_create['board parent']
					]
				);
				if ($smcFunc['db_num_rows']($request))
				{
					$row = $smcFunc['db_fetch_assoc']($request);
					$boardOptions['target_category'] = $row['id_cat'];
					$boardOptions['move_to'] = 'child';
					$boardOptions['target_board'] = $row['id_board'];
				}
				$smcFunc['db_free_result']($request);
				if (empty($boardOptions['target_board']))
				{
					throw new ExpectationException('Board "' . $board_to_create['board name'] . '" is meant to be a sub-board of "' . $board_to_create['board parent'] . '" but this was not found.', $this->getSession());
				}
			}

			// Work out if we're allowing or denying anyone.
			if (!empty($board_to_create['can see']))
			{
				$groups = explode(',', $board_to_create['can see']);
				$groups = array_map('trim', $groups);
				$group_ids = $this->get_group_ids($groups);
				if (isset($group_ids['Guests']))
				{
					$group_ids['Guests'] = -1;
				}
				foreach ($group_ids as $group_name => $group_id)
				{
					if (empty($group_id) && $group_name != 'Regular Members')
					{
						// Regular Members intentionally have an id of 0, but we need to screen the rest.
						throw new ExpectationException('Group "' . $group_name . '" is meant to be added to "' . $board_to_create['board name'] . '" but it does not exist.', $this->getSession());
					}
				}
				$boardOptions['access_groups'] = array_unique(array_values($group_ids));
			}
			if (!empty($board_to_create['cannot see']))
			{
				$groups = explode(',', $board_to_create['cannot see']);
				$groups = array_map('trim', $groups);
				$group_ids = $this->get_group_ids($groups);
				if (isset($group_ids['Guests']))
				{
					$group_ids['Guests'] = -1;
				}
				foreach ($group_ids as $group_name => $group_id)
				{
					if (!$group_id && $group_name != 'Regular Members')
					{
						// Regular Members intentionally have an id of 0, but we need to screen the rest.
						throw new ExpectationException('Group "' . $group_name . '" is meant to be added to "' . $board_to_create['board name'] . '" but it does not exist.', $this->getSession());
					}
				}
				$boardOptions['deny_groups'] = array_unique(array_values($group_ids));
			}

			// And finally actually create the board.
			createBoard($boardOptions);
		}
	}

	/**
	 * Creates groups in the database.
	 * @param TableNode $table The groups to be added
	 * @throws ExpectationException if the list of groups cannot be added
	 */
	private function create_groups(TableNode $table)
	{
		global $smcFunc;

		// Check there's no duplicates.
		$groups_to_create = $table->getHash();

		$groupnames = [];
		foreach ($groups_to_create as $line => $group_to_create)
		{
			if (empty($group_to_create['group_name']))
			{
				throw new ExpectationException('No group name given on line ' . $line, $this->getSession());
			}
			$groupnames[] = $group_to_create['group_name'];
		}
		$group_ids = $this->get_group_ids($groupnames);
		foreach ($group_ids as $groupname => $group_id)
		{
			if ($group_id)
			{
				throw new ExpectationException('Group "' . $groupname . '" cannot be created; it already exists.', $this->getSession());
			}
		}

		foreach ($groups_to_create as $line => $group_to_create)
		{
			if (empty($group_to_create['group level']))
			{
				throw new ExpectationException('No group level given on line ' . $line, $this->getSession());
			}
			if (!in_array($group_to_create['group level'], ['account', 'character']))
			{
				throw new ExpectationException('Invalid group level "' . $group_to_create['group level'] . '" on line ' . $line, $this->getSession());
			}

			$group_type = 0;
			if (isset($group_to_create['group type']))
			{
				switch($group_to_create['group type'])
				{
					case 'private':
						$group_type = 0;
						break;
					case 'protected':
						$group_type = 1;
						break;
					case 'requestable':
						$group_type = 2;
						break;
					case 'joinable':
					case 'free':
						$group_type = 3;
						break;
					default:
						throw new ExpectationException('Invalid group type "' . $group_to_create['group type'] . '" on line ' . $line, $this->getSession());
				}
			}

			// Inserting the new group.
			$id_group = $smcFunc['db_insert']('',
				'{db_prefix}membergroups',
				[
					'description' => 'string', 'group_name' => 'string-80',
					'icons' => 'string', 'online_color' => 'string', 'group_type' => 'int', 'is_character' => 'int',
				],
				[
					'', $group_to_create['group_name'],
					'1#icon.png', '', $group_type, $group_to_create['group level'] == 'character' ? 1 : 0,
				],
				['id_group'],
				1
			);

			// Groups added in this way inherit regular member permissions - main+board if account, board if character.
			if ($group_to_create['group level'] == 'account')
			{
				$request = $smcFunc['db_query']('', '
                    SELECT permission, add_deny
                    FROM {db_prefix}permissions
                    WHERE id_group = {int:copy_from}',
					[
						'copy_from' => 0,
					]
				);
				$inserts = [];
				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					$inserts[] = [$id_group, $row['permission'], $row['add_deny']];
				}
				$smcFunc['db_free_result']($request);

				if (!empty($inserts))
				{
					$smcFunc['db_insert']('insert',
						'{db_prefix}permissions',
						['id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'],
						$inserts,
						['id_group', 'permission']
					);
				}
			}

			// And now board-profile permissions.
			$request = $smcFunc['db_query']('', '
                SELECT id_profile, permission, add_deny
                FROM {db_prefix}board_permissions
                WHERE id_group = {int:copy_from}',
				[
					'copy_from' => 0,
				]
			);
			$inserts = [];
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$inserts[] = [$id_group, $row['id_profile'], $row['permission'], $row['add_deny']];
			}
			$smcFunc['db_free_result']($request);

			if (!empty($inserts))
			{
				$smcFunc['db_insert']('insert',
					'{db_prefix}board_permissions',
					['id_group' => 'int', 'id_profile' => 'int', 'permission' => 'string', 'add_deny' => 'int'],
					$inserts,
					['id_group', 'id_profile', 'permission']
				);
			}
		}
	}

	/**
	 * Adds characters to groups for testing purposes
	 * @param TableNode $table A table listing characters and groups to be added
	 * @throws ExpectationException if the list cannot be added
	 */
	private function add_characters_to_group(TableNode $table)
	{
		global $sourcedir;

		$characters = [];
		$groups = [];
		$associations = $table->getHash();

		foreach ($associations as $line => $association)
		{
			if (empty($association['character name']))
			{
				throw new ExpectationException('No character name given on line ' . $line, $this->getSession());
			}
			if (empty($association['group']))
			{
				throw new ExpectationException('No group given on line ' . $line, $this->getSession());
			}

			$characters[] = $association['character name'];
			$groups[] = $association['group'];
		}

		$character_ids = $this->get_character_ids($characters);
		$group_ids = $this->get_group_ids($groups);

		foreach ($character_ids as $character => $id)
		{
			if (empty($id))
			{
				throw new ExpectationException('Character "' . $character . '" could not be found', $this->getSession());
			}
		}
		foreach ($group_ids as $group => $id)
		{
			if (empty($id))
			{
				throw new ExpectationException('Group "' . $group . '" could not be found', $this->getSession());
			}
		}

		require_once($sourcedir . '/Subs-Membergroups.php');
		foreach ($associations as $association)
		{
			addCharactersToGroup($character_ids[$association['character name']], $group_ids[$association['group']]);
		}
	}

	/**
	 * Adds users to groups for testing purposes
	 * @param TableNode $table A table listing users and groups to be added
	 * @throws ExpectationException if the list cannot be added
	 */
	private function add_users_to_group(TableNode $table)
	{
		global $sourcedir;

		$users = [];
		$groups = [];
		$associations = $table->getHash();

		foreach ($associations as $line => $association)
		{
			if (empty($association['username']))
			{
				throw new ExpectationException('No user name given on line ' . $line, $this->getSession());
			}
			if (empty($association['group']))
			{
				throw new ExpectationException('No group given on line ' . $line, $this->getSession());
			}

			$users[] = $association['username'];
			$groups[] = $association['group'];
		}

		$user_ids = $this->get_user_ids($users);
		$group_ids = $this->get_group_ids($groups);

		foreach ($user_ids as $user => $id)
		{
			if (empty($id))
			{
				throw new ExpectationException('User "' . $user . '" could not be found', $this->getSession());
			}
		}
		foreach ($group_ids as $group => $id)
		{
			if (empty($id))
			{
				throw new ExpectationException('Group "' . $group . '" could not be found', $this->getSession());
			}
		}

		require_once($sourcedir . '/Subs-Membergroups.php');
		foreach ($associations as $association)
		{
			addMembersToGroup($user_ids[$association['username']], $group_ids[$association['group']], 'auto', true, true);
		}
	}
}
