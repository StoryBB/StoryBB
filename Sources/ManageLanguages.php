<?php

/**
 * This file handles the administration of languages tasks.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Model\Policy;
use StoryBB\Model\Language;
use StoryBB\Model\Theme;

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
	if (!empty($_GET['set_default']))
	{
		checkSession('get');
		validateToken('admin-lang', 'get');

		getLanguages();
		$lang_exists = false;
		foreach ($context['languages'] as $lang)
		{
			if ($_GET['set_default'] == $lang['filename'])
			{
				$lang_exists = true;
				break;
			}
		}

		if ($_GET['set_default'] != $language && $lang_exists)
		{
			require_once($sourcedir . '/Subs-Admin.php');
			updateSettingsFile(array('language' => '\'' . $_GET['set_default'] . '\''));
			$language = $_GET['def_language'];
		}
	}

	// Create another one time token here.
	createToken('admin-lang', 'get');

	$listOptions = [
		'id' => 'language_list',
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'base_href' => $scripturl . '?action=admin;area=languages',
		'title' => $txt['edit_languages'],
		'get_items' => [
			'function' => 'list_getLanguages',
		],
		'get_count' => [
			'function' => 'list_getNumLanguages',
		],
		'columns' => [
			'default' => [
				'header' => [
					'value' => $txt['languages_default'],
					'class' => 'centercol',
				],
				'data' => [
					'function' => function($rowData) use ($scripturl, $context, $txt)
					{
						if ($rowData['default'])
						{
							return $txt['languages_current_default'];
						}

						$link = '<a class="button" href="{scripturl}?action=admin;area=languages;set_default={lang};{session};{token}">{text}</a>';
						return strtr($link, [
							'{scripturl}' => $scripturl,
							'{lang}' => $rowData['id'],
							'{session}' => $context['session_var'] . '=' . $context['session_id'],
							'{token}' => $context['admin-lang_token_var'] . '=' . $context['admin-lang_token'],
							'{text}' => $txt['languages_make_default'],
						]);
					},
					'style' => 'width: 8%;',
					'class' => 'centercol',
				],
			],
			'name' => [
				'header' => [
					'value' => $txt['languages_lang_name'],
				],
				'data' => [
					'db_htmlsafe' => 'name',
				],
			],
			'edit' => [
				'header' => [
					'value' => $txt['edit'],
					'class' => 'centercol',
				],
				'data' => [
					'function' => function($rowData) use ($scripturl, $txt)
					{
						return sprintf('<a href="%1$s?action=admin;area=languages;sa=editlang;lid=%2$s">%3$s</a>', $scripturl, $rowData['id'], $txt['edit']);
					},
					'class' => 'centercol',
				],
			],
			'count' => [
				'header' => [
					'value' => $txt['languages_users'],
					'class' => 'centercol',
				],
				'data' => [
					'db_htmlsafe' => 'count',
					'class' => 'centercol',
				],
			],
			'locale' => [
				'header' => [
					'value' => $txt['languages_locale'],
					'class' => 'centercol',
				],
				'data' => [
					'db_htmlsafe' => 'locale',
					'class' => 'centercol',
				],
			],
			'rtl' => [
				'header' => [
					'value' => $txt['languages_right_to_left'],
					'class' => 'centercol',
				],
				'data' => [
					'function' => function($rowData) use ($txt)
					{
						return $rowData['rtl'] ? $txt['yes'] : $txt['no'];
					},
					'class' => 'centercol',
				],
			],
		],
		'form' => [
			'href' => $scripturl . '?action=admin;area=languages',
			'token' => 'admin-lang',
		],
	];

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

	$languages = [];
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
		$general = json_decode(file_get_contents($settings['default_theme_dir'] . '/languages/' . $lang['filename'] . '/' . $lang['filename'] . '.json'), true);

		$languages[$lang['filename']] = array(
			'id' => $lang['filename'],
			'count' => 0,
			'default' => $language == $lang['filename'] || ($language == '' && $lang['filename'] == 'en-us'),
			'locale' => $general['locale'],
			'name' => $general['native_name'] . (!empty($general['english_name']) && $general['english_name'] != $general['native_name'] ? ' (' . $general['english_name'] . ')' : ''),
			'rtl' => !empty($general['is_rtl']),
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

		if (!isset($languages[$row['lngfile']]) && isset($languages['en-us']))
			$languages['en-us']['count'] += $row['num_users'];
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
		'language' => array('language', $txt['default_language'], 'file', 'select', [], null, 'disabled' => $settings_not_writable),
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
	global $settings, $context, $smcFunc, $txt, $modSettings, $boarddir, $sourcedir, $language, $scripturl;

	loadLanguage('ManageSettings');

	// Select the languages tab.
	$context['menu_data_' . $context['admin_menu_id']]['current_subsection'] = 'edit';
	$context['page_title'] = $txt['edit_languages'];

	$context['lang_id'] = isset($_GET['lid']) ? $_GET['lid'] : '';
	list($theme_id, $file_id) = empty($_REQUEST['tfid']) || strpos($_REQUEST['tfid'], '_') === false ? array(1, '') : explode('_', $_REQUEST['tfid']);

	// Clean the ID - just in case.
	if (preg_match('~([A-Za-z0-9_-]+)~', $context['lang_id'], $matches))
	{
		$context['lang_id'] = $matches[1];
	}
	else
	{
		$context['lang_id'] = '';
	}

	// Get all the theme data.
	$themes = array(
		1 => array(
			'name' => $txt['dvc_default'],
			'theme_dir' => $settings['default_theme_dir'],
		),
	);
	foreach (Theme::get_theme_list() as $tid => $theme)
	{
		if (isset($themes[$tid]))
		{
			continue;
		}
		$themes[$tid] = $theme;
	}

	// This will be where we look
	$lang_dirs = [];

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

	$current_file = $file_id ? $lang_dirs[$theme_id] . '/' . $context['lang_id'] . '/' . $file_id . '.php' : '';
	if (!file_exists($current_file))
	{
		$current_file = '';
	}

	// Now for every theme get all the files and stick them in context!
	$context['possible_files'] = [];
	foreach ($lang_dirs as $theme => $theme_dir)
	{
		// Open it up.
		$dir = dir($theme_dir);
		while ($entry = $dir->read())
		{
			if ($entry[0] == '.')
			{
				continue;
			}

			if (!is_dir($theme_dir . '/' . $entry) || !file_exists($theme_dir . '/' . $entry . '/' . $entry . '.json'))
				continue;

			foreach (scandir($theme_dir . '/' . $entry) as $file)
			{
				if (!preg_match('/^([A-Z][A-Za-z0-9]+)\.php$/', $file, $matches))
				{
					continue;
				}

				if ($matches[1] == 'EmailTemplates')
				{
					continue;
				}

				if (!isset($context['possible_files'][$theme]))
				{
					$context['possible_files'][$theme] = [
						'id' => $theme,
						'name' => $themes[$theme]['name'],
						'files' => [
							'main_files' => [],
							'admin_files' => [],
							'all_files' => [],
						]
					];
				}

				$file_entry = [
					'id' => $matches[1],
					'name' => isset($txt['lang_file_desc_' . $matches[1]]) ? $txt['lang_file_desc_' . $matches[1]] : $matches[1],
					'selected' => $theme_id == $theme && $file_id == $matches[1],
					'edit_link' => $scripturl . '?action=admin;area=languages;sa=editlang;lid=' . $context['lang_id'] . ';tfid=' . $theme . '_' . $matches[1],
				];

				$context['possible_files'][$theme]['files']['all_files'][] = $file_entry;

				$is_admin = in_array($matches[1], ['Admin', 'Modlog']) || strpos($matches[1], 'Manage') === 0;
				$file_block = $is_admin ? 'admin_files' : 'main_files';
				$context['possible_files'][$theme]['files'][$file_block][$matches[1]] = $file_entry;
			}
		}
		$dir->close();
		foreach (['all_files', 'main_files', 'admin_files'] as $fileset)
		{
			uasort($context['possible_files'][$theme]['files'][$fileset], function($val1, $val2)
			{
				return strcmp($val1['name'], $val2['name']);
			});
		}
		// While we had the general strings sorted into their own bubble, we should put them first.
		$context['possible_files'][$theme]['files']['main_files'] = array_merge(
			['General' => $context['possible_files'][$theme]['files']['main_files']['General']],
			$context['possible_files'][$theme]['files']['main_files']
		);
	}

	// Quickly load index language entries.
	$language_manifest = @json_decode(file_get_contents($settings['default_theme_dir'] . '/languages/' . $context['lang_id'] . '/' . $context['lang_id'] . '.json'), true);
	$context['lang_file_not_writable_message'] = '';
	// Setup the primary settings context.
	$context['primary_settings'] = array(
		'name' => $language_manifest['native_name'],
		'locale' => $language_manifest['locale'],
		'rtl' => !empty($language_manifest['is_rtl']),
	);

	// If we are editing a file work away at that.
	if ($current_file)
	{
		$master = Language::get_file_for_editing($current_file);
		$delta = Language::get_language_changes((int) $theme_id, $context['lang_id'], $file_id);
		$context['entries'] = [];

		foreach ($master as $lang_var => $lang_strings)
		{
			foreach ($lang_strings as $lang_key => $lang_string)
			{
				$context['entries'][$lang_var . '_' . $lang_key] = [
					'link' => $scripturl . '?action=admin;area=languages;sa=editlang;lid=' . $context['lang_id'] . ';tfid=' . $theme_id . '_' . $file_id . ';eid=' . $lang_var . '_' . urlencode($lang_key),
					'lang_var' => $lang_var,
					'display' => $lang_key,
					'master' => $lang_string,
					'current' => isset($delta[$lang_var][$lang_key]) ? $delta[$lang_var][$lang_key] : '',
				];
			}
		}

		if (isset($_GET['eid']) && isset($context['entries'][$_GET['eid']]))
		{
			$context['sub_template'] = 'admin_languages_edit_entry';
			loadJavaScriptFile('manage_languages.js', array('defer' => true, 'minimize' => false), 'manage_languages');
			$context['current_entry'] = $context['entries'][$_GET['eid']];
			if (empty($context['current_entry']['current']))
			{
				$context['current_entry']['current'] = $context['current_entry']['master'];
			}

			// This is the path we might actually save something.
			if (isset($_POST['save_entry']))
			{
				checkSession();
				validateToken('admin-mlang');

				if (is_array($context['current_entry']['master']))
				{
					$entries = [];
					if (!empty($_POST['entry_key']) && !empty($_POST['entry_value']) && is_array($_POST['entry_key']) && is_array($_POST['entry_value']))
					{
						foreach ($_POST['entry_key'] as $k => $v)
						{
							if (trim($v) != '' && isset($_POST['entry_value'][$k]))
							{
								$entries[$v] = (string) $_POST['entry_value'][$k];
							}
						}
						// Let's see if they are the same.
						$master = $context['current_entry']['master'];
						asort($master);
						$current = $entries;
						asort($current);
						if ($master === $current)
						{
							Language::delete_current_entry((int) $theme_id, $context['lang_id'], $file_id, $context['current_entry']['lang_var'], $context['current_entry']['display']);
						}
						else
						{
							Language::save_multiple_entry((int) $theme_id, $context['lang_id'], $file_id, $context['current_entry']['lang_var'], $context['current_entry']['display'], $entries);
						}
					}
				}
				else
				{
					$entry = !empty($_POST['entry']) ? $_POST['entry'] : '';
					if ($entry === $context['current_entry']['master'])
					{
						Language::delete_current_entry((int) $theme_id, $context['lang_id'], $file_id, $context['current_entry']['lang_var'], $context['current_entry']['display']);
					}
					else
					{
						Language::save_single_entry((int) $theme_id, $context['lang_id'], $file_id, $context['current_entry']['lang_var'], $context['current_entry']['display'], $entry);
					}
				}

				session_flash('success', $txt['settings_saved']);
				redirectexit('action=admin;area=languages;sa=editlang;lid=' . $context['lang_id']);
			}
		}
		else
		{
			$context['sub_template'] = 'admin_languages_edit_list';
		}
	}
	else
	{
		$context['sub_template'] = 'admin_languages_select';

		loadLanguage('Login');
		$context['policies'] = [];
		foreach (Policy::get_policy_list() as $policy_type)
		{
			$context['policies'][] = [
				'name' => $txt['policy_type_' . $policy_type['policy_type']],
				'link' => $scripturl . '?action=admin;area=regcenter;sa=policies;policy=' . $policy_type['id_policy_type'] . ';lang=' . $context['lang_id'],
			];
		}
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
		for ($i = 0, $n = strlen($string); $i < $n; $i++)
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
		for ($i = 0, $n = strlen($string); $i < $n; $i++)
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
