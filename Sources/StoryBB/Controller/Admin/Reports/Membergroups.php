<?php

/**
 * A report on membergroups.
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
use InvalidArgumentException;

class Membergroups extends AbstractAdminController implements Administrative, MaintenanceAccessible
{
	use Database;

	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('reports/membergroups', (new Route('/reports/membergroups', ['_controller' => [static::class, 'report']])));
	}

	public function report(): Response
	{
		$db = $this->db();

		$rendercontext = [
			'page_title' => new Phrase('Admin:membergroups'),
		];

		$data['account_groups'] = [
			-1 => [
				'name' => new Phrase('General:guest_title'),
				'color' => '',
				'max_pm' => '',
				'type' => new Phrase('Reports:member_group_type_automatic'),
			],
			0 => [
				'name' => new Phrase('Reports:full_member'),
				'color' => '',
				'max_pm',
				'type' => new Phrase('Reports:member_group_type_automatic'),
			],
		];
		$data['character_groups'] = [];

		$result = $db->query('', '
			SELECT id_group, group_name, online_color, max_messages, icons, group_type, is_character
			FROM {db_prefix}membergroups
			ORDER BY group_name');
		while ($row = $db->fetch_assoc($result))
		{
			if ($row['is_character'])
			{
				$data['character_groups'][$row['id_group']] = [
					'name' => $row['group_name'],
					'color' => $row['online_color'],
					'type' => $this->get_group_type((int) $row['group_type']),
				];
			}
			else
			{
				$data['account_groups'][$row['id_group']] = [
					'name' => $row['group_name'],
					'color' => $row['online_color'],
					'max_pm' => '',
					'type' => $this->get_group_type((int) $row['group_type']),
				];
			}
		}

		$data['account_group_columns'] = count($data['account_groups']) + 1;
		$data['character_group_columns'] = count($data['character_groups']) + 1;

		// Now sort out boards.
		$data['boards'] = [];
		$data['boards_matrix_accounts'] = [];
		$data['boards_matrix_characters'] = [];
		$result = $db->query('', '
			SELECT id_board, name, member_groups, deny_member_groups
			FROM {db_prefix}boards
			ORDER BY board_order');
		while ($row = $db->fetch_assoc($result))
		{
			$data['boards'][$row['id_board']] = $row['name'];

			$member_groups = $this->get_group_ids($row['member_groups']);
			$deny_member_groups = $this->get_group_ids($row['deny_member_groups']);
			foreach ($data['account_groups'] as $group_id => $group)
			{
				$data['boards_matrix_accounts'][$row['id_board']][$group_id] = 0;
				if (in_array($group_id, $deny_member_groups)) {
					$data['boards_matrix_accounts'][$row['id_board']][$group_id] = -1;
				} elseif (in_array($group_id, $member_groups)) {
					$data['boards_matrix_accounts'][$row['id_board']][$group_id] = 1;
				}
			}
			foreach ($data['character_groups'] as $group_id => $group)
			{
				$data['boards_matrix_characters'][$row['id_board']][$group_id] = 0;
				if (in_array($group_id, $deny_member_groups)) {
					$data['boards_matrix_characters'][$row['id_board']][$group_id] = -1;
				} elseif (in_array($group_id, $member_groups)) {
					$data['boards_matrix_characters'][$row['id_board']][$group_id] = 1;
				}
			}
		}
		$db->free_result($result);

		$data['total_group_count'] = count($data['group_counts']['account']) + count($data['group_counts']['character']);

		$rendercontext['data'] = $data;

		return $this->render('admin/reports/membergroups.twig', 'reports/membergroups', $rendercontext);
	}

	protected function get_group_ids(string $group_string): array
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

	protected function get_group_type(int $group_type): Phrase
	{
		$types = [
			Group::TYPE_PRIVATE => 'member_group_type_private',
			Group::TYPE_PROTECTED => 'member_group_type_protected',
			Group::TYPE_REQUESTABLE => 'member_group_type_requestable',
			Group::TYPE_JOINABLE => 'member_group_type_joinable',
		];

		if (isset($types[$group_type]))
		{
			return new Phrase('Reports:' . $types[$group_type]);
		}

		throw new InvalidArgumentException('Unknown group type ' . $group_type);
	}
}
