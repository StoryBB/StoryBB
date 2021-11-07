<?php

/**
 * Displays the summary profile page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Helper\IP;

class InfoSummary extends AbstractProfileController
{
	public function display_action()
	{
		global $context, $memberContext, $txt, $modSettings, $user_profile, $sourcedir, $scripturl, $smcFunc, $user_info;

		$memID = $this->params['u'];

		$context['sub_template'] = 'profile_summary';

		// Set up the stuff and load the user.
		$context += [
			'page_title' => sprintf($txt['profile_of_username'], $memberContext[$memID]['name']),
			'can_send_pm' => allowedTo('pm_send'),
			'can_have_buddy' => allowedTo('profile_identity_own') && !empty($modSettings['enable_buddylist']),
			'can_issue_warning' => allowedTo('issue_warning') && $modSettings['warning_settings'][0] == 1,
			'can_view_warning' => (allowedTo('moderate_forum') || allowedTo('issue_warning') || allowedTo('view_warning_any') || ($context['user']['is_owner'] && allowedTo('view_warning_own')) && $modSettings['warning_settings'][0] === 1)
		];
		$context['member'] = &$memberContext[$memID];

		// Set a canonical URL for this page.
		$context['canonical_url'] = $scripturl . '?action=profile;u=' . $memID;

		// Are there things we don't show?
		$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : [];

		// See if they have broken any warning levels...
		list ($modSettings['warning_enable'], $modSettings['user_limit']) = explode(',', $modSettings['warning_settings']);
		if (!empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $context['member']['warning'])
			$context['warning_status'] = $txt['profile_warning_is_muted'];
		elseif (!empty($modSettings['warning_moderate']) && $modSettings['warning_moderate'] <= $context['member']['warning'])
			$context['warning_status'] = $txt['profile_warning_is_moderation'];
		elseif (!empty($modSettings['warning_watch']) && $modSettings['warning_watch'] <= $context['member']['warning'])
			$context['warning_status'] = $txt['profile_warning_is_watch'];

		// They haven't even been registered for a full day!?
		$days_registered = (int) ((time() - $user_profile[$memID]['date_registered']) / (3600 * 24));
		if (empty($user_profile[$memID]['date_registered']) || $days_registered < 1)
			$context['member']['posts_per_day'] = $txt['not_applicable'];
		else
			$context['member']['posts_per_day'] = comma_format($context['member']['real_posts'] / $days_registered, 3);

		// Set the age...
		$context['member'] += [
			'show_birth' => false,
			'today_is_birthday' => false,
			'age' => false,
		];

		if (!empty($context['member']['birthday_visibility']) && $context['member']['birth_date'] > '1004-01-01')
		{
			list ($birth_year, $birth_month, $birth_day) = explode('-', $context['member']['birth_date']);
			$datearray = getdate(forum_time());
			$context['member']['today_is_birthday'] = $datearray['mon'] == $birth_month && $datearray['mday'] == $birth_day;

			if ($context['member']['birthday_visibility'] == 1)
			{
				// Showing day/month only.
				$context['member']['show_birth'] = true;
				$context['member']['formatted_birthdate'] = dateformat(0, $birth_month, $birth_day, $user_info['time_format']);
			}
			elseif ($context['member']['birthday_visibility'] == 2)
			{
				// Showing full date (and thus age)
				$context['member']['show_birth'] = true;
				$context['member']['formatted_birthdate'] = dateformat($birth_year, $birth_month, $birth_day, $user_info['time_format']);

				$age = $datearray['year'] - $birth_year - (($datearray['mon'] > $birth_month || ($datearray['mon'] == $birth_month && $datearray['mday'] >= $birth_day)) ? 0 : 1);
				$context['member']['age'] = sprintf($txt['age_profile'], $age);
			}
		}

		if (allowedTo('moderate_forum'))
		{
			// Make sure it's a valid ip address; otherwise, don't bother...
			if (IP::is_valid_ipv4($memberContext[$memID]['ip']) && empty($modSettings['disableHostnameLookup']))
				$context['member']['hostname'] = IP::get_host($memberContext[$memID]['ip']);
			else
				$context['member']['hostname'] = '';

			$context['can_see_ip'] = true;
		}
		else
			$context['can_see_ip'] = false;

		// Are they hidden?
		$context['member']['is_hidden'] = empty($user_profile[$memID]['show_online']);
		$context['member']['show_last_login'] = allowedTo('admin_forum') || !$context['member']['is_hidden'];

		// If the user is awaiting activation, and the viewer has permission - setup some activation context messages.
		if ($context['member']['is_activated'] % 10 != 1 && allowedTo('moderate_forum'))
		{
			$context['activate_type'] = $context['member']['is_activated'];
			// What should the link text be?
			$context['activate_link_text'] = in_array($context['member']['is_activated'], [3, 4, 5, 13, 14, 15]) ? $txt['account_approve'] : $txt['account_activate'];

			// Should we show a custom message?
			$context['activate_message'] = isset($txt['account_activate_method_' . $context['member']['is_activated'] % 10]) ? $txt['account_activate_method_' . $context['member']['is_activated'] % 10] : $txt['account_not_activated'];

			// If they can be approved, we need to set up a token for them.
			$context['token_check'] = 'profile-aa' . $memID;
			createToken($context['token_check'], 'get');

			$context['activate_link'] = $scripturl . '?action=profile;area=activat_eaccount;u=' . $context['id_member'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';' . $context[$context['token_check'] . '_token_var'] . '=' . $context[$context['token_check'] . '_token'];
		}

		// Is the signature even enabled on this forum?
		$context['signature_enabled'] = substr($modSettings['signature_settings'], 0, 1) == 1;

		// How about, are they banned?
		$context['member']['bans'] = [];
		if (allowedTo('moderate_forum'))
		{
			// Can they edit the ban?
			$context['can_edit_ban'] = allowedTo('manage_bans');

			$ban_query = [];
			$ban_query_vars = [
				'time' => time(),
			];
			$ban_query[] = 'id_member = ' . $context['member']['id'];
			$ban_query[] = ' {inet:ip} BETWEEN bi.ip_low and bi.ip_high';
			$ban_query_vars['ip'] = $memberContext[$memID]['ip'];
			// Do we have a hostname already?
			if (!empty($context['member']['hostname']))
			{
				$ban_query[] = '({string:hostname} LIKE hostname)';
				$ban_query_vars['hostname'] = $context['member']['hostname'];
			}
			// Check their email as well...
			if (strlen($context['member']['email']) != 0)
			{
				$ban_query[] = '({string:email} LIKE bi.email_address)';
				$ban_query_vars['email'] = $context['member']['email'];
			}

			// So... are they banned?  Dying to know!
			$request = $smcFunc['db']->query('', '
				SELECT bg.id_ban_group, bg.name, bg.cannot_access, bg.cannot_post,
					bg.cannot_login, bg.reason
				FROM {db_prefix}ban_items AS bi
					INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group AND (bg.expire_time IS NULL OR bg.expire_time > {int:time}))
				WHERE (' . implode(' OR ', $ban_query) . ')',
				$ban_query_vars
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				// Work out what restrictions we actually have.
				$ban_restrictions = [];
				foreach (['access', 'login', 'post'] as $type)
					if ($row['cannot_' . $type])
						$ban_restrictions[] = $txt['ban_type_' . $type];

				// No actual ban in place?
				if (empty($ban_restrictions))
					continue;

				// Prepare the link for context.
				$ban_explanation = sprintf($txt['user_cannot_due_to'], implode(', ', $ban_restrictions), '<a href="' . $scripturl . '?action=admin;area=ban;sa=edit;bg=' . $row['id_ban_group'] . '">' . $row['name'] . '</a>');

				$context['member']['bans'][$row['id_ban_group']] = [
					'reason' => empty($row['reason']) ? '' : '<br><br><strong>' . $txt['ban_reason'] . ':</strong> ' . $row['reason'],
					'cannot' => [
						'access' => !empty($row['cannot_access']),
						'post' => !empty($row['cannot_post']),
						'login' => !empty($row['cannot_login']),
					],
					'explanation' => $ban_explanation,
				];
			}
			$smcFunc['db']->free_result($request);
		}
		loadCustomFields($memID);

		$context['print_custom_fields'] = [];

		// Any custom profile fields?
		if (!empty($context['custom_fields']))
		{
			foreach ($context['custom_fields'] as $custom)
			{
				$context['print_custom_fields'][$context['cust_profile_fields_placement'][$custom['placement']]][] = $custom;
			}

			foreach ($context['print_custom_fields'] as $placement => $fields)
			{
				foreach ($fields as $id => $field)
				{
					if (empty($field['output_html']))
					{
						unset($context['print_custom_fields'][$placement][$id]);
					}
				}

				if (empty($context['print_custom_fields'][$placement]))
				{
					unset ($context['print_custom_fields'][$placement]);
				}
			}
		}

		$cur_profile = $user_profile[$memID];
		$main_char = $cur_profile['characters'][$cur_profile['main_char']];
		$context['member']['signature'] = $main_char['sig_parsed'];
		$user_groups = [];
		if (!empty($main_char['main_char_group']))
			$user_groups[] = $main_char['main_char_group'];
		if (!empty($cur_profile['id_group']))
			$user_groups[] = $cur_profile['id_group'];
		if (!empty($cur_profile['additional_groups']))
			$user_groups = array_merge($user_groups, explode(',', $cur_profile['additional_groups']));
		if (!empty($main_char['char_groups']))
			$user_groups = array_merge($user_groups, explode(',', $main_char['char_groups']));

		$details = get_labels_and_badges($user_groups);
		$context['member']['group'] = $details['title'];
		$context['member']['badges'] = $details['badges'];

		foreach ($context['member']['characters'] as $id_char => $char)
		{
			if ($char['is_main'])
				continue;
			else
				$context['member']['characters'][$id_char]['is_main'] = (bool) $char['is_main'];

			$context['member']['characters'][$id_char]['retired'] = (bool) $char['retired'];
			$user_groups = [];
			if (!empty($char['main_char_group']))
				$user_groups[] = $char['main_char_group'];
			if (!empty($char['char_groups']))
				$user_groups = array_merge($user_groups, explode(',', $char['char_groups']));
			$details = get_labels_and_badges($user_groups);
			$context['member']['characters'][$id_char]['display_group'] = $details['title'];
		}
	}
}
