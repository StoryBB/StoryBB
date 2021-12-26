<?php

/**
 * This file has the primary job of showing and editing people's profiles.
 * It also allows the user to change some of their or another's preferences,
 * and such things.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Helper\Navigation\Navigation;
use StoryBB\Helper\Navigation\Item as NavItem;
use StoryBB\Helper\Navigation\HiddenItem as NavHiddenItem;
use StoryBB\Helper\Navigation\Section as NavSection;
use StoryBB\Helper\Navigation\Tab as NavTab;
use StoryBB\Helper\Navigation\HiddenTab as NavTabHidden;
use StoryBB\Hook\Observable;
use StoryBB\StringLibrary;

function Profile()
{
	global $txt, $scripturl, $user_info, $context, $sourcedir, $user_profile, $cur_profile;
	global $modSettings, $memberContext, $profile_vars, $post_errors, $user_settings;
	global $smcFunc, $settings;

	loadLanguage('Profile');
	loadJavaScriptFile('profile.js', ['defer' => false], 'sbb_profile');

	$memID = isset($_REQUEST['u']) ? (int) $_REQUEST['u'] : $user_info['id'];

	$memberResult = loadMemberData($memID, false, 'profile');

	if (!$memberResult)
	{
		fatal_lang_error('not_a_user', false, 404);
	}

	$context['id_member'] = $memID;
	$cur_profile = $user_profile[$memID];

	// Let's have some information about this member ready, too.
	loadMemberContext($memID);
	if (empty($memberContext[$memID]))
	{
		fatal_lang_error('not_a_user', false, 404);
	}
	$context['member'] = $memberContext[$memID];

	// Is this the profile of the user himself or herself?
	$context['user']['is_owner'] = $memID == $user_info['id'];

	$navigation = new Navigation;

	$profile = $navigation->add_tab(new NavTab('information', $txt['profiletab_information']));
	$section = $profile->add_section(new NavSection('information', $txt['profiletab_information']));
	$section->add_item(new NavItem(
		'info_summary',
		$txt['profile_info_summary'],
		['area' => 'summary', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\InfoSummary',
		$context['user']['is_owner'] ? ['is_not_guest'] : ['profile_view']
	));
	$section->add_item(new NavItem(
		'send_pm',
		$txt['profile_send_pm'],
		['action' => 'pm', 'sa' => 'send', 'u' => $memID],
		'',
		$context['user']['is_owner'] ? [] : ['pm_send']
	));
	$section->add_item(new NavItem(
		'report_user',
		$txt['profile_report_user'],
		['action' => 'reporttm', 'u' => $memID],
		'',
		$context['user']['is_owner'] ? [] : ['report_user']
	));
	$section->add_item((new NavItem(
		'show_drafts',
		$txt['profile_show_drafts'],
		['area' => 'drafts', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\ShowDrafts',
		$context['user']['is_owner'] ? ['is_not_guest'] : []
	))->is_enabled(function () use ($modSettings) {
		return !empty($modSettings['drafts_post_enabled']);
	}));

	$account_overview = $navigation->add_tab(new NavTab('account', $txt['profiletab_account_overview']));
	$section = $account_overview->add_section(new NavSection('account', $txt['profiletab_account_overview']));
	$section->add_item(new NavItem(
		'account_settings',
		$txt['profile_account_settings'],
		['area' => 'account_settings', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\AccountSettings',
		$context['user']['is_owner'] ? ['profile_identity_any', 'profile_identity_own', 'profile_password_any', 'profile_password_own', 'manage_membergroups'] : ['profile_identity_any', 'profile_password_any', 'manage_membergroups']
	));
	$section->add_item((new NavItem(
		'view_warnings',
		$txt['profile_view_warnings'],
		['area' => 'view_warnings', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\WarningListing',
		$context['user']['is_owner'] ? ['profile_warning_own', 'profile_warning_any', 'issue_warning', 'moderate_forum'] : ['profile_warning_any', 'issue_warning', 'moderate_forum']
	))->is_enabled(function () use ($modSettings, $cur_profile) {
		return $modSettings['warning_settings'][0] == 1 && !empty($cur_profile['warning']);
	}));
	$section->add_item((new NavItem(
		'group_membership',
		$txt['profile_group_membership'],
		['area' => 'group_membership', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\GroupMembership',
		$context['user']['is_owner'] ? ['is_not_guest'] : ['manage_membergroups']
	))->is_enabled(function() use ($modSettings) {
		return !empty($modSettings['show_group_membership']);
	}));
	$section->add_item(new NavItem(
		'delete_account',
		$txt['profile_delete_account'],
		['area' => 'delete_account', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\DeleteAccount',
		$context['user']['is_owner'] ? ['profile_remove_any', 'profile_remove_own'] : ['profile_remove_any']
	));
	$section->add_item(new NavItem(
		'export_data',
		$txt['profile_export_data'],
		['area' => 'export_data', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\ExportData',
		$context['user']['is_owner'] ? ['is_not_guest'] : ['admin_forum']
	));
	$section->add_item((new NavItem(
		'paid_subscriptions',
		$txt['profile_paid_subscriptions'],
		['area' => 'subscriptions', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\Subscriptions',
		$context['user']['is_owner'] ? ['is_not_guest'] : ['moderate_forum']
	))->is_enabled(function() use ($modSettings, $smcFunc) {
		if (empty($modSettings['paid_enabled']))
		{
			return false;
		}

		// check for whethere there are any valid subs.
		$get_active_subs = $smcFunc['db']->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}subscriptions
			WHERE active = {int:active}', [
				'active' => 1,
			]
		);

		list ($num_subs) = $smcFunc['db']->fetch_row($get_active_subs);
		$smcFunc['db']->free_result($get_active_subs);

		return $num_subs > 0;
	}));
	$section->add_item(new NavItem(
		'merge_account',
		$txt['merge_char_account'],
		['area' => 'merge_acct', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\MergeAccount',
		$context['user']['is_owner'] ? [] : ['admin_forum']
	));

	$prefs = $navigation->add_tab(new NavTab('prefs', $txt['profiletab_preferences']));
	$section = $prefs->add_section(new NavSection('prefs', $txt['profiletab_preferences']));
	$section->add_item((new NavItem(
		'ignored_boards',
		$txt['profile_ignored_boards'],
		['area' => 'ignored_boards', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\IgnoredBoards',
		$context['user']['is_owner'] ? ['profile_extra_any', 'profile_extra_own'] : ['profile_extra_any']
	))->is_enabled(function () use ($modSettings) {
		return !empty($modSettings['allow_ignore_boards']);
	}));
	$section->add_item((new NavItem(
		'buddy_list',
		$txt['profile_buddy_list'],
		['area' => 'buddies', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\BuddyList',
		$context['user']['is_owner'] ? ['profile_extra_any', 'profile_extra_own'] : []
	))->is_enabled(function () use ($modSettings) {
		return !empty($modSettings['enable_buddylist']);
	}));
	$section->add_item((new NavItem(
		'ignored_people',
		$txt['profile_ignored_people'],
		['area' => 'ignored_people', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\IgnoredPeople',
		$context['user']['is_owner'] ? ['profile_extra_any', 'profile_extra_own'] : []
	))->is_enabled(function () use ($modSettings) {
		return !empty($modSettings['enable_buddylist']);
	}));
	$section->add_item(new NavItem(
		'avatar_signature',
		$txt['profile_avatar_signature'],
		['area' => 'avatar_signature', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\AvatarSignature',
		$context['user']['is_owner'] ? ['profile_forum_any', 'profile_forum_own'] : ['profile_forum_any']
	));
	$section->add_item(new NavItem(
		'preferences',
		$txt['profile_forum_preferences'],
		['area' => 'preferences', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\LookLayoutPreferences',
		$context['user']['is_owner'] ? ['profile_extra_any', 'profile_extra_own'] : ['profile_extra_any']
	));

	$characters = $navigation->add_tab(new NavTab('characters', $txt['profiletab_characters']));
	if (!empty($context['member']['characters']))
	{
		$char_sheet_override = allowedTo('admin_forum') || $context['user']['is_owner'];

		$section = $characters->add_section(new NavSection('meta_character', ''));

		$section->add_item(new NavItem(
			'create_character',
			$txt['char_create'],
			['area' => 'character_create', 'u' => $memID],
			'StoryBB\\Controller\\Profile\\CharacterCreate',
			$context['user']['is_owner'] ? ['is_not_guest'] : []
		));

		if (count($context['member']['characters']) > 1)
		{
			$section = $characters->add_section(new NavSection('characters', $txt['characters']));
		}

		foreach ($context['member']['characters'] as $id_character => $character)
		{
			// Skip the OOC 'character'.
			if ($character['is_main'])
			{
				continue;
			}
			$section->add_item(new NavItem(
				'character_profile_' . $id_character,
				$character['character_name'],
				['area' => 'characters', 'u' => $memID, 'char' => $id_character],
				'StoryBB\\Controller\\Profile\\CharacterProfile',
				$context['user']['is_owner'] ? ['is_not_guest'] : ['profile_view']
			));
			$section->add_item(new NavHiddenItem(
				'character_sheet_' . $id_character,
				$character['character_name'] . ' - ' . $txt['char_sheet'],
				['area' => 'character_sheet', 'u' => $memID, 'char' => $id_character],
				'StoryBB\\Controller\\Profile\\CharacterSheet',
				!empty($character['char_sheet']) || $char_sheet_override ? ['is_not_guest', 'profile_view'] : ['admin_forum'],
				'',
				['area' => 'characters', 'u' => $memID, 'char' => $id_character]
			));
			$section->add_item(new NavHiddenItem(
				'character_posts_' . $id_character,
				$character['character_name'] . ' - ' . $txt['showMessages'],
				['area' => 'character_posts', 'u' => $memID, 'char' => $id_character],
				'StoryBB\\Controller\\Profile\\CharacterPosts',
				$context['user']['is_owner'] ? ['is_not_guest'] : ['profile_view'],
				'',
				['area' => 'characters', 'u' => $memID, 'char' => $id_character]
			));
			$section->add_item(new NavHiddenItem(
				'character_topics_' . $id_character,
				$character['character_name'] . ' - ' . $txt['showTopics'],
				['area' => 'character_topics', 'u' => $memID, 'char' => $id_character],
				'StoryBB\\Controller\\Profile\\CharacterTopics',
				$context['user']['is_owner'] ? ['is_not_guest'] : ['profile_view'],
				'',
				['area' => 'characters', 'u' => $memID, 'char' => $id_character]
			));
		}
	}

	$notifications = $navigation->add_tab(new NavTab('notifications', $txt['profiletab_notification_settings']));
	$section = $notifications->add_section(new NavSection('notifications', $txt['profiletab_notification_settings']));
	$section->add_item(new NavItem(
		'bookmarks',
		$txt['profile_bookmarks'],
		['area' => 'bookmarks', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\Bookmarks',
		$context['user']['is_owner'] ? ['is_not_guest'] : []
	));
	$section->add_item(new NavItem(
		'ignored_topics',
		$txt['profile_ignored_topics'],
		['area' => 'ignored_topics', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\IgnoredTopics',
		$context['user']['is_owner'] ? ['is_not_guest'] : []
	));
	$section->add_item(new NavItem(
		'alerts',
		$txt['profile_my_alerts'],
		['area' => 'alerts', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\AlertsListing',
		$context['user']['is_owner'] ? ['is_not_guest'] : []
	));
	$section->add_item(new NavItem(
		'watched_topics',
		$txt['watched_topics'],
		['area' => 'watched_topics', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\WatchedTopics',
		$context['user']['is_owner'] ? ['is_not_guest'] : ['profile_extra_any']
	));
	$section->add_item(new NavItem(
		'watched_boards',
		$txt['watched_boards'],
		['area' => 'watched_boards', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\WatchedBoards',
		$context['user']['is_owner'] ? ['is_not_guest'] : ['profile_extra_any']
	));
	$section->add_item(new NavItem(
		'alert_preferences',
		$txt['profile_alert_preferences'],
		['area' => 'alert_preferences', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\AlertPreferences',
		$context['user']['is_owner'] ? ['is_not_guest'] : ['profile_extra_any']
	));

	$history = $navigation->add_tab(new NavTab('history', $txt['profiletab_history_stats']));
	$section = $history->add_section(new NavSection('history', $txt['profiletab_history_stats']));
	$section->add_item(new NavItem(
		'posts',
		$txt['profile_post_history'],
		['area' => 'posts', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\ShowPosts',
		$context['user']['is_owner'] ? ['is_not_guest'] : ['profile_view']
	));
	$section->add_item(new NavItem(
		'topics',
		$txt['profile_topic_history'],
		['area' => 'topics', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\ShowTopics',
		$context['user']['is_owner'] ? ['is_not_guest'] : ['profile_view']
	));
	$section->add_item(new NavItem(
		'attachments',
		$txt['profile_attachments'],
		['area' => 'attachments', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\ShowAttachments',
		$context['user']['is_owner'] ? ['is_not_guest'] : ['profile_view']
	));
	$section->add_item(new NavItem(
		'show_stats',
		$txt['profile_show_stats'],
		['area' => 'stats', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\Stats',
		$context['user']['is_owner'] ? ['is_not_guest'] : ['profile_view']
	));

	$admin = $navigation->add_tab(new NavTab('admin', $txt['profiletab_admin']));
	$section = $admin->add_section(new NavSection('admin', $txt['profiletab_admin']));
	$section->add_item(new NavItem(
		'account_activity',
		$txt['profile_account_activity'],
		['area' => 'activity', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\AccountActivity',
		['moderate_forum']
	));
	$section->add_item(new NavItem(
		'ip_lookup',
		$txt['profile_ip_lookup'],
		['action' => 'admin', 'area' => 'logs', 'sa' => 'ip', 'u' => $memID],
		'',
		['moderate_forum']
	));
	$section->add_item((new NavItem(
		'edit_history',
		$txt['profile_edit_history'],
		['area' => 'edit_history', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\EditHistory',
		['moderate_forum']
	))->is_enabled(function () use ($modSettings) {
		return !empty($modSettings['userlog_enabled']);
	}));
	$section->add_item((new NavItem(
		'login_history',
		$txt['profile_login_history'],
		['area' => 'login_history', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\LoginHistory',
		['moderate_forum']
	))->is_enabled(function () use ($modSettings) {
		return !empty($modSettings['loginHistoryDays']);
	}));
	$section->add_item((new NavItem(
		'group_requests',
		$txt['profile_group_requests'],
		['area' => 'group_requests', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\GroupRequests',
		['moderate_forum']
	))->is_enabled(function () use ($modSettings, $user_info) {
		return !empty($modSettings['show_group_membership']) && $user_info['mod_cache']['gq'] != '0=1';
	}));
	$section->add_item(new NavItem(
		'permissions',
		$txt['profile_account_permissions'],
		['area' => 'permissions', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\ShowPermissions',
		['manage_permissions']
	));
	$section->add_item((new NavItem(
		'ban_account',
		$txt['profile_ban_account'],
		['action' => 'admin', 'area' => 'ban', 'sa' => 'add', 'user' => $memID],
		'',
		$context['user']['is_owner'] ? [] : ['manage_bans']
	))->is_enabled(function () use ($cur_profile) {
		return $cur_profile['id_group'] != 1 && !in_array(1, explode(',', $cur_profile['additional_groups']));
	}));
	$section->add_item((new NavItem(
		'issue_warning',
		$txt['profile_issue_warning'],
		['area' => 'issue_warning', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\IssueWarning',
		$context['user']['is_owner'] ? [] : ['issue_warning']
	))->is_enabled(function () use ($modSettings) {
		return !empty($modSettings['warning_settings'][0]);
	}));

	// This 'tab' is for the things we want to route from here but that we don't want to ever explicitly show on a tab.
	$hidden = $navigation->add_tab(new NavTabHidden('hidden'));
	$section = $hidden->add_section(new NavSection('hidden', ''));
	$section->add_item(new NavItem(
		'alerts_popup',
		'',
		['area' => 'alerts_popup', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\AlertsPopup',
		$context['user']['is_owner'] ? ['is_not_guest'] : []
	));
	$section->add_item(new NavItem(
		'characters_popup',
		'',
		['area' => 'characters_popup', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\CharactersPopup',
		$context['user']['is_owner'] ? ['is_not_guest'] : []
	));
	$section->add_item(new NavItem(
		'character_switch',
		'',
		['area' => 'char_switch', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\CharacterSwitch',
		$context['user']['is_owner'] ? ['is_not_guest'] : []
	));
	$section->add_item(new NavItem(
		'activate_account',
		'',
		['area' => 'activate_account', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\ActivateAccount',
		$context['user']['is_owner'] ? [] : ['moderate_forum']
	));

	$params = array_merge($_REQUEST, ['u' => $memID]);
	$result = $navigation->set_visible_menu_item($params);

	if ($result)
	{
		$context['linktree'][] = [
			'name' => sprintf($txt['profile_of_username'], $context['member']['name']),
		];

		$appended = $navigation->append_linktree($context['linktree'], ['action' => 'profile']);
	}

	$context['page_title'] = sprintf($txt['profile_of_username'], $context['member']['name']);
	if (!empty($appended))
	{
		$context['page_title'] .= ' - ' . implode(': ', $appended);
	}

	$navigation->dispatch($params);
	$context['navigation'] = $navigation->export(['action' => 'profile']);
}

/**
 * Load any custom fields for this area... no area means load all, 'summary' loads all public ones.
 *
 * @param int $memID The ID of the member
 * @param string $area Which area to load fields for
 */
function loadCustomFields($memID, $area = 'summary')
{
	global $context, $txt, $user_profile, $smcFunc, $user_info, $settings, $scripturl;

	// Get the right restrictions in place...
	$where = 'active = 1';
	if (!allowedTo('admin_forum') && $area != 'register')
	{
		// If it's the owner they can see two types of private fields, regardless.
		if ($memID == $user_info['id'])
			$where .= $area == 'summary' ? ' AND private < 3' : ' AND (private = 0 OR private = 2)';
		else
			$where .= $area == 'summary' ? ' AND private < 2' : ' AND private = 0';
	}

	if ($area == 'register')
		$where .= ' AND show_reg != 0';
	elseif ($area != 'summary')
		$where .= ' AND show_profile = {string:area}';

	// Load all the relevant fields - and data.
	$request = $smcFunc['db']->query('', '
		SELECT
			id_field, col_name, field_name, field_desc, field_type, field_order, show_reg, field_length, field_options,
			default_value, bbc, enclose, placement
		FROM {db_prefix}custom_fields
		WHERE ' . $where . '
			AND in_character = 0
		ORDER BY field_order',
		[
			'area' => $area,
		]
	);
	$context['custom_fields'] = [];
	$context['custom_fields_required'] = false;

	if ($memID && isset($user_profile[$memID]))
	{
		foreach ($user_profile[$memID]['characters'] as $id_character => $character)
		{
			if ($character['is_main'])
			{
				$context['character'] = $character;
				break;
			}
		}
	}

	// @todo deduplicate this with the profile controllers' trait?
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// Shortcut.
		$exists = $memID && isset($context['character']['cfraw'][$row['id_field']]);
		$value = $exists ? $context['character']['cfraw'][$row['id_field']] : '';

		// If this was submitted already then make the value the posted version.
		if (isset($_POST['customfield']) && isset($_POST['customfield'][$row['id_field']]))
		{
			$value = StringLibrary::escape($_POST['customfield'][$row['id_field']]);
			if (in_array($row['field_type'], ['select', 'radio']))
					$value = ($options = explode(',', $row['field_options'])) && isset($options[$value]) ? $options[$value] : '';
		}

		// HTML for the input form.
		$output_html = $value;
		if ($row['field_type'] == 'check')
		{
			$true = (!$exists && $row['default_value']) || $value;
			$input_html = '<input type="checkbox" name="customfield[' . $row['id_field'] . ']" id="customfield[' . $row['id_field'] . ']"' . ($true ? ' checked' : '') . '>';
			$output_html = $true ? $txt['yes'] : $txt['no'];
		}
		elseif ($row['field_type'] == 'select')
		{
			$input_html = '<select name="customfield[' . $row['id_field'] . ']" id="customfield[' . $row['id_field'] . ']"><option value="-1"></option>';
			$options = explode(',', $row['field_options']);
			foreach ($options as $k => $v)
			{
				$true = (!$exists && $row['default_value'] == $v) || $value == $v;
				$input_html .= '<option value="' . $k . '"' . ($true ? ' selected' : '') . '>' . $v . '</option>';
				if ($true)
					$output_html = $v;
			}

			$input_html .= '</select>';
		}
		elseif ($row['field_type'] == 'radio')
		{
			$input_html = '<fieldset>';
			$options = explode(',', $row['field_options']);
			foreach ($options as $k => $v)
			{
				$true = (!$exists && $row['default_value'] == $v) || $value == $v;
				$input_html .= '<label for="customfield_' . $row['id_field'] . '_' . $k . '"><input type="radio" name="customfield[' . $row['id_field'] . ']" id="customfield_' . $row['id_field'] . '_' . $k . '" value="' . $k . '"' . ($true ? ' checked' : '') . '>' . $v . '</label><br>';
				if ($true)
					$output_html = $v;
			}
			$input_html .= '</fieldset>';
		}
		elseif ($row['field_type'] == 'text')
		{
			$input_html = '<input type="text" name="customfield[' . $row['id_field'] . ']" id="customfield[' . $row['id_field'] . ']"' . ($row['field_length'] != 0 ? ' maxlength="' . $row['field_length'] . '"' : '') . ' size="' . ($row['field_length'] == 0 || $row['field_length'] >= 50 ? 50 : ($row['field_length'] > 30 ? 30 : ($row['field_length'] > 10 ? 20 : 10))) . '" value="' . un_htmlspecialchars($value) . '"' . ($row['show_reg'] == 2 ? ' required' : '') . '>';
		}
		else
		{
			@list ($rows, $cols) = @explode(',', $row['default_value']);
			$input_html = '<textarea name="customfield[' . $row['id_field'] . ']" id="customfield[' . $row['id_field'] . ']"' . (!empty($rows) ? ' rows="' . $rows . '"' : '') . (!empty($cols) ? ' cols="' . $cols . '"' : '') . ($row['show_reg'] == 2 ? ' required' : '') . '>' . un_htmlspecialchars($value) . '</textarea>';
		}

		// Parse BBCode
		if ($row['bbc'])
			$output_html = Parser::parse_bbc($output_html);
		elseif ($row['field_type'] == 'textarea')
			// Allow for newlines at least
			$output_html = strtr($output_html, ["\n" => '<br>']);

		// Enclosing the user input within some other text?
		if (!empty($row['enclose']) && !empty($output_html))
			$output_html = strtr($row['enclose'], [
				'{SCRIPTURL}' => $scripturl,
				'{IMAGES_URL}' => $settings['images_url'],
				'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
				'{INPUT}' => un_htmlspecialchars($output_html),
			]);

		$context['custom_fields'][] = [
			'name' => $row['field_name'],
			'desc' => $row['field_desc'],
			'type' => $row['field_type'],
			'order' => $row['field_order'],
			'input_html' => $input_html,
			'output_html' => $output_html,
			'placement' => $row['placement'],
			'colname' => $row['col_name'],
			'value' => $value,
			'show_reg' => $row['show_reg'],
		];
		$context['custom_fields_required'] = $context['custom_fields_required'] || $row['show_reg'] == 2;
	}
	$smcFunc['db']->free_result($request);

	call_integration_hook('integrate_load_custom_profile_fields', [$memID, $area]);
}
