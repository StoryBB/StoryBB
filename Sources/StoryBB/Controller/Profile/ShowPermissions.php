<?php

/**
 * Displays the summary profile page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Helper\IP;

class ShowPermissions extends AbstractProfileController
{
	public function display_action()
	{
		global $txt, $board;
		global $user_profile, $context, $sourcedir, $smcFunc;

		// Verify if the user has sufficient permissions.
		isAllowedTo('manage_permissions');

		loadLanguage('ManagePermissions');
		loadLanguage('Admin');

		// Load all the permission profiles.
		require_once($sourcedir . '/ManagePermissions.php');
		loadPermissionProfiles();

		$memID = $this->params['u'];
		$context['member']['id'] = $memID;
		$context['member']['name'] = $user_profile[$memID]['real_name'];

		$context['page_title'] = $txt['showPermissions'];
		$context['sub_template'] = 'profile_show_permissions';
		$board = empty($board) ? 0 : (int) $board;
		$context['board'] = $board;

		// Determine which groups this user is in.
		if (empty($user_profile[$memID]['additional_groups']))
			$curGroups = [];
		else
			$curGroups = explode(',', $user_profile[$memID]['additional_groups']);
		$curGroups[] = $user_profile[$memID]['id_group'];

		// Load a list of boards for the jump box - except the defaults.
		$request = $smcFunc['db']->query('order_by_board_order', '
			SELECT b.id_board, b.name, b.id_profile, b.member_groups, COALESCE(mods.id_member, modgs.id_group, 0) AS is_mod
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
				LEFT JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_board = b.id_board AND modgs.id_group IN ({array_int:current_groups}))
			WHERE {query_see_board}',
			[
				'current_member' => $memID,
				'current_groups' => $curGroups,
			]
		);
		$context['boards'] = [];
		$context['no_access_boards'] = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (count(array_intersect($curGroups, explode(',', $row['member_groups']))) === 0 && !$row['is_mod'])
				$context['no_access_boards'][] = [
					'id' => $row['id_board'],
					'name' => $row['name'],
					'is_last' => false,
				];
			elseif ($row['id_profile'] != 1 || $row['is_mod'])
				$context['boards'][$row['id_board']] = [
					'id' => $row['id_board'],
					'name' => $row['name'],
					'selected' => $board == $row['id_board'],
					'profile' => $row['id_profile'],
					'profile_name' => $context['profiles'][$row['id_profile']]['name'],
				];
		}
		$smcFunc['db']->free_result($request);

		require_once($sourcedir . '/Subs-Boards.php');
		sortBoards($context['boards']);

		if (!empty($context['no_access_boards']))
			$context['no_access_boards'][count($context['no_access_boards']) - 1]['is_last'] = true;

		$context['member']['permissions'] = [
			'general' => [],
			'board' => []
		];

		// If you're an admin we know you can do everything, we might as well leave.
		$context['member']['has_all_permissions'] = in_array(1, $curGroups);
		if ($context['member']['has_all_permissions'])
			return;

		$denied = [];

		// Get all general permissions.
		$result = $smcFunc['db']->query('', '
			SELECT p.permission, p.add_deny, mg.group_name, p.id_group
			FROM {db_prefix}permissions AS p
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = p.id_group)
			WHERE p.id_group IN ({array_int:group_list})
			ORDER BY p.add_deny DESC, p.permission, mg.group_name',
			[
				'group_list' => $curGroups,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			// We don't know about this permission, it doesn't exist :P.
			if (!isset($txt['permissionname_' . $row['permission']]))
				continue;

			if (empty($row['add_deny']))
				$denied[] = $row['permission'];

			// Permissions that end with _own or _any consist of two parts.
			if (in_array(substr($row['permission'], -4), ['_own', '_any']) && isset($txt['permissionname_' . substr($row['permission'], 0, -4)]))
				$name = $txt['permissionname_' . substr($row['permission'], 0, -4)] . ' - ' . $txt['permissionname_' . $row['permission']];
			else
				$name = $txt['permissionname_' . $row['permission']];

			// Add this permission if it doesn't exist yet.
			if (!isset($context['member']['permissions']['general'][$row['permission']]))
				$context['member']['permissions']['general'][$row['permission']] = [
					'id' => $row['permission'],
					'groups' => [
						'allowed' => [],
						'denied' => []
					],
					'name' => $name,
					'is_denied' => false,
					'is_global' => true,
				];

			// Add the membergroup to either the denied or the allowed groups.
			$context['member']['permissions']['general'][$row['permission']]['groups'][empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['id_group'] == 0 ? $txt['membergroups_members'] : $row['group_name'];

			// Once denied is always denied.
			$context['member']['permissions']['general'][$row['permission']]['is_denied'] |= empty($row['add_deny']);
		}
		$smcFunc['db']->free_result($result);

		$request = $smcFunc['db']->query('', '
			SELECT
				bp.add_deny, bp.permission, bp.id_group, mg.group_name' . (empty($board) ? '' : ',
				b.id_profile, CASE WHEN (mods.id_member IS NULL AND modgs.id_group IS NULL) THEN 0 ELSE 1 END AS is_moderator') . '
			FROM {db_prefix}board_permissions AS bp' . (empty($board) ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = {int:current_board})
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
				LEFT JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_board = b.id_board AND modgs.id_group IN ({array_int:group_list}))') . '
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = bp.id_group)
			WHERE bp.id_profile = {raw:current_profile}
				AND bp.id_group IN ({array_int:group_list}' . (empty($board) ? ')' : ', {int:moderator_group})
				AND (mods.id_member IS NOT NULL OR modgs.id_group IS NOT NULL OR bp.id_group != {int:moderator_group})'),
			[
				'current_board' => $board,
				'group_list' => $curGroups,
				'current_member' => $memID,
				'current_profile' => empty($board) ? '1' : 'b.id_profile',
				'moderator_group' => 3,
			]
		);

		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// We don't know about this permission, it doesn't exist :P.
			if (!isset($txt['permissionname_' . $row['permission']]))
				continue;

			// The name of the permission using the format 'permission name' - 'own/any topic/event/etc.'.
			if (in_array(substr($row['permission'], -4), ['_own', '_any']) && isset($txt['permissionname_' . substr($row['permission'], 0, -4)]))
				$name = $txt['permissionname_' . substr($row['permission'], 0, -4)] . ' - ' . $txt['permissionname_' . $row['permission']];
			else
				$name = $txt['permissionname_' . $row['permission']];

			// Create the structure for this permission.
			if (!isset($context['member']['permissions']['board'][$row['permission']]))
				$context['member']['permissions']['board'][$row['permission']] = [
					'id' => $row['permission'],
					'groups' => [
						'allowed' => [],
						'denied' => []
					],
					'name' => $name,
					'is_denied' => false,
					'is_global' => empty($board),
				];

			$context['member']['permissions']['board'][$row['permission']]['groups'][empty($row['add_deny']) ? 'denied' : 'allowed'][$row['id_group']] = $row['id_group'] == 0 ? $txt['membergroups_members'] : $row['group_name'];

			$context['member']['permissions']['board'][$row['permission']]['is_denied'] |= empty($row['add_deny']);
		}
		$smcFunc['db']->free_result($request);
	}
}
