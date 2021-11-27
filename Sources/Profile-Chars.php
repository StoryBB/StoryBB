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

	redirectexit();
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
