<?php

/**
 * This file has two main jobs, but they really are one.  It registers new
 * members, and it helps the administrator moderate member registrations.
 * Similarly, it handles account activation as well.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Model\Policy;
use StoryBB\Helper\Wave;

/**
 * Begin the registration process.
 *
 * @param array $reg_errors Holds information about any errors that occurred
 */
function Register($reg_errors = [])
{
	global $txt, $boarddir, $context, $modSettings, $user_info;
	global $language, $scripturl, $smcFunc, $sourcedir, $cur_profile;

	// Is this an incoming AJAX check?
	if (isset($_GET['sa']) && $_GET['sa'] == 'usernamecheck')
		return RegisterCheckUsername();

	// Check if the administrator has it disabled.
	if (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == '3')
		fatal_lang_error('registration_disabled', false);

	// If this user is an admin - redirect them to the admin registration page.
	if (allowedTo('moderate_forum') && !$user_info['is_guest'])
		redirectexit('action=admin;area=regcenter;sa=register');
	// You are not a guest, so you are a member - and members don't get to register twice!
	elseif (empty($user_info['is_guest']))
		redirectexit();

	loadLanguage('Login');

	// Do we need them to agree to the registration agreement, first?
	$policies = Policy::get_policies_for_registration();
	$context['registration_policies'] = [];
	foreach ($policies as $policy_type => $policy_name)
	{
		$context['registration_policies'][$policy_type] = '<a href="' . $scripturl . '?action=help;sa=' . $policy_type . '" target="_blank" rel="noopener">' . $policy_name . '</a>';
	}

	// Show the user the right form.
	$context['sub_template'] = 'register_form';
	$context['page_title'] = $txt['registration_form'];

	// Kinda need this.
	if ($context['sub_template'] == 'register_form')
		loadJavaScriptFile('register.js', array('defer' => false), 'sbb_register');

	// Add the register chain to the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=signup',
		'name' => $txt['register'],
	);

	// Prepare the time gate! Do it like so, in case later attempts want to reset the limit for any reason, but make sure the time is the current one.
	if (!isset($_SESSION['register']))
		$_SESSION['register'] = array(
			'timenow' => time(),
			'limit' => 10, // minimum number of seconds required on this page for registration
		);
	else
		$_SESSION['register']['timenow'] = time();

	if (!empty($modSettings['userLanguage']))
	{
		$selectedLanguage = empty($_SESSION['language']) ? $language : $_SESSION['language'];

		// Do we have any languages?
		if (empty($context['languages']))
			getLanguages();

		// Try to find our selected language.
		foreach ($context['languages'] as $key => $lang)
		{
			$context['languages'][$key]['name'] = $lang['name'];

			// Found it!
			if ($selectedLanguage == $lang['filename'])
				$context['languages'][$key]['selected'] = true;
		}
	}

	// Any custom fields we want filled in?
	require_once($sourcedir . '/Profile.php');
	loadCustomFields(0, 'register');

	// Preprocessing: sometimes we're given eval strings to make options for custom fields.
	if (!empty($context['profile_fields'])) {
		foreach ($context['profile_fields'] as $key => $field) {
			if ($field['type'] == 'select' && !is_array($field['options'])) {
				$context['profile_fields'][$key]['options'] = eval($field['options']);
			}
		}
	}

	StoryBB\Template::add_helper([
		'makeHTTPS' => function($url) { 
			return strtr($url, array('http://' => 'https://'));
		},
		'field_isText' => function($type) {
			return in_array($type, array('int', 'float', 'text', 'password', 'url'));
		}
	]);

	// Or any standard ones?
	$reg_fields = [];
	if (!empty($modSettings['registration_fields']))
	{
		$reg_fields = explode(',', $modSettings['registration_fields']);
	}
	if (!empty($modSettings['minimum_age']) || !empty($modSettings['age_on_registration']))
	{
		$reg_fields[] = 'birthday_date';
	}
	if (!empty($reg_fields))
	{
		require_once($sourcedir . '/Profile-Modify.php');

		// Setup some important context.
		loadLanguage('Profile');

		$context['user']['is_owner'] = true;

		// Here, and here only, emulate the permissions the user would have to do this.
		$user_info['permissions'] = array_merge($user_info['permissions'], array('profile_account_own', 'profile_extra_own', 'profile_other_own', 'profile_password_own', 'profile_website_own'));

		// We might have had some submissions on this front - go check.
		foreach ($reg_fields as $field)
			if (isset($_POST[$field]))
				$cur_profile[$field] = $smcFunc['htmlspecialchars']($_POST[$field]);

		// Load all the fields in question.
		setupProfileContext($reg_fields);
	}
	$context['profile_fields_required'] = [];
	if (isset($context['profile_fields']['birthday_date']))
	{
		$context['profile_fields_required']['birthday_date'] = $context['profile_fields']['birthday_date'];
		unset($context['profile_fields']['birthday_date']);
	}

	// Generate a visual verification code to make sure the user is no bot.
	if (!empty($modSettings['reg_verification']))
	{
		require_once($sourcedir . '/Subs-Editor.php');
		$verificationOptions = array(
			'id' => 'register',
		);
		$context['visual_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}
	// Otherwise we have nothing to show.
	else
		$context['visual_verification'] = false;

	if (!empty($modSettings['minimum_age']) || !empty($modSettings['age_on_registration']))
	{
		$context['member']['birth_date'] = [
			'day' => isset($_POST['bday2']) ? $_POST['bday2'] : '',
			'month' => isset($_POST['bday1']) ? $_POST['bday1'] : '',
			'year' => isset($_POST['bday3']) ? $_POST['bday3'] : '',
		];
	}

	$context += array(
		'username' => isset($_POST['user']) ? $smcFunc['htmlspecialchars']($_POST['user']) : '',
		'real_name' => isset($_POST['real_name']) ? $smcFunc['htmlspecialchars']($_POST['real_name']) : '',
		'first_char' => isset($_POST['first_char']) ? $smcFunc['htmlspecialchars']($_POST['first_char']) : '',
		'email' => isset($_POST['email']) ? $smcFunc['htmlspecialchars']($_POST['email']) : '',
		'notify_announcements' => !empty($_POST['notify_announcements']) ? 1 : 0,
	);

	// Were there any errors?
	$context['registration_errors'] = [];
	if (!empty($reg_errors))
		$context['registration_errors'] = $reg_errors;

	$context['display_edit_real_name'] = false;
	if (allowedTo('profile_displayed_name_any') || allowedTo('moderate_forum'))
		$context['display_edit_real_name'] = true;
	// If you are a guest, will you be allowed to once you register?
	else
	{
		require_once($sourcedir . '/Subs-Members.php');
		$context['display_edit_real_name'] = in_array(0, groupsAllowedTo('profile_displayed_name_own')['allowed']);
	}
	$context['display_create_character'] = !empty($modSettings['registration_character']) && in_array($modSettings['registration_character'], ['optional', 'required']);

	createToken('register');
}

/**
 * Actually register the member.
 */
function Register2()
{
	global $txt, $modSettings, $context, $sourcedir;
	global $smcFunc, $maintenance;

	require_once($sourcedir . '/Subs-Members.php');

	checkSession();
	validateToken('register');

	// Check to ensure we're forcing SSL for authentication
	if (!empty($modSettings['force_ssl']) && empty($maintenance) && !httpsOn())
		fatal_lang_error('register_ssl_required');

	// Start collecting together any errors.
	$reg_errors = [];

	// You can't register if it's disabled.
	if (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 3)
		fatal_lang_error('registration_disabled', false);

	// Check whether they have accepted policies.
	$policies = Policy::get_policies_for_registration();
	foreach ($policies as $policy_type => $policy_name)
	{
		if (empty($_POST['policy_' . $policy_type]))
		{
			loadLanguage('Errors');
			$reg_errors[] = sprintf($txt['registration_require_policy'], $policy_name);
		}
	}

	// Check the time gate for miscreants. First make sure they came from somewhere that actually set it up.
	if (empty($_SESSION['register']['timenow']) || empty($_SESSION['register']['limit']))
		redirectexit('action=signup');
	// Failing that, check the time on it.
	if (time() - $_SESSION['register']['timenow'] < $_SESSION['register']['limit'])
	{
		loadLanguage('Errors');
		$reg_errors[] = $txt['error_too_quickly'];
	}

	// Check whether the visual verification code was entered correctly.
	if (!empty($modSettings['reg_verification']))
	{
		require_once($sourcedir . '/Subs-Editor.php');
		$verificationOptions = array(
			'id' => 'register',
		);
		$context['visual_verification'] = create_control_verification($verificationOptions, true);

		if (is_array($context['visual_verification']))
		{
			loadLanguage('Errors');
			foreach ($context['visual_verification'] as $error)
				$reg_errors[] = $txt['error_' . $error];
		}
	}

	foreach ($_POST as $key => $value)
	{
		if (!is_array($_POST[$key]))
			$_POST[$key] = htmltrim__recursive(str_replace(array("\n", "\r"), '', $_POST[$key]));
	}

	// Collect all extra registration fields someone might have filled in.
	$possible_strings = array(
		'first_char',
		'time_format',
		'buddy_list',
		'pm_ignore_list',
		'avatar',
		'lngfile',
		'secret_question', 'secret_answer',
	);
	$possible_ints = array(
		'id_theme',
	);
	$possible_floats = array(
		'time_offset',
	);
	$possible_bools = array(
		'show_online',
	);

	// We may want to add certain things to these if selected in the admin panel.
	if (!empty($modSettings['registration_fields']))
	{
		$reg_fields = explode(',', $modSettings['registration_fields']);

		// Website is a little different
		if (in_array('website', $reg_fields))
			$possible_strings = array_merge($possible_strings, array('website_url', 'website_title'));
	}

	if (isset($_POST['secret_answer']) && $_POST['secret_answer'] != '')
		$_POST['secret_answer'] = md5($_POST['secret_answer']);

	// Needed for isReservedName() and registerMember().
	require_once($sourcedir . '/Subs-Members.php');

	// Maybe you want set the displayed name during registration
	if (isset($_POST['real_name']))
	{
		// Are you already allowed to edit the displayed name?
		if (allowedTo('profile_displayed_name_any') || allowedTo('moderate_forum'))
			$canEditDisplayName = true;

		// If you are a guest, will you be allowed to once you register?
		else
		{
			$canEditDisplayName = in_array(0, groupsAllowedTo('profile_displayed_name_own')['allowed']);
		}

		if ($canEditDisplayName)
		{
			// Sanitize it
			$_POST['real_name'] = trim(preg_replace('~[\t\n\r \x0B\0\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}]+~u', ' ', $_POST['real_name']));

			// Only set it if we are sure it is good
			if (trim($_POST['real_name']) != '' && !isReservedName($_POST['real_name']) && $smcFunc['strlen']($_POST['real_name']) < 60)
				$possible_strings[] = 'real_name';
		}
	}

	// Handle a string as a birthdate...
	if (!empty($modSettings['minimum_age']) || !empty($modSettings['age_on_registration']))
	{
		if (!isset($_POST['bday1']))
			$_POST['bday1'] = '';
		if (!isset($_POST['bday2']))
			$_POST['bday2'] = '';
		if (!isset($_POST['bday3']))
			$_POST['bday3'] = '';

		// Make sure it's valid and if it is, handle it.
		$_POST['birthdate'] = checkdate((int) $_POST['bday1'], (int) $_POST['bday2'], $_POST['bday3'] < 1004 ? 1004 : (int) $_POST['bday3']) ? sprintf('%04d-%02d-%02d', $_POST['bday3'] < 1004 ? 1004 : $_POST['bday3'], $_POST['bday1'], $_POST['bday2']) : '1004-01-01';
		if ($_POST['birthdate'] == '1004-01-01' || $_POST['bday3'] <= 1004)
		{
			loadLanguage('Errors');
			$reg_errors['invalid_dob'] = $txt['error_dob_required'];
		}
	}

	// Also check if it's valid or not.
	if (!empty($modSettings['minimum_age']) && $_POST['birthdate'] != '1004-01-01')
	{
		$datearray = getdate(forum_time());
		$age = $datearray['year'] - $_POST['bday3'] - (($datearray['mon'] > $_POST['bday1'] || ($datearray['mon'] == $_POST['bday1'] && $datearray['mday'] >= $_POST['bday2'])) ? 0 : 1);
		if ($age < (int) $modSettings['minimum_age'])
		{
			$reg_errors['invalid_dob'] = sprintf($txt['error_dob_not_old_enough'], $modSettings['minimum_age']);
		}
		else
		{
			$possible_strings[] = 'birthdate';
		}
	}

	// Validate the passed language file.
	if (isset($_POST['lngfile']) && !empty($modSettings['userLanguage']))
	{
		// Do we have any languages?
		if (empty($context['languages']))
			getLanguages();

		// Did we find it?
		if (isset($context['languages'][$_POST['lngfile']]))
			$_SESSION['language'] = $_POST['lngfile'];
		else
			unset($_POST['lngfile']);
	}
	else
		unset($_POST['lngfile']);

	// Set the options needed for registration.
	$regOptions = array(
		'interface' => 'guest',
		'username' => !empty($_POST['user']) ? $_POST['user'] : '',
		'email' => !empty($_POST['email']) ? $_POST['email'] : '',
		'password' => !empty($_POST['passwrd1']) ? $_POST['passwrd1'] : '',
		'password_check' => !empty($_POST['passwrd2']) ? $_POST['passwrd2'] : '',
		'check_reserved_name' => true,
		'check_password_strength' => true,
		'check_email_ban' => true,
		'send_welcome_email' => !empty($modSettings['send_welcomeEmail']),
		'require' => empty($modSettings['registration_method']) ? 'nothing' : ($modSettings['registration_method'] == 1 ? 'activation' : 'approval'),
		'extra_register_vars' => [],
		'theme_vars' => [],
		'timezone' => !empty($modSettings['default_timezone']) ? $modSettings['default_timezone'] : '',
		'reg_policies' => array_keys($policies),
	);

	// Include the additional options that might have been filled in.
	foreach ($possible_strings as $var)
		if (isset($_POST[$var]))
			$regOptions['extra_register_vars'][$var] = $smcFunc['htmlspecialchars']($_POST[$var], ENT_QUOTES);
	foreach ($possible_ints as $var)
		if (isset($_POST[$var]))
			$regOptions['extra_register_vars'][$var] = (int) $_POST[$var];
	foreach ($possible_floats as $var)
		if (isset($_POST[$var]))
			$regOptions['extra_register_vars'][$var] = (float) $_POST[$var];
	foreach ($possible_bools as $var)
		if (isset($_POST[$var]))
			$regOptions['extra_register_vars'][$var] = empty($_POST[$var]) ? 0 : 1;

	// Registration options are always default options...
	if (isset($_POST['default_options']))
		$_POST['options'] = isset($_POST['options']) ? $_POST['options'] + $_POST['default_options'] : $_POST['default_options'];
	$regOptions['theme_vars'] = isset($_POST['options']) && is_array($_POST['options']) ? $_POST['options'] : [];

	// Make sure they are clean, dammit!
	$regOptions['theme_vars'] = htmlspecialchars__recursive($regOptions['theme_vars']);

	// Check whether we have fields that simply MUST be displayed?
	$request = $smcFunc['db_query']('', '
		SELECT col_name, field_name, field_type, field_length, mask, show_reg
		FROM {db_prefix}custom_fields
		WHERE active = {int:is_active}
		ORDER BY field_order',
		array(
			'is_active' => 1,
		)
	);
	$custom_field_errors = [];
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Don't allow overriding of the theme variables.
		if (isset($regOptions['theme_vars'][$row['col_name']]))
			unset($regOptions['theme_vars'][$row['col_name']]);

		// Not actually showing it then?
		if (!$row['show_reg'])
			continue;

		// Prepare the value!
		$value = isset($_POST['customfield'][$row['col_name']]) ? trim($_POST['customfield'][$row['col_name']]) : '';

		// We only care for text fields as the others are valid to be empty.
		if (!in_array($row['field_type'], array('check', 'select', 'radio')))
		{
			// Is it too long?
			if ($row['field_length'] && $row['field_length'] < $smcFunc['strlen']($value))
				$custom_field_errors[] = array('custom_field_too_long', array($row['field_name'], $row['field_length']));

			// Any masks to apply?
			if ($row['field_type'] == 'text' && !empty($row['mask']) && $row['mask'] != 'none')
			{
				if ($row['mask'] == 'email' && (!filter_var($value, FILTER_VALIDATE_EMAIL) || strlen($value) > 255))
					$custom_field_errors[] = array('custom_field_invalid_email', array($row['field_name']));
				elseif ($row['mask'] == 'number' && preg_match('~[^\d]~', $value))
					$custom_field_errors[] = array('custom_field_not_number', array($row['field_name']));
				elseif (substr($row['mask'], 0, 5) == 'regex' && trim($value) != '' && preg_match(substr($row['mask'], 5), $value) === 0)
					$custom_field_errors[] = array('custom_field_inproper_format', array($row['field_name']));
			}
		}

		// Is this required but not there?
		if (trim($value) == '' && $row['show_reg'] > 1)
			$custom_field_errors[] = array('custom_field_empty', array($row['field_name']));
	}
	$smcFunc['db_free_result']($request);

	// Process any errors.
	if (!empty($custom_field_errors))
	{
		loadLanguage('Errors');
		foreach ($custom_field_errors as $error)
			$reg_errors[] = vsprintf($txt['error_' . $error[0]], $error[1]);
	}

	// Lets check for other errors before trying to register the member.
	if (!empty($reg_errors))
	{
		$_SESSION['register']['limit'] = 5; // If they've filled in some details, they won't need the full 10 seconds of the limit.
		return Register($reg_errors);
	}

	$memberID = registerMember($regOptions, true);

	// What there actually an error of some kind dear boy?
	if (is_array($memberID))
	{
		$reg_errors = array_merge($reg_errors, $memberID);
		return Register($reg_errors);
	}

	// Do our spam protection now.
	spamProtection('register');

	// Do they want to recieve announcements?
	require_once($sourcedir . '/Subs-Notify.php');
	$prefs = getNotifyPrefs($memberID, 'announcements', true);
	$var = !empty($_POST['notify_announcements']);
	$pref = !empty($prefs[$memberID]['announcements']);

	// Don't update if the default is the same.
	if ($var != $pref)
	{
		setNotifyPrefs($memberID, array('announcements' => (int) !empty($_POST['notify_announcements'])));
	}

	// We'll do custom fields after as then we get to use the helper function!
	if (!empty($_POST['customfield']))
	{
		require_once($sourcedir . '/Profile.php');
		require_once($sourcedir . '/Profile-Modify.php');
		makeCustomFieldChanges($memberID, 'register');
	}

	// Basic template variable setup.
	if (!empty($modSettings['registration_method']))
	{
		$context += array(
			'page_title' => $txt['register'],
			'title' => $txt['registration_successful'],
			'sub_template' => 'register_success',
			'description' => $modSettings['registration_method'] == 2 ? $txt['approval_after_registration'] : $txt['activate_after_registration']
		);
	}
	else
	{
		call_integration_hook('integrate_activate', array($regOptions['username']));

		setLoginCookie(0, $memberID, hash_salt($regOptions['register_vars']['passwd'], $regOptions['register_vars']['password_salt']));

		redirectexit('action=login2;sa=check;member=' . $memberID, $context['server']['needs_login_fix']);
	}
}

/**
 * Activate an users account.
 *
 * Checks for mail changes, resends password if needed.
 */
function Activate()
{
	global $context, $txt, $modSettings, $scripturl, $sourcedir, $smcFunc, $language, $user_info;

	// Logged in users should not bother to activate their accounts
	if (!empty($user_info['id']))
		redirectexit();

	loadLanguage('Login');

	if (empty($_REQUEST['u']) && empty($_POST['user']))
	{
		if (empty($modSettings['registration_method']) || $modSettings['registration_method'] == '3')
			fatal_lang_error('no_access', false);

		$context['member_id'] = 0;
		$context['sub_template'] = 'login_resend';
		$context['page_title'] = $txt['invalid_activation_resend'];
		$context['can_activate'] = empty($modSettings['registration_method']) || $modSettings['registration_method'] == '1';
		$context['default_username'] = isset($_GET['user']) ? $_GET['user'] : '';

		return;
	}

	// Get the code from the database...
	$request = $smcFunc['db_query']('', '
		SELECT id_member, validation_code, member_name, real_name, email_address, is_activated, passwd, lngfile
		FROM {db_prefix}members' . (empty($_REQUEST['u']) ? '
		WHERE member_name = {string:email_address} OR email_address = {string:email_address}' : '
		WHERE id_member = {int:id_member}') . '
		LIMIT 1',
		array(
			'id_member' => isset($_REQUEST['u']) ? (int) $_REQUEST['u'] : 0,
			'email_address' => isset($_POST['user']) ? $_POST['user'] : '',
		)
	);

	// Does this user exist at all?
	if ($smcFunc['db_num_rows']($request) == 0)
	{
		$context['sub_template'] = 'login_manual_activate';
		$context['page_title'] = $txt['invalid_userid'];
		$context['member_id'] = 0;

		return;
	}

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Change their email address? (they probably tried a fake one first :P.)
	if (isset($_POST['new_email'], $_REQUEST['passwd']) && hash_password($row['member_name'], $_REQUEST['passwd']) == $row['passwd'] && ($row['is_activated'] == 0 || $row['is_activated'] == 2))
	{
		if (empty($modSettings['registration_method']) || $modSettings['registration_method'] == 3)
			fatal_lang_error('no_access', false);

		if (!filter_var($_POST['new_email'], FILTER_VALIDATE_EMAIL))
			fatal_error(sprintf($txt['valid_email_needed'], $smcFunc['htmlspecialchars']($_POST['new_email'])), false);

		// Make sure their email isn't banned.
		isBannedEmail($_POST['new_email'], 'cannot_register', $txt['ban_register_prohibited']);

		// Ummm... don't even dare try to take someone else's email!!
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE email_address = {string:email_address}
			LIMIT 1',
			array(
				'email_address' => $_POST['new_email'],
			)
		);

		if ($smcFunc['db_num_rows']($request) != 0)
			fatal_lang_error('email_in_use', false, array($smcFunc['htmlspecialchars']($_POST['new_email'])));
		$smcFunc['db_free_result']($request);

		updateMemberData($row['id_member'], array('email_address' => $_POST['new_email']));
		$row['email_address'] = $_POST['new_email'];

		$email_change = true;
	}

	// Resend the password, but only if the account wasn't activated yet.
	if (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'resend' && ($row['is_activated'] == 0 || $row['is_activated'] == 2) && (!isset($_REQUEST['code']) || $_REQUEST['code'] == ''))
	{
		require_once($sourcedir . '/Subs-Post.php');

		$replacements = array(
			'REALNAME' => $row['real_name'],
			'USERNAME' => $row['member_name'],
			'ACTIVATIONLINK' => $scripturl . '?action=activate;u=' . $row['id_member'] . ';code=' . $row['validation_code'],
			'ACTIVATIONLINKWITHOUTCODE' => $scripturl . '?action=activate;u=' . $row['id_member'],
			'ACTIVATIONCODE' => $row['validation_code'],
			'FORGOTPASSWORDLINK' => $scripturl . '?action=reminder',
		);

		$emaildata = loadEmailTemplate('resend_activate_message', $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

		StoryBB\Helper\Mail::send($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'resendact', $emaildata['is_html'], 0);

		$context['page_title'] = $txt['invalid_activation_resend'];

		// This will ensure we don't actually get an error message if it works!
		$context['error_title'] = '';

		fatal_lang_error(!empty($email_change) ? 'change_email_success' : 'resend_email_success', false);
	}

	// Quit if this code is not right.
	if (empty($_REQUEST['code']) || $row['validation_code'] != $_REQUEST['code'])
	{
		if (!empty($row['is_activated']))
			fatal_lang_error('already_activated', false);
		elseif ($row['validation_code'] == '')
		{
			loadLanguage('Profile');
			fatal_error(sprintf($txt['registration_not_approved'], $scripturl . '?action=activate;user=' . $row['member_name']), false);
		}

		$context['sub_template'] = 'login_manual_activate';
		$context['page_title'] = $txt['invalid_activation_code'];
		$context['member_id'] = $row['id_member'];

		return;
	}

	// Let the integration know that they've been activated!
	call_integration_hook('integrate_activate', array($row['member_name']));

	// Validation complete - update the database!
	updateMemberData($row['id_member'], array('is_activated' => 1, 'validation_code' => ''));

	// Also do a proper member stat re-evaluation.
	updateStats('member', false);

	if (!isset($_POST['new_email']))
	{
		require_once($sourcedir . '/Subs-Post.php');

		adminNotify('activation', $row['id_member'], $row['member_name']);
	}

	$context += array(
		'page_title' => $txt['registration_successful'],
		'sub_template' => 'login_main',
		'default_username' => $row['member_name'],
		'default_password' => '',
		'never_expire' => false,
		'description' => $txt['activate_success']
	);
}

/**
 * Show the verification code or let it be heard.
 */
function VerificationCode()
{
	global $sourcedir, $context, $scripturl;

	$verification_id = isset($_GET['vid']) ? $_GET['vid'] : '';
	$code = $verification_id && isset($_SESSION[$verification_id . '_vv']) ? $_SESSION[$verification_id . '_vv']['code'] : (isset($_SESSION['visual_verification_code']) ? $_SESSION['visual_verification_code'] : '');

	// Somehow no code was generated or the session was lost.
	if (empty($code))
	{
		header('Content-Type: image/gif');
		die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
	}

	// Show a window that will play the verification code.
	elseif (isset($_REQUEST['sound']))
	{
		loadLanguage('Login');

		$context['verification_sound_href'] = $scripturl . '?action=verificationcode;rand=' . md5(mt_rand()) . ($verification_id ? ';vid=' . $verification_id : '') . ';format=.wav';
		$context['sub_template'] = 'register_sound_verification';
		StoryBB\Template::add_helper(['isBrowser' => 'isBrowser']);
		$context['popup_id'] = 'sound_verification';
		StoryBB\Template::set_layout('popup');

		obExit();
	}

	// If we have GD, try the nice code.
	elseif (empty($_REQUEST['format']))
	{
		require_once($sourcedir . '/Subs-Graphics.php');

		if (in_array('gd', get_loaded_extensions()) && !showCodeImage($code))
			header('HTTP/1.1 400 Bad Request');

		// Otherwise just show a pre-defined letter.
		elseif (isset($_REQUEST['letter']))
		{
			$_REQUEST['letter'] = (int) $_REQUEST['letter'];
			if ($_REQUEST['letter'] > 0 && $_REQUEST['letter'] <= strlen($code) && !showLetterImage(strtoupper($code{$_REQUEST['letter'] - 1})))
			{
				header('Content-Type: image/gif');
				die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
			}
		}
		// You must be up to no good.
		else
		{
			header('Content-Type: image/gif');
			die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
		}
	}

	elseif ($_REQUEST['format'] === '.wav')
	{
		Wave::create($code);
	}

	// We all die one day...
	die();
}

/**
 * See if a username already exists.
 */
function RegisterCheckUsername()
{
	global $sourcedir, $context;

	// This is XML!
	StoryBB\Template::set_layout('xml');
	$context['sub_template'] = 'xml_check_username';
	$context['checked_username'] = isset($_GET['username']) ? un_htmlspecialchars($_GET['username']) : '';
	$context['valid_username'] = true;
	StoryBB\Template::add_helper([
		'cleanXml' => 'cleanXml'
	]);

	// Clean it up like mother would.
	$context['checked_username'] = preg_replace('~[\t\n\r \x0B\0\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}]+~u', ' ', $context['checked_username']);

	require_once($sourcedir . '/Subs-Auth.php');
	$errors = validateUsername(0, $context['checked_username'], true);

	$context['valid_username'] = empty($errors);
}

/**
 * It doesn't actually send anything, this action just shows a message for a guest.
 *
 */
function SendActivation()
{
	global $context, $txt;

	$context['user']['is_logged'] = false;
	$context['user']['is_guest'] = true;

	$context['page_title'] = $txt['profile'];
	$context['sub_template'] = 'register_success';
	$context['title'] = $txt['activate_changed_email_title'];
	$context['description'] = $txt['activate_changed_email_desc'];

	// We're gone!
	obExit();
}
