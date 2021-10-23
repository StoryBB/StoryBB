<?php
/**
 * This file provides handling for character-specific features within the profile area.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Helper\Autocomplete;
use StoryBB\Helper\Parser;
use StoryBB\StringLibrary;

/**
 * Display sthe list of characters on the site.
 */
function CharacterList()
{
	global $context, $smcFunc, $txt, $scripturl, $modSettings, $settings, $user_info;
	global $image_proxy_enabled, $image_proxy_secret, $boardurl;

	$_GET['char'] = isset($_GET['char']) ? (int) $_GET['char'] : 0;
	if ($_GET['char'])
	{
		$result = $smcFunc['db']->query('', '
			SELECT chars.id_character, mem.id_member
			FROM {db_prefix}characters AS chars
			INNER JOIN {db_prefix}members AS mem ON (chars.id_member = mem.id_member)
			WHERE id_character = {int:id_character}',
			[
				'id_character' => $_GET['char'],
			]
		);
		$redirect = '';
		if ($smcFunc['db']->num_rows($result))
		{
			$row = $smcFunc['db']->fetch_assoc($result);
			$redirect = 'action=profile;u=' . $row['id_member'] . ';area=characters;char=' . $row['id_character'];
		}
		$smcFunc['db']->free_result($result);
		redirectexit($redirect);
	}

	isAllowedTo('view_mlist');
	loadLanguage('Profile');

	$context['filter_characters_in_no_groups'] = allowedTo('admin_forum');
	$context['page_title'] = $txt['chars_menu_title'];
	$context['sub_template'] = 'characterlist_main';
	$context['linktree'][] = [
		'name' => $txt['chars_menu_title'],
		'url' => $scripturl . '?action=characters',
	];

	if (isset($_GET['sa']) && $_GET['sa'] == 'sheets')
		return CharacterSheetList();

	$context['filterable_groups'] = [];
	foreach (get_char_membergroup_data() as $id_group => $group)
	{
		if ($group['is_character'])
			$context['filterable_groups'][$id_group] = $group;
	}

	$context['filter_groups'] = [];
	$filter = [];
	if (isset($_POST['filter']) && is_array($_POST['filter']))
	{
		$filter = $_POST['filter'];
	}
	elseif (isset($_GET['filter']))
	{
		$filter = explode(',', base64_decode($_GET['filter']));
	}

	if (!empty($filter))
	{
		if (allowedTo('admin_forum') && in_array(-1, $filter))
			$context['filter_groups'] = true;
		else
		{
			foreach ($filter as $filter_val)
			{
				if (isset($context['filterable_groups'][$filter_val]))
					$context['filter_groups'][] = (int) $filter_val;
			}
		}
	}

	$clauses = [
		'chars.is_main = {int:not_main}',
	];
	$vars = [
		'not_main' => 0,
	];

	$filter_url = '';
	if (!empty($context['filter_groups']))
	{
		if (is_array($context['filter_groups']))
		{
			$vars['filter_groups'] = $context['filter_groups'];
			$this_clause = [];
			foreach ($context['filter_groups'] as $group)
			{
				$this_clause[] = 'FIND_IN_SET(' . $group . ', chars.char_groups)';
			}
			$clauses[] = '(chars.main_char_group IN ({array_int:filter_groups}) OR (' . implode(' OR ', $this_clause) . '))';
			$filter_url = ';filter=' . base64_encode(implode(',', $context['filter_groups']));
		}
		else
		{
			$clauses[] = '(chars.main_char_group = 0 AND chars.char_groups = {empty})';
			$filter_url = ';filter=' . base64_encode('-1');
		}
	}

	$request = $smcFunc['db']->query('', '
		SELECT COUNT(id_character)
		FROM {db_prefix}characters AS chars
		WHERE ' . implode(' AND ', $clauses),
		$vars
	);
	list($context['char_count']) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	$context['items_per_page'] = 12;
	$context['page_index'] = constructPageIndex($scripturl . '?action=characters' . $filter_url . ';start=%1$d', $_REQUEST['start'], $context['char_count'], $context['items_per_page'], true);
	$vars['start'] = $_REQUEST['start'];
	$vars['limit'] = $context['items_per_page'];

	$context['char_list'] = [];
	if (!empty($context['char_count']))
	{
		$request = $smcFunc['db']->query('', '
			SELECT chars.id_character, chars.id_member, chars.character_name,
				a.filename, COALESCE(a.id_attach, 0) AS id_attach, chars.avatar, chars.posts, chars.date_created,
				chars.main_char_group, chars.char_groups, chars.char_sheet,
				chars.retired
			FROM {db_prefix}characters AS chars
			LEFT JOIN {db_prefix}attachments AS a ON (chars.id_character = a.id_character AND a.attachment_type = 1)
			WHERE ' . implode(' AND ', $clauses) . '
			ORDER BY chars.character_name
			LIMIT {int:start}, {int:limit}',
			$vars
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$row['character_avatar'] = set_avatar_data([
				'filename' => $row['filename'],
				'avatar' => $row['avatar'],
			]);

			$timestamp = $row['date_created'] + ($user_info['time_offset'] + $modSettings['time_offset']);
			$year = date('Y', $timestamp);
			$month = date('m', $timestamp);
			$day = date('d', $timestamp);
			$row['date_created_format'] = dateformat((int) $year, (int) $month, (int) $day);

			$row['character_link'] = $scripturl . '?action=profile;u=' . $row['id_member'] . ';area=characters;char=' . $row['id_character'];

			$groups = !empty($row['main_char_group']) ? [$row['main_char_group']] : [];
			$groups = array_merge($groups, explode(',', $row['char_groups']));
			$details = get_labels_and_badges($groups);
			$row['group_title'] = $details['title'];
			$row['group_color'] = $details['color'];
			$row['group_badges'] = $details['badges'];
			$context['char_list'][] = $row;
		}
		$smcFunc['db']->free_result($request);
	}

	if (!empty($context['filter_groups']))
	{
		addInlineJavascript('$(\'#filter_opts_link\').trigger(\'click\');', true);
	}
}

/**
 * Show a filtered form of the character list based on characters being in a given group.
 * Params should be set up by the caller, which is CharacterList().
 */
function CharacterSheetList()
{
	global $context, $smcFunc;

	loadLanguage('Profile');

	$context['group_id'] = isset($_GET['group']) ? (int) $_GET['group'] : 0;
	if (empty($context['group_id']))
	{
		redirectexit('action=characters');
	}

	$context['sub_template'] = 'characterlist_filtered';

	$sort = [
		'last_active' => [
			'asc' => 'chars.last_active ASC',
			'desc' => 'chars.last_active DESC',
		],
		'name' => [
			'asc' => 'chars.character_name ASC',
			'desc' => 'chars.character_name DESC',
		]
	];
	$context['sort_by'] = isset($_GET['sort'], $sort[$_GET['sort']]) ? $_GET['sort'] : 'last_active';
	$context['sort_order'] = isset($_GET['dir']) && !empty($_GET['sort']) && ($_GET['dir'] == 'asc' || $_GET['dir'] == 'desc') ? $_GET['dir'] : 'desc';

	$context['characters'] = [];
	$request = $smcFunc['db']->query('', '
		SELECT chars.id_character, chars.id_member, chars.character_name,
			chars.date_created, chars.last_active, a.filename, COALESCE(a.id_attach, 0) AS id_attach, chars.avatar,
			chars.posts, chars.main_char_group, chars.char_groups, chars.retired
		FROM {db_prefix}characters AS chars
		LEFT JOIN {db_prefix}attachments AS a ON (chars.id_character = a.id_character AND a.attachment_type = 1)
		WHERE chars.char_sheet != 0
			AND main_char_group = {int:group}
		ORDER BY {raw:sort}',
		[
			'group' => $context['group_id'],
			'sort' => $sort[$context['sort_by']][$context['sort_order']],
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$row['character_avatar'] = set_avatar_data([
			'filename' => $row['filename'],
			'avatar' => $row['avatar'],
		]);
		$row['group_list'] = array_merge((array) $row['main_char_group'], explode(',', $row['char_groups']));
		$row['groups'] = get_labels_and_badges($row['group_list']);
		$row['date_created_format'] = timeformat($row['date_created']);
		$row['last_active_format'] = timeformat($row['last_active']);
		$context['characters'][] = $row;
	}
	$smcFunc['db']->free_result($request);
}

/**
 * Moves a post between characters on an account.
 */
function ReattributePost()
{
	global $topic, $smcFunc, $user_info, $board_info;

	// 1. Session check, quick and easy to get out the way before we forget.
	checkSession('get');

	// 2. Get the message id and verify that it exists inside the topic in question.
	$msg = isset($_GET['msg']) ? (int) $_GET['msg'] : 0;
	$result = $smcFunc['db']->query('', '
		SELECT t.id_topic, t.locked, t.id_member_started, m.id_member AS id_member_posted,
			m.id_character, c.character_name AS old_character
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
			INNER JOIN {db_prefix}characters AS c ON (m.id_character = c.id_character)
		WHERE m.id_msg = {int:msg}',
		[
			'msg' => $msg,
		]
	);

	// 2a. Doesn't exist?
	if ($smcFunc['db']->num_rows($result) == 0)
		fatal_lang_error('no_access', false);

	$row = $smcFunc['db']->fetch_assoc($result);
	$smcFunc['db']->free_result($result);

	// 2b. Not the topic we thought it was?
	if ($row['id_topic'] != $topic)
		fatal_lang_error('no_access', false);

	// 3. Verify we have permission. We loaded $topic's board's permissions earlier.
	// Now verify that we have the relevant powers.
	$is_poster = $user_info['id'] == $row['id_member_posted'];
	$is_topic_starter = $user_info['id'] == $row['id_member_started'];
	$can_modify = (!$row['locked'] || allowedTo('moderate_board')) && (allowedTo('modify_any') || (allowedTo('modify_replies') && $is_topic_starter) || (allowedTo('modify_own') && $is_poster));
	if (!$can_modify)
		fatal_lang_error('no_access', false);

	// 4. Verify that the requested character belongs to the person we're changing to.
	// And is a valid target for such things.
	$character = isset($_GET['char']) ? (int) $_GET['char'] : 0;
	$valid_characters = get_user_possible_characters($row['id_member_posted'], $board_info['id']);
	if (!isset($valid_characters[$character]))
		fatal_lang_error('no_access', false);

	// 5. So we've verified the topic matches the message, the user has power
	// to edit the message, and the message owner's new character exists.
	// Time to reattribute the message!
	$smcFunc['db']->query('', '
		UPDATE {db_prefix}messages
		SET id_character = {int:char}
		WHERE id_msg = {int:msg}',
		[
			'char' => $character,
			'msg' => $msg,
		]
	);

	// 6. Having reattributed the post, now let's also fix the post count.
	// If we're supposed to, that is.
	if ($board_info['posts_count'])
	{
		// Subtract one from the post count of the current owner.
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}characters
			SET posts = (CASE WHEN posts <= 1 THEN 0 ELSE posts - 1 END)
			WHERE id_character = {int:char}',
			[
				'char' => $row['id_character'],
			]
		);

		// Add one to the new owner.
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}characters
			SET posts = posts + 1
			WHERE id_character = {int:char}',
			[
				'char' => $character,
			]
		);
	}

	// 7. Add it to the moderation log.
	logAction('char_reattribute', [
		'member' => $row['id_member_posted'],
		'old_character' => $row['old_character'],
		'new_character' => $valid_characters[$character]['name'],
		'message' => $msg,
	], 'moderate');

	// 8. All done. Exit back to the post.
	redirectexit('topic=' . $topic . '.msg' . $msg . '#msg' . $msg);
}
