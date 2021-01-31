<?php

/**
 * This class handles characters.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Model;

/**
 * This class handles characters.
 */
class Character
{
	/**
	 * Move a character physically to another account.
	 *
	 * @param int $source_chr The ID of the character to be moved
	 * @param int $dest_acct The ID of the account to move the character to
	 * @return mixed True on success, otherwise string indicating error type
	 * @todo refactor this to emit exceptions rather than mixed return types
	 */
	public static function move_char_accounts($source_chr, $dest_acct)
	{
		global $smcFunc, $modSettings;

		// First, establish that both exist.
		$loaded = loadMemberData([$dest_acct]);
		if (!in_array($dest_acct, $loaded))
			return 'not_found';

		$request = $smcFunc['db']->query('', '
			SELECT chars.id_member, chars.id_character, chars.is_main, chars.posts, mem.current_character
			FROM {db_prefix}characters AS chars
				INNER JOIN {db_prefix}members AS mem ON (chars.id_member = mem.id_member)
			WHERE id_character = {int:char}',
			[
				'char' => $source_chr,
			]
		);
		$row = $smcFunc['db']->fetch_assoc($request);
		if (empty($row))
		{
			return 'cannot_move_char_not_found';
		}
		$smcFunc['db']->free_result($request);
		if ($row['is_main'])
		{
			return 'main'; // Can't move your main/OOC character out of your account
		}

		// Before we can say we're good to go, make sure this character isn't
		// currently in use or online.
		if ($row['current_character'] == $source_chr)
		{
			return 'online';
		}
		$request = $smcFunc['db']->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}log_online
			WHERE log_time >= {int:log_time}
				AND id_character = {int:source_chr}',
			[
				'log_time' => time() - $modSettings['lastActive'] * 60,
				'source_chr' => $source_chr,
			]
		);
		list ($is_online) = $smcFunc['db']->fetch_row($request);
		if ($is_online)
		{
			return 'online';
		}

		// So at this point, we know the account + character of the source
		// and we know the destination account, and we know they exist.

		// Move the character
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}characters
			SET id_member = {int:dest_acct}
			WHERE id_character = {int:source_chr}',
			[
				'source_chr' => $source_chr,
				'dest_acct' => $dest_acct,
			]
		);

		// Move the character's posts
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}messages
			SET id_member = {int:dest_acct}
			WHERE id_character = {int:source_chr}',
			[
				'source_chr' => $source_chr,
				'dest_acct' => $dest_acct,
			]
		);

		// Update the post counts of both accounts
		if ($row['posts'] > 0)
		{
			// We don't need to fudge individual characters, only the account itself
			// so, add the posts to the dest account.
			updateMemberData($dest_acct, ['posts' => 'posts + ' . $row['posts']]);
			// And subtract from the source acct which we helpfully found earlier
			updateMemberData($row['id_member'], ['posts' => 'posts - ' . $row['posts']]);
		}

		// Reassign topics - find topics started by this particular character
		$topics = [];
		$request = $smcFunc['db']->query('', '
			SELECT t.id_topic
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
			WHERE m.id_character = {int:source_chr}
			ORDER BY NULL',
			[
				'source_chr' => $source_chr,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$topics[] = (int) $row['id_topic'];
		}
		$smcFunc['db']->free_result($request);
		if (!empty($topics))
		{
			$smcFunc['db']->query('', '
				UPDATE {db_prefix}topics
				SET id_member_started = {int:dest_acct}
				WHERE id_topic IN ({array_int:topics})',
				[
					'dest_acct' => $dest_acct,
					'topics' => $topics,
				]
			);
		}
		// Reassign topics - last post in a topic
		$topics = [];
		$request = $smcFunc['db']->query('', '
			SELECT t.id_topic
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (t.id_last_msg = m.id_msg)
			WHERE m.id_character = {int:source_chr}
			ORDER BY NULL',
			[
				'source_chr' => $source_chr,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$topics[] = (int) $row['id_topic'];
		}
		$smcFunc['db']->free_result($request);
		if (!empty($topics))
		{
			$smcFunc['db']->query('', '
				UPDATE {db_prefix}topics
				SET id_member_updated = {int:dest_acct}
				WHERE id_topic IN ({array_int:topics})',
				[
					'dest_acct' => $dest_acct,
					'topics' => $topics,
				]
			);
		}

		return true;
	}

	/**
	 * Load character sheet templates.
	 */
	public static function load_sheet_templates()
	{
		global $context, $smcFunc, $sourcedir;
		require_once($sourcedir . '/Subs-Post.php');

		$context['sheet_templates'] = [];
		// Go fetch the possible templates.
		$request = $smcFunc['db']->query('', '
			SELECT id_template, template_name, template
			FROM {db_prefix}character_sheet_templates
			ORDER BY position ASC');
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$context['sheet_templates'][$row['id_template']] = [
				'name' => $row['template_name'],
				'body' => strtr(un_htmlspecialchars(un_preparsecode($row['template'])), ["\r" => '', '&#039' => '\'']),
			];
		}
		$smcFunc['db']->free_result($request);
	}

	/**
	 * Mark a given character's sheet as unapproved.
	 *
	 * @param int $char Character ID whose character sheet should be marked as unapproved.
	 */
	public static function mark_sheet_unapproved($char)
	{
		global $smcFunc;
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}character_sheet_versions
			SET approval_state = 0
			WHERE id_character = {int:char}',
			[
				'char' => (int) $char,
			]
		);
	}

	/**
	 * Load the profile groups attached to the current character being viewed.
	 *
	 * @return bool True on success (values loaded into $context)
	 */
	public static function load_groups()
	{
		global $txt, $context, $smcFunc, $user_settings;

		$context['member_groups'] = [
			0 => [
				'id' => 0,
				'name' => $txt['no_primary_character_group'],
				'is_primary' => $context['character']['main_char_group'] == 0,
				'can_be_additional' => false,
				'can_be_primary' => true,
			]
		];
		$curGroups = explode(',', $context['character']['char_groups']);

		// Load membergroups, but only those groups the user can assign.
		$request = $smcFunc['db']->query('', '
			SELECT group_name, id_group, hidden
			FROM {db_prefix}membergroups
			WHERE id_group != {int:moderator_group}
				AND is_character = 1' . (allowedTo('admin_forum') ? '' : '
				AND group_type != {int:is_protected}') . '
			ORDER BY group_name',
			[
				'moderator_group' => 3,
				'is_protected' => 1,
				'newbie_group' => 4,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$context['member_groups'][$row['id_group']] = [
				'id' => $row['id_group'],
				'name' => $row['group_name'],
				'is_primary' => $context['character']['main_char_group'] == $row['id_group'],
				'is_additional' => in_array($row['id_group'], $curGroups),
				'can_be_additional' => true,
				'can_be_primary' => $row['hidden'] != 2,
			];
		}
		$smcFunc['db']->free_result($request);

		$context['member']['group_id'] = $user_settings['id_group'];

		return true;
	}

	/**
	 * Deletes a character, regardless of its type.
	 *
	 * @param int $character_id The character to delete. Will also delete OOC character entries.
	 */
	public static function delete_character(int $character_id)
	{
		global $smcFunc, $sourcedir;

		require_once($sourcedir . '/ManageAttachments.php');
		removeAttachments(['id_character' => $character_id, 'attachment_type' => Attachment::ATTACHMENT_AVATAR]);

		// And their custom fields.
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}custom_field_values
			WHERE id_character = {int:char}',
			[
				'char' => $character_id,
			]
		);

		// So we can delete them.
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}characters
			WHERE id_character = {int:char}',
			[
				'char' => $character_id,
			]
		);
	}
}
