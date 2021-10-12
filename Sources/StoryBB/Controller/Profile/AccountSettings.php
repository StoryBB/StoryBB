<?php

/**
 * Displays the account settings page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

class AccountSettings extends AbstractProfileController
{
	use ProfileSettingsTrait;

	protected function get_token_name()
	{
		return str_replace('%u', $this->params['u'], 'profile-ac%u');
	}

	public function display_action()
	{
		global $context, $txt, $sourcedir, $scripturl, $modSettings;

		$memID = $this->params['u'];

		createToken($this->get_token_name());
		$context['token_check'] = $this->get_token_name();

		require_once($sourcedir . '/Profile-Modify.php');

		loadThemeOptions($memID);
		if (allowedTo(['profile_identity_own', 'profile_identity_any', 'profile_password_own', 'profile_password_any']))
			loadCustomFields($memID, 'account');

		$context['sub_template'] = 'profile_options';
		$context['page_desc'] = $txt['account_info'];

		setupProfileContext(
			[
				'member_name', 'real_name', 'date_registered', 'posts', 'lngfile', 'hr',
				'id_group', 'hr',
				'email_address', 'show_online', 'hr',
				'birthday_date', 'birthday_visibility', 'hr',
				'passwrd1', 'passwrd2', 'hr',
				'secret_question', 'secret_answer',
			]
		);

		$context['require_password'] = true;

		$context['profile_submit_url'] = $scripturl . '?action=profile;area=account_settings;u=' . $memID;
		$context['profile_submit_url'] = !empty($modSettings['force_ssl']) && $modSettings['force_ssl'] < 2 ? strtr($context['profile_submit_url'], ['http://' => 'https://']) : $context['profile_submit_url'];
	}

	public function post_action()
	{
		global $context, $txt, $sourcedir, $modSettings, $post_errors, $profile_vars, $user_info, $cur_profile;

		$memID = $this->params['u'];

		validateToken($this->get_token_name());

		require_once($sourcedir . '/Profile-Modify.php');

		$post_errors = [];
		$profile_vars = [];

		// Clean up the POST variables.
		$this->sanitize_post();

		// Account Settings will want to check the password if it's the owner changing it.
		if ($context['user']['is_owner'])
		{
			$this->validate_password();
		}

		// Change the IP address in the database.
		if ($context['user']['is_owner'])
			$profile_vars['member_ip'] = $user_info['ip'];

		saveProfileFields('account');

		call_integration_hook('integrate_profile_save', [&$profile_vars, &$post_errors, $memID, $cur_profile, 'account']);

		// There was a problem, let them try to re-enter.
		if (!empty($post_errors))
		{
			// Load the language file so we can give a nice explanation of the errors.
			loadLanguage('Errors');
			$context['post_errors'] = $post_errors;
		}
		elseif (!empty($profile_vars))
		{
			// If we've changed the password, notify any integration that may be listening in.
			$this->handle_changed_password();

			// Changing the avatar is not necessarily obvious.
			$this->handle_changed_avatar();

			// Change the rest of the variables.
			updateMemberData($memID, $profile_vars);

			$this->update_latest_member();

			// Anything worth logging?
			$this->log_changes();

			// Have we got any post save functions to execute?
			if (!empty($context['profile_execute_on_save']))
				foreach ($context['profile_execute_on_save'] as $saveFunc)
					$saveFunc();

			// Let them know it worked!
			session_flash('success', $context['user']['is_owner'] ? $txt['profile_updated_own'] : sprintf($txt['profile_updated_else'], $cur_profile['member_name']));

			// Invalidate any cached data.
			cache_put_data('member_data-profile-' . $memID, null, 0);

			redirectexit('action=profile;area=account_settings;u=' . $memID);
		}

		// Have some errors for some reason?
		if (!empty($post_errors))
		{
			// Set all the errors so the template knows what went wrong.
			foreach ($post_errors as $error_type)
				$context['modify_error'][$error_type] = true;
		}

		return $this->display_action();
	}
}
