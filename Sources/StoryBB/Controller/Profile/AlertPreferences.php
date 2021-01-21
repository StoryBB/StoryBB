<?php

/**
 * Displays the alerts popup page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Model\Alert;

class AlertPreferences extends AbstractProfileController
{
	protected function get_token_name()
	{
		return str_replace('%u', $this->params['u'], 'profile-nt%u');
	}

	public function display_action()
	{
		global $txt, $context, $modSettings, $smcFunc, $sourcedir;

		loadCSSFile('admin.css', [], 'sbb_admin');
		loadJavaScriptFile('alertSettings.js', [], 'sbb_alertSettings');

		$memID = $this->params['u'];

		$context['action'] = 'action=profile;area=alert_preferences;u=' . $memID;

		$context['token_check'] = $this->get_token_name();
		createToken($context['token_check']);

		$this->load_alert_preferences();

		// Now we need to set up for the template.
		$context['alert_groups'] = [];
		foreach ($context['alert_types'] as $id => $group)
		{
			$context['alert_groups'][$id] = [
				'title' => $txt['alert_group_' . $id],
				'group_config' => [],
				'options' => [],
			];

			// If this group of settings has its own section-specific settings, expose them to the template.
			if (!empty($context['alert_group_options'][$id]))
			{
				$context['alert_groups'][$id]['group_config'] = $context['alert_group_options'][$id];
				foreach ($context['alert_group_options'][$id] as $pos => $opts) {
					// Make the label easy to deal with.
					$context['alert_groups'][$id]['group_config'][$pos]['label'] = $txt['alert_opt_' . $opts[1]];

					// Make sure we have a label position that is sane.
					if (empty($opts['position']) || !in_array($opts['position'], ['before', 'after'])) {
						$context['alert_groups'][$id]['group_config'][$pos]['position'] = 'before';
					}

					// Export the value cleanly.
					$context['alert_groups'][$id]['group_config'][$pos]['value'] = 0;
					if (isset($context['alert_prefs'][$opts[1]]))
						$context['alert_groups'][$id]['group_config'][$pos]['value'] = $context['alert_prefs'][$opts[1]];
				}
			}

			// Fix up the options in this group.
			foreach ($group as $alert_id => $alert_option)
			{
				$alert_option['label'] = $txt['alert_' . $alert_id];
				foreach ($context['alert_bits'] as $alert_type => $bitmask)
				{
					if ($alert_option[$alert_type] == 'yes')
					{
						$this_value = isset($context['alert_prefs'][$alert_id]) ? $context['alert_prefs'][$alert_id] : 0;
						$alert_option[$alert_type] = $this_value & $bitmask ? 'yes' : 'no';
					}
				}
				$context['alert_groups'][$id]['options'][$alert_id] = $alert_option;
			}
		}

		$context['sub_template'] = 'profile_alert_configuration';
	}

	protected function load_alert_preferences()
	{
		global $txt, $context, $modSettings, $smcFunc, $sourcedir;

		$memID = $this->params['u'];

		require_once($sourcedir . '/Profile-Modify.php');

		// What options are set
		loadThemeOptions($memID);

		// Now load all the values for this user.
		require_once($sourcedir . '/Subs-Notify.php');
		$prefs = getNotifyPrefs($memID, '', true);

		$context['alert_prefs'] = !empty($prefs[$memID]) ? $prefs[$memID] : [];

		$context['member'] += [
			'alert_timeout' => isset($context['alert_prefs']['alert_timeout']) ? $context['alert_prefs']['alert_timeout'] : 10,
		];

		[$alert_types, $group_options] = Alert::alert_configuration();

		// Now we have to do some permissions testing.
		require_once($sourcedir . '/Subs-Members.php');
		$perms_cache = [];
		$request = $smcFunc['db']->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}group_moderators
			WHERE id_member = {int:memID}',
			[
				'memID' => $memID,
			]
		);

		list ($can_mod) = $smcFunc['db']->fetch_row($request);

		if (!isset($perms_cache['manage_membergroups']))
		{
			$members = membersAllowedTo('manage_membergroups');
			$perms_cache['manage_membergroups'] = in_array($memID, $members);
		}

		if (!($perms_cache['manage_membergroups'] || $can_mod != 0))
			unset($alert_types['members']['request_group']);

		foreach ($alert_types as $group => $items)
		{
			foreach ($items as $alert_key => $alert_value)
			{
				if (!isset($alert_value['permission']))
					continue;
				if (!isset($perms_cache[$alert_value['permission']['name']]))
				{
					$in_board = !empty($alert_value['permission']['is_board']) ? 0 : null;
					$members = membersAllowedTo($alert_value['permission']['name'], $in_board);
					$perms_cache[$alert_value['permission']['name']] = in_array($memID, $members);
				}

				if (!$perms_cache[$alert_value['permission']['name']])
					unset ($alert_types[$group][$alert_key]);
			}

			if (empty($alert_types[$group]))
				unset ($alert_types[$group]);
		}

		// And finally, exporting it to be useful later.
		$context['alert_types'] = $alert_types;
		$context['alert_group_options'] = $group_options;

		$context['alert_bits'] = [
			'alert' => 0x01,
			'email' => 0x02,
		];
	}

	public function post_action()
	{
		global $context, $txt;

		$memID = $this->params['u'];

		$this->load_alert_preferences();

		if (isset($_POST['notify_submit']))
		{
			validateToken($this->get_token_name(), 'post');

			// We need to step through the list of valid settings and figure out what the user has set.
			$update_prefs = [];

			// Now the group level options
			foreach ($context['alert_group_options'] as $group)
			{
				foreach ($group as $this_option)
				{
					switch ($this_option[0])
					{
						case 'check':
							$update_prefs[$this_option[1]] = !empty($_POST['opt_' . $this_option[1]]) ? 1 : 0;
							break;
						case 'select':
							if (isset($_POST['opt_' . $this_option[1]], $this_option['opts'][$_POST['opt_' . $this_option[1]]]))
								$update_prefs[$this_option[1]] = $_POST['opt_' . $this_option[1]];
							else
							{
								// We didn't have a sane value. Let's grab the first item from the possibles.
								$keys = array_keys($this_option['opts']);
								$first = array_shift($keys);
								$update_prefs[$this_option[1]] = $first;
							}
							break;
					}
				}
			}

			// Now the individual options
			foreach ($context['alert_types'] as $items)
			{
				foreach ($items as $item_key => $this_options)
				{
					$this_value = 0;
					foreach ($context['alert_bits'] as $type => $bitvalue)
					{
						if ($this_options[$type] == 'yes' && !empty($_POST[$type . '_' . $item_key]) || $this_options[$type] == 'always')
							$this_value |= $bitvalue;
					}
					if (!isset($context['alert_prefs'][$item_key]) || $context['alert_prefs'][$item_key] != $this_value)
						$update_prefs[$item_key] = $this_value;
				}
			}

			if (!empty($_POST['opt_alert_timeout']))
				$update_prefs['alert_timeout'] = $context['member']['alert_timeout'] = (int) $_POST['opt_alert_timeout'];

			setNotifyPrefs((int) $memID, $update_prefs);
			foreach ($update_prefs as $pref => $value)
				$context['alert_prefs'][$pref] = $value;

			session_flash('success', $context['user']['is_owner'] ? $txt['profile_updated_own'] : sprintf($txt['profile_updated_else'], $cur_profile['member_name']));
		}

		redirectexit('action=profile;area=alert_preferences;u=' . $memID);
	}
}
