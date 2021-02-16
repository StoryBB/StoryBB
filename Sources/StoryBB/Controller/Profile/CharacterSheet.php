<?php

/**
 * Displays the character sheet page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\StringLibrary;
use StoryBB\Helper\Parser;
use StoryBB\Model\Character;

class CharacterSheet extends AbstractProfileController
{
	use CharacterTrait;

	public function display_action()
	{
		global $context, $txt, $smcFunc, $scripturl, $sourcedir;

		$char_id = $this->init_character();

		// First, get rid of people shouldn't have a sheet at all - the OOC characters
		if ($context['character']['is_main'])
		{
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
		}

		if (isset($_GET['history']))
		{
			return $this->display_history();
		}

		if (isset($_GET['compare']))
		{
			return $this->display_compare();
		}

		if (isset($_GET['edit']))
		{
			return $this->display_edit();
		}

		if (isset($_GET['approve']))
		{
			return $this->display_approve();
		}

		if (isset($_GET['approval']))
		{
			return $this->display_approval();
		}

		if (isset($_GET['reject']))
		{
			return $this->display_reject();
		}

		// Then if we're looking at a character who doesn't have an approved one
		// and the user couldn't see it... you are the weakest link, goodbye.
		if (empty($context['character']['char_sheet']) && empty($context['user']['is_owner']) && !allowedTo('admin_forum'))
		{
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
		}

		// Fetch the current character sheet - for the owner + admin, show most recent
		// whatever, for everyone else show them the most recent approved
		if ($context['user']['is_owner'] || allowedTo('admin_forum'))
		{
			$request = $smcFunc['db']->query('', '
				SELECT id_version, sheet_text, created_time, id_approver, approved_time, approval_state
				FROM {db_prefix}character_sheet_versions
				WHERE id_character = {int:character}
				ORDER BY id_version DESC
				LIMIT 1',
				[
					'character' => $context['character']['id_character'],
				]
			);
			if ($smcFunc['db']->num_rows($request) > 0)
			{
				$context['character']['sheet_details'] = $smcFunc['db']->fetch_assoc($request);
				$smcFunc['db']->free_result($request);
			}
		}
		else
		{
			$request = $smcFunc['db']->query('', '
				SELECT id_version, sheet_text, created_time, id_approver, approved_time, approval_state
				FROM {db_prefix}character_sheet_versions
				WHERE id_version = {int:version}',
				[
					'version' => $context['character']['char_sheet'],
				]
			);
			$context['character']['sheet_details'] = $smcFunc['db']->fetch_assoc($request);
			$smcFunc['db']->free_result($request);
		}

		if (!empty($context['character']['sheet_details']['sheet_text'])) {
			$context['character']['sheet_details']['sheet_text'] = Parser::parse_bbc($context['character']['sheet_details']['sheet_text'], false);
		} else {
			$context['character']['sheet_details']['sheet_text'] = '';
		}

		$context['sub_template'] = 'profile_character_sheet';

		$context['sheet_buttons'] = [];
		$context['show_sheet_comments'] = false;
		if ($context['user']['is_owner'] || allowedTo('admin_forum'))
		{
			// Always have an edit button
			$context['sheet_buttons']['edit'] = [
				'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=character_sheet;edit;char=' . $context['character']['id_character'],
				'text' => 'char_sheet_edit',
			];
			// Only have a history button if there's actually some history
			if (!empty($context['character']['sheet_details']['sheet_text']))
			{
				$context['sheet_buttons']['history'] = [
					'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=character_sheet;history;char=' . $context['character']['id_character'],
					'text' => 'char_sheet_history',
				];
				// Only have a send-for-approval button if it hasn't been approved
				// and it hasn't yet been sent for approval either
				if (empty($context['character']['sheet_details']['id_approver']) && empty($context['character']['sheet_details']['approval_state']))
				{
					$context['sheet_buttons']['send_for_approval'] = [
						'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=character_sheet;approval;char=' . $context['character']['id_character'] . ';' . $context['session_var'] . '=' . $context['session_id'],
						'text' => 'char_sheet_send_for_approval',
					];
				}
			}
			// Compare to last approved only if we had a previous approval and the
			// current one isn't approved right now
			if (empty($context['character']['sheet_details']['id_approver']) && !empty($context['character']['char_sheet']))
			{
				$context['sheet_buttons']['compare'] = [
					'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=character_sheet;compare;char=' . $context['character']['id_character'],
					'text' => 'char_sheet_compare',
				];
			}
			// And the infamous approve button
			if (!empty($context['character']['sheet_details']['sheet_text']) && empty($context['character']['sheet_details']['id_approver']) && allowedTo('admin_forum'))
			{
				$context['sheet_buttons']['approve'] = [
					'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=character_sheet;approve;version=' . $context['character']['sheet_details']['id_version'] . ';char=' . $context['character']['id_character'] . ';' . $context['session_var'] . '=' . $context['session_id'],
					'text' => 'char_sheet_approve',
					'custom' => 'onclick="return confirm(' . JavaScriptEscape($txt['char_sheet_approve_are_you_sure']) . ')"',
				];
			}
			// And if it's pending approval and we might want to kick it back?
			if (!empty($context['character']['sheet_details']['approval_state']) && empty($context['character']['sheet_details']['id_approver']) && allowedTo('admin_forum'))
			{
				$context['sheet_buttons']['reject'] = [
					'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=character_sheet;reject;version=' . $context['character']['sheet_details']['id_version'] . ';char=' . $context['character']['id_character'] . ';' . $context['session_var'] . '=' . $context['session_id'],
					'text' => 'char_sheet_reject',
					'custom' => 'onclick="return confirm(' . JavaScriptEscape($txt['char_sheet_reject_are_you_sure']) . ')"',
				];
			}

			// And since this is the owner or admin, we should look at comments.
			if (!empty($context['character']['sheet_details']['sheet_text'])) {
				$context['show_sheet_comments'] = true;
				$context['sheet_comments'] = [];
				// First, find the time of the last approved case.
				$last_approved = 0;
				$request = $smcFunc['db']->query('', '
					SELECT MAX(approved_time) AS last_approved
					FROM {db_prefix}character_sheet_versions
					WHERE id_approver != 0
						AND id_character = {int:character}',
						[
							'character' => $context['character']['id_character'],
						]
					);
				if ($row = $smcFunc['db']->fetch_assoc($request))
				{
					$last_approved = (int) $row['last_approved'];
				}
				$smcFunc['db']->free_result($request);

				// Now get any comments for this character since the last approval.
				$request = $smcFunc['db']->query('', '
					SELECT csc.id_comment, csc.id_author, mem.real_name, csc.time_posted, csc.sheet_comment
					FROM {db_prefix}character_sheet_comments AS csc
					LEFT JOIN {db_prefix}members AS mem ON (csc.id_author = mem.id_member)
					WHERE id_character = {int:character}
						AND time_posted > {int:approval}
					ORDER BY id_comment DESC',
					[
						'character' => $context['character']['id_character'],
						'approval' => $last_approved,
					]
				);
				while ($row = $smcFunc['db']->fetch_assoc($request))
				{
					$row['sheet_comment_parsed'] = Parser::parse_bbc($row['sheet_comment'], true, 'sheet-comment-' . $row['id_comment']);
					$context['sheet_comments'][$row['id_comment']] = $row;
				}
				$smcFunc['db']->free_result($request);
			}

			// Make an editor box
			require_once($sourcedir . '/Subs-Post.php');
			require_once($sourcedir . '/Subs-Editor.php');

			// Now create the editor.
			$editorOptions = [
				'id' => 'message',
				'value' => '',
				'labels' => [
					'post_button' => $txt['save'],
				],
				// add height and width for the editor
				'height' => '175px',
				'width' => '100%',
				'preview_type' => 0,
				'required' => true,
			];
			create_control_richedit($editorOptions);
		}
	}

	public function post_action()
	{
		global $context, $txt, $smcFunc, $scripturl, $sourcedir;

		$char_id = $this->init_character();

		if (isset($_GET['edit']))
		{
			return $this->post_edit();
		}

		// First, get rid of people shouldn't have a sheet at all - the OOC characters
		if ($context['character']['is_main'])
		{
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
		}

		// Fetch the current character sheet - for the owner + admin, show most recent
		// whatever, for everyone else show them the most recent approved
		if ($context['user']['is_owner'] || allowedTo('admin_forum'))
		{
			$request = $smcFunc['db']->query('', '
				SELECT id_version, sheet_text, created_time, id_approver, approved_time, approval_state
				FROM {db_prefix}character_sheet_versions
				WHERE id_character = {int:character}
				ORDER BY id_version DESC
				LIMIT 1',
				[
					'character' => $context['character']['id_character'],
				]
			);
			if ($smcFunc['db']->num_rows($request) > 0)
			{
				$context['character']['sheet_details'] = $smcFunc['db']->fetch_assoc($request);
				$smcFunc['db']->free_result($request);
			}

			if (isset($_POST['message']))
			{
				// We might be saving a comment on this.
				checkSession();
				require_once($sourcedir . '/Subs-Post.php');
				require_once($sourcedir . '/Subs-Editor.php');

				$message = StringLibrary::escape($_POST['message'], ENT_QUOTES);
				preparsecode($message);

				if (!empty($message))
				{
					// WE GAHT ONE!!!!!!!!!
					$smcFunc['db']->insert('insert',
						'{db_prefix}character_sheet_comments',
						['id_character' => 'int', 'id_author' => 'int', 'time_posted' => 'int', 'sheet_comment' => 'string'],
						[$context['character']['id_character'], $context['user']['id'], time(), $message],
						['id_comment']
					);
				}
			}
		}

		redirectexit('action=profile;u=' . $context['id_member'] . ';area=character_sheet;char=' . $context['character']['id_character']);
	}

	public function display_history()
	{
		global $context, $txt, $smcFunc;

		// Then we need to be either the owner or an admin to see this.
		if (empty($context['user']['is_owner']) && !allowedTo('admin_forum'))
		{
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
		}

		$context['history_items'] = [];

		// First, get all the sheet versions.
		$request = $smcFunc['db']->query('', '
			SELECT id_version, sheet_text, created_time, id_approver,
				mem.real_name AS approver_name, approved_time
			FROM {db_prefix}character_sheet_versions AS csv
			LEFT JOIN {db_prefix}members AS mem ON (csv.id_approver = mem.id_member)
			WHERE csv.id_character = {int:char}
			ORDER BY NULL',
			[
				'char' => $context['character']['id_character'],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (!empty($row['id_approver']))
			{
				$context['history_items'][$row['approved_time'] . 'a' . $row['id_version']] = $row['id_version'];
			}
			$row['type'] = 'sheet';
			$row['sheet_text_parsed'] = Parser::parse_bbc($row['sheet_text'], false);
			$row['created_time_format'] = timeformat($row['created_time']);
			$row['approved_time_format'] = timeformat($row['approved_time']);
			if (empty($row['approver_name']))
			{
				$row['approver_name'] = $txt['char_unknown'];
			}
			$context['history_items'][$row['created_time'] . 'S' . $row['id_version']] = $row;
		}
		$smcFunc['db']->free_result($request);

		// Then get all the comments.
		$request = $smcFunc['db']->query('', '
			SELECT id_comment, id_author, mem.real_name, time_posted, sheet_comment
			FROM {db_prefix}character_sheet_comments AS csc
			LEFT JOIN {db_prefix}members AS mem ON (csc.id_author = mem.id_member)
			WHERE csc.id_character = {int:char}
			ORDER BY NULL',
			[
				'char' => $context['character']['id_character'],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$row['type'] = 'comment';
			$row['sheet_comment_parsed'] = Parser::parse_bbc($row['sheet_comment'], true, 'sheet-comment-' . $row['id_comment']);
			$row['time_posted_format'] = timeformat($row['time_posted']);
			$context['history_items'][$row['time_posted'] . 'c' . $row['id_comment']] = $row;
		}
		$smcFunc['db']->free_result($request);

		// Then stand back and do some magic.
		// We spliced this array together unordered using timestamp + c/S + id
		// comments will implicitly be sorted as chronologically after
		// sheet versions as a result and id to avoid clashes.
		krsort($context['history_items']);

		$context['page_title'] = $txt['char_sheet_history'];
		$context['sub_template'] = 'profile_character_sheet_history';

		addInlineJavascript('
		$(".click_collapse, .windowbg .sheet").hide();
		$(".click_expand, .click_collapse").on("click", function(e) {
			e.preventDefault();
			$(this).closest(".windowbg").find(".click_expand, .click_collapse, .sheet").toggle();
		});
		', true);
	}

	public function display_compare()
	{
		global $context, $txt, $smcFunc;

		// First, get rid of people shouldn't have a sheet at all - the OOC characters
		if ($context['character']['is_main'])
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

		// Then if we're looking at a character who doesn't have an approved one
		// and the user couldn't see it... you are the weakest link, goodbye.
		if (empty($context['character']['char_sheet']) && empty($context['user']['is_owner']) && !allowedTo('admin_forum'))
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

		// So, does the user have a current-not-yet-approved one? We need to get
		// the latest to find this out.
		$request = $smcFunc['db']->query('', '
			SELECT id_version, sheet_text, created_time, id_approver, approved_time, approval_state
			FROM {db_prefix}character_sheet_versions
			WHERE id_character = {int:character}
				AND id_version > {int:current_version}
			ORDER BY id_version DESC
			LIMIT 1',
			[
				'character' => $context['character']['id_character'],
				'current_version' => $context['character']['char_sheet'],
			]
		);
		if ($smcFunc['db']->num_rows($request) == 0)
		{
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=character_sheet;char=' . $context['character']['id_character']);
		}
		$context['character']['sheet_details'] = $smcFunc['db']->fetch_assoc($request);
		$smcFunc['db']->free_result($request);

		// Now we need to go get the currently approved one too.
		$request = $smcFunc['db']->query('', '
			SELECT id_version, sheet_text, created_time, id_approver, approved_time, approval_state
			FROM {db_prefix}character_sheet_versions
			WHERE id_version = {int:current_version}',
			[
				'current_version' => $context['character']['char_sheet'],
			]
		);
		$context['character']['original_sheet'] = $smcFunc['db']->fetch_assoc($request);
		$smcFunc['db']->free_result($request);

		// And parse the bbc.
		foreach (['original_sheet', 'sheet_details'] as $sheet) {
			$context['character'][$sheet]['sheet_text_parsed'] = Parser::parse_bbc($context['character'][$sheet]['sheet_text'], false);
		}

		$context['page_title'] = $txt['char_sheet_compare'];
		$context['sub_template'] = 'profile_character_sheet_compare';
	}

	public function display_edit()
	{
		global $context, $txt, $smcFunc, $sourcedir;

		loadLanguage('Admin');

		loadJavascriptFile('sheet_preview.js', ['default_theme' => true]);

		// First, get rid of people shouldn't have a sheet at all - the OOC characters
		if ($context['character']['is_main'])
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

		// Then if we're looking at a character who doesn't have an approved one
		// and the user couldn't see it... you are the weakest link, goodbye.
		if (empty($context['character']['char_sheet']) && empty($context['user']['is_owner']) && !allowedTo('admin_forum'))
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

		$request = $smcFunc['db']->query('', '
			SELECT id_version, sheet_text, created_time, id_approver, approved_time, approval_state
			FROM {db_prefix}character_sheet_versions
			WHERE id_character = {int:character}
			ORDER BY id_version DESC
			LIMIT 1',
			[
				'character' => $context['character']['id_character'],
			]
		);
		if ($smcFunc['db']->num_rows($request) > 0)
		{
			$context['character']['sheet_details'] = $smcFunc['db']->fetch_assoc($request);
			$smcFunc['db']->free_result($request);
		}

		// Make an editor box
		require_once($sourcedir . '/Subs-Post.php');
		require_once($sourcedir . '/Subs-Editor.php');

		if (!isset($context['sheet_preview_raw']))
		{
			$context['sheet_preview_raw'] = !empty($context['character']['sheet_details']['sheet_text']) ? un_preparsecode($context['character']['sheet_details']['sheet_text']) : '';
		}

		// Now create the editor.
		$editorOptions = [
			'id' => 'message',
			'value' => $context['sheet_preview_raw'],
			'labels' => [
				'post_button' => $txt['save'],
			],
			// add height and width for the editor
			'height' => '500px',
			'width' => '100%',
			'preview_type' => 0,
			'required' => true,
		];
		create_control_richedit($editorOptions);

		Character::load_sheet_templates();

		addInlineJavascript('
		var sheet_templates = ' . json_encode($context['sheet_templates']) . ';
		$("#insert_char_template").on("click", function (e) {
			e.preventDefault();
			var tmpl = $("#char_sheet_template").val();
			if (sheet_templates.hasOwnProperty(tmpl))
				$("#message").data("sceditor").InsertText(sheet_templates[tmpl].body);
		});', true);

		// Now fetch the comments
		$context['sheet_comments'] = [];
		if (!empty($context['character']['sheet_details']['created_time']) && empty($context['character']['sheet_details']['id_approver']))
		{
			$request = $smcFunc['db']->query('', '
				SELECT id_comment, id_author, mem.real_name, time_posted, sheet_comment
				FROM {db_prefix}character_sheet_comments AS csc
					LEFT JOIN {db_prefix}members AS mem ON (csc.id_author = mem.id_member)
				WHERE id_character = {int:character}
					AND time_posted > {int:last_approved_time}
				ORDER BY NULL',
				[
					'character' => $context['character']['id_character'],
					'last_approved_time' => $context['character']['sheet_details']['created_time'],
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				if (empty($row['real_name']))
					$row['real_name'] = $txt['char_unknown'];
				$context['sheet_comments'][$row['id_comment']] = $row;
			}
			$smcFunc['db']->free_result($request);
			krsort($context['sheet_comments']);
		}

		$context['sub_template'] = 'profile_character_sheet_edit';
	}

	public function post_edit()
	{
		global $context, $txt, $smcFunc, $sourcedir;

		loadLanguage('Admin');

		// Make an editor box
		require_once($sourcedir . '/Subs-Post.php');
		require_once($sourcedir . '/Subs-Editor.php');

		// Then try to get some content.
		$message = StringLibrary::escape($_POST['message'], ENT_QUOTES);
		preparsecode($message);

		if (!empty($message))
		{
			if (!empty($_POST['preview']))
			{
				$context['sheet_preview_raw'] = un_preparsecode($message);
				$context['sheet_preview'] = Parser::parse_bbc($message, false);
				return $this->display_action();
			}
			// So we have a character sheet. Let's do a comparison against
			// the last character sheet saved just in case the user did something
			// a little bit weird/silly.
			if (empty($context['character']['sheet_details']['sheet_text']) || $message != $context['character']['sheet_details']['sheet_text'])
			{
				// It's different, good. So insert it, making it await approval.
				$smcFunc['db']->insert('insert',
					'{db_prefix}character_sheet_versions',
					[
						'sheet_text' => 'string', 'id_character' => 'int', 'id_member' => 'int',
						'created_time' => 'int', 'id_approver' => 'int', 'approved_time' => 'int', 'approval_state' => 'int'
					],
					[
						$message, $context['character']['id_character'], $context['user']['id'],
						time(), 0, 0, 0
					],
					['id_version']
				);
				// Mark previous versions of the character sheet as not awaited approval.
				Character::mark_sheet_unapproved($context['character']['id_character']);
			}
		}

		redirectexit('action=profile;u=' . $context['id_member'] . ';area=character_sheet;char=' . $context['character']['id_character']);
	}

	public function display_approval()
	{
		global $smcFunc, $context, $sourcedir;

		checkSession('get');

		// First, get rid of people shouldn't have a sheet at all - the OOC characters
		if ($context['character']['is_main'])
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

		// Then if we're looking at a character who doesn't have an approved one
		// and the user couldn't see it... you are the weakest link, goodbye.
		if (empty($context['user']['is_owner']))
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

		// So which one are we offering up for approval?
		// First, find the last approved case.
		$last_approved = 0;
		$request = $smcFunc['db']->query('', '
			SELECT MAX(id_version) AS last_approved
			FROM {db_prefix}character_sheet_versions
			WHERE id_approver != 0
				AND id_character = {int:character}',
				[
					'character' => $context['character']['id_character'],
				]
			);
		if ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$last_approved = (int) $row['last_approved'];
		}
		$smcFunc['db']->free_result($request);

		// Now find the highest version after the last approved (or highest ever)
		// for this character.
		$request = $smcFunc['db']->query('', '
			SELECT MAX(id_version) AS highest_id
			FROM {db_prefix}character_sheet_versions
			WHERE id_version > {int:last_approved}
				AND id_character = {int:character}',
				[
					'last_approved' => $last_approved,
					'character' => $context['character']['id_character'],
				]
			);
		$row = $smcFunc['db']->fetch_assoc($request);
		if (empty($row))
		{
			// There isn't a version to mark as pending approval.
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
		}

		// OK, time to mark it as ready for approval.
		$request = $smcFunc['db']->query('', '
			UPDATE {db_prefix}character_sheet_versions
			SET approval_state = 1
			WHERE id_version = {int:version}',
			[
				'version' => $row['highest_id'],
			]
		);

		// Now notify peoples that this is a thing.
		require_once($sourcedir . '/Subs-Members.php');
		$admins = membersAllowedTo('admin_forum');

		$alert_rows = [];
		foreach ($admins as $id_member)
		{
			$alert_rows[] = [
				'alert_time' => time(),
				'id_member' => $id_member,
				'id_member_started' => $context['id_member'],
				'member_name' => $context['member']['name'],
				'chars_src' => $context['character']['id_character'],
				'content_type' => 'member',
				'content_id' => 0,
				'content_action' => 'char_sheet_approval',
				'is_read' => 0,
				'extra' => '',
			];
		}

		if (!empty($alert_rows))
		{
			$smcFunc['db']->insert('',
				'{db_prefix}user_alerts',
				['alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string', 'chars_src' => 'int',
					'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'],
				$alert_rows,
				[]
			);
			updateMemberData($admins, ['alerts' => '+']);
		}

		redirectexit('action=profile;u=' . $context['id_member'] . ';area=character_sheet;char=' . $context['character']['id_character']);
	}

	public function display_approve()
	{
		global $context, $smcFunc;

		checkSession('get');
		isAllowedTo('admin_forum');

		// If we're here, we have a valid character ID on a valid user ID.
		// We need to check that 1) we have a character sheet to approve,
		// 2) it requires approving, and 3) it's the most recent one.
		$version = isset($_GET['version']) ? (int) $_GET['version'] : 0;
		if (empty($version))
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

		$request = $smcFunc['db']->query('', '
			SELECT id_character, id_approver, approval_state
			FROM {db_prefix}character_sheet_versions
			WHERE id_version = {int:version}',
			[
				'version' => $version,
			]
		);
		if ($smcFunc['db']->num_rows($request) == 0)
		{
			// Doesn't exist, so bail.
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
		}

		$row = $smcFunc['db']->fetch_assoc($request);
		$smcFunc['db']->free_result($request);

		// Correct character?
		if ($row['id_character'] != $context['character']['id_character'])
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

		// Has it already been approved?
		if (!empty($row['id_approver']))
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

		// Last test: any other rows for this user
		$request = $smcFunc['db']->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}character_sheet_versions
			WHERE id_version > {int:version}
				AND id_character = {int:character}',
			[
				'version' => $version,
				'character' => $context['character']['id_character'],
			]
		);
		list ($count) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		if ($count > 0)
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

		// OK, so this version is good to go for approval. Approve the sheet...
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}character_sheet_versions
			SET id_approver = {int:approver},
				approved_time = {int:time},
				approval_state = {int:zero}
			WHERE id_version = {int:version}',
			[
				'approver' => $context['user']['id'],
				'time' => time(),
				'zero' => 0,
				'version' => $version,
			]
		);
		// And the character...
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}characters
			SET char_sheet = {int:version}
			WHERE id_character = {int:character}',
			[
				'version' => $version,
				'character' => $context['character']['id_character'],
			]
		);

		// And send the character sheet owner an alert.
		$smcFunc['db']->insert('',
			'{db_prefix}user_alerts',
			['alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
				'chars_src' => 'int', 'chars_dest' => 'int',
				'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'],
			[time(), $context['id_member'], $context['user']['id'], '',
				0, $context['character']['id_character'],
				'member', 0, 'char_sheet_approved', 0, ''],
			[]
		);
		updateMemberData($context['id_member'], ['alerts' => '+']);

		redirectexit('action=profile;u=' . $context['id_member'] . ';area=character_sheet;char=' . $context['character']['id_character']);
	}

	public function display_reject()
	{
		global $context;

		checkSession('get');

		// First, get rid of people shouldn't have a sheet at all - the OOC characters
		if ($context['character']['is_main'])
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

		// Then we're not an admin...
		if (!allowedTo('admin_forum'))
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

		Character::mark_sheet_unapproved($context['character']['id_character']);
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=character_sheet;char=' . $context['character']['id_character']);
	}
}
