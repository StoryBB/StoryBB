<?php

/**
 * Displays the character profile page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\StringLibrary;
use StoryBB\Helper\Autocomplete;
use StoryBB\Helper\Parser;
use StoryBB\Model\Alert;
use StoryBB\Model\Attachment;
use StoryBB\Model\Character;

class CharacterProfile extends AbstractProfileController
{
	use CharacterTrait;

	public function display_action()
	{
		global $context, $modSettings, $smcFunc, $txt;

		$this->init_character();

		// There are a few actions that shouldn't have a menu item but have to be processed somewhere.
		if (isset($_GET['sa']))
		{
			$sa = [
				'retire' => 'display_action_retire',
				'delete' => 'display_action_delete',
				'move_acct' => 'display_action_move',
				'theme' => 'display_action_theme',
				'edit' => 'display_action_edit',
			];
			if (isset($sa[$_GET['sa']]))
			{
				$method = $sa[$_GET['sa']];
				$this->$method();
				return;
			}
		}

		$theme_id = !empty($context['character']['id_theme']) ? $context['character']['id_theme'] : $modSettings['theme_guests'];
		$request = $smcFunc['db']->query('', '
			SELECT value
			FROM {db_prefix}themes
			WHERE id_theme = {int:id_theme}
				AND variable = {string:variable}
			LIMIT 1', [
				'id_theme' => $theme_id,
				'variable' => 'name',
			]
		);
		list ($context['character']['theme_name']) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		$groups = array_merge([$context['character']['main_char_group']], explode(',', $context['character']['char_groups']));
		$groups = array_filter(array_map('intval', $groups));
		$context['character']['group_labels'] = get_labels_and_badges($groups);

		$context['character']['days_registered'] = (int) ((time() - $context['character']['date_created']) / (3600 * 24));
		$context['character']['date_created_format'] = timeformat($context['character']['date_created']);
		$context['character']['last_active_format'] = timeformat($context['character']['last_active']);
		$context['character']['signature_parsed'] = Parser::parse_bbc($context['character']['signature'], true, 'sig_char' . $context['character']['id_character']);
		if (empty($context['character']['date_created']) || $context['character']['days_registered'] < 1)
			$context['character']['posts_per_day'] = $txt['not_applicable'];
		else
			$context['character']['posts_per_day'] = comma_format($context['character']['posts'] / $context['character']['days_registered'], 2);

		$context['sub_template'] = 'profile_character_summary';

		$this->load_custom_fields();
	}

	protected function display_action_retire()
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
		$smcFunc['db']->insert('insert',
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

	protected function display_action_delete()
	{
		global $context, $smcFunc, $sourcedir;

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
		$result = $smcFunc['db']->query('', '
			SELECT COUNT(id_msg)
			FROM {db_prefix}messages
			WHERE id_character = {int:char}',
			[
				'char' => $context['character']['id_character'],
			]
		);
		list ($count) = $smcFunc['db']->fetch_row($result);
		$smcFunc['db']->free_result($result);

		if ($count > 0)
		{
			fatal_lang_error('this_character_cannot_delete_posts', false);
		}

		// Is the character currently in action?
		$result = $smcFunc['db']->query('', '
			SELECT current_character
			FROM {db_prefix}members
			WHERE id_member = {int:member}',
			[
				'member' => $context['id_member'],
			]
		);
		list ($current_character) = $smcFunc['db']->fetch_row($result);
		$smcFunc['db']->free_result($result);
		if ($current_character == $context['character']['id_character'])
		{
			fatal_lang_error($context['user']['is_owner'] ? 'this_character_cannot_delete_active_self' : 'this_character_cannot_delete_active', false);
		}

		// Delete alerts attached to this character.
		// But first, find all the members where this is relevant, and where they have unread alerts (so we can fix the alert count).
		$result = $smcFunc['db']->query('', '
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
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			$members[$row['id_member']] = $row['id_member'];
		}
		$smcFunc['db']->free_result($result);
		// Having found all of the people whose alert counts need to be fixed, let's now purge all these alerts.
		$smcFunc['db']->query('', '
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

		Character::delete_character($context['character']['id_character']);

		redirectexit('action=profile;u=' . $context['id_member']);
	}

	protected function display_action_move()
	{
		global $context, $txt;

		// Some basic sanity checks.
		if ($context['character']['is_main'])
			fatal_lang_error('cannot_move_main', false);

		$context['page_title'] = $txt['move_char_account'];
		$context['sub_template'] = 'profile_character_move_account';
		Autocomplete::init('member', '#move_acct');
	}

	public function post_action()
	{
		$this->init_character();

		if (isset($_GET['sa']))
		{
			$sa = [
				'move_acct' => 'post_action_move',
				'theme' => 'post_action_theme',
				'edit' => 'post_action_edit',
			];
			if (isset($sa[$_GET['sa']]))
			{
				$method = $sa[$_GET['sa']];
				$this->$method();
			}
		}
		else
		{
			redirectexit('action=profile;u=' . $this->params['u']);
		}
	}

	protected function post_action_move()
	{
		global $context, $txt, $smcFunc, $user_profile;

		if (isset($_POST['move_acct_id']))
		{
			$result = Character::move_char_accounts($context['character']['id_character'], $_POST['move_acct_id']);
			if ($result !== true)
				fatal_lang_error('cannot_move_' . $result, false);

			session_flash('success', sprintf($txt['move_success'], $context['character']['character_name']));

			redirectexit('action=profile;u=' . $_POST['move_acct_id']);
		}
		elseif (isset($_POST['move_acct']))
		{
			// We picked an account to move to, let's see if we can find and if we can,
			// get its details so that we can check for sure it's what the user wants.
			$request = $smcFunc['db']->query('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE id_member = {int:id_member}',
				[
					'id_member' => (int) $_POST['move_acct'],
				]
			);
			if ($smcFunc['db']->num_rows($request) == 0)
				fatal_lang_error('cannot_move_not_found', false);

			list ($dest) = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);

			loadMemberData($dest);

			$context['move_destination_id'] = $dest;
			$context['move_destination'] = $user_profile[$dest];
			$context['sub_template'] = 'profile_character_move_account_confirm';

			return;
		}

		redirectexit('action=profile;u=' . $this->params['u']);
	}

	protected function display_action_theme()
	{
		global $context, $smcFunc, $modSettings;

		// If they don't have permission to be here, goodbye.
		if (!$context['character']['editable']) {
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
		}

		$known_themes = explode(',', $modSettings['knownThemes']);
		$context['themes'] = [];
		foreach ($known_themes as $id_theme) {
			$context['themes'][$id_theme] = [
				'name' => '',
				'theme_dir' => '',
				'images_url' => '',
				'thumbnail' => ''
			];
		}

		$request = $smcFunc['db']->query('', '
			SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE id_member = 0
				AND variable IN ({array_string:vars})',
			[
				'vars' => ['name', 'images_url', 'theme_dir'],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (!isset($context['themes'][$row['id_theme']]))
			{
				continue;
			}
			$context['themes'][$row['id_theme']][$row['variable']] = $row['value'];
		}
		$smcFunc['db']->free_result($request);

		foreach ($context['themes'] as $id_theme => $theme)
		{
			if (empty($theme['name']) || empty($theme['images_url']) || !file_exists($theme['theme_dir']))
				unset ($context['themes'][$id_theme]);

			foreach (['.png', '.gif', '.jpg'] as $ext)
				if (file_exists($theme['theme_dir'] . '/images/thumbnail' . $ext))
				{
					$context['themes'][$id_theme]['thumbnail'] = $theme['images_url'] . '/thumbnail' . $ext;
					break;
				}

			if (empty($context['themes'][$id_theme]['thumbnail']))
				unset ($context['themes'][$id_theme]);
		}

		uasort($context['themes'], function($a, $b) {
			return strcasecmp($a['name'], $b['name']);
		});

		$context['sub_template'] = 'profile_character_theme';
	}

	protected function post_action_theme()
	{
		global $context, $user_info;
		$this->display_action_theme();

		if (!empty($_POST['chartheme']) && is_array($_POST['chartheme']))
		{
			list($id_theme) = array_keys($_POST['chartheme']);
			if (isset($context['themes'][$id_theme]))
			{
				updateCharacterData($context['character']['id_character'], ['id_theme' => $id_theme]);

				if ($context['user']['is_owner'] && $context['character']['id_character'] == $user_info['id_character'])
				{
					unset ($_SESSION['id_theme']);
				}
			}
		}

		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
	}

	protected function display_action_edit()
	{
		global $context, $smcFunc, $txt, $sourcedir, $user_info, $modSettings;
		global $profile_vars, $settings;

		// If they don't have permission to be here, goodbye.
		if (!$context['character']['editable']) {
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
		}

		$context['sub_template'] = 'profile_character_edit';
		loadJavascriptFile('chars.js', ['default_theme' => true], 'chars');

		$context['character']['groups_editable'] = false;
		if (allowedTo('manage_membergroups') && !$context['character']['is_main'])
		{
			$context['character']['groups_editable'] = true;
			Character::load_groups();
		}

		require_once($sourcedir . '/Subs-Post.php');
		require_once($sourcedir . '/Profile-Modify.php');
		profileLoadSignatureData();

		$context['form_errors'] = [];
		$default_avatar = $settings['images_url'] . '/default.png';

		$context['character']['avatar_settings'] = [
			'custom' => stristr($context['character']['avatar'], 'http://') || stristr($context['character']['avatar'], 'https://') ? $context['character']['avatar'] : 'http://',
			'selection' => $context['character']['avatar'] == '' || (stristr($context['character']['avatar'], 'http://') || stristr($context['character']['avatar'], 'https://')) ? '' : $context['character']['avatar'],
			'allow_upload' => allowedTo('profile_upload_avatar') || (!$context['user']['is_owner'] && allowedTo('profile_extra_any')),
			'allow_external' => allowedTo('profile_remote_avatar') || (!$context['user']['is_owner'] && allowedTo('profile_extra_any')),
		];

		if ((!empty($context['character']['avatar']) && $context['character']['avatar'] != $default_avatar) && $context['character']['id_attach'] > 0 && $context['character']['avatar_settings']['allow_upload'])
		{
			$context['character']['avatar_settings'] += [
				'choice' => 'upload',
				'external' => 'http://'
			];
			$context['character']['avatar'] = $modSettings['custom_avatar_url'] . '/' . $context['character']['avatar_filename'];
		}
		// Use "avatar_original" here so we show what the user entered even if the image proxy is enabled
		elseif ((stristr($context['character']['avatar'], 'http://') || stristr($context['character']['avatar'], 'https://')) && $context['character']['avatar_settings']['allow_external'] && $context['character']['avatar'] != $default_avatar)
			$context['character']['avatar_settings'] += [
				'choice' => 'external',
				'external' => $context['character']['avatar_original']
			];
		else
			$context['character']['avatar_settings'] += [
				'choice' => 'none',
				'external' => 'http://'
			];

		$context['character']['avatar_settings']['is_url'] = (strpos($context['character']['avatar_settings']['external'], 'https://') === 0) || (strpos($context['character']['avatar_settings']['external'], 'http://') === 0);

		$form_value = !empty($context['character']['signature']) ? $context['character']['signature'] : '';
		// Get it ready for the editor.
		$form_value = un_preparsecode($form_value);
		censorText($form_value);
		$form_value = str_replace(['"', '<', '>', '&nbsp;'], ['&quot;', '&lt;', '&gt;', ' '], $form_value);
		$context['character']['signature_parsed'] = Parser::parse_bbc($context['character']['signature'], true, 'sig_char_' . $context['character']['id_character']);

		require_once($sourcedir . '/Subs-Editor.php');
		$editorOptions = [
			'id' => 'char_signature',
			'value' => $form_value,
			'disable_smiley_box' => false,
			'labels' => [],
			'height' => '200px',
			'width' => '80%',
			'preview_type' => 0,
			'required' => true,
		];
		create_control_richedit($editorOptions);

		createToken('edit-char' . $context['character']['id_character'], 'post');

		$this->load_custom_fields(true);
		foreach ($context['character']['custom_fields'] as $key => $value)
		{
			if ($value['show_profile'] != 'char')
			{
				unset ($context['character']['custom_fields'][$key]);
			}
		}
	}

	protected function post_action_edit()
	{
		global $context, $smcFunc, $txt, $sourcedir, $user_info, $modSettings;
		global $profile_vars, $settings;

		// If they don't have permission to be here, goodbye.
		if (!$context['character']['editable']) {
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
		}

		loadLanguage('Errors');

		validateToken('edit-char' . $context['character']['id_character'], 'post');

		$this->display_action_edit();

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

		$new_name = !empty($_POST['char_name']) ? StringLibrary::escape(trim($_POST['char_name']), ENT_QUOTES) : '';
		if ($new_name == '')
			$context['form_errors'][] = $txt['char_error_character_must_have_name'];
		elseif ($new_name != $context['character']['character_name'])
		{
			// Check if the name already exists.
			$result = $smcFunc['db']->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}characters
				WHERE character_name LIKE {string:new_name}
					AND id_character != {int:char}',
				[
					'new_name' => $new_name,
					'char' => $context['character']['id_character'],
				]
			);
			list ($matching_names) = $smcFunc['db']->fetch_row($result);
			$smcFunc['db']->free_result($result);

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

		$new_sig = !empty($_POST['char_signature']) ? StringLibrary::escape($_POST['char_signature'], ENT_QUOTES) : '';
		$valid_sig = profileValidateSignature($new_sig);
		if ($valid_sig === true)
			$changes['signature'] = $new_sig; // sanitised by profileValidateSignature
		else
			$context['form_errors'][] = $valid_sig;

		$cf_errors = makeCustomFieldChanges($context['id_member'], $context['character']['id_character'], 'char', true, true);
		foreach ($cf_errors as $error)
		{
			$context['form_errors'][] = $cf_errors;
		}

		if (!empty($changes) && empty($context['form_errors']))
		{
			if ($context['character']['is_main'])
			{
				if (isset($changes['character_name']))
					updateMemberData($context['id_member'], ['real_name' => $changes['character_name']]);
			}

			// Notify any hooks that there are groups changes.
			if (isset($changes['main_char_group']) || isset($changes['char_groups']))
			{
				$primary_group = isset($changes['main_char_group']) ? $changes['main_char_group'] : $context['character']['main_char_group'];
				$additional_groups = isset($changes['char_groups']) ? $changes['char_groups'] : $context['character']['char_groups'];

				call_integration_hook('integrate_profile_profileSaveCharGroups', [$context['id_member'], $context['character']['id_character'], $primary_group, $additional_groups]);
			}

			if (!empty($modSettings['userlog_enabled'])) {
				$rows = [];
				foreach ($changes as $key => $new_value)
				{
					$change_array = [
						'previous' => $context['character'][$key],
						'new' => $new_value,
						'applicator' => $context['user']['id'],
						'member_affected' => $context['id_member'],
						'id_character' => $context['character']['id_character'],
						'character_name' => !empty($changes['character_name']) ? $changes['character_name'] : $context['character']['character_name'],
					];
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
					$rows[] = [
						'id_log' => 2, // 2 = profile edits log
						'log_time' => time(),
						'id_member' => $context['id_member'],
						'ip' => $user_info['ip'],
						'action' => $context['character']['is_main'] && $key == 'character_name' ? 'real_name' : 'char_' . $key,
						'id_board' => 0,
						'id_topic' => 0,
						'id_msg' => 0,
						'extra' => json_encode($change_array),
					];
				}
				if (!empty($rows)) {
					$smcFunc['db']->insert('insert',
						'{db_prefix}log_actions',
						['id_log' => 'int', 'log_time' => 'int', 'id_member' => 'int',
							'ip' => 'inet', 'action' => 'string', 'id_board' => 'int',
							'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string'],
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
}
