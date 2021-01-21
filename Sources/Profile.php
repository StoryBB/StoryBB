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
	$section->add_item(new NavItem(
		'account_settings',
		$txt['profile_account_settings'],
		['area' => 'account_settings', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\AccountSettings',
		$context['user']['is_owner'] ? ['profile_identity_any', 'profile_identity_own', 'profile_password_any', 'profile_password_own', 'manage_membergroups'] : ['profile_identity_any', 'profile_password_any', 'manage_membergroups']
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

		$section = $characters->add_section(new NavSection('create_character', ''));
		$section->add_item(new NavItem(
			'create_character',
			$txt['char_create'],
			['area' => 'character_create', 'u' => $memID],
			'StoryBB\\Controller\\Profile\\CharacterCreate',
			$context['user']['is_owner'] ? ['is_not_guest'] : []
		));

		foreach ($context['member']['characters'] as $id_character => $character)
		{
			// Skip the OOC 'character'.
			if ($character['is_main'])
			{
				continue;
			}
			$section = $characters->add_section(new NavSection('character_' . $id_character, $character['character_name']));
			$section->add_item(new NavItem(
				'character_profile_' . $id_character,
				$txt['char_profile'],
				['area' => 'characters', 'u' => $memID, 'char' => $id_character],
				'StoryBB\\Controller\\Profile\\CharacterProfile',
				$context['user']['is_owner'] ? ['is_not_guest'] : ['profile_view']
			));
			$section->add_item(new NavItem(
				'character_sheet_' . $id_character,
				$txt['char_sheet'],
				['area' => 'character_sheet', 'u' => $memID, 'char' => $id_character],
				'StoryBB\\Controller\\Profile\\CharacterSheet',
				!empty($character['char_sheet']) || $char_sheet_override ? ['is_not_guest', 'profile_view'] : ['admin_forum']
			));
			$section->add_item(new NavItem(
				'character_stats_' . $id_character,
				$txt['char_stats'],
				['area' => 'character_stats', 'u' => $memID, 'char' => $id_character],
				'StoryBB\\Controller\\Profile\\CharacterStats',
				$context['user']['is_owner'] ? ['is_not_guest'] : ['profile_view']
			));
			$section->add_item(new NavItem(
				'character_posts_' . $id_character,
				$txt['showPosts_char'],
				['area' => 'character_posts', 'u' => $memID, 'char' => $id_character],
				'StoryBB\\Controller\\Profile\\CharacterPosts',
				$context['user']['is_owner'] ? ['is_not_guest'] : ['profile_view']
			));
			$section->add_item(new NavItem(
				'character_topics_' . $id_character,
				$txt['showTopics_char'],
				['area' => 'character_topics', 'u' => $memID, 'char' => $id_character],
				'StoryBB\\Controller\\Profile\\CharacterTopics',
				$context['user']['is_owner'] ? ['is_not_guest'] : ['profile_view']
			));
		}
	}

	/*
	$char_sheet_override = allowedTo('admin_forum') || $context['user']['is_owner'];
	// Now we need to add the user's characters to the profile menu, "creatively".
	if (!empty($context['member']['characters'])) {
		foreach ($context['member']['characters'] as $id_character => $character) {
			if (!empty($character['avatar'])) {
				addInlineCss('
span.character_' . $id_character . ' { background-image: url(' . $character['avatar'] . '); background-size: cover }');
			}
			$profile_areas['chars']['areas']['character_' . $id_character] = [
				'function' => 'character_profile',
				'file' => 'Profile-Chars.php',
				'label' => $character['character_name'],
				'icon' => !empty($character['avatar']) ? 'char_avatar character_' . $id_character : 'char_avatar char_unknown',
				'enabled' => true,
				'permission' => [
					'own' => 'is_not_guest',
					'any' => 'profile_view',
				],
				'select' => 'characters',
				'custom_url' => $scripturl . '?action=profile;area=characters;char=' . $id_character,
				'subsections' => [
					'profile' => [$txt['char_profile'], ['is_not_guest', 'profile_view']],
					'sheet' => [$txt['char_sheet'], !empty($character['char_sheet']) || $char_sheet_override ? ['is_not_guest', 'profile_view'] : ['admin_forum'], 'enabled' => empty($character['is_main'])],
					'posts' => [$txt['showPosts_char'], ['is_not_guest', 'profile_view']],
					'topics' => [$txt['showTopics_char'], ['is_not_guest', 'profile_view']],
					'stats' => [$txt['char_stats'], ['is_not_guest', 'profile_view']],
				],
			];
		}
	}
	*/

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
		['action' => 'admin', 'area' => 'ban', 'sa' => 'add', 'u' => $memID],
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
		'profile_popup',
		'',
		['area' => 'popup', 'u' => $memID],
		'StoryBB\\Controller\\Profile\\ProfilePopup',
		$context['user']['is_owner'] ? ['is_not_guest'] : []
	));
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

	$navigation->dispatch(array_merge($_REQUEST, ['u' => $memID]));
	$context['navigation'] = $navigation->export(['action' => 'profile']);

	// echo '<pre style="margin-left:100px">'; print_r($navigation); echo '</pre>';
}

/**
 * The main designating function for modifying profiles. Loads up info, determins what to do, etc.
 *
 * @deprecated
 * @param array $post_errors Any errors that occurred
 */
function ModifyProfile($post_errors = [])
{
	global $txt, $scripturl, $user_info, $context, $sourcedir, $user_profile, $cur_profile;
	global $modSettings, $memberContext, $profile_vars, $post_errors, $user_settings;
	global $smcFunc, $settings;

	// Don't reload this as we may have processed error strings.
	if (empty($post_errors))
		loadLanguage('Profile+Drafts');

	require_once($sourcedir . '/Subs-Menu.php');

	// Did we get the user by name...
	if (isset($_REQUEST['user']))
		$memberResult = loadMemberData($_REQUEST['user'], true, 'profile');
	// ... or by id_member?
	elseif (!empty($_REQUEST['u']))
		$memberResult = loadMemberData((int) $_REQUEST['u'], false, 'profile');
	// If it was just ?action=profile, edit your own profile, but only if you're not a guest.
	else
	{
		// Members only...
		is_not_guest();
		$memberResult = loadMemberData($user_info['id'], false, 'profile');
	}

	// Check if loadMemberData() has returned a valid result.
	if (!$memberResult)
		fatal_lang_error('not_a_user', false, 404);

	// If all went well, we have a valid member ID!
	list ($memID) = $memberResult;
	$context['id_member'] = $memID;
	$cur_profile = $user_profile[$memID];

	// Let's have some information about this member ready, too.
	loadMemberContext($memID);
	$context['member'] = $memberContext[$memID];

	// Is this the profile of the user himself or herself?
	$context['user']['is_owner'] = $memID == $user_info['id'];

	// Group management isn't actually a permission. But we need it to be for this, so we need a phantom permission.
	// And we care about what the current user can do, not what the user whose profile it is.
	if ($user_info['mod_cache']['gq'] != '0=1')
		$user_info['permissions'][] = 'approve_group_requests';

	// If paid subscriptions are enabled, make sure we actually have at least one subscription available...
	$context['subs_available'] = false;

	if (!empty($modSettings['paid_enabled']))
	{
		$get_active_subs = $smcFunc['db']->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}subscriptions
			WHERE active = {int:active}', [
				'active' => 1,
			]
		);

		list ($num_subs) = $smcFunc['db']->fetch_row($get_active_subs);

		$context['subs_available'] = ($num_subs > 0);

		$smcFunc['db']->free_result($get_active_subs);
	}

	/* Define all the sections within the profile area!
		We start by defining the permission required - then StoryBB takes this and turns it into the relevant context ;)
		Possible fields:
			For Section:
				string $title:		Section title.
				array $areas:		Array of areas within this section.

			For Areas:
				string $label:		Text string that will be used to show the area in the menu.
				string $file:		Optional text string that may contain a file name that's needed for inclusion in order to display the area properly.
				string $custom_url:	Optional href for area.
				string $function:	Function to execute for this section. Can be a call to an static method: class::method
				string $class		If your function is a method, set the class field with your class's name and StoryBB will create a new instance for it.
				bool $enabled:		Should area be shown?
				string $sc:			Session check validation to do on save - note without this save will get unset - if set.
				bool $hidden:		Does this not actually appear on the menu?
				bool $password:		Whether to require the user's password in order to save the data in the area.
				array $subsections:	Array of subsections, in order of appearance.
				array $permission:	Array of permissions to determine who can access this area. Should contain arrays $own and $any.
	*/
	$profile_areas = [
		'chars' => [
			'title' => $txt['chars_menu_title'],
			'areas' => [
				/* This definition doesn't seem to do anything - but it's there to make sure things work correctly! */
				'characters' => [
					'file' => 'Profile-Chars.php',
					'function' => 'character_profile',
					'enabled' => true,
					'permission' => [
						'own' => 'is_not_guest',
						'any' => 'profile_view',
					],
				],
			],
		],
	];

	addInlineCss('
span.char_unknown { background-image: url(' . $settings['images_url'] . '/default.png); }');

	$char_sheet_override = allowedTo('admin_forum') || $context['user']['is_owner'];
	// Now we need to add the user's characters to the profile menu, "creatively".
	if (!empty($context['member']['characters'])) {
		foreach ($context['member']['characters'] as $id_character => $character) {
			if (!empty($character['avatar'])) {
				addInlineCss('
span.character_' . $id_character . ' { background-image: url(' . $character['avatar'] . '); background-size: cover }');
			}
			$profile_areas['chars']['areas']['character_' . $id_character] = [
				'function' => 'character_profile',
				'file' => 'Profile-Chars.php',
				'label' => $character['character_name'],
				'icon' => !empty($character['avatar']) ? 'char_avatar character_' . $id_character : 'char_avatar char_unknown',
				'enabled' => true,
				'permission' => [
					'own' => 'is_not_guest',
					'any' => 'profile_view',
				],
				'select' => 'characters',
				'custom_url' => $scripturl . '?action=profile;area=characters;char=' . $id_character,
				'subsections' => [
					'profile' => [$txt['char_profile'], ['is_not_guest', 'profile_view']],
					'sheet' => [$txt['char_sheet'], !empty($character['char_sheet']) || $char_sheet_override ? ['is_not_guest', 'profile_view'] : ['admin_forum'], 'enabled' => empty($character['is_main'])],
					'posts' => [$txt['showPosts_char'], ['is_not_guest', 'profile_view']],
					'topics' => [$txt['showTopics_char'], ['is_not_guest', 'profile_view']],
					'stats' => [$txt['char_stats'], ['is_not_guest', 'profile_view']],
				],
			];
		}
	}

	// Let them modify profile areas easily.
	call_integration_hook('integrate_pre_profile_areas', [&$profile_areas]);

	// Do some cleaning ready for the menu function.
	$context['password_areas'] = [];
	$current_area = isset($_REQUEST['area']) ? $_REQUEST['area'] : '';

	foreach ($profile_areas as $section_id => $section)
	{
		// Do a bit of spring cleaning so to speak.
		foreach ($section['areas'] as $area_id => $area)
		{
			// If it said no permissions that meant it wasn't valid!
			if (empty($area['permission'][$context['user']['is_owner'] ? 'own' : 'any']))
				$profile_areas[$section_id]['areas'][$area_id]['enabled'] = false;
			// Otherwise pick the right set.
			else
				$profile_areas[$section_id]['areas'][$area_id]['permission'] = $area['permission'][$context['user']['is_owner'] ? 'own' : 'any'];

			// Password required in most cases
			if (!empty($area['password']))
				$context['password_areas'][] = $area_id;
		}
	}

	// Set a few options for the menu.
	$menuOptions = [
		'disable_url_session_check' => true,
		'current_area' => $current_area,
		'extra_url_parameters' => [
			'u' => $context['id_member'],
		],
	];

	// Actually create the menu!
	$profile_include_data = createMenu($profile_areas, $menuOptions);

	// No menu means no access.
	if (!$profile_include_data && (!$user_info['is_guest'] || validateSession()))
		fatal_lang_error('no_access', false);

	// Make a note of the Unique ID for this menu.
	$context['profile_menu_id'] = $context['max_menu_id'];
	$context['profile_menu_name'] = 'menu_data_' . $context['profile_menu_id'];

	// Set the selected item - now it's been validated.
	$current_area = $profile_include_data['current_area'];
	$current_sa = $profile_include_data['current_subsection'];
	$context['menu_item_selected'] = $current_area;

	// Before we go any further, let's work on the area we've said is valid. Note this is done here just in case we ever compromise the menu function in error!
	$context['completed_save'] = false;
	$context['do_preview'] = isset($_REQUEST['preview_signature']);

	$security_checks = [];
	$found_area = false;
	foreach ($profile_areas as $section_id => $section)
	{
		// Do a bit of spring cleaning so to speak.
		foreach ($section['areas'] as $area_id => $area)
		{
			// Is this our area?
			if ($current_area == $area_id)
			{
				// This can't happen - but is a security check.
				if ((isset($section['enabled']) && $section['enabled'] == false) || (isset($area['enabled']) && $area['enabled'] == false))
					fatal_lang_error('no_access', false);

				// Are we saving data in a valid area?
				if (isset($area['sc']) && (isset($_REQUEST['save']) || $context['do_preview']))
				{
					$security_checks['session'] = $area['sc'];
					$context['completed_save'] = true;
				}

				// Do we need to perform a token check?
				if (!empty($area['token']))
				{
					$security_checks[isset($_REQUEST['save']) ? 'validateToken' : 'needsToken'] = $area['token'];
					$token_name = $area['token'] !== true ? str_replace('%u', $context['id_member'], $area['token']) : 'profile-u' . $context['id_member'];

					$token_type = isset($area['token_type']) && in_array($area['token_type'], ['request', 'post', 'get']) ? $area['token_type'] : 'post';
				}

				// Does this require session validating?
				if (!empty($area['validate']) || (isset($_REQUEST['save']) && !$context['user']['is_owner']))
					$security_checks['validate'] = true;

				// Permissions for good measure.
				if (!empty($profile_include_data['permission']))
					$security_checks['permission'] = $profile_include_data['permission'];

				// Either way got something.
				$found_area = true;
			}
		}
	}

	// Oh dear, some serious security lapse is going on here... we'll put a stop to that!
	if (!$found_area)
		fatal_lang_error('no_access', false);

	// Release this now.
	unset($profile_areas);

	// Now the context is setup have we got any security checks to carry out additional to that above?
	if (isset($security_checks['session']))
		checkSession($security_checks['session']);
	if (isset($security_checks['validate']))
		validateSession();
	if (isset($security_checks['validateToken']))
		validateToken($token_name, $token_type);
	if (isset($security_checks['permission']))
		isAllowedTo($security_checks['permission']);

	// Create a token if needed.
	if (isset($security_checks['needsToken']) || isset($security_checks['validateToken']))
	{
		createToken($token_name, $token_type);
		$context['token_check'] = $token_name;
	}

	// File to include?
	if (isset($profile_include_data['file']))
		require_once($sourcedir . '/' . $profile_include_data['file']);

	// Build the link tree.
	$context['linktree'][] = [
		'url' => $scripturl . '?action=profile' . ($memID != $user_info['id'] ? ';u=' . $memID : ''),
		'name' => sprintf($txt['profile_of_username'], $context['member']['name']),
	];

	if (!empty($profile_include_data['label']))
		$context['linktree'][] = [
			'url' => $scripturl . '?action=profile' . ($memID != $user_info['id'] ? ';u=' . $memID : '') . ';area=' . $profile_include_data['current_area'],
			'name' => $profile_include_data['label'],
		];

	if (!empty($profile_include_data['current_subsection']) && $profile_include_data['subsections'][$profile_include_data['current_subsection']][0] != $profile_include_data['label'])
		$context['linktree'][] = [
			'url' => $scripturl . '?action=profile' . ($memID != $user_info['id'] ? ';u=' . $memID : '') . ';area=' . $profile_include_data['current_area'] . ';sa=' . $profile_include_data['current_subsection'],
			'name' => $profile_include_data['subsections'][$profile_include_data['current_subsection']][0],
		];

	// Set the template for this area and add the profile layer.
	$context['sub_template'] = $profile_include_data['function'];
	StoryBB\Template::add_layer('profile');

	// All the subactions that require a user password in order to validate.
	$check_password = $context['user']['is_owner'] && in_array($profile_include_data['current_area'], $context['password_areas']);
	$context['require_password'] = $check_password;

	loadJavaScriptFile('profile.js', ['defer' => false], 'sbb_profile');

	// These will get populated soon!
	$post_errors = [];
	$profile_vars = [];

	// Right - are we saving - if so let's save the old data first.
	if ($context['completed_save'])
	{
		// Clean up the POST variables.
		$_POST = htmltrim__recursive($_POST);
		$_POST = htmlspecialchars__recursive($_POST);

		if ($check_password)
		{
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
			if (!$good_password && !hash_verify_password($user_profile[$memID]['member_name'], un_htmlspecialchars(stripslashes($_POST['oldpasswrd'])), $user_info['passwd']))
				$post_errors[] = 'bad_password';

			// Warn other elements not to jump the gun and do custom changes!
			if (in_array('bad_password', $post_errors))
				$context['password_auth_failed'] = true;
		}

		// Change the IP address in the database.
		if ($context['user']['is_owner'])
			$profile_vars['member_ip'] = $user_info['ip'];

		// Now call the sub-action function...
		if (in_array($current_area, ['account', 'forumprofile', 'theme']))
			saveProfileFields();
		else
		{
			$force_redirect = true;
			// Ensure we include this.
			require_once($sourcedir . '/Profile-Modify.php');
			saveProfileChanges($profile_vars, $post_errors, $memID);
		}

		call_integration_hook('integrate_profile_save', [&$profile_vars, &$post_errors, $memID, $cur_profile, $current_area]);

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
			if (isset($profile_vars['passwd']))
			{
				(new Observable\Account\PasswordReset($cur_profile['member_name'], $cur_profile['member_name'], $_POST['passwrd2']))->execute();
			}

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
			updateMemberData($memID, $profile_vars);

			// What if this is the newest member?
			if ($modSettings['latestMember'] == $memID)
				updateStats('member');
			elseif (isset($profile_vars['real_name']))
				updateSettings(['memberlist_updated' => time()]);

			// Anything worth logging?
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

			// Have we got any post save functions to execute?
			if (!empty($context['profile_execute_on_save']))
				foreach ($context['profile_execute_on_save'] as $saveFunc)
					$saveFunc();

			// Let them know it worked!
			session_flash('success', $context['user']['is_owner'] ? $txt['profile_updated_own'] : sprintf($txt['profile_updated_else'], $cur_profile['member_name']));

			// Invalidate any cached data.
			cache_put_data('member_data-profile-' . $memID, null, 0);
		}
	}

	// Have some errors for some reason?
	if (!empty($post_errors))
	{
		// Set all the errors so the template knows what went wrong.
		foreach ($post_errors as $error_type)
			$context['modify_error'][$error_type] = true;
	}
	// If it's you then we should redirect upon save.
	elseif (!empty($profile_vars) && $context['user']['is_owner'] && !$context['do_preview'])
	{
		session_flash('success', $txt['profile_updated_own']);
		redirectexit('action=profile;area=' . $current_area . (!empty($current_sa) ? ';sa=' . $current_sa : ''));
	}
	elseif (!empty($force_redirect))
		redirectexit('action=profile' . ($context['user']['is_owner'] ? '' : ';u=' . $memID) . ';area=' . $current_area);


	// Get the right callable.
	$call = call_helper($profile_include_data['function'], true);

	// Is it valid?
	if (!empty($call))
		call_user_func($call, $memID);

	// Set the page title if it's not already set...
	if (!isset($context['page_title']))
		$context['page_title'] = $txt['profile'] . (isset($txt[$current_area]) ? ' - ' . $txt[$current_area] : '');
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
			col_name, field_name, field_desc, field_type, field_order, show_reg, field_length, field_options,
			default_value, bbc, enclose, placement
		FROM {db_prefix}custom_fields
		WHERE ' . $where . '
		ORDER BY field_order',
		[
			'area' => $area,
		]
	);
	$context['custom_fields'] = [];
	$context['custom_fields_required'] = false;
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// Shortcut.
		$exists = $memID && isset($user_profile[$memID], $user_profile[$memID]['options'][$row['col_name']]);
		$value = $exists ? $user_profile[$memID]['options'][$row['col_name']] : '';

		// If this was submitted already then make the value the posted version.
		if (isset($_POST['customfield']) && isset($_POST['customfield'][$row['col_name']]))
		{
			$value = StringLibrary::escape($_POST['customfield'][$row['col_name']]);
			if (in_array($row['field_type'], ['select', 'radio']))
					$value = ($options = explode(',', $row['field_options'])) && isset($options[$value]) ? $options[$value] : '';
		}

		// HTML for the input form.
		$output_html = $value;
		if ($row['field_type'] == 'check')
		{
			$true = (!$exists && $row['default_value']) || $value;
			$input_html = '<input type="checkbox" name="customfield[' . $row['col_name'] . ']" id="customfield[' . $row['col_name'] . ']"' . ($true ? ' checked' : '') . '>';
			$output_html = $true ? $txt['yes'] : $txt['no'];
		}
		elseif ($row['field_type'] == 'select')
		{
			$input_html = '<select name="customfield[' . $row['col_name'] . ']" id="customfield[' . $row['col_name'] . ']"><option value="-1"></option>';
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
				$input_html .= '<label for="customfield_' . $row['col_name'] . '_' . $k . '"><input type="radio" name="customfield[' . $row['col_name'] . ']" id="customfield_' . $row['col_name'] . '_' . $k . '" value="' . $k . '"' . ($true ? ' checked' : '') . '>' . $v . '</label><br>';
				if ($true)
					$output_html = $v;
			}
			$input_html .= '</fieldset>';
		}
		elseif ($row['field_type'] == 'text')
		{
			$input_html = '<input type="text" name="customfield[' . $row['col_name'] . ']" id="customfield[' . $row['col_name'] . ']"' . ($row['field_length'] != 0 ? ' maxlength="' . $row['field_length'] . '"' : '') . ' size="' . ($row['field_length'] == 0 || $row['field_length'] >= 50 ? 50 : ($row['field_length'] > 30 ? 30 : ($row['field_length'] > 10 ? 20 : 10))) . '" value="' . un_htmlspecialchars($value) . '"' . ($row['show_reg'] == 2 ? ' required' : '') . '>';
		}
		else
		{
			@list ($rows, $cols) = @explode(',', $row['default_value']);
			$input_html = '<textarea name="customfield[' . $row['col_name'] . ']" id="customfield[' . $row['col_name'] . ']"' . (!empty($rows) ? ' rows="' . $rows . '"' : '') . (!empty($cols) ? ' cols="' . $cols . '"' : '') . ($row['show_reg'] == 2 ? ' required' : '') . '>' . un_htmlspecialchars($value) . '</textarea>';
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
