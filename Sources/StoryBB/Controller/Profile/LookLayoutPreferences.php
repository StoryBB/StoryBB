<?php

/**
 * Displays the look and layout preferences page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Container;

class LookLayoutPreferences extends AbstractProfileController
{
	use ProfileSettingsTrait;

	protected function get_token_name()
	{
		return str_replace('%u', $this->params['u'], 'profile-th%u');
	}

	public function display_action()
	{
		global $context, $txt, $sourcedir, $scripturl, $modSettings;

		$memID = $this->params['u'];

		createToken($this->get_token_name());
		$context['token_check'] = $this->get_token_name();

		loadLanguage('Drafts');

		$container = Container::instance();
		$prefs_manager = $container->instantiate('StoryBB\\User\\PreferenceManager');
		$context['theme_options'] = $prefs_manager->get_default_preferences();

		require_once($sourcedir . '/Profile-Modify.php');

		loadThemeOptions($memID);

		$context['sub_template'] = 'profile_options';
		$context['page_desc'] = $txt['theme_info'];

		if (allowedTo(['profile_forum_own', 'profile_forum_any']))
			loadCustomFields($memID, 'prefs');

		// Do some fix-ups to keep the template a bit simpler.
		foreach ($context['theme_options'] as $id => $setting)
		{
			if (!is_array($setting))
			{
				$context['theme_options'][$id] = $txt[$setting];
				continue;
			}

			$context['theme_options'][$id]['label'] = $txt[$setting['label']];

			// If it's going to be disabled through a modSettings entry, do that first.
			if (!empty($setting['disableOn']) && !empty($modSettings[$setting['disableOn']])) {
				unset ($context['theme_options'][$id]);
				continue;
			}

			// Make sure there's a type given, or create the more canonical names we want to use here.
			if (!isset($setting['type']) || $setting['type'] == 'bool')
				$context['theme_options'][$id]['type'] = 'checkbox';
			elseif ($setting['type'] == 'int' || $setting['type'] == 'integer')
				$context['theme_options'][$id]['type'] = 'number';
			elseif ($setting['type'] == 'string')
				$context['theme_options'][$id]['type'] = 'text';

			if (isset($setting['options']))
			{
				$context['theme_options'][$id]['type'] = 'list';
				foreach ($setting['options'] as $opt_key => $opt_val)
				{
					if (is_string($opt_val))
					{
						$context['theme_options'][$id]['options'][$opt_key] = isset($txt[$opt_val]) ? $txt[$opt_val] : $opt_val;
					}
				}
			}

			// Make the value more readily available to the template.
			$context['theme_options'][$id]['user_value'] = '';
			if (isset($context['member']['options'][$setting['id']]))
				$context['theme_options'][$id]['user_value'] = $context['member']['options'][$setting['id']];
		}

		setupProfileContext(
			[
				'time_format', 'timezone',
				'immersive_mode',
				'theme_settings',
			]
		);

		$context['profile_submit_url'] = $scripturl . '?action=profile;area=preferences;u=' . $memID;
		$context['profile_submit_url'] = !empty($modSettings['force_ssl']) && $modSettings['force_ssl'] < 2 ? strtr($context['profile_submit_url'], ['http://' => 'https://']) : $context['profile_submit_url'];
	}

	public function post_action()
	{
		global $context, $txt, $sourcedir, $modSettings, $maintenance, $post_errors, $profile_vars, $user_info, $cur_profile;

		$memID = $this->params['u'];

		validateToken($this->get_token_name());

		require_once($sourcedir . '/Profile-Modify.php');

		$post_errors = [];
		$profile_vars = [];

		// Clean up the POST variables.
		$this->sanitize_post();

		// Change the IP address in the database.
		if ($context['user']['is_owner'])
			$profile_vars['member_ip'] = $user_info['ip'];

		saveProfileFields('prefs');

		call_integration_hook('integrate_profile_save', [&$profile_vars, &$post_errors, $memID, $cur_profile, 'prefs']);

		// There was a problem, let them try to re-enter.
		if (!empty($post_errors))
		{
			// Load the language file so we can give a nice explanation of the errors.
			loadLanguage('Errors');
			$context['post_errors'] = $post_errors;
		}
		elseif (!empty($profile_vars))
		{
			// Changing the avatar is not necessarily obvious.
			$this->handle_changed_avatar();

			// Change the rest of the variables.
			updateMemberData($memID, $profile_vars);

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
		}

		// Have some errors for some reason?
		if (!empty($post_errors))
		{
			// Set all the errors so the template knows what went wrong.
			foreach ($post_errors as $error_type)
				$context['modify_error'][$error_type] = true;
		}
		// If it's you then we should redirect upon save.
		elseif (!empty($profile_vars) && $context['user']['is_owner'])
		{
			session_flash('success', $txt['profile_updated_own']);
			redirectexit('action=profile;area=preferences');
		}

		return $this->display_action();
	}
}
