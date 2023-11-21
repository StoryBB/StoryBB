<?php

/**
 * The report of all staff.
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
use StoryBB\Model\BoardModerators;
use StoryBB\Model\Group;
use StoryBB\Model\PermissionProfile;
use StoryBB\Routing\RenderResponse;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\Response;

class Staff extends AbstractAdminController implements Administrative, MaintenanceAccessible
{
	use Database;

	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('reports/staff', (new Route('/reports/staff', ['_controller' => [static::class, 'report']])));
	}

	public function report(): Response
	{
		$rendercontext = [
			'page_title' => new Phrase('Admin:staff'),
			'staff' => [],
		];

		$db = $this->db();

		$perms_profile = App::make(PermissionProfile::class);
		$boards = $this->get_all_boards();
		$all_groups = App::make(Group::class)->get_all_names();

		// Get all the local moderators.
		$local_moderators_by_board = App::make(BoardModerators::class)->get_all();
		$local_mods = [];
		foreach ($local_moderators_by_board as $board_id => $board_mods)
		{
			foreach ($board_mods as $mod_id => $mod_name)
			{
				if (isset($boards[$board_id]))
				{
					$local_mods[$mod_id][$board_id] = $boards[$board_id];
				}
			}
		}

		// Get the local moderators where they're made that way by way of board group moderators.
		$request = $db->query('', '
			SELECT mem.id_member, modgs.id_board
			FROM {db_prefix}members AS mem
				INNER JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_group = mem.id_group OR FIND_IN_SET(modgs.id_group, mem.additional_groups) != 0)',
			[]
		);

		while ($row = $db->fetch_assoc($request))
		{
			if (isset($boards[$row['id_board']]))
			{
				$local_mods[$row['id_member']][$row['id_board']] = $boards[$row['id_board']];
			}
		}
		$db->free_result($request);

		// Get global moderators and admins.
		$global_mods = array_intersect(
			$perms_profile->get_permitted_members('moderate_board', 'board', 0),
			$perms_profile->get_permitted_members('approve_posts', 'board', 0),
			$perms_profile->get_permitted_members('remove_any', 'board', 0),
			$perms_profile->get_permitted_members('modify_any', 'board', 0),
		);

		$admins = array_merge(
			$perms_profile->get_permitted_members('admin_forum', 'general'),
			$perms_profile->get_permitted_members('manage_membergroups', 'general'),
			$perms_profile->get_permitted_members('manage_permissions', 'general'),
		);

		$all_staff = array_unique(array_merge($admins, $global_mods, array_keys($local_mods)));

		// Get all the names of all the people in question.
		$request = $db->query('', '
			SELECT id_member, real_name, id_group, posts, last_login
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:staff_list})
			ORDER BY real_name',
			[
				'staff_list' => $all_staff,
			]
		);
		while ($row = $db->fetch_assoc($request))
		{
			$staff = [
				'name' => $row['real_name'],
				'position' => $all_groups[$row['id_group']] ?? $all_groups[0],
				'posts' => $row['posts'],
			];

			if (in_array($row['id_member'], $admins) || in_array($row['id_member'], $global_mods))
			{
				$staff['moderates'] = '<em>' . (new Phrase('Reports:report_staff_all_boards')) . '</em>';
			}
			elseif (isset($local_mods[$row['id_member']]))
			{
				$staff['moderates'] = implode(', ', $local_mods[$row['id_member']]);
			}
			else
			{
				$staff['moderates'] = '<em>' . (new Phrase('Reports:report_staff_no_boards')) . '</em>';
			}

			// @todo add last login time here

			$rendercontext['staff'][] = $staff;
		}

		return $this->render('admin/reports/staff.twig', 'reports/staff', $rendercontext);
	}

	protected function get_all_boards(): array
	{
		$db = $this->db();
		$boards = [];

		$request = $db->query('', '
			SELECT id_board, name
			FROM {db_prefix}boards',
			[]
		);

		while ($row = $db->fetch_assoc($request))
		{
			$boards[$row['id_board']] = $row['name'];
		}
		$db->free_result($request);

		return $boards;
	}
}
