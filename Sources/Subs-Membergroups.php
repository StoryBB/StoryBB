<?php

/**
 * This file contains functions regarding manipulation of and information about membergroups.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Helper\Parser;
use StoryBB\Model\Group;
use StoryBB\Hook\Observable;

/**
 * Delete one of more membergroups.
 * Requires the manage_membergroups permission.
 * Returns true on success or false on failure.
 * Has protection against deletion of protected membergroups.
 * Deletes the permissions linked to the membergroup.
 * Takes members out of the deleted membergroups.
 * @param int|array $groups The ID of the group to delete or an array of IDs of groups to delete
 * @return bool|string True for success, otherwise an identifier as to reason for failure
 */
function deleteMembergroups($groups)
{
	global $smcFunc, $txt;

	// Make sure it's an array.
	if (!is_array($groups))
	{
		$groups = [(int) $groups];
	}
	else
	{
		$groups = array_unique($groups);

		// Make sure all groups are integer.
		foreach ($groups as $key => $value)
		{
			$groups[$key] = (int) $value;
		}
	}

	// Some groups are protected (guests, administrators, moderators).
	$protected_groups = [Group::GUEST, Group::UNGROUPED_ACCOUNT, Group::ADMINISTRATOR, Group::BOARD_MODERATOR];

	// There maybe some others as well.
	if (!allowedTo('admin_forum'))
	{
		$request = $smcFunc['db']->query('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE group_type = {int:is_protected}',
			[
				'is_protected' => 1,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$protected_groups[] = $row['id_group'];
		}
		$smcFunc['db']->free_result($request);
	}

	// Make sure they don't delete protected groups!
	$groups = array_diff($groups, array_unique($protected_groups));
	if (empty($groups))
	{
		return 'no_group_found';
	}

	// Make sure they don't try to delete a group attached to a paid subscription.
	$subscriptions = [];
	$request = $smcFunc['db']->query('', '
		SELECT id_subscribe, name, id_group, add_groups
		FROM {db_prefix}subscriptions
		ORDER BY name');
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		if (in_array($row['id_group'], $groups))
		{
			$subscriptions[] = $row['name'];
		}
		else
		{
			$add_groups = explode(',', $row['add_groups']);
			if (count(array_intersect($add_groups, $groups)) != 0)
			{
				$subscriptions[] = $row['name'];
			}
		}
	}
	$smcFunc['db']->free_result($request);
	if (!empty($subscriptions))
	{
		// Uh oh. But before we return, we need to update a language string because we want the names of the groups.
		loadLanguage('ManageMembers');
		$txt['membergroups_cannot_delete_paid'] = sprintf($txt['membergroups_cannot_delete_paid'], implode(', ', $subscriptions));
		return 'group_cannot_delete_sub';
	}

	// Log the deletion.
	$request = $smcFunc['db']->query('', '
		SELECT group_name
		FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:group_list})',
		[
			'group_list' => $groups,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		logAction('delete_group', ['group' => $row['group_name']], 'admin');
	}
	$smcFunc['db']->free_result($request);

	// Notify any plugins that this has happened.
	(new Observable\Group\Deleted($groups))->execute();

	// Remove the membergroups themselves.
	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:group_list})',
		[
			'group_list' => $groups,
		]
	);

	// Remove the permissions of the membergroups.
	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}permissions
		WHERE id_group IN ({array_int:group_list})',
		[
			'group_list' => $groups,
		]
	);
	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}board_permissions
		WHERE id_group IN ({array_int:group_list})',
		[
			'group_list' => $groups,
		]
	);
	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}group_moderators
		WHERE id_group IN ({array_int:group_list})',
		[
			'group_list' => $groups,
		]
	);
	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}moderator_groups
		WHERE id_group IN ({array_int:group_list})',
		[
			'group_list' => $groups,
		]
	);

	// Delete any outstanding requests.
	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}log_group_requests
		WHERE id_group IN ({array_int:group_list})',
		[
			'group_list' => $groups,
		]
	);

	// Update the primary groups of members.
	$smcFunc['db']->query('', '
		UPDATE {db_prefix}members
		SET id_group = {int:regular_group}
		WHERE id_group IN ({array_int:group_list})',
		[
			'group_list' => $groups,
			'regular_group' => Group::UNGROUPED_ACCOUNT,
		]
	);

	// Update any inherited groups (Lose inheritance).
	$smcFunc['db']->query('', '
		UPDATE {db_prefix}membergroups
		SET id_parent = {int:uninherited}
		WHERE id_parent IN ({array_int:group_list})',
		[
			'group_list' => $groups,
			'uninherited' => -2,
		]
	);

	// Update the additional groups of members.
	$request = $smcFunc['db']->query('', '
		SELECT id_member, additional_groups
		FROM {db_prefix}members
		WHERE FIND_IN_SET({raw:additional_groups_explode}, additional_groups) != 0',
		[
			'additional_groups_explode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
		]
	);
	$updates = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$updates[$row['additional_groups']][] = $row['id_member'];
	}
	$smcFunc['db']->free_result($request);

	foreach ($updates as $additional_groups => $memberArray)
	{
		updateMemberData($memberArray, ['additional_groups' => implode(',', array_diff(explode(',', $additional_groups), $groups))]);
	}

	// No boards can provide access to these membergroups anymore.
	$request = $smcFunc['db']->query('', '
		SELECT id_board, member_groups
		FROM {db_prefix}boards
		WHERE FIND_IN_SET({raw:member_groups_explode}, member_groups) != 0',
		[
			'member_groups_explode' => implode(', member_groups) != 0 OR FIND_IN_SET(', $groups),
		]
	);
	$updates = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$updates[$row['member_groups']][] = $row['id_board'];
	}
	$smcFunc['db']->free_result($request);

	foreach ($updates as $member_groups => $boardArray)
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}boards
			SET member_groups = {string:member_groups}
			WHERE id_board IN ({array_int:board_lists})',
			[
				'board_lists' => $boardArray,
				'member_groups' => implode(',', array_diff(explode(',', $member_groups), $groups)),
			]
		);
	}

	// Make a note of the fact that the cache may be wrong.
	updateSettings(['settings_updated' => time()]);

	// It was a success.
	return true;
}

/**
 * Remove one or more members from one or more membergroups.
 * Requires the manage_membergroups permission.
 * Function includes a protection against removing from implicit groups.
 * Non-admins are not able to remove members from the admin group.
 * @param int|array $members The ID of a member or an array of member IDs
 * @param null|array The groups to remove the member(s) from. If null, the specified members are stripped from all their membergroups.
 * @param bool $permissionCheckDone Whether we've already checked permissions prior to calling this function
 * @param bool $ignoreProtected Whether to ignore protected groups
 * @return bool Whether the operation was successful
 */
function removeMembersFromGroups($members, $groups = null, $permissionCheckDone = false, $ignoreProtected = false)
{
	global $smcFunc, $modSettings, $sourcedir;

	// You're getting nowhere without this permission, unless of course you are the group's moderator.
	if (!$permissionCheckDone)
	{
		isAllowedTo('manage_membergroups');
	}

	// Assume something will happen.
	updateSettings(['settings_updated' => time()]);

	// Cleaning the input.
	if (!is_array($members))
	{
		$members = [(int) $members];
	}
	else
	{
		$members = array_unique($members);

		// Cast the members to integer.
		foreach ($members as $key => $value)
		{
			$members[$key] = (int) $value;
		}
	}

	// Before we get started, let's check we won't leave the admin group empty!
	if ($groups === null || $groups == Group::ADMINISTRATOR || (is_array($groups) && in_array(Group::ADMINISTRATOR, $groups)))
	{
		$admins = [];
		listMembergroupMembers_Href($admins, 1);

		// Remove any admins if there are too many.
		$non_changing_admins = array_diff(array_keys($admins), $members);

		if (empty($non_changing_admins))
		{
			$members = array_diff($members, array_keys($admins));
		}
	}

	// Just in case.
	if (empty($members))
	{
		return false;
	}
	elseif ($groups === null)
	{
		// Wanna remove all groups from these members? That's easy.
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}members
			SET
				id_group = {int:regular_member},
				additional_groups = {empty}
			WHERE id_member IN ({array_int:member_list})' . (allowedTo('admin_forum') ? '' : '
				AND id_group != {int:admin_group}
				AND FIND_IN_SET({int:admin_group}, additional_groups) = 0'),
			[
				'member_list' => $members,
				'regular_member' => Group::UNGROUPED_ACCOUNT,
				'admin_group' => Group::ADMINISTRATOR,
			]
		);

		// Log what just happened.
		foreach ($members as $member)
		{
			logAction('removed_all_groups', ['member' => $member], 'admin');
		}

		return true;
	}
	elseif (!is_array($groups))
	{
		$groups = [(int) $groups];
	}
	else
	{
		$groups = array_unique($groups);

		// Make sure all groups are integer.
		foreach ($groups as $key => $value)
		{
			$groups[$key] = (int) $value;
		}
	}

	// Fetch a list of groups members cannot be assigned to explicitly, and the group names of the ones we want.
	$implicitGroups = [Group::GUEST, Group::UNGROUPED_ACCOUNT, Group::BOARD_MODERATOR];
	$request = $smcFunc['db']->query('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:group_list})',
		[
			'group_list' => $groups,
		]
	);
	$group_names = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$group_names[$row['id_group']] = $row['group_name'];
	}
	$smcFunc['db']->free_result($request);

	// Now get rid of those groups.
	$groups = array_diff($groups, $implicitGroups);

	// Don't forget the protected groups.
	if (!allowedTo('admin_forum') && !$ignoreProtected)
	{
		$request = $smcFunc['db']->query('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE group_type = {int:is_protected}',
			[
				'is_protected' => 1,
			]
		);
		$protected_groups = [Group::ADMINISTRATOR];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$protected_groups[] = $row['id_group'];
		}
		$smcFunc['db']->free_result($request);

		// If you're not an admin yourself, you can't touch protected groups!
		$groups = array_diff($groups, array_unique($protected_groups));
	}

	// Only continue if there are still groups and members left.
	if (empty($groups) || empty($members))
	{
		return false;
	}

	// First, reset those who have this as their primary group - this is the easy one.
	$log_inserts = [];
	$request = $smcFunc['db']->query('', '
		SELECT id_member, id_group
		FROM {db_prefix}members AS members
		WHERE id_group IN ({array_int:group_list})
			AND id_member IN ({array_int:member_list})',
		[
			'group_list' => $groups,
			'member_list' => $members,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$log_inserts[] = ['group' => $group_names[$row['id_group']], 'member' => $row['id_member']];
	}
	$smcFunc['db']->free_result($request);

	$smcFunc['db']->query('', '
		UPDATE {db_prefix}members
		SET id_group = {int:regular_member}
		WHERE id_group IN ({array_int:group_list})
			AND id_member IN ({array_int:member_list})',
		[
			'group_list' => $groups,
			'member_list' => $members,
			'regular_member' => Group::UNGROUPED_ACCOUNT,
		]
	);

	// Those who have it as part of their additional group must be updated the long way... sadly.
	$request = $smcFunc['db']->query('', '
		SELECT id_member, additional_groups
		FROM {db_prefix}members
		WHERE (FIND_IN_SET({raw:additional_groups_implode}, additional_groups) != 0)
			AND id_member IN ({array_int:member_list})
		LIMIT {int:limit}',
		[
			'member_list' => $members,
			'additional_groups_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
			'limit' => count($members),
		]
	);
	$updates = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// What log entries must we make for this one, eh?
		foreach (explode(',', $row['additional_groups']) as $group)
		{
			if (in_array($group, $groups))
			{
				$log_inserts[] = ['group' => $group_names[$group], 'member' => $row['id_member']];
			}
		}

		$updates[$row['additional_groups']][] = $row['id_member'];
	}
	$smcFunc['db']->free_result($request);

	foreach ($updates as $additional_groups => $memberArray)
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}members
			SET additional_groups = {string:additional_groups}
			WHERE id_member IN ({array_int:member_list})',
			[
				'member_list' => $memberArray,
				'additional_groups' => implode(',', array_diff(explode(',', $additional_groups), $groups)),
			]
		);
	}

	(new Observable\Group\AccountsRemoved($members, $groups))->execute();

	// Do the log.
	if (!empty($log_inserts))
	{
		require_once($sourcedir . '/Logging.php');
		foreach ($log_inserts as $extra)
		{
			logAction('removed_from_group', $extra, 'admin');
		}
	}

	// Mission successful.
	return true;
}

/**
 * Removes one or more groups from one or more characters, and applies to both main and additional groups.
 *
 * @param int|array Character ID or array of character IDs
 * @param int|array Group ID or array of group IDs
 * @return bool True on success
 */
function removeCharactersFromGroups($characters, $groups)
{
	global $smcFunc, $sourcedir, $modSettings;

	updateSettings(['settings_updated' => time()]);

	if (!is_array($characters))
	{
		$characters = [(int) $characters];
	}
	else
	{
		$characters = array_unique(array_map('intval', $characters));
	}

	if (!is_array($groups))
	{
		$groups = [(int) $groups];
	}
	else
	{
		$groups = array_unique(array_map('intval', $groups));
	}

	$groups = array_diff($groups, [Group::GUEST, Group::UNGROUPED_ACCOUNT, Group::BOARD_MODERATOR]);

	// Check against protected groups
	if (!allowedTo('admin_forum'))
	{
		$request = $smcFunc['db']->query('', '
			SELECT group_type
			FROM {db_prefix}membergroups
			WHERE id_group IN ({array_int:current_group})',
			[
				'current_group' => $groups,
			]
		);
		$protected = [];
		while ($row = $smcFunc['db']->fetch_row($request))
		{
			$protected[] = $row[0];
		}
		$smcFunc['db']->free_result($request);

		$groups = array_diff($groups, $protected);
	}

	if (empty($groups) || empty($characters))
		return false;

	$request = $smcFunc['db']->query('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:current_group})',
		[
			'current_group' => $groups,
		]
	);
	$group_names = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$group_names[$row['id_group']] = $row['group_name'];
	}
	$smcFunc['db']->free_result($request);

	// First, reset those who have this as their primary group - this is the easy one.
	$log_inserts = [];
	$request = $smcFunc['db']->query('', '
		SELECT id_member, id_character, character_name, main_char_group
		FROM {db_prefix}characters AS characters
		WHERE main_char_group IN ({array_int:group_list})
			AND id_character IN ({array_int:char_list})',
		[
			'group_list' => $groups,
			'char_list' => $characters,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
		$log_inserts[] = ['group' => $group_names[$row['main_char_group']], 'member' => $row['id_member'], 'character' => $row['character_name']];
	$smcFunc['db']->free_result($request);

	$smcFunc['db']->query('', '
		UPDATE {db_prefix}characters
		SET main_char_group = {int:regular_member}
		WHERE main_char_group IN ({array_int:group_list})
			AND id_character IN ({array_int:char_list})',
		[
			'group_list' => $groups,
			'char_list' => $characters,
			'regular_member' => 0,
		]
	);

	// Those who have it as part of their additional group must be updated the long way... sadly.
	$request = $smcFunc['db']->query('', '
		SELECT id_member, id_character, character_name, char_groups
		FROM {db_prefix}characters
		WHERE (FIND_IN_SET({raw:additional_groups_implode}, char_groups) != 0)
			AND id_character IN ({array_int:char_list})
		LIMIT ' . count($characters),
		[
			'char_list' => $characters,
			'additional_groups_implode' => implode(', char_groups) != 0 OR FIND_IN_SET(', $groups),
		]
	);
	$updates = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// What log entries must we make for this one, eh?
		foreach (explode(',', $row['char_groups']) as $id_group)
		{
			if (in_array($id_group, $groups))
			{
				$log_inserts[] = ['group' => $group_names[$id_group], 'member' => $row['id_member'], 'character' => $row['character_name']];
			}
		}

		$updates[$row['char_groups']][] = $row['id_member'];
	}
	$smcFunc['db']->free_result($request);

	foreach ($updates as $char_groups => $memberArray)
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}characters
			SET char_groups = {string:char_groups}
			WHERE id_member IN ({array_int:member_list})',
			[
				'member_list' => $memberArray,
				'char_groups' => implode(',', array_diff(explode(',', $char_groups), $groups)),
			]
		);

	(new Observable\Group\CharactersRemoved($characters, $groups))->execute();

	// Do the log.
	if (!empty($log_inserts))
	{
		require_once($sourcedir . '/Logging.php');
		foreach ($log_inserts as $extra)
		{
			logAction('char_removed_from_group', $extra, 'admin');
		}
	}

	return true;
}

/**
 * Add one or more members to a membergroup
 *
 * Requires the manage_membergroups permission.
 * Function has protection against adding members to implicit groups.
 * Non-admins are not able to add members to the admin group.
 *
 * @param int|array $members A single member or an array containing the IDs of members
 * @param int $group The group to add them to
 * @param string $type Specifies whether the group is added as primary or as additional group.
 * Supported types:
 * 	- only_primary      - Assigns a membergroup as primary membergroup, but only
 * 						  if a member has not yet a primary membergroup assigned,
 * 						  unless the member is already part of the membergroup.
 * 	- only_additional   - Assigns a membergroup to the additional membergroups,
 * 						  unless the member is already part of the membergroup.
 * 	- force_primary     - Assigns a membergroup as primary membergroup no matter
 * 						  what the previous primary membergroup was.
 * 	- auto              - Assigns a membergroup to the primary group if it's still
 * 						  available. If not, assign it to the additional group.
 * @param bool $permissionCheckDone Whether we've already done a permission check
 * @param bool $ignoreProtected Whether to ignore protected groups
 * @return bool Whether the operation was successful
 */
function addMembersToGroup($members, $group, $type = 'auto', $permissionCheckDone = false, $ignoreProtected = false)
{
	global $smcFunc, $sourcedir;

	// Show your licence, but only if it hasn't been done yet.
	if (!$permissionCheckDone)
	{
		isAllowedTo('manage_membergroups');
	}

	// Make sure we don't keep old stuff cached.
	updateSettings(['settings_updated' => time()]);

	if (!is_array($members))
	{
		$members = [(int) $members];
	}
	else
	{
		$members = array_unique($members);

		// Make sure all members are integer.
		foreach ($members as $key => $value)
		{
			$members[$key] = (int) $value;
		}
	}
	$group = (int) $group;

	// Some groups just don't like explicitly having members.
	$implicitGroups = [Group::GUEST, Group::UNGROUPED_ACCOUNT, Group::BOARD_MODERATOR];
	$request = $smcFunc['db']->query('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE id_group = {int:current_group}',
		[
			'current_group' => $group,
		]
	);
	$group_names = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$group_names[$row['id_group']] = $row['group_name'];
	}
	$smcFunc['db']->free_result($request);

	// Sorry, you can't join an implicit group.
	if (in_array($group, $implicitGroups) || empty($members))
	{
		return false;
	}

	// Only admins can add admins...
	if (!allowedTo('admin_forum') && $group == Group::ADMINISTRATOR)
	{
		return false;
	}
	// ... and assign protected groups!
	elseif (!allowedTo('admin_forum') && !$ignoreProtected)
	{
		$request = $smcFunc['db']->query('', '
			SELECT group_type
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT {int:limit}',
			[
				'current_group' => $group,
				'limit' => 1,
			]
		);
		list ($is_protected) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		// Is it protected?
		if ($is_protected == 1)
			return false;
	}

	// Do the actual updates.
	if ($type == 'only_additional')
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}members
			SET additional_groups = CASE WHEN additional_groups = {empty} THEN {string:id_group_string} ELSE CONCAT(additional_groups, {string:id_group_string_extend}) END
			WHERE id_member IN ({array_int:member_list})
				AND id_group != {int:id_group}
				AND FIND_IN_SET({int:id_group}, additional_groups) = 0',
			[
				'member_list' => $members,
				'id_group' => $group,
				'id_group_string' => (string) $group,
				'id_group_string_extend' => ',' . $group,
			]
		);
	}
	elseif ($type == 'only_primary' || $type == 'force_primary')
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}members
			SET id_group = {int:id_group}
			WHERE id_member IN ({array_int:member_list})' . ($type == 'force_primary' ? '' : '
				AND id_group = {int:regular_group}
				AND FIND_IN_SET({int:id_group}, additional_groups) = 0'),
			[
				'member_list' => $members,
				'id_group' => $group,
				'regular_group' => Group::UNGROUPED_ACCOUNT,
			]
		);
	}
	elseif ($type == 'auto')
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}members
			SET
				id_group = CASE WHEN id_group = {int:regular_group} THEN {int:id_group} ELSE id_group END,
				additional_groups = CASE WHEN id_group = {int:id_group} THEN additional_groups
					WHEN additional_groups = {empty} THEN {string:id_group_string}
					ELSE CONCAT(additional_groups, {string:id_group_string_extend}) END
			WHERE id_member IN ({array_int:member_list})
				AND id_group != {int:id_group}
				AND FIND_IN_SET({int:id_group}, additional_groups) = 0',
			[
				'member_list' => $members,
				'regular_group' => Group::UNGROUPED_ACCOUNT,
				'id_group' => $group,
				'id_group_string' => (string) $group,
				'id_group_string_extend' => ',' . $group,
			]
		);
	}
	// Ack!!?  What happened?
	else
	{
		trigger_error('addMembersToGroup(): Unknown type \'' . $type . '\'', E_USER_WARNING);
	}

	(new Observable\Group\AccountsAdded($members, $group))->execute();

	// Log the data.
	require_once($sourcedir . '/Logging.php');
	foreach ($members as $member)
	{
		logAction('added_to_group', ['group' => $group_names[$group], 'member' => $member], 'admin');
	}

	return true;
}

/**
 * Adds characters into a group (or more accurately, adds a group to characters' settings) - additional groups only.
 *
 * @param int|array A character ID or array of character ids
 * @param int $group A group to apply
 * @return bool True on success
 */
function addCharactersToGroup($characters, $group)
{
	global $smcFunc, $sourcedir;

	updateSettings(['settings_updated' => time()]);

	if (!is_array($characters))
	{
		$characters = [(int) $characters];
	}
	else
	{
		$characters = array_unique(array_map('intval', $characters));
	}

	$group = (int) $group;
	if (in_array($group, [Group::GUEST, Group::UNGROUPED_ACCOUNT, Group::BOARD_MODERATOR]))
	{
		return false;
	}

	// Check against protected groups
	if (!allowedTo('admin_forum'))
	{
		$request = $smcFunc['db']->query('', '
			SELECT group_type
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT {int:limit}',
			[
				'current_group' => $group,
				'limit' => 1,
			]
		);
		list ($is_protected) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		// Is it protected?
		if ($is_protected == 1)
		{
			return false;
		}
	}

	// Do the dirty deed
	$smcFunc['db']->query('', '
		UPDATE {db_prefix}characters
		SET char_groups = CASE WHEN char_groups = {empty} THEN {string:id_group_string} ELSE CONCAT(char_groups, {string:id_group_string_extend}) END
		WHERE id_character IN ({array_int:char_list})
			AND main_char_group != {int:id_group}
			AND FIND_IN_SET({int:id_group}, char_groups) = 0',
		[
			'char_list' => $characters,
			'id_group' => $group,
			'id_group_string' => (string) $group,
			'id_group_string_extend' => ',' . $group,
		]
	);

	// Get the members for these characters.
	$members = [];
	$request = $smcFunc['db']->query('', '
		SELECT id_member, id_character, character_name
		FROM {db_prefix}characters
		WHERE id_character IN ({array_int:char_list})',
		[
			'char_list' => $characters,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$members[$row['id_character']] = $row;
	}
	$smcFunc['db']->free_result($request);

	$request = $smcFunc['db']->query('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE id_group = {int:current_group}',
		[
			'current_group' => $group,
		]
	);
	$group_names = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$group_names[$row['id_group']] = $row['group_name'];
	}
	$smcFunc['db']->free_result($request);

	(new Observable\Group\CharactersAdded($characters, $group))->execute();

	// Log the data.
	require_once($sourcedir . '/Logging.php');
	foreach ($characters as $character)
	{
		logAction('char_added_to_group', ['group' => $group_names[$group], 'member' => $members[$character]['id_member'], 'character' => $members[$character]['character_name']], 'admin');
	}

	return true;
}

/**
 * Gets the members of a supplied membergroup
 * Returns them as a link for display
 *
 * @param array &$members The IDs of the members
 * @param int $membergroup The ID of the group
 * @param int $limit How many members to show (null for no limit)
 * @return bool True if there are more members to display, false otherwise
 */
function listMembergroupMembers_Href(&$members, $membergroup, $limit = null)
{
	global $scripturl, $smcFunc;

	$request = $smcFunc['db']->query('', '
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE id_group = {int:id_group} OR FIND_IN_SET({int:id_group}, additional_groups) != 0' . ($limit === null ? '' : '
		LIMIT ' . ($limit + 1)),
		[
			'id_group' => $membergroup,
		]
	);
	$members = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$members[$row['id_member']] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
	}
	$smcFunc['db']->free_result($request);

	// If there are more than $limit members, add a 'more' link.
	if ($limit !== null && count($members) > $limit)
	{
		array_pop($members);
		return true;
	}
	else
		return false;
}

/**
 * Retrieve a list of (visible) membergroups used by the cache.
 *
 * @return array An array of information about the cache
 */
function cache_getMembergroupList()
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

/**
 * Helper function to generate a list of membergroups for display
 *
 * @param int $start What item to start with (not used here)
 * @param int $items_per_page How many items to show on each page (not used here)
 * @param string $sort An SQL query indicating how to sort the results
 * @param string $membergroup_type Should be 'post_count' for post groups or anything else for regular groups
 * @return array An array of group member info for the list
 */
function list_getMembergroups($start, $items_per_page, $sort, $membergroup_type)
{
	global $scripturl, $context, $settings, $smcFunc, $user_info, $txt;

	$request = $smcFunc['db']->query('substring_membergroups', '
		SELECT mg.id_group, mg.group_name, mg.description, mg.group_type, mg.online_color, mg.hidden,
			mg.icons, COALESCE(gm.id_member, 0) AS can_moderate, 0 AS num_members, is_character
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}group_moderators AS gm ON (gm.id_group = mg.id_group AND gm.id_member = {int:current_member})
		WHERE is_character = {int:is_character}' . (allowedTo('admin_forum') ? '' : '
			AND mg.id_group != {int:mod_group}') . '
		ORDER BY {raw:sort}',
		[
			'current_member' => $user_info['id'],
			'is_character' => ($membergroup_type === 'character' ? 1 : 0),
			'mod_group' => Group::BOARD_MODERATOR,
			'sort' => $sort,
		]
	);

	// Start collecting the data.
	$groups = [];
	$group_ids = [];
	$context['can_moderate'] = allowedTo('manage_membergroups');
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// We only list the groups they can see.
		if ($row['hidden'] && !$row['can_moderate'] && !allowedTo('manage_membergroups'))
		{
			continue;
		}

		$row['icons'] = explode('#', $row['icons']);

		$groups[$row['id_group']] = [
			'id_group' => $row['id_group'],
			'group_name' => $row['group_name'],
			'is_character' => $row['is_character'],
			'desc' => Parser::parse_bbc($row['description'], false, '', $context['description_allowed_tags']),
			'online_color' => $row['online_color'],
			'type' => $row['group_type'],
			'num_members' => $row['num_members'],
			'moderators' => [],
			'icons' => !empty($row['icons'][0]) && !empty($row['icons'][1]) ? str_repeat('<img src="' . $settings['images_url'] . '/membericons/' . $row['icons'][1] . '" alt="*">', $row['icons'][0]) : $txt['membergroup_no_badge'],
		];

		$context['can_moderate'] |= $row['can_moderate'];
		$group_ids[] = $row['id_group'];
	}
	$smcFunc['db']->free_result($request);

	// If we found any membergroups, get the amount of members in them.
	if (!empty($group_ids))
	{
		$query = $smcFunc['db']->query('', '
			SELECT id_group, COUNT(*) AS num_members
			FROM {db_prefix}members
			WHERE id_group IN ({array_int:group_list})
			GROUP BY id_group',
			[
				'group_list' => $group_ids,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($query))
		{
			if (isset($groups[$row['id_group']]))
			{
				$groups[$row['id_group']]['num_members'] += $row['num_members'];
			}
		}
		$smcFunc['db']->free_result($query);

		// And collect all the characters too.
		$query = $smcFunc['db']->query('', '
			SELECT main_char_group, COUNT(*) AS num_members
			FROM {db_prefix}characters
			WHERE main_char_group IN ({array_int:group_list})
			GROUP BY main_char_group',
			[
				'group_list' => $group_ids,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($query))
		{
			if (isset($groups[$row['main_char_group']]))
			{
				$groups[$row['main_char_group']]['num_members'] += $row['num_members'];
			}
		}
		$smcFunc['db']->free_result($query);

		if ($context['can_moderate'])
		{
			$query = $smcFunc['db']->query('', '
				SELECT mg.id_group, COUNT(*) AS num_members
				FROM {db_prefix}membergroups AS mg
					INNER JOIN {db_prefix}characters AS chars ON (chars.char_groups != {empty}
						AND chars.main_char_group != mg.id_group
						AND FIND_IN_SET(mg.id_group, chars.char_groups) != 0)
				WHERE mg.id_group IN ({array_int:group_list})
				GROUP BY mg.id_group',
				[
					'group_list' => $group_ids,
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($query))
			{
				$groups[$row['id_group']]['num_members'] += $row['num_members'];
			}
			$smcFunc['db']->free_result($query);
		}

		// Only do additional groups if we can moderate...
		if ($context['can_moderate'])
		{
			$query = $smcFunc['db']->query('', '
				SELECT mg.id_group, COUNT(*) AS num_members
				FROM {db_prefix}membergroups AS mg
					INNER JOIN {db_prefix}members AS mem ON (mem.additional_groups != {empty}
						AND mem.id_group != mg.id_group
						AND FIND_IN_SET(mg.id_group, mem.additional_groups) != 0)
				WHERE mg.id_group IN ({array_int:group_list})
				GROUP BY mg.id_group',
				[
					'group_list' => $group_ids,
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($query))
			{
				$groups[$row['id_group']]['num_members'] += $row['num_members'];
			}
			$smcFunc['db']->free_result($query);
		}

		$query = $smcFunc['db']->query('', '
			SELECT mods.id_group, mods.id_member, mem.member_name, mem.real_name
			FROM {db_prefix}group_moderators AS mods
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
			WHERE mods.id_group IN ({array_int:group_list})',
			[
				'group_list' => $group_ids,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($query))
		{
			$groups[$row['id_group']]['moderators'][] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
		}
		$smcFunc['db']->free_result($query);
	}

	// Apply manual sorting if the 'number of members' column is selected.
	if (substr($sort, 0, 1) == '1' || strpos($sort, ', 1') !== false)
	{
		$sort_ascending = strpos($sort, 'DESC') === false;

		foreach ($groups as $group)
		{
			$sort_array[] = $group['id_group'] != 3 ? (int) $group['num_members'] : -1;
		}

		array_multisort($sort_array, $sort_ascending ? SORT_ASC : SORT_DESC, SORT_REGULAR, $groups);
	}

	return $groups;
}
