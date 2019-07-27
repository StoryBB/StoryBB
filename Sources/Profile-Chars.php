<?php
/**
 * This file provides handling for character-specific features within the profile area.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Helper\Autocomplete;
use StoryBB\Helper\Parser;

/**
 * Setup to fetch the HTML for the characters popup (excluding all other forum chrome)
 *
 * @param int $memID The user ID that we are fetching for, passed automatically from caller
 */
function characters_popup($memID)
{
	global $context, $user_info, $sourcedir, $db_show_debug, $cur_profile, $smcFunc;

	// We do not want to output debug information here.
	$db_show_debug = false;

	// We only want to output our little layer here.
	StoryBB\Template::set_layout('raw');
	StoryBB\Template::remove_all_layers();
	$context['sub_template'] = 'profile_character_popup';

	$context['current_characters'] = $cur_profile['characters'];
}

/**
 * Handle switching the active character on the currently logged in account.
 *
 * @param int $memID The current user ID
 * @param int $char The character ID to switch to (if not supplied, fetched from GET)
 * @param bool $return Whether to return to main flow or not, normally redirects
 * @return bool If returning to main flow, true on success. Will end execution otherwise.
 */
function char_switch($memID, $char = null, $return = false)
{
	global $smcFunc, $modSettings;

	if (!$return) {
		checkSession('get');
	}

	if ($char === null)
		$char = isset($_GET['char']) ? (int) $_GET['char'] : 0;

	if (empty($char)) {
		if ($return)
			return false;
		else
			die;
	}
	// Let's check the user actually owns this character
	$result = $smcFunc['db_query']('', '
		SELECT id_character, id_member
		FROM {db_prefix}characters
		WHERE id_character = {int:id_character}
			AND id_member = {int:id_member}
			AND retired = 0',
		array(
			'id_character' => $char,
			'id_member' => $memID,
		)
	);
	$found = $smcFunc['db_num_rows']($result) > 0;
	$smcFunc['db_free_result']($result);

	if (!$found) {
		if ($return)
			return false;
		else
			die;
	}

	// So it's valid. Update the members table first of all.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET current_character = {int:id_character}
		WHERE id_member = {int:id_member}',
		array(
			'id_character' => $char,
			'id_member' => $memID,
		)
	);
	// Now the online log too.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_online
		SET id_character = {int:id_character}
		WHERE id_member = {int:id_member}',
		array(
			'id_character' => $char,
			'id_member' => $memID,
		)
	);
	// And last active
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}characters
		SET last_active = {int:last_active}
		WHERE id_character = {int:character}',
		array(
			'last_active' => time(),
			'character' => $char,
		)
	);

	// If caching would have cached the user's record, nuke it.
	if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
		cache_put_data('user_settings-' . $id_member, null, 60);

	// Whatever they had in session for theme, disregard it.
	unset ($_SESSION['id_theme']);

	if ($return)
		return true;
	else
		die;
}

/**
 * Handle switching the active character on the currently logged in account, and redirecting back to the profile on completion.
 * Character ID to switch to is pulled from GET.
 *
 * @param int $memID The current user ID
 */
function char_switch_redir($memID)
{
	checkSession('get');

	$char = isset($_GET['char']) ? (int) $_GET['char'] : 0;

	if (char_switch($memID, $char, true)) {
		redirectexit('action=profile;u=' . $memID . ';area=characters;char=' . $char);
	}

	redirectexit('action=profile;u=' . $memID);
}

/**
 * Core dispatcher for the characters area within the profile page.
 * Also identifies current character from GET where specified. Unless a different action is given, will present the character summary.
 *
 * @param int $memID The current user ID
 */
function character_profile($memID)
{
	global $user_profile, $context, $scripturl, $modSettings, $smcFunc, $txt, $user_info;

	$char_id = isset($_GET['char']) ? (int) $_GET['char'] : 0;
	if (!isset($user_profile[$memID]['characters'][$char_id])) {
		// character doesn't exist... bye.
		redirectexit('action=profile;u=' . $memID);
	}

	$context['character'] = $user_profile[$memID]['characters'][$char_id];
	$context['character']['editable'] = $context['user']['is_owner'] || allowedTo('admin_forum');
	$context['user']['can_admin'] = allowedTo('admin_forum');

	$context['character']['retire_eligible'] = !$context['character']['is_main'];
	if ($context['user']['is_owner'] && $user_info['id_character'] == $context['character']['id_character'])
	{
		$context['character']['retire_eligible'] = false; // Can't retire if you're logged in as them
	}

	$context['linktree'][] = array(
		'name' => $txt['chars_menu_title'],
		'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . '#user_char_list',
	);
	$context['linktree'][] = array(
		'name' => $context['character']['character_name'],
		'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=characters;sa=profile;char=' . $char_id,
	);
	$subactions = array(
		'edit' => 'char_edit',
		'theme' => 'char_theme',
		'sheet' => 'char_sheet',
		'retire' => 'char_retire',
		'move_acct' => 'char_move_account',
		'sheet_edit' => 'char_sheet_edit',
		'sheet_approval' => 'char_sheet_approval',
		'sheet_approve' => 'char_sheet_approve',
		'sheet_reject' => 'char_sheet_reject',
		'sheet_compare' => 'char_sheet_compare',
		'sheet_history' => 'char_sheet_history',
		'delete' => 'char_delete',
		'posts' => 'char_posts',
		'topics' => 'char_posts',
		'stats' => 'char_stats',
	);
	if (isset($_GET['sa'], $subactions[$_GET['sa']])) {
		$func = $subactions[$_GET['sa']];
		return $func();
	}

	$theme_id = !empty($context['character']['id_theme']) ? $context['character']['id_theme'] : $modSettings['theme_guests'];
	$request = $smcFunc['db_query']('', '
		SELECT value
		FROM {db_prefix}themes
		WHERE id_theme = {int:id_theme}
			AND variable = {string:variable}
		LIMIT 1', array(
			'id_theme' => $theme_id,
			'variable' => 'name',
		)
	);
	list ($context['character']['theme_name']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$context['character']['days_registered'] = (int) ((time() - $context['character']['date_created']) / (3600 * 24));
	$context['character']['date_created_format'] = timeformat($context['character']['date_created']);
	$context['character']['last_active_format'] = timeformat($context['character']['last_active']);
	$context['character']['signature_parsed'] = Parser::parse_bbc($context['character']['signature'], true, 'sig_char' . $context['character']['id_character']);
	if (empty($context['character']['date_created']) || $context['character']['days_registered'] < 1)
		$context['character']['posts_per_day'] = $txt['not_applicable'];
	else
		$context['character']['posts_per_day'] = comma_format($context['character']['posts'] / $context['character']['days_registered'], 2);

	$context['sub_template'] = 'profile_character_summary';
}

/**
 * Creating a character, both the initial form and actually performing the creation.
 */
function char_create()
{
	global $context, $smcFunc, $txt, $sourcedir, $user_info, $modSettings;

	loadLanguage('Admin');

	$context['sub_template'] = 'profile_character_create';
	
	$context['character'] = [
		'character_name' => '',
		'age' => '',
		'sheet' => '',
	];

	$context['form_errors'] = [];

	// Make an editor box
	require_once($sourcedir . '/Subs-Post.php');
	require_once($sourcedir . '/Subs-Editor.php');

	// See if they're saving.
	if (isset($_POST['create_char']))
	{
		checkSession();
		$context['character']['character_name'] = !empty($_POST['char_name']) ? $smcFunc['htmlspecialchars'](trim($_POST['char_name']), ENT_QUOTES) : '';
		$context['character']['age'] = !empty($_POST['age']) ? $smcFunc['htmlspecialchars']($_POST['age'], ENT_QUOTES) : '';
		$message = $smcFunc['htmlspecialchars']($_POST['message'], ENT_QUOTES);
		preparsecode($message);
		$context['character']['sheet'] = $message;

		if ($context['character']['character_name'] == '')
			$context['form_errors'][] = $txt['char_error_character_must_have_name'];
		else
		{
			// Check if the name already exists.
			$result = $smcFunc['db_query']('', '
				SELECT COUNT(*)
				FROM {db_prefix}characters
				WHERE character_name LIKE {string:new_name}',
				array(
					'new_name' => $context['character']['character_name'],
				)
			);
			list ($matching_names) = $smcFunc['db_fetch_row']($result);
			$smcFunc['db_free_result']($result);

			if ($matching_names)
				$context['form_errors'][] = $txt['char_error_duplicate_character_name'];
		}

		if (empty($context['form_errors']))
		{
			// So no errors, we can save this new character, yay!
			$smcFunc['db_insert']('insert',
				'{db_prefix}characters',
				['id_member' => 'int', 'character_name' => 'string', 'avatar' => 'string',
					'signature' => 'string', 'id_theme' => 'int', 'posts' => 'int',
					'age' => 'string', 'date_created' => 'int', 'last_active' => 'int',
					'is_main' => 'int', 'main_char_group' => 'int', 'char_groups' => 'string',
					'char_sheet' => 'int', 'retired' => 'int'],
				[$context['id_member'], $context['character']['character_name'], '',
					'', 0, 0,
					$context['character']['age'], time(), time(),
					0, 0, '',
					0, 0],
				['id_character']
			);
			$context['character']['id_character'] = $smcFunc['db']->inserted_id();
			trackStats(array('chars' => '+'));

			if (!empty($context['character']['sheet']))
			{
				// Also gotta insert this.
				$smcFunc['db_insert']('insert',
					'{db_prefix}character_sheet_versions',
					['sheet_text' => 'string', 'id_character' => 'int', 'id_member' => 'int',
						'created_time' => 'int', 'id_approver' => 'int', 'approved_time' => 'int', 'approval_state' => 'int'],
					[$context['character']['sheet'], $context['character']['id_character'], $context['id_member'],
						time(), 0, 0, 0],
					['id_version']
				);
			}
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
		}
	}

	// Now create the editor.
	$editorOptions = array(
		'id' => 'message',
		'value' => un_preparsecode($context['character']['sheet']),
		'labels' => array(
			'post_button' => $txt['save'],
		),
		// add height and width for the editor
		'height' => '500px',
		'width' => '80%',
		'preview_type' => 0,
		'required' => true,
	);
	create_control_richedit($editorOptions);

	load_char_sheet_templates();

	addInlineJavascript('
	var sheet_templates = ' . json_encode($context['sheet_templates']) . ';
	$("#insert_char_template").on("click", function (e) {
		e.preventDefault();
		var tmpl = $("#char_sheet_template").val();
		if (sheet_templates.hasOwnProperty(tmpl))
			$("#message").data("sceditor").InsertText(sheet_templates[tmpl].body);
	});', true);
}

/**
 * Editing a character, both showing the form and performing the edit.
 */
function char_edit()
{
	global $context, $smcFunc, $txt, $sourcedir, $user_info, $modSettings, $scripturl;
	global $profile_vars, $settings;

	// If they don't have permission to be here, goodbye.
	if (!$context['character']['editable']) {
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
	}

	$context['sub_template'] = 'profile_character_edit';
	loadJavascriptFile('chars.js', array('default_theme' => true), 'chars');

	$context['character']['groups_editable'] = false;
	if (allowedTo('manage_membergroups') && !$context['character']['is_main'])
	{
		$context['character']['groups_editable'] = true;
		profileLoadCharGroups();
	}

	require_once($sourcedir . '/Subs-Post.php');
	require_once($sourcedir . '/Profile-Modify.php');
	profileLoadSignatureData();

	$context['form_errors'] = [];
	$default_avatar = $settings['images_url'] . '/default.png';

	$context['character']['avatar_settings'] = array(
		'custom' => stristr($context['character']['avatar'], 'http://') || stristr($context['character']['avatar'], 'https://') ? $context['character']['avatar'] : 'http://',
		'selection' => $context['character']['avatar'] == '' || (stristr($context['character']['avatar'], 'http://') || stristr($context['character']['avatar'], 'https://')) ? '' : $context['character']['avatar'],
		'allow_upload' => allowedTo('profile_upload_avatar') || (!$context['user']['is_owner'] && allowedTo('profile_extra_any')),
		'allow_external' => allowedTo('profile_remote_avatar') || (!$context['user']['is_owner'] && allowedTo('profile_extra_any')),
	);

	if ((!empty($context['character']['avatar']) && $context['character']['avatar'] != $default_avatar) && $context['character']['id_attach'] > 0 && $context['character']['avatar_settings']['allow_upload'])
	{
		$context['character']['avatar_settings'] += array(
			'choice' => 'upload',
			'external' => 'http://'
		);
		$context['character']['avatar'] = $modSettings['custom_avatar_url'] . '/' . $context['character']['avatar_filename'];
	}
	// Use "avatar_original" here so we show what the user entered even if the image proxy is enabled
	elseif ((stristr($context['character']['avatar'], 'http://') || stristr($context['character']['avatar'], 'https://')) && $context['character']['avatar_settings']['allow_external'] && $context['character']['avatar'] != $default_avatar)
		$context['character']['avatar_settings'] += array(
			'choice' => 'external',
			'external' => $context['character']['avatar_original']
		);
	else
		$context['character']['avatar_settings'] += array(
			'choice' => 'none',
			'external' => 'http://'
		);

	$context['character']['avatar_settings']['is_url'] = (strpos($context['character']['avatar_settings']['external'], 'https://') === 0) || (strpos($context['character']['avatar_settings']['external'], 'http://') === 0);

	if (isset($_POST['edit_char']))
	{
		loadLanguage('Errors');

		checkSession();
		validateToken('edit-char' . $context['character']['id_character'], 'post');

		$changes = [];

		$avatar_value = !empty($_POST['avatar_choice']) ? $_POST['avatar_choice'] : '';
		$state = profileSaveAvatarData($avatar_value);
		if ($state !== false) {
			$context['form_errors']['bad_avatar'] = $txt['profile_error_' . $state];
		}
		elseif (isset($profile_vars['avatar']))
		{
			$context['character']['avatar'] = $profile_vars['avatar'];
			$changes['avatar'] = $profile_vars['avatar'];
		}

		$new_name = !empty($_POST['char_name']) ? $smcFunc['htmlspecialchars'](trim($_POST['char_name']), ENT_QUOTES) : '';
		if ($new_name == '')
			$context['form_errors'][] = $txt['char_error_character_must_have_name'];
		elseif ($new_name != $context['character']['character_name'])
		{
			// Check if the name already exists.
			$result = $smcFunc['db_query']('', '
				SELECT COUNT(*)
				FROM {db_prefix}characters
				WHERE character_name LIKE {string:new_name}
					AND id_character != {int:char}',
				array(
					'new_name' => $new_name,
					'char' => $context['character']['id_character'],
				)
			);
			list ($matching_names) = $smcFunc['db_fetch_row']($result);
			$smcFunc['db_free_result']($result);

			if ($matching_names)
				$context['form_errors'][] = $txt['char_error_duplicate_character_name'];
			else
				$changes['character_name'] = $new_name;
		}

		if ($context['character']['groups_editable'])
		{
			// Editing groups is a little bit complicated.
			$new_id_group = isset($_POST['id_group'], $context['member_groups'][$_POST['id_group']]) && $context['member_groups'][$_POST['id_group']]['can_be_primary'] ? (int) $_POST['id_group'] : $context['character']['main_char_group'];
			$new_char_groups = [];
			if (isset($_POST['additional_groups']) && is_array($_POST['additional_groups']))
			{
				foreach ($_POST['additional_groups'] as $id_group)
				{
					if (!isset($context['member_groups'][$id_group]))
						continue;
					if (!$context['member_groups'][$id_group]['can_be_additional'])
						continue;
					if ($id_group == $new_id_group)
						continue;
					$new_char_groups[] = (int) $id_group;
				}
			}
			$new_char_groups = implode(',', $new_char_groups);

			if ($new_id_group != $context['character']['main_char_group'])
				$changes['main_char_group'] = $new_id_group;
			if ($new_char_groups != $context['character']['char_groups'])
			$changes['char_groups'] = $new_char_groups;
		}

		$new_age = !empty($_POST['age']) ? $smcFunc['htmlspecialchars'](trim($_POST['age']), ENT_QUOTES) : '';
		if ($new_age != $context['character']['age'])
			$changes['age'] = $new_age;

		$new_sig = !empty($_POST['char_signature']) ? $smcFunc['htmlspecialchars']($_POST['char_signature'], ENT_QUOTES) : '';
		$valid_sig = profileValidateSignature($new_sig);
		if ($valid_sig === true)
			$changes['signature'] = $new_sig; // sanitised by profileValidateSignature
		else
			$context['form_errors'][] = $valid_sig;

		if (!empty($changes) && empty($context['form_errors']))
		{
			if ($context['character']['is_main'])
			{
				if (isset($changes['character_name']))
					updateMemberData($context['id_member'], array('real_name' => $changes['character_name']));
			}

			// Notify any hooks that there are groups changes.
			if (isset($changes['main_char_group']) || isset($changes['char_groups']))
			{
				$primary_group = isset($changes['main_char_group']) ? $changes['main_char_group'] : $context['character']['main_char_group'];
				$additional_groups = isset($changes['char_groups']) ? $changes['char_groups'] : $context['character']['char_groups'];

				call_integration_hook('integrate_profile_profileSaveCharGroups', array($context['id_member'], $context['character']['id_character'], $primary_group, $additional_groups));
			}

			if (!empty($modSettings['userlog_enabled'])) {
				$rows = [];
				foreach ($changes as $key => $new_value)
				{
					$change_array = array(
						'previous' => $context['character'][$key],
						'new' => $changes[$key],
						'applicator' => $context['user']['id'],
						'member_affected' => $context['id_member'],
						'id_character' => $context['character']['id_character'],
						'character_name' => !empty($changes['character_name']) ? $changes['character_name'] : $context['character']['character_name'],
					);
					if ($key == 'main_char_group')
					{
						$change_array['previous'] = $context['member_groups'][$context['character'][$key]]['name'];
						$change_array['new'] = $context['member_groups'][$changes[$key]]['name'];
					}
					if ($key == 'char_groups')
					{
						$previous = [];
						$new = [];
						foreach (explode(',', $context['character']['char_groups']) as $id_group)
							if (isset($context['member_groups'][$id_group]))
								$previous[] = $context['member_groups'][$id_group]['name'];

						foreach (explode(',', $changes['char_groups']) as $id_group)
							if (isset($context['member_groups'][$id_group]))
								$new[] = $context['member_groups'][$id_group]['name'];

						$change_array['previous'] = implode(', ', $previous);
						$change_array['new'] = implode(', ', $new);
					}
					$rows[] = array(
						'id_log' => 2, // 2 = profile edits log
						'log_time' => time(),
						'id_member' => $context['id_member'],
						'ip' => $user_info['ip'],
						'action' => $context['character']['is_main'] && $key == 'character_name' ? 'real_name' : 'char_' . $key,
						'id_board' => 0,
						'id_topic' => 0,
						'id_msg' => 0,
						'extra' => json_encode($change_array),
					);
				}
				if (!empty($rows)) {
					$smcFunc['db_insert']('insert',
						'{db_prefix}log_actions',
						array('id_log' => 'int', 'log_time' => 'int', 'id_member' => 'int',
							'ip' => 'inet', 'action' => 'string', 'id_board' => 'int',
							'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string'),
						$rows,
						[]
					);
				}
			}
			updateCharacterData($context['character']['id_character'], $changes);
			session_flash('success', sprintf($txt[$context['user']['is_owner'] ? 'character_updated_you' : 'character_updated_else'], $context['character']['character_name']));
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character'] . ';sa=edit');
		}

		// Put the new values back in for the form
		$context['character'] = array_merge($context['character'], $changes);
		if (isset($changes['main_char_group']) || isset($changes['char_groups']))
		{
			foreach (array_keys($context['member_groups']) as $id_group)
			{
				$context['member_groups']['is_primary'] = $id_group == $new_id_group;
				$context['member_groups']['is_additional'] = in_array($id_group, $new_char_groups);
			}
		}
	}

	$form_value = !empty($context['character']['signature']) ? $context['character']['signature'] : '';
	// Get it ready for the editor.
	$form_value = un_preparsecode($form_value);
	censorText($form_value);
	$form_value = str_replace(array('"', '<', '>', '&nbsp;'), array('&quot;', '&lt;', '&gt;', ' '), $form_value);
	$context['character']['signature_parsed'] = Parser::parse_bbc($context['character']['signature'], true, 'sig_char_' . $context['character']['id_character']);

	require_once($sourcedir . '/Subs-Editor.php');
	$editorOptions = array(
		'id' => 'char_signature',
		'value' => $form_value,
		'disable_smiley_box' => false,
		'labels' => [],
		'height' => '200px',
		'width' => '80%',
		'preview_type' => 0,
		'required' => true,
	);
	create_control_richedit($editorOptions);

	createToken('edit-char' . $context['character']['id_character'], 'post');
}

/**
 * Deleting a character.
 */
function char_delete()
{
	global $context, $smcFunc, $txt, $sourcedir, $user_info, $modSettings;

	// If they don't have permission to be here, goodbye.
	if (!$context['character']['editable']) {
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
	}

	// Check the session; this is actually a less hardcore action than
	// editing so we don't really need the token thing - the character
	// cannot have done anything at this point in order to be removed.
	checkSession('get');

	// Can't delete main accounts
	if ($context['character']['is_main'])
	{
		fatal_lang_error('this_character_cannot_delete_main', false);
	}

	// Let's see how many posts they have (for really realz, not what their post count says)
	$result = $smcFunc['db_query']('', '
		SELECT COUNT(id_msg)
		FROM {db_prefix}messages
		WHERE id_character = {int:char}',
		array(
			'char' => $context['character']['id_character'],
		)
	);
	list ($count) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	if ($count > 0)
	{
		fatal_lang_error('this_character_cannot_delete_posts', false);
	}

	// Is the character currently in action?
	$result = $smcFunc['db_query']('', '
		SELECT current_character
		FROM {db_prefix}members
		WHERE id_member = {int:member}',
		array(
			'member' => $context['id_member'],
		)
	);
	list ($current_character) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);
	if ($current_character == $context['character']['id_character'])
	{
		fatal_lang_error($context['user']['is_owner'] ? 'this_character_cannot_delete_active_self' : 'this_character_cannot_delete_active', false);
	}

	// Delete alerts attached to this character.
	// But first, find all the members where this is relevant, and where they have unread alerts (so we can fix the alert count).
	$result = $smcFunc['db_query']('', '
		SELECT mem.id_member FROM {db_prefix}members AS mem
		INNER JOIN {db_prefix}user_alerts AS a ON (a.id_member = mem.id_member)
		WHERE a.is_read = {int:unread}
		AND (a.chars_src = {int:chars_src} OR a.chars_dest = {int:chars_dest})
		GROUP BY mem.id_member',
		[
			'unread' => 0,
			'chars_src' => $context['character']['id_character'],
			'chars_dest' => $context['character']['id_character'],
		]
	);
	$members = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$members[] = $row['id_member'];
	}
	$smcFunc['db_free_result']($result);
	// Having found all of the people whose alert counts need to be fixed, let's now purge all these alerts.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}user_alerts
		WHERE (chars_src = {int:chars_src} OR chars_dest = {int:chars_dest})',
		[
			'chars_src' => $context['character']['id_character'],
			'chars_dest' => $context['character']['id_character'],
		]
	);
	// And finally fix the counts of those members.
	foreach ($members as $member)
	{
		$alert_count = Alert::count_for_member($member, true);
		updateMemberData($member, ['alerts' => $alert_count]);
	}

	// So we can delete them.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}characters
		WHERE id_character = {int:char}',
		array(
			'char' => $context['character']['id_character'],
		)
	);

	redirectexit('action=profile;u=' . $context['id_member']);
}

/**
 * Choosing a theme for a given character.
 */
function char_theme()
{
	global $context, $smcFunc, $modSettings;

	// If they don't have permission to be here, goodbye.
	if (!$context['character']['editable']) {
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
	}

	$known_themes = explode(',', $modSettings['knownThemes']);
	$context['themes'] = [];
	foreach ($known_themes as $id_theme) {
		$context['themes'][$id_theme] = array(
			'name' => '',
			'theme_dir' => '',
			'images_url' => '',
			'thumbnail' => ''
		);
	}

	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE id_member = 0
			AND variable IN ({array_string:vars})',
		array(
			'vars' => array('name', 'images_url', 'theme_dir'),
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['themes'][$row['id_theme']][$row['variable']] = $row['value'];
	$smcFunc['db_free_result']($request);

	foreach ($context['themes'] as $id_theme => $theme)
	{
		if (empty($theme['name']) || empty($theme['images_url']) || !file_exists($theme['theme_dir']))
			unset ($context['themes'][$id_theme]);

		foreach (array('.png', '.gif', '.jpg') as $ext)
			if (file_exists($theme['theme_dir'] . '/images/thumbnail' . $ext))
			{
				$context['themes'][$id_theme]['thumbnail'] = $theme['images_url'] . '/thumbnail' . $ext;
				break;
			}

		if (empty($context['themes'][$id_theme]['thumbnail']))
			unset ($context['themes'][$id_theme]);
	}

	if (!empty($_POST['theme']) && is_array($_POST['theme']))
	{
		checkSession();
		list($id_theme) = array_keys($_POST['theme']);
		if (isset($context['themes'][$id_theme]))
		{
			updateCharacterData($context['character']['id_character'], array('id_theme' => $id_theme));
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
		}
	}

	$context['sub_template'] = 'profile_character_theme';
}

/**
 * Showing the posts/topics made by a given character.
 */
function char_posts()
{
	global $txt, $user_info, $scripturl, $modSettings;
	global $context, $user_profile, $sourcedir, $smcFunc, $board;

	// Some initial context.
	$context['start'] = (int) $_REQUEST['start'];
	$context['sub_template'] = 'profile_character_posts';

	// Create the tabs for the template.
	$context[$context['profile_menu_name']]['tab_data'] = array(
		'title' => $txt['showPosts'],
		'description' => $txt['showPosts_help_char'],
		'tabs' => array(
			'posts' => array(
			),
			'topics' => array(
			),
		),
	);

	// Shortcut used to determine which $txt['show*'] string to use for the title, based on the SA
	$title = array(
		'posts' => 'Posts',
		'topics' => 'Topics'
	);

	// Set the page title
	if (isset($_GET['sa']) && array_key_exists($_GET['sa'], $title))
	{
		$context['linktree'][] = array(
			'name' => $txt['show' . $title[$_GET['sa']] . '_char'],
			'url' => $scripturl . '?action=profile;area=characters;char=' . $context['character']['id_character'] . ';sa=' . $_GET['sa'] . ';u=' . $context['id_member'],
		);
		$context['page_title'] = $txt['show' . $title[$_GET['sa']]];
	}
	else
	{
		$context['linktree'][] = array(
			'name' => $txt['showPosts_char'],
			'url' => $scripturl . '?action=profile;area=characters;char=' . $context['character']['id_character'] . ';sa=posts;u=' . $context['id_member'],
		);
		$context['page_title'] = $txt['showPosts'];
	}

	$context['page_title'] .= ' - ' . $context['character']['character_name'];

	// Is the load average too high to allow searching just now?
	if (!empty($context['load_average']) && !empty($modSettings['loadavg_show_posts']) && $context['load_average'] >= $modSettings['loadavg_show_posts'])
		fatal_lang_error('loadavg_show_posts_disabled', false);

	// Are we just viewing topics?
	$context['is_topics'] = isset($_GET['sa']) && $_GET['sa'] == 'topics' ? true : false;

	// Default to 10.
	if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount']))
		$_REQUEST['viewscount'] = '10';

	if ($context['is_topics'])
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}topics AS t' . ($user_info['query_see_board'] == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})') . '
				INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
			WHERE m.id_character = {int:current_member}' . (!empty($board) ? '
				AND t.id_board = {int:board}' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
				AND t.approved = {int:is_approved}'),
			array(
				'current_member' => $context['character']['id_character'],
				'is_approved' => 1,
				'board' => $board,
			)
		);
	else
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}messages AS m' . ($user_info['query_see_board'] == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})') . '
			WHERE m.id_character = {int:current_member}' . (!empty($board) ? '
				AND m.id_board = {int:board}' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
				AND m.approved = {int:is_approved}'),
			array(
				'current_member' => $context['character']['id_character'],
				'is_approved' => 1,
				'board' => $board,
			)
		);
	list ($msgCount) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT MIN(id_msg), MAX(id_msg)
		FROM {db_prefix}messages AS m
		WHERE m.id_character = {int:current_member}' . (!empty($board) ? '
			AND m.id_board = {int:board}' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
			AND m.approved = {int:is_approved}'),
		array(
			'current_member' => $context['character']['id_character'],
			'is_approved' => 1,
			'board' => $board,
		)
	);
	list ($min_msg_member, $max_msg_member) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$reverse = false;
	$range_limit = '';

	if ($context['is_topics'])
		$maxPerPage = empty($modSettings['disableCustomPerPage']) && !empty($options['topics_per_page']) ? $options['topics_per_page'] : $modSettings['defaultMaxTopics'];
	else
		$maxPerPage = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];

	$maxIndex = $maxPerPage;

	// Make sure the starting place makes sense and construct our friend the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=profile;area=characters;char=' . $context['character']['id_character'] . ';u=' . $context['id_member'] . ($context['is_topics'] ? ';sa=topics' : ';sa=posts') . (!empty($board) ? ';board=' . $board : ''), $context['start'], $msgCount, $maxIndex);
	$context['current_page'] = $context['start'] / $maxIndex;

	// Reverse the query if we're past 50% of the pages for better performance.
	$start = $context['start'];
	$reverse = $_REQUEST['start'] > $msgCount / 2;
	if ($reverse)
	{
		$maxIndex = $msgCount < $context['start'] + $maxPerPage + 1 && $msgCount > $context['start'] ? $msgCount - $context['start'] : $maxPerPage;
		$start = $msgCount < $context['start'] + $maxPerPage + 1 || $msgCount < $context['start'] + $maxPerPage ? 0 : $msgCount - $context['start'] - $maxPerPage;
	}

	// Guess the range of messages to be shown.
	if ($msgCount > 1000)
	{
		$margin = floor(($max_msg_member - $min_msg_member) * (($start + $maxPerPage) / $msgCount) + .1 * ($max_msg_member - $min_msg_member));
		// Make a bigger margin for topics only.
		if ($context['is_topics'])
		{
			$margin *= 5;
			$range_limit = $reverse ? 't.id_first_msg < ' . ($min_msg_member + $margin) : 't.id_first_msg > ' . ($max_msg_member - $margin);
		}
		else
			$range_limit = $reverse ? 'm.id_msg < ' . ($min_msg_member + $margin) : 'm.id_msg > ' . ($max_msg_member - $margin);
	}

	// Find this user's posts.  The left join on categories somehow makes this faster, weird as it looks.
	$looped = false;
	while (true)
	{
		if ($context['is_topics'])
		{
			$request = $smcFunc['db_query']('', '
				SELECT
					b.id_board, b.name AS bname, c.id_cat, c.name AS cname, t.id_member_started, t.id_first_msg, t.id_last_msg,
					t.approved, m.body, m.smileys_enabled, m.subject, m.poster_time, m.id_topic, m.id_msg
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				WHERE m.id_character = {int:current_member}' . (!empty($board) ? '
					AND t.id_board = {int:board}' : '') . (empty($range_limit) ? '' : '
					AND ' . $range_limit) . '
					AND {query_see_board}' . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
					AND t.approved = {int:is_approved} AND m.approved = {int:is_approved}') . '
				ORDER BY t.id_first_msg ' . ($reverse ? 'ASC' : 'DESC') . '
				LIMIT ' . $start . ', ' . $maxIndex,
				array(
					'current_member' => $context['character']['id_character'],
					'is_approved' => 1,
					'board' => $board,
				)
			);
		}
		else
		{
			$request = $smcFunc['db_query']('', '
				SELECT
					b.id_board, b.name AS bname, c.id_cat, c.name AS cname, m.id_topic, m.id_msg,
					t.id_member_started, t.id_first_msg, t.id_last_msg, m.body, m.smileys_enabled,
					m.subject, m.poster_time, m.approved
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				WHERE m.id_character = {int:current_member}' . (!empty($board) ? '
					AND b.id_board = {int:board}' : '') . (empty($range_limit) ? '' : '
					AND ' . $range_limit) . '
					AND {query_see_board}' . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
					AND t.approved = {int:is_approved} AND m.approved = {int:is_approved}') . '
				ORDER BY m.id_msg ' . ($reverse ? 'ASC' : 'DESC') . '
				LIMIT ' . $start . ', ' . $maxIndex,
				array(
					'current_member' => $context['character']['id_character'],
					'is_approved' => 1,
					'board' => $board,
				)
			);
		}

		// Make sure we quit this loop.
		if ($smcFunc['db_num_rows']($request) === $maxIndex || $looped)
			break;
		$looped = true;
		$range_limit = '';
	}

	// Start counting at the number of the first message displayed.
	$counter = $reverse ? $context['start'] + $maxIndex + 1 : $context['start'];
	$context['posts'] = [];
	$board_ids = array('own' => [], 'any' => []);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Censor....
		censorText($row['body']);
		censorText($row['subject']);

		// Do the code.
		$row['body'] = Parser::parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// And the array...
		$context['posts'][$counter += $reverse ? -1 : 1] = array(
			'body' => $row['body'],
			'counter' => $counter,
			'category' => array(
				'name' => $row['cname'],
				'id' => $row['id_cat']
			),
			'board' => array(
				'name' => $row['bname'],
				'id' => $row['id_board']
			),
			'topic' => $row['id_topic'],
			'subject' => $row['subject'],
			'start' => 'msg' . $row['id_msg'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'id' => $row['id_msg'],
			'can_reply' => false,
			'can_mark_notify' => !$context['user']['is_guest'],
			'can_delete' => false,
			'delete_possible' => ($row['id_first_msg'] != $row['id_msg'] || $row['id_last_msg'] == $row['id_msg']) && (empty($modSettings['edit_disable_time']) || $row['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()),
			'approved' => $row['approved'],
			'css_class' => $row['approved'] ? 'windowbg' : 'approvebg',
		);

		if ($user_info['id'] == $row['id_member_started'])
			$board_ids['own'][$row['id_board']][] = $counter;
		$board_ids['any'][$row['id_board']][] = $counter;
	}
	$smcFunc['db_free_result']($request);

	// All posts were retrieved in reverse order, get them right again.
	if ($reverse)
		$context['posts'] = array_reverse($context['posts'], true);

	// These are all the permissions that are different from board to board..
	if ($context['is_topics'])
		$permissions = array(
			'own' => array(
				'post_reply_own' => 'can_reply',
			),
			'any' => array(
				'post_reply_any' => 'can_reply',
			)
		);
	else
		$permissions = array(
			'own' => array(
				'post_reply_own' => 'can_reply',
				'delete_own' => 'can_delete',
			),
			'any' => array(
				'post_reply_any' => 'can_reply',
				'delete_any' => 'can_delete',
			)
		);

	// For every permission in the own/any lists...
	foreach ($permissions as $type => $list)
	{
		foreach ($list as $permission => $allowed)
		{
			// Get the boards they can do this on...
			$boards = boardsAllowedTo($permission);

			// Hmm, they can do it on all boards, can they?
			if (!empty($boards) && $boards[0] == 0)
				$boards = array_keys($board_ids[$type]);

			// Now go through each board they can do the permission on.
			foreach ($boards as $board_id)
			{
				// There aren't any posts displayed from this board.
				if (!isset($board_ids[$type][$board_id]))
					continue;

				// Set the permission to true ;).
				foreach ($board_ids[$type][$board_id] as $counter)
					$context['posts'][$counter][$allowed] = true;
			}
		}
	}

	// Clean up after posts that cannot be deleted and quoted.
	$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));
	foreach ($context['posts'] as $counter => $dummy)
	{
		$context['posts'][$counter]['can_delete'] &= $context['posts'][$counter]['delete_possible'];
		$context['posts'][$counter]['can_quote'] = $context['posts'][$counter]['can_reply'] && $quote_enabled;
	}

	// Allow last minute changes.
	call_integration_hook('integrate_profile_showPosts');
}

/**
 * Load the profile groups attached to the current character being viewed.
 *
 * @return bool True on success (values loaded into $context)
 */
function profileLoadCharGroups()
{
	global $cur_profile, $txt, $context, $smcFunc, $user_settings;

	$context['member_groups'] = array(
		0 => array(
			'id' => 0,
			'name' => $txt['no_primary_character_group'],
			'is_primary' => $context['character']['main_char_group'] == 0,
			'can_be_additional' => false,
			'can_be_primary' => true,
		)
	);
	$curGroups = explode(',', $context['character']['char_groups']);

	// Load membergroups, but only those groups the user can assign.
	$request = $smcFunc['db_query']('', '
		SELECT group_name, id_group, hidden
		FROM {db_prefix}membergroups
		WHERE id_group != {int:moderator_group}
			AND is_character = 1' . (allowedTo('admin_forum') ? '' : '
			AND group_type != {int:is_protected}') . '
		ORDER BY group_name',
		array(
			'moderator_group' => 3,
			'is_protected' => 1,
			'newbie_group' => 4,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['member_groups'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'is_primary' => $context['character']['main_char_group'] == $row['id_group'],
			'is_additional' => in_array($row['id_group'], $curGroups),
			'can_be_additional' => true,
			'can_be_primary' => $row['hidden'] != 2,
		);
	}
	$smcFunc['db_free_result']($request);

	$context['member']['group_id'] = $user_settings['id_group'];

	return true;
}

/**
 * Retiring a character from active use (or making them unretired)
 */
function char_retire()
{
	global $context, $smcFunc, $txt, $user_info;

	checkSession('get');

	// If the character isn't eligible for retirement, goodbye.
	if (!$context['character']['retire_eligible']) {
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
	}

	// This is really quite straightforward.
	$new_state = $context['character']['retired'] ? 0 : 1; // If currently retired, make them not, etc.
	updateCharacterData($context['character']['id_character'], ['retired' => $new_state]);

	$change_array = [
		'previous' => $context['character']['retired'] ? $txt['yes'] : $txt['no'],
		'new' => $new_state ? $txt['yes'] : $txt['no'],
		'applicator' => $context['user']['id'],
		'member_affected' => $context['id_member'],
		'id_character' => $context['character']['id_character'],
		'character_name' => $context['character']['character_name'],
	];
	$smcFunc['db_insert']('insert',
		'{db_prefix}log_actions',
		['id_log' => 'int', 'log_time' => 'int', 'id_member' => 'int',
			'ip' => 'inet', 'action' => 'string', 'id_board' => 'int',
			'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string'],
		[2, time(), $context['id_member'],
			$user_info['ip'], 'char_retired', 0,
			0, 0, json_encode($change_array)],
		[]
	);

	// And back to the character.
	redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
}

/**
 * Viewing the stats for a given character.
 */
function char_stats()
{
	global $txt, $scripturl, $context, $user_profile, $user_info, $modSettings, $smcFunc;

	$context['page_title'] = $txt['statPanel_showStats'] . ' ' . $context['character']['character_name'];
	$context['sub_template'] = 'profile_character_stats';

	StoryBB\Template::add_helper([
		'inverted_percent' => function($pc)
		{
			return 100 - $pc;
		},
		'pie_percent' => function($pc)
		{
			return round($pc / 5) * 20;
		},
	]);

	// Is the load average too high to allow searching just now?
	if (!empty($context['load_average']) && !empty($modSettings['loadavg_userstats']) && $context['load_average'] >= $modSettings['loadavg_userstats'])
		fatal_lang_error('loadavg_userstats_disabled', false);

	$context['num_posts'] = comma_format($context['character']['posts']);
	// Menu tab
	$context[$context['profile_menu_name']]['tab_data'] = array(
		'title' => $txt['statPanel_generalStats'] . ' - ' . $context['character']['character_name']
	);

	$context['linktree'][] = array(
		'name' => $txt['char_stats'],
		'url' => $scripturl . '?action=profile;area=characters;char=' . $context['character']['id_character'] . ';sa=stats;u=' . $context['id_member'],
	);

	// Number of topics started.
	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}topics AS t
		INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
		WHERE m.id_character = {int:id_character}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND t.id_board != {int:recycle_board}' : ''),
		array(
			'id_character' => $context['character']['id_character'],
			'recycle_board' => $modSettings['recycle_board'],
		)
	);
	list ($context['num_topics']) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);
	$context['num_topics'] = comma_format($context['num_topics']);

	// Grab the board this character posted in most often.
	$result = $smcFunc['db_query']('', '
		SELECT
			b.id_board, MAX(b.name) AS name, MAX(b.num_posts) AS num_posts, COUNT(*) AS message_count
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE m.id_character = {int:id_character}
			AND b.count_posts = {int:count_enabled}
			AND {query_see_board}
		GROUP BY b.id_board
		ORDER BY message_count DESC
		LIMIT 10',
		array(
			'id_character' => $context['character']['id_character'],
			'count_enabled' => 0,
		)
	);
	$context['popular_boards'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$context['popular_boards'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'posts' => $row['message_count'],
			'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'posts_percent' => $context['character']['posts'] == 0 ? 0 : ($row['message_count'] * 100) / $context['character']['posts'],
			'total_posts' => $row['num_posts'],
			'total_posts_char' => $context['character']['posts'],
		);
	}
	$smcFunc['db_free_result']($result);

	// Now get the 10 boards this user has most often participated in.
	$result = $smcFunc['db_query']('profile_board_stats', '
		SELECT
			b.id_board, MAX(b.name) AS name, b.num_posts, COUNT(*) AS message_count,
			CASE WHEN COUNT(*) > MAX(b.num_posts) THEN 1 ELSE COUNT(*) / MAX(b.num_posts) END * 100 AS percentage
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE m.id_character = {int:id_character}
			AND {query_see_board}
		GROUP BY b.id_board, b.num_posts
		ORDER BY percentage DESC
		LIMIT 10',
		array(
			'id_character' => $context['character']['id_character'],
		)
	);
	$context['board_activity'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$context['board_activity'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'posts' => $row['message_count'],
			'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'percent' => comma_format((float) $row['percentage'], 2),
			'posts_percent' => (float) $row['percentage'],
			'total_posts' => $row['num_posts'],
		);
	}
	$smcFunc['db_free_result']($result);

	// Posting activity by time.
	$result = $smcFunc['db_query']('user_activity_by_time', '
		SELECT
			HOUR(FROM_UNIXTIME(poster_time + {int:time_offset})) AS hour,
			COUNT(*) AS post_count
		FROM {db_prefix}messages
		WHERE id_character = {int:id_character}' . ($modSettings['totalMessages'] > 100000 ? '
			AND id_topic > {int:top_ten_thousand_topics}' : '') . '
		GROUP BY hour',
		array(
			'id_character' => $context['character']['id_character'],
			'top_ten_thousand_topics' => $modSettings['totalTopics'] - 10000,
			'time_offset' => (($user_info['time_offset'] + $modSettings['time_offset']) * 3600),
		)
	);
	$maxPosts = $realPosts = 0;
	$context['posts_by_time'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		// Cast as an integer to remove the leading 0.
		$row['hour'] = (int) $row['hour'];

		$maxPosts = max($row['post_count'], $maxPosts);
		$realPosts += $row['post_count'];

		$context['posts_by_time'][$row['hour']] = array(
			'hour' => $row['hour'],
			'hour_format' => stripos($user_info['time_format'], '%p') === false ? $row['hour'] : date('g a', mktime($row['hour'])),
			'posts' => $row['post_count'],
			'posts_percent' => 0,
			'is_last' => $row['hour'] == 23,
		);
	}
	$smcFunc['db_free_result']($result);

	if ($maxPosts > 0)
		for ($hour = 0; $hour < 24; $hour++)
		{
			if (!isset($context['posts_by_time'][$hour]))
				$context['posts_by_time'][$hour] = array(
					'hour' => $hour,
					'hour_format' => stripos($user_info['time_format'], '%p') === false ? $hour : date('g a', mktime($hour)),
					'posts' => 0,
					'posts_percent' => 0,
					'relative_percent' => 0,
					'is_last' => $hour == 23,
				);
			else
			{
				$context['posts_by_time'][$hour]['posts_percent'] = round(($context['posts_by_time'][$hour]['posts'] * 100) / $realPosts);
				$context['posts_by_time'][$hour]['relative_percent'] = round(($context['posts_by_time'][$hour]['posts'] * 100) / $maxPosts);
			}
		}

	// Put it in the right order.
	ksort($context['posts_by_time']);
}

/**
 * Viewing a character sheet for a character.
 */
function char_sheet()
{
	global $context, $txt, $smcFunc, $scripturl, $sourcedir;

	// First, get rid of people shouldn't have a sheet at all - the OOC characters
	if ($context['character']['is_main'])
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// Then if we're looking at a character who doesn't have an approved one
	// and the user couldn't see it... you are the weakest link, goodbye.
	if (empty($context['character']['char_sheet']) && empty($context['user']['is_owner']) && !allowedTo('admin_forum'))
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// Fetch the current character sheet - for the owner + admin, show most recent
	// whatever, for everyone else show them the most recent approved
	if ($context['user']['is_owner'] || allowedTo('admin_forum'))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_version, sheet_text, created_time, id_approver, approved_time, approval_state
			FROM {db_prefix}character_sheet_versions
			WHERE id_character = {int:character}
			ORDER BY id_version DESC
			LIMIT 1',
			array(
				'character' => $context['character']['id_character'],
			)
		);
		if ($smcFunc['db_num_rows']($request) > 0)
		{
			$context['character']['sheet_details'] = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);
		}

		if (isset($_POST['message']))
		{
			// We might be saving a comment on this.
			checkSession();
			require_once($sourcedir . '/Subs-Post.php');
			require_once($sourcedir . '/Subs-Editor.php');

			$message = $smcFunc['htmlspecialchars']($_POST['message'], ENT_QUOTES);
			preparsecode($message);

			if (!empty($message))
			{
				// WE GAHT ONE!!!!!!!!!
				$smcFunc['db_insert']('insert',
					'{db_prefix}character_sheet_comments',
					array('id_character' => 'int', 'id_author' => 'int', 'time_posted' => 'int', 'sheet_comment' => 'string'),
					array($context['character']['id_character'], $context['user']['id'], time(), $message),
					array('id_comment')
				);
				redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character'] . ';sa=sheet');
			}
		}
	}
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_version, sheet_text, created_time, id_approver, approved_time, approval_state
			FROM {db_prefix}character_sheet_versions
			WHERE id_version = {int:version}',
			array(
				'version' => $context['character']['char_sheet'],
			)
		);
		$context['character']['sheet_details'] = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);
	}

	if (!empty($context['character']['sheet_details']['sheet_text'])) {
	$context['character']['sheet_details']['sheet_text'] = Parser::parse_bbc($context['character']['sheet_details']['sheet_text'], false);
	} else {
		$context['character']['sheet_details']['sheet_text'] = '';
	}

	$context['linktree'][] = array(
		'name' => $txt['char_sheet'],
		'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=characters;sa=sheet;char=' . $context['character']['id_character'],
	);

	$context['page_title'] = $txt['char_sheet'] . ' - ' . $context['character']['character_name'];
	$context['sub_template'] = 'profile_character_sheet';

	$context['sheet_buttons'] = [];
	$context['show_sheet_comments'] = false;
	if ($context['user']['is_owner'] || allowedTo('admin_forum'))
	{
		// Always have an edit button
		$context['sheet_buttons']['edit'] = array(
			'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=characters;sa=sheet_edit;char=' . $context['character']['id_character'],
			'text' => 'char_sheet_edit',
		);
		// Only have a history button if there's actually some history
		if (!empty($context['character']['sheet_details']['sheet_text']))
		{
			$context['sheet_buttons']['history'] = array(
				'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=characters;sa=sheet_history;char=' . $context['character']['id_character'],
				'text' => 'char_sheet_history',
			);
			// Only have a send-for-approval button if it hasn't been approved
			// and it hasn't yet been sent for approval either
			if (empty($context['character']['sheet_details']['id_approver']) && empty($context['character']['sheet_details']['approval_state']))
			{
				$context['sheet_buttons']['send_for_approval'] = array(
					'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=characters;sa=sheet_approval;char=' . $context['character']['id_character'] . ';' . $context['session_var'] . '=' . $context['session_id'],
					'text' => 'char_sheet_send_for_approval',
				);
			}
		}
		// Compare to last approved only if we had a previous approval and the
		// current one isn't approved right now
		if (empty($context['character']['sheet_details']['id_approver']) && !empty($context['character']['char_sheet']))
		{
			$context['sheet_buttons']['compare'] = array(
				'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=characters;sa=sheet_compare;char=' . $context['character']['id_character'],
				'text' => 'char_sheet_compare',
			);
		}
		// And the infamous approve button
		if (!empty($context['character']['sheet_details']['sheet_text']) && empty($context['character']['sheet_details']['id_approver']) && allowedTo('admin_forum'))
		{
			$context['sheet_buttons']['approve'] = array(
				'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=characters;sa=sheet_approve;version=' . $context['character']['sheet_details']['id_version'] . ';char=' . $context['character']['id_character'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				'text' => 'char_sheet_approve',
				'custom' => 'onclick="return confirm(' . JavaScriptEscape($txt['char_sheet_approve_are_you_sure']) . ')"',
			);
		}
		// And if it's pending approval and we might want to kick it back?
		if (!empty($context['character']['sheet_details']['approval_state']) && empty($context['character']['sheet_details']['id_approver']) && allowedTo('admin_forum'))
		{
			$context['sheet_buttons']['reject'] = array(
				'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=characters;sa=sheet_reject;version=' . $context['character']['sheet_details']['id_version'] . ';char=' . $context['character']['id_character'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				'text' => 'char_sheet_reject',
				'custom' => 'onclick="return confirm(' . JavaScriptEscape($txt['char_sheet_reject_are_you_sure']) . ')"',
			);
		}

		// And since this is the owner or admin, we should look at comments.
		if (!empty($context['character']['sheet_details']['sheet_text'])) {
			$context['show_sheet_comments'] = true;
			$context['sheet_comments'] = [];
			// First, find the time of the last approved case.
			$last_approved = 0;
			$request = $smcFunc['db_query']('', '
				SELECT MAX(approved_time) AS last_approved
				FROM {db_prefix}character_sheet_versions
				WHERE id_approver != 0
					AND id_character = {int:character}',
					array(
						'character' => $context['character']['id_character'],
					)
				);
			if ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$last_approved = (int) $row['last_approved'];
			}
			$smcFunc['db_free_result']($request);

			// Now get any comments for this character since the last approval.
			$request = $smcFunc['db_query']('', '
				SELECT csc.id_comment, csc.id_author, mem.real_name, csc.time_posted, csc.sheet_comment
				FROM {db_prefix}character_sheet_comments AS csc
				LEFT JOIN {db_prefix}members AS mem ON (csc.id_author = mem.id_member)
				WHERE id_character = {int:character}
					AND time_posted > {int:approval}
				ORDER BY id_comment DESC',
				array(
					'character' => $context['character']['id_character'],
					'approval' => $last_approved,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$row['sheet_comment_parsed'] = Parser::parse_bbc($row['sheet_comment'], true, 'sheet-comment-' . $row['id_comment']);
				$context['sheet_comments'][$row['id_comment']] = $row;
			}
			$smcFunc['db_free_result']($request);
		}

		// Make an editor box
		require_once($sourcedir . '/Subs-Post.php');
		require_once($sourcedir . '/Subs-Editor.php');

		// Now create the editor.
		$editorOptions = array(
			'id' => 'message',
			'value' => '',
			'labels' => array(
				'post_button' => $txt['save'],
			),
			// add height and width for the editor
			'height' => '175px',
			'width' => '100%',
			'preview_type' => 0,
			'required' => true,
		);
		create_control_richedit($editorOptions);
	}
}

/**
 * Viewing the history of edits to a character sheet.
 */
function char_sheet_history()
{
	global $context, $txt, $smcFunc, $scripturl, $sourcedir;

	// First, get rid of people shouldn't have a sheet at all - the OOC characters
	if ($context['character']['is_main'])
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// Then we need to be either the owner or an admin to see this.
	if (empty($context['user']['is_owner']) && !allowedTo('admin_forum'))
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	$context['history_items'] = [];

	// First, get all the sheet versions.
	$request = $smcFunc['db_query']('', '
		SELECT id_version, sheet_text, created_time, id_approver,
			mem.real_name AS approver_name, approved_time
		FROM {db_prefix}character_sheet_versions AS csv
		LEFT JOIN {db_prefix}members AS mem ON (csv.id_version = mem.id_member)
		WHERE csv.id_character = {int:char}
		ORDER BY NULL',
		array(
			'char' => $context['character']['id_character'],
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
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
	$smcFunc['db_free_result']($request);

	// Then get all the comments.
	$request = $smcFunc['db_query']('', '
		SELECT id_comment, id_author, mem.real_name, time_posted, sheet_comment
		FROM {db_prefix}character_sheet_comments AS csc
		LEFT JOIN {db_prefix}members AS mem ON (csc.id_author = mem.id_member)
		WHERE csc.id_character = {int:char}
		ORDER BY NULL',
		array(
			'char' => $context['character']['id_character'],
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['type'] = 'comment';
		$row['sheet_comment_parsed'] = Parser::parse_bbc($row['sheet_comment'], true, 'sheet-comment-' . $row['id_comment']);
		$row['time_posted_format'] = timeformat($row['time_posted_format']);
		$context['history_items'][$row['time_posted'] . 'c' . $row['id_comment']] = $row;
	}
	$smcFunc['db_free_result']($request);

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

/**
 * Editing a character sheet.
 */
function char_sheet_edit()
{
	global $context, $txt, $smcFunc, $scripturl, $sourcedir;

	loadLanguage('Admin');

	// First, get rid of people shouldn't have a sheet at all - the OOC characters
	if ($context['character']['is_main'])
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// Then if we're looking at a character who doesn't have an approved one
	// and the user couldn't see it... you are the weakest link, goodbye.
	if (empty($context['character']['char_sheet']) && empty($context['user']['is_owner']) && !allowedTo('admin_forum'))
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	$request = $smcFunc['db_query']('', '
		SELECT id_version, sheet_text, created_time, id_approver, approved_time, approval_state
		FROM {db_prefix}character_sheet_versions
		WHERE id_character = {int:character}
		ORDER BY id_version DESC
		LIMIT 1',
		array(
			'character' => $context['character']['id_character'],
		)
	);
	if ($smcFunc['db_num_rows']($request) > 0)
	{
		$context['character']['sheet_details'] = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);
	}

	// Make an editor box
	require_once($sourcedir . '/Subs-Post.php');
	require_once($sourcedir . '/Subs-Editor.php');

	if (isset($_POST['message']))
	{
		// Are we saving? Let's see if session's legit first.
		checkSession();
		// Then try to get some content.
		$message = $smcFunc['htmlspecialchars']($_POST['message'], ENT_QUOTES);
		preparsecode($message);

		if (!empty($message))
		{
			// So we have a character sheet. Let's do a comparison against
			// the last character sheet saved just in case the user did something
			// a little bit weird/silly.
			if (empty($context['character']['sheet_details']['sheet_text']) || $message != $context['character']['sheet_details']['sheet_text'])
			{
				// It's different, good. So insert it, making it await approval.
				$smcFunc['db_insert']('insert',
					'{db_prefix}character_sheet_versions',
					array(
						'sheet_text' => 'string', 'id_character' => 'int', 'id_member' => 'int',
						'created_time' => 'int', 'id_approver' => 'int', 'approved_time' => 'int', 'approval_state' => 'int'
					),
					array(
						$message, $context['character']['id_character'], $context['user']['id'],
						time(), 0, 0, 0
					),
					array('id_version')
				);
				// Mark previous versions of the character sheet as not awaited approval.
				mark_char_sheet_unapproved($context['character']['id_character']);
			}
		}

		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character'] . ';sa=sheet');
	}

	// Now create the editor.
	$editorOptions = array(
		'id' => 'message',
		'value' => !empty($context['character']['sheet_details']['sheet_text']) ? un_preparsecode($context['character']['sheet_details']['sheet_text']) : '',
		'labels' => array(
			'post_button' => $txt['save'],
		),
		// add height and width for the editor
		'height' => '500px',
		'width' => '100%',
		'preview_type' => 0,
		'required' => true,
	);
	create_control_richedit($editorOptions);

	load_char_sheet_templates();

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
		$request = $smcFunc['db_query']('', '
			SELECT id_comment, id_author, mem.real_name, time_posted, sheet_comment
			FROM {db_prefix}character_sheet_comments AS csc
				LEFT JOIN {db_prefix}members AS mem ON (csc.id_author = mem.id_member)
			WHERE id_character = {int:character}
				AND time_posted > {int:last_approved_time}
			ORDER BY NULL',
			array(
				'character' => $context['character']['id_character'],
				'last_approved_time' => $context['character']['sheet_details']['created_time'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (empty($row['real_name']))
				$row['real_name'] = $txt['char_unknown'];
			$context['sheet_comments'][$row['id_comment']] = $row;
		}
		$smcFunc['db_free_result']($request);
		krsort($context['sheet_comments']);
	}

	$context['page_title'] = $txt['char_sheet'] . ' - ' . $context['character']['character_name'];
	$context['sub_template'] = 'profile_character_sheet_edit';
}

/**
 * Load the possible character sheet templates into $context.
 */
function load_char_sheet_templates()
{
	global $context, $smcFunc, $sourcedir;
	require_once($sourcedir . '/Subs-Post.php');

	$context['sheet_templates'] = [];
	// Go fetch the possible templates.
	$request = $smcFunc['db_query']('', '
		SELECT id_template, template_name, template
		FROM {db_prefix}character_sheet_templates
		ORDER BY position ASC');
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['sheet_templates'][$row['id_template']] = array(
			'name' => $row['template_name'],
			'body' => un_preparsecode($row['template']),
		);
	}
	$smcFunc['db_free_result']($request);
}

/**
 * Marking a character sheet ready for approval by admins.
 */
function char_sheet_approval()
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
	$request = $smcFunc['db_query']('', '
		SELECT MAX(id_version) AS last_approved
		FROM {db_prefix}character_sheet_versions
		WHERE id_approver != 0
			AND id_character = {int:character}',
			array(
				'character' => $context['character']['id_character'],
			)
		);
	if ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$last_approved = (int) $row['last_approved'];
	}
	$smcFunc['db_free_result']($request);

	// Now find the highest version after the last approved (or highest ever)
	// for this character.
	$request = $smcFunc['db_query']('', '
		SELECT MAX(id_version) AS highest_id
		FROM {db_prefix}character_sheet_versions
		WHERE id_version > {int:last_approved}
			AND id_character = {int:character}',
			array(
				'last_approved' => $last_approved,
				'character' => $context['character']['id_character'],
			)
		);
	$row = $smcFunc['db_fetch_assoc']($request);
	if (empty($row))
	{
		// There isn't a version to mark as pending approval.
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
	}

	// OK, time to mark it as ready for approval.
	$request = $smcFunc['db_query']('', '
		UPDATE {db_prefix}character_sheet_versions
		SET approval_state = 1
		WHERE id_version = {int:version}',
		array(
			'version' => $row['highest_id'],
		)
	);

	// Now notify peoples that this is a thing.
	require_once($sourcedir . '/Subs-Members.php');
	$admins = membersAllowedTo('admin_forum');

	$alert_rows = [];
	foreach ($admins as $id_member)
	{
		$alert_rows[] = array(
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
		);
	}

	if (!empty($alert_rows))
	{
		$smcFunc['db_insert']('',
			'{db_prefix}user_alerts',
			array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
				'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
			$alert_rows,
			[]
		);
		updateMemberData($admins, array('alerts' => '+'));
	}

	redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character'] . ';sa=sheet');
}

/**
 * Approving a character sheet.
 */
function char_sheet_approve()
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

	$request = $smcFunc['db_query']('', '
		SELECT id_character, id_approver, approval_state
		FROM {db_prefix}character_sheet_versions
		WHERE id_version = {int:version}',
		array(
			'version' => $version,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
	{
		// Doesn't exist, so bail.
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
	}

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Correct character?
	if ($row['id_character'] != $context['character']['id_character'])
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// Has it already been approved?
	if (!empty($row['id_approver']))
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// Last test: any other rows for this user
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}character_sheet_versions
		WHERE id_version > {int:version}
			AND id_character = {int:character}',
		array(
			'version' => $version,
			'character' => $context['character']['id_character'],
		)
	);
	list ($count) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if ($count > 0)
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// OK, so this version is good to go for approval. Approve the sheet...
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}character_sheet_versions
		SET id_approver = {int:approver},
			approved_time = {int:time},
			approval_state = {int:zero}
		WHERE id_version = {int:version}',
		array(
			'approver' => $context['user']['id'],
			'time' => time(),
			'zero' => 0,
			'version' => $version,
		)
	);
	// And the character...
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}characters
		SET char_sheet = {int:version}
		WHERE id_character = {int:character}',
		array(
			'version' => $version,
			'character' => $context['character']['id_character'],
		)
	);

	// And send the character sheet owner an alert.
	$smcFunc['db_insert']('',
		'{db_prefix}user_alerts',
		array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
			'chars_src' => 'int', 'chars_dest' => 'int',
			'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
		array(time(), $context['id_member'], $context['user']['id'], '',
			0, $context['character']['id_character'],
			'member', 0, 'char_sheet_approved', 0, ''),
		[]
	);
	updateMemberData($context['id_member'], array('alerts' => '+'));

	redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character'] . ';sa=sheet');
}

/**
 * Reject a version of a character sheet (sending it back to the creator for changes)
 */
function char_sheet_reject()
{
	global $context, $smcFunc;

	checkSession('get');

	// First, get rid of people shouldn't have a sheet at all - the OOC characters
	if ($context['character']['is_main'])
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// Then we're not an admin...
	if (!allowedTo('admin_forum'))
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	mark_char_sheet_unapproved($context['character']['id_character']);
	redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character'] . ';sa=sheet');
}

/**
 * Show two versiosns of a character sheet side by side.
 */
function char_sheet_compare()
{
	global $context, $txt, $smcFunc, $scripturl, $sourcedir;

	// First, get rid of people shouldn't have a sheet at all - the OOC characters
	if ($context['character']['is_main'])
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// Then if we're looking at a character who doesn't have an approved one
	// and the user couldn't see it... you are the weakest link, goodbye.
	if (empty($context['character']['char_sheet']) && empty($context['user']['is_owner']) && !allowedTo('admin_forum'))
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// So, does the user have a current-not-yet-approved one? We need to get
	// the latest to find this out.
	$request = $smcFunc['db_query']('', '
		SELECT id_version, sheet_text, created_time, id_approver, approved_time, approval_state
		FROM {db_prefix}character_sheet_versions
		WHERE id_character = {int:character}
			AND id_version > {int:current_version}
		ORDER BY id_version DESC
		LIMIT 1',
		array(
			'character' => $context['character']['id_character'],
			'current_version' => $context['character']['char_sheet'],
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
	{
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character'] . ';sa=sheet');
	}
	$context['character']['sheet_details'] = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Now we need to go get the currently approved one too.
	$request = $smcFunc['db_query']('', '
		SELECT id_version, sheet_text, created_time, id_approver, approved_time, approval_state
		FROM {db_prefix}character_sheet_versions
		WHERE id_version = {int:current_version}',
		array(
			'current_version' => $context['character']['char_sheet'],
		)
	);
	$context['character']['original_sheet'] = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// And parse the bbc.
	foreach (['original_sheet', 'sheet_details'] as $sheet) {
		$context['character'][$sheet]['sheet_text_parsed'] = Parser::parse_bbc($context['character'][$sheet]['sheet_text'], false);
	}

	$context['page_title'] = $txt['char_sheet_compare'];
	$context['sub_template'] = 'profile_character_sheet_compare';
}

/**
 * Mark a given character's sheet as unapproved.
 *
 * @param int $char Character ID whose character sheet should be marked as unapproved.
 */
function mark_char_sheet_unapproved($char)
{
	global $smcFunc;
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}character_sheet_versions
		SET approval_state = 0
		WHERE id_character = {int:char}',
		array(
			'char' => (int) $char,
		)
	);
}


/**
 * Handle UI aspects of merging multiple accounts accounts, including requesting from the user and security checks.
 * Defers actual processing to merge_char_accounts().
 *
 * @param int $memID The source account to be merged into something else.
 */
function char_merge_account($memID)
{
	global $context, $txt, $user_profile, $smcFunc;

	// Some basic sanity checks.
	if ($context['user']['is_owner'])
		fatal_lang_error('cannot_merge_self', false);
	if ($user_profile[$memID]['id_group'] == 1 || in_array('1', explode(',', $user_profile[$memID]['additional_groups'])))
		fatal_lang_error('cannot_merge_admin', false);

	$context['page_title'] = $txt['merge_char_account'];
	$context['sub_template'] = 'profile_merge_account';
	Autocomplete::init('member', '#merge_acct');

	if (isset($_POST['merge_acct_id']))
	{
		checkSession();
		$result = merge_char_accounts($context['id_member'], $_POST['merge_acct_id']);
		if ($result !== true)
			fatal_lang_error('cannot_merge_' . $result, false);

		session_flash('success', sprintf($txt['merge_success'], $context['member']['name']));

		redirectexit('action=profile;u=' . $_POST['merge_acct_id']);
	}
	elseif (isset($_POST['merge_acct']))
	{
		checkSession();

		// We picked an account to merge, let's see if we can find and if we can,
		// get its details so that we can check for sure it's what the user wants.
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}',
			array(
				'id_member' => (int) $_POST['merge_acct'],
			)
		);
		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('cannot_merge_not_found', false);

		list ($dest) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		loadMemberData($dest);

		$context['merge_destination_id'] = $dest;
		$context['merge_destination'] = $user_profile[$dest];
		$context['sub_template'] = 'profile_merge_account_confirm';
	}
}

/**
 * Perform the actual merger of accounts. Everything from $source gets added to $dest.
 *
 * @param int $source Account ID to merge from
 * @param int $dest Account ID to merge into
 * @return mixed True on success, otherwise string indicating error type
 * @todo refactor this to emit exceptions rather than mixed types
 */
function merge_char_accounts($source, $dest)
{
	global $user_profile, $sourcedir, $smcFunc;

	if ($source == $dest)
		return 'no_same';

	$loaded = loadMemberData(array($source, $dest));
	if (!in_array($source, $loaded) || !in_array($dest, $loaded))
		return 'no_exist';

	if ($user_profile[$source]['id_group'] == 1 || in_array('1', explode(',', $user_profile[$source]['additional_groups'])))
		return 'no_merge_admin';

	// Work out which the main characters are.
	$source_main = 0;
	$dest_main = 0;
	foreach ($user_profile[$source]['characters'] as $id_char => $char)
	{
		if ($char['is_main'])
		{
			$source_main = $id_char;
			break;
		}
	}
	foreach ($user_profile[$dest]['characters'] as $id_char => $char)
	{
		if ($char['is_main'])
		{
			$dest_main = $id_char;
			break;
		}
	}
	if (empty($source_main) || empty($dest_main))
		return 'no_main';

	// Move characters
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}characters
		SET id_member = {int:dest}
		WHERE id_member = {int:source}
			AND id_character != {int:source_main}',
		array(
			'source' => $source,
			'source_main' => $source_main,
			'dest' => $dest,
			'dest_main' => $dest_main,
		)
	);

	// Move posts over - main
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET id_member = {int:dest},
			id_character = {int:dest_main}
		WHERE id_member = {int:source}
			AND id_character = {int:source_main}',
		array(
			'source' => $source,
			'source_main' => $source_main,
			'dest' => $dest,
			'dest_main' => $dest_main,
		)
	);

	// Move posts over - characters (i.e. whatever's left)
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET id_member = {int:dest}
		WHERE id_member = {int:source}',
		array(
			'source' => $source,
			'dest' => $dest,
		)
	);

	// Fix post counts of destination accounts
	$total_posts = 0;
	foreach ($user_profile[$source]['characters'] as $char)
		$total_posts += $char['posts'];

	if (!empty($total_posts))
		updateMemberData($dest, array('posts' => 'posts + ' . $total_posts));

	if (!empty($user_profile[$source]['characters'][$source_main]['posts']))
		updateCharacterData($dest_main, array('posts' => 'posts + ' . $user_profile[$source]['characters'][$source_main]['posts']));

	// Reassign topics
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_member_started = {int:dest}
		WHERE id_member_started = {int:source}',
		array(
			'source' => $source,
			'dest' => $dest,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_member_updated = {int:dest}
		WHERE id_member_updated = {int:source}',
		array(
			'source' => $source,
			'dest' => $dest,
		)
	);

	// Move PMs - sent items
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}personal_messages
		SET id_member_from = {int:dest}
		WHERE id_member_from = {int:source}',
		array(
			'source' => $source,
			'dest' => $dest,
		)
	);

	// Move PMs - received items
	// First we have to get all the existing recipient rows
	$rows = [];
	$request = $smcFunc['db_query']('', '
		SELECT id_pm, bcc, is_read, is_new, deleted
		FROM {db_prefix}pm_recipients
		WHERE id_member = {int:source}',
		array(
			'source' => $source,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$rows[] = array(
			'id_pm' => $row['id_pm'],
			'id_member' => $dest,
			'bcc' => $row['bcc'],
			'is_read' => $row['is_read'],
			'is_new' => $row['is_new'],
			'deleted' => $row['deleted'],
			'is_inbox' => 1,
		);
	}
	$smcFunc['db_free_result']($request);
	if (!empty($rows))
	{
		$smcFunc['db_insert']('ignore',
			'{db_prefix}pm_recipients',
			array(
				'id_pm' => 'int', 'id_member' => 'int', 'bcc' => 'int', 'is_read' => 'int',
				'is_new' => 'int', 'deleted' => 'int', 'is_inbox' => 'int',
			),
			$rows,
			array('id_pm', 'id_member')
		);
	}

	// Delete the source user
	require_once($sourcedir . '/Subs-Members.php');
	deleteMembers($source);

	return true;
}

/**
 * Handle UI aspects of moving characters between accounts, including requesting from the user and security checks.
 * Defers actual processing to move_char_accounts().
 */
function char_move_account()
{
	global $context, $txt, $user_profile, $smcFunc;

	// Some basic sanity checks.
	if ($context['character']['is_main'])
		fatal_lang_error('cannot_move_main', false);

	$context['page_title'] = $txt['move_char_account'];
	$context['sub_template'] = 'profile_character_move_account';
	Autocomplete::init('member', '#move_acct');

	if (isset($_POST['move_acct_id']))
	{
		checkSession();
		$result = move_char_accounts($context['character']['id_character'], $_POST['move_acct_id']);
		if ($result !== true)
			fatal_lang_error('cannot_move_' . $result, false);

		session_flash('success', sprintf($txt['move_success'], $context['character']['character_name']));

		redirectexit('action=profile;u=' . $_POST['move_acct_id']);
	}
	elseif (isset($_POST['move_acct']))
	{
		checkSession();

		// We picked an account to move to, let's see if we can find and if we can,
		// get its details so that we can check for sure it's what the user wants.
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}',
			[
				'id_member' => (int) $_POST['move_acct'],
			]
		);
		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('cannot_move_not_found', false);

		list ($dest) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		loadMemberData($dest);

		$context['move_destination_id'] = $dest;
		$context['move_destination'] = $user_profile[$dest];
		$context['sub_template'] = 'profile_character_move_account_confirm';
	}
}

/**
 * Move a character physically to another account.
 *
 * @param int $source_chr The ID of the character to be moved
 * @param int $dest_acct The ID of the account to move the character to
 * @return mixed True on success, otherwise string indicating error type
 * @todo refactor this to emit exceptions rather than mixed return types
 */
function move_char_accounts($source_chr, $dest_acct)
{
	global $user_profile, $sourcedir, $smcFunc, $modSettings;

	// First, establish that both exist.
	$loaded = loadMemberData(array($dest_acct));
	if (!in_array($dest_acct, $loaded))
		return 'not_found';

	$request = $smcFunc['db_query']('', '
		SELECT chars.id_member, chars.id_character, chars.is_main, chars.posts, mem.current_character
		FROM {db_prefix}characters AS chars
			INNER JOIN {db_prefix}members AS mem ON (chars.id_member = mem.id_member)
		WHERE id_character = {int:char}',
		[
			'char' => $source_chr,
		]
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	if (empty($row))
	{
		return 'cannot_move_char_not_found';
	}
	$smcFunc['db_free_result']($request);
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
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_online
		WHERE log_time >= {int:log_time}
			AND id_character = {int:source_chr}',
		[
			'log_time' => time() - $modSettings['lastActive'] * 60,
			'source_chr' => $source_chr,
		]
	);
	list ($is_online) = $smcFunc['db_fetch_row']($request);
	if ($is_online)
	{
		return 'online';
	}

	// So at this point, we know the account + character of the source
	// and we know the destination account, and we know they exist.

	// Move the character
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}characters
		SET id_member = {int:dest_acct}
		WHERE id_character = {int:source_chr}',
		[
			'source_chr' => $source_chr,
			'dest_acct' => $dest_acct,
		]
	);

	// Move the character's posts
	$smcFunc['db_query']('', '
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
	$request = $smcFunc['db_query']('', '
		SELECT t.id_topic
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
		WHERE m.id_character = {int:source_chr}
		ORDER BY NULL',
		[
			'source_chr' => $source_chr,
		]
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$topics[] = (int) $row['id_topic'];
	}
	$smcFunc['db_free_result']($request);
	if (!empty($topics))
	{
		$smcFunc['db_query']('', '
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
	$request = $smcFunc['db_query']('', '
		SELECT t.id_topic
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (t.id_last_msg = m.id_msg)
		WHERE m.id_character = {int:source_chr}
		ORDER BY NULL',
		[
			'source_chr' => $source_chr,
		]
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$topics[] = (int) $row['id_topic'];
	}
	$smcFunc['db_free_result']($request);
	if (!empty($topics))
	{
		$smcFunc['db_query']('', '
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
 * Display sthe list of characters on the site.
 */
function CharacterList()
{
	global $context, $smcFunc, $txt, $scripturl, $modSettings, $settings;
	global $image_proxy_enabled, $image_proxy_secret;

	$_GET['char'] = isset($_GET['char']) ? (int) $_GET['char'] : 0;
	if ($_GET['char'])
	{
		$result = $smcFunc['db_query']('', '
			SELECT chars.id_character, mem.id_member
			FROM {db_prefix}characters AS chars
			INNER JOIN {db_prefix}members AS mem ON (chars.id_member = mem.id_member)
			WHERE id_character = {int:id_character}',
			array(
				'id_character' => $_GET['char'],
			)
		);
		$redirect = '';
		if ($smcFunc['db_num_rows']($result))
		{
			$row = $smcFunc['db_fetch_assoc']($result);
			$redirect = 'action=profile;u=' . $row['id_member'] . ';area=characters;char=' . $row['id_character'];
		}
		$smcFunc['db_free_result']($result);
		redirectexit($redirect);
	}

	isAllowedTo('view_mlist');
	loadLanguage('Profile');

	$context['filter_characters_in_no_groups'] = allowedTo('admin_forum');
	$context['page_title'] = $txt['chars_menu_title'];
	$context['sub_template'] = 'characterlist_main';
	$context['linktree'][] = array(
		'name' => $txt['chars_menu_title'],
		'url' => $scripturl . '?action=characters',
	);

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

	$clauses = array(
		'chars.is_main = {int:not_main}',
	);
	$vars = array(
		'not_main' => 0,
	);

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

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(id_character)
		FROM {db_prefix}characters AS chars
		WHERE ' . implode(' AND ', $clauses),
		$vars
	);
	list($context['char_count']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$context['items_per_page'] = 12;
	$context['page_index'] = constructPageIndex($scripturl . '?action=characters' . $filter_url . ';start=%1$d', $_REQUEST['start'], $context['char_count'], $context['items_per_page'], true);
	$vars['start'] = $_REQUEST['start'];
	$vars['limit'] = $context['items_per_page'];

	$context['char_list'] = [];
	if (!empty($context['char_count']))
	{
		if (!empty($modSettings['avatar_max_width']))
		{
			addInlineCss('
.char_list_avatar { width: ' . $modSettings['avatar_max_width'] . 'px; height: ' . $modSettings['avatar_max_height'] . 'px; }
.char_list_name { max-width: ' . $modSettings['avatar_max_width'] . 'px; }');
		}

		$request = $smcFunc['db_query']('', '
			SELECT chars.id_character, chars.id_member, chars.character_name,
				chars.avatar, chars.posts, chars.date_created,
				chars.main_char_group, chars.char_groups, chars.char_sheet,
				chars.retired
			FROM {db_prefix}characters AS chars
			WHERE ' . implode(' AND ', $clauses) . '
			ORDER BY chars.character_name
			LIMIT {int:start}, {int:limit}',
			$vars
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($image_proxy_enabled && !empty($row['avatar']) && stripos($row['avatar'], 'http://') !== false)
				$row['avatar'] = $boardurl . '/proxy.php?request=' . urlencode($row['avatar']) . '&hash=' . md5($row['avatar'] . $image_proxy_secret);
			elseif (empty($row['avatar']))
				$row['avatar'] = $settings['images_url'] . '/default.png';

			$row['date_created_format'] = timeformat($row['date_created']);

			$groups = !empty($row['main_char_group']) ? array($row['main_char_group']) : [];
			$groups = array_merge($groups, explode(',', $row['char_groups']));
			$details = get_labels_and_badges($groups);
			$row['group_title'] = $details['title'];
			$row['group_color'] = $details['color'];
			$row['group_badges'] = $details['badges'];
			$context['char_list'][] = $row;
		}
		$smcFunc['db_free_result']($request);
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
	global $context, $txt, $smcFunc;

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
	$request = $smcFunc['db_query']('', '
		SELECT chars.id_character, chars.id_member, chars.character_name,
			chars.date_created, chars.last_active, chars.avatar, chars.posts,
			chars.main_char_group, chars.char_groups, chars.retired
		FROM {db_prefix}characters AS chars
		WHERE chars.char_sheet != 0
			AND main_char_group = {int:group}
		ORDER BY {raw:sort}',
		[
			'group' => $context['group_id'],
			'sort' => $sort[$context['sort_by']][$context['sort_order']],
		]
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['group_list'] = array_merge((array) $row['main_char_group'], explode(',', $row['char_groups']));
		$row['groups'] = get_labels_and_badges($row['group_list']);
		$row['date_created_format'] = timeformat($row['date_created']);
		$row['last_active_format'] = timeformat($row['last_active']);
		$context['characters'][] = $row;
	}
	$smcFunc['db_free_result']($request);
}

/**
 * Moves a post between characters on an account.
 */
function ReattributePost()
{
	global $topic, $smcFunc, $modSettings, $user_info, $board_info;

	// 1. Session check, quick and easy to get out the way before we forget.
	checkSession('get');

	// 2. Get the message id and verify that it exists inside the topic in question.
	$msg = isset($_GET['msg']) ? (int) $_GET['msg'] : 0;
	$result = $smcFunc['db_query']('', '
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
	if ($smcFunc['db_num_rows']($result) == 0)
		fatal_lang_error('no_access', false);

	$row = $smcFunc['db_fetch_assoc']($result);
	$smcFunc['db_free_result']($result);

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
	$smcFunc['db_query']('', '
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
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}characters
			SET posts = (CASE WHEN posts <= 1 THEN 0 ELSE posts - 1 END)
			WHERE id_character = {int:char}',
			[
				'char' => $row['id_character'],
			]
		);

		// Add one to the new owner.
		$smcFunc['db_query']('', '
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
