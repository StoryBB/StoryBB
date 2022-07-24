<?php

/**
 * Contains all the functionality required to be able to edit the core server
 * settings. This includes anything from which an error may result in the forum
 * destroying itself in a firey fury.
 *
 * Adding options to one of the setting screens isn't hard. Call prepareDBSettingsContext;
 * The basic format for a checkbox is:
 * 		array('check', 'nameInModSettingsAndSQL'),
 * And for a text box:
 * 		array('text', 'nameInModSettingsAndSQL')
 * (NOTE: You have to add an entry for this at the bottom!)
 *
 * In these cases, it will look for $txt['nameInModSettingsAndSQL'] as the description,
 * and $helptxt['nameInModSettingsAndSQL'] as the help popup description.
 *
 * Here's a quick explanation of how to add a new item:
 *
 * - A text input box.  For textual values.
 * 		array('text', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A text input box.  For numerical values.
 * 		array('int', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A text input box.  For floating point values.
 * 		array('float', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A large text input box. Used for textual values spanning multiple lines.
 * 		array('large_text', 'nameInModSettingsAndSQL', 'OptionalNumberOfRows'),
 * - A check box.  Either one or zero. (boolean)
 * 		array('check', 'nameInModSettingsAndSQL'),
 * - A selection box.  Used for the selection of something from a list.
 * 		array('select', 'nameInModSettingsAndSQL', array('valueForSQL' => $txt['displayedValue'])),
 * 		Note that just saying array('first', 'second') will put 0 in the SQL for 'first'.
 * - A password input box. Used for passwords, no less!
 * 		array('password', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A permission - for picking groups who have a permission.
 * 		array('permissions', 'manage_groups'),
 * - A BBC selection box.
 * 		array('bbc', 'sig_bbc'),
 * - A list of boards to choose from
 *  	array('boards', 'likes_boards'),
 *  	Note that the storage in the database is as 1,2,3,4
 *
 * For each option:
 * 	- type (see above), variable name, size/possible values.
 * 	  OR make type '' for an empty string for a horizontal rule.
 *  - SET preinput - to put some HTML prior to the input box.
 *  - SET postinput - to put some HTML following the input box.
 *  - SET invalid - to mark the data as invalid.
 *  - PLUS you can override label and help parameters by forcing their keys in the array, for example:
 *  	array('text', 'invalidlabel', 3, 'label' => 'Actual Label')
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Helper\Bbcode\AbstractParser;
use StoryBB\Helper\Parser;
use StoryBB\Helper\ServerEnvironment;
use StoryBB\StringLibrary;

/**
 * This is the main dispatcher. Sets up all the available sub-actions, all the tabs and selects
 * the appropriate one based on the sub-action.
 *
 * Requires the admin_forum permission.
 * Redirects to the appropriate function based on the sub-action.
 *
 * @uses edit_settings adminIndex.
 */
function ModifySettings()
{
	global $context, $txt, $boarddir;

	// This is just to keep the database password more secure.
	isAllowedTo('admin_forum');

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = [
		'title' => $txt['admin_server_settings'],
		'help' => '',
		'description' => $txt['admin_basic_settings'],
	];

	checkSession('request');

	// The settings are in here, I swear!
	loadLanguage('ManageSettings');

	$context['page_title'] = $txt['admin_server_settings'];

	$subActions = [
		'general' => 'ModifyGeneralSettings',
		'security' => 'ModifyGeneralSecuritySettings',
	];

	// By default we're editing the core settings
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'general';
	$context['sub_action'] = $_REQUEST['sa'];

	// Warn the user if there's any relevant information regarding Settings.php.
	$settings_not_writable = !is_writable($boarddir . '/Settings.php');
	$settings_backup_fail = !@is_writable($boarddir . '/Settings_bak.php') || !@copy($boarddir . '/Settings.php', $boarddir . '/Settings_bak.php');

	if ($settings_not_writable)
		$context['settings_message'] = '<div class="centertext"><strong>' . $txt['settings_not_writable'] . '</strong></div><br>';
	elseif ($settings_backup_fail)
		$context['settings_message'] = '<div class="centertext"><strong>' . $txt['admin_backup_fail'] . '</strong></div><br>';

	$context['settings_not_writable'] = $settings_not_writable;

	routing_integration_hook('integrate_server_settings', [&$subActions]);

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * General forum settings - forum name, maintenance mode, etc.
 * Practically, this shows an interface for the settings in Settings.php to be changed.
 *
 * - Requires the admin_forum permission.
 * - Uses the edit_settings administration area.
 * - Contains the actual array of settings to show from Settings.php.
 * - Accessed from ?action=admin;area=serversettings;sa=general.
 *
 * @param bool $return_config Whether to return the $config_vars array (for pagination purposes)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyGeneralSettings($return_config = false)
{
	global $scripturl, $context, $txt, $modSettings, $boardurl, $sourcedir;

	// If no cert, force_ssl must remain 0
	require_once($sourcedir . '/Subs.php');
	if (!ServerEnvironment::ssl_cert_found($boardurl) && empty($modSettings['force_ssl']))
		$disable_force_ssl = true;
	else
		$disable_force_ssl = false;

	/* If you're writing a mod, it's a bad idea to add things here....
	For each option:
		variable name, description, type (constant), size/possible values, helptext, optional 'min' (minimum value for float/int, defaults to 0), optional 'max' (maximum value for float/int), optional 'step' (amount to increment/decrement value for float/int)
	OR	an empty string for a horizontal rule.
	OR	a string for a titled section. */
	$config_vars = [
		['force_ssl', $txt['force_ssl'], 'db', 'select', [$txt['force_ssl_off'], $txt['force_ssl_auth'], $txt['force_ssl_complete']], 'force_ssl', 'disabled' => $disable_force_ssl],
		['image_proxy_enabled', $txt['image_proxy_enabled'], 'file', 'check', null, 'image_proxy_enabled'],
		['image_proxy_secret', $txt['image_proxy_secret'], 'file', 'text', 30, 'image_proxy_secret'],
		['image_proxy_maxsize', $txt['image_proxy_maxsize'], 'file', 'int', null, 'image_proxy_maxsize'],
	];

	settings_integration_hook('integrate_general_settings', [&$config_vars]);

	if ($return_config)
		return [$txt['admin_server_settings'] . ' - ' . $txt['general_settings'], $config_vars];

	// Setup the template stuff.
	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=general;save';
	$context['settings_title'] = $txt['general_settings'];

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		settings_integration_hook('integrate_save_general_settings');

		// Ensure all URLs are aligned with the new force_ssl setting
		// Treat unset like 0
		if (isset($_POST['force_ssl']))
			AlignURLsWithSSLSetting($_POST['force_ssl']);
		else
			AlignURLsWithSSLSetting(0);
			
		saveSettings($config_vars);
		session_flash('success', $txt['settings_saved']);
		redirectexit('action=admin;area=serversettings;sa=general;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Fill the config array.
	prepareServerSettingsContext($config_vars);

	// Some javascript for SSL
	addInlineJavaScript('
$(function()
{
	$("#force_ssl").change(function()
	{
		var mode = $(this).val() == 2 ? false : true;
		$("#image_proxy_enabled").prop("disabled", mode);
		$("#image_proxy_secret").prop("disabled", mode);
		$("#image_proxy_maxsize").prop("disabled", mode);
	}).change();
});');
}

/**
 * Align URLs with SSL Setting.
 *
 * If force_ssl has changed, ensure all URLs are aligned with the new setting.
 * This includes:
 *     - $boardurl
 *     - $modSettings['avatar_url']
 *     - $modSettings['custom_avatar_url'] - if found
 *     - theme_url - all entries in the themes table
 *     - images_url - all entries in the themes table
 *
 * This function will NOT overwrite URLs that are not subfolders of $boardurl.
 * The admin must have pointed those somewhere else on purpose, so they must be updated manually.
 * 
 * A word of caution: You can't trust the http/https scheme reflected for these URLs in $globals
 * (e.g., $boardurl) or in $modSettings.  This is because StoryBB may change them in memory to comply
 * with the force_ssl setting - a soft redirect may be in effect...  Thus, conditional updates
 * to these values do not work.  You gotta just brute force overwrite them based on force_ssl.
 *
 * @param int $new_force_ssl is the current force_ssl setting.
 * @return void Returns nothing, just does its job
 */
function AlignURLsWithSSLSetting($new_force_ssl = 0)
{
	global $boardurl, $modSettings, $sourcedir, $smcFunc;
	require_once($sourcedir . '/Subs-Admin.php');

	// Check $boardurl
	if ($new_force_ssl == 2)
		$newval = strtr($boardurl, ['http://' => 'https://']);
	else
		$newval = strtr($boardurl, ['https://' => 'http://']);
	updateSettingsFile(['boardurl' => '\'' . addslashes($newval) . '\'']);

	$new_settings = [];

	// Check $custom_avatar_url, but only if it points to a subfolder of $boardurl
	// This one had been optional in the past, make sure it is set first
	if (isset($modSettings['custom_avatar_url']) && BoardurlMatch($modSettings['custom_avatar_url']))
	{
		if ($new_force_ssl == 2)
			$newval = strtr($modSettings['custom_avatar_url'], ['http://' => 'https://']);
		else
			$newval = strtr($modSettings['custom_avatar_url'], ['https://' => 'http://']);
		$new_settings['custom_avatar_url'] = $newval;
	}

	// Save updates to the settings table
	if (!empty($new_settings))
		updateSettings($new_settings, true);

	// Now we move onto the themes.
	// First, get a list of theme URLs...
	$request = $smcFunc['db']->query('', '
		SELECT id_theme, variable, value
		  FROM {db_prefix}themes
		 WHERE variable in ({string:themeurl}, {string:imagesurl})
		   AND id_member = {int:zero}',
		[
			'themeurl' => 'theme_url',
			'imagesurl' => 'images_url',
			'zero' => 0,
		]
	);

	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// First check to see if it points to a subfolder of $boardurl
		if (BoardurlMatch($row['value']))
		{
			if ($new_force_ssl == 2)
				$newval = strtr($row['value'], ['http://' => 'https://']);
			else
				$newval = strtr($row['value'], ['https://' => 'http://']);
			$smcFunc['db']->query('', '
				UPDATE {db_prefix}themes
				   SET value = {string:theme_val}
				 WHERE variable = {string:theme_var}
				   AND id_theme = {string:theme_id}
				   AND id_member = {int:zero}',
				[
					'theme_val' => $newval,
					'theme_var' => $row['variable'],
					'theme_id' => $row['id_theme'],
					'zero' => 0,
				]
			);
		}
	}
	$smcFunc['db']->free_result($request);
}

/**
 * $boardurl Match.
 *
 * Helper function to see if the url being checked is based off of $boardurl.
 * If not, it was overridden by the admin to some other value on purpose, and should not
 * be stepped on by StoryBB when aligning URLs with the force_ssl setting.
 * The site admin must change URLs that are not aligned with $boardurl manually.
 *
 * @param string $url is the url to check.
 * @return bool Returns true if the url is based off of $boardurl (without the scheme), false if not
 */
function BoardurlMatch($url = '')
{
	global $boardurl;

	// Strip the schemes
	$urlpath = strtr($url, ['http://' => '', 'https://' => '']);
	$boardurlpath = strtr($boardurl, ['http://' => '', 'https://' => '']);

	// If leftmost portion of path matches boardurl, return true
	$result = strpos($urlpath, $boardurlpath);
	if ($result === false || $result != 0)
		return false;
	else
		return true;
}

/**
 * Settings really associated with general security aspects.
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyGeneralSecuritySettings($return_config = false)
{
	global $txt, $scripturl, $context;

	$config_vars = [
			['int', 'failed_login_threshold'],
			['int', 'loginHistoryDays', 'subtext' => $txt['zero_to_disable']],
		'',
			['check', 'securityDisable'],
		'',
			// Reactive on email, and approve on delete
			['check', 'send_validation_onChange'],
		'',
			// Password strength.
			['select', 'password_strength', [$txt['setting_password_strength_low'], $txt['setting_password_strength_medium'], $txt['setting_password_strength_high']]],
		'',
			['select', 'frame_security', ['SAMEORIGIN' => $txt['setting_frame_security_SAMEORIGIN'], 'DENY' => $txt['setting_frame_security_DENY'], 'DISABLE' => $txt['setting_frame_security_DISABLE']]],
		'',
			['select', 'proxy_ip_header', ['disabled' => $txt['setting_proxy_ip_header_disabled'], 'autodetect' => $txt['setting_proxy_ip_header_autodetect'], 'HTTP_X_FORWARDED_FOR' => 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP' => 'HTTP_CLIENT_IP', 'HTTP_X_REAL_IP' => 'HTTP_X_REAL_IP', 'CF-Connecting-IP' => 'CF-Connecting-IP']],
			['text', 'proxy_ip_servers'],
	];

	settings_integration_hook('integrate_general_security_settings', [&$config_vars]);

	if ($return_config)
		return [$txt['security_settings'], $config_vars];

	// Saving?
	if (isset($_GET['save']))
	{
		saveDBSettings($config_vars);
		session_flash('success', $txt['settings_saved']);

		settings_integration_hook('integrate_save_general_security_settings');

		writeLog();
		redirectexit('action=admin;area=serversettings;sa=security;' . $context['session_var'] . '=' . $context['session_id']);
	}

	$context['post_url'] = $scripturl . '?action=admin;area=serversettings;save;sa=security';
	$context['settings_title'] = $txt['security_settings'];

	prepareDBSettingContext($config_vars);
}

/**
 * Helper function, it sets up the context for the manage server settings.
 * - The basic usage of the six numbered key fields are
 * - array (0 ,1, 2, 3, 4, 5
 *		0 variable name - the name of the saved variable
 *		1 label - the text to show on the settings page
 *		2 saveto - file or db, where to save the variable name - value pair
 *		3 type - type of data to save, int, float, text, check
 *		4 size - false or field size
 *		5 help - '' or helptxt variable name
 *	)
 *
 * the following named keys are also permitted
 * 'disabled' => A string of code that will determine whether or not the setting should be disabled
 * 'postinput' => Text to display after the input field
 * 'preinput' => Text to display before the input field
 * 'subtext' => Additional descriptive text to display under the field's label
 * 'min' => minimum allowed value (for int/float). Defaults to 0 if not set.
 * 'max' => maximum allowed value (for int/float)
 * 'step' => how much to increment/decrement the value by (only for int/float - mostly used for float values).
 *
 * @param array $config_vars An array of configuration variables
 */
function prepareServerSettingsContext(&$config_vars)
{
	global $context, $modSettings;

	$context['config_vars'] = [];
	foreach ($config_vars as $identifier => $config_var)
	{
		if (!is_array($config_var) || !isset($config_var[1]))
			$context['config_vars'][] = $config_var;
		else
		{
			$varname = $config_var[0];
			global $$varname; // @todo Remove.

			// Set the subtext in case it's part of the label.
			// @todo Temporary. Preventing divs inside label tags.
			$divPos = strpos($config_var[1], '<div');
			$subtext = '';
			if ($divPos !== false)
			{
				$subtext = preg_replace('~</?div[^>]*>~', '', substr($config_var[1], $divPos));
				$config_var[1] = substr($config_var[1], 0, $divPos);
			}

			$context['config_vars'][$config_var[0]] = [
				'label' => $config_var[1],
				'help' => isset($config_var[5]) ? $config_var[5] : '',
				'type' => $config_var[3],
				'size' => empty($config_var[4]) ? 0 : $config_var[4],
				'data' => isset($config_var[4]) && is_array($config_var[4]) && $config_var[3] != 'select' ? $config_var[4] : [],
				'name' => $config_var[0],
				'value' => $config_var[2] == 'file' ? StringLibrary::escape($$varname) : (isset($modSettings[$config_var[0]]) ? StringLibrary::escape($modSettings[$config_var[0]]) : (in_array($config_var[3], ['int', 'float']) ? 0 : '')),
				'disabled' => !empty($context['settings_not_writable']) || !empty($config_var['disabled']),
				'invalid' => false,
				'subtext' => !empty($config_var['subtext']) ? $config_var['subtext'] : $subtext,
				'javascript' => '',
				'preinput' => !empty($config_var['preinput']) ? $config_var['preinput'] : '',
				'postinput' => !empty($config_var['postinput']) ? $config_var['postinput'] : '',
			];

			// Handle min/max/step if necessary
			if ($config_var[3] == 'int' || $config_var[3] == 'float')
			{
				// Default to a min of 0 if one isn't set
				if (isset($config_var['min']))
					$context['config_vars'][$config_var[0]]['min'] = $config_var['min'];
				else
					$context['config_vars'][$config_var[0]]['min'] = 0;

				if (isset($config_var['max']))
					$context['config_vars'][$config_var[0]]['max'] = $config_var['max'];

				if (isset($config_var['step']))
					$context['config_vars'][$config_var[0]]['step'] = $config_var['step'];
			}

			// If this is a select box handle any data.
			if (!empty($config_var[4]) && is_array($config_var[4]))
			{
				// If it's associative
				$config_values = array_values($config_var[4]);
				if (isset($config_values[0]) && is_array($config_values[0]))
					$context['config_vars'][$config_var[0]]['data'] = $config_var[4];
				else
				{
					foreach ($config_var[4] as $key => $item)
						$context['config_vars'][$config_var[0]]['data'][] = [$key, $item];
				}
			}
		}
	}

	// Two tokens because saving these settings requires both saveSettings and saveDBSettings
	createToken('admin-ssc');
	createToken('admin-dbsc');

	$context['sub_template'] = 'admin_settings';
}

/**
 * Helper function, it sets up the context for database settings.
 *
 * @param array $config_vars An array of configuration variables
 */
function prepareDBSettingContext(&$config_vars)
{
	global $txt, $helptxt, $context, $modSettings, $sourcedir;

	loadLanguage('Help');

	$context['config_vars'] = [];
	$inlinePermissions = [];
	$bbcChoice = [];
	$board_list = false;
	foreach ($config_vars as $config_var)
	{
		// HR?
		if (!is_array($config_var))
			$context['config_vars'][] = $config_var;
		else
		{
			// If it has no name it doesn't have any purpose!
			if (empty($config_var[1]))
				continue;

			// Special case for inline permissions
			if ($config_var[0] == 'permissions' && allowedTo('manage_permissions'))
				$inlinePermissions[] = $config_var[1];
			elseif ($config_var[0] == 'permissions')
				continue;

			if ($config_var[0] == 'boards')
				$board_list = true;

			// Are we showing the BBC selection box?
			if ($config_var[0] == 'bbc')
				$bbcChoice[] = $config_var[1];

			// We need to do some parsing of the value before we pass it in.
			if (isset($modSettings[$config_var[1]]))
			{
				switch ($config_var[0])
				{
					case 'select':
						$value = $modSettings[$config_var[1]];
						break;
					case 'json':
						$value = StringLibrary::escape(json_encode($modSettings[$config_var[1]]));
						break;
					case 'boards':
						$value = explode(',', $modSettings[$config_var[1]]);
						break;
					default:
						$value = StringLibrary::escape($modSettings[$config_var[1]]);
				}
			}
			else
			{
				// Darn, it's empty. What type is expected?
				switch ($config_var[0])
				{
					case 'int':
					case 'float':
						$value = 0;
						break;
					case 'select':
						$value = !empty($config_var['multiple']) ? json_encode([]) : '';
						break;
					case 'boards':
						$value = [];
						break;
					default:
						$value = '';
				}
			}

			$context['config_vars'][$config_var[1]] = [
				'label' => isset($config_var['text_label']) ? $config_var['text_label'] : (isset($txt[$config_var[1]]) ? $txt[$config_var[1]] : (isset($config_var[3]) && !is_array($config_var[3]) ? $config_var[3] : '')),
				'help' => isset($helptxt[$config_var[1]]) ? $config_var[1] : '',
				'type' => $config_var[0],
				'size' => !empty($config_var['size']) ? $config_var['size'] : (!empty($config_var[2]) && !is_array($config_var[2]) ? $config_var[2] : (in_array($config_var[0], ['int', 'float']) ? 6 : 0)),
				'data' => [],
				'name' => $config_var[1],
				'value' => $value,
				'disabled' => false,
				'invalid' => !empty($config_var['invalid']),
				'javascript' => '',
				'var_message' => !empty($config_var['message']) && isset($txt[$config_var['message']]) ? $txt[$config_var['message']] : '',
				'preinput' => isset($config_var['preinput']) ? $config_var['preinput'] : '',
				'postinput' => isset($config_var['postinput']) ? $config_var['postinput'] : '',
			];

			// Handle min/max/step if necessary
			if ($config_var[0] == 'int' || $config_var[0] == 'float')
			{
				// Default to a min of 0 if one isn't set
				if (isset($config_var['min']))
					$context['config_vars'][$config_var[1]]['min'] = $config_var['min'];
				else
					$context['config_vars'][$config_var[1]]['min'] = 0;

				if (isset($config_var['max']))
					$context['config_vars'][$config_var[1]]['max'] = $config_var['max'];

				if (isset($config_var['step']))
					$context['config_vars'][$config_var[1]]['step'] = $config_var['step'];
			}

			// If this is a select box handle any data.
			if (!empty($config_var[2]) && is_array($config_var[2]))
			{
				// If we allow multiple selections, we need to adjust a few things.
				if ($config_var[0] == 'select' && !empty($config_var['multiple']))
				{
					$context['config_vars'][$config_var[1]]['name'] .= '[]';
					$context['config_vars'][$config_var[1]]['value'] = !empty($context['config_vars'][$config_var[1]]['value']) ? sbb_json_decode($context['config_vars'][$config_var[1]]['value'], true) : [];
				}

				// If it's associative
				if (isset($config_var[2][0]) && is_array($config_var[2][0]))
					$context['config_vars'][$config_var[1]]['data'] = $config_var[2];
				else
				{
					foreach ($config_var[2] as $key => $item)
						$context['config_vars'][$config_var[1]]['data'][] = [$key, $item];
				}
			}

			// Finally allow overrides - and some final cleanups.
			foreach ($config_var as $k => $v)
			{
				if (!is_numeric($k))
				{
					if (substr($k, 0, 2) == 'on')
						$context['config_vars'][$config_var[1]]['javascript'] .= ' ' . $k . '="' . $v . '"';
					else
						$context['config_vars'][$config_var[1]][$k] = $v;
				}

				// See if there are any other labels that might fit?
				if (isset($txt['setting_' . $config_var[1]]))
					$context['config_vars'][$config_var[1]]['label'] = $txt['setting_' . $config_var[1]];
				elseif (isset($txt['groups_' . $config_var[1]]))
					$context['config_vars'][$config_var[1]]['label'] = $txt['groups_' . $config_var[1]];
			}

			// Set the subtext in case it's part of the label.
			// @todo Temporary. Preventing divs inside label tags.
			$divPos = strpos($context['config_vars'][$config_var[1]]['label'], '<div');
			if ($divPos !== false)
			{
				$context['config_vars'][$config_var[1]]['subtext'] = preg_replace('~</?div[^>]*>~', '', substr($context['config_vars'][$config_var[1]]['label'], $divPos));
				$context['config_vars'][$config_var[1]]['label'] = substr($context['config_vars'][$config_var[1]]['label'], 0, $divPos);
			}
		}
	}

	// If we have inline permissions we need to prep them.
	if (!empty($inlinePermissions) && allowedTo('manage_permissions'))
	{
		require_once($sourcedir . '/ManagePermissions.php');
		init_inline_permissions($inlinePermissions, isset($context['permissions_excluded']) ? $context['permissions_excluded'] : []);
	}

	if ($board_list)
	{
		require_once($sourcedir . '/Subs-MessageIndex.php');
		$context['board_list'] = getBoardList();

		addInlineJavascript('
		$("legend.board_selector").closest("fieldset").hide();
		$("a.board_selector").click(function(e) {
			e.preventDefault();
			$(this).hide().next("fieldset").show();
		});
		$("fieldset legend.board_selector a").click(function(e) {
			e.preventDefault();
			$(this).closest("fieldset").hide().prev("a").show();
		});
		', true);
	}

	// What about any BBC selection boxes?
	if (!empty($bbcChoice))
	{
		// What are the options, eh?
		$bbcTags = AbstractParser::get_all_bbcodes();
		$totalTags = count($bbcTags);

		// The number of columns we want to show the BBC tags in.
		$numColumns = isset($context['num_bbc_columns']) ? $context['num_bbc_columns'] : 3;

		// Start working out the context stuff.
		$context['bbc_columns'] = [];
		$tagsPerColumn = ceil($totalTags / $numColumns);

		$col = 0;
		$i = 0;
		foreach ($bbcTags as $tag)
		{
			if ($i % $tagsPerColumn == 0 && $i != 0)
				$col++;

			$context['bbc_columns'][$col][] = [
				'tag' => $tag,
				// @todo  'tag_' . ?
				'show_help' => isset($helptxt[$tag]),
			];

			$i++;
		}

		// Now put whatever BBC options we may have into context too!
		foreach ($bbcChoice as $bbc)
		{
			$context['config_vars'][$bbc] += [
				'bbc_title' => isset($txt['bbc_title_' . $bbc]) ? $txt['bbc_title_' . $bbc] : $txt['bbcTagsToUse_select'],
				'bbc_disabled' => empty($modSettings['bbc_disabled_' . $bbc]) ? [] : $modSettings['bbc_disabled_' . $bbc],
				'all_selected' => empty($modSettings['bbc_disabled_' . $bbc]),
			];
		}
	}

	call_integration_hook('integrate_prepare_db_settings', [&$config_vars]);
	createToken('admin-dbsc');

	$context['sub_template'] = 'admin_settings';
}

/**
 * Helper function. Saves settings by putting them in Settings.php or saving them in the settings table.
 *
 * - Saves those settings set from ?action=admin;area=serversettings.
 * - Requires the admin_forum permission.
 * - Contains arrays of the types of data to save into Settings.php.
 *
 * @param array $config_vars An array of configuration variables
 */
function saveSettings(&$config_vars)
{
	global $sourcedir;

	validateToken('admin-ssc');

	// Fix the darn stupid cookiename! (more may not be allowed, but these for sure!)
	if (isset($_POST['cookiename']))
		$_POST['cookiename'] = preg_replace('~[,;\s\.$]+~u', '', $_POST['cookiename']);

	// Fix the forum's URL if necessary.
	if (isset($_POST['boardurl']))
	{
		if (substr($_POST['boardurl'], -10) == '/index.php')
			$_POST['boardurl'] = substr($_POST['boardurl'], 0, -10);
		elseif (substr($_POST['boardurl'], -1) == '/')
			$_POST['boardurl'] = substr($_POST['boardurl'], 0, -1);
		if (substr($_POST['boardurl'], 0, 7) != 'http://' && substr($_POST['boardurl'], 0, 7) != 'file://' && substr($_POST['boardurl'], 0, 8) != 'https://')
			$_POST['boardurl'] = 'http://' . $_POST['boardurl'];
	}

	// Any passwords?
	$config_passwords = [
		'db_passwd',
	];

	// All the strings to write.
	$config_strs = [
		'language', 'boardurl',
		'cookiename',
		'db_name', 'db_user', 'db_server', 'db_prefix',
		'boarddir', 'sourcedir',
		'cachedir', 'cachedir_sqlite', 'cache_accelerator', 'cache_memcached', 'cache_redis',
		'image_proxy_secret',
	];

	// All the numeric variables.
	$config_ints = [
		'cache_enable',
		'image_proxy_maxsize',
	];

	// All the checkboxes
	$config_bools = ['db_persist', 'image_proxy_enabled'];

	// Now sort everything into a big array, and figure out arrays and etc.
	$new_settings = [];
	// Figure out which config vars we're saving here...
	foreach ($config_vars as $var)
	{
		if (!is_array($var) || $var[2] != 'file' || (!in_array($var[0], $config_bools) && !isset($_POST[$var[0]])))
			continue;

		$config_var = $var[0];

		if (in_array($config_var, $config_passwords))
		{
			if (isset($_POST[$config_var][1]) && $_POST[$config_var][0] == $_POST[$config_var][1])
				$new_settings[$config_var] = '\'' . addcslashes($_POST[$config_var][0], '\'\\') . '\'';
		}
		elseif (in_array($config_var, $config_strs))
		{
			$new_settings[$config_var] = '\'' . addcslashes($_POST[$config_var], '\'\\') . '\'';
		}
		elseif (in_array($config_var, $config_ints))
		{
			$new_settings[$config_var] = (int) $_POST[$config_var];

			// If no min is specified, assume 0. This is done to avoid having to specify 'min => 0' for all settings where 0 is the min...
			$min = isset($var['min']) ? $var['min'] : 0;
			$new_settings[$config_var] = max($min, $new_settings[$config_var]);

			// Is there a max value for this as well?
			if (isset($var['max']))
				$new_settings[$config_var] = min($var['max'], $new_settings[$config_var]);
		}
		elseif (in_array($config_var, $config_bools))
		{
			if (!empty($_POST[$config_var]))
				$new_settings[$config_var] = '1';
			else
				$new_settings[$config_var] = '0';
		}
		else
		{
			// This shouldn't happen, but it might...
			fatal_error('Unknown config_var \'' . $config_var . '\'');
		}
	}

	// Save the relevant settings in the Settings.php file.
	require_once($sourcedir . '/Subs-Admin.php');
	updateSettingsFile($new_settings);

	// Now loop through the remaining (database-based) settings.
	$new_settings = [];
	foreach ($config_vars as $config_var)
	{
		// We just saved the file-based settings, so skip their definitions.
		if (!is_array($config_var) || $config_var[2] == 'file')
			continue;

		$new_setting = [$config_var[3], $config_var[0]];

		// Select options need carried over, too.
		if (isset($config_var[4]))
			$new_setting[] = $config_var[4];

		// Include min and max if necessary
		if (isset($config_var['min']))
			$new_setting['min'] = $config_var['min'];

		if (isset($config_var['max']))
			$new_setting['max'] = $config_var['max'];

		// Rewrite the definition a bit.
		$new_settings[] = $new_setting;
	}

	// Save the new database-based settings, if any.
	if (!empty($new_settings))
		saveDBSettings($new_settings);
}

/**
 * Helper function for saving database settings.
 *
 * @param array $config_vars An array of configuration variables
 */
function saveDBSettings(&$config_vars)
{
	global $sourcedir, $smcFunc;
	static $board_list = null;

	validateToken('admin-dbsc');

	$inlinePermissions = [];
	foreach ($config_vars as $var)
	{
		if (!isset($var[1]) || (!isset($_POST[$var[1]]) && $var[0] != 'check' && $var[0] != 'permissions' && $var[0] != 'boards' && ($var[0] != 'bbc' || !isset($_POST[$var[1] . '_enabledTags']))))
			continue;

		// Checkboxes!
		elseif ($var[0] == 'check')
			$setArray[$var[1]] = !empty($_POST[$var[1]]) ? '1' : '0';
		// Select boxes!
		elseif ($var[0] == 'select' && in_array($_POST[$var[1]], array_keys($var[2])))
			$setArray[$var[1]] = $_POST[$var[1]];
		elseif ($var[0] == 'select' && !empty($var['multiple']) && array_intersect($_POST[$var[1]], array_keys($var[2])) != [])
		{
			// For security purposes we validate this line by line.
			$lOptions = [];
			foreach ($_POST[$var[1]] as $invar)
				if (in_array($invar, array_keys($var[2])))
					$lOptions[] = $invar;

			$setArray[$var[1]] = json_encode($lOptions);
		}
		// List of boards!
		elseif ($var[0] == 'boards')
		{
			// We just need a simple list of valid boards, nothing more.
			if ($board_list === null)
			{
				$board_list = [];
				$request = $smcFunc['db']->query('', '
					SELECT id_board
					FROM {db_prefix}boards');
				while ($row = $smcFunc['db']->fetch_row($request))
					$board_list[$row[0]] = true;

				$smcFunc['db']->free_result($request);
			}

			$lOptions = [];

			if (!empty($_POST[$var[1]]))
				foreach ($_POST[$var[1]] as $invar => $dummy)
					if (isset($board_list[$invar]))
						$lOptions[] = $invar;

			$setArray[$var[1]] = !empty($lOptions) ? implode(',', $lOptions) : '';
		}
		// Integers!
		elseif ($var[0] == 'int')
		{
			$setArray[$var[1]] = (int) $_POST[$var[1]];

			// If no min is specified, assume 0. This is done to avoid having to specify 'min => 0' for all settings where 0 is the min...
			$min = isset($var['min']) ? $var['min'] : 0;
			$setArray[$var[1]] = max($min, $setArray[$var[1]]);

			// Do we have a max value for this as well?
			if (isset($var['max']))
				$setArray[$var[1]] = min($var['max'], $setArray[$var[1]]);
		}
		// Floating point!
		elseif ($var[0] == 'float')
		{
			$setArray[$var[1]] = (float) $_POST[$var[1]];

			// If no min is specified, assume 0. This is done to avoid having to specify 'min => 0' for all settings where 0 is the min...
			$min = isset($var['min']) ? $var['min'] : 0;
			$setArray[$var[1]] = max($min, $setArray[$var[1]]);

			// Do we have a max value for this as well?
			if (isset($var['max']))
				$setArray[$var[1]] = min($var['max'], $setArray[$var[1]]);
		}
		// Text!
		elseif (in_array($var[0], ['text', 'large_text', 'color', 'date', 'datetime', 'datetime-local', 'email', 'month', 'time']))
			$setArray[$var[1]] = $_POST[$var[1]];
		// Passwords!
		elseif ($var[0] == 'password')
		{
			if (isset($_POST[$var[1]][1]) && $_POST[$var[1]][0] == $_POST[$var[1]][1])
				$setArray[$var[1]] = $_POST[$var[1]][0];
		}
		// BBC.
		elseif ($var[0] == 'bbc')
		{
			$bbcTags = AbstractParser::get_all_bbcodes();

			if (!isset($_POST[$var[1] . '_enabledTags']))
				$_POST[$var[1] . '_enabledTags'] = [];
			elseif (!is_array($_POST[$var[1] . '_enabledTags']))
				$_POST[$var[1] . '_enabledTags'] = [$_POST[$var[1] . '_enabledTags']];

			$setArray[$var[1]] = implode(',', array_diff($bbcTags, $_POST[$var[1] . '_enabledTags']));
		}
		// Permissions?
		elseif ($var[0] == 'permissions')
			$inlinePermissions[] = $var[1];
	}

	if (!empty($setArray))
		updateSettings($setArray);

	// If we have inline permissions we need to save them.
	if (!empty($inlinePermissions) && allowedTo('manage_permissions'))
	{
		require_once($sourcedir . '/ManagePermissions.php');
		save_inline_permissions($inlinePermissions);
	}
}
