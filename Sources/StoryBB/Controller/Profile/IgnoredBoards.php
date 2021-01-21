<?php

/**
 * Displays the ignored boards page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

class IgnoredBoards extends AbstractProfileController
{
	protected function get_token_name()
	{
		return str_replace('%u', $this->params['u'], 'profile-ib%u');
	}

	public function display_action()
	{
		global $context, $modSettings, $smcFunc, $cur_profile, $sourcedir;

		// Have the admins enabled this option?
		if (empty($modSettings['allow_ignore_boards']))
		{
			fatal_lang_error('ignoreboards_disallowed', 'user');
		}

		$memID = $this->params['u'];

		createToken($this->get_token_name(), 'post');

		// Find all the boards this user is allowed to see.
		$request = $smcFunc['db']->query('order_by_board_order', '
			SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level,
				'. (!empty($cur_profile['ignore_boards']) ? 'b.id_board IN ({array_int:ignore_boards})' : '0') . ' AS is_ignored
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			WHERE {query_see_board}
				AND redirect = {string:empty_string}',
			[
				'ignore_boards' => !empty($cur_profile['ignore_boards']) ? explode(',', $cur_profile['ignore_boards']) : [],
				'empty_string' => '',
			]
		);
		$context['num_boards'] = $smcFunc['db']->num_rows($request);
		$context['categories'] = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// This category hasn't been set up yet..
			if (!isset($context['categories'][$row['id_cat']]))
				$context['categories'][$row['id_cat']] = [
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'boards' => []
				];

			// Set this board up, and let the template know when it's a child.  (indent them..)
			$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = [
				'id' => $row['id_board'],
				'name' => $row['name'],
				'child_level' => $row['child_level'],
				'selected' => (bool) $row['is_ignored'],
			];
		}
		$smcFunc['db']->free_result($request);

		require_once($sourcedir . '/Subs-Boards.php');
		sortCategories($context['categories']);

		// Now, let's sort the list of categories into the boards for templates that like that.
		$temp_boards = [];
		foreach ($context['categories'] as $category)
		{
			// Include a list of boards per category for easy toggling.
			$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);

			$temp_boards[] = [
				'name' => $category['name'],
				'child_ids' => array_keys($category['boards'])
			];
			$temp_boards = array_merge($temp_boards, array_values($category['boards']));
		}

		$max_boards = ceil(count($temp_boards) / 2);
		if ($max_boards == 1)
			$max_boards = 2;

		// Now, alternate them so they can be shown left and right ;).
		$context['board_columns'] = [];
		for ($i = 0; $i < $max_boards; $i++)
		{
			$context['board_columns'][] = $temp_boards[$i];
			if (isset($temp_boards[$i + $max_boards]))
				$context['board_columns'][] = $temp_boards[$i + $max_boards];
			else
				$context['board_columns'][] = [];
		}

		$context['split_categories'] = array_chunk($context['categories'], ceil(count($context['categories']) / 2), true);
		$context['sub_template'] = 'profile_ignoreboards';

		$context['token_check'] = $this->get_token_name();
	}

	public function post_action()
	{
		global $modSettings, $cur_profile;

		// Have the admins enabled this option?
		if (empty($modSettings['allow_ignore_boards']))
		{
			fatal_lang_error('ignoreboards_disallowed', 'user');
		}

		validateToken($this->get_token_name());

		$memID = $this->params['u'];

		if (isset($_POST['sa']) && $_POST['sa'] == 'ignoreboards' && empty($_POST['ignore_brd']))
			$_POST['ignore_brd'] = [];

		$ignore_boards = '';

		if (isset($_POST['ignore_brd']))
		{
			if (!is_array($_POST['ignore_brd']))
				$_POST['ignore_brd'] = [$_POST['ignore_brd']];

			foreach ($_POST['ignore_brd'] as $k => $d)
			{
				$d = (int) $d;
				if ($d != 0)
					$_POST['ignore_brd'][$k] = $d;
				else
					unset($_POST['ignore_brd'][$k]);
			}
			$ignore_boards = implode(',', $_POST['ignore_brd']);
		}

		if ($cur_profile['ignore_boards'] != $ignore_boards)
		{
			updateMemberData($memID, ['ignore_boards' => $ignore_boards]);
		}

		$this->use_generic_save_message();

		redirectexit('action=profile;area=ignored_boards;u=' . $memID);
	}
}
