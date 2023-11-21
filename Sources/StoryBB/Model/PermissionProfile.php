<?php

/**
 * This class handles permission profiles.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Model;

use StoryBB\App;
use StoryBB\Phrase;
use StoryBB\Dependency\Database;

/**
 * This class handles permission profiles.
 */
class PermissionProfile
{
	use Database;

	/**
	 * Returns all the permission profiles' names and high level details.
	 *
	 * @return array An array by profile id of permission profiles.
	 */
	public function get_all(): array
	{
		$profiles = [];
		$db = $this->db();

		$request = $db->query('', '
			SELECT id_profile, profile_name
			FROM {db_prefix}permission_profiles
			ORDER BY id_profile',
			[]
		);

		while ($row = $db->fetch_assoc($request))
		{
			// Format the label nicely.
			$name = $row['profile_name'];
			if (in_array($row['profile_name'], ['default', 'no_polls', 'reply_only', 'read_only']))
			{
				$name = (string) new Phrase('ManagePermissions:permissions_profile_' . $row['profile_name']);
			}

			$profiles[$row['id_profile']] = [
				'id' => $row['id_profile'],
				'name' => $name,
				'can_modify' => $row['id_profile'] == 1 || $row['id_profile'] > 4,
				'unformatted_name' => $row['profile_name'],
			];
		}
		$db->free_result($request);

		return $profiles;
	}

	public function get_profile_id_by_board(int $perms_context): int
	{
		if ($perms_context == 0)
		{
			return 1; // Default profile for default context.
		}

		$db = $this->db();

		$request = $db->query('', '
			SELECT id_profile
			FROM {db_prefix}boards
			WHERE id_board = {int:id_board}
			LIMIT 1',
			[
				'id_board' => $perms_context,
			]
		);
		if ($db->num_rows($request) == 0)
		{
			throw new \RuntimeException('Cannot query permissions for non-existant board ' . $perms_context);
		}
		[$profile_id] = $db->fetch_row($request);
		$db->free_result($request);

		return (int) $profile_id;
	}

	public function get_groups_by_board_permission(string $permission, int $profile_id): array
	{
		$member_groups = [
			'allowed' => [1],
			'denied' => [],
		];

		$db = $this->db();

		$request = $db->query('', '
			SELECT bp.id_group, bp.add_deny
			FROM {db_prefix}board_permissions AS bp
			WHERE bp.permission = {string:permission}
				AND bp.id_profile = {int:profile_id}',
			[
				'profile_id' => $profile_id,
				'permission' => $permission,
			]
		);
		while ($row = $db->fetch_assoc($request))
		{
			$member_groups[$row['add_deny'] === '1' ? 'allowed' : 'denied'][] = (int) $row['id_group'];
		}
		$db->free_result($request);

		return $member_groups;
	}

	public function get_permitted_groups($permission, string $type = 'general', int $perms_context = 0)
	{
		$db = $this->db();

		switch ($type)
		{
			case 'general':
				$member_groups = [
					'allowed' => [1],
					'denied' => [],
				];

				$request = $db->query('', '
					SELECT id_group, add_deny
					FROM {db_prefix}permissions
					WHERE permission = {string:permission}',
					[
						'permission' => $permission,
					]
				);
				while ($row = $db->fetch_assoc($request))
				{
					$member_groups[$row['add_deny'] === '1' ? 'allowed' : 'denied'][] = $row['id_group'];
				}
				$db->free_result($request);
				break;

			case 'board':
				$profile_id = $this->get_profile_id_by_board($perms_context);
				$member_groups = $this->get_groups_by_board_permission($permission, $profile_id);

				$board_group_mods = App::make(BoardGroupModerators::class);
				$moderators = $board_group_mods->get_all();
				$groups = array_keys($moderators[$perms_context] ?? []);

				// "Inherit" any additional permissions from the "Moderators" group
				foreach ($groups as $mod_group)
				{
					// If they're not specifically allowed, but the moderator group is, then allow it
					if (in_array(3, $member_groups['allowed']) && !in_array($mod_group, $member_groups['allowed']))
					{
						$member_groups['allowed'][] = $mod_group;
					}

					// They're not denied, but the moderator group is, so deny it
					if (in_array(3, $member_groups['denied']) && !in_array($mod_group, $member_groups['denied']))
					{
						$member_groups['denied'][] = $mod_group;
					}
				}
				break;
			default:
				throw new \RuntimeException('Unknwon permission type ' . $type);
		}

		// Denied is never allowed.
		$member_groups['allowed'] = array_diff($member_groups['allowed'], $member_groups['denied']);
		return $member_groups;
	}

	public function get_permitted_members($permission, string $type = 'general', int $perms_context = 0)
	{
		$db = $this->db();

		$member_groups = $this->get_permitted_groups($permission, $type, $perms_context);

		$all_groups = array_merge($member_groups['allowed'], $member_groups['denied']);

		switch ($type)
		{
			case 'general':
				$include_moderators = false;
				$exclude_moderators = false;

				break;

			case 'board':
				$include_moderators = in_array(3, $member_groups['allowed']);
				$exclude_moderators = in_array(3, $member_groups['denied']);
				break;
		}

		$member_groups['allowed'] = array_diff($member_groups['allowed'], [3]);
		$member_groups['denied'] = array_diff($member_groups['denied'], [3]);

		$request = $db->query('', '
			SELECT mem.id_member
			FROM {db_prefix}members AS mem' . ($include_moderators || $exclude_moderators ? '
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_member = mem.id_member AND mods.id_board = {int:board_id})
				LEFT JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_group IN ({array_int:all_member_groups}))' : '') . '
			WHERE (' . ($include_moderators ? 'mods.id_member IS NOT NULL OR modgs.id_group IS NOT NULL OR ' : '') . 'mem.id_group IN ({array_int:member_groups_allowed}) OR FIND_IN_SET({raw:member_group_allowed_implode}, mem.additional_groups) != 0)' . (empty($member_groups['denied']) ? '' : '
				AND NOT (' . ($exclude_moderators ? 'mods.id_member IS NOT NULL OR modgs.id_group IS NOT NULL OR ' : '') . 'mem.id_group IN ({array_int:member_groups_denied}) OR FIND_IN_SET({raw:member_group_denied_implode}, mem.additional_groups) != 0)'),
			[
				'member_groups_allowed' => $member_groups['allowed'],
				'member_groups_denied' => $member_groups['denied'],
				'all_member_groups' => $all_groups,
				'board_id' => $perms_context,
				'member_group_allowed_implode' => implode(', mem.additional_groups) != 0 OR FIND_IN_SET(', $member_groups['allowed']),
				'member_group_denied_implode' => implode(', mem.additional_groups) != 0 OR FIND_IN_SET(', $member_groups['denied']),
			]
		);
		$members = [];
		while ($row = $db->fetch_assoc($request))
		{
			$members[] = $row['id_member'];
		}
		$db->free_result($request);

		return $members;
	}
}
