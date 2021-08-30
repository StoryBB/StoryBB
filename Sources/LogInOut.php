<?php

/**
 * This file is concerned pretty entirely, as you see from its name, with
 * logging in and out members, and the validation of that.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Container;
use StoryBB\Hook\Observable;
use StoryBB\StringLibrary;

/**
 * Actually logs you in.
 * What it does:
 * - checks credentials and checks that login was successful.
 * - it employs protection against a specific IP or user trying to brute force
 *  a login to an account.
 * - upgrades password encryption on login, if necessary.
 * - after successful login, redirects you to $_SESSION['login_url'].
 * - accessed from ?action=login2, by forms.
 * On error, uses the same templates Login() uses.
 */
function Login2()
{
	global $txt, $scripturl, $user_info, $user_settings, $smcFunc;
	global $cookiename, $modSettings, $context, $sourcedir, $maintenance;


	// Are you guessing with a script?
	spamProtection('login');

	// Set the login_url if it's not already set (but careful not to send us to an attachment).
	if ((empty($_SESSION['login_url']) && isset($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'dlattach') === false && preg_match('~(board|topic)[=,]~', $_SESSION['old_url']) != 0) || (isset($_GET['quicklogin']) && isset($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'login') === false))
		$_SESSION['login_url'] = $_SESSION['old_url'];

	// Been guessing a lot, haven't we?
	if (isset($_SESSION['failed_login']) && $_SESSION['failed_login'] >= $modSettings['failed_login_threshold'] * 3)
		fatal_lang_error('login_threshold_fail', 'login');



	loadLanguage('Login');
	$context['sub_template'] = 'login_main';

	// Set up the default/fallback stuff.
	$context['default_username'] = isset($_POST['user']) ? preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', StringLibrary::escape($_POST['user'])) : '';
	$context['default_password'] = '';
	$context['login_errors'] = [$txt['error_occured']];
	$context['page_title'] = $txt['login'];

	// Add the login chain to the link tree.
	$context['linktree'][] = [
		'url' => $scripturl . '?action=login',
		'name' => $txt['login'],
	];



	// Are we using any sort of integration to validate the login?
	if (in_array('retry', call_integration_hook('integrate_validate_login', [$_POST['user'], isset($_POST['passwrd']) ? $_POST['passwrd'] : null, $context['cookie_time']]), true))
	{
		$context['login_errors'] = [$txt['incorrect_password']];
		return;
	}

	// Load the data up!
	$request = $smcFunc['db']->query('', '
		SELECT passwd, id_member, id_group, lngfile, is_activated, email_address, additional_groups, member_name, password_salt,
			passwd_flood
		FROM {db_prefix}members
		WHERE ' . ($smcFunc['db']->is_case_sensitive() ? 'LOWER(member_name) = LOWER({string:user_name})' : 'member_name = {string:user_name}') . '
		LIMIT 1',
		[
			'user_name' => $smcFunc['db']->is_case_sensitive() ? strtolower($_POST['user']) : $_POST['user'],
		]
	);
	// Probably mistyped or their email, try it as an email address. (member_name first, though!)
	if ($smcFunc['db']->num_rows($request) == 0 && strpos($_POST['user'], '@') !== false)
	{
		$smcFunc['db']->free_result($request);

		$request = $smcFunc['db']->query('', '
			SELECT passwd, id_member, id_group, lngfile, is_activated, email_address, additional_groups, member_name, password_salt,
			passwd_flood
			FROM {db_prefix}members
			WHERE email_address = {string:user_name}
			LIMIT 1',
			[
				'user_name' => $_POST['user'],
			]
		);
	}


	$user_settings = $smcFunc['db']->fetch_assoc($request);
	$smcFunc['db']->free_result($request);

	// Bad password!  Thought you could fool the database?!
	if (!hash_verify_password($user_settings['member_name'], un_htmlspecialchars($_POST['passwrd']), $user_settings['passwd']))
	{
		// Let's be cautious, no hacking please. thanx.
		validatePasswordFlood($user_settings['id_member'], $user_settings['member_name'], $user_settings['passwd_flood']);

		// They've messed up again - keep a count to see if they need a hand.
		$_SESSION['failed_login'] = isset($_SESSION['failed_login']) ? ($_SESSION['failed_login'] + 1) : 1;

		// Hmm... don't remember it, do you?  Here, try the password reminder ;).
		if ($_SESSION['failed_login'] >= $modSettings['failed_login_threshold'])
			redirectexit('action=reminder');
		// We'll give you another chance...
		else
		{
			// Log an error so we know that it didn't go well in the error log.
			log_error($txt['incorrect_password'] . ' - <span class="remove">' . $user_settings['member_name'] . '</span>', 'user');

			$context['login_errors'] = [$txt['incorrect_password']];
			return;
		}
	}
	elseif (!empty($user_settings['passwd_flood']))
	{
		// Let's be sure they weren't a little hacker.
		validatePasswordFlood($user_settings['id_member'], $user_settings['member_name'], $user_settings['passwd_flood'], true);

		// If we got here then we can reset the flood counter.
		updateMemberData($user_settings['id_member'], ['passwd_flood' => '']);
	}

	// Correct password, but they've got no salt; fix it!
	if ($user_settings['password_salt'] == '')
	{
		$user_settings['password_salt'] = substr(md5(mt_rand()), 0, 4);
		updateMemberData($user_settings['id_member'], ['password_salt' => $user_settings['password_salt']]);
	}

	// Check their activation status.
	if (!checkActivation())
		return;

	DoLogin();
}

/**
 * Check activation status of the current user.
 */
function checkActivation()
{
	global $context, $txt, $scripturl, $user_settings, $modSettings;

	if (!isset($context['login_errors']))
		$context['login_errors'] = [];

	// What is the true activation status of this account?
	$activation_status = $user_settings['is_activated'] > 10 ? $user_settings['is_activated'] - 10 : $user_settings['is_activated'];

	// Awaiting approval still?
	if ($activation_status == 3)
		fatal_lang_error('still_awaiting_approval', 'user');
	// Awaiting deletion, changed their mind?
	elseif ($activation_status == 4)
	{
		if (isset($_REQUEST['undelete']))
		{
			updateMemberData($user_settings['id_member'], ['is_activated' => 1]);
			updateSettings(['unapprovedMembers' => ($modSettings['unapprovedMembers'] > 0 ? $modSettings['unapprovedMembers'] - 1 : 0)]);
		}
		else
		{
			$context['login_errors'][] = $txt['awaiting_delete_account'];
			$context['login_show_undelete'] = true;
			return false;
		}
	}
	// Standard activation?
	elseif ($activation_status != 1)
	{
		log_error($txt['activate_not_completed1'] . ' - <span class="remove">' . $user_settings['member_name'] . '</span>', false);

		$context['login_errors'][] = $txt['activate_not_completed1'] . ' <a href="' . $scripturl . '?action=activate;sa=resend;u=' . $user_settings['id_member'] . '">' . $txt['activate_not_completed2'] . '</a>';
		return false;
	}
	return true;
}

/**
 * Perform the logging in. (set cookie, call hooks, etc)
 */
function DoLogin()
{
	global $user_info, $user_settings, $smcFunc;
	global $maintenance, $modSettings, $context, $sourcedir;

	// Load cookie authentication stuff.
	require_once($sourcedir . '/Subs-Auth.php');

	// Call login integration functions.
	(new Observable\Account\LoggedIn($user_settings['member_name'], $user_settings['id_member'], $context['cookie_time']))->execute();

	// Get ready to set the cookie...
	$user_info['id'] = $user_settings['id_member'];

	// Bam!  Cookie set.  A session too, just in case.


	// Reset the login threshold.
	if (isset($_SESSION['failed_login']))
		unset($_SESSION['failed_login']);

	$user_info['is_guest'] = false;
	$user_settings['additional_groups'] = explode(',', $user_settings['additional_groups']);
	$user_info['is_admin'] = $user_settings['id_group'] == 1 || in_array(1, $user_settings['additional_groups']);

	// Are you banned?
	is_not_banned(true);

	// Don't stick the language or theme after this point.
	unset($_SESSION['language'], $_SESSION['id_theme']);

	// You've logged in, haven't you?
	$update = ['member_ip' => $user_info['ip'], 'member_ip2' => $_SERVER['BAN_CHECK_IP'] ?? $user_info['ip']];
	$update['last_login'] = time();
	updateMemberData($user_info['id'], $update);

	// Get rid of the online entry for that old guest....
	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}log_online
		WHERE session = {string:session}',
		[
			'session' => 'ip' . $user_info['ip'],
		]
	);
	$_SESSION['log_time'] = 0;

	// Log this entry, only if we have it enabled.
	if (!empty($modSettings['loginHistoryDays']))
		$smcFunc['db']->insert('insert',
			'{db_prefix}member_logins',
			[
				'id_member' => 'int', 'time' => 'int', 'ip' => 'inet', 'ip2' => 'inet',
			],
			[
				$user_info['id'], time(), $user_info['ip'], $user_info['ip2']
			],
			[
				'id_member', 'time'
			]
		);

	$container = \StoryBB\Container::instance();
	$urlgenerator = $container->get('urlgenerator');
	$urlgenerator->generate('logout', ['t' => $container->get('session')->get('session_value')]);

	// Just log you back out if it's in maintenance mode and you AREN'T an admin.
	if (empty($maintenance) || allowedTo('admin_forum'))
		redirectexit('action=login2;sa=check;member=' . $user_info['id']);
	else
		redirectexit($urlgenerator->generate('logout', ['t' => $container->get('session')->get('session_value')]));
}

/**
 * This protects against brute force attacks on a member's password.
 * Importantly, even if the password was right we DON'T TELL THEM!
 *
 * @param int $id_member The ID of the member
 * @param string $member_name The name of the member.
 * @param bool|string $password_flood_value False if we don't have a flood value, otherwise a string with a timestamp and number of tries separated by a |
 * @param bool $was_correct Whether or not the password was correct
 */
function validatePasswordFlood($id_member, $member_name, $password_flood_value = false, $was_correct = false)
{
	global $cookiename, $sourcedir;

	// As this is only brute protection, we allow 5 attempts every 10 seconds.

	// Destroy any session or cookie data about this member, as they validated wrong.
	$container = Container::instance();
	$session = $container->get('session');
	$session->invalidate(3600);
	$session->clear('userid');

	if (isset($_SESSION['login_' . $cookiename]))
		unset($_SESSION['login_' . $cookiename]);

	// We need a member!
	if (!$id_member)
	{
		// Redirect back!
		redirectexit();

		// Probably not needed, but still make sure...
		fatal_lang_error('no_access', false);
	}

	// Right, have we got a flood value?
	if ($password_flood_value !== false)
		@list ($time_stamp, $number_tries) = explode('|', $password_flood_value);

	// Timestamp or number of tries invalid?
	if (empty($number_tries) || empty($time_stamp))
	{
		$number_tries = 0;
		$time_stamp = time();
	}

	// They've failed logging in already
	if (!empty($number_tries))
	{
		// Give them less chances if they failed before
		$number_tries = $time_stamp < time() - 20 ? 2 : $number_tries;

		// They are trying too fast, make them wait longer
		if ($time_stamp < time() - 10)
			$time_stamp = time();
	}

	$number_tries++;

	// Broken the law?
	if ($number_tries > 5)
		fatal_lang_error('login_threshold_brute_fail', 'login', [$member_name]);

	// Otherwise set the members data. If they correct on their first attempt then we actually clear it, otherwise we set it!
	updateMemberData($id_member, ['passwd_flood' => $was_correct && $number_tries == 1 ? '' : $time_stamp . '|' . $number_tries]);

}
