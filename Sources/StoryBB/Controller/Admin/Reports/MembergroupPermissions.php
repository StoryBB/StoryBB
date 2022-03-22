<?php

/**
 * The staff permissions report.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Admin\Reports;

use StoryBB\App;
use StoryBB\Phrase;
use StoryBB\Controller\Admin\AbstractAdminController;
use StoryBB\Routing\Behaviours\Administrative;
use StoryBB\Routing\Behaviours\MaintenanceAccessible;
use StoryBB\Dependency\Database;
use StoryBB\Model\Group;
use StoryBB\Routing\RenderResponse;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\Response;

class MembergroupPermissions extends AbstractAdminController implements Administrative, MaintenanceAccessible
{
	use Database;

	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('reports/membergroup_permissions', (new Route('/reports/membergroup_permissions', ['_controller' => [static::class, 'report']])));
	}

	public function report(): Response
	{
		$db = $this->db();

		$rendercontext = [
			'page_title' => new Phrase('Admin:membergroup_permissions'),
		];

		// Set up all the groups that we're reporting on.
		$data['groups'] = [
			-1 => new Phrase('General:guest_title'),
			0 => new Phrase('Reports:full_member'),
		];

		$data['group_counts'] = [
			'account' => 2,
			'character' => 0,
		];

		$result = $db->query('', '
			SELECT id_group, group_name, is_character
			FROM {db_prefix}membergroups
			WHERE id_group NOT IN ({int:admin}, {int:moderator})
			ORDER BY is_character, id_group',
			[
				'admin' => Group::ADMINISTRATOR,
				'moderator' => Group::BOARD_MODERATOR,
			]
		);
		while ($row = $db->fetch_assoc($result))
		{
			$data['groups'][$row['id_group']] = $row['group_name'];
			$data['group_counts'][$row['is_character'] ? 'character' : 'account']++;
		}
		$db->free_result($result);

		// Actually get all the permissions.
		$result = $db->query('', '
			SELECT id_group, add_deny, permission
			FROM {db_prefix}permissions
			WHERE id_group != {int:moderator}
			ORDER BY permission',
			[
				'moderator' => Group::BOARD_MODERATOR,
			]
		);
		$permissions = [];
		while ($row = $db->fetch_assoc($result))
		{
			$permissions[$row['permission']][$row['id_group']] = $row['add_deny'];
		}
		$db->free_result($result);

		$data['permissions'] = [];
		$data['permissions_matrix'] = [];
		$group_ids = array_keys($data['groups']);
		foreach ($permissions as $permission => $matrix)
		{
			$data['permissions'][$permission] = $this->get_permission_name($permission);
			// This splits the result into 1 = Allow, 0 = Disallow, -1 = Deny.
			foreach ($group_ids as $group_id) {
				$data['permissions_matrix'][$permission][$group_id] = 0;
				if (isset($matrix[$group_id]))
				{
					$data['permissions_matrix'][$permission][$group_id] = $matrix[$group_id] ? 1 : -1;
				}
			}
		}

		// Now sort out boards.
		$data['boards'] = [];
		$data['boards_matrix'] = [];
		$result = $db->query('', '
			SELECT id_board, name, member_groups, deny_member_groups
			FROM {db_prefix}boards
			ORDER BY board_order');
		while ($row = $db->fetch_assoc($result))
		{
			$data['boards'][$row['id_board']] = $row['name'];

			$member_groups = $this->get_group_ids($row['member_groups']);
			$deny_member_groups = $this->get_group_ids($row['deny_member_groups']);
			foreach ($group_ids as $group_id)
			{
				$data['boards_matrix'][$row['id_board']][$group_id] = 0;
				if (in_array($group_id, $deny_member_groups)) {
					$data['boards_matrix'][$row['id_board']][$group_id] = -1;
				} elseif (in_array($group_id, $member_groups)) {
					$data['boards_matrix'][$row['id_board']][$group_id] = 1;
				}
			}
		}
		$db->free_result($result);

		$data['total_group_count'] = count($data['group_counts']['account']) + count($data['group_counts']['character']);
		$rendercontext['data'] = $data;

		return $this->render('admin/reports/membergroup_permissions.twig', 'reports/membergroup_permissions', $rendercontext);
	}

	public function get_group_ids(string $group_string): array
	{
		$groups = [];
		foreach (explode(',', $group_string) as $group)
		{
			if ($group === '0')
			{
				$groups[] = 0;
				continue;
			}

			if (!trim($group))
			{
				continue;
			}
			$groups[] = (int) $group;
		}
		return $groups;
	}

	public function get_permission_name(string $permission)
	{
		return new Phrase('Reports:group_perms_name_' . $permission);
	}
}
