<?php

/**
 * The admin home page handler.
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
use StoryBB\Controller\Administrative;
use StoryBB\Controller\MaintenanceAccessible;
use StoryBB\Dependency\Database;
use StoryBB\Model\BoardGroupModerators;
use StoryBB\Model\BoardModerators;
use StoryBB\Model\Group;
use StoryBB\Model\PermissionProfile;
use StoryBB\Routing\RenderResponse;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\Response;

class Boards extends AbstractAdminController implements Administrative, MaintenanceAccessible
{
	use Database;

	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('reports/boards', (new Route('/reports/boards', ['_controller' => [static::class, 'report']])));
	}

	public function report(): Response
	{
		$rendercontext = [
			'page_title' => new Phrase('Admin:boards'),
			'data' => [],
		];

		$headings = [
			'category' => new Phrase('Reports:board_category'),
			'parent' => new Phrase('Reports:board_parent'),
			'redirect' => new Phrase('Reports:board_redirect'),
			'num_topics' => new Phrase('Reports:board_num_topics'),
			'num_posts' => new Phrase('Reports:board_num_posts'),
			'in_character' => new Phrase('Reports:board_in_character'),
			'count_posts' => new Phrase('Reports:board_count_posts'),
			'theme' => new Phrase('Reports:board_theme'),
			'override_theme' => new Phrase('Reports:board_override_theme'),
			'profile' => new Phrase('Reports:board_profile'),
			'moderators' => new Phrase('Reports:board_moderators'),
			'moderator_groups' => new Phrase('Reports:board_moderator_groups'),
			'groups' => new Phrase('Reports:board_groups'),
			'disallowed_groups' => new Phrase('Reports:board_disallowed_groups'),
		];

		$db = $this->db();

		// Get all the moderators for boards etc.
		$permission_profiles = (App::make(PermissionProfile::class))->get_all();
		$moderators = (App::make(BoardModerators::class))->get_all();
		$moderator_groups = (App::make(BoardGroupModerators::class))->get_all();
		$groups = (App::make(Group::class))->get_all_names();

		// Go through each board!
		$request = $db->query('order_by_board_order', '
			SELECT b.id_board, b.name, b.num_posts, b.num_topics, b.count_posts, b.in_character, b.member_groups, b.override_theme, b.id_profile, b.deny_member_groups,
				b.redirect, c.name AS cat_name, COALESCE(par.name, {string:text_none}) AS parent_name, COALESCE(th.value, {string:text_none}) AS theme_name
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				LEFT JOIN {db_prefix}boards AS par ON (par.id_board = b.id_parent)
				LEFT JOIN {db_prefix}themes AS th ON (th.id_theme = b.id_theme AND th.variable = {string:name})
			ORDER BY b.board_order',
			[
				'name' => 'name',
				'text_none' => (string) new Phrase('General:none'),
			]
		);

		$yes = new Phrase('General:yes');
		$no = new Phrase('General:no');
		$none = new Phrase('General:none');

		while ($row = $db->fetch_assoc($request))
		{
			$this_table = [
				'title' => $row['name'],
			];

			// Fill in blanks for everything in the right order.
			foreach ($headings as $heading => $heading_text)
			{
				$this_table['heading'][$heading] = $heading_text;
				$this_table['data'][$heading] = '';
			}

			if (empty($row['redirect']))
			{
				unset($this_table['heading']['redirect'], $this_table['data']['redirect']);
			}

			$data = [
				'category' => $row['cat_name'],
				'parent' => $row['parent_name'],
				'redirect' => $row['redirect'],
				'num_posts' => $row['num_posts'],
				'num_topics' => $row['num_topics'],
				'in_character' => $row['in_character'] ? new Phrase('Reports:board_is_ic') : new Phrase('Reports:board_is_ooc'),
				'count_posts' => empty($row['count_posts']) ? $yes : $no,
				'theme' => $row['theme_name'],
				'profile' => $permission_profiles[$row['id_profile']]['name'],
				'override_theme' => $row['override_theme'] ? $yes : $no,
				'moderators' => empty($moderators[$row['id_board']]) ? $none : implode(', ', $moderators[$row['id_board']]),
				'moderator_groups' => empty($moderator_groups[$row['id_board']]) ? $none : implode(', ', $moderator_groups[$row['id_board']]),
				'groups' => [],
				'disallowed_groups' => [],
			];

			// Work out the membergroups who can and cannot access it (but only if enabled).
			foreach (explode(',', $row['member_groups']) as $group)
			{
				if (isset($groups[$group]))
				{
					$data['groups'][] = $groups[$group];
				}
			}
			$data['groups'] = implode(', ', $data['groups']) ?: '<em>' . $none . '</em>';

			foreach (explode(',', $row['deny_member_groups']) as $group)
			{
				if (isset($groups[$group]))
				{
					$data['disallowed_groups'][] = $groups[$group];
				}
			}
			$data['disallowed_groups'] = implode(', ', $data['disallowed_groups']) ?: '<em>' . $none . '</em>';

			// Lastly, glue it all together.
			foreach ($data as $key => $value)
			{
				if (isset($this_table['data'][$key]))
				{
					$this_table['data'][$key] = $value;
				}
			}

			$rendercontext['data'][] = $this_table;
		}

		$db->free_result($request);

		return $this->render('admin/report_2col.twig', 'reports/boards', $rendercontext);
	}
}
