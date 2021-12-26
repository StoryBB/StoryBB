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

use StoryBB\Helper\Parser;

/**
 * This class handles characters.
 */
class Character
{
	const SHEET_NORMAL = 0;
	const SHEET_PENDING = 1;
	const SHEET_REJECTED = 2;

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
	 * Get latest revision of any kind of a given character's character sheet.
	 * 
	 * @param int $character_id The character ID
	 * @return ?array An array of the character sheet revision, or null if not found.
	 */
	public static function get_latest_character_sheet(int $character_id): ?array
	{
		global $smcFunc;

		$request = $smcFunc['db']->query('', '
			SELECT id_version, sheet_text, created_time, id_approver, approved_time, approval_state
			FROM {db_prefix}character_sheet_versions
			WHERE id_character = {int:character}
			ORDER BY id_version DESC
			LIMIT 1',
			[
				'character' => $character_id,
			]
		);
		if ($smcFunc['db']->num_rows($request) > 0)
		{
			$return = $smcFunc['db']->fetch_assoc($request);
		}
		$smcFunc['db']->free_result($request);

		return $return ?? null;
	}

	/**
	 * Get timestamp of the last time this character's sheet was submitted for review.
	 *
	 * Note that an approval will modify the last timestamp for submission.
	 *
	 * @param int $character_id The character ID
	 * @return int Either the timestamp of the last submission, or 0 if never submitted.
	 */
	public static function get_last_submitted_timestamp(int $character_id): int
	{
		global $smcFunc;

		$last_submitted = 0;

		$request = $smcFunc['db']->query('', '
			SELECT csv.id_version, csv.created_time, csv_approved.approved_time, chars.char_sheet AS last_approved
			FROM {db_prefix}character_sheet_versions AS csv
			JOIN {db_prefix}characters AS chars ON (csv.id_character = chars.id_character)
			LEFT JOIN {db_prefix}character_sheet_versions AS csv_approved ON (chars.char_sheet = csv_approved.id_version)
			WHERE (csv.approval_state = {int:pending} OR csv.approval_state = {int:rejected})
				AND csv.id_character = {int:character}
			ORDER BY id_version DESC
			LIMIT 1',
				[
					'pending' => static::SHEET_PENDING,
					'rejected' => static::SHEET_REJECTED,
					'character' => $character_id,
				]
			);
		if ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if ($row['approved_time'] > $row['created_time'])
			{
				$last_submitted = (int) $row['approved_time'];
			}
			else
			{
				$last_submitted = (int) $row['created_time'];
			}
		}
		$smcFunc['db']->free_result($request);

		return $last_submitted;
	}

	/**
	 * Get timestamp of the last approved version of a character sheet for a character.
	 *
	 * @param int $character_id The character ID
	 * @return int The timestamp of the character sheet version, or 0 if never approved.
	 */
	public static function get_last_approval_timestamp(int $character_id): int
	{
		global $smcFunc;

		$last_approved = 0;
		$request = $smcFunc['db']->query('', '
			SELECT MAX(approved_time) AS last_approved
			FROM {db_prefix}character_sheet_versions
			WHERE id_approver != 0
				AND id_character = {int:character}',
				[
					'character' => $character_id,
				]
			);
		if ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$last_approved = (int) $row['last_approved'];
		}
		$smcFunc['db']->free_result($request);

		return $last_approved;
	}

	/**
	 * Get character sheet comments.
	 *
	 * @param int $character_id The character ID
	 * @param int $timestamp Comments filtered to after this timestamp
	 * @return array An array of comment records
	 */
	public static function get_sheet_comments(int $character_id, int $timestamp = 0): array
	{
		global $smcFunc, $txt;

		$return = [];

		$request = $smcFunc['db']->query('', '
			SELECT csc.id_comment, csc.id_author, mem.real_name, csc.time_posted, csc.sheet_comment
			FROM {db_prefix}character_sheet_comments AS csc
			LEFT JOIN {db_prefix}members AS mem ON (csc.id_author = mem.id_member)
			WHERE id_character = {int:character}
				AND time_posted > {int:approval}
			ORDER BY id_comment DESC',
			[
				'character' => $character_id,
				'approval' => $timestamp,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (empty($row['real_name']))
			{
				$row['real_name'] = $txt['char_unknown'];
			}
			$row['time_posted_format'] = timeformat($row['time_posted']);
			$row['sheet_comment_parsed'] = Parser::parse_bbc($row['sheet_comment'], true, 'sheet-comment-' . $row['id_comment']);
			$return[$row['id_comment']] = $row;
		}
		$smcFunc['db']->free_result($request);

		return $return;
	}

	/**
	 * Mark a given character's sheet as unapproved.
	 *
	 * @param int $char Character ID whose character sheet should be marked as unapproved.
	 * @param int $new_status The new status to mark the most recent revision as.
	 */
	public static function mark_sheet_unapproved(int $char, int $new_status = Character::SHEET_NORMAL)
	{
		global $smcFunc;

		// First fetch the current status that we're going to unapprove.
		$result = $smcFunc['db']->query('', '
				SELECT id_version
				FROM {db_prefix}character_sheet_versions
				WHERE id_character = {int:char}
				ORDER BY id_version DESC
				LIMIT 1',
			[
				'char' => (int) $char,
			]
		);
		$row = $smcFunc['db']->fetch_assoc($result);
		if (empty($row))
		{
			return;
		}
		$smcFunc['db']->free_result($result);

		$smcFunc['db']->query('', '
			UPDATE {db_prefix}character_sheet_versions
			SET approval_state = {int:new_status}
			WHERE id_version = {int:id_version}
			LIMIT 1',
			[
				'char' => (int) $char,
				'new_status' => $new_status,
				'id_version' => $row['id_version'],
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

		$result = $smcFunc['db']->query('', '
			SELECT id_character, character_name
			FROM {db_prefix}characters
			WHERE id_character = {int:character_id}',
			[
				'character_id' => $character_id,
			]
		);
		$row = $smcFunc['db']->fetch_row($result);
		if (empty($row))
		{
			return;
		}
		[$character_name] = $row;
		$smcFunc['db']->free_result($result);

		// Fix their messages.
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}messages
			SET id_character = 0,
				id_member = 0,
				id_creator = 0,
				poster_name = {string:character_name},
				poster_email = {empty}
			WHERE id_character = {int:id_character}',
			[
				'id_character' => $character_id,
				'character_name' => $character_name,
			]
		);

		// And their avatar.
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

		// And any topic invites they got.
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}topic_invites
			WHERE id_character = {int:char}',
			[
				'char' => $character_id,
			]
		);

		// See if they're in any ships.
		$result = $smcFunc['db']->query('', '
			SELECT id_ship
			FROM {db_prefix}shipper
			WHERE first_character = {int:first_char}
				OR second_character = {int:second_char}',
			[
				'first_char' => $character_id,
				'second_char' => $character_id,
			]
		);
		$ships = [];
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			$ships[$row['id_ship']] = $row['id_ship'];
		}
		$smcFunc['db']->free_result($result);

		if (!empty($ships))
		{
			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}shipper_timeline
				WHERE id_ship IN ({array_int:ships})',
				[
					'ships' => $ships,
				]
			);
			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}shipper
				WHERE id_ship IN ({array_int:ships})',
				[
					'ships' => $ships,
				]
			);
		}

		// And clean up character sheets.
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}character_sheet_versions
			WHERE id_character = {int:char}',
			[
				'char' => $character_id,
			]
		);
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}character_sheet_comments
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

	/**
	 * Returns the number of outstanding character sheets.
	 *
	 * @return int Number of character sheets currently pending review and not explicitly returned to users.
	 */
	public static function count_pending_character_sheets(): int
	{
		global $smcFunc;

		$count = 0;
		$request = $smcFunc['db']->query('', '
			SELECT COUNT(csv2.id_character)
			FROM (
				SELECT MAX(id_version) AS max_ver, id_character
				FROM {db_prefix}character_sheet_versions GROUP BY id_character
			) AS csv
			JOIN {db_prefix}character_sheet_versions AS csv2 ON (csv.max_ver = csv2.id_version)
			WHERE csv2.approval_state = {int:pending}',
			[
				'pending' => static::SHEET_PENDING,
			]
		);
		[$count] = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);
		return (int) $count;
	}
}
