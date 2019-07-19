<?php

/**
 * This file concerns itself almost completely with theme administration.
 * Its tasks include changing theme settings, installing and removing
 * themes, choosing the current theme, and editing themes.
 *
 * @todo Update this for the new package manager?
 *
 * Creating and distributing theme packages:
 * 	There isn't that much required to package and distribute your own themes...
 * just do the following:
 * - create a theme_info.xml file, with the root element theme-info.
 * - its name should go in a name element, just like description.
 * - your name should go in author. (email in the email attribute.)
 * - any support website for the theme should be in website.
 * - layers and templates (non-default) should go in those elements ;).
 * - if the images dir isn't images, specify in the images element.
 * - any extra rows for themes should go in extra, serialized. (as in array(variable => value).)
 * - tar and gzip the directory - and you're done!
 * - please include any special license in a license.txt file.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

/**
 * Subaction handler - manages the action and delegates control to the proper
 * sub-action.
 * It loads both the Themes and Settings language files.
 * Checks the session by GET or POST to verify the sent data.
 * Requires the user not be a guest. (@todo what?)
 * Accessed via ?action=admin;area=theme.
 */
function ThemesMain()
{
	global $txt, $context, $sourcedir;

	// Load the important language files...
	loadLanguage('Themes');
	loadLanguage('Settings');
	loadLanguage('Drafts');

	// No funny business - guests only.
	is_not_guest();

	require_once($sourcedir . '/Subs-Themes.php');

	// Default the page title to Theme Administration by default.
	$context['page_title'] = $txt['themeadmin_title'];

	// Theme administration, removal, choice, or installation...
	$subActions = array(
		'admin' => 'ThemeAdmin',
		'list' => 'ThemeList',
		'reset' => 'SetThemeOptions',
		'options' => 'SetThemeOptions',
		'install' => 'ThemeInstall',
		'remove' => 'RemoveTheme',
		'pick' => 'PickTheme',
		'enable' => 'EnableTheme',
		'copy' => 'CopyTemplate',
	);

	// @todo Layout Settings?  huh?
	if (!empty($context['admin_menu_name']))
	{
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['themeadmin_title'],
			'help' => 'themes',
			'description' => $txt['themeadmin_description'],
			'tabs' => array(
				'admin' => array(
					'description' => $txt['themeadmin_admin_desc'],
				),
				'list' => array(
					'description' => $txt['themeadmin_list_desc'],
				),
				'reset' => array(
					'description' => $txt['themeadmin_reset_desc'],
				),
			),
		);
	}

	// CRUD $subActions as needed.
	call_integration_hook('integrate_manage_themes', array(&$subActions));

	// Whatever you decide to do, clean the minify cache.
	cache_put_data('minimized_css', null);

	// Follow the sa or just go to administration.
	if (isset($_GET['sa']) && !empty($subActions[$_GET['sa']]))
		call_helper($subActions[$_GET['sa']]);

	else
		call_helper($subActions['admin']);
}

/**
 * This function allows administration of themes and their settings,
 * as well as global theme settings.
 *  - sets the settings theme_allow, theme_guests, and knownThemes.
 *  - requires the admin_forum permission.
 *  - accessed with ?action=admin;area=theme;sa=admin.
 *
 *  @uses Themes template
 *  @uses Admin language file
 */
function ThemeAdmin()
{
	global $context, $boarddir;

	// Are handling any settings?
	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('admin-tm');

		if (isset($_POST['options']['known_themes']))
			foreach ($_POST['options']['known_themes'] as $key => $id)
				$_POST['options']['known_themes'][$key] = (int) $id;

		else
			fatal_lang_error('themes_none_selectable', false);

		if (!in_array($_POST['options']['theme_guests'], $_POST['options']['known_themes']))
			fatal_lang_error('themes_default_selectable', false);

		// Commit the new settings.
		updateSettings(array(
			'theme_allow' => $_POST['options']['theme_allow'],
			'theme_guests' => $_POST['options']['theme_guests'],
			'knownThemes' => implode(',', $_POST['options']['known_themes']),
		));
		if ((int) $_POST['theme_reset'] == 0 || in_array($_POST['theme_reset'], $_POST['options']['known_themes']))
			updateMemberData(null, array('id_theme' => (int) $_POST['theme_reset']));

		redirectexit('action=admin;area=theme;' . $context['session_var'] . '=' . $context['session_id'] . ';sa=admin');
	}

	loadLanguage('Admin');
	isAllowedTo('admin_forum');
	$context['sub_template'] = 'admin_themes_main';

	// List all installed and enabled themes.
	get_all_themes(true);

	// Can we create a new theme?
	$context['can_create_new'] = is_writable($boarddir . '/Themes');
	$context['new_theme_dir'] = substr(realpath($boarddir . '/Themes/default'), 0, -7);

	// Look for a non existent theme directory. (ie theme87.)
	$theme_dir = $boarddir . '/Themes/theme';
	$i = 1;
	while (file_exists($theme_dir . $i))
		$i++;

	$context['new_theme_name'] = 'theme' . $i;

	// A bunch of tokens for a bunch of forms.
	createToken('admin-tm');
	createToken('admin-t-file');
	createToken('admin-t-copy');
	createToken('admin-t-dir');
}

/**
 * This function lists the available themes and provides an interface to reset
 * the paths of all the installed themes.
 */
function ThemeList()
{
	global $context, $boarddir, $boardurl, $smcFunc;

	loadLanguage('Admin');
	isAllowedTo('admin_forum');

	if (isset($_REQUEST['th']))
		return SetThemeSettings();
		
	if (isset($_GET['done']))
		$context['done'] = $_GET['done'];
	else
		$context['done'] = false;

	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('admin-tl');

		// Calling the almighty power of global vars!
		get_all_themes(false);

		$setValues = [];
		foreach ($context['themes'] as $id => $theme)
		{
			if (file_exists($_POST['reset_dir'] . '/' . basename($theme['theme_dir'])))
			{
				$setValues[] = array($id, 0, 'theme_dir', realpath($_POST['reset_dir'] . '/' . basename($theme['theme_dir'])));
				$setValues[] = array($id, 0, 'theme_url', $_POST['reset_url'] . '/' . basename($theme['theme_dir']));
				$setValues[] = array($id, 0, 'images_url', $_POST['reset_url'] . '/' . basename($theme['theme_dir']) . '/' . basename($theme['images_url']));
			}

			if (isset($theme['base_theme_dir']) && file_exists($_POST['reset_dir'] . '/' . basename($theme['base_theme_dir'])))
			{
				$setValues[] = array($id, 0, 'base_theme_dir', realpath($_POST['reset_dir'] . '/' . basename($theme['base_theme_dir'])));
				$setValues[] = array($id, 0, 'base_theme_url', $_POST['reset_url'] . '/' . basename($theme['base_theme_dir']));
				$setValues[] = array($id, 0, 'base_images_url', $_POST['reset_url'] . '/' . basename($theme['base_theme_dir']) . '/' . basename($theme['base_images_url']));
			}

			cache_put_data('theme_settings-' . $id, null, 90);
		}

		if (!empty($setValues))
		{
			$smcFunc['db_insert']('replace',
				'{db_prefix}themes',
				array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				$setValues,
				array('id_theme', 'variable', 'id_member')
			);
		}

		redirectexit('action=admin;area=theme;sa=list;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Get all installed themes.
	get_all_themes(false);

	$context['reset_dir'] = realpath($boarddir . '/Themes');
	$context['reset_url'] = $boardurl . '/Themes';

	$context['sub_template'] = 'admin_themes_list';
	createToken('admin-tl');
	createToken('admin-tr', 'request');
	createToken('admin-tre', 'request');
}

/**
 * Administrative global settings.
 */
function SetThemeOptions()
{
	global $txt, $context, $settings, $modSettings, $smcFunc;

	$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);

	isAllowedTo('admin_forum');

	if (empty($_GET['th']) && empty($_GET['id']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE variable IN ({string:name}, {string:theme_dir})
				AND id_member = {int:no_member}',
			array(
				'no_member' => 0,
				'name' => 'name',
				'theme_dir' => 'theme_dir',
			)
		);
		$context['themes'] = [];
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!isset($context['themes'][$row['id_theme']]))
				$context['themes'][$row['id_theme']] = array(
					'id' => $row['id_theme'],
					'num_default_options' => 0,
					'num_members' => 0,
				);
			$context['themes'][$row['id_theme']][$row['variable']] = $row['value'];
		}
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT id_theme, COUNT(*) AS value
			FROM {db_prefix}themes
			WHERE id_member = {int:guest_member}
			GROUP BY id_theme',
			array(
				'guest_member' => -1,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['themes'][$row['id_theme']]['num_default_options'] = $row['value'];
		$smcFunc['db_free_result']($request);

		// Need to make sure we don't do custom fields.
		$request = $smcFunc['db_query']('', '
			SELECT col_name
			FROM {db_prefix}custom_fields',
			array(
			)
		);
		$customFields = [];
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$customFields[] = $row['col_name'];
		$smcFunc['db_free_result']($request);
		$customFieldsQuery = empty($customFields) ? '' : ('AND variable NOT IN ({array_string:custom_fields})');

		$request = $smcFunc['db_query']('themes_count', '
			SELECT COUNT(DISTINCT id_member) AS value, id_theme
			FROM {db_prefix}themes
			WHERE id_member > {int:no_member}
				' . $customFieldsQuery . '
			GROUP BY id_theme',
			array(
				'no_member' => 0,
				'custom_fields' => empty($customFields) ? [] : $customFields,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['themes'][$row['id_theme']]['num_members'] = $row['value'];
		$smcFunc['db_free_result']($request);

		// There has to be a Settings template!
		foreach ($context['themes'] as $k => $v)
			if (empty($v['theme_dir']) || (!file_exists($v['theme_dir'] . '/Settings.template.php') && empty($v['num_members'])))
				unset($context['themes'][$k]);

		$context['sub_template'] = 'admin_themes_reset_list';

		createToken('admin-stor', 'request');
		return;
	}

	// Submit?
	if (isset($_POST['submit']) && empty($_POST['who']))
	{
		checkSession();
		validateToken('admin-sto');

		if (empty($_POST['options']))
			$_POST['options'] = [];
		if (empty($_POST['default_options']))
			$_POST['default_options'] = [];

		// Set up the sql query.
		$setValues = [];

		foreach ($_POST['options'] as $opt => $val)
			$setValues[] = array(-1, $_GET['th'], $opt, is_array($val) ? implode(',', $val) : $val);

		$old_settings = [];
		foreach ($_POST['default_options'] as $opt => $val)
		{
			$old_settings[] = $opt;

			$setValues[] = array(-1, 1, $opt, is_array($val) ? implode(',', $val) : $val);
		}

		// If we're actually inserting something..
		if (!empty($setValues))
		{
			// Are there options in non-default themes set that should be cleared?
			if (!empty($old_settings))
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}themes
					WHERE id_theme != {int:default_theme}
						AND id_member = {int:guest_member}
						AND variable IN ({array_string:old_settings})',
					array(
						'default_theme' => 1,
						'guest_member' => -1,
						'old_settings' => $old_settings,
					)
				);

			$smcFunc['db_insert']('replace',
				'{db_prefix}themes',
				array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				$setValues,
				array('id_theme', 'variable', 'id_member')
			);
		}

		cache_put_data('theme_settings-' . $_GET['th'], null, 90);
		cache_put_data('theme_settings-1', null, 90);

		redirectexit('action=admin;area=theme;' . $context['session_var'] . '=' . $context['session_id'] . ';sa=reset');
	}
	elseif (isset($_POST['submit']) && $_POST['who'] == 1)
	{
		checkSession();
		validateToken('admin-sto');

		$_POST['options'] = empty($_POST['options']) ? [] : $_POST['options'];
		$_POST['options_master'] = empty($_POST['options_master']) ? [] : $_POST['options_master'];
		$_POST['default_options'] = empty($_POST['default_options']) ? [] : $_POST['default_options'];
		$_POST['default_options_master'] = empty($_POST['default_options_master']) ? [] : $_POST['default_options_master'];

		$old_settings = [];
		foreach ($_POST['default_options'] as $opt => $val)
		{
			if ($_POST['default_options_master'][$opt] == 0)
				continue;
			elseif ($_POST['default_options_master'][$opt] == 1)
			{
				// Delete then insert for ease of database compatibility!
				$smcFunc['db_query']('substring', '
					DELETE FROM {db_prefix}themes
					WHERE id_theme = {int:default_theme}
						AND id_member != {int:no_member}
						AND variable = SUBSTRING({string:option}, 1, 255)',
					array(
						'default_theme' => 1,
						'no_member' => 0,
						'option' => $opt,
					)
				);
				$smcFunc['db_query']('substring', '
					INSERT INTO {db_prefix}themes
						(id_member, id_theme, variable, value)
					SELECT id_member, 1, SUBSTRING({string:option}, 1, 255), SUBSTRING({string:value}, 1, 65534)
					FROM {db_prefix}members',
					array(
						'option' => $opt,
						'value' => (is_array($val) ? implode(',', $val) : $val),
					)
				);

				$old_settings[] = $opt;
			}
			elseif ($_POST['default_options_master'][$opt] == 2)
			{
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}themes
					WHERE variable = {string:option_name}
						AND id_member > {int:no_member}',
					array(
						'no_member' => 0,
						'option_name' => $opt,
					)
				);
			}
		}

		// Delete options from other themes.
		if (!empty($old_settings))
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}themes
				WHERE id_theme != {int:default_theme}
					AND id_member > {int:no_member}
					AND variable IN ({array_string:old_settings})',
				array(
					'default_theme' => 1,
					'no_member' => 0,
					'old_settings' => $old_settings,
				)
			);

		foreach ($_POST['options'] as $opt => $val)
		{
			if ($_POST['options_master'][$opt] == 0)
				continue;
			elseif ($_POST['options_master'][$opt] == 1)
			{
				// Delete then insert for ease of database compatibility - again!
				$smcFunc['db_query']('substring', '
					DELETE FROM {db_prefix}themes
					WHERE id_theme = {int:current_theme}
						AND id_member != {int:no_member}
						AND variable = SUBSTRING({string:option}, 1, 255)',
					array(
						'current_theme' => $_GET['th'],
						'no_member' => 0,
						'option' => $opt,
					)
				);
				$smcFunc['db_query']('substring', '
					INSERT INTO {db_prefix}themes
						(id_member, id_theme, variable, value)
					SELECT id_member, {int:current_theme}, SUBSTRING({string:option}, 1, 255), SUBSTRING({string:value}, 1, 65534)
					FROM {db_prefix}members',
					array(
						'current_theme' => $_GET['th'],
						'option' => $opt,
						'value' => (is_array($val) ? implode(',', $val) : $val),
					)
				);
			}
			elseif ($_POST['options_master'][$opt] == 2)
			{
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}themes
					WHERE variable = {string:option}
						AND id_member > {int:no_member}
						AND id_theme = {int:current_theme}',
					array(
						'no_member' => 0,
						'current_theme' => $_GET['th'],
						'option' => $opt,
					)
				);
			}
		}

		redirectexit('action=admin;area=theme;' . $context['session_var'] . '=' . $context['session_id'] . ';sa=reset');
	}
	elseif (!empty($_GET['who']) && $_GET['who'] == 2)
	{
		checkSession('get');
		validateToken('admin-stor', 'request');

		// Don't delete custom fields!!
		if ($_GET['th'] == 1)
		{
			$request = $smcFunc['db_query']('', '
				SELECT col_name
				FROM {db_prefix}custom_fields',
				array(
				)
			);
			$customFields = [];
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$customFields[] = $row['col_name'];
			$smcFunc['db_free_result']($request);
		}
		$customFieldsQuery = empty($customFields) ? '' : ('AND variable NOT IN ({array_string:custom_fields})');

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}themes
			WHERE id_member > {int:no_member}
				AND id_theme = {int:current_theme}
				' . $customFieldsQuery,
			array(
				'no_member' => 0,
				'current_theme' => $_GET['th'],
				'custom_fields' => empty($customFields) ? [] : $customFields,
			)
		);

		redirectexit('action=admin;area=theme;' . $context['session_var'] . '=' . $context['session_id'] . ';sa=reset');
	}

	$old_id = $settings['theme_id'];
	$old_settings = $settings;

	loadTheme($_GET['th'], false);

	loadLanguage('Profile');
	// @todo Should we just move these options so they are no longer theme dependant?
	loadLanguage('PersonalMessage');

	// Let the theme take care of the settings.
	$context['theme_options'] = StoryBB\Model\Theme::get_user_options();

	$context['sub_template'] = 'admin_themes_options';
	$context['page_title'] = $txt['theme_settings'];

	$context['options'] = $context['theme_options'];
	$context['theme_settings'] = $settings;

	if (empty($_REQUEST['who']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT variable, value
			FROM {db_prefix}themes
			WHERE id_theme IN (1, {int:current_theme})
				AND id_member = {int:guest_member}',
			array(
				'current_theme' => $_GET['th'],
				'guest_member' => -1,
			)
		);
		$context['theme_options'] = [];
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['theme_options'][$row['variable']] = $row['value'];
		$smcFunc['db_free_result']($request);

		$context['theme_options_reset'] = false;
	}
	else
	{
		$context['theme_options'] = [];
		$context['theme_options_reset'] = true;
	}

	foreach ($context['options'] as $i => $setting)
	{
		// Just skip separators
		if (!is_array($setting))
			continue;

		// Is this disabled?
		if (($setting['id'] == 'topics_per_page' || $setting['id'] == 'messages_per_page') && !empty($modSettings['disableCustomPerPage']))
		{
			unset($context['options'][$i]);
			continue;
		}

		if (!isset($setting['type']) || $setting['type'] == 'bool')
			$context['options'][$i]['type'] = 'checkbox';
		elseif ($setting['type'] == 'int' || $setting['type'] == 'integer')
			$context['options'][$i]['type'] = 'number';
		elseif ($setting['type'] == 'string')
			$context['options'][$i]['type'] = 'text';

		if (isset($setting['options']))
			$context['options'][$i]['type'] = 'list';

		$context['options'][$i]['value'] = !isset($context['theme_options'][$setting['id']]) ? '' : $context['theme_options'][$setting['id']];
	}

	// Restore the existing theme.
	loadTheme($old_id, false);
	$settings = $old_settings;

	createToken('admin-sto');
}

/**
 * Administrative global settings.
 * - saves and requests global theme settings. ($settings)
 * - loads the Admin language file.
 * - calls ThemeAdmin() if no theme is specified. (the theme center.)
 * - requires admin_forum permission.
 * - accessed with ?action=admin;area=theme;sa=list&th=xx.
 */
function SetThemeSettings()
{
	global $txt, $context, $settings, $modSettings, $smcFunc;

	if (empty($_GET['th']) && empty($_GET['id']))
		return ThemeAdmin();

	$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];

	// Select the best fitting tab.
	$context[$context['admin_menu_name']]['current_subsection'] = 'list';

	loadLanguage('Admin');
	isAllowedTo('admin_forum');

	// Validate inputs/user.
	if (empty($_GET['th']))
		fatal_lang_error('no_theme', false);

	$old_id = $settings['theme_id'];
	$old_settings = $settings;

	loadTheme($_GET['th'], false);

	// Also load the actual themes language file - in case of special settings.
	loadLanguage('Settings', '', true, true);

	// And the custom language strings...
	loadLanguage('ThemeStrings', '', false, true);

	// Let the theme take care of the settings.
	$context['theme_settings'] = StoryBB\Model\Theme::get_theme_settings();

	// Load the variants separately...
	$settings['theme_variants'] = [];
	if (file_exists($settings['theme_dir'] . '/index.template.php'))
	{
		$file_contents = implode('', file($settings['theme_dir'] . '/index.template.php'));
		if (preg_match('~\$settings\[\'theme_variants\'\]\s*=(.+?);~', $file_contents, $matches))
				eval('global $settings;' . $matches[0]);
	}

	// Submitting!
	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('admin-sts');

		if (empty($_POST['options']))
			$_POST['options'] = [];
		if (empty($_POST['default_options']))
			$_POST['default_options'] = [];

		// Make sure items are cast correctly.
		foreach ($context['theme_settings'] as $item)
		{
			// Disregard this item if this is just a separator.
			if (!is_array($item))
				continue;

			foreach (array('options', 'default_options') as $option)
			{
				if (!isset($_POST[$option][$item['id']]))
					continue;
				elseif (isset($item['options']))
				{
					// Dropdown. If not a valid value, pick the first item in the list.
					if (!isset($item['options'][$_POST[$option][$item['id']]]))
					{
						$_POST[$option][$item['id']] = array_keys($item['options'])[0];
					}
				}
				elseif (empty($item['type']))
				{
					// Checkbox.
					$_POST[$option][$item['id']] = $_POST[$option][$item['id']] ? 1 : 0;
				}
				elseif ($item['type'] == 'number')
				{
					// Number
					$_POST[$option][$item['id']] = (int) $_POST[$option][$item['id']];
				}
			}
		}

		// Set up the sql query.
		$inserts = [];
		foreach ($_POST['options'] as $opt => $val)
			$inserts[] = array(0, $_GET['th'], $opt, is_array($val) ? implode(',', $val) : $val);
		foreach ($_POST['default_options'] as $opt => $val)
			$inserts[] = array(0, 1, $opt, is_array($val) ? implode(',', $val) : $val);
		// If we're actually inserting something..
		if (!empty($inserts))
		{
			$smcFunc['db_insert']('replace',
				'{db_prefix}themes',
				array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				$inserts,
				array('id_member', 'id_theme', 'variable')
			);
		}

		cache_put_data('theme_settings-' . $_GET['th'], null, 90);
		cache_put_data('theme_settings-1', null, 90);

		// Invalidate the cache.
		updateSettings(array('settings_updated' => time()));

		redirectexit('action=admin;area=theme;sa=list;th=' . $_GET['th'] . ';' . $context['session_var'] . '=' . $context['session_id']);
	}

	$context['sub_template'] = 'admin_themes_settings';
	$context['page_title'] = $txt['theme_settings'];

	foreach ($settings as $setting => $dummy)
	{
		if (!in_array($setting, array('theme_url', 'theme_dir', 'images_url', 'template_dirs')))
			$settings[$setting] = htmlspecialchars__recursive($settings[$setting]);
	}

	$context['settings'] = $context['theme_settings'];
	$context['theme_settings'] = $settings;
	
	

	foreach ($context['settings'] as $i => $setting)
	{
		// Separators are dummies, so leave them alone.
		if (!is_array($setting))
			continue;

		if (!isset($setting['type']) || $setting['type'] == 'bool')
			$context['settings'][$i]['type'] = 'checkbox';
		elseif ($setting['type'] == 'int' || $setting['type'] == 'integer')
			$context['settings'][$i]['type'] = 'number';
		elseif ($setting['type'] == 'string')
			$context['settings'][$i]['type'] = 'text';

		if (isset($setting['options']))
			$context['settings'][$i]['type'] = 'list';

		$context['settings'][$i]['value'] = !isset($settings[$setting['id']]) ? '' : $settings[$setting['id']];
	}

	// Do we support variants?
	if (!empty($settings['theme_variants']))
	{
		$context['theme_variants'] = [];
		foreach ($settings['theme_variants'] as $variant)
		{
			// Have any text, old chap?
			$context['theme_variants'][$variant] = array(
				'label' => isset($txt['variant_' . $variant]) ? $txt['variant_' . $variant] : $variant,
				'thumbnail' => !file_exists($settings['theme_dir'] . '/images/thumbnail.png') || file_exists($settings['theme_dir'] . '/images/thumbnail_' . $variant . '.png') ? $settings['images_url'] . '/thumbnail_' . $variant . '.png' : ($settings['images_url'] . '/thumbnail.png'),
			);
		}
		$context['default_variant'] = !empty($settings['default_variant']) && isset($context['theme_variants'][$settings['default_variant']]) ? $settings['default_variant'] : $settings['theme_variants'][0];
		
		$context['default_variant']['thumbnail'] = $context['theme_variants'][$context['default_variant']]['thumbnail'];
	}

	// Restore the current theme.
	loadTheme($old_id, false);

	$settings = $old_settings;

	// We like Kenny better than Token.
	createToken('admin-sts');
}

/**
 * Remove a theme from the database.
 * - removes an installed theme.
 * - requires an administrator.
 * - accessed with ?action=admin;area=theme;sa=remove.
 */
function RemoveTheme()
{
	global $context;

	checkSession('get');

	isAllowedTo('admin_forum');
	validateToken('admin-tr', 'request');

	// The theme's ID must be an integer.
	$themeID = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];

	// You can't delete the default theme!
	if ($themeID == 1)
		fatal_lang_error('no_access', false);

	$theme_info = get_single_theme($themeID);

	// Remove it from the DB.
	remove_theme($themeID);

	// And remove all its files and folders too.
	if (!empty($theme_info) && !empty($theme_info['theme_dir']))
		remove_dir($theme_info['theme_dir']);

	// Go back to the list page.
	redirectexit('action=admin;area=theme;sa=list;' . $context['session_var'] . '=' . $context['session_id'] . ';done=removing');
}

/**
 * Handles enabling/disabling a theme from the admin center
 */
function EnableTheme()
{
	global $modSettings, $context;

	checkSession('get');

	isAllowedTo('admin_forum');
	validateToken('admin-tre', 'request');

	// The theme's ID must be an string.
	$themeID = isset($_GET['th']) ? (string) trim($_GET['th']) : (string) trim($_GET['id']);

	// Get the current list.
	$enableThemes = explode(',', $modSettings['enableThemes']);

	// Are we disabling it?
	if (isset($_GET['disabled']))
		$enableThemes = array_diff($enableThemes, array($themeID));

	// Nope? then enable it!
	else
		$enableThemes[] = (string) $themeID;

	// Update the setting.
	$enableThemes = strtr(implode(',', $enableThemes), array(',,' => ','));
	updateSettings(array('enableThemes' => $enableThemes));

	// Done!
	redirectexit('action=admin;area=theme;sa=list;' . $context['session_var'] . '=' . $context['session_id'] . ';done=' . (isset($_GET['disabled']) ? 'disabling' : 'enabling'));
}

/**
 * Choose a theme from a list.
 * allows an user or administrator to pick a new theme with an interface.
 * - can edit everyone's (u = 0), guests' (u = -1), or a specific user's.
 * - uses the Themes template. (pick sub template.)
 * - accessed with ?action=admin;area=theme;sa=pick.
 * @todo thought so... Might be better to split this file in ManageThemes and Themes,
 * with centralized admin permissions on ManageThemes.
 */
function PickTheme()
{
	global $txt, $context, $modSettings, $user_info, $language, $smcFunc, $settings, $scripturl;

	loadLanguage('Profile');

	// Build the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=theme;sa=pick;u=' . (!empty($_REQUEST['u']) ? (int) $_REQUEST['u'] : 0),
		'name' => $txt['theme_pick'],
	);
	$context['default_theme_id'] = $modSettings['theme_default'];

	$_SESSION['id_theme'] = 0;

	if (isset($_GET['id']))
		$_GET['th'] = $_GET['id'];

	// Saving a variant cause JS doesn't work - pretend it did ;)
	if (isset($_POST['save']))
	{
		// Which theme?
		foreach ($_POST['save'] as $k => $v)
			$_GET['th'] = (int) $k;

		if (isset($_POST['vrt'][$k]))
			$_GET['vrt'] = $_POST['vrt'][$k];
	}

	// Have we made a decision, or are we just browsing?
	if (isset($_GET['th']))
	{
		checkSession('get');

		$_GET['th'] = (int) $_GET['th'];

		// Save for this user.
		if (!isset($_REQUEST['u']) || !allowedTo('admin_forum'))
		{
			updateMemberData($user_info['id'], array('id_theme' => (int) $_GET['th']));

			// A variants to save for the user?
			if (!empty($_GET['vrt']))
			{
				$smcFunc['db_insert']('replace',
					'{db_prefix}themes',
					array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
					array($_GET['th'], $user_info['id'], 'theme_variant', $_GET['vrt']),
					array('id_theme', 'id_member', 'variable')
				);
				cache_put_data('theme_settings-' . $_GET['th'] . ':' . $user_info['id'], null, 90);

				$_SESSION['id_variant'] = 0;
			}

			redirectexit('action=profile;area=theme');
		}

		// If changing members or guests - and there's a variant - assume changing default variant.
		if (!empty($_GET['vrt']) && ($_REQUEST['u'] == '0' || $_REQUEST['u'] == '-1'))
		{
			$smcFunc['db_insert']('replace',
				'{db_prefix}themes',
				array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				array($_GET['th'], 0, 'default_variant', $_GET['vrt']),
				array('id_theme', 'id_member', 'variable')
			);

			// Make it obvious that it's changed
			cache_put_data('theme_settings-' . $_GET['th'], null, 90);
		}

		// For everyone.
		if ($_REQUEST['u'] == '0')
		{
			updateMemberData(null, array('id_theme' => (int) $_GET['th']));

			// Remove any custom variants.
			if (!empty($_GET['vrt']))
			{
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}themes
					WHERE id_theme = {int:current_theme}
						AND variable = {string:theme_variant}',
					array(
						'current_theme' => (int) $_GET['th'],
						'theme_variant' => 'theme_variant',
					)
				);
			}

			redirectexit('action=admin;area=theme;sa=admin;' . $context['session_var'] . '=' . $context['session_id']);
		}
		// Change the default/guest theme.
		elseif ($_REQUEST['u'] == '-1')
		{
			updateSettings(array('theme_guests' => (int) $_GET['th']));

			redirectexit('action=admin;area=theme;sa=admin;' . $context['session_var'] . '=' . $context['session_id']);
		}
		// Change a specific member's theme.
		else
		{
			// The forum's default theme is always 0 and we
			if (isset($_GET['th']) && $_GET['th'] == 0)
					$_GET['th'] = $modSettings['theme_guests'];

			updateMemberData((int) $_REQUEST['u'], array('id_theme' => (int) $_GET['th']));

			if (!empty($_GET['vrt']))
			{
				$smcFunc['db_insert']('replace',
					'{db_prefix}themes',
					array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
					array($_GET['th'], (int) $_REQUEST['u'], 'theme_variant', $_GET['vrt']),
					array('id_theme', 'id_member', 'variable')
				);
				cache_put_data('theme_settings-' . $_GET['th'] . ':' . (int) $_REQUEST['u'], null, 90);

				if ($user_info['id'] == $_REQUEST['u'])
					$_SESSION['id_variant'] = 0;
			}

			redirectexit('action=profile;u=' . (int) $_REQUEST['u'] . ';area=theme');
		}
	}

	// Figure out who the member of the minute is, and what theme they've chosen.
	if (!isset($_REQUEST['u']) || !allowedTo('admin_forum'))
	{
		$context['current_member'] = $user_info['id'];
		$context['current_theme'] = $user_info['theme'];
	}
	// Everyone can't chose just one.
	elseif ($_REQUEST['u'] == '0')
	{
		$context['current_member'] = 0;
		$context['current_theme'] = 0;
	}
	// Guests and such...
	elseif ($_REQUEST['u'] == '-1')
	{
		$context['current_member'] = -1;
		$context['current_theme'] = $modSettings['theme_guests'];
	}
	// Someones else :P.
	else
	{
		$context['current_member'] = (int) $_REQUEST['u'];

		$request = $smcFunc['db_query']('', '
			SELECT id_theme
			FROM {db_prefix}members
			WHERE id_member = {int:current_member}
			LIMIT 1',
			array(
				'current_member' => $context['current_member'],
			)
		);
		list ($context['current_theme']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	// Get the theme name and descriptions.
	$context['available_themes'] = [];
	if (!empty($modSettings['knownThemes']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE variable IN ({string:name}, {string:theme_url}, {string:theme_dir}, {string:images_url}, {string:disable_user_variant})' . (!allowedTo('admin_forum') ? '
				AND id_theme IN ({array_string:known_themes})' : '') . '
				AND id_theme != {int:default_theme}
				AND id_member = {int:no_member}
				AND id_theme IN ({array_string:enable_themes})',
			array(
				'default_theme' => 0,
				'name' => 'name',
				'no_member' => 0,
				'theme_url' => 'theme_url',
				'theme_dir' => 'theme_dir',
				'images_url' => 'images_url',
				'disable_user_variant' => 'disable_user_variant',
				'known_themes' => explode(',', $modSettings['knownThemes']),
				'enable_themes' => explode(',', $modSettings['enableThemes']),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!isset($context['available_themes'][$row['id_theme']]))
				$context['available_themes'][$row['id_theme']] = array(
					'id' => $row['id_theme'],
					'selected' => $context['current_theme'] == $row['id_theme'],
					'num_users' => 0
				);
			$context['available_themes'][$row['id_theme']][$row['variable']] = $row['value'];
		}
		$smcFunc['db_free_result']($request);
	}

	// Okay, this is a complicated problem: the default theme is 1, but they aren't allowed to access 1!
	if (!isset($context['available_themes'][$modSettings['theme_guests']]))
	{
		$context['available_themes'][0] = array(
			'num_users' => 0
		);
		$guest_theme = 0;
	}
	else
		$guest_theme = $modSettings['theme_guests'];

	$request = $smcFunc['db_query']('', '
		SELECT id_theme, COUNT(*) AS the_count
		FROM {db_prefix}members
		GROUP BY id_theme
		ORDER BY id_theme DESC',
		array(
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Figure out which theme it is they are REALLY using.
		if (!empty($modSettings['knownThemes']) && !in_array($row['id_theme'], explode(',', $modSettings['knownThemes'])))
			$row['id_theme'] = $guest_theme;
		elseif (empty($modSettings['theme_allow']))
			$row['id_theme'] = $guest_theme;

		if (isset($context['available_themes'][$row['id_theme']]))
			$context['available_themes'][$row['id_theme']]['num_users'] += $row['the_count'];
		else
			$context['available_themes'][$guest_theme]['num_users'] += $row['the_count'];
	}
	$smcFunc['db_free_result']($request);

	// Get any member variant preferences.
	$variant_preferences = [];
	if ($context['current_member'] > 0)
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_theme, value
			FROM {db_prefix}themes
			WHERE variable = {string:theme_variant}
				AND id_member IN ({array_int:id_member})
			ORDER BY id_member ASC',
			array(
				'theme_variant' => 'theme_variant',
				'id_member' => isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'pick' ? array(-1, $context['current_member']) : array(-1),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$variant_preferences[$row['id_theme']] = $row['value'];
		$smcFunc['db_free_result']($request);
	}

	// Save the setting first.
	$current_images_url = $settings['images_url'];
	$current_theme_variants = !empty($settings['theme_variants']) ? $settings['theme_variants'] : [];

	foreach ($context['available_themes'] as $id_theme => $theme_data)
	{
		// Don't try to load the forum or board default theme's data... it doesn't have any!
		if ($id_theme == 0)
			continue;

		// The thumbnail needs the correct path.
		$settings['images_url'] = &$theme_data['images_url'];

		if (file_exists($theme_data['theme_dir'] . '/languages/Settings.' . $user_info['language'] . '.php'))
			include($theme_data['theme_dir'] . '/languages/Settings.' . $user_info['language'] . '.php');
		elseif (file_exists($theme_data['theme_dir'] . '/languages/Settings.' . $language . '.php'))
			include($theme_data['theme_dir'] . '/languages/Settings.' . $language . '.php');
		else
		{
			$txt['theme_thumbnail_href'] = '{images_url}/thumbnail.png';
			$txt['theme_description'] = '';
		}

		$context['available_themes'][$id_theme]['thumbnail_href'] = str_replace('{images_url}', $theme_data['images_url'], $txt['theme_thumbnail_href']);
		$context['available_themes'][$id_theme]['description'] = $txt['theme_description'];

		// Are there any variants?
		if (file_exists($theme_data['theme_dir'] . '/index.template.php') && (empty($theme_data['disable_user_variant']) || allowedTo('admin_forum')))
		{
			$file_contents = implode('', file($theme_data['theme_dir'] . '/index.template.php'));
			if (preg_match('~\$settings\[\'theme_variants\'\]\s*=(.+?);~', $file_contents, $matches))
			{
				$settings['theme_variants'] = [];

				// Fill settings up.
				eval('global $settings;' . $matches[0]);

				if (!empty($settings['theme_variants']))
				{
					loadLanguage('Settings');

					$context['available_themes'][$id_theme]['variants'] = [];
					foreach ($settings['theme_variants'] as $variant)
						$context['available_themes'][$id_theme]['variants'][$variant] = array(
							'label' => isset($txt['variant_' . $variant]) ? $txt['variant_' . $variant] : $variant,
							'thumbnail' => !file_exists($theme_data['theme_dir'] . '/images/thumbnail.png') || file_exists($theme_data['theme_dir'] . '/images/thumbnail_' . $variant . '.png') ? $theme_data['images_url'] . '/thumbnail_' . $variant . '.png' : ($theme_data['images_url'] . '/thumbnail.png'),
						);

					$context['available_themes'][$id_theme]['selected_variant'] = isset($_GET['vrt']) ? $_GET['vrt'] : (!empty($variant_preferences[$id_theme]) ? $variant_preferences[$id_theme] : (!empty($settings['default_variant']) ? $settings['default_variant'] : $settings['theme_variants'][0]));
					if (!isset($context['available_themes'][$id_theme]['variants'][$context['available_themes'][$id_theme]['selected_variant']]['thumbnail']))
						$context['available_themes'][$id_theme]['selected_variant'] = $settings['theme_variants'][0];

					$context['available_themes'][$id_theme]['thumbnail_href'] = $context['available_themes'][$id_theme]['variants'][$context['available_themes'][$id_theme]['selected_variant']]['thumbnail'];
					// Allow themes to override the text.
					$context['available_themes'][$id_theme]['pick_label'] = isset($txt['variant_pick']) ? $txt['variant_pick'] : $txt['theme_pick_variant'];
				}
			}
		}
	}
	// Then return it.
	$settings['images_url'] = $current_images_url;
	$settings['theme_variants'] = $current_theme_variants;

	// As long as we're not doing the default theme...
	if (!isset($_REQUEST['u']) || $_REQUEST['u'] >= 0)
	{
		if ($guest_theme != 0)
			$context['available_themes'][0] = $context['available_themes'][$guest_theme];

		$context['available_themes'][0]['id'] = 0;
		$context['available_themes'][0]['name'] = $txt['theme_forum_default'];
		$context['available_themes'][0]['selected'] = $context['current_theme'] == 0;
		$context['available_themes'][0]['description'] = $txt['theme_global_description'];
	}

	ksort($context['available_themes']);

	$context['page_title'] = $txt['theme_pick'];
	$context['sub_template'] = 'admin_themes_pick';
}

/**
 * Installs new themes, calls the respective function according to the install type.
 * - puts themes in $boardurl/Themes.
 * - assumes the gzip has a root directory in it. (ie default.)
 * Requires admin_forum.
 * Accessed with ?action=admin;area=theme;sa=install.
 */
function ThemeInstall()
{
	global $sourcedir, $txt, $context, $boarddir, $boardurl;
	global $themedir, $themeurl, $smcFunc;

	checkSession('request');
	isAllowedTo('admin_forum');

	require_once($sourcedir . '/Subs-Package.php');

	// Make it easier to change the path and url.
	$themedir = $boarddir . '/Themes';
	$themeurl = $boardurl . '/Themes';


	$subActions = array(
		'copy' => 'InstallCopy',
		'dir' => 'InstallDir',
	);

	// Is there a function to call?
	if (isset($_GET['do']) && !empty($_GET['do']) && isset($subActions[$_GET['do']]))
	{
		$action = $smcFunc['htmlspecialchars'](trim($_GET['do']));

		// Got any info from the specific form?
		if (!isset($_POST['save_' . $action]))
			fatal_lang_error('theme_install_no_action', false);

		validateToken('admin-t-' . $action);

		// Hopefully the themes directory is writable, or we might have a problem.
		if (!is_writable($themedir))
			fatal_lang_error('theme_install_write_error', 'critical');

		// Call the function and handle the result.
		$result = $subActions[$action]();

		// Everything went better than expected!
		if (!empty($result))
		{
			$context['sub_template'] = 'admin_themes_installed';
			$context['page_title'] = $txt['theme_installed'];
			$context['installed_theme'] = $result;
		}
	}

	// Nope, show a nice error.
	else
		fatal_lang_error('theme_install_no_action', false);
}

/**
 * Makes a copy from the default theme, assigns a name for it and installs it.
 *
 * Creates a new .xml file containing all the theme's info.
 * @return array The newly created theme's info.
 */
function InstallCopy()
{
	global $themedir, $themeurl, $settings, $smcFunc, $context;
	global $forum_version;

	// There's gotta be something to work with.
	if (!isset($_REQUEST['copy']) || empty($_REQUEST['copy']))
		fatal_lang_error('theme_install_error_title', false);

	// Get a cleaner version.
	$name = preg_replace('~[^A-Za-z0-9_\- ]~', '', $_REQUEST['copy']);

	// Is there a theme already named like this?
	if (file_exists($themedir . '/' . $name))
		fatal_lang_error('theme_install_already_dir', false);

	// This is a brand new theme so set all possible values.
	$context['to_install'] = array(
		'theme_dir' => $themedir . '/' . $name,
		'theme_url' => $themeurl . '/' . $name,
		'name' => $name,
		'images_url' => $themeurl . '/' . $name . '/images',
		'version' => '1.0',
		'install_for' => '1.0 - 1.0.99, ' . strtr($forum_version, array('StoryBB ' => '')),
	);

	// Create the specific dir.
	umask(0);
	mkdir($context['to_install']['theme_dir'], 0777);

	// Buy some time.
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	// Create subdirectories for css and javascript files.
	mkdir($context['to_install']['theme_dir'] . '/css', 0777);
	mkdir($context['to_install']['theme_dir'] . '/scripts', 0777);

	// Copy over the default non-theme files.
	$to_copy = array('/index.php', '/css/index.css', '/css/responsive.css', '/css/slider.min.css', '/css/rtl.css', '/css/admin.css', '/scripts/theme.js');

	foreach ($to_copy as $file)
	{
		copy($settings['default_theme_dir'] . $file, $context['to_install']['theme_dir'] . $file);
		sbb_chmod($context['to_install']['theme_dir'] . $file, 0777);
	}

	// And now the entire images directory!
	copytree($settings['default_theme_dir'] . '/images', $context['to_install']['theme_dir'] . '/images');
	package_flush_cache();

	// Let's add a theme.json to this theme. Most of this should come from the default theme.
	$json_loaded = @json_decode(file_get_contents($settings['default_theme_dir'] . '/theme.json'), true);
	$json = [
		'id' => 'StoryBB:' . $smcFunc['strtolower']($context['to_install']['name']),
		'name' => $context['to_install']['name'],
		'theme_version' => '1.0',
		'storybb_version' => $context['to_install']['install_for'],
	];
	$json += $json_loaded;

	file_put_contents($context['to_install']['theme_dir'] . '/theme.json', json_encode($json, JSON_PRETTY_PRINT));

	// Install the theme. theme_install() will take care of possible errors.
	$context['to_install']['id'] = theme_install($context['to_install']);

	// return the info.
	return $context['to_install'];
}

/**
 * Install a theme from a specific dir
 *
 * Assumes the dir is located on the main Themes dir. Ends execution with fatal_lang_error() on any error.
 * @return array The newly created theme's info.
 */
function InstallDir()
{
	global $themedir, $themeurl, $context;

	// Cannot use the theme dir as a theme dir.
	if (!isset($_REQUEST['theme_dir']) || empty($_REQUEST['theme_dir']) || rtrim(realpath($_REQUEST['theme_dir']), '/\\') == realpath($themedir))
		fatal_lang_error('theme_install_invalid_dir', false);

	// Check is there is "something" on the dir.
	elseif (!is_dir($_REQUEST['theme_dir']) || !file_exists($_REQUEST['theme_dir'] . '/theme.json'))
		fatal_lang_error('theme_install_error', false);

	$name = basename($_REQUEST['theme_dir']);
	$name = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $name);

	// All good! set some needed vars.
	$context['to_install'] = array(
		'theme_dir' => $_REQUEST['theme_dir'],
		'theme_url' => $themeurl . '/' . $name,
		'name' => $name,
		'images_url' => $themeurl . '/' . $name . '/images',
	);

	// Read its info form the XML file.
	$theme_info = get_theme_info($context['to_install']['theme_dir']);
	$context['to_install'] += $theme_info;

	// Install the theme. theme_install() will take care of possible errors.
	$context['to_install']['id'] = theme_install($context['to_install']);

	// return the info.
	return $context['to_install'];
}

/**
 * Set an option via javascript.
 * - sets a theme option without outputting anything.
 * - can be used with javascript, via a dummy image... (which doesn't require
 * the page to reload.)
 * - requires someone who is logged in.
 * - accessed via ?action=jsoption;var=variable;val=value;session_var=sess_id.
 * - does not log access to the Who's Online log. (in index.php..)
 */
function SetJavaScript()
{
	global $settings, $user_info, $smcFunc, $options;

	// Check the session id.
	checkSession('get');

	// This good-for-nothing pixel is being used to keep the session alive.
	if (empty($_GET['var']) || !isset($_GET['val']))
		redirectexit($settings['images_url'] . '/blank.png');

	// Sorry, guests can't go any further than this.
	if ($user_info['is_guest'] || $user_info['id'] == 0)
		obExit(false);

	$reservedVars = array(
		'actual_theme_url',
		'actual_images_url',
		'base_theme_dir',
		'base_theme_url',
		'default_images_url',
		'default_theme_dir',
		'default_theme_url',
		'default_template',
		'images_url',
		'number_recent_posts',
		'theme_dir',
		'theme_id',
		'theme_url',
		'name',
	);

	// Can't change reserved vars.
	if (in_array(strtolower($_GET['var']), $reservedVars))
		redirectexit($settings['images_url'] . '/blank.png');

	// Use a specific theme?
	if (isset($_GET['th']) || isset($_GET['id']))
	{
		// Invalidate the current themes cache too.
		cache_put_data('theme_settings-' . $settings['theme_id'] . ':' . $user_info['id'], null, 60);

		$settings['theme_id'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];
	}

	// If this is the admin preferences the passed value will just be an element of it.
	if ($_GET['var'] == 'admin_preferences')
	{
		$options['admin_preferences'] = !empty($options['admin_preferences']) ? sbb_json_decode($options['admin_preferences'], true) : [];
		// New thingy...
		if (isset($_GET['admin_key']) && strlen($_GET['admin_key']) < 5)
			$options['admin_preferences'][$_GET['admin_key']] = $_GET['val'];

		// Change the value to be something nice,
		$_GET['val'] = json_encode($options['admin_preferences']);
	}

	// Update the option.
	$smcFunc['db_insert']('replace',
		'{db_prefix}themes',
		array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
		array($settings['theme_id'], $user_info['id'], $_GET['var'], is_array($_GET['val']) ? implode(',', $_GET['val']) : $_GET['val']),
		array('id_theme', 'id_member', 'variable')
	);

	cache_put_data('theme_settings-' . $settings['theme_id'] . ':' . $user_info['id'], null, 60);

	// Don't output anything...
	redirectexit($settings['images_url'] . '/blank.png');
}
