<?php

/**
 * Helper for profile page settings.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Hook\Observable;

trait ProfileSettingsTrait
{
	protected function sanitize_post()
	{
		$old = $_POST;

		$_POST = htmltrim__recursive($_POST);
		$_POST = htmlspecialchars__recursive($_POST);

		// The password fields shouldn't be adjusted because this isn't a vector for XSS - if people want to use HTML in their password, that's _fine_.
		foreach (['passwrd1', 'passwrd2'] as $field)
		{
			if (isset($old[$field]))
			{
				$_POST[$field] = $old[$field];
			}
		}
	}
	protected function validate_password()
	{
		global $modSettings, $maintenance, $post_errors, $context, $sourcedir, $cur_profile, $user_info, $user_profile;

		$memID = $this->params['u'];

		// Check to ensure we're forcing SSL for authentication
		if (!empty($modSettings['force_ssl']) && empty($maintenance) && !httpsOn())
			fatal_lang_error('login_ssl_required');

		// You didn't even enter a password!
		if (trim($_POST['oldpasswrd']) == '')
			$post_errors[] = 'no_password';

		// Since the password got modified due to all the $_POST cleaning, lets undo it so we can get the correct password
		$_POST['oldpasswrd'] = un_htmlspecialchars($_POST['oldpasswrd']);

		// Does the integration want to check passwords?
		$good_password = in_array(true, call_integration_hook('integrate_verify_password', [$cur_profile['member_name'], $_POST['oldpasswrd'], false]), true);

		// Bad password!!!
		require_once($sourcedir . '/Subs-Auth.php');
		if (!$good_password && !hash_verify_password($user_profile[$memID]['member_name'], un_htmlspecialchars(stripslashes($_POST['oldpasswrd'])), $user_info['passwd']))
			$post_errors[] = 'bad_password';

		// Warn other elements not to jump the gun and do custom changes!
		if (in_array('bad_password', $post_errors))
			$context['password_auth_failed'] = true;
	}

	protected function log_changes()
	{
		global $context, $modSettings, $sourcedir, $user_info;

		$memID = $this->params['u'];

		if (!empty($context['log_changes']) && !empty($modSettings['modlog_enabled']))
		{
			$log_changes = [];
			require_once($sourcedir . '/Logging.php');
			foreach ($context['log_changes'] as $k => $v)
				$log_changes[] = [
					'action' => $k,
					'log_type' => 'user',
					'extra' => array_merge($v, [
						'applicator' => $user_info['id'],
						'member_affected' => $memID,
					]),
				];

			logActions($log_changes);
		}
	}

	protected function handle_changed_password()
	{
		global $cur_profile, $profile_vars;

		if (isset($profile_vars['passwd']))
		{
			(new Observable\Account\PasswordReset($cur_profile['member_name'], $cur_profile['member_name'], $_POST['passwrd2']))->execute();
		}
	}

	protected function handle_changed_avatar()
	{
		global $profile_vars, $context;

		if (isset($profile_vars['avatar'])) {
			if (!isset($context['character']['id_character'])) {
				foreach ($context['member']['characters'] as $id_char => $char) {
					if ($char['is_main'])
					{
						$context['character']['id_character'] = $id_char;
						break;
					}
				}
			}
			if (!empty($context['character']['id_character']))
				updateCharacterData($context['character']['id_character'], ['avatar' => $profile_vars['avatar']]);

			unset ($profile_vars['avatar']);
		}
	}

	protected function update_latest_member()
	{
		global $modSettings, $profile_vars;

		// What if this is the newest member?
		if ($modSettings['latestMember'] == $this->params['u'])
			updateStats('member');
		elseif (isset($profile_vars['real_name']))
			updateSettings(['memberlist_updated' => time()]);
	}
}
