<?php

/**
 * This file handles the administration of languages tasks.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * This is the main function for the languages area.
 * It dispatches the requests.
 * Loads the ManageLanguages template. (sub-actions will use it)
 * @todo lazy loading.
 *
 * @uses ManageSettings language file
 */
function ManageLanguages()
{
	global $context, $txt;

	loadLanguage('ManageSettings');

	$context['page_title'] = $txt['edit_languages'];

	$subActions = array(
		'edit' => 'ModifyLanguages',
		'settings' => 'ModifyLanguageSettings',
		'editlang' => 'ModifyLanguage',
	);

	// By default we're managing languages.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'edit';
	$context['sub_action'] = $_REQUEST['sa'];

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['language_configuration'],
		'description' => $txt['language_description'],
	);

	call_integration_hook('integrate_manage_languages', array(&$subActions));

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * This lists all the current languages and allows editing of them.
 */
function ModifyLanguages()
{
	global $txt, $context, $scripturl, $modSettings;
	global $sourcedir, $language, $boarddir;

	// Setting a new default?
	if (!empty($_POST['set_default']) && !empty($_POST['def_language']))
	{
		checkSession();
		validateToken('admin-lang');

		getLanguages();
		$lang_exists = false;
		foreach ($context['languages'] as $lang)
		{
			if ($_POST['def_language'] == $lang['filename'])
			{
				$lang_exists = true;
				break;
			}
		}

		if ($_POST['def_language'] != $language && $lang_exists)
		{
			require_once($sourcedir . '/Subs-Admin.php');
			updateSettingsFile(array('language' => '\'' . $_POST['def_language'] . '\''));
			$language = $_POST['def_language'];
		}
	}

	// Create another one time token here.
	createToken('admin-lang');

	$listOptions = array(
		'id' => 'language_list',
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'base_href' => $scripturl . '?action=admin;area=languages',
		'title' => $txt['edit_languages'],
		'get_items' => array(
			'function' => 'list_getLanguages',
		),
		'get_count' => array(
			'function' => 'list_getNumLanguages',
		),
		'columns' => array(
			'default' => array(
				'header' => array(
					'value' => $txt['languages_default'],
					'class' => 'centercol',
				),
				'data' => array(
					'function' => function($rowData)
					{
						return '<input type="radio" name="def_language" value="' . $rowData['id'] . '"' . ($rowData['default'] ? ' checked' : '') . ' onclick="highlightSelected(\'list_language_list_' . $rowData['id'] . '\');" class="input_radio">';
					},
					'style' => 'width: 8%;',
					'class' => 'centercol',
				),
			),
			'name' => array(
				'header' => array(
					'value' => $txt['languages_lang_name'],
				),
				'data' => array(
					'function' => function($rowData) use ($scripturl)
					{
						return sprintf('<a href="%1$s?action=admin;area=languages;sa=editlang;lid=%2$s">%3$s</a>', $scripturl, $rowData['id'], $rowData['name']);
					},
				),
			),
			'count' => array(
				'header' => array(
					'value' => $txt['languages_users'],
				),
				'data' => array(
					'db_htmlsafe' => 'count',
				),
			),
			'locale' => array(
				'header' => array(
					'value' => $txt['languages_locale'],
				),
				'data' => array(
					'db_htmlsafe' => 'locale',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=languages',
			'token' => 'admin-lang',
		),
		'additional_rows' => array(
			array(
				'position' => 'top_of_list',
				'value' => '<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '"><input type="submit" name="set_default" value="' . $txt['save'] . '"' . (is_writable($boarddir . '/Settings.php') ? '' : ' disabled') . ' class="button_submit">',
			),
			array(
				'position' => 'bottom_of_list',
				'value' => '<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '"><input type="submit" name="set_default" value="' . $txt['save'] . '"' . (is_writable($boarddir . '/Settings.php') ? '' : ' disabled') . ' class="button_submit">',
			),
		),
	);

	// We want to highlight the selected language. Need some Javascript for this.
	addInlineJavaScript('
	function highlightSelected(box)
	{
		$("tr.highlight2").removeClass("highlight2");
		$("#" + box).addClass("highlight2");
	}
	highlightSelected("list_language_list_' . ($language == '' ? 'english' : $language) . '");', true);

	// Display a warning if we cannot edit the default setting.
	if (!is_writable($boarddir . '/Settings.php'))
		$listOptions['additional_rows'][] = array(
				'position' => 'after_title',
				'value' => $txt['language_settings_writable'],
				'class' => 'smalltext alert',
			);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'generic_list_page';
	$context['default_list'] = 'language_list';
}

/**
 * How many languages?
 * Callback for the list in ManageLanguageSettings().
 * @return int The number of available languages
 */
function list_getNumLanguages()
{
	return count(getLanguages());
}

/**
 * Fetch the actual language information.
 * Callback for $listOptions['get_items']['function'] in ManageLanguageSettings.
 * Determines which languages are available by looking for the "index.{language}.php" file.
 * Also figures out how many users are using a particular language.
 * @return array An array of information about currenty installed languages
 */
function list_getLanguages()
{
	global $settings, $smcFunc, $language, $context, $txt;

	$languages = array();
	// Keep our old entries.
	$old_txt = $txt;
	$backup_actual_theme_dir = $settings['actual_theme_dir'];
	$backup_base_theme_dir = !empty($settings['base_theme_dir']) ? $settings['base_theme_dir'] : '';

	// Override these for now.
	$settings['actual_theme_dir'] = $settings['base_theme_dir'] = $settings['default_theme_dir'];
	getLanguages();

	// Put them back.
	$settings['actual_theme_dir'] = $backup_actual_theme_dir;
	if (!empty($backup_base_theme_dir))
		$settings['base_theme_dir'] = $backup_base_theme_dir;
	else
		unset($settings['base_theme_dir']);

	// Get the language files and data...
	foreach ($context['languages'] as $lang)
	{
		// Load the file to get the character set.
		require($settings['default_theme_dir'] . '/languages/index.' . $lang['filename'] . '.php');

		$languages[$lang['filename']] = array(
			'id' => $lang['filename'],
			'count' => 0,
			'default' => $language == $lang['filename'] || ($language == '' && $lang['filename'] == 'english'),
			'locale' => $txt['lang_locale'],
			'name' => $smcFunc['ucwords'](strtr($lang['filename'], array('_' => ' ', '-utf8' => ''))),
		);
	}

	// Work out how many people are using each language.
	$request = $smcFunc['db_query']('', '
		SELECT lngfile, COUNT(*) AS num_users
		FROM {db_prefix}members
		GROUP BY lngfile',
		array(
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Default?
		if (empty($row['lngfile']) || !isset($languages[$row['lngfile']]))
			$row['lngfile'] = $language;

		if (!isset($languages[$row['lngfile']]) && isset($languages['english']))
			$languages['english']['count'] += $row['num_users'];
		elseif (isset($languages[$row['lngfile']]))
			$languages[$row['lngfile']]['count'] += $row['num_users'];
	}
	$smcFunc['db_free_result']($request);

	// Restore the current users language.
	$txt = $old_txt;

	// Return how many we have.
	return $languages;
}

/**
 * Edit language related settings.
 *
 * @param bool $return_config Whether to return the $config_vars array (used in admin search)
 * @return void|array Returns nothing or the $config_vars array if $return_config is true
 */
function ModifyLanguageSettings($return_config = false)
{
	global $scripturl, $context, $txt, $boarddir, $sourcedir;

	// We'll want to save them someday.
	require_once $sourcedir . '/ManageServer.php';

	// Warn the user if the backup of Settings.php failed.
	$settings_not_writable = !is_writable($boarddir . '/Settings.php');
	$settings_backup_fail = !@is_writable($boarddir . '/Settings_bak.php') || !@copy($boarddir . '/Settings.php', $boarddir . '/Settings_bak.php');

	/* If you're writing a mod, it's a bad idea to add things here....
	For each option:
		variable name, description, type (constant), size/possible values, helptext.
	OR	an empty string for a horizontal rule.
	OR	a string for a titled section. */
	$config_vars = array(
		'language' => array('language', $txt['default_language'], 'file', 'select', array(), null, 'disabled' => $settings_not_writable),
		array('userLanguage', $txt['userLanguage'], 'db', 'check', null, 'userLanguage'),
	);

	call_integration_hook('integrate_language_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Get our languages. No cache
	getLanguages(false);
	foreach ($context['languages'] as $lang)
		$config_vars['language'][4][$lang['filename']] = array($lang['filename'], $lang['name']);

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		checkSession();

		call_integration_hook('integrate_save_language_settings', array(&$config_vars));

		saveSettings($config_vars);
		if (!$settings_not_writable && !$settings_backup_fail)
			session_flash('success', $txt['settings_saved']);
		redirectexit('action=admin;area=languages;sa=settings');
	}

	// Setup the template stuff.
	$context['post_url'] = $scripturl . '?action=admin;area=languages;sa=settings;save';
	$context['settings_title'] = $txt['language_settings'];
	$context['save_disabled'] = $settings_not_writable;

	if ($settings_not_writable)
		$context['settings_message'] = '<div class="centertext"><strong>' . $txt['settings_not_writable'] . '</strong></div><br>';
	elseif ($settings_backup_fail)
		$context['settings_message'] = '<div class="centertext"><strong>' . $txt['admin_backup_fail'] . '</strong></div><br>';

	// Fill the config array.
	prepareServerSettingsContext($config_vars);
}

/**
 * Edit a particular set of language entries.
 */
function ModifyLanguage()
{
	global $settings, $context, $smcFunc, $txt, $modSettings, $boarddir, $sourcedir, $language;

	loadLanguage('ManageSettings');

	// Select the languages tab.
	$context['menu_data_' . $context['admin_menu_id']]['current_subsection'] = 'edit';
	$context['page_title'] = $txt['edit_languages'];
	$context['sub_template'] = 'admin_languages_edit';

	$context['lang_id'] = $_GET['lid'];
	list($theme_id, $file_id) = empty($_REQUEST['tfid']) || strpos($_REQUEST['tfid'], '+') === false ? array(1, '') : explode('+', $_REQUEST['tfid']);

	// Clean the ID - just in case.
	preg_match('~([A-Za-z0-9_-]+)~', $context['lang_id'], $matches);
	$context['lang_id'] = $matches[1];

	// Get all the theme data.
	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE id_theme != {int:default_theme}
			AND id_member = {int:no_member}
			AND variable IN ({string:name}, {string:theme_dir})',
		array(
			'default_theme' => 1,
			'no_member' => 0,
			'name' => 'name',
			'theme_dir' => 'theme_dir',
		)
	);
	$themes = array(
		1 => array(
			'name' => $txt['dvc_default'],
			'theme_dir' => $settings['default_theme_dir'],
		),
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$themes[$row['id_theme']][$row['variable']] = $row['value'];
	$smcFunc['db_free_result']($request);

	// This will be where we look
	$lang_dirs = array();

	// Does a hook need to add in some additional places to look for languages?
	call_integration_hook('integrate_modifylanguages', array(&$themes, &$lang_dirs));

	// Check we have themes with a path and a name - just in case - and add the path.
	foreach ($themes as $id => $data)
	{
		if (count($data) != 2)
			unset($themes[$id]);
		elseif (is_dir($data['theme_dir'] . '/languages'))
			$lang_dirs[$id] = $data['theme_dir'] . '/languages';

		// How about image directories?
		if (is_dir($data['theme_dir'] . '/images/' . $context['lang_id']))
			$images_dirs[$id] = $data['theme_dir'] . '/images/' . $context['lang_id'];
	}

	$current_file = $file_id ? $lang_dirs[$theme_id] . '/' . $file_id . '.' . $context['lang_id'] . '.php' : '';

	// Now for every theme get all the files and stick them in context!
	$context['possible_files'] = array();
	foreach ($lang_dirs as $theme => $theme_dir)
	{
		// Open it up.
		$dir = dir($theme_dir);
		while ($entry = $dir->read())
		{
			// We're only after the files for this language.
			if (preg_match('~^([A-Za-z]+)\.' . $context['lang_id'] . '\.php$~', $entry, $matches) == 0)
				continue;

			if (!isset($context['possible_files'][$theme]))
				$context['possible_files'][$theme] = array(
					'id' => $theme,
					'name' => $themes[$theme]['name'],
					'files' => array(),
				);

			$context['possible_files'][$theme]['files'][] = array(
				'id' => $matches[1],
				'name' => isset($txt['lang_file_desc_' . $matches[1]]) ? $txt['lang_file_desc_' . $matches[1]] : $matches[1],
				'selected' => $theme_id == $theme && $file_id == $matches[1],
			);
		}
		$dir->close();
		usort($context['possible_files'][$theme]['files'], function($val1, $val2)
		{
			return strcmp($val1['name'], $val2['name']);
		});
	}

	// We no longer wish to speak this language.
	if (!empty($_POST['delete_main']) && $context['lang_id'] != 'english')
	{
		checkSession();
		validateToken('admin-mlang');

		// @todo Todo: FTP Controls?
		require_once($sourcedir . '/Subs-Package.php');

		// First, loop through the array to remove the files.
		foreach ($lang_dirs as $curPath)
		{
			foreach ($context['possible_files'][1]['files'] as $lang)
				if (file_exists($curPath . '/' . $lang['id'] . '.' . $context['lang_id'] . '.php'))
					unlink($curPath . '/' . $lang['id'] . '.' . $context['lang_id'] . '.php');

			// Check for the email template.
			if (file_exists($curPath . '/EmailTemplates.' . $context['lang_id'] . '.php'))
				unlink($curPath . '/EmailTemplates.' . $context['lang_id'] . '.php');
		}

		// Second, the agreement file.
		if (file_exists($boarddir . '/agreement.' . $context['lang_id'] . '.txt'))
			unlink($boarddir . '/agreement.' . $context['lang_id'] . '.txt');

		// Third, a related images folder, if it exists...
		if (!empty($images_dirs))
			foreach ($images_dirs as $curPath)
				if (is_dir($curPath))
					deltree($curPath);

		// Fourth, members can no longer use this language.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET lngfile = {empty}
			WHERE lngfile = {string:current_language}',
			array(
				'empty_string' => '',
				'current_language' => $context['lang_id'],
			)
		);

		// Fifth, update getLanguages() cache.
		if (!empty($modSettings['cache_enable']))
		{
			cache_put_data('known_languages', null, !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600);
		}

		// Sixth, if we deleted the default language, set us back to english?
		if ($context['lang_id'] == $language)
		{
			require_once($sourcedir . '/Subs-Admin.php');
			$language = 'english';
			updateSettingsFile(array('language' => '\'' . $language . '\''));
		}

		// Seventh, get out of here.
		redirectexit('action=admin;area=languages;sa=edit;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Saving primary settings?
	$madeSave = false;
	if (!empty($_POST['save_main']) && !$current_file)
	{
		checkSession();
		validateToken('admin-mlang');

		// Read in the current file.
		$current_data = implode('', file($settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php'));
		// These are the replacements. old => new
		$replace_array = array(
			'~\$txt\[\'lang_locale\'\]\s=\s(\'|")[^\r\n]+~' => '$txt[\'lang_locale\'] = \'' . preg_replace('~[^\w-]~i', '', $_POST['locale']) . '\';',
			'~\$txt\[\'lang_rtl\'\]\s=\s[A-Za-z0-9]+;~' => '$txt[\'lang_rtl\'] = ' . (!empty($_POST['rtl']) ? 'true' : 'false') . ';',
		);
		$current_data = preg_replace(array_keys($replace_array), array_values($replace_array), $current_data);
		$fp = fopen($settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php', 'w+');
		fwrite($fp, $current_data);
		fclose($fp);

		$madeSave = true;
	}

	// Quickly load index language entries.
	$old_txt = $txt;
	require($settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php');
	$context['lang_file_not_writable_message'] = is_writable($settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php') ? '' : sprintf($txt['lang_file_not_writable'], $settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php');
	// Setup the primary settings context.
	$context['primary_settings'] = array(
		'name' => $smcFunc['ucwords'](strtr($context['lang_id'], array('_' => ' ', '-utf8' => ''))),
		'locale' => $txt['lang_locale'],
		'rtl' => $txt['lang_rtl'],
	);

	// Restore normal service.
	$txt = $old_txt;

	// Are we saving?
	$save_strings = array();
	if (isset($_POST['save_entries']) && !empty($_POST['entry']))
	{
		checkSession();
		validateToken('admin-mlang');

		// Clean each entry!
		foreach ($_POST['entry'] as $k => $v)
		{
			// Only try to save if it's changed!
			if ($_POST['entry'][$k] != $_POST['comp'][$k])
				$save_strings[$k] = cleanLangString($v, false);
		}
	}

	// If we are editing a file work away at that.
	if ($current_file)
	{
		$context['entries_not_writable_message'] = is_writable($current_file) ? '' : sprintf($txt['lang_entries_not_writable'], $current_file);

		$entries = array();
		// We can't just require it I'm afraid - otherwise we pass in all kinds of variables!
		$multiline_cache = '';
		foreach (file($current_file) as $line)
		{
			// Got a new entry?
			if ($line[0] == '$' && !empty($multiline_cache))
			{
				preg_match('~\$(helptxt|txt|editortxt)\[\'(.+)\'\]\s?=\s?(.+);~ms', strtr($multiline_cache, array("\r" => '')), $matches);
				if (!empty($matches[3]))
				{
					$entries[$matches[2]] = array(
						'type' => $matches[1],
						'full' => $matches[0],
						'entry' => $matches[3],
					);
					$multiline_cache = '';
				}
			}
			$multiline_cache .= $line;
		}
		// Last entry to add?
		if ($multiline_cache)
		{
			preg_match('~\$(helptxt|txt|editortxt)\[\'(.+)\'\]\s?=\s?(.+);~ms', strtr($multiline_cache, array("\r" => '')), $matches);
			if (!empty($matches[3]))
				$entries[$matches[2]] = array(
					'type' => $matches[1],
					'full' => $matches[0],
					'entry' => $matches[3],
				);
		}

		// These are the entries we can definitely save.
		$final_saves = array();

		$context['file_entries'] = array();
		foreach ($entries as $entryKey => $entryValue)
		{
			// Ignore some things we set separately.
			$ignore_files = array('lang_locale', 'lang_rtl');
			if (in_array($entryKey, $ignore_files))
				continue;

			// These are arrays that need breaking out.
			$arrays = array('days', 'days_short', 'months', 'months_titles', 'months_short', 'happy_birthday_author', 'karlbenson1_author', 'nite0859_author', 'zwaldowski_author', 'geezmo_author', 'karlbenson2_author');
			if (in_array($entryKey, $arrays))
			{
				// Get off the first bits.
				$entryValue['entry'] = substr($entryValue['entry'], strpos($entryValue['entry'], '(') + 1, strrpos($entryValue['entry'], ')') - strpos($entryValue['entry'], '('));
				$entryValue['entry'] = explode(',', strtr($entryValue['entry'], array(' ' => '')));

				// Now create an entry for each item.
				$cur_index = 0;
				$save_cache = array(
					'enabled' => false,
					'entries' => array(),
				);
				foreach ($entryValue['entry'] as $id => $subValue)
				{
					// Is this a new index?
					if (preg_match('~^(\d+)~', $subValue, $matches))
					{
						$cur_index = $matches[1];
						$subValue = substr($subValue, strpos($subValue, '\''));
					}

					// Clean up some bits.
					$subValue = strtr($subValue, array('"' => '', '\'' => '', ')' => ''));

					// Can we save?
					if (isset($save_strings[$entryKey . '-+- ' . $cur_index]))
					{
						$save_cache['entries'][$cur_index] = strtr($save_strings[$entryKey . '-+- ' . $cur_index], array('\'' => ''));
						$save_cache['enabled'] = true;
					}
					else
						$save_cache['entries'][$cur_index] = $subValue;

					$context['file_entries'][] = array(
						'key' => $entryKey . '-+- ' . $cur_index,
						'value' => $subValue,
						'rows' => 1,
					);
					$cur_index++;
				}

				// Do we need to save?
				if ($save_cache['enabled'])
				{
					// Format the string, checking the indexes first.
					$items = array();
					$cur_index = 0;
					foreach ($save_cache['entries'] as $k2 => $v2)
					{
						// Manually show the custom index.
						if ($k2 != $cur_index)
						{
							$items[] = $k2 . ' => \'' . $v2 . '\'';
							$cur_index = $k2;
						}
						else
							$items[] = '\'' . $v2 . '\'';

						$cur_index++;
					}
					// Now create the string!
					$final_saves[$entryKey] = array(
						'find' => $entryValue['full'],
						'replace' => '$' . $entryValue['type'] . '[\'' . $entryKey . '\'] = array(' . implode(', ', $items) . ');',
					);
				}
			}
			else
			{
				// Saving?
				if (isset($save_strings[$entryKey]) && $save_strings[$entryKey] != $entryValue['entry'])
				{
					// @todo Fix this properly.
					if ($save_strings[$entryKey] == '')
						$save_strings[$entryKey] = '\'\'';

					// Set the new value.
					$entryValue['entry'] = $save_strings[$entryKey];
					// And we know what to save now!
					$final_saves[$entryKey] = array(
						'find' => $entryValue['full'],
						'replace' => '$' . $entryValue['type'] . '[\'' . $entryKey . '\'] = ' . $save_strings[$entryKey] . ';',
					);
				}

				$editing_string = cleanLangString($entryValue['entry'], true);
				$context['file_entries'][] = array(
					'key' => $entryKey,
					'value' => $editing_string,
					'rows' => (int) (strlen($editing_string) / 38) + substr_count($editing_string, "\n") + 1,
				);
			}
		}

		// Any saves to make?
		if (!empty($final_saves))
		{
			checkSession();

			$file_contents = implode('', file($current_file));
			foreach ($final_saves as $save)
				$file_contents = strtr($file_contents, array($save['find'] => $save['replace']));

			// Save the actual changes.
			$fp = fopen($current_file, 'w+');
			fwrite($fp, strtr($file_contents, array("\r" => '')));
			fclose($fp);

			$madeSave = true;
		}

		// Another restore.
		$txt = $old_txt;
	}

	// If we saved, redirect.
	if ($madeSave)
		redirectexit('action=admin;area=languages;sa=editlang;lid=' . $context['lang_id']);

	if (!empty($context['file_entries']))
	{
		// We need to split this up into pairs for the template.
		$context['split_file_entries'] = array_chunk($context['file_entries'], 2);
	}

	createToken('admin-mlang');
}

/**
 * This function cleans language entries to/from display.
 * @todo This function could be two functions?
 *
 * @param string $string The language string
 * @param bool $to_display Whether or not this is going to be displayed
 * @return string The cleaned string
 */
function cleanLangString($string, $to_display = true)
{
	global $smcFunc;

	// If going to display we make sure it doesn't have any HTML in it - etc.
	$new_string = '';
	if ($to_display)
	{
		// Are we in a string (0 = no, 1 = single quote, 2 = parsed)
		$in_string = 0;
		$is_escape = false;
		for ($i = 0; $i < strlen($string); $i++)
		{
			// Handle escapes first.
			if ($string{$i} == '\\')
			{
				// Toggle the escape.
				$is_escape = !$is_escape;
				// If we're now escaped don't add this string.
				if ($is_escape)
					continue;
			}
			// Special case - parsed string with line break etc?
			elseif (($string{$i} == 'n' || $string{$i} == 't') && $in_string == 2 && $is_escape)
			{
				// Put the escape back...
				$new_string .= $string{$i} == 'n' ? "\n" : "\t";
				$is_escape = false;
				continue;
			}
			// Have we got a single quote?
			elseif ($string{$i} == '\'')
			{
				// Already in a parsed string, or escaped in a linear string, means we print it - otherwise something special.
				if ($in_string != 2 && ($in_string != 1 || !$is_escape))
				{
					// Is it the end of a single quote string?
					if ($in_string == 1)
						$in_string = 0;
					// Otherwise it's the start!
					else
						$in_string = 1;

					// Don't actually include this character!
					continue;
				}
			}
			// Otherwise a double quote?
			elseif ($string{$i} == '"')
			{
				// Already in a single quote string, or escaped in a parsed string, means we print it - otherwise something special.
				if ($in_string != 1 && ($in_string != 2 || !$is_escape))
				{
					// Is it the end of a double quote string?
					if ($in_string == 2)
						$in_string = 0;
					// Otherwise it's the start!
					else
						$in_string = 2;

					// Don't actually include this character!
					continue;
				}
			}
			// A join/space outside of a string is simply removed.
			elseif ($in_string == 0 && (empty($string{$i}) || $string{$i} == '.'))
				continue;
			// Start of a variable?
			elseif ($in_string == 0 && $string{$i} == '$')
			{
				// Find the whole of it!
				preg_match('~([\$A-Za-z0-9\'\[\]_-]+)~', substr($string, $i), $matches);
				if (!empty($matches[1]))
				{
					// Come up with some pseudo thing to indicate this is a var.
					/**
					 * @todo Do better than this, please!
					 */
					$new_string .= '{%' . $matches[1] . '%}';

					// We're not going to reparse this.
					$i += strlen($matches[1]) - 1;
				}

				continue;
			}
			// Right, if we're outside of a string we have DANGER, DANGER!
			elseif ($in_string == 0)
			{
				continue;
			}

			// Actually add the character to the string!
			$new_string .= $string{$i};
			// If anything was escaped it ain't any longer!
			$is_escape = false;
		}

		// Unhtml then rehtml the whole thing!
		$new_string = $smcFunc['htmlspecialchars'](un_htmlspecialchars($new_string));
	}
	else
	{
		// Keep track of what we're doing...
		$in_string = 0;
		// This is for deciding whether to HTML a quote.
		$in_html = false;
		for ($i = 0; $i < strlen($string); $i++)
		{
			// We don't do parsed strings apart from for breaks.
			if ($in_string == 2)
			{
				$in_string = 0;
				$new_string .= '"';
			}

			// Not in a string yet?
			if ($in_string != 1)
			{
				$in_string = 1;
				$new_string .= ($new_string ? ' . ' : '') . '\'';
			}

			// Is this a variable?
			if ($string{$i} == '{' && $string{$i + 1} == '%' && $string{$i + 2} == '$')
			{
				// Grab the variable.
				preg_match('~\{%([\$A-Za-z0-9\'\[\]_-]+)%\}~', substr($string, $i), $matches);
				if (!empty($matches[1]))
				{
					if ($in_string == 1)
						$new_string .= '\' . ';
					elseif ($new_string)
						$new_string .= ' . ';

					$new_string .= $matches[1];
					$i += strlen($matches[1]) + 3;
					$in_string = 0;
				}

				continue;
			}
			// Is this a lt sign?
			elseif ($string{$i} == '<')
			{
				// Probably HTML?
				if ($string{$i + 1} != ' ')
					$in_html = true;
				// Assume we need an entity...
				else
				{
					$new_string .= '&lt;';
					continue;
				}
			}
			// What about gt?
			elseif ($string{$i} == '>')
			{
				// Will it be HTML?
				if ($in_html)
					$in_html = false;
				// Otherwise we need an entity...
				else
				{
					$new_string .= '&gt;';
					continue;
				}
			}
			// Is it a slash? If so escape it...
			if ($string{$i} == '\\')
				$new_string .= '\\';
			// The infamous double quote?
			elseif ($string{$i} == '"')
			{
				// If we're in HTML we leave it as a quote - otherwise we entity it.
				if (!$in_html)
				{
					$new_string .= '&quot;';
					continue;
				}
			}
			// A single quote?
			elseif ($string{$i} == '\'')
			{
				// Must be in a string so escape it.
				$new_string .= '\\';
			}

			// Finally add the character to the string!
			$new_string .= $string{$i};
		}

		// If we ended as a string then close it off.
		if ($in_string == 1)
			$new_string .= '\'';
		elseif ($in_string == 2)
			$new_string .= '"';
	}

	return $new_string;
}
