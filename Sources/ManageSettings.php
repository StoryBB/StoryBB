<?php

/**
 * This file is here to make it easier for installed mods to have
 * settings and options.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Helper\Parser;
use StoryBB\ClassManager;
use StoryBB\Model\Theme;
use StoryBB\StringLibrary;

/**
 * This function makes sure the requested subaction does exists, if it doesn't, it sets a default action or.
 *
 * @param array $subActions An array containing all possible subactions.
 * @param string $defaultAction The default action to be called if no valid subaction was found.
 */
function loadGeneralSettingParameters($subActions = [], $defaultAction = null)
{
	global $context, $sourcedir;

	// You need to be an admin to edit settings!
	isAllowedTo('admin_forum');

	loadLanguage('Help');
	loadLanguage('ManageSettings');

	// Will need the utility functions from here.
	require_once($sourcedir . '/ManageServer.php');

	// If no fallback was specified, use the first subaction.
	$defaultAction = $defaultAction ?: key($subActions);

	// I want...
	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : $defaultAction;
	$context['sub_action'] = $_REQUEST['sa'];
}

/**
 * This function passes control through to the relevant tab.
 */
function ModifyFeatureSettings()
{
	global $context, $txt, $settings, $scripturl;

	$context['page_title'] = $txt['modSettings_title'];

	$subActions = [
		'basic' => 'ModifyBasicSettings',
		'sig' => 'ModifySignatureSettings',
		'profile' => 'ShowCustomProfiles',
		'profileedit' => 'EditCustomProfiles',
		'alerts' => 'ModifyAlertsSettings',
	];

	loadGeneralSettingParameters($subActions, 'basic');

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = [
		'title' => $txt['modSettings_title'],
		'description' => sprintf($txt['modSettings_desc'], $settings['theme_id'], $context['session_id'], $context['session_var'], $scripturl),
		'tabs' => [
			'basic' => [
			],
			'sig' => [
				'description' => $txt['signature_settings_desc'],
			],
			'profile' => [
				'description' => $txt['custom_profile_desc'],
			],
			'alerts' => [
				'description' => $txt['notifications_desc'],
			],
		],
	];

	routing_integration_hook('integrate_modify_features', [&$subActions]);

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * Config array for changing the basic forum settings
 * Accessed  from ?action=admin;area=featuresettings;sa=basic;
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyBasicSettings($return_config = false)
{
	global $txt, $scripturl, $context, $cachedir, $modSettings;

	$config_vars = [
			// Basic stuff, titles, permissions...
			['text', 'forum_name', 'size' => 50],
			'',
			['check', 'allow_guestAccess'],
			['check', 'enable_likes'],
			['check', 'enable_mentions'],
			['check', 'enable_buddylist'],
			['check', 'allow_hideOnline'],
			['check', 'topic_move_any'],
			['int', 'defaultMaxListItems', 'step' => 1, 'min' => 1, 'max' => 999],
			// Pagination stuff.
			['int', 'defaultMaxMembers'],
		'',
			// Stuff that just is everywhere - today, search, online, etc.
			['select', 'todayMod', [$txt['today_disabled'], $txt['today_only'], $txt['yesterday_today']]],
		'',
			// css and js minification.
			['check', 'minimize_css'],
			['check', 'minimize_js'],
		'',
			// SEO stuff
			['text', 'meta_keywords', 'subtext' => $txt['meta_keywords_note'], 'size' => 50],
			['large_text', 'meta_description'],
		'',
			// Number formatting, timezones.
			['select', 'time_format', \StoryBB\Helper\Datetime::list_dateformats()],
			['float', 'time_offset', 'subtext' => $txt['setting_time_offset_note'], 6, 'postinput' => $txt['hours'], 'step' => 0.25, 'min' => -23.5, 'max' => 23.5],
			'default_timezone' => ['select', 'default_timezone', []],
			['text', 'timezone_priority_countries', 'subtext' => $txt['setting_timezone_priority_countries_note']],
		'',
			// Who's online?
			['check', 'who_enabled'],
			['int', 'lastActive', 6, 'postinput' => $txt['minutes']],
		'',
			// Statistics.
			['check', 'trackStats'],
			['check', 'hitStats'],
		'',
			// The time-between-posts divider.
			['int', 'timeBetweenPosts', 'subtext' => $txt['zero_to_disable']],
			['select', 'timeBetweenPostsBoards', [
				'ic' => $txt['ic_boards_only'],
				'ooc' => $txt['ooc_boards_only'],
				'icooc' => $txt['ic_and_ooc_boards'],
			]],
		'',
			// Option-ish things... miscellaneous sorta.
			['check', 'disallow_sendBody'],
		'',
			// Alerts stuff
			['check', 'enable_ajax_alerts'],
		'',
			['text', 'analytics_google_id', 'subtext' => $txt['analytics_google_id_sub']],
		'',
			['check', 'xmlnews_enable', 'onclick' => 'document.getElementById(\'xmlnews_maxlen\').disabled = !this.checked;'],
			['int', 'xmlnews_maxlen', 'subtext' => $txt['xmlnews_maxlen_note'], 10],
	];

	// Get all the time zones.
	if (function_exists('timezone_identifiers_list') && function_exists('date_default_timezone_set'))
	{
		$all_zones = timezone_identifiers_list();
		// Make sure we set the value to the same as the printed value.
		foreach ($all_zones as $zone)
			$config_vars['default_timezone'][2][$zone] = $zone;
	}
	else
		unset($config_vars['default_timezone']);

	settings_integration_hook('integrate_modify_basic_settings', [&$config_vars]);

	if ($return_config)
		return [$txt['mods_cat_features'], $config_vars];

	// Add some javascript at the bottom...
	addInlineJavaScript('
	document.getElementById("xmlnews_maxlen").disabled = !document.getElementById("xmlnews_enable").checked;', true);

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Prevent absurd boundaries here - make it a day tops.
		if (isset($_POST['lastActive']))
			$_POST['lastActive'] = min((int) $_POST['lastActive'], 1440);

		// Just to try to cut down obvious problems...
		if (isset($_POST['analytics_google_id']))
		{
			$_POST['analytics_google_id'] = trim($_POST['analytics_google_id']);
		}

		$new_minimize_css = !empty($_POST['minimize_css']);
		$old_minimize_css = !empty($modSettings['minimize_css']);

		if ($old_minimize_css != $new_minimize_css)
		{
			// It doesn't matter whether we're turning it on or off, we want to force a reset either way.
			Theme::clear_css_cache();
		}

		settings_integration_hook('integrate_save_basic_settings');

		saveDBSettings($config_vars);
		session_flash('success', $txt['settings_saved']);

		writeLog();
		redirectexit('action=admin;area=featuresettings;sa=basic');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=basic';
	$context['settings_title'] = $txt['mods_cat_features'];

	prepareDBSettingContext($config_vars);
}

/**
 * Moderation type settings - although there are fewer than we have you believe ;)
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyWarningSettings($return_config = false)
{
	global $txt, $scripturl, $context, $modSettings, $sourcedir;

	// You need to be an admin to edit settings!
	isAllowedTo('admin_forum');

	loadLanguage('Help');
	loadLanguage('ManageSettings');

	// We need the existing ones for this
	list ($currently_enabled, $modSettings['user_limit'], $modSettings['warning_decrement']) = explode(',', $modSettings['warning_settings']);

	$config_vars = [
			// Warning system?
			'enable' => ['check', 'warning_enable'],
	];

	if (!empty($modSettings['warning_settings']) && $currently_enabled)
		$config_vars += [
			'',
				['int', 'warning_watch', 'subtext' => $txt['setting_warning_watch_note'] . ' ' . $txt['zero_to_disable']],
				'moderate' => ['int', 'warning_moderate', 'subtext' => $txt['setting_warning_moderate_note'] . ' ' . $txt['zero_to_disable']],
				['int', 'warning_mute', 'subtext' => $txt['setting_warning_mute_note'] . ' ' . $txt['zero_to_disable']],
				'rem1' => ['int', 'user_limit', 'subtext' => $txt['setting_user_limit_note']],
				'rem2' => ['int', 'warning_decrement', 'subtext' => $txt['setting_warning_decrement_note'] . ' ' . $txt['zero_to_disable']],
		];

	settings_integration_hook('integrate_warning_settings', [&$config_vars]);

	if ($return_config)
		return [$txt['warnings'], $config_vars];

	// Will need the utility functions from here.
	require_once($sourcedir . '/ManageServer.php');

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Make sure these don't have an effect.
		if (!$currently_enabled && empty($_POST['warning_enable']))
		{
			$_POST['warning_watch'] = 0;
			$_POST['warning_moderate'] = 0;
			$_POST['warning_mute'] = 0;
		}
		// If it was disabled and we're enabling it now, set some sane defaults.
		elseif (!$currently_enabled && !empty($_POST['warning_enable']))
		{
			// Need to add these, these weren't there before...
			$vars = [
				'warning_watch' => 10,
				'warning_moderate' => 35,
				'warning_mute' => 60,
			];

			foreach ($vars as $var => $value)
			{
				$config_vars[] = ['int', $var];
				$_POST[$var] = $value;
			}
		}
		else
		{
			$_POST['warning_watch'] = min($_POST['warning_watch'], 100);
			$_POST['warning_moderate'] = min($_POST['warning_moderate'], 100);
			$_POST['warning_mute'] = min($_POST['warning_mute'], 100);
		}

		// We might not have these already depending on how we got here.
		$_POST['user_limit'] = isset($_POST['user_limit']) ? (int) $_POST['user_limit'] : $modSettings['user_limit'];
		$_POST['warning_decrement'] = isset($_POST['warning_decrement']) ? (int) $_POST['warning_decrement'] : $modSettings['warning_decrement'];

		// Fix the warning setting array!
		$_POST['warning_settings'] = (!empty($_POST['warning_enable']) ? 1 : 0) . ',' . min(100, $_POST['user_limit']) . ',' . min(100, $_POST['warning_decrement']);
		$save_vars = $config_vars;
		$save_vars[] = ['text', 'warning_settings'];
		unset($save_vars['enable'], $save_vars['rem1'], $save_vars['rem2']);

		settings_integration_hook('integrate_save_warning_settings', [&$save_vars]);

		saveDBSettings($save_vars);
		session_flash('success', $txt['settings_saved']);
		redirectexit('action=admin;area=warnings');
	}

	// We actually store lots of these together - for efficiency.
	list ($modSettings['warning_enable'], $modSettings['user_limit'], $modSettings['warning_decrement']) = explode(',', $modSettings['warning_settings']);

	$context['post_url'] = $scripturl . '?action=admin;area=warnings;save';
	$context['settings_title'] = $txt['warnings'];
	$context['page_title'] = $txt['warnings'];

	$context[$context['admin_menu_name']]['tab_data'] = [
		'title' => $txt['warnings'],
		'help' => '',
		'description' => $txt['warnings_desc'],
	];

	prepareDBSettingContext($config_vars);
}

/**
 * Let's try keep the spam to a minimum ah Thantos?
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyAntispamSettings($return_config = false)
{
	global $txt, $scripturl, $context, $modSettings, $sourcedir;

	loadLanguage('Help');
	loadLanguage('ManageSettings');

	$config_vars = [
				['check', 'reg_verification'],
				['check', 'search_enable_captcha'],
				// This, my friend, is a cheat :p
				'guest_verify' => ['check', 'guests_require_captcha', 'subtext' => $txt['setting_guests_require_captcha_desc']],
				['int', 'posts_require_captcha', 'subtext' => $txt['posts_require_captcha_desc'], 'onchange' => 'if (this.value > 0){ document.getElementById(\'guests_require_captcha\').checked = true; document.getElementById(\'guests_require_captcha\').disabled = true;} else {document.getElementById(\'guests_require_captcha\').disabled = false;}'],
			'',
			// PM Settings
				'pm1' => ['int', 'max_pm_recipients', 'subtext' => $txt['max_pm_recipients_note']],
				'pm2' => ['int', 'pm_posts_verification', 'subtext' => $txt['pm_posts_verification_note']],
				'pm3' => ['int', 'pm_posts_per_hour', 'subtext' => $txt['pm_posts_per_hour_note']],
	];

	$verifiables = [];
	foreach (ClassManager::get_classes_implementing('StoryBB\\Helper\\Verifiable\\Verifiable') as $class)
	{
		$verifiable = new $class('admin');
		foreach ($verifiable->get_settings() as $setting_id => $setting)
		{
			if (is_numeric($setting_id))
			{
				$config_vars[] = $setting;
			}
			else
			{
				$config_vars[$setting_id] = $setting;
			}
		}
		$verifiables[] = $verifiable;
	}

	settings_integration_hook('integrate_spam_settings', [&$config_vars]);

	if ($return_config)
		return [$txt['antispam_title'], $config_vars];

	// You need to be an admin to edit settings!
	isAllowedTo('admin_forum');

	// Will need the utility functions from here.
	require_once($sourcedir . '/ManageServer.php');

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Fix PM settings.
		$_POST['pm_spam_settings'] = (int) $_POST['max_pm_recipients'] . ',' . (int) $_POST['pm_posts_verification'] . ',' . (int) $_POST['pm_posts_per_hour'];

		// Hack in guest requiring verification!
		if (empty($_POST['posts_require_captcha']) && !empty($_POST['guests_require_captcha']))
			$_POST['posts_require_captcha'] = -1;

		$save_vars = $config_vars;
		unset($save_vars['pm1'], $save_vars['pm2'], $save_vars['pm3'], $save_vars['guest_verify']);

		$save_vars[] = ['text', 'pm_spam_settings'];

		foreach ($verifiables as $verifiable)
		{
			$verifiable->put_settings($save_vars);
		}

		settings_integration_hook('integrate_save_spam_settings', [&$save_vars]);

		// Now save.
		saveDBSettings($save_vars);
		session_flash('success', $txt['settings_saved']);

		redirectexit('action=admin;area=antispam');
	}

	// Hack for PM spam settings.
	list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);

	// Hack for guests requiring verification.
	$modSettings['guests_require_captcha'] = !empty($modSettings['posts_require_captcha']);
	$modSettings['posts_require_captcha'] = !isset($modSettings['posts_require_captcha']) || $modSettings['posts_require_captcha'] == -1 ? 0 : $modSettings['posts_require_captcha'];

	// Some minor javascript for the guest post setting.
	if ($modSettings['posts_require_captcha'])
		$context['settings_post_javascript'] .= '
		document.getElementById(\'guests_require_captcha\').disabled = true;';

	// And everything else.
	$context['post_url'] = $scripturl . '?action=admin;area=antispam;save';
	$context['settings_title'] = $txt['antispam_Settings'];
	$context['page_title'] = $txt['antispam_title'];

	$context[$context['admin_menu_name']]['tab_data'] = [
		'title' => $txt['antispam_title'],
		'description' => $txt['antispam_Settings_desc'],
	];

	prepareDBSettingContext($config_vars);
}

/**
 * You'll never guess what this function does...
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifySignatureSettings($return_config = false)
{
	global $context, $txt, $modSettings, $sig_start, $smcFunc, $scripturl;

	$config_vars = [
			// Are signatures even enabled?
			['check', 'signature_enable'],
		'',
			// Tweaking settings!
			['int', 'signature_max_length', 'subtext' => $txt['zero_for_no_limit']],
			['int', 'signature_max_lines', 'subtext' => $txt['zero_for_no_limit']],
			['int', 'signature_max_font_size', 'subtext' => $txt['zero_for_no_limit']],
			['check', 'signature_allow_smileys', 'onclick' => 'document.getElementById(\'signature_max_smileys\').disabled = !this.checked;'],
			['int', 'signature_max_smileys', 'subtext' => $txt['zero_for_no_limit']],
		'',
			// Image settings.
			['int', 'signature_max_images', 'subtext' => $txt['signature_max_images_note']],
			['int', 'signature_max_image_width', 'subtext' => $txt['zero_for_no_limit']],
			['int', 'signature_max_image_height', 'subtext' => $txt['zero_for_no_limit']],
		'',
			['bbc', 'signature_bbc'],
	];

	settings_integration_hook('integrate_signature_settings', [&$config_vars]);

	if ($return_config)
		return [$txt['signature_settings'], $config_vars];

	// Setup the template.
	$context['page_title'] = $txt['signature_settings'];

	// Disable the max smileys option if we don't allow smileys at all!
	$context['settings_post_javascript'] = 'document.getElementById(\'signature_max_smileys\').disabled = !document.getElementById(\'signature_allow_smileys\').checked;';

	// Load all the signature settings.
	list ($sig_limits, $sig_bbc) = explode(':', $modSettings['signature_settings']);
	$sig_limits = explode(',', $sig_limits);
	$disabledTags = !empty($sig_bbc) ? explode(',', $sig_bbc) : [];

	// Applying to ALL signatures?!!
	if (isset($_GET['apply']))
	{
		// Security!
		checkSession('get');

		$sig_start = time();
		// This is horrid - but I suppose some people will want the option to do it.
		$_GET['step'] = isset($_GET['step']) ? (int) $_GET['step'] : 0;
		$done = false;

		$request = $smcFunc['db']->query('', '
			SELECT MAX(id_member)
			FROM {db_prefix}members',
			[
			]
		);
		list ($context['max_member']) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		while (!$done)
		{
			$changes = [];

			$request = $smcFunc['db']->query('', '
				SELECT id_member, signature
				FROM {db_prefix}members
				WHERE id_member BETWEEN {int:step} AND {int:step} + 49
					AND id_group != {int:admin_group}
					AND FIND_IN_SET({int:admin_group}, additional_groups) = 0',
				[
					'admin_group' => 1,
					'step' => $_GET['step'],
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				// Apply all the rules we can realistically do.
				$sig = strtr($row['signature'], ['<br>' => "\n"]);

				// Max characters...
				if (!empty($sig_limits[1]))
					$sig = StringLibrary::substr($sig, 0, $sig_limits[1]);
				// Max lines...
				if (!empty($sig_limits[2]))
				{
					$count = 0;
					for ($i = 0, $n = strlen($sig); $i < $n; $i++)
					{
						if ($sig[$i] == "\n")
						{
							$count++;
							if ($count >= $sig_limits[2])
								$sig = substr($sig, 0, $i) . strtr(substr($sig, $i), ["\n" => ' ']);
						}
					}
				}

				if (!empty($sig_limits[7]) && preg_match_all('~\[size=([\d\.]+)?(px|pt|em|x-large|larger)~i', $sig, $matches) !== false && isset($matches[2]))
				{
					foreach ($matches[1] as $ind => $size)
					{
						$limit_broke = 0;
						// Attempt to allow all sizes of abuse, so to speak.
						if ($matches[2][$ind] == 'px' && $size > $sig_limits[7])
							$limit_broke = $sig_limits[7] . 'px';
						elseif ($matches[2][$ind] == 'pt' && $size > ($sig_limits[7] * 0.75))
							$limit_broke = ((int) $sig_limits[7] * 0.75) . 'pt';
						elseif ($matches[2][$ind] == 'em' && $size > ((float) $sig_limits[7] / 16))
							$limit_broke = ((float) $sig_limits[7] / 16) . 'em';
						elseif ($matches[2][$ind] != 'px' && $matches[2][$ind] != 'pt' && $matches[2][$ind] != 'em' && $sig_limits[7] < 18)
							$limit_broke = 'large';

						if ($limit_broke)
							$sig = str_replace($matches[0][$ind], '[size=' . $sig_limits[7] . 'px', $sig);
					}
				}

				// Stupid images - this is stupidly, stupidly challenging.
				if ((!empty($sig_limits[3]) || !empty($sig_limits[5]) || !empty($sig_limits[6])))
				{
					$replaces = [];
					$img_count = 0;
					// Get all BBC tags...
					preg_match_all('~\[img(\s+width=([\d]+))?(\s+height=([\d]+))?(\s+width=([\d]+))?\s*\](?:<br>)*([^<">]+?)(?:<br>)*\[/img\]~i', $sig, $matches);
					// ... and all HTML ones.
					preg_match_all('~&lt;img\s+src=(?:&quot;)?((?:http://|ftp://|https://|ftps://).+?)(?:&quot;)?(?:\s+alt=(?:&quot;)?(.*?)(?:&quot;)?)?(?:\s?/)?&gt;~i', $sig, $matches2, PREG_PATTERN_ORDER);
					// And stick the HTML in the BBC.
					if (!empty($matches2))
					{
						foreach ($matches2[0] as $ind => $dummy)
						{
							$matches[0][] = $matches2[0][$ind];
							$matches[1][] = '';
							$matches[2][] = '';
							$matches[3][] = '';
							$matches[4][] = '';
							$matches[5][] = '';
							$matches[6][] = '';
							$matches[7][] = $matches2[1][$ind];
						}
					}
					// Try to find all the images!
					if (!empty($matches))
					{
						$image_count_holder = [];
						foreach ($matches[0] as $key => $image)
						{
							$width = -1;
							$height = -1;
							$img_count++;
							// Too many images?
							if (!empty($sig_limits[3]) && $img_count > $sig_limits[3])
							{
								// If we've already had this before we only want to remove the excess.
								if (isset($image_count_holder[$image]))
								{
									$img_offset = -1;
									$rep_img_count = 0;
									while ($img_offset !== false)
									{
										$img_offset = strpos($sig, $image, $img_offset + 1);
										$rep_img_count++;
										if ($rep_img_count > $image_count_holder[$image])
										{
											// Only replace the excess.
											$sig = substr($sig, 0, $img_offset) . str_replace($image, '', substr($sig, $img_offset));
											// Stop looping.
											$img_offset = false;
										}
									}
								}
								else
									$replaces[$image] = '';

								continue;
							}

							// Does it have predefined restraints? Width first.
							if ($matches[6][$key])
								$matches[2][$key] = $matches[6][$key];
							if ($matches[2][$key] && $sig_limits[5] && $matches[2][$key] > $sig_limits[5])
							{
								$width = $sig_limits[5];
								$matches[4][$key] = $matches[4][$key] * ($width / $matches[2][$key]);
							}
							elseif ($matches[2][$key])
								$width = $matches[2][$key];
							// ... and height.
							if ($matches[4][$key] && $sig_limits[6] && $matches[4][$key] > $sig_limits[6])
							{
								$height = $sig_limits[6];
								if ($width != -1)
									$width = $width * ($height / $matches[4][$key]);
							}
							elseif ($matches[4][$key])
								$height = $matches[4][$key];

							// If the dimensions are still not fixed - we need to check the actual image.
							if (($width == -1 && $sig_limits[5]) || ($height == -1 && $sig_limits[6]))
							{
								$sizes = url_image_size($matches[7][$key]);
								if (is_array($sizes))
								{
									// Too wide?
									if ($sizes[0] > $sig_limits[5] && $sig_limits[5])
									{
										$width = $sig_limits[5];
										$sizes[1] = $sizes[1] * ($width / $sizes[0]);
									}
									// Too high?
									if ($sizes[1] > $sig_limits[6] && $sig_limits[6])
									{
										$height = $sig_limits[6];
										if ($width == -1)
											$width = $sizes[0];
										$width = $width * ($height / $sizes[1]);
									}
									elseif ($width != -1)
										$height = $sizes[1];
								}
							}

							// Did we come up with some changes? If so remake the string.
							if ($width != -1 || $height != -1)
							{
								$replaces[$image] = '[img' . ($width != -1 ? ' width=' . round($width) : '') . ($height != -1 ? ' height=' . round($height) : '') . ']' . $matches[7][$key] . '[/img]';
							}

							// Record that we got one.
							$image_count_holder[$image] = isset($image_count_holder[$image]) ? $image_count_holder[$image] + 1 : 1;
						}
						if (!empty($replaces))
							$sig = str_replace(array_keys($replaces), array_values($replaces), $sig);
					}
				}
				// Try to fix disabled tags.
				if (!empty($disabledTags))
				{
					$sig = preg_replace('~\[(?:' . implode('|', $disabledTags) . ').+?\]~i', '', $sig);
					$sig = preg_replace('~\[/(?:' . implode('|', $disabledTags) . ')\]~i', '', $sig);
				}

				$sig = strtr($sig, ["\n" => '<br>']);
				call_integration_hook('integrate_apply_signature_settings', [&$sig, $sig_limits, $disabledTags]);
				if ($sig != $row['signature'])
					$changes[$row['id_member']] = $sig;
			}
			if ($smcFunc['db']->num_rows($request) == 0)
				$done = true;
			$smcFunc['db']->free_result($request);

			// Do we need to delete what we have?
			if (!empty($changes))
			{
				foreach ($changes as $id => $sig)
					$smcFunc['db']->query('', '
						UPDATE {db_prefix}members
						SET signature = {string:signature}
						WHERE id_member = {int:id_member}',
						[
							'id_member' => $id,
							'signature' => $sig,
						]
					);
			}

			$_GET['step'] += 50;
			if (!$done)
				pauseSignatureApplySettings();
		}
		$settings_applied = true;
	}

	$context['signature_settings'] = [
		'enable' => isset($sig_limits[0]) ? $sig_limits[0] : 0,
		'max_length' => isset($sig_limits[1]) ? $sig_limits[1] : 0,
		'max_lines' => isset($sig_limits[2]) ? $sig_limits[2] : 0,
		'max_images' => isset($sig_limits[3]) ? $sig_limits[3] : 0,
		'allow_smileys' => isset($sig_limits[4]) && $sig_limits[4] == -1 ? 0 : 1,
		'max_smileys' => isset($sig_limits[4]) && $sig_limits[4] != -1 ? $sig_limits[4] : 0,
		'max_image_width' => isset($sig_limits[5]) ? $sig_limits[5] : 0,
		'max_image_height' => isset($sig_limits[6]) ? $sig_limits[6] : 0,
		'max_font_size' => isset($sig_limits[7]) ? $sig_limits[7] : 0,
	];

	// Temporarily make each setting a modSetting!
	foreach ($context['signature_settings'] as $key => $value)
		$modSettings['signature_' . $key] = $value;

	// Make sure we check the right tags!
	$modSettings['bbc_disabled_signature_bbc'] = $disabledTags;

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Clean up the tag stuff!
		$bbcTags = [];
		foreach (Parser::parse_bbc(false) as $tag)
			$bbcTags[] = $tag['tag'];

		if (!isset($_POST['signature_bbc_enabledTags']))
			$_POST['signature_bbc_enabledTags'] = [];
		elseif (!is_array($_POST['signature_bbc_enabledTags']))
			$_POST['signature_bbc_enabledTags'] = [$_POST['signature_bbc_enabledTags']];

		$sig_limits = [];
		foreach ($context['signature_settings'] as $key => $value)
		{
			if ($key == 'allow_smileys')
				continue;
			elseif ($key == 'max_smileys' && empty($_POST['signature_allow_smileys']))
				$sig_limits[] = -1;
			else
				$sig_limits[] = !empty($_POST['signature_' . $key]) ? max(1, (int) $_POST['signature_' . $key]) : 0;
		}

		settings_integration_hook('integrate_save_signature_settings', [&$sig_limits, &$bbcTags]);

		$_POST['signature_settings'] = implode(',', $sig_limits) . ':' . implode(',', array_diff($bbcTags, $_POST['signature_bbc_enabledTags']));

		// Even though we have practically no settings let's keep the convention going!
		$save_vars = [];
		$save_vars[] = ['text', 'signature_settings'];

		saveDBSettings($save_vars);
		session_flash('success', $txt['settings_saved']);
		redirectexit('action=admin;area=featuresettings;sa=sig');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=sig';
	$context['settings_title'] = $txt['signature_settings'];

	$context['settings_message'] = !empty($settings_applied) ? '<div class="infobox">' . $txt['signature_settings_applied'] . '</div>' : '<p class="centertext">' . sprintf($txt['signature_settings_warning'], $context['session_id'], $context['session_var'], $scripturl) . '</p>';

	prepareDBSettingContext($config_vars);
}

/**
 * Just pause the signature applying thing.
 */
function pauseSignatureApplySettings()
{
	global $context, $txt, $sig_start;

	// Try get more time...
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	// Have we exhausted all the time we allowed?
	if (time() - $sig_start < 3)
		return;

	$context['continue_get_data'] = '?action=admin;area=featuresettings;sa=sig;apply;step=' . $_GET['step'] . ';' . $context['session_var'] . '=' . $context['session_id'];
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = '2';
	$context['sub_template'] = 'not_done';

	// Specific stuff to not break this template!
	$context[$context['admin_menu_name']]['current_subsection'] = 'sig';

	// Get the right percent.
	$context['continue_percent'] = round(($_GET['step'] / $context['max_member']) * 100);

	// Never more than 100%!
	$context['continue_percent'] = min($context['continue_percent'], 100);

	obExit();
}

/**
 * Show all the custom profile fields available to the user.
 */
function ShowCustomProfiles()
{
	global $txt, $scripturl, $context;
	global $sourcedir;

	$context['page_title'] = $txt['custom_profile_title'];
	$context['sub_template'] = 'admin_profile_fields';

	// What about standard fields they can tweak?
	$standard_fields = ['timezone', 'posts', 'warning_status'];
	// What fields can't you put on the registration page?
	$context['fields_no_registration'] = ['posts', 'warning_status'];

	// Are we saving any standard field changes?
	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('admin-scp');

		// Do the active ones first.
		$disable_fields = array_flip($standard_fields);
		if (!empty($_POST['active']))
		{
			foreach ($_POST['active'] as $value)
				if (isset($disable_fields[$value]))
					unset($disable_fields[$value]);
		}
		// What we have left!
		$changes['disabled_profile_fields'] = empty($disable_fields) ? '' : implode(',', array_keys($disable_fields));

		// Things we want to show on registration?
		$reg_fields = [];
		if (!empty($_POST['reg']))
		{
			foreach ($_POST['reg'] as $value)
				if (in_array($value, $standard_fields) && !isset($disable_fields[$value]))
					$reg_fields[] = $value;
		}
		// What we have left!
		$changes['registration_fields'] = empty($reg_fields) ? '' : implode(',', $reg_fields);

		session_flash('success', $txt['settings_saved']);
		if (!empty($changes))
			updateSettings($changes);
		redirectexit('action=admin;area=featuresettings;sa=profile');
	}

	createToken('admin-scp');

	// Need to know the max order for custom fields
	$context['custFieldsMaxOrder_ic'] = custFieldsMaxOrder(true);
	$context['custFieldsMaxOrder_ooc'] = custFieldsMaxOrder(false);

	require_once($sourcedir . '/Subs-List.php');

	$listOptions = [
		'id' => 'standard_profile_fields',
		'title' => $txt['standard_profile_title'],
		'base_href' => $scripturl . '?action=admin;area=featuresettings;sa=profile',
		'get_items' => [
			'function' => 'list_getProfileFields',
			'params' => [
				true,
			],
		],
		'columns' => [
			'field' => [
				'header' => [
					'value' => $txt['standard_profile_field'],
				],
				'data' => [
					'db' => 'label',
					'style' => 'width: 60%;',
				],
			],
			'active' => [
				'header' => [
					'value' => $txt['custom_edit_active'],
					'class' => 'centercol',
				],
				'data' => [
					'function' => function ($rowData)
					{
						$isChecked = $rowData['disabled'] ? '' : ' checked';
						$onClickHandler = $rowData['can_show_register'] ? sprintf(' onclick="document.getElementById(\'reg_%1$s\').disabled = !this.checked;"', $rowData['id']) : '';
						return sprintf('<input type="checkbox" name="active[]" id="active_%1$s" value="%1$s"%2$s%3$s>', $rowData['id'], $isChecked, $onClickHandler);
					},
					'style' => 'width: 20%;',
					'class' => 'centercol',
				],
			],
			'show_on_registration' => [
				'header' => [
					'value' => $txt['custom_edit_registration'],
					'class' => 'centercol',
				],
				'data' => [
					'function' => function ($rowData)
					{
						$isChecked = $rowData['on_register'] && !$rowData['disabled'] ? ' checked' : '';
						$isDisabled = $rowData['can_show_register'] ? '' : ' disabled';
						return sprintf('<input type="checkbox" name="reg[]" id="reg_%1$s" value="%1$s"%2$s%3$s>', $rowData['id'], $isChecked, $isDisabled);
					},
					'style' => 'width: 20%;',
					'class' => 'centercol',
				],
			],
		],
		'form' => [
			'href' => $scripturl . '?action=admin;area=featuresettings;sa=profile',
			'name' => 'standardProfileFields',
			'token' => 'admin-scp',
		],
		'additional_rows' => [
			[
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="save" value="' . $txt['save'] . '">',
			],
		],
	];
	createList($listOptions);

	$listOptions = [
		'id' => 'custom_profile_fields',
		'title' => $txt['custom_profile_title_ic_fields'],
		'base_href' => $scripturl . '?action=admin;area=featuresettings;sa=profile',
		'default_sort_col' => 'field_order',
		'no_items_label' => $txt['custom_profile_none_ic'],
		'items_per_page' => 25,
		'get_items' => [
			'function' => 'list_getProfileFields',
			'params' => [
				false,
				true,
			],
		],
		'get_count' => [
			'function' => 'list_getProfileFieldSize',
		],
		'columns' => [
			'field_order' => [
				'header' => [
					'value' => $txt['custom_profile_fieldorder'],
				],
				'data' => [
					'function' => function ($rowData) use ($context, $txt, $scripturl)
					{
						$return = '<p class="centertext bold_text">'. $rowData['field_order'] .'<br />';

						if ($rowData['field_order'] > 1)
							$return .= '<a href="' . $scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $rowData['id_field'] . ';move=up"><span class="toggle_up" title="'. $txt['custom_edit_order_move'] .' '. $txt['custom_edit_order_up'] .'"></span></a>';

						if ($rowData['field_order'] < $context['custFieldsMaxOrder_ic'])
							$return .= '<a href="' . $scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $rowData['id_field'] . ';move=down"><span class="toggle_down" title="'. $txt['custom_edit_order_move'] .' '. $txt['custom_edit_order_down'] .'"></span></a>';

						$return .= '</p>';

						return $return;
					},
					'style' => 'width: 12%;',
				],
				'sort' => [
					'default' => 'field_order',
					'reverse' => 'field_order DESC',
				],
			],
			'field_name' => [
				'header' => [
					'value' => $txt['custom_profile_fieldname'],
				],
				'data' => [
					'function' => function ($rowData) use ($scripturl)
					{
						return sprintf('<a href="%1$s?action=admin;area=featuresettings;sa=profileedit;fid=%2$d">%3$s</a><div class="smalltext">%4$s</div>', $scripturl, $rowData['id_field'], $rowData['field_name'], $rowData['field_desc']);
					},
					'style' => 'width: 62%;',
				],
				'sort' => [
					'default' => 'field_name',
					'reverse' => 'field_name DESC',
				],
			],
			'field_type' => [
				'header' => [
					'value' => $txt['custom_profile_fieldtype'],
				],
				'data' => [
					'function' => function ($rowData) use ($txt)
					{
						$textKey = sprintf('custom_profile_type_%1$s', $rowData['field_type']);
						return isset($txt[$textKey]) ? $txt[$textKey] : $textKey;
					},
					'style' => 'width: 15%;',
					'class' => 'hidden',
				],
				'sort' => [
					'default' => 'field_type',
					'reverse' => 'field_type DESC',
				],
			],
			'active' => [
				'header' => [
					'value' => $txt['custom_profile_active'],
				],
				'data' => [
					'function' => function ($rowData) use ($txt)
					{
						return $rowData['active'] ? $txt['yes'] : $txt['no'];
					},
					'style' => 'width: 8%;',
					'class' => 'hidden',
				],
				'sort' => [
					'default' => 'active DESC',
					'reverse' => 'active',
				],
			],
			'placement' => [
				'header' => [
					'value' => $txt['custom_profile_placement'],
				],
				'data' => [
					'function' => function ($rowData)
					{
						global $txt, $context;

						return $txt['custom_profile_placement_' . (empty($rowData['placement']) ? 'standard' : $context['cust_profile_fields_placement'][$rowData['placement']])];
					},
					'style' => 'width: 8%;',
					'class' => 'hidden',
				],
				'sort' => [
					'default' => 'placement DESC',
					'reverse' => 'placement',
				],
			],
			'show_on_registration' => [
				'data' => [
					'sprintf' => [
						'format' => '<a href="' . $scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=%1$s">' . $txt['modify'] . '</a>',
						'params' => [
							'id_field' => false,
						],
					],
					'style' => 'width: 15%;',
				],
			],
		],
		'form' => [
			'href' => $scripturl . '?action=admin;area=featuresettings;sa=profileedit',
			'name' => 'customProfileFields',
		],
		'additional_rows' => [
			[
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="new_ic" value="' . $txt['custom_profile_make_new'] . '">',
			],
		],
	];
	createList($listOptions);

	$listOptions = [
		'id' => 'custom_profile_fields_ic',
		'title' => $txt['custom_profile_title_ooc_fields'],
		'base_href' => $scripturl . '?action=admin;area=featuresettings;sa=profile',
		'default_sort_col' => 'field_order',
		'no_items_label' => $txt['custom_profile_none_ooc'],
		'items_per_page' => 25,
		'get_items' => [
			'function' => 'list_getProfileFields',
			'params' => [
				false,
				false,
			],
		],
		'get_count' => [
			'function' => 'list_getProfileFieldSize',
		],
		'columns' => [
			'field_order' => [
				'header' => [
					'value' => $txt['custom_profile_fieldorder'],
				],
				'data' => [
					'function' => function ($rowData) use ($context, $txt, $scripturl)
					{
						$return = '<p class="centertext bold_text">'. $rowData['field_order'] .'<br />';

						if ($rowData['field_order'] > 1)
							$return .= '<a href="' . $scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $rowData['id_field'] . ';move=up"><span class="toggle_up" title="'. $txt['custom_edit_order_move'] .' '. $txt['custom_edit_order_up'] .'"></span></a>';

						if ($rowData['field_order'] < $context['custFieldsMaxOrder_ooc'])
							$return .= '<a href="' . $scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $rowData['id_field'] . ';move=down"><span class="toggle_down" title="'. $txt['custom_edit_order_move'] .' '. $txt['custom_edit_order_down'] .'"></span></a>';

						$return .= '</p>';

						return $return;
					},
					'style' => 'width: 12%;',
				],
				'sort' => [
					'default' => 'field_order',
					'reverse' => 'field_order DESC',
				],
			],
			'field_name' => [
				'header' => [
					'value' => $txt['custom_profile_fieldname'],
				],
				'data' => [
					'function' => function ($rowData) use ($scripturl)
					{
						return sprintf('<a href="%1$s?action=admin;area=featuresettings;sa=profileedit;fid=%2$d">%3$s</a><div class="smalltext">%4$s</div>', $scripturl, $rowData['id_field'], $rowData['field_name'], $rowData['field_desc']);
					},
					'style' => 'width: 62%;',
				],
				'sort' => [
					'default' => 'field_name',
					'reverse' => 'field_name DESC',
				],
			],
			'field_type' => [
				'header' => [
					'value' => $txt['custom_profile_fieldtype'],
				],
				'data' => [
					'function' => function ($rowData) use ($txt)
					{
						$textKey = sprintf('custom_profile_type_%1$s', $rowData['field_type']);
						return isset($txt[$textKey]) ? $txt[$textKey] : $textKey;
					},
					'style' => 'width: 15%;',
					'class' => 'hidden',
				],
				'sort' => [
					'default' => 'field_type',
					'reverse' => 'field_type DESC',
				],
			],
			'active' => [
				'header' => [
					'value' => $txt['custom_profile_active'],
				],
				'data' => [
					'function' => function ($rowData) use ($txt)
					{
						return $rowData['active'] ? $txt['yes'] : $txt['no'];
					},
					'style' => 'width: 8%;',
					'class' => 'hidden',
				],
				'sort' => [
					'default' => 'active DESC',
					'reverse' => 'active',
				],
			],
			'placement' => [
				'header' => [
					'value' => $txt['custom_profile_placement'],
				],
				'data' => [
					'function' => function ($rowData)
					{
						global $txt, $context;

						return $txt['custom_profile_placement_' . (empty($rowData['placement']) ? 'standard' : $context['cust_profile_fields_placement'][$rowData['placement']])];
					},
					'style' => 'width: 8%;',
					'class' => 'hidden',
				],
				'sort' => [
					'default' => 'placement DESC',
					'reverse' => 'placement',
				],
			],
			'show_on_registration' => [
				'data' => [
					'sprintf' => [
						'format' => '<a href="' . $scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=%1$s">' . $txt['modify'] . '</a>',
						'params' => [
							'id_field' => false,
						],
					],
					'style' => 'width: 15%;',
				],
			],
		],
		'form' => [
			'href' => $scripturl . '?action=admin;area=featuresettings;sa=profileedit',
			'name' => 'customProfileFields',
		],
		'additional_rows' => [
			[
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="new_ooc" value="' . $txt['custom_profile_make_new'] . '">',
			],
		],
	];
	createList($listOptions);
}

/**
 * Callback for createList().
 * @param int $start The item to start with (used for pagination purposes)
 * @param int $items_per_page The number of items to display per page
 * @param string $sort A string indicating how to sort the results
 * @param bool $standardFields Whether or not to include standard fields as well
 * @return array An array of info about the various profile fields
 */
function list_getProfileFields($start, $items_per_page, $sort, $standardFields, $in_character = false)
{
	global $txt, $modSettings, $smcFunc;

	$list = [];

	if ($standardFields)
	{
		$standard_fields = ['timezone', 'posts', 'warning_status'];
		$fields_no_registration = ['posts', 'warning_status'];
		$disabled_fields = isset($modSettings['disabled_profile_fields']) ? explode(',', $modSettings['disabled_profile_fields']) : [];
		$registration_fields = isset($modSettings['registration_fields']) ? explode(',', $modSettings['registration_fields']) : [];

		foreach ($standard_fields as $field)
			$list[] = [
				'id' => $field,
				'label' => isset($txt['standard_profile_field_' . $field]) ? $txt['standard_profile_field_' . $field] : (isset($txt[$field]) ? $txt[$field] : $field),
				'disabled' => in_array($field, $disabled_fields),
				'on_register' => in_array($field, $registration_fields) && !in_array($field, $fields_no_registration),
				'can_show_register' => !in_array($field, $fields_no_registration),
			];
	}
	else
	{
		// Load all the fields.
		$request = $smcFunc['db']->query('', '
			SELECT id_field, col_name, field_name, field_desc, field_type, field_order, active, placement
			FROM {db_prefix}custom_fields
			WHERE in_character = {int:in_character}
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:items_per_page}',
			[
				'sort' => $sort,
				'start' => $start,
				'items_per_page' => $items_per_page,
				'in_character' => $in_character ? 1 : 0,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$list[] = $row;
		$smcFunc['db']->free_result($request);
	}

	return $list;
}

/**
 * Callback for createList().
 * @return int The total number of custom profile fields
 */
function list_getProfileFieldSize()
{
	global $smcFunc;

	$request = $smcFunc['db']->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}custom_fields',
		[
		]
	);

	list ($numProfileFields) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	return $numProfileFields;
}

/**
 * Edit some profile fields?
 */
function EditCustomProfiles()
{
	global $txt, $scripturl, $context, $smcFunc;

	// Sort out the context!
	$context['fid'] = isset($_GET['fid']) ? (int) $_GET['fid'] : 0;
	$context[$context['admin_menu_name']]['current_subsection'] = 'profile';
	$context['page_title'] = $context['fid'] ? $txt['custom_edit_title'] : (!empty($_REQUEST['new_ic']) ? $txt['custom_add_title_ic'] : $txt['custom_add_title_ooc']);
	$context['sub_template'] = 'admin_profile_fields_edit';
	StoryBB\Template::add_helper([
		'begins_with' => function($string, $test)
		{
			return strpos($string, $test) === 0;
		}
	]);

	// Load the profile language for section names.
	loadLanguage('Profile');

	// There's really only a few places we can go...
	$move_to = ['up', 'down'];

	if ($context['fid'])
	{
		$request = $smcFunc['db']->query('', '
			SELECT
				id_field, col_name, field_name, field_desc, field_type, field_order, field_length, field_options,
				show_reg, show_display, show_profile, private, active, default_value, can_search,
				bbc, mask, enclose, placement, in_character
			FROM {db_prefix}custom_fields
			WHERE id_field = {int:current_field}',
			[
				'current_field' => $context['fid'],
			]
		);
		$context['field'] = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if ($row['field_type'] == 'textarea')
				@list ($rows, $cols) = @explode(',', $row['default_value']);
			else
			{
				$rows = 3;
				$cols = 30;
			}

			$context['field'] = [
				'name' => $row['field_name'],
				'desc' => $row['field_desc'],
				'col_name' => $row['col_name'],
				'profile_area' => $row['show_profile'],
				'reg' => $row['show_reg'],
				'display' => $row['show_display'],
				'type' => $row['field_type'],
				'order' => $row['field_order'],
				'max_length' => $row['field_length'],
				'rows' => $rows,
				'cols' => $cols,
				'bbc' => $row['bbc'] ? true : false,
				'default_check' => $row['field_type'] == 'check' && $row['default_value'] ? true : false,
				'default_select' => $row['field_type'] == 'select' || $row['field_type'] == 'radio' ? $row['default_value'] : '',
				'options' => strlen($row['field_options']) > 1 ? explode(',', $row['field_options']) : ['', '', ''],
				'active' => $row['active'],
				'private' => $row['private'],
				'can_search' => $row['can_search'],
				'mask' => $row['mask'],
				'regex' => substr($row['mask'], 0, 5) == 'regex' ? substr($row['mask'], 5) : '',
				'enclose' => $row['enclose'],
				'placement' => $row['placement'],
				'in_character' => (int) $row['in_character'],
			];
		}
		$smcFunc['db']->free_result($request);
	}

	// Setup the default values as needed.
	if (empty($context['field']))
		$context['field'] = [
			'name' => '',
			'col_name' => '???',
			'desc' => '',
			'profile_area' => 'account',
			'reg' => false,
			'display' => false,
			'mlist' => false,
			'type' => 'text',
			'order' => 0,
			'max_length' => 255,
			'rows' => 4,
			'cols' => 30,
			'bbc' => false,
			'default_check' => false,
			'default_select' => '',
			'options' => ['', '', ''],
			'active' => true,
			'private' => false,
			'can_search' => false,
			'mask' => 'nohtml',
			'regex' => '',
			'enclose' => '',
			'placement' => 0,
			'in_character' => !empty($_REQUEST['new_ic']) ? 1 : 0,
		];

	$order_count = custFieldsMaxOrder(!empty($context['field']['in_character']));

	// Are we moving it?
	if (isset($_GET['move']) && in_array(StringLibrary::escape($_GET['move']), $move_to))
	{
		// Down is the new up.
		$new_order = ($_GET['move'] == 'up' ? ($context['field']['order'] - 1) : ($context['field']['order'] + 1));

		// Is this a valid position?
		if ($new_order <= 0 || $new_order > $order_count)
			redirectexit('action=admin;area=featuresettings;sa=profile'); // @todo implement an error handler

		// All good, proceed.
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}custom_fields
			SET field_order = {int:old_order}
			WHERE field_order = {int:new_order}
				AND in_character = {int:in_character}',
			[
				'new_order' => $new_order,
				'old_order' => $context['field']['order'],
				'in_character' => $context['field']['in_character'] ? 1 : 0,
			]
		);
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}custom_fields
			SET field_order = {int:new_order}
			WHERE id_field = {int:id_field}',
			[
				'new_order' => $new_order,
				'id_field' => $context['fid'],
			]
		);
		redirectexit('action=admin;area=featuresettings;sa=profile'); // @todo perhaps a nice confirmation message, dunno.
	}

	// Are we saving?
	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('admin-ecp');

		// Everyone needs a name - even the (bracket) unknown...
		if (trim($_POST['field_name']) == '')
		{
			loadLanguage('Errors');
			session_flash('error', $txt['custom_option_need_name']);
			redirectexit($scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $_GET['fid'] . ';new_ic=' . ($context['field']['in_character'] ? '1' : '0'));
		}

		// Regex you say?  Do a very basic test to see if the pattern is valid
		if (!empty($_POST['regex']) && @preg_match($_POST['regex'], 'dummy') === false)
		{
			loadLanguage('Errors');
			session_flash('error', $txt['custom_option_regex_error']);
			redirectexit($scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $_GET['fid'] . ';new_ic=' . ($context['field']['in_character'] ? '1' : '0'));
		}

		$_POST['field_name'] = StringLibrary::escape($_POST['field_name']);
		$_POST['field_desc'] = StringLibrary::escape($_POST['field_desc']);

		// Checkboxes...
		$show_reg = !$context['field']['in_character'] && isset($_POST['reg']) ? (int) $_POST['reg'] : 0;
		$show_display = isset($_POST['display']) ? 1 : 0;
		$bbc = isset($_POST['bbc']) ? 1 : 0;
		if ($context['field']['in_character'])
		{
			$valid_profile = ['none', 'char'];
		}
		else
		{
			$valid_profile = ['none', 'account', 'prefs'];
		}
		$show_profile = in_array($_POST['profile_area'], $valid_profile) ? $_POST['profile_area'] : 'none';
		$active = isset($_POST['active']) ? 1 : 0;
		$private = !$context['field']['in_character'] && isset($_POST['private']) ? (int) $_POST['private'] : 0;
		$can_search = !$context['field']['in_character'] && isset($_POST['can_search']) ? 1 : 0;

		// Some masking stuff...
		$mask = isset($_POST['mask']) ? $_POST['mask'] : '';
		if ($mask == 'regex' && isset($_POST['regex']))
			$mask .= $_POST['regex'];

		$field_length = isset($_POST['max_length']) ? (int) $_POST['max_length'] : 255;
		$enclose = isset($_POST['enclose']) ? $_POST['enclose'] : '';
		$placement = isset($_POST['placement']) ? (int) $_POST['placement'] : 0;

		// Select options?
		$field_options = '';
		$newOptions = [];
		$default = isset($_POST['default_check']) && $_POST['field_type'] == 'check' ? 1 : '';
		if (!empty($_POST['select_option']) && ($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio'))
		{
			foreach ($_POST['select_option'] as $k => $v)
			{
				// Clean, clean, clean...
				$v = StringLibrary::escape($v);
				$v = strtr($v, [',' => '']);

				// Nada, zip, etc...
				if (trim($v) == '')
					continue;

				// Otherwise, save it boy.
				$field_options .= $v . ',';
				// This is just for working out what happened with old options...
				$newOptions[$k] = $v;

				// Is it default?
				if (isset($_POST['default_select']) && $_POST['default_select'] == $k)
					$default = $v;
			}
			$field_options = substr($field_options, 0, -1);
		}

		// Text area has default has dimensions
		if ($_POST['field_type'] == 'textarea')
			$default = (int) $_POST['rows'] . ',' . (int) $_POST['cols'];

		// Come up with the unique name?
		if (empty($context['fid']))
		{
			$col_name = StringLibrary::substr(strtr($_POST['field_name'], [' ' => '']), 0, 6);
			preg_match('~([\w\d_-]+)~', $col_name, $matches);

			// If there is nothing to the name, then let's start out own - for foreign languages etc.
			if (isset($matches[1]))
				$col_name = $initial_col_name = 'cust_' . strtolower($matches[1]);
			else
				$col_name = $initial_col_name = 'cust_' . mt_rand(1, 9999);

			// Make sure this is unique.
			$current_fields = [];
			$request = $smcFunc['db']->query('', '
				SELECT id_field, col_name
				FROM {db_prefix}custom_fields');
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$current_fields[$row['id_field']] = $row['col_name'];
			$smcFunc['db']->free_result($request);

			$unique = false;
			for ($i = 0; !$unique && $i < 9; $i ++)
			{
				if (!in_array($col_name, $current_fields))
					$unique = true;
				else
					$col_name = $initial_col_name . $i;
			}

			// Still not a unique column name? Leave it up to the user, then.
			if (!$unique)
				fatal_lang_error('custom_option_not_unique');
		}
		// Work out what to do with the user data otherwise...
		else
		{
			// Anything going to check or select is pointless keeping - as is anything coming from check!
			if (($_POST['field_type'] == 'check' && $context['field']['type'] != 'check')
				|| (($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio') && $context['field']['type'] != 'select' && $context['field']['type'] != 'radio')
				|| ($context['field']['type'] == 'check' && $_POST['field_type'] != 'check'))
			{
				$smcFunc['db']->query('', '
					DELETE FROM {db_prefix}themes
					WHERE variable = {string:current_column}
						AND id_member > {int:no_member}',
					[
						'no_member' => 0,
						'current_column' => $context['field']['col_name'],
					]
				);
			}
			// Otherwise - if the select is edited may need to adjust!
			elseif ($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio')
			{
				$optionChanges = [];
				$takenKeys = [];
				// Work out what's changed!
				foreach ($context['field']['options'] as $k => $option)
				{
					if (trim($option) == '')
						continue;

					// Still exists?
					if (in_array($option, $newOptions))
					{
						$takenKeys[] = $k;
						continue;
					}
				}

				// Finally - have we renamed it - or is it really gone?
				foreach ($optionChanges as $k => $option)
				{
					// Just been renamed?
					if (!in_array($k, $takenKeys) && !empty($newOptions[$k]))
						$smcFunc['db']->query('', '
							UPDATE {db_prefix}themes
							SET value = {string:new_value}
							WHERE variable = {string:current_column}
								AND value = {string:old_value}
								AND id_member > {int:no_member}',
							[
								'no_member' => 0,
								'new_value' => $newOptions[$k],
								'current_column' => $context['field']['col_name'],
								'old_value' => $option,
							]
						);
				}
			}
			// @todo Maybe we should adjust based on new text length limits?
		}

		// Do the insertion/updates.
		if ($context['fid'])
		{
			$smcFunc['db']->query('', '
				UPDATE {db_prefix}custom_fields
				SET
					field_name = {string:field_name}, field_desc = {string:field_desc},
					field_type = {string:field_type}, field_length = {int:field_length},
					field_options = {string:field_options}, show_reg = {int:show_reg},
					show_display = {int:show_display}, show_profile = {string:show_profile},
					private = {int:private}, active = {int:active}, default_value = {string:default_value},
					can_search = {int:can_search}, bbc = {int:bbc}, mask = {string:mask},
					enclose = {string:enclose}, placement = {int:placement}
				WHERE id_field = {int:current_field}',
				[
					'field_length' => $field_length,
					'show_reg' => $show_reg,
					'show_display' => $show_display,
					'private' => $private,
					'active' => $active,
					'can_search' => $can_search,
					'bbc' => $bbc,
					'current_field' => $context['fid'],
					'field_name' => $_POST['field_name'],
					'field_desc' => $_POST['field_desc'],
					'field_type' => $_POST['field_type'],
					'field_options' => $field_options,
					'show_profile' => $show_profile,
					'default_value' => $default,
					'mask' => $mask,
					'enclose' => $enclose,
					'placement' => $placement,
				]
			);

			// Just clean up any old selects - these are a pain!
			if (($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio') && !empty($newOptions))
			{
				$smcFunc['db']->query('', '
					DELETE FROM {db_prefix}custom_field_values
					WHERE id_field = {int:current_field}
						AND value NOT IN ({array_string:new_option_values})',
					[
						'current_field' => $context['fid'],
						'new_option_values' => $newOptions,
					]
				);
			}
		}
		else
		{
			// Gotta figure it out the order.
			$new_order = $order_count > 0 ? ($order_count + 1) : 1;

			$smcFunc['db']->insert('',
				'{db_prefix}custom_fields',
				[
					'col_name' => 'string', 'field_name' => 'string', 'field_desc' => 'string',
					'field_type' => 'string', 'field_length' => 'string', 'field_options' => 'string', 'field_order' => 'int',
					'show_reg' => 'int', 'show_display' => 'int', 'show_profile' => 'string',
					'private' => 'int', 'active' => 'int', 'default_value' => 'string', 'can_search' => 'int',
					'bbc' => 'int', 'mask' => 'string', 'enclose' => 'string', 'placement' => 'int', 'in_character' => 'int',
				],
				[
					$col_name, $_POST['field_name'], $_POST['field_desc'],
					$_POST['field_type'], $field_length, $field_options, $new_order,
					$show_reg, $show_display, $show_profile,
					$private, $active, $default, $can_search,
					$bbc, $mask, $enclose, $placement, $context['field']['in_character'],
				],
				['id_field']
			);
		}
	}
	// Deleting?
	elseif (isset($_POST['delete']) && $context['field']['col_name'])
	{
		checkSession();
		validateToken('admin-ecp');

		// Delete the user data first.
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}custom_field_values
			WHERE id_field = {int:current_field}',
			[
				'current_field' => $context['fid'],
			]
		);
		// Finally - the field itself is gone!
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}custom_fields
			WHERE id_field = {int:current_field}',
			[
				'current_field' => $context['fid'],
			]
		);

		// Re-arrange the order.
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}custom_fields
			SET field_order = field_order - 1
			WHERE field_order > {int:current_order}
				AND in_character = {int:in_character}',
			[
				'current_order' => $context['field']['order'],
				'in_character' => !empty($context['field']['in_character']) ? 1 : 0,
			]
		);
	}

	// Rebuild display cache etc.
	if (isset($_POST['delete']) || isset($_POST['save']))
	{
		checkSession();

		$request = $smcFunc['db']->query('', '
			SELECT id_field, col_name, field_name, field_type, field_order, bbc, enclose, placement, field_options, in_character
			FROM {db_prefix}custom_fields
			WHERE show_display = {int:is_displayed}
				AND active = {int:active}
				AND private != {int:not_owner_only}
				AND private != {int:not_admin_only}
			ORDER BY field_order',
			[
				'is_displayed' => 1,
				'active' => 1,
				'not_owner_only' => 2,
				'not_admin_only' => 3,
			]
		);

		$fields = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$fields[$row['in_character'] ? 'ic' : 'ooc'][] = [
				'id' => $row['id_field'],
				'col_name' => strtr($row['col_name'], ['|' => '', ';' => '']),
				'title' => strtr($row['field_name'], ['|' => '', ';' => '']),
				'type' => $row['field_type'],
				'order' => $row['field_order'],
				'bbc' => $row['bbc'] ? '1' : '0',
				'placement' => !empty($row['placement']) ? $row['placement'] : '0',
				'enclose' => !empty($row['enclose']) ? $row['enclose'] : '',
				'options' => (!empty($row['field_options']) ? explode(',', $row['field_options']) : []),
			];
		}
		$smcFunc['db']->free_result($request);

		updateSettings(['displayFields' => json_encode($fields)]);
		session_flash('success', $txt['settings_saved']);
		redirectexit('action=admin;area=featuresettings;sa=profile');
	}

	createToken('admin-ecp');
}

/**
 * Returns the maximum field_order value for the custom fields
 * @return int The maximum value of field_order from the custom_fields table
 */
function custFieldsMaxOrder($in_character)
{
	global $smcFunc;

	// Gotta know the order limit
	$result = $smcFunc['db']->query('', '
		SELECT MAX(field_order)
		FROM {db_prefix}custom_fields
		WHERE in_character = {int:in_character}',
		[
			'in_character' => $in_character ? 1 : 0,
		]
	);

	list ($order_count) = $smcFunc['db']->fetch_row($result);
	$smcFunc['db']->free_result($result);

	return (int) $order_count;
}

/**
 * Allow to edit the settings on the pruning screen.
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyLogSettings($return_config = false)
{
	global $txt, $scripturl, $sourcedir, $context, $modSettings;

	// Make sure we understand what's going on.
	loadLanguage('ManageSettings');

	$context['page_title'] = $txt['log_settings'];

	$config_vars = [
			['check', 'modlog_enabled', 'help' => 'modlog'],
			['check', 'adminlog_enabled', 'help' => 'adminlog'],
			['check', 'userlog_enabled', 'help' => 'userlog'],
			// The error log is a wonderful thing.
			['title', 'errlog', 'desc' => $txt['error_log_desc']],
			['check', 'enableErrorLogging'],
			['check', 'enableErrorQueryLogging'],
			['check', 'log_ban_hits'],
			// Even do the pruning?
			['title', 'pruning_title', 'desc' => $txt['pruning_desc']],
			// The array indexes are there so we can remove/change them before saving.
			'pruningOptions' => ['check', 'pruningOptions'],
		'',
			// Various logs that could be pruned.
			['int', 'pruneErrorLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']], // Error log.
			['int', 'pruneModLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']], // Moderation log.
			['int', 'pruneBanLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']], // Ban hit log.
			['int', 'pruneReportLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']], // Report to moderator log.
			['int', 'pruneScheduledTaskLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']], // Log of the scheduled tasks and how long they ran.
			// If you add any additional logs make sure to add them after this point.  Additionally, make sure you add them to the weekly scheduled task.
			// Mod Developers: Do NOT use the pruningOptions master variable for this as StoryBB Core may overwrite your setting in the future!
		'',
			['int', 'retention_policy_standard', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_means_zero']],
			['int', 'retention_policy_sensitive', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_means_zero']],
	];

	// We want to be toggling some of these for a nice user experience. If you want to add yours to the list of those magically hidden when the 'pruning' option is off, add to this.
	$prune_toggle = ['pruneErrorLog', 'pruneModLog', 'pruneBanLog', 'pruneReportLog', 'pruneScheduledTaskLog'];

	settings_integration_hook('integrate_prune_settings', [&$config_vars, &$prune_toggle, false]);

	if ($return_config)
		return [$txt['log_settings'], $config_vars];

	if (!empty($prune_toggle))
	{
		addInlineJavaScript('
	$(document).ready(function () {
		$("input#pruningOptions").on("change", function() {
			var newval = $("input#pruningOptions").is(":checked");
			$("#' . implode(', #', $prune_toggle) . '").closest("dd").toggle(newval).prev("dt").toggle(newval);
		}).trigger("change");
	});', true);
	}

	// We'll need this in a bit.
	require_once($sourcedir . '/ManageServer.php');

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Because of the excitement attached to combining pruning log items, we need to duplicate everything here.
		$savevar = [
			['check', 'modlog_enabled'],
			['check', 'adminlog_enabled'],
			['check', 'userlog_enabled'],
			['check', 'enableErrorLogging'],
			['check', 'enableErrorQueryLogging'],
			['check', 'log_ban_hits'],
			['text', 'pruningOptions']
		];

		settings_integration_hook('integrate_prune_settings', [&$savevar, &$prune_toggle, true]);

		if (!empty($_POST['pruningOptions']))
		{
			$vals = [];
			foreach ($config_vars as $index => $dummy)
			{
				if (!is_array($dummy) || $index == 'pruningOptions' || !in_array($dummy[1], $prune_toggle))
					continue;

				$vals[] = empty($_POST[$dummy[1]]) || $_POST[$dummy[1]] < 0 ? 0 : (int) $_POST[$dummy[1]];
			}
			$_POST['pruningOptions'] = implode(',', $vals);
		}
		else
			$_POST['pruningOptions'] = '';

		saveDBSettings($savevar);
		session_flash('success', $txt['settings_saved']);
		redirectexit('action=admin;area=logs;sa=settings');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=logs;save;sa=settings';
	$context['settings_title'] = $txt['log_settings'];

	// Get the actual values
	if (!empty($modSettings['pruningOptions']))
	{
		@list ($modSettings['pruneErrorLog'], $modSettings['pruneModLog'], $modSettings['pruneBanLog'], $modSettings['pruneReportLog'], $modSettings['pruneScheduledTaskLog']) = explode(',', $modSettings['pruningOptions']);
		$modSettings['pruningOptions'] = 1;
	}
	else
		$modSettings['pruneErrorLog'] = $modSettings['pruneModLog'] = $modSettings['pruneBanLog'] = $modSettings['pruneReportLog'] = $modSettings['pruneScheduledTaskLog'] = 0;

	prepareDBSettingContext($config_vars);
}

/**
 * Handles modifying the alerts settings
 */
function ModifyAlertsSettings()
{
	global $context, $sourcedir, $txt;

	// Dummy settings for the template...
	$context['user']['is_owner'] = false;
	$context['member'] = [];
	$context['id_member'] = 0;
	$context['menu_item_selected'] = 'alerts';
	$context['token_check'] = 'noti-admin';

	// Specify our action since we'll want to post back here instead of the profile
	$context['action'] = 'action=admin;area=featuresettings;sa=alerts;'. $context['session_var'] .'='. $context['session_id'];

	loadLanguage('Profile');

	include_once($sourcedir . '/Profile-Modify.php');
	alert_configuration(0);

	$context['page_title'] = $txt['notify_settings'];

	// Override the description
	$context['description'] = $txt['notifications_desc'];
	$context['sub_template'] = 'profile_alert_configuration';
}
