<?php

/**
 * This file contains all the administration settings for topics and posts.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\App;
use StoryBB\Helper\Bbcode\AbstractParser;
use StoryBB\Helper\Parser;
use StoryBB\StringLibrary;
use StoryBB\Task\Scheduler;

/**
 * The main entrance point for the 'Posts and topics' screen.
 * Like all others, it checks permissions, then forwards to the right function
 * based on the given sub-action.
 * Defaults to sub-action 'posts'.
 * Accessed from ?action=admin;area=postsettings.
 * Requires (and checks for) the admin_forum permission.
 */
function ManagePostSettings()
{
	global $context, $txt;

	// Make sure you can be here.
	isAllowedTo('admin_forum');
	loadLanguage('Drafts');

	$subActions = [
		'posts' => 'ModifyPostSettings',
		'topics' => 'ModifyTopicSettings',
		'bbc' => 'ModifyBBCSettings',
		'fonts' => 'ModifyFontSettings',
		'censor' => 'SetCensor',
		'drafts' => 'ModifyDraftSettings',
	];

	// Default the sub-action to 'posts'.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'posts';

	$context['page_title'] = $txt['manageposts_title'];

	// Tabs for browsing the different post functions.
	$context[$context['admin_menu_name']]['tab_data'] = [
		'title' => $txt['manageposts_title'],
		'help' => '',
		'description' => $txt['manageposts_description'],
		'tabs' => [
			'posts' => [
				'description' => $txt['manageposts_settings_description'],
			],
			'topics' => [
				'description' => $txt['manageposts_topic_settings_description'],
			],
			'bbc' => [
				'description' => $txt['manageposts_bbc_settings_description'],
			],
			'fonts' => [
				'description' => $txt['manageposts_font_settings_description'],
			],
			'censor' => [
				'description' => $txt['admin_censored_desc'],
			],
			'drafts' => [
				'description' => $txt['drafts_show_desc'],
			],
		],
	];

	routing_integration_hook('integrate_manage_posts', [&$subActions]);

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * Set a few Bulletin Board Code settings. It loads a list of Bulletin Board Code tags to allow disabling tags.
 * Requires the admin_forum permission.
 * Accessed from ?action=admin;area=featuresettings;sa=bbc.
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 * @uses Admin template, edit_bbc_settings sub-template.
 */
function ModifyBBCSettings($return_config = false)
{
	global $context, $txt, $modSettings, $scripturl, $sourcedir;

	$config_vars = [
			// Main tweaks
			['check', 'enableBBC'],
			['check', 'enableBBC', 0, 'onchange' => 'toggleBBCDisabled(\'disabledBBC\', !this.checked);'],
			['check', 'enablePostHTML'],
			['check', 'autoLinkUrls'],
		'',
			['bbc', 'disabledBBC'],
		'',
			['large_text', 'bbcode_colors', 'rows' => 8, 'subtext' => $txt['one_color_per_line']],
	];

	$context['settings_post_javascript'] = '
		toggleBBCDisabled(\'disabledBBC\', ' . (empty($modSettings['enableBBC']) ? 'true' : 'false') . ');';

	settings_integration_hook('integrate_modify_bbc_settings', [&$config_vars]);

	if ($return_config)
		return [$txt['manageposts_bbc_settings_title'], $config_vars];

	// Setup the template.
	require_once($sourcedir . '/ManageServer.php');
	$context['page_title'] = $txt['manageposts_bbc_settings_title'];

	// Make sure we check the right tags!
	$modSettings['bbc_disabled_disabledBBC'] = empty($modSettings['disabledBBC']) ? [] : explode(',', $modSettings['disabledBBC']);

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Clean up the tags.
		$bbcTags = AbstractParser::get_all_bbcodes();

		if (!isset($_POST['disabledBBC_enabledTags']))
			$_POST['disabledBBC_enabledTags'] = [];
		elseif (!is_array($_POST['disabledBBC_enabledTags']))
			$_POST['disabledBBC_enabledTags'] = [$_POST['disabledBBC_enabledTags']];
		// Work out what is actually disabled!
		$_POST['disabledBBC'] = implode(',', array_diff($bbcTags, $_POST['disabledBBC_enabledTags']));

		$colors = $_POST['bbcode_colors'] ?? '';
		$colors = array_map('trim', explode("\n", $colors));
		$colors = array_filter($colors, function($x) {
			if (preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $x))
			{
				return true;
			}
			if (preg_match('/^AliceBlue|AntiqueWhite|Aqua|Aquamarine|Azure|Beige|Bisque|Black|BlanchedAlmond|Blue|BlueViolet|Brown|BurlyWood|CadetBlue|Chartreuse|Chocolate|Coral|CornflowerBlue|Cornsilk|Crimson|Cyan|DarkBlue|DarkCyan|DarkGoldenRod|DarkGray|DarkGrey|DarkGreen|DarkKhaki|DarkMagenta|DarkOliveGreen|DarkOrange|DarkOrchid|DarkRed|DarkSalmon|DarkSeaGreen|DarkSlateBlue|DarkSlateGray|DarkSlateGrey|DarkTurquoise|DarkViolet|DeepPink|DeepSkyBlue|DimGray|DimGrey|DodgerBlue|FireBrick|FloralWhite|ForestGreen|Fuchsia|Gainsboro|GhostWhite|Gold|GoldenRod|Gray|Grey|Green|GreenYellow|HoneyDew|HotPink|IndianRed|Indigo|Ivory|Khaki|Lavender|LavenderBlush|LawnGreen|LemonChiffon|LightBlue|LightCoral|LightCyan|LightGoldenRodYellow|LightGray|LightGrey|LightGreen|LightPink|LightSalmon|LightSeaGreen|LightSkyBlue|LightSlateGray|LightSlateGrey|LightSteelBlue|LightYellow|Lime|LimeGreen|Linen|Magenta|Maroon|MediumAquaMarine|MediumBlue|MediumOrchid|MediumPurple|MediumSeaGreen|MediumSlateBlue|MediumSpringGreen|MediumTurquoise|MediumVioletRed|MidnightBlue|MintCream|MistyRose|Moccasin|NavajoWhite|Navy|OldLace|Olive|OliveDrab|Orange|OrangeRed|Orchid|PaleGoldenRod|PaleGreen|PaleTurquoise|PaleVioletRed|PapayaWhip|PeachPuff|Peru|Pink|Plum|PowderBlue|Purple|RebeccaPurple|Red|RosyBrown|RoyalBlue|SaddleBrown|Salmon|SandyBrown|SeaGreen|SeaShell|Sienna|Silver|SkyBlue|SlateBlue|SlateGray|SlateGrey|Snow|SpringGreen|SteelBlue|Tan|Teal|Thistle|Tomato|Turquoise|Violet|Wheat|White|WhiteSmoke|Yellow|YellowGreen$/i', $x))
			{
				return true;
			}

			return false;
		});
		$_POST['bbcode_colors'] = implode("\n", $colors);

		settings_integration_hook('integrate_save_bbc_settings', [$bbcTags]);

		saveDBSettings($config_vars);
		session_flash('success', $txt['settings_saved']);
		redirectexit('action=admin;area=postsettings;sa=bbc');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=postsettings;save;sa=bbc';
	$context['settings_title'] = $txt['manageposts_bbc_settings_title'];

	prepareDBSettingContext($config_vars);
}

/**
 * Shows an interface to set and test censored words.
 * It uses the censor_vulgar, censor_proper, censorWholeWord, and censorIgnoreCase
 * settings.
 * Requires the admin_forum permission.
 * Accessed from ?action=admin;area=postsettings;sa=censor.
 *
 * @uses the Admin template and the edit_censored sub template.
 */
function SetCensor()
{
	global $txt, $modSettings, $context, $sourcedir;

	if (!empty($_POST['save_censor']))
	{
		// Make sure censoring is something they can do.
		checkSession();
		validateToken('admin-censor');

		$censored_vulgar = [];
		$censored_proper = [];

		// Rip it apart, then split it into two arrays.
		if (isset($_POST['censortext']))
		{
			$_POST['censortext'] = explode("\n", strtr($_POST['censortext'], ["\r" => '']));

			foreach ($_POST['censortext'] as $c)
				list ($censored_vulgar[], $censored_proper[]) = array_pad(explode('=', trim($c)), 2, '');
		}
		elseif (isset($_POST['censor_vulgar'], $_POST['censor_proper']))
		{
			if (is_array($_POST['censor_vulgar']))
			{
				foreach ($_POST['censor_vulgar'] as $i => $value)
				{
					if (trim(strtr($value, '*', ' ')) == '')
						unset($_POST['censor_vulgar'][$i], $_POST['censor_proper'][$i]);
				}

				$censored_vulgar = $_POST['censor_vulgar'];
				$censored_proper = $_POST['censor_proper'];
			}
			else
			{
				$censored_vulgar = explode("\n", strtr($_POST['censor_vulgar'], ["\r" => '']));
				$censored_proper = explode("\n", strtr($_POST['censor_proper'], ["\r" => '']));
			}
		}

		// Set the new arrays and settings in the database.
		$updates = [
			'censor_vulgar' => implode("\n", $censored_vulgar),
			'censor_proper' => implode("\n", $censored_proper),
			'allow_no_censored' => empty($_POST['allow_no_censored']) ? '0' : '1',
			'censorWholeWord' => empty($_POST['censorWholeWord']) ? '0' : '1',
			'censorIgnoreCase' => empty($_POST['censorIgnoreCase']) ? '0' : '1',
		];

		call_integration_hook('integrate_save_censors', [&$updates]);

		$context['saved_successful'] = true;
		updateSettings($updates);
	}

	if (isset($_POST['censortest']))
	{
		require_once($sourcedir . '/Subs-Post.php');
		$censorText = StringLibrary::escape($_POST['censortest'], ENT_QUOTES);
		preparsecode($censorText);
		$context['censor_test'] = strtr(censorText($censorText), ['"' => '&quot;']);
	}

	// Set everything up for the template to do its thang.
	$censor_vulgar = explode("\n", $modSettings['censor_vulgar']);
	$censor_proper = explode("\n", $modSettings['censor_proper']);

	$context['censored_words'] = [];
	for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++)
	{
		if (empty($censor_vulgar[$i]))
			continue;

		// Skip it, it's either spaces or stars only.
		if (trim(strtr($censor_vulgar[$i], '*', ' ')) == '')
			continue;

		$context['censored_words'][StringLibrary::escape(trim($censor_vulgar[$i]))] = isset($censor_proper[$i]) ? StringLibrary::escape($censor_proper[$i]) : '';
	}

	call_integration_hook('integrate_censors');

	// Since the "Allow users to disable the word censor" stuff was moved from a theme setting to a global one, we need this...
	loadLanguage('Themes');

	$context['sub_template'] = 'admin_censor_words';
	$context['page_title'] = $txt['admin_censored_words'];

	createToken('admin-censor');
}

/**
 * Modify any setting related to posts and posting.
 * Requires the admin_forum permission.
 * Accessed from ?action=admin;area=postsettings;sa=posts.
 *
 * @param bool $return_config Whether or not to return the $config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the config_vars array if $return_config is true
 * @uses Admin template, edit_post_settings sub-template.
 */
function ModifyPostSettings($return_config = false)
{
	global $context, $txt, $modSettings, $scripturl, $sourcedir, $db_type;

	// All the settings...
	$config_vars = [
			// Simple post options...
			['check', 'removeNestedQuotes'],
			['check', 'disable_wysiwyg'],
			['check', 'additional_options_collapsable'],
		'',
			// Posting limits...
			['int', 'max_messageLength', 'subtext' => $txt['max_messageLength_zero'], 'postinput' => $txt['manageposts_characters']],
			['int', 'topicSummaryPosts', 'postinput' => $txt['manageposts_posts']],
		'',
			// Posting time limits...
			['int', 'spamWaitTime', 'postinput' => $txt['manageposts_seconds']],
			['int', 'edit_wait_time', 'postinput' => $txt['manageposts_seconds']],
			['int', 'edit_disable_time', 'subtext' => $txt['zero_to_disable'], 'postinput' => $txt['manageposts_minutes']],
		'',
			// Automagic image resizing.
			['int', 'max_image_width', 'subtext' => $txt['zero_for_no_limit']],
			['int', 'max_image_height', 'subtext' => $txt['zero_for_no_limit']],
		'',
			// First & Last message preview lengths
			['int', 'preview_characters', 'subtext' => $txt['zero_to_disable'], 'postinput' => $txt['preview_characters_units']],
	];

	settings_integration_hook('integrate_modify_post_settings', [&$config_vars]);

	if ($return_config)
		return [$txt['manageposts_settings'], $config_vars];

	// We'll want this for our easy save.
	require_once($sourcedir . '/ManageServer.php');

	// Setup the template.
	$context['page_title'] = $txt['manageposts_settings'];

	// Are we saving them - are we??
	if (isset($_GET['save']))
	{
		checkSession();

		// If we're changing the post preview length let's check its valid
		if (!empty($_POST['preview_characters']))
			$_POST['preview_characters'] = (int) min(max(0, $_POST['preview_characters']), 512);

		settings_integration_hook('integrate_save_post_settings');

		saveDBSettings($config_vars);
		session_flash('success', $txt['settings_saved']);
		redirectexit('action=admin;area=postsettings;sa=posts');
	}

	// Final settings...
	$context['post_url'] = $scripturl . '?action=admin;area=postsettings;save;sa=posts';
	$context['settings_title'] = $txt['manageposts_settings'];

	// Prepare the settings...
	prepareDBSettingContext($config_vars);
}

/**
 * Modify any setting related to topics.
 * Requires the admin_forum permission.
 * Accessed from ?action=admin;area=postsettings;sa=topics.

 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns $config_vars if $return_config is true
 * @uses Admin template, edit_topic_settings sub-template.
 */
function ModifyTopicSettings($return_config = false)
{
	global $context, $txt, $sourcedir, $scripturl;

	loadLanguage('ManageSettings');

	// Here are all the topic settings.
	$config_vars = [
			['select', 'pollMode', [$txt['disable_polls'], $txt['enable_polls'], $txt['polls_as_topics']]],
		'',
			// Pagination etc...
			['int', 'oldTopicDays', 'postinput' => $txt['manageposts_days'], 'subtext' => $txt['zero_to_disable']],
			['int', 'defaultMaxTopics', 'postinput' => $txt['manageposts_topics']],
			['int', 'defaultMaxMessages', 'postinput' => $txt['manageposts_posts']],
		'',
			// All, next/prev...
			['int', 'enableAllMessages', 'postinput' => $txt['manageposts_posts'], 'subtext' => $txt['enableAllMessages_zero']],
			['check', 'disableCustomPerPage'],
		'',
			// Topic related settings (show icons/avatars etc...)
			['check', 'subject_toggle'],
			['check', 'show_modify'],
			['check', 'show_profile_buttons'],
			['check', 'show_user_images'],
		'',
			// First & Last message preview lengths
			['int', 'preview_characters', 'subtext' => $txt['zero_to_disable'], 'postinput' => $txt['preview_characters_units']],
			['check', 'message_index_preview_first', 'subtext' => $txt['message_index_preview_first_desc']],
	];

	settings_integration_hook('integrate_modify_topic_settings', [&$config_vars]);

	if ($return_config)
		return [$txt['manageposts_topic_settings'], $config_vars];

	// Get the settings template ready.
	require_once($sourcedir . '/ManageServer.php');

	// Setup the template.
	$context['page_title'] = $txt['manageposts_topic_settings'];

	// Are we saving them - are we??
	if (isset($_GET['save']))
	{
		checkSession();
		settings_integration_hook('integrate_save_topic_settings');

		saveDBSettings($config_vars);
		session_flash('success', $txt['settings_saved']);
		redirectexit('action=admin;area=postsettings;sa=topics');
	}

	// Final settings...
	$context['post_url'] = $scripturl . '?action=admin;area=postsettings;save;sa=topics';
	$context['settings_title'] = $txt['manageposts_topic_settings'];

	// Prepare the settings...
	prepareDBSettingContext($config_vars);
}

/**
 * Modify any setting related to drafts.
 * Requires the admin_forum permission.
 * Accessed from ?action=admin;area=postsettings;sa=drafts
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 * @uses Admin template, edit_topic_settings sub-template.
 */
function ModifyDraftSettings($return_config = false)
{
	global $context, $txt, $sourcedir, $scripturl;

	// Here are all the draft settings, a bit lite for now, but we can add more :P
	$config_vars = [
		// Draft settings ...
		['check', 'drafts_post_enabled'],
		['check', 'drafts_pm_enabled'],
		['check', 'drafts_charsheet_enabled'],
		['check', 'drafts_show_saved_enabled', 'subtext' => $txt['drafts_show_saved_enabled_subnote']],
		['int', 'drafts_keep_days', 'postinput' => $txt['days_word'], 'subtext' => $txt['drafts_keep_days_subnote']],
		'',
		['check', 'drafts_autosave_enabled', 'subtext' => $txt['drafts_autosave_enabled_subnote']],
		['int', 'drafts_autosave_frequency', 'postinput' => $txt['manageposts_seconds'], 'subtext' => $txt['drafts_autosave_frequency_subnote']],
	];

	if ($return_config)
		return [$txt['managedrafts_settings'], $config_vars];

	// Get the settings template ready.
	require_once($sourcedir . '/ManageServer.php');

	// Setup the template.
	$context['page_title'] = $txt['managedrafts_settings'];

	// Saving them ?
	if (isset($_GET['save']))
	{
		checkSession();

		// Protect them from themselves.
		$_POST['drafts_autosave_frequency'] = !isset($_POST['drafts_autosave_frequency']) || $_POST['drafts_autosave_frequency'] < 30 ? 30 : $_POST['drafts_autosave_frequency'];

		// Also disable the scheduled task if we're not using it.
		Scheduler::set_enabled_state('StoryBB\\Task\\Schedulable\\RemoveOldDrafts', !empty($_POST['drafts_keep_days']));

		require_once($sourcedir . '/ScheduledTasks.php');
		CalculateNextTrigger();

		// Save everything else and leave.
		saveDBSettings($config_vars);
		session_flash('success', $txt['settings_saved']);
		redirectexit('action=admin;area=postsettings;sa=drafts');
	}

	// some javascript to enable / disable the frequency input box
	$context['settings_post_javascript'] = '
		function toggle()
		{
			$("#drafts_autosave_frequency").prop("disabled", !($("#drafts_autosave_enabled").prop("checked")));
		};
		toggle();

		$("#drafts_autosave_enabled").click(function() { toggle(); });
	';

	// Final settings...
	$context['post_url'] = $scripturl . '?action=admin;area=postsettings;sa=drafts;save';
	$context['settings_title'] = $txt['managedrafts_settings'];

	// Prepare the settings...
	prepareDBSettingContext($config_vars);
}

function ModifyFontSettings($return_config = false)
{
	global $context, $txt, $sourcedir, $scripturl, $modSettings;

	// Most of the settings in this page make no sense to be returning in search mode.
	if ($return_config)
	{
		return [$txt['manageposts_font_settings'], [
			$txt['fonts_shown_in_editor'],
			$txt['standard_fonts'],
			$txt['fonts_from_themes'],
		]];
	}

	// Get the settings template ready.
	require_once($sourcedir . '/ManageServer.php');

	$config_vars = [
			['desc', 'fonts_shown_in_editor'],
		$txt['standard_fonts'],
	];

	// Add in the semi-standard fonts that people have. We need to create identifiers like this for the purposes of fonts.
	$standard_fonts = [
		'arial' => 'Arial',
		'arialblack' => 'Arial Black',
		'couriernew' => 'Courier New',
		'georgia' => 'Georgia',
		'sansserif' => 'Sans-serif',
		'serif' => 'Serif',
		'timesnewroman' => 'Times New Roman',
	];

	$configured_fonts = !empty($modSettings['editor_fonts']) ? json_decode($modSettings['editor_fonts'], true) : [];
	if (!isset($configured_fonts['standard']))
	{
		$configured_fonts['standard'] = [];
	}
	if (!isset($configured_fonts['theme']))
	{
		$configured_fonts['theme'] = [];
	}

	foreach ($standard_fonts as $font_id => $fontname)
	{
		// First, fake the label for the purposes of the form.
		$txt['editor_fonts_standard_font_' . $font_id] = $fontname;
		$config_vars[] = ['check', 'editor_fonts_standard_font_' . $font_id];
		if (in_array($font_id, $configured_fonts['standard']))
		{
			$modSettings['editor_fonts_standard_font_' . $font_id] = 1;
		}
	}

	// And now on to the fonts from themes.
	$fonts_from_themes = App::container()->get('thememanager')->get_font_list();

	if (!empty($fonts_from_themes))
	{
		$config_vars[] = $txt['fonts_from_themes'];
		foreach ($fonts_from_themes as $font_name => $themes)
		{
			$hash = sha1($font_name); // Just needs to be unique-ish and websafe, not crypto-secure.
			$txt['editor_fonts_theme_font_' . $hash] = $font_name . ' (' . implode(', ', array_keys($themes)) . ')';
			$config_vars[] = ['check', 'editor_fonts_theme_font_' . $hash];
			if (in_array($font_name, $configured_fonts['theme']))
			{
				$modSettings['editor_fonts_theme_font_' . $hash] = 1;
			}
		}
	}

	if (isset($_REQUEST['save']))
	{
		checkSession();

		$new_fonts = ['standard' => [], 'theme' => []];

		// First save the standard fonts.
		foreach ($standard_fonts as $font_id => $fontname) {
			if (!empty($_POST['editor_fonts_standard_font_' . $font_id]))
			{
				$new_fonts['standard'][] = $font_id;
			}
		}

		// Now the theme's fonts.
		foreach ($fonts_from_themes as $font_name => $themes)
		{
			$hash = sha1($font_name);
			if (!empty($_POST['editor_fonts_theme_font_' . $hash]))
			{
				$new_fonts['theme'][] = $font_name;
			}
		}

		updateSettings(['editor_fonts' => json_encode($new_fonts)]);
		App::container()->get('thememanager')->clear_css_cache();
		redirectexit('action=admin;area=postsettings;sa=fonts');
	}

	$context['page_title'] = $txt['manageposts_font_settings'];
	$context['post_url'] = $scripturl . '?action=admin;area=postsettings;sa=fonts;save';
	$context['settings_title'] = $context['page_title'];

	prepareDBSettingContext($config_vars);
}
