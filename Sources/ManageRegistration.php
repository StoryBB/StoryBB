<?php

/**
 * This file helps the administrator setting registration settings and policy
 * as well as allow the administrator to register new members themselves.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Model\Policy;
use StoryBB\StringLibrary;

/**
 * Entrance point for the registration center, it checks permissions and forwards
 * to the right function based on the subaction.
 * Accessed by ?action=admin;area=regcenter.
 * Requires either the moderate_forum or the admin_forum permission.
 *
 * @uses Login language file
 * @uses Register template.
 */
function RegCenter()
{
	global $context, $txt;

	// Old templates might still request this.
	if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'browse')
		redirectexit('action=admin;area=viewmembers;sa=browse' . (isset($_REQUEST['type']) ? ';type=' . $_REQUEST['type'] : ''));

	$subActions = [
		'register' => ['AdminRegister', 'moderate_forum'],
		'settings' => ['ModifyRegistrationSettings', 'admin_forum'],
		'policies' => ['ManagePolicies', 'admin_forum'],
	];

	// Work out which to call...
	$context['sub_action'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (allowedTo('moderate_forum') ? 'register' : 'settings');

	// Must have sufficient permissions.
	isAllowedTo($subActions[$context['sub_action']][1]);

	// Loading, always loading.
	loadLanguage('Login');

	// Next create the tabs for the template.
	$context[$context['admin_menu_name']]['tab_data'] = [
		'title' => $txt['registration_center'],
		'help' => '',
		'description' => $txt['admin_settings_desc'],
		'tabs' => [
			'register' => [
				'description' => $txt['admin_register_desc'],
			],
			'policies' => [
				'description' => $txt['admin_policies_desc'],
			],
			'settings' => [
				'description' => $txt['admin_settings_desc'],
			],
		]
	];

	routing_integration_hook('integrate_manage_registrations', [&$subActions]);

	// Finally, get around to calling the function...
	call_helper($subActions[$context['sub_action']][0]);
}

/**
 * This function allows the admin to register a new member by hand.
 * It also allows assigning a primary group to the member being registered.
 * Accessed by ?action=admin;area=regcenter;sa=register
 * Requires the moderate_forum permission.
 *
 * @uses Register template, admin_register sub-template.
 */
function AdminRegister()
{
	global $txt, $context, $sourcedir, $scripturl, $smcFunc;

	// Are there any custom profile fields required during registration?
	require_once($sourcedir . '/Profile.php');
	loadCustomFields(0, 'register');

	if (!empty($_POST['regSubmit']))
	{
		checkSession();
		validateToken('admin-regc');

		foreach ($_POST as $key => $value)
			if (!is_array($_POST[$key]))
				$_POST[$key] = htmltrim__recursive(str_replace(["\n", "\r"], '', $value));

		$regOptions = [
			'interface' => 'admin',
			'username' => $_POST['user'],
			'email' => $_POST['email'],
			'password' => $_POST['password'],
			'password_check' => $_POST['password'],
			'check_reserved_name' => true,
			'check_password_strength' => false,
			'check_email_ban' => false,
			'send_welcome_email' => isset($_POST['emailPassword']) || empty($_POST['password']),
			'require' => isset($_POST['emailActivate']) ? 'activation' : 'nothing',
			'memberGroup' => empty($_POST['group']) || !allowedTo('manage_membergroups') ? 0 : (int) $_POST['group'],
		];

		require_once($sourcedir . '/Subs-Members.php');
		$memberID = registerMember($regOptions);
		if (!empty($memberID))
		{
			// We'll do custom fields after as then we get to use the helper function!
			if (!empty($_POST['customfield']))
			{
				require_once($sourcedir . '/Profile-Modify.php');
				makeCustomFieldChanges($memberID, 0, 'register');
			}

			$context['new_member'] = [
				'id' => $memberID,
				'name' => $_POST['user'],
				'href' => $scripturl . '?action=profile;u=' . $memberID,
				'link' => '<a href="' . $scripturl . '?action=profile;u=' . $memberID . '">' . $_POST['user'] . '</a>',
			];
			$context['registration_done'] = sprintf($txt['admin_register_done'], $context['new_member']['link']);
		}
	}


	// Load the assignable member groups.
	if (allowedTo('manage_membergroups'))
	{
		$request = $smcFunc['db']->query('', '
			SELECT group_name, id_group
			FROM {db_prefix}membergroups
			WHERE id_group != {int:moderator_group}' . (allowedTo('admin_forum') ? '' : '
				AND id_group != {int:admin_group}
				AND group_type != {int:is_protected}') . '
				AND hidden != {int:hidden_group}
			ORDER BY group_name',
			[
				'moderator_group' => 3,
				'admin_group' => 1,
				'is_protected' => 1,
				'hidden_group' => 2,
				'newbie_group' => 4,
			]
		);
		$context['member_groups'] = [0 => $txt['admin_register_group_none']];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$context['member_groups'][$row['id_group']] = $row['group_name'];
		$smcFunc['db']->free_result($request);
	}
	else
		$context['member_groups'] = [];

	// Basic stuff.
	$context['sub_template'] = 'register_admin';
	$context['page_title'] = $txt['registration_center'];
	createToken('admin-regc');
	loadJavaScriptFile('register.js', ['defer' => false], 'sbb_register');
}

/**
 * This function handles registration settings.
 * Accessed by ?action=admin;area=regcenter;sa=settings.
 * Requires the admin_forum permission.
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyRegistrationSettings($return_config = false)
{
	global $txt, $context, $scripturl, $sourcedir;

	// This is really quite wanting.
	require_once($sourcedir . '/ManageServer.php');

	$config_vars = [
			['select', 'registration_method', [$txt['setting_registration_standard'], $txt['setting_registration_activate'], $txt['setting_registration_approval'], $txt['setting_registration_disabled']]],
			['int', 'remove_unapproved_accounts_days', 'min' => 0, 'max' => 30, 'subtext' => $txt['zero_to_disable']],
			['check', 'send_welcomeEmail'],
			['select', 'registration_character', [
				'disabled' => $txt['setting_registration_character_disabled'],
				'optional' => $txt['setting_registration_character_optional'],
				'required' => $txt['setting_registration_character_required'],
			]],
		'',
			['int', 'minimum_age'],
			['check', 'minimum_age_profile'],
			['check', 'age_on_registration'],
		'',
			['check', 'show_cookie_notice'],
			['title', 'admin_reserved_set'],
			['large_text', 'reserveNames', 'subtext' => $txt['admin_reserved_line']],
			['check', 'reserveWord'],
			['check', 'reserveCase'],
			['check', 'reserveUser'],
			['check', 'reserveName'],
	];

	settings_integration_hook('integrate_modify_registration_settings', [&$config_vars]);

	if ($return_config)
		return [$txt['registration_center'] . ' - ' . $txt['settings'], $config_vars];

	// Setup the template
	$context['page_title'] = $txt['registration_center'];

	if (isset($_GET['save']))
	{
		checkSession();

		$_POST['reserveNames'] = str_replace("\r", '', $_POST['reserveNames']);

		settings_integration_hook('integrate_save_registration_settings');

		saveDBSettings($config_vars);
		session_flash('success', $txt['settings_saved']);
		redirectexit('action=admin;area=regcenter;sa=settings');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=regcenter;save;sa=settings';
	$context['settings_title'] = $txt['settings'];

	prepareDBSettingContext($config_vars);
}

/**
 * This area handles site policies.
 * Accessed by ?action=admin;area=regcenter;sa=policies.
 * Requires the admin_forum permission.
 */
function ManagePolicies()
{
	global $txt, $context, $sourcedir;
	loadLanguage('Login');

	$context['policies'] = Policy::get_policy_list();
	$context['page_title'] = $txt['admin_policies'];

	$context['policy_language'] = isset($_REQUEST['lang']) ? $_REQUEST['lang'] : '';

	// If the user specified an actual policy, let them edit that.
	if (!empty($_REQUEST['policy']) && isset($context['policies'][$_REQUEST['policy']]))
	{
		$policy = $context['policies'][$_REQUEST['policy']];
		require_once($sourcedir . '/Subs-Post.php');
		require_once($sourcedir . '/Subs-Editor.php');

		// Is it for a language we know about?
		if (isset($policy['versions'][$context['policy_language']]) || in_array($context['policy_language'], $policy['no_language']))
		{
			$context['policy_token'] = 'adm-pol-' . $_REQUEST['policy'] . '-' . $context['policy_language'];
			$context['policy'] = $policy;
			$context['policy_id'] = $_REQUEST['policy'];

			// Are we saving?
			if (isset($_POST['save']))
			{
				checkSession();
				validateToken($context['policy_token']);

				$policy_details = [];

				// Now, let's validate/process things in order.
				// Policy name is not optional - but if we don't have one, don't overwrite what we already had.
				if (!empty($_POST['policy_name']))
				{
					$policy_details['title'] = StringLibrary::escape($_POST['policy_name']);
				}
				elseif (!empty($policy['versions'][$context['policy_language']]))
				{
					$policy_details['title'] = $policy['versions'][$context['policy_language']]['title'];
				}
				else
				{
					// Fall back to English which we really should have.
					$policy_details['title'] = $policy['versions']['en-us']['title'];
				}

				// Policy description is optional.
				$policy_details['description'] = isset($_POST['policy_desc']) ? StringLibrary::escape($_POST['policy_desc'], ENT_QUOTES) : '';

				// Showing on registration is easy.
				$policy_details['show_reg'] = !empty($_POST['show_reg']);

				// Showing on help is also easy.
				$policy_details['show_help'] = !empty($_POST['show_help']);

				// Lastly, showing in the footer.
				$policy_details['show_footer'] = !empty($_POST['show_footer']);

				if (!empty($_POST['message']))
				{
					$policy_details['policy_text'] = StringLibrary::escape($_POST['message'], ENT_QUOTES);
					preparsecode($policy_details['policy_text']);
					// We need to fix a few of the replacements where we have links as placeholders.
					$replacements = [
						'[url=http://{$' => '[url={$',
						'[url=&quot;http://{$' => '[url=&quot;{$',
					];
					$policy_details['policy_text'] = strtr($policy_details['policy_text'], $replacements);

					$policy_details['edit_id_member'] = $context['user']['id'];
					$policy_details['edit_member_name'] = $context['user']['name'];
				}

				$force_update = false;
				if ($context['policy']['require_acceptance'])
				{
					// If the policy can be required, let's check if that's a thing.
					$force_update = !empty($_POST['policy_reagree']);
					$policy_details['policy_edit'] = !empty($_POST['policy_edit']) ? StringLibrary::escape($_POST['policy_edit'], ENT_QUOTES) : '';
				}

				// Update the policy with the new details.
				Policy::update_policy($_REQUEST['policy'], $context['policy_language'], $policy_details);

				if ($force_update)
				{
					// Now that a policy exists, bump people to reagree to it.
					Policy::reset_acceptance([$context['user']['id']]);
				}

				// And we're done here.
				redirectexit('action=admin;area=regcenter;sa=policies');
			}

			// If not, push everything we need into the context so we can get it in the template.
			if (isset($policy['versions'][$context['policy_language']]))
			{
				$context['policy_version'] = $policy['versions'][$context['policy_language']];
				$context['policy_revision'] = Policy::get_policy_revision((int) $context['policy_version']['last_revision']);
				$context['policy_version']['policy_text'] = $context['policy_revision']['revision_text'];
			}
			else
			{
				$context['policy_version'] = [
					'title' => '',
					'description' => '',
					'policy_text' => '',
				];
			}

			// Now create the editor.
			$editorOptions = [
				'id' => 'message',
				'value' => un_preparsecode($context['policy_version']['policy_text']),
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

			createToken($context['policy_token']);
			$context['sub_template'] = 'admin_policy_edit';
			return;
		}
	}

	// So we're displaying a list of policies and their language versions.
	$context['sub_template'] = 'admin_policy_list';
}
