<?php

/**
 * This file has the primary job of showing and editing people's profiles.
 * 	It also allows the user to change some of their or another's preferences,
 * 	and such things
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Container;
use StoryBB\Model\Alert;
use StoryBB\Model\Attachment;
use StoryBB\Helper\Autocomplete;
use StoryBB\Helper\Parser;
use StoryBB\Hook\Observable;
use StoryBB\StringLibrary;
use GuzzleHttp\Client;

/**
 * This defines every profile field known to man.
 *
 * @param bool $force_reload Whether to reload the data
 */
function loadProfileFields($force_reload = false)
{
	global $context, $profile_fields, $txt, $scripturl, $modSettings, $user_info, $smcFunc, $cur_profile, $language;
	global $sourcedir, $profile_vars;

	// Don't load this twice!
	if (!empty($profile_fields) && !$force_reload)
		return;

	/* This horrific array defines all the profile fields in the whole world!
		In general each "field" has one array - the key of which is the database column name associated with said field. Each item
		can have the following attributes:

				string $type:			The type of field this is - valid types are:
					- callback:		This is a field which has its own callback mechanism for templating.
					- check:		A simple checkbox.
					- hidden:		This doesn't have any visual aspects but may have some validity.
					- password:		A password box.
					- select:		A select box.
					- text:			A string of some description.

				string $label:			The label for this item - default will be $txt[$key] if this isn't set.
				string $subtext:		The subtext (Small label) for this item.
				int $size:			Optional size for a text area.
				array $input_attr:		An array of text strings to be added to the input box for this item.
				string $value:			The value of the item. If not set $cur_profile[$key] is assumed.
				string $permission:		Permission required for this item (Excluded _any/_own subfix which is applied automatically).
				function $input_validate:	A runtime function which validates the element before going to the database. It is passed
								the relevant $_POST element if it exists and should be treated like a reference.

								Return types:
					- true:			Element can be stored.
					- false:		Skip this element.
					- a text string:	An error occured - this is the error message.

				function $preload:		A function that is used to load data required for this element to be displayed. Must return
								true to be displayed at all.

				string $cast_type:		If set casts the element to a certain type. Valid types (bool, int, float).
				string $save_key:		If the index of this element isn't the database column name it can be overriden
								with this string.
				bool $is_dummy:			If set then nothing is acted upon for this element.
				bool $enabled:			A test to determine whether this is even available - if not is unset.
				string $link_with:		Key which links this field to an overall set.

		Note that all elements that have a custom input_validate must ensure they set the value of $cur_profile correct to enable
		the changes to be displayed correctly on submit of the form.

	*/

	$profile_fields = [
		'avatar_choice' => [
			'type' => 'callback',
			'callback_func' => 'avatar_select',
			// This handles the permissions too.
			'preload' => 'profileLoadAvatarData',
			'input_validate' => 'profileSaveAvatarData',
			'save_key' => 'avatar',
		],
		'birthday_date' => [
			'type' => 'callback',
			'callback_func' => 'birthday_date',
			'permission' => 'profile_extra',
			'preload' => function() use ($cur_profile, &$context, $txt, $modSettings)
			{
				if (empty($cur_profile['birthdate']) || $cur_profile['birthdate'] == '1004-01-01')
				{
					// No existing birthdate, therefore editable.
					$context['member']['birthdate'] = '';
					$context['member']['birthdate_editable'] = true;
					$context['member']['birth_date'] = [
						'year' => '',
						'month' => '',
						'day' => '',
					];
				}
				elseif (!allowedTo('admin_forum'))
				{
					// Regular members aren't allowed to edit their DOB.
					list ($year, $month, $day) = explode('-', $cur_profile['birthdate']);
					$context['member']['formatted_birthdate'] = dateformat($year, $month, $day, $cur_profile['time_format']);

					$context['member']['birthdate_editable'] = false;
				}
				else
				{
					// The profile user has entered a date and the current user is an admin.
					list($year, $month, $day) = explode('-', $cur_profile['birthdate']);
					$context['member']['birth_date'] = [
						'year' => (int) $year,
						'month' => (int) $month,
						'day' => (int) $day,
					];
					$context['member']['birthdate_editable'] = true;
				}

				return true;
			},
			'input_validate' => function(&$value) use (&$cur_profile, &$profile_vars, $modSettings)
			{
				if (!empty($cur_profile['birthdate']) && $cur_profile['birthdate'] != '1004-01-01')
				{
					// If it's not already empty, and it's being edited, make sure we're an admin.
					if (!allowedTo('admin_forum'))
					{
						$value = $cur_profile['birthdate'];
						return true;
					}
				}

				// OK so either we're adding a date for the first time or we're updating an existing date.
				if (isset($_POST['bday1'], $_POST['bday2'], $_POST['bday3']))
				{
					// Make sure it's valid and if it is, handle it.
					$value = checkdate($_POST['bday1'], $_POST['bday2'], $_POST['bday3'] < 1004 ? 1004 : $_POST['bday3']) ? sprintf('%04d-%02d-%02d', $_POST['bday3'] < 1004 ? 1004 : $_POST['bday3'], $_POST['bday1'], $_POST['bday2']) : '1004-01-01';

					// Also check if it's valid or not.
					if (!empty($modSettings['minimum_age']) && !empty($modSettings['minimum_age_profile']) && $value != '1004-01-01')
					{
						$datearray = getdate(forum_time());
						$age = $datearray['year'] - $_POST['bday3'] - (($datearray['mon'] > $_POST['bday1'] || ($datearray['mon'] == $_POST['bday1'] && $datearray['mday'] >= $_POST['bday2'])) ? 0 : 1);
						if ($age < (int) $modSettings['minimum_age'])
						{
							return false;
						}
					}
				}
				else
				{
					// Not sure what happened here. Put the value back to what it was.
					$value = $cur_profile['birthdate'];
				}

				return true;
			},
			'link_with' => 'birthday',
		],
		'birthday_visibility' => [
			'type' => 'select',
			'options' => [
				0 => $txt['birthday_visibility_adminonly'],
				1 => $txt['birthday_visibility_daymonth'],
				2 => $txt['birthday_visibility_fulldate'],
			],
			'permission' => 'profile_extra',
			'link_with' => 'birthday',
		],
		'date_registered' => [
			'type' => 'date',
			'value' => empty($cur_profile['date_registered']) ? $txt['not_applicable'] : strftime('%Y-%m-%d', $cur_profile['date_registered'] + ($user_info['time_offset'] + $modSettings['time_offset']) * 3600),
			'label' => $txt['date_registered'],
			'log_change' => true,
			'permission' => 'moderate_forum',
			'input_validate' => function(&$value) use ($txt, $user_info, $modSettings, $cur_profile, $context)
			{
				// Bad date!  Go try again - please?
				if (($value = strtotime($value)) === false)
				{
					$value = $cur_profile['date_registered'];
					return $txt['invalid_registration'] . ' ' . strftime('%d %b %Y ' . (strpos($user_info['time_format'], '%H') !== false ? '%I:%M:%S %p' : '%H:%M:%S'), forum_time(false));
				}
				// As long as it doesn't equal "N/A"...
				elseif ($value != $txt['not_applicable'] && $value != strtotime(strftime('%Y-%m-%d', $cur_profile['date_registered'] + ($user_info['time_offset'] + $modSettings['time_offset']) * 3600)))
					$value = $value - ($user_info['time_offset'] + $modSettings['time_offset']) * 3600;
				else
					$value = $cur_profile['date_registered'];

				return true;
			},
		],
		'email_address' => [
			'type' => 'email',
			'label' => $txt['user_email_address'],
			'subtext' => $txt['valid_email'],
			'log_change' => true,
			'permission' => 'profile_password',
			'js_submit' => !empty($modSettings['send_validation_onChange']) ? '
	form_handle.addEventListener(\'submit\', function(event)
	{
		if (this.email_address.value != "'. $cur_profile['email_address'] . '")
		{
			alert('. JavaScriptEscape($txt['email_change_logout']) . ');
			return true;
		}
	}, false);' : '',
			'input_validate' => function(&$value)
			{
				global $context, $old_profile, $profile_vars, $sourcedir, $modSettings;

				if (strtolower($value) == strtolower($old_profile['email_address']))
					return false;

				$isValid = profileValidateEmail($value, $context['id_member']);

				// Do they need to revalidate? If so schedule the function!
				if ($isValid === true && !empty($modSettings['send_validation_onChange']) && !allowedTo('moderate_forum'))
				{
					require_once($sourcedir . '/Subs-Members.php');
					$profile_vars['validation_code'] = generateValidationCode();
					$profile_vars['is_activated'] = 2;
					$context['profile_execute_on_save'][] = 'profileSendActivation';
					unset($context['profile_execute_on_save']['reload_user']);
				}

				return $isValid;
			},
		],
		// Selecting group membership is a complicated one so we treat it separate!
		'id_group' => [
			'type' => 'callback',
			'callback_func' => 'group_manage',
			'permission' => 'manage_membergroups',
			'preload' => 'profileLoadGroups',
			'log_change' => true,
			'input_validate' => 'profileSaveGroups',
		],
		'id_theme' => [
			'type' => 'callback',
			'callback_func' => 'theme_pick',
			'permission' => 'profile_extra',
			'enabled' => $modSettings['theme_allow'] || allowedTo('admin_forum'),
			'preload' => function() use ($smcFunc, &$context, $cur_profile, $txt)
			{
				$request = $smcFunc['db']->query('', '
					SELECT value
					FROM {db_prefix}themes
					WHERE id_theme = {int:id_theme}
						AND variable = {string:variable}
					LIMIT 1', [
						'id_theme' => $cur_profile['id_theme'],
						'variable' => 'name',
					]
				);
				list ($name) = $smcFunc['db']->fetch_row($request);
				$smcFunc['db']->free_result($request);

				$context['member']['theme'] = [
					'id' => $cur_profile['id_theme'],
					'name' => empty($cur_profile['id_theme']) ? $txt['theme_forum_default'] : $name
				];
				return true;
			},
			'input_validate' => function(&$value)
			{
				$value = (int) $value;
				return true;
			},
		],
		'immersive_mode' => [
			'type' => 'check',
			'label' => $txt['immersive_mode'],
			'subtext' => $txt['immersive_mode_desc'],
			'permission' => 'profile_identity',
			'enabled' => in_array($modSettings['enable_immersive_mode'], ['user_on', 'user_off']),
		],
		'lngfile' => [
			'type' => 'select',
			'options' => function() use (&$context)
			{
				return $context['profile_languages'];
			},
			'label' => $txt['preferred_language'],
			'permission' => 'profile_identity',
			'preload' => 'profileLoadLanguages',
			'enabled' => !empty($modSettings['userLanguage']),
			'value' => empty($cur_profile['lngfile']) ? $language : $cur_profile['lngfile'],
			'input_validate' => function(&$value) use (&$context, $cur_profile)
			{
				// Load the languages.
				profileLoadLanguages();

				if (isset($context['profile_languages'][$value]))
				{
					if ($context['user']['is_owner'] && empty($context['password_auth_failed']))
						$_SESSION['language'] = $value;
					return true;
				}
				else
				{
					$value = $cur_profile['lngfile'];
					return false;
				}
			},
		],
		// The username is not always editable - so adjust it as such.
		'member_name' => [
			'type' => allowedTo('admin_forum') && isset($_GET['changeusername']) ? 'text' : 'label',
			'label' => $txt['username'],
			'subtext' => allowedTo('admin_forum') && !isset($_GET['changeusername']) ? '[<a href="' . $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=account;changeusername" style="font-style: italic;">' . $txt['username_change'] . '</a>]' : '',
			'log_change' => true,
			'permission' => 'profile_identity',
			'prehtml' => allowedTo('admin_forum') && isset($_GET['changeusername']) ? '<div class="alert">' . $txt['username_warning'] . '</div>' : '',
			'input_validate' => function(&$value) use ($sourcedir, $context, $user_info, $cur_profile)
			{
				if (allowedTo('admin_forum'))
				{
					// We'll need this...
					require_once($sourcedir . '/Subs-Auth.php');

					// Maybe they are trying to change their password as well?
					$resetPassword = true;
					if (isset($_POST['passwrd1']) && $_POST['passwrd1'] != '' && isset($_POST['passwrd2']) && $_POST['passwrd1'] == $_POST['passwrd2'] && validatePassword($_POST['passwrd1'], $value, [$cur_profile['real_name'], $user_info['username'], $user_info['name'], $user_info['email']]) == null)
						$resetPassword = false;

					// Do the reset... this will send them an email too.
					if ($resetPassword)
						resetPassword($context['id_member'], $value);
					elseif ($value !== null)
					{
						validateUsername($context['id_member'], trim(preg_replace('~[\t\n\r \x0B\0\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}]+~u', ' ', $value)));
						updateMemberData($context['id_member'], ['member_name' => $value]);

						// Call this here so any integrated systems will know about the name change (resetPassword() takes care of this if we're letting StoryBB generate the password)
						(new Observable\Account\PasswordReset($cur_profile['member_name'], $value, $_POST['passwrd1']))->execute();
					}
				}
				return false;
			},
		],
		'passwrd1' => [
			'type' => 'password',
			'label' => ucwords($txt['choose_pass']),
			'subtext' => $txt['password_strength'],
			'size' => 20,
			'value' => '',
			'permission' => 'profile_password',
			'save_key' => 'passwd',
			// Note this will only work if passwrd2 also exists!
			'input_validate' => function(&$value) use ($sourcedir, $user_info, $smcFunc, $cur_profile, $modSettings)
			{
				global $txt;

				// If we didn't try it then ignore it!
				if ($value == '')
					return false;

				// Do the two entries for the password even match?
				if (!isset($_POST['passwrd2']) || $value != $_POST['passwrd2'])
					return 'bad_new_password';

				// Let's get the validation function into play...
				require_once($sourcedir . '/Subs-Auth.php');
				$passwordErrors = validatePassword($value, $cur_profile['member_name'], [$cur_profile['real_name'], $user_info['username'], $user_info['name'], $user_info['email']]);

				// Were there errors?
				if ($passwordErrors != null)
				{
					loadLanguage('Errors');
					$txt['profile_error_password_short'] = numeric_context('profile_error_password_short_contextual', (empty($modSettings['password_strength']) ? 4 : 8));

					return 'password_' . $passwordErrors;
				}

				// Set up the new password variable... ready for storage.
				$value = hash_password($cur_profile['member_name'], un_htmlspecialchars($value));

				return true;
			},
		],
		'passwrd2' => [
			'type' => 'password',
			'label' => ucwords($txt['verify_pass']),
			'size' => 20,
			'value' => '',
			'permission' => 'profile_password',
			'is_dummy' => true,
		],
		// This does ALL the pm settings
		'pm_prefs' => [
			'type' => 'callback',
			'callback_func' => 'pm_settings',
			'permission' => 'pm_read',
			'preload' => function() use (&$context, $cur_profile)
			{
				$context['receive_from'] = !empty($cur_profile['pm_receive_from']) ? $cur_profile['pm_receive_from'] : 0;

				return true;
			},
			'input_validate' => function(&$value) use (&$cur_profile, &$profile_vars)
			{
				// Simple validate and apply the two "sub settings"
				$value = max(min($value, 2), 0);

				$cur_profile['pm_receive_from'] = $profile_vars['pm_receive_from'] = max(min((int) $_POST['pm_receive_from'], 4), 0);

				return true;
			},
		],
		'posts' => [
			'type' => 'int',
			'label' => $txt['profile_posts'],
			'log_change' => true,
			'size' => 7,
			'permission' => 'moderate_forum',
			'input_validate' => function(&$value)
			{
				if (!is_numeric($value))
					return 'digits_only';
				else
					$value = $value != '' ? strtr($value, [',' => '', '.' => '', ' ' => '']) : 0;
				return true;
			},
		],
		'real_name' => [
			'type' => allowedTo('profile_displayed_name_own') || allowedTo('profile_displayed_name_any') || allowedTo('moderate_forum') ? 'text' : 'label',
			'label' => $txt['name'],
			'subtext' => $txt['display_name_desc'],
			'log_change' => true,
			'input_attr' => ['maxlength="60"'],
			'permission' => 'profile_displayed_name',
			'enabled' => allowedTo('profile_displayed_name_own') || allowedTo('profile_displayed_name_any') || allowedTo('moderate_forum'),
			'input_validate' => function(&$value) use ($context, $smcFunc, $sourcedir, $cur_profile)
			{
				$value = trim(preg_replace('~[\t\n\r \x0B\0\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}]+~u', ' ', $value));

				if (trim($value) == '')
					return 'no_name';
				elseif (StringLibrary::strpos($value) > 60)
					return 'name_too_long';
				elseif ($cur_profile['real_name'] != $value)
				{
					require_once($sourcedir . '/Subs-Members.php');
					if (isReservedName($value, $context['id_member']))
						return 'name_taken';
				}
				return true;
			},
		],
		'secret_question' => [
			'type' => 'text',
			'label' => $txt['secret_question'],
			'subtext' => $txt['secret_desc'],
			'size' => 50,
			'permission' => 'profile_password',
		],
		'secret_answer' => [
			'type' => 'text',
			'label' => $txt['secret_answer'],
			'subtext' => $txt['secret_desc2'],
			'size' => 20,
			'postinput' => '<span class="smalltext"><a href="' . $scripturl . '?action=helpadmin;help=secret_why_blank" onclick="return reqOverlayDiv(this.href);" class="main_icons help">' . $txt['secret_why_blank'] . '</a></span>',
			'value' => '',
			'permission' => 'profile_password',
			'input_validate' => function(&$value)
			{
				$value = $value != '' ? md5($value) : '';
				return true;
			},
		],
		'signature' => [
			'type' => 'callback',
			'callback_func' => 'signature_modify',
			'permission' => 'profile_signature',
			'enabled' => substr($modSettings['signature_settings'], 0, 1) == 1,
			'preload' => 'profileLoadSignatureData',
			'input_validate' => 'profileValidateSignature',
		],
		'show_online' => [
			'type' => 'check',
			'label' => $txt['show_online'],
			'permission' => 'profile_identity',
			'enabled' => !empty($modSettings['allow_hideOnline']) || allowedTo('moderate_forum'),
		],
		// Pretty much a dummy entry - it populates all the theme settings.
		'theme_settings' => [
			'type' => 'callback',
			'callback_func' => 'theme_settings',
			'permission' => 'profile_extra',
			'is_dummy' => true,
			'preload' => function() use (&$context, $user_info, $modSettings)
			{
				loadLanguage('Settings');

				$context['allow_no_censored'] = false;
				if ($user_info['is_admin'] || $context['user']['is_owner'])
					$context['allow_no_censored'] = !empty($modSettings['allow_no_censored']);

				return true;
			},
		],
		'time_format' => [
			'type' => 'select',
			'options' => array_merge(['' => $txt['timeformat_default']], \StoryBB\Helper\Datetime::list_dateformats()),
			// 'callback_func' => 'timeformat_modify',
			'permission' => 'profile_extra',
		],
		'timezone' => [
			'type' => 'select',
			'options' => \StoryBB\Helper\Datetime::list_timezones(),
			'permission' => 'profile_extra',
			'label' => $txt['timezone'],
			'input_validate' => function($value)
			{
				$tz = \StoryBB\Helper\Datetime::list_timezones();
				if (!isset($tz[$value]))
					return 'bad_timezone';

				return true;
			},
		],
		'website_title' => [
			'type' => 'text',
			'label' => $txt['website_title'],
			'subtext' => $txt['include_website_url'],
			'size' => 50,
			'permission' => 'profile_website',
			'link_with' => 'website',
		],
		'website_url' => [
			'type' => 'url',
			'label' => $txt['website_url'],
			'subtext' => $txt['complete_url'],
			'size' => 50,
			'permission' => 'profile_website',
			// Fix the URL...
			'input_validate' => function(&$value)
			{
				if (strlen(trim($value)) > 0 && strpos($value, '://') === false)
					$value = 'http://' . $value;
				if (strlen($value) < 8 || (substr($value, 0, 7) !== 'http://' && substr($value, 0, 8) !== 'https://'))
					$value = '';
				return true;
			},
			'link_with' => 'website',
		],
	];

	call_integration_hook('integrate_load_profile_fields', [&$profile_fields]);

	$disabled_fields = !empty($modSettings['disabled_profile_fields']) ? explode(',', $modSettings['disabled_profile_fields']) : [];
	// For each of the above let's take out the bits which don't apply - to save memory and security!
	foreach ($profile_fields as $key => $field)
	{
		// Do we have permission to do this?
		if (isset($field['permission']) && !allowedTo(($context['user']['is_owner'] ? [$field['permission'] . '_own', $field['permission'] . '_any'] : $field['permission'] . '_any')) && !allowedTo($field['permission']))
			unset($profile_fields[$key]);

		// Is it enabled?
		if (isset($field['enabled']) && !$field['enabled'])
			unset($profile_fields[$key]);

		// Is it specifically disabled?
		if (in_array($key, $disabled_fields) || (isset($field['link_with']) && in_array($field['link_with'], $disabled_fields)))
			unset($profile_fields[$key]);
	}
}

/**
 * Setup the context for a page load!
 *
 * @param array $fields The profile fields to display. Each item should correspond to an item in the $profile_fields array generated by loadProfileFields
 */
function setupProfileContext($fields)
{
	global $profile_fields, $context, $cur_profile, $txt, $scripturl;

	// Some default bits.
	$context['profile_prehtml'] = '';
	$context['profile_posthtml'] = '';
	$context['profile_javascript'] = '';
	$context['profile_onsubmit_javascript'] = '';

	call_integration_hook('integrate_setup_profile_context', [&$fields]);

	// Make sure we have this!
	loadProfileFields(true);

	// First check for any linked sets.
	foreach ($profile_fields as $key => $field)
		if (isset($field['link_with']) && in_array($field['link_with'], $fields))
			$fields[] = $key;

	$i = 0;
	$last_type = '';
	foreach ($fields as $key => $field)
	{
		if (isset($profile_fields[$field]))
		{
			// Shortcut.
			$cur_field = &$profile_fields[$field];

			// Does it have a preload and does that preload succeed?
			if (isset($cur_field['preload']) && !$cur_field['preload']())
				continue;

			// If this is anything but complex we need to do more cleaning!
			if ($cur_field['type'] != 'callback' && $cur_field['type'] != 'hidden')
			{
				if (!isset($cur_field['label']))
					$cur_field['label'] = isset($txt[$field]) ? $txt[$field] : $field;

				// Everything has a value!
				if (!isset($cur_field['value']))
					$cur_field['value'] = isset($cur_profile[$field]) ? $cur_profile[$field] : '';

				// Any input attributes?
				$cur_field['input_attr'] = !empty($cur_field['input_attr']) ? implode(',', $cur_field['input_attr']) : '';
			}

			// Was there an error with this field on posting?
			if (isset($context['profile_errors'][$field]))
				$cur_field['is_error'] = true;

			// Any javascript stuff?
			if (!empty($cur_field['js_submit']))
				$context['profile_onsubmit_javascript'] .= $cur_field['js_submit'];
			if (!empty($cur_field['js']))
				$context['profile_javascript'] .= $cur_field['js'];

			// Any template stuff?
			if (!empty($cur_field['prehtml']))
				$context['profile_prehtml'] .= $cur_field['prehtml'];
			if (!empty($cur_field['posthtml']))
				$context['profile_posthtml'] .= $cur_field['posthtml'];

			// Finally put it into context?
			if ($cur_field['type'] != 'hidden')
			{
				$last_type = $cur_field['type'];
				$context['profile_fields'][$field] = &$profile_fields[$field];
			}
		}
		// Bodge in a line break - without doing two in a row ;)
		elseif ($field == 'hr' && $last_type != 'hr' && $last_type != '')
		{
			$last_type = 'hr';
			$context['profile_fields'][$i++]['type'] = 'hr';
		}
	}

	// Make sure all of the selects really come with arrays of options, rather than callbacks.
	foreach ($context['profile_fields'] as $pf => $field)
	{
		if (empty($field['type']) || $field['type'] != 'select')
			continue;

		if (!empty($field['options']) && !is_array($field['options']))
		{
			$context['profile_fields'][$pf]['options'] = $field['options']();
		}
	}

	// Some spicy JS. @TODO rewrite with jQuery sometime.
	addInlineJavaScript('
	if (document.forms.creator) {
		var form_handle = document.forms.creator;
		createEventListener(form_handle);
		' . (!empty($context['require_password']) ? '
		form_handle.addEventListener(\'submit\', function(event)
		{
			if (this.oldpasswrd.value == "")
			{
				event.preventDefault();
				alert(' . (JavaScriptEscape($txt['required_security_reasons'])) . ');
				return false;
			}
		}, false);' : '') . '
	}', true);

	// Any onsubmit javascript?
	if (!empty($context['profile_onsubmit_javascript']))
		addInlineJavaScript($context['profile_onsubmit_javascript'], true);

	// Any totally custom stuff?
	if (!empty($context['profile_javascript']))
		addInlineJavaScript($context['profile_javascript'], true);

	// Free up some memory.
	unset($profile_fields);

	// Do some processing to make the submission URL.
	if (!empty($context['menu_item_selected']) && !empty($context['id_member']))
	{
		$context['profile_submit_url'] = !empty($context['profile_custom_submit_url']) ? $context['profile_custom_submit_url'] : $scripturl . '?action=profile;area=' . $context['menu_item_selected'] . ';u=' . $context['id_member'];
		$context['profile_submit_url'] = !empty($context['require_password']) && !empty($modSettings['force_ssl']) && $modSettings['force_ssl'] < 2 ? strtr($context['profile_submit_url'], ['http://' => 'https://']) : $context['profile_submit_url'];
	}
}

/**
 * Save the profile changes.
 */
function saveProfileFields()
{
	global $profile_fields, $profile_vars, $context, $old_profile, $post_errors, $cur_profile;

	// Load them up.
	loadProfileFields();

	// This makes things easier...
	$old_profile = $cur_profile;

	// This allows variables to call activities when they save - by default just to reload their settings
	$context['profile_execute_on_save'] = [];
	if ($context['user']['is_owner'])
		$context['profile_execute_on_save']['reload_user'] = 'profileReloadUser';

	// Assume we log nothing.
	$context['log_changes'] = [];

	// Cycle through the profile fields working out what to do!
	foreach ($profile_fields as $key => $field)
	{
		if (!isset($_POST[$key]) || !empty($field['is_dummy']) || (isset($_POST['preview_signature']) && $key == 'signature'))
			continue;

		// What gets updated?
		$db_key = isset($field['save_key']) ? $field['save_key'] : $key;

		// Right - we have something that is enabled, we can act upon and has a value posted to it. Does it have a validation function?
		if (isset($field['input_validate']))
		{
			$is_valid = $field['input_validate']($_POST[$key]);
			// An error occurred - set it as such!
			if ($is_valid !== true)
			{
				// Is this an actual error?
				if ($is_valid !== false)
				{
					$post_errors[$key] = $is_valid;
					$profile_fields[$key]['is_error'] = $is_valid;
				}
				// Retain the old value.
				$cur_profile[$key] = $_POST[$key];
				continue;
			}
		}

		// Are we doing a cast?
		$field['cast_type'] = empty($field['cast_type']) ? $field['type'] : $field['cast_type'];

		// Finally, clean up certain types.
		if ($field['cast_type'] == 'int')
			$_POST[$key] = (int) $_POST[$key];
		elseif ($field['cast_type'] == 'float')
			$_POST[$key] = (float) $_POST[$key];
		elseif ($field['cast_type'] == 'check')
			$_POST[$key] = !empty($_POST[$key]) ? 1 : 0;

		// If we got here we're doing OK.
		if ($field['type'] != 'hidden' && (!isset($old_profile[$key]) || $_POST[$key] != $old_profile[$key]))
		{
			// Set the save variable.
			$profile_vars[$db_key] = $_POST[$key];
			// And update the user profile.
			$cur_profile[$key] = $_POST[$key];

			// Are we logging it?
			if (!empty($field['log_change']) && isset($old_profile[$key]))
				$context['log_changes'][$key] = [
					'previous' => $old_profile[$key],
					'new' => $_POST[$key],
				];
		}

		// Logging group changes are a bit different...
		if ($key == 'id_group' && $field['log_change'])
		{
			profileLoadGroups();

			// Any changes to primary group?
			if ($_POST['id_group'] != $old_profile['id_group'])
			{
				$context['log_changes']['id_group'] = [
					'previous' => !empty($old_profile[$key]) && isset($context['member_groups'][$old_profile[$key]]) ? $context['member_groups'][$old_profile[$key]]['name'] : '',
					'new' => !empty($_POST[$key]) && isset($context['member_groups'][$_POST[$key]]) ? $context['member_groups'][$_POST[$key]]['name'] : '',
				];
			}

			// Prepare additional groups for comparison.
			$additional_groups = [
				'previous' => !empty($old_profile['additional_groups']) ? explode(',', $old_profile['additional_groups']) : [],
				'new' => !empty($_POST['additional_groups']) ? array_diff($_POST['additional_groups'], [0]) : [],
			];

			sort($additional_groups['previous']);
			sort($additional_groups['new']);

			// What about additional groups?
			if ($additional_groups['previous'] != $additional_groups['new'])
			{
				foreach ($additional_groups as $type => $groups)
				{
					foreach ($groups as $id => $group)
					{
						if (isset($context['member_groups'][$group]))
							$additional_groups[$type][$id] = $context['member_groups'][$group]['name'];
						else
							unset($additional_groups[$type][$id]);
					}
					$additional_groups[$type] = implode(', ', $additional_groups[$type]);
				}

				$context['log_changes']['additional_groups'] = $additional_groups;
			}
		}
	}

	// @todo Temporary
	if ($context['user']['is_owner'])
		$changeOther = allowedTo(['profile_extra_any', 'profile_extra_own']);
	else
		$changeOther = allowedTo('profile_extra_any');
	if ($changeOther && empty($post_errors))
	{
		makeThemeChanges($context['id_member'], isset($_POST['id_theme']) ? (int) $_POST['id_theme'] : $old_profile['id_theme']);
		if (!empty($_REQUEST['sa']))
		{
			$custom_fields_errors = makeCustomFieldChanges($context['id_member'], $_REQUEST['sa'], false, true);

			if (!empty($custom_fields_errors))
				$post_errors = array_merge($post_errors, $custom_fields_errors);
		}
	}

	// Free memory!
	unset($profile_fields);
}

/**
 * Save the profile changes
 *
 * @param array &$profile_vars The items to save
 * @param array &$post_errors An array of information about any errors that occurred
 * @param int $memID The ID of the member whose profile we're saving
 */
function saveProfileChanges(&$profile_vars, &$post_errors, $memID)
{
	global $user_profile, $context;

	// These make life easier....
	$old_profile = &$user_profile[$memID];

	// Permissions...
	if ($context['user']['is_owner'])
	{
		$changeOther = allowedTo(['profile_extra_any', 'profile_extra_own', 'profile_website_any', 'profile_website_own', 'profile_signature_any', 'profile_signature_own']);
	}
	else
		$changeOther = allowedTo(['profile_extra_any', 'profile_website_any', 'profile_signature_any']);

	// Arrays of all the changes - makes things easier.
	$profile_bools = [];
	$profile_ints = [];
	$profile_floats = [];
	$profile_strings = [
		'buddy_list',
		'ignore_boards',
	];

	if (isset($_POST['sa']) && $_POST['sa'] == 'ignoreboards' && empty($_POST['ignore_brd']))
		$_POST['ignore_brd'] = [];

	unset($_POST['ignore_boards']); // Whatever it is set to is a dirty filthy thing.  Kinda like our minds.
	if (isset($_POST['ignore_brd']))
	{
		if (!is_array($_POST['ignore_brd']))
			$_POST['ignore_brd'] = [$_POST['ignore_brd']];

		foreach ($_POST['ignore_brd'] as $k => $d)
		{
			$d = (int) $d;
			if ($d != 0)
				$_POST['ignore_brd'][$k] = $d;
			else
				unset($_POST['ignore_brd'][$k]);
		}
		$_POST['ignore_boards'] = implode(',', $_POST['ignore_brd']);
		unset($_POST['ignore_brd']);

	}

	// Here's where we sort out all the 'other' values...
	if ($changeOther)
	{
		makeThemeChanges($memID, isset($_POST['id_theme']) ? (int) $_POST['id_theme'] : $old_profile['id_theme']);
		//makeAvatarChanges($memID, $post_errors);

		if (!empty($_REQUEST['sa']))
			makeCustomFieldChanges($memID, $_REQUEST['sa'], false);

		foreach ($profile_bools as $var)
			if (isset($_POST[$var]))
				$profile_vars[$var] = empty($_POST[$var]) ? '0' : '1';
		foreach ($profile_ints as $var)
			if (isset($_POST[$var]))
				$profile_vars[$var] = $_POST[$var] != '' ? (int) $_POST[$var] : '';
		foreach ($profile_floats as $var)
			if (isset($_POST[$var]))
				$profile_vars[$var] = (float) $_POST[$var];
		foreach ($profile_strings as $var)
			if (isset($_POST[$var]))
				$profile_vars[$var] = $_POST[$var];
	}
}

/**
 * Make any theme changes that are sent with the profile.
 *
 * @param int $memID The ID of the user
 * @param int $id_theme The ID of the theme
 */
function makeThemeChanges($memID, $id_theme)
{
	global $context;

	if (!empty($context['password_auth_failed']))
	{
		return;
	}

	if (isset($_POST['options']) && is_array($_POST['options']))
	{
		// Load up the system theme options to validate what we're checking against.
		$container = Container::instance();
		$site_settings = $container->get('sitesettings');
		$prefs_manager = $container->instantiate('StoryBB\\User\\PreferenceManager');
		$defaults = $prefs_manager->get_default_preferences();

		$newprefs = [];

		foreach ($defaults as $setting)
		{
			// If it's not an array, it's not a setting, move along.
			if (!is_array($setting))
			{
				continue;
			}

			// If the setting is disabled in configuration, skip it.
			if (isset($setting['disableOn']) && $site_settings->{$setting['disableOn']})
			{
				continue;
			}

			if (!isset($_POST['options'][$setting['id']]))
			{
				continue;
			}

			if (!isset($setting['type']) || $setting['type'] == 'bool')
				$type = 'checkbox';
			elseif ($setting['type'] == 'int' || $setting['type'] == 'integer')
				$type = 'number';
			elseif ($setting['type'] == 'string')
				$type = 'text';

			if (isset($setting['options']))
				$type = 'list';

			switch ($type)
			{
				case 'checkbox':
					$newprefs[$setting['id']] = $_POST['options'][$setting['id']] ? 1 : 0;
					break;

				case 'number':
					$newprefs[$setting['id']] = (int) $_POST['options'][$setting['id']];
					if (isset($setting['max']) && $newprefs[$setting['id']] > $setting['max'])
					{
						$newprefs[$setting['id']] = $setting['max'];
					}
					if (isset($setting['min']) && $newprefs[$setting['id']] < $setting['min'])
					{
						$newprefs[$setting['id']] = $setting['min'];
					}
					break;

				case 'text':
					$newprefs[$setting['id']] = StringLibrary::escape($_POST['options'][$setting['id']]);
					break;

				case 'list':
					$newprefs[$setting['id']] = $_POST['options'][$setting['id']];
					if (!isset($setting['options'][$newprefs[$setting['id']]]))
					{
						$newprefs[$setting['id']] = array_keys($setting['options'])[0];
					}
					break;
			}
		}

		$prefs_manager->save_preferences($memID, $newprefs);
	}
}

/**
 * Make any notification changes that need to be made.
 *
 * @param int $memID The ID of the member
 */
function makeNotificationChanges($memID)
{
	global $smcFunc, $sourcedir;

	require_once($sourcedir . '/Subs-Notify.php');

	// Update the boards they are being notified on.
	if (isset($_POST['edit_notify_boards']) && !empty($_POST['notify_boards']))
	{
		// Make sure only integers are deleted.
		foreach ($_POST['notify_boards'] as $index => $id)
			$_POST['notify_boards'][$index] = (int) $id;

		// id_board = 0 is reserved for topic notifications.
		$_POST['notify_boards'] = array_diff($_POST['notify_boards'], [0]);

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}log_notify
			WHERE id_board IN ({array_int:board_list})
				AND id_member = {int:selected_member}',
			[
				'board_list' => $_POST['notify_boards'],
				'selected_member' => $memID,
			]
		);
	}

	// We are editing topic notifications......
	elseif (isset($_POST['edit_notify_topics']) && !empty($_POST['notify_topics']))
	{
		foreach ($_POST['notify_topics'] as $index => $id)
			$_POST['notify_topics'][$index] = (int) $id;

		// Make sure there are no zeros left.
		$_POST['notify_topics'] = array_diff($_POST['notify_topics'], [0]);

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}log_notify
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member = {int:selected_member}',
			[
				'topic_list' => $_POST['notify_topics'],
				'selected_member' => $memID,
			]
		);
		foreach ($_POST['notify_topics'] as $topic)
			setNotifyPrefs($memID, ['topic_notify_' . $topic => 0]);
	}

	// We are removing topic preferences
	elseif (isset($_POST['remove_notify_topics']) && !empty($_POST['notify_topics']))
	{
		$prefs = [];
		foreach ($_POST['notify_topics'] as $topic)
			$prefs[] = 'topic_notify_' . $topic;
		deleteNotifyPrefs($memID, $prefs);
	}

	// We are removing board preferences
	elseif (isset($_POST['remove_notify_board']) && !empty($_POST['notify_boards']))
	{
		$prefs = [];
		foreach ($_POST['notify_boards'] as $board)
			$prefs[] = 'board_notify_' . $board;
		deleteNotifyPrefs($memID, $prefs);
	}
}

/**
 * Save any changes to the custom profile fields
 *
 * @param int $memID The ID of the member
 * @param string $area The area of the profile these fields are in
 * @param bool $sanitize = true Whether or not to sanitize the data
 * @param bool $returnErrors Whether or not to return any error information
 * @return void|array Returns nothing or returns an array of error info if $returnErrors is true
 */
function makeCustomFieldChanges($memID, $area, $sanitize = true, $returnErrors = false)
{
	global $context, $smcFunc, $user_profile, $user_info, $modSettings;
	global $sourcedir;

	$errors = [];

	if ($sanitize && isset($_POST['customfield']))
		$_POST['customfield'] = htmlspecialchars__recursive($_POST['customfield']);

	$where = $area == 'register' ? 'show_reg != 0' : 'show_profile = {string:area}';

	// Load the fields we are saving too - make sure we save valid data (etc).
	$request = $smcFunc['db']->query('', '
		SELECT col_name, field_name, field_desc, field_type, field_length, field_options, default_value, show_reg, mask, private
		FROM {db_prefix}custom_fields
		WHERE ' . $where . '
			AND active = {int:is_active}',
		[
			'is_active' => 1,
			'area' => $area,
		]
	);
	$changes = [];
	$log_changes = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		/* This means don't save if:
			- The user is NOT an admin.
			- The data is not freely viewable and editable by users.
			- The data is not invisible to users but editable by the owner (or if it is the user is not the owner)
			- The area isn't registration, and if it is that the field is not supposed to be shown there.
		*/
		if ($row['private'] != 0 && !allowedTo('admin_forum') && ($memID != $user_info['id'] || $row['private'] != 2) && ($area != 'register' || $row['show_reg'] == 0))
			continue;

		// Validate the user data.
		if ($row['field_type'] == 'check')
			$value = isset($_POST['customfield'][$row['col_name']]) ? 1 : 0;
		elseif ($row['field_type'] == 'select' || $row['field_type'] == 'radio')
		{
			$value = $row['default_value'];
			foreach (explode(',', $row['field_options']) as $k => $v)
				if (isset($_POST['customfield'][$row['col_name']]) && $_POST['customfield'][$row['col_name']] == $k)
					$value = $v;
		}
		// Otherwise some form of text!
		else
		{
			$value = isset($_POST['customfield'][$row['col_name']]) ? $_POST['customfield'][$row['col_name']] : '';
			if ($row['field_length'])
				$value = StringLibrary::substr($value, 0, $row['field_length']);

			// Any masks?
			if ($row['field_type'] == 'text' && !empty($row['mask']) && $row['mask'] != 'none')
			{
				$value = StringLibrary::htmltrim($value);
				$valueReference = un_htmlspecialchars($value);

				// Try and avoid some checks. '0' could be a valid non-empty value.
				if (empty($value) && !is_numeric($value))
					$value = '';

				if ($row['mask'] == 'nohtml' && ($valueReference != strip_tags($valueReference) || $value != filter_var($value, FILTER_SANITIZE_STRING) || preg_match('/<(.+?)[\s]*\/?[\s]*>/si', $valueReference)))
				{
					if ($returnErrors)
						$errors[] = 'custom_field_nohtml_fail';

					else
						$value = '';
				}
				elseif ($row['mask'] == 'email' && (!filter_var($value, FILTER_VALIDATE_EMAIL) || strlen($value) > 255))

				{
					if ($returnErrors)
						$errors[] = 'custom_field_mail_fail';

					else
						$value = '';
				}
				elseif ($row['mask'] == 'number')
				{
					$value = (int) $value;
				}
				elseif (substr($row['mask'], 0, 5) == 'regex' && trim($value) != '' && preg_match(substr($row['mask'], 5), $value) === 0)
				{
					if ($returnErrors)
						$errors[] = 'custom_field_regex_fail';

					else
						$value = '';
				}

				unset($valueReference);
			}
		}

		// Did it change?
		if (!isset($user_profile[$memID]['options'][$row['col_name']]) || $user_profile[$memID]['options'][$row['col_name']] !== $value)
		{
			$log_changes[] = [
				'action' => 'customfield_' . $row['col_name'],
				'log_type' => 'user',
				'extra' => [
					'previous' => !empty($user_profile[$memID]['options'][$row['col_name']]) ? $user_profile[$memID]['options'][$row['col_name']] : '',
					'new' => $value,
					'applicator' => $user_info['id'],
					'member_affected' => $memID,
				],
			];
			$changes[] = [1, $row['col_name'], $value, $memID];
			$user_profile[$memID]['options'][$row['col_name']] = $value;
		}
	}
	$smcFunc['db']->free_result($request);

	$hook_errors = call_integration_hook('integrate_save_custom_profile_fields', [&$changes, &$log_changes, &$errors, $returnErrors, $memID, $area, $sanitize]);

	if (!empty($hook_errors) && is_array($hook_errors))
		$errors = array_merge($errors, $hook_errors);

	// Make those changes!
	if (!empty($changes) && empty($context['password_auth_failed']) && empty($errors))
	{
		$smcFunc['db']->insert('replace',
			'{db_prefix}themes',
			['id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534', 'id_member' => 'int'],
			$changes,
			['id_theme', 'variable', 'id_member']
		);
		if (!empty($log_changes) && !empty($modSettings['modlog_enabled']))
		{
			require_once($sourcedir . '/Logging.php');
			logActions($log_changes);
		}
	}

	if ($returnErrors)
		return $errors;
}

/**
 * Show all the users buddies, as well as a add/delete interface.
 *
 * @param int $memID The ID of the member
 */
function editBuddyIgnoreLists($memID)
{
	global $context, $txt, $modSettings;

	// Do a quick check to ensure people aren't getting here illegally!
	if (!$context['user']['is_owner'] || empty($modSettings['enable_buddylist']))
		fatal_lang_error('no_access', false);

	// Can we email the user direct?
	$context['can_moderate_forum'] = allowedTo('moderate_forum');
	$context['can_send_email'] = allowedTo('moderate_forum');

	$subActions = [
		'buddies' => ['editBuddies', $txt['editBuddies']],
		'ignore' => ['editIgnoreList', $txt['editIgnoreList']],
	];

	$context['list_area'] = isset($_GET['sa']) && isset($subActions[$_GET['sa']]) ? $_GET['sa'] : 'buddies';

	// Create the tabs for the template.
	$context[$context['profile_menu_name']]['tab_data'] = [
		'title' => $txt['editBuddyIgnoreLists'],
		'description' => $txt['buddy_ignore_desc'],
		'tabs' => [
			'buddies' => [],
			'ignore' => [],
		],
	];

	// Pass on to the actual function.
	$context['sub_template'] = $subActions[$context['list_area']][0];
	$call = call_helper($subActions[$context['list_area']][0], true);

	if (!empty($call))
		call_user_func($call, $memID);
}

/**
 * Show all the users buddies, as well as a add/delete interface.
 *
 * @param int $memID The ID of the member
 */
function editBuddies($memID)
{
	global $txt, $scripturl, $settings;
	global $context, $user_profile, $memberContext, $smcFunc;

	$context['show_buddy_email_address'] = allowedTo('moderate_forum');
	$context['sub_template'] = 'profile_buddy';

	// For making changes!
	$buddiesArray = explode(',', $user_profile[$memID]['buddy_list']);
	foreach ($buddiesArray as $k => $dummy)
		if ($dummy == '')
			unset($buddiesArray[$k]);

	$saved = false;

	// Removing a buddy?
	if (isset($_GET['remove']))
	{
		checkSession('get');

		call_integration_hook('integrate_remove_buddy', [$memID]);

		// Heh, I'm lazy, do it the easy way...
		foreach ($buddiesArray as $key => $buddy)
			if ($buddy == (int) $_GET['remove'])
			{
				unset($buddiesArray[$key]);
				$saved = true;
			}

		// Make the changes.
		$user_profile[$memID]['buddy_list'] = implode(',', $buddiesArray);
		updateMemberData($memID, ['buddy_list' => $user_profile[$memID]['buddy_list']]);

		if ($saved)
		{
			session_flash('success', sprintf($context['user']['is_owner'] ? $txt['profile_updated_own'] : $txt['profile_updated_else'], $context['member']['name']));
		}
		else
		{
			session_flash('error', $txt['could_not_remove_person']);
		}

		// Redirect off the page because we don't like all this ugly query stuff to stick in the history.
		redirectexit('action=profile;area=lists;sa=buddies;u=' . $memID);
	}
	elseif (isset($_POST['new_buddy']))
	{
		checkSession();
		// Prepare the string for extraction...
		$new_buddy = !empty($_POST['new_buddy']) ? (array) $_POST['new_buddy'] : [];
		foreach ($new_buddy as $k => $v)
		{
			$v = (int) $v;
			if ($v <= 0)
				unset ($new_buddy[$k]);
			else
				$new_buddy[$k] = $v;
		}
		$new_buddy = array_diff($new_buddy, $buddiesArray);

		call_integration_hook('integrate_add_buddies', [$memID, &$new_buddy]);

		if (!empty($new_buddy))
		{
			$saved = true;
			$buddiesArray = array_merge($buddiesArray, $new_buddy);

			// Now update the current users buddy list.
			$user_profile[$memID]['buddy_list'] = implode(',', $buddiesArray);
			updateMemberData($memID, ['buddy_list' => $user_profile[$memID]['buddy_list']]);
		}

		if ($saved)
		{
			session_flash('success', sprintf($context['user']['is_owner'] ? $txt['profile_updated_own'] : $txt['profile_updated_else'], $context['member']['name']));
		}
		else
		{
			session_flash('error', $txt['could_not_add_person']);
		}

		// Back to the buddy list!
		redirectexit('action=profile;area=lists;sa=buddies;u=' . $memID);
	}

	// Get all the users "buddies"...
	$buddies = [];

	// Gotta load the custom profile fields names.
	$request = $smcFunc['db']->query('', '
		SELECT col_name, field_name, field_desc, field_type, bbc, enclose
		FROM {db_prefix}custom_fields
		WHERE active = {int:active}
			AND private < {int:private_level}',
		[
			'active' => 1,
			'private_level' => 2,
		]
	);

	$context['custom_pf'] = [];
	$disabled_fields = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
		if (!isset($disabled_fields[$row['col_name']]))
			$context['custom_pf'][$row['col_name']] = [
				'label' => $row['field_name'],
				'type' => $row['field_type'],
				'bbc' => !empty($row['bbc']),
				'enclose' => $row['enclose'],
			];

	$smcFunc['db']->free_result($request);

	if (!empty($buddiesArray))
	{
		$result = $smcFunc['db']->query('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:buddy_list})
			ORDER BY real_name
			LIMIT {int:buddy_list_count}',
			[
				'buddy_list' => $buddiesArray,
				'buddy_list_count' => substr_count($user_profile[$memID]['buddy_list'], ',') + 1,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($result))
			$buddies[] = $row['id_member'];
		$smcFunc['db']->free_result($result);
	}

	$context['buddy_count'] = count($buddies);

	// Load all the members up.
	loadMemberData($buddies, false, 'profile');

	// Setup the context for each buddy.
	$context['buddies'] = [];
	foreach ($buddies as $buddy)
	{
		loadMemberContext($buddy);
		$context['buddies'][$buddy] = $memberContext[$buddy];

		// Make sure to load the appropriate fields for each user
		if (!empty($context['custom_pf']))
		{
			foreach ($context['custom_pf'] as $key => $column)
			{
				// Don't show anything if there isn't anything to show.
				if (!isset($context['buddies'][$buddy]['options'][$key]))
				{
					$context['buddies'][$buddy]['options'][$key] = '';
					continue;
				}

				if ($column['bbc'] && !empty($context['buddies'][$buddy]['options'][$key]))
					$context['buddies'][$buddy]['options'][$key] = strip_tags(Parser::parse_bbc($context['buddies'][$buddy]['options'][$key]));

				elseif ($column['type'] == 'check')
					$context['buddies'][$buddy]['options'][$key] = $context['buddies'][$buddy]['options'][$key] == 0 ? $txt['no'] : $txt['yes'];

				// Enclosing the user input within some other text?
				if (!empty($column['enclose']) && !empty($context['buddies'][$buddy]['options'][$key]))
					$context['buddies'][$buddy]['options'][$key] = strtr($column['enclose'], [
						'{SCRIPTURL}' => $scripturl,
						'{IMAGES_URL}' => $settings['images_url'],
						'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
						'{INPUT}' => $context['buddies'][$buddy]['options'][$key],
					]);
			}
		}
	}

	$context['columns_colspan'] = count($context['custom_pf']) + 3 + ($context['show_buddy_email_address'] ? 1 : 0);

	Autocomplete::init('member', '#new_buddy');

	call_integration_hook('integrate_view_buddies', [$memID]);
}

/**
 * Allows the user to view their ignore list, as well as the option to manage members on it.
 *
 * @param int $memID The ID of the member
 */
function editIgnoreList($memID)
{
	global $txt;
	global $context, $user_profile, $memberContext, $smcFunc;

	$context['show_ignore_email_address'] = allowedTo('moderate_forum');

	// For making changes!
	$ignoreArray = explode(',', $user_profile[$memID]['pm_ignore_list']);
	foreach ($ignoreArray as $k => $dummy)
		if ($dummy == '')
			unset($ignoreArray[$k]);

	$saved = false;

	// Removing a member from the ignore list?
	if (isset($_GET['remove']))
	{
		checkSession('get');

		// Heh, I'm lazy, do it the easy way...
		foreach ($ignoreArray as $key => $id_remove)
			if ($id_remove == (int) $_GET['remove'])
			{
				unset($ignoreArray[$key]);
				$saved = true;
			}

		// Make the changes.
		$user_profile[$memID]['pm_ignore_list'] = implode(',', $ignoreArray);
		updateMemberData($memID, ['pm_ignore_list' => $user_profile[$memID]['pm_ignore_list']]);

		if ($saved)
		{
			session_flash('success', sprintf($context['user']['is_owner'] ? $txt['profile_updated_own'] : $txt['profile_updated_else'], $context['member']['name']));
		}
		else
		{
			session_flash('error', $txt['could_not_remove_person']);
		}

		// Redirect off the page because we don't like all this ugly query stuff to stick in the history.
		redirectexit('action=profile;area=lists;sa=ignore;u=' . $memID);
	}
	elseif (isset($_POST['new_ignore']))
	{
		checkSession();
		// Prepare the string for extraction...
		$new_ignore = !empty($_POST['new_ignore']) ? (array) $_POST['new_ignore'] : [];
		foreach ($new_ignore as $k => $v)
		{
			$v = (int) $v;
			if ($v <= 0)
				unset ($new_ignore[$k]);
			else
				$new_ignore[$k] = $v;
		}
		$new_ignore = array_diff($new_ignore, $ignoreArray);

		call_integration_hook('integrate_add_ignore', [$memID, &$new_ignore]);

		if (!empty($new_ignore))
		{
			$saved = true;
			$ignoreArray = array_merge($ignoreArray, $new_ignore);

			// Now update the current users buddy list.
			$user_profile[$memID]['pm_ignore_list'] = implode(',', $ignoreArray);
			updateMemberData($memID, ['pm_ignore_list' => $user_profile[$memID]['pm_ignore_list']]);
		}

		if ($saved)
		{
			session_flash('success', sprintf($context['user']['is_owner'] ? $txt['profile_updated_own'] : $txt['profile_updated_else'], $context['member']['name']));
		}
		else
		{
			session_flash('error', $txt['could_not_add_person']);
		}

		// Back to the list of pityful people!
		redirectexit('action=profile;area=lists;sa=ignore;u=' . $memID);
	}

	// Initialise the list of members we're ignoring.
	$ignored = [];

	if (!empty($ignoreArray))
	{
		$result = $smcFunc['db']->query('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:ignore_list})
			ORDER BY real_name
			LIMIT {int:ignore_list_count}',
			[
				'ignore_list' => $ignoreArray,
				'ignore_list_count' => substr_count($user_profile[$memID]['pm_ignore_list'], ',') + 1,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($result))
			$ignored[] = $row['id_member'];
		$smcFunc['db']->free_result($result);
	}

	$context['ignore_count'] = count($ignored);

	// Load all the members up.
	loadMemberData($ignored, false, 'profile');

	// Setup the context for each buddy.
	$context['ignore_list'] = [];
	foreach ($ignored as $ignore_member)
	{
		loadMemberContext($ignore_member);
		$context['ignore_list'][$ignore_member] = $memberContext[$ignore_member];
	}

	Autocomplete::init('member', '#new_ignore');

	$context['sub_template'] = 'profile_ignore';
}

/**
 * Handles the account section of the profile
 *
 * @param int $memID The ID of the member
 */
function account($memID)
{
	global $context, $txt;

	loadThemeOptions($memID);
	if (allowedTo(['profile_identity_own', 'profile_identity_any', 'profile_password_own', 'profile_password_any']))
		loadCustomFields($memID, 'account');

	$context['sub_template'] = 'profile_options';
	$context['page_desc'] = $txt['account_info'];

	setupProfileContext(
		[
			'member_name', 'real_name', 'date_registered', 'posts', 'lngfile', 'hr',
			'id_group', 'hr',
			'email_address', 'show_online', 'hr',
			'immersive_mode', 'hr',
			'passwrd1', 'passwrd2', 'hr',
			'secret_question', 'secret_answer',
		]
	);
}

/**
 * Handles the main "Forum Profile" section of the profile
 *
 * @param int $memID The ID of the member
 */
function forumProfile($memID)
{
	global $context, $txt;

	loadThemeOptions($memID);
	if (allowedTo(['profile_forum_own', 'profile_forum_any']))
		loadCustomFields($memID, 'forumprofile');

	$context['sub_template'] = 'profile_options';
	$context['page_desc'] = str_replace('{forum_name}', $context['forum_name_html_safe'], $txt['forumProfile_info']);
	$context['show_preview_button'] = true;

	setupProfileContext(
		[
			'avatar_choice', 'hr',
			'birthday_date', 'birthday_visibility', 'hr',
			'website_title', 'website_url',
		]
	);
}

/**
 * Handles the "Look and Layout" section of the profile
 *
 * @param int $memID The ID of the member
 */
function theme($memID)
{
	global $txt, $context, $modSettings;

	$container = Container::instance();
	$prefs_manager = $container->instantiate('StoryBB\\User\\PreferenceManager');
	$context['theme_options'] = $prefs_manager->get_default_preferences();

	loadThemeOptions($memID);

	$context['sub_template'] = 'profile_options';
	$context['page_desc'] = $txt['theme_info'];

	// Do some fix-ups to keep the template a bit simpler.
	foreach ($context['theme_options'] as $id => $setting)
	{
		if (!is_array($setting))
		{
			$context['theme_options'][$id] = $txt[$setting];
			continue;
		}

		$context['theme_options'][$id]['label'] = $txt[$setting['label']];

		// If it's going to be disabled through a modSettings entry, do that first.
		if (!empty($setting['disableOn']) && !empty($modSettings[$setting['disableOn']])) {
			unset ($context['theme_options'][$id]);
			continue;
		}

		// Make sure there's a type given, or create the more canonical names we want to use here.
		if (!isset($setting['type']) || $setting['type'] == 'bool')
			$context['theme_options'][$id]['type'] = 'checkbox';
		elseif ($setting['type'] == 'int' || $setting['type'] == 'integer')
			$context['theme_options'][$id]['type'] = 'number';
		elseif ($setting['type'] == 'string')
			$context['theme_options'][$id]['type'] = 'text';

		if (isset($setting['options']))
		{
			$context['theme_options'][$id]['type'] = 'list';
			foreach ($setting['options'] as $opt_key => $opt_val)
			{
				if (is_string($opt_val))
				{
					$context['theme_options'][$id]['options'][$opt_key] = isset($txt[$opt_val]) ? $txt[$opt_val] : $opt_val;
				}
			}
		}

		// Make the value more readily available to the template.
		$context['theme_options'][$id]['user_value'] = '';
		if (isset($context['member']['options'][$setting['id']]))
			$context['theme_options'][$id]['user_value'] = $context['member']['options'][$setting['id']];
	}

	setupProfileContext(
		[
			'time_format', 'timezone',
			'theme_settings',
		]
	);
}

/**
 * Display the notifications and settings for changes.
 *
 * @param int $memID The ID of the member
 */
function notification($memID)
{
	global $txt, $context;

	// Going to want this for consistency.
	loadCSSFile('admin.css', [], 'sbb_admin');

	// This is just a bootstrap for everything else.
	$sa = [
		'alerts' => 'alert_configuration',
		'markread' => 'alert_markread',
		'topics' => 'alert_notifications_topics',
		'boards' => 'alert_notifications_boards',
	];

	$subAction = !empty($_GET['sa']) && isset($sa[$_GET['sa']]) ? $_GET['sa'] : 'alerts';

	$context['sub_template'] = $sa[$subAction];
	$context[$context['profile_menu_name']]['tab_data'] = [
		'title' => $txt['notification'],
		'help' => '',
		'description' => $txt['notification_info'],
	];
	$sa[$subAction]($memID);
}

/**
 * Handles configuration of alert preferences
 *
 * @param int $memID The ID of the member
 */
function alert_configuration($memID)
{
	global $txt, $context, $modSettings, $smcFunc, $sourcedir;

	if (!isset($context['token_check']))
		$context['token_check'] = 'profile-nt' . $memID;

	is_not_guest();
	if (!$context['user']['is_owner'])
		isAllowedTo('profile_extra_any');

	// Set the post action if we're coming from the profile...
	if (!isset($context['action']))
		$context['action'] = 'action=profile;area=notification;sa=alerts;u=' . $memID;

	// What options are set
	loadThemeOptions($memID);
	loadJavaScriptFile('alertSettings.js', [], 'sbb_alertSettings');

	// Now load all the values for this user.
	require_once($sourcedir . '/Subs-Notify.php');
	$prefs = getNotifyPrefs($memID, '', $memID != 0);

	$context['alert_prefs'] = !empty($prefs[$memID]) ? $prefs[$memID] : [];

	$context['member'] += [
		'alert_timeout' => isset($context['alert_prefs']['alert_timeout']) ? $context['alert_prefs']['alert_timeout'] : 10,
	];

	// Now for the exciting stuff.
	// We have groups of items, each item has both an alert and an email key as well as an optional help string.
	// Valid values for these keys are 'always', 'yes', 'never'; if using always or never you should add a help string.
	$alert_types = [
		'board' => [
			'topic_notify' => ['alert' => 'yes', 'email' => 'yes'],
			'board_notify' => ['alert' => 'yes', 'email' => 'yes'],
		],
		'msg' => [
			'msg_mention' => ['alert' => 'yes', 'email' => 'yes'],
			'msg_quote' => ['alert' => 'yes', 'email' => 'yes'],
			'msg_like' => ['alert' => 'yes', 'email' => 'never'],
			'unapproved_reply' => ['alert' => 'yes', 'email' => 'yes'],
		],
		'pm' => [
			'pm_new' => ['alert' => 'never', 'email' => 'yes', 'help' => 'alert_pm_new', 'permission' => ['name' => 'pm_read', 'is_board' => false]],
			'pm_reply' => ['alert' => 'never', 'email' => 'yes', 'help' => 'alert_pm_new', 'permission' => ['name' => 'pm_send', 'is_board' => false]],
		],
		'groupr' => [
			'groupr_approved' => ['alert' => 'always', 'email' => 'yes'],
			'groupr_rejected' => ['alert' => 'always', 'email' => 'yes'],
		],
		'moderation' => [
			'unapproved_post' => ['alert' => 'yes', 'email' => 'yes', 'permission' => ['name' => 'approve_posts', 'is_board' => true]],
			'msg_report' => ['alert' => 'yes', 'email' => 'yes', 'permission' => ['name' => 'moderate_board', 'is_board' => true]],
			'msg_report_reply' => ['alert' => 'yes', 'email' => 'yes', 'permission' => ['name' => 'moderate_board', 'is_board' => true]],
			'member_report' => ['alert' => 'yes', 'email' => 'yes', 'permission' => ['name' => 'moderate_forum', 'is_board' => false]],
			'member_report_reply' => ['alert' => 'yes', 'email' => 'yes', 'permission' => ['name' => 'moderate_forum', 'is_board' => false]],
			'approval_notify' => ['alert' => 'never', 'email' => 'yes', 'permission' => ['name' => 'approve_posts', 'is_board' => true]],
		],
		'members' => [
			'member_register' => ['alert' => 'yes', 'email' => 'yes', 'permission' => ['name' => 'moderate_forum', 'is_board' => false]],
			'request_group' => ['alert' => 'yes', 'email' => 'yes'],
			'warn_any' => ['alert' => 'yes', 'email' => 'yes', 'permission' => ['name' => 'issue_warning', 'is_board' => false]],
			'buddy_request'  => ['alert' => 'yes', 'email' => 'never'],
			'birthday'  => ['alert' => 'yes', 'email' => 'yes'],
		],
		'paidsubs' => [
			'paidsubs_expiring' => ['alert' => 'yes', 'email' => 'yes'],
		],
	];
	$group_options = [
		'board' => [
			['check', 'msg_auto_notify', 'position' => 'after'],
			['check', 'msg_receive_body', 'position' => 'after'],
			['select', 'msg_notify_pref', 'position' => 'before', 'opts' => [
				0 => $txt['alert_opt_msg_notify_pref_nothing'],
				1 => $txt['alert_opt_msg_notify_pref_instant'],
				2 => $txt['alert_opt_msg_notify_pref_first'],
				3 => $txt['alert_opt_msg_notify_pref_daily'],
				4 => $txt['alert_opt_msg_notify_pref_weekly'],
			]],
			['select', 'msg_notify_type', 'position' => 'before', 'opts' => [
				1 => $txt['notify_send_type_everything'],
				2 => $txt['notify_send_type_everything_own'],
				3 => $txt['notify_send_type_only_replies'],
				4 => $txt['notify_send_type_nothing'],
			]],
		],
		'pm' => [
			['select', 'pm_notify', 'position' => 'before', 'opts' => [
				1 => $txt['email_notify_all'],
				2 => $txt['email_notify_buddies'],
			]],
		],
	];

	// Disable paid subscriptions at group level if they're disabled
	if (empty($modSettings['paid_enabled']))
		unset($alert_types['paidsubs']);

	// Disable membergroup requests at group level if they're disabled
	if (empty($modSettings['show_group_membership']))
		unset($alert_types['groupr'], $alert_types['members']['request_group']);

	// Disable mentions if they're disabled
	if (empty($modSettings['enable_mentions']))
		unset($alert_types['msg']['msg_mention']);

	// Disable likes if they're disabled
	if (empty($modSettings['enable_likes']))
		unset($alert_types['msg']['msg_like']);

	// Disable buddy requests if they're disabled
	if (empty($modSettings['enable_buddylist']))
		unset($alert_types['members']['buddy_request']);

	// Now, now, we could pass this through global but we should really get into the habit of
	// passing content to hooks, not expecting hooks to splatter everything everywhere.
	call_integration_hook('integrate_alert_types', [&$alert_types, &$group_options]);

	// Now we have to do some permissions testing - but only if we're not loading this from the admin center
	if (!empty($memID))
	{
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
	}

	// And finally, exporting it to be useful later.
	$context['alert_types'] = $alert_types;
	$context['alert_group_options'] = $group_options;

	$context['alert_bits'] = [
		'alert' => 0x01,
		'email' => 0x02,
	];

	if (isset($_POST['notify_submit']))
	{
		checkSession();
		validateToken($context['token_check'], 'post');

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

		makeNotificationChanges($memID);

		session_flash('success', $txt['profile_updated_own']);
	}

	createToken($context['token_check'], 'post');

	// Now we need to set up for the template.
	$context['alert_groups'] = [];
	foreach ($alert_types as $id => $group) {
		$context['alert_groups'][$id] = [
			'title' => $txt['alert_group_' . $id],
			'group_config' => [],
			'options' => [],
		];

		// If this group of settings has its own section-specific settings, expose them to the template.
		if (!empty($group_options[$id]))
		{
			$context['alert_groups'][$id]['group_config'] = $group_options[$id];
			foreach ($group_options[$id] as $pos => $opts) {
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

/**
 * Marks all alerts as read for the specified user
 *
 * @param int $memID The ID of the member
 */
function alert_markread($memID)
{
	global $context, $db_show_debug, $smcFunc;

	// We do not want to output debug information here.
	$db_show_debug = false;

	// We only want to output our little layer here.
	StoryBB\Template::set_layout('raw');
	StoryBB\Template::remove_all_layers();

	if (isset($_GET['alert']))
	{
		$alert = (int) $_GET['alert'];
	}

	loadLanguage('Alerts');

	// Now we're all set up.
	is_not_guest();
	if (!$context['user']['is_owner'])
	{
		fatal_error('no_access');
	}

	checkSession('get');

	// Assuming we're here, mark everything as read and head back.
	// We only spit back the little layer because this should be called AJAXively.
	$smcFunc['db']->query('', '
		UPDATE {db_prefix}user_alerts
		SET is_read = {int:now}
		WHERE id_member = {int:current_member}' . ($alert ? '
			AND id_alert = {int:alert}' : '') . '
			AND is_read = 0',
		[
			'now' => time(),
			'current_member' => $memID,
			'alert' => $alert,
		]
	);

	if ($alert)
	{
		// If we managed a specific ID, we need to process that a little differently.
		if ($smcFunc['db']->affected_rows())
		{
			updateMemberData($memID, ['alerts' => '-']);
		}
	}
	else
	{
		// We marked everything read.
		updateMemberData($memID, ['alerts' => 0]);
	}

	$context['sub_template'] = 'alerts_popup';
	alerts_popup($memID);
}

/**
 * Marks a group of alerts as un/read
 *
 * @param int $memID The user ID.
 * @param array|integer $toMark The ID of a single alert or an array of IDs. The function will convert single integers to arrays for better handling.
 * @param integer $read To mark as read or unread, 1 for read, 0 or any other value different than 1 for unread.
 * @return integer How many alerts remain unread
 */
function alert_mark($memID, $toMark, $read = 0)
{
	return StoryBB\Model\Alert::change_read($memID, $toMark, $read = 0);
}

/**
 * Deletes a single or a group of alerts by ID
 *
 * @param int|array The ID of a single alert to delete or an array containing the IDs of multiple alerts. The function will convert integers into an array for better handling.
 * @param bool|int $memID The user ID. Used to update the user unread alerts count.
 * @return void|int If the $memID param is set, returns the new amount of unread alerts.
 */
function alert_delete($toDelete, $memID = false)
{
	return StoryBB\Model\Alert::delete($toDelete, $memID);
}

/**
 * Counts how many alerts a user has - either unread or all depending on $unread
 *
 * @param int $memID The user ID.
 * @param bool $unread Whether to only count unread alerts.
 * @return int The number of requested alerts
 * @deprecated Call StoryBB\Model\Alert::count_for_member instead
 */
function alert_count($memID, $unread = false)
{
	return Alert::count_for_member($memID, $unread);
}

/**
 * Handles alerts related to topics and posts
 *
 * @param int $memID The ID of the member
 */
function alert_notifications_topics($memID)
{
	global $txt, $scripturl, $context, $modSettings, $sourcedir;

	$context['sub_template'] = 'profile_alerts_watchedtopics';

	// Because of the way this stuff works, we want to do this ourselves.
	if (isset($_POST['edit_notify_topics']) || isset($_POST['remove_notify_topics']))
	{
		checkSession();
		validateToken(str_replace('%u', $memID, 'profile-nt%u'), 'post');

		makeNotificationChanges($memID);
		session_flash('success', $txt['profile_updated_own']);
	}

	// Now set up for the token check.
	$context['token_check'] = str_replace('%u', $memID, 'profile-nt%u');
	createToken($context['token_check'], 'post');

	// Gonna want this for the list.
	require_once($sourcedir . '/Subs-List.php');

	// Do the topic notifications.
	$listOptions = [
		'id' => 'topic_notification_list',
		'width' => '100%',
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'no_items_label' => $txt['notifications_topics_none'] . '<br><br>' . $txt['notifications_topics_howto'],
		'no_items_align' => 'left',
		'base_href' => $scripturl . '?action=profile;u=' . $memID . ';area=notification;sa=topics',
		'default_sort_col' => 'last_post',
		'get_items' => [
			'function' => 'list_getTopicNotifications',
			'params' => [
				$memID,
			],
		],
		'get_count' => [
			'function' => 'list_getTopicNotificationCount',
			'params' => [
				$memID,
			],
		],
		'columns' => [
			'subject' => [
				'header' => [
					'value' => $txt['notifications_topics'],
					'class' => 'lefttext',
				],
				'data' => [
					'function' => function($topic) use ($txt)
					{
						$link = $topic['link'];

						if ($topic['new'])
							$link .= ' <a href="' . $topic['new_href'] . '" class="new_posts">' . $txt['new'] . '</a>';

						$link .= '<br><span class="smalltext"><em>' . $txt['in'] . ' ' . $topic['board_link'] . '</em></span>';

						return $link;
					},
				],
				'sort' => [
					'default' => 'ms.subject',
					'reverse' => 'ms.subject DESC',
				],
			],
			'started_by' => [
				'header' => [
					'value' => $txt['started_by'],
					'class' => 'lefttext',
				],
				'data' => [
					'db' => 'poster_link',
				],
				'sort' => [
					'default' => 'real_name_col',
					'reverse' => 'real_name_col DESC',
				],
			],
			'last_post' => [
				'header' => [
					'value' => $txt['last_post'],
					'class' => 'lefttext',
				],
				'data' => [
					'sprintf' => [
						'format' => '<span class="smalltext">%1$s<br>' . $txt['by'] . ' %2$s</span>',
						'params' => [
							'updated' => false,
							'poster_updated_link' => false,
						],
					],
				],
				'sort' => [
					'default' => 'ml.id_msg DESC',
					'reverse' => 'ml.id_msg',
				],
			],
			'alert' => [
				'header' => [
					'value' => $txt['notify_what_how'],
					'class' => 'lefttext',
				],
				'data' => [
					'function' => function($topic) use ($txt)
					{
						$pref = $topic['notify_pref'];
						$mode = !empty($topic['unwatched']) ? 0 : ($pref & 0x02 ? 3 : ($pref & 0x01 ? 2 : 1));
						return $txt['notify_topic_' . $mode];
					},
				],
			],
			'delete' => [
				'header' => [
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'style' => 'width: 4%;',
					'class' => 'centercol',
				],
				'data' => [
					'sprintf' => [
						'format' => '<input type="checkbox" name="notify_topics[]" value="%1$d">',
						'params' => [
							'id' => false,
						],
					],
					'class' => 'centercol',
				],
			],
		],
		'form' => [
			'href' => $scripturl . '?action=profile;area=notification;sa=topics',
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => [
				'u' => $memID,
				'sa' => $context['menu_item_selected'],
				$context['session_var'] => $context['session_id'],
			],
			'token' => $context['token_check'],
		],
		'additional_rows' => [
			[
				'position' => 'bottom_of_list',
				'value' => '<input type="submit" name="edit_notify_topics" value="' . $txt['notifications_update'] . '">
							<input type="submit" name="remove_notify_topics" value="' . $txt['notification_remove_pref'] . '">',
				'class' => 'floatright',
			],
		],
	];

	// Create the notification list.
	createList($listOptions);
}

/**
 * Handles preferences related to board-level notifications
 *
 * @param int $memID The ID of the member
 */
function alert_notifications_boards($memID)
{
	global $txt, $scripturl, $context, $sourcedir;

	$context['sub_template'] = 'profile_alerts_watchedboards';

	// Because of the way this stuff works, we want to do this ourselves.
	if (isset($_POST['edit_notify_boards']) || isset($_POST['remove_notify_boards']))
	{
		checkSession();
		validateToken(str_replace('%u', $memID, 'profile-nt%u'), 'post');

		makeNotificationChanges($memID);
		session_flash('success', $txt['profile_updated_own']);
	}

	// Now set up for the token check.
	$context['token_check'] = str_replace('%u', $memID, 'profile-nt%u');
	createToken($context['token_check'], 'post');

	// Gonna want this for the list.
	require_once($sourcedir . '/Subs-List.php');

	// Fine, start with the board list.
	$listOptions = [
		'id' => 'board_notification_list',
		'width' => '100%',
		'no_items_label' => $txt['notifications_boards_none'] . '<br><br>' . $txt['notifications_boards_howto'],
		'no_items_align' => 'left',
		'base_href' => $scripturl . '?action=profile;u=' . $memID . ';area=notification;sa=boards',
		'default_sort_col' => 'board_name',
		'get_items' => [
			'function' => 'list_getBoardNotifications',
			'params' => [
				$memID,
			],
		],
		'columns' => [
			'board_name' => [
				'header' => [
					'value' => $txt['notifications_boards'],
					'class' => 'lefttext',
				],
				'data' => [
					'function' => function($board) use ($txt)
					{
						$link = $board['link'];

						if ($board['new'])
							$link .= ' <a href="' . $board['href'] . '" class="new_posts">' . $txt['new'] . '</a>';

						return $link;
					},
				],
				'sort' => [
					'default' => 'name',
					'reverse' => 'name DESC',
				],
			],
			'alert' => [
				'header' => [
					'value' => $txt['notify_what_how'],
					'class' => 'lefttext',
				],
				'data' => [
					'function' => function($board) use ($txt)
					{
						$pref = $board['notify_pref'];
						$mode = $pref & 0x02 ? 3 : ($pref & 0x01 ? 2 : 1);
						return $txt['notify_board_' . $mode];
					},
				],
			],
			'delete' => [
				'header' => [
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'style' => 'width: 4%;',
					'class' => 'centercol',
				],
				'data' => [
					'sprintf' => [
						'format' => '<input type="checkbox" name="notify_boards[]" value="%1$d">',
						'params' => [
							'id' => false,
						],
					],
					'class' => 'centercol',
				],
			],
		],
		'form' => [
			'href' => $scripturl . '?action=profile;area=notification;sa=boards',
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => [
				'u' => $memID,
				'sa' => $context['menu_item_selected'],
				$context['session_var'] => $context['session_id'],
			],
			'token' => $context['token_check'],
		],
		'additional_rows' => [
			[
				'position' => 'bottom_of_list',
				'value' => '<input type="submit" name="edit_notify_boards" value="' . $txt['notifications_update'] . '">
							<input type="submit" name="remove_notify_boards" value="' . $txt['notification_remove_pref'] . '">',
				'class' => 'floatright',
			],
		],
	];

	// Create the board notification list.
	createList($listOptions);
}

/**
 * Determins how many topics a user has requested notifications for
 *
 * @param int $memID The ID of the member
 * @return int The number of topic notifications for this user
 */
function list_getTopicNotificationCount($memID)
{
	global $smcFunc;

	$request = $smcFunc['db']->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE ln.id_member = {int:selected_member}
			AND {query_see_board}
			AND t.approved = {int:is_approved}',
		[
			'selected_member' => $memID,
			'is_approved' => 1,
		]
	);
	list ($totalNotifications) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	return (int) $totalNotifications;
}

/**
 * Gets information about all the topics a user has requested notifications for. Callback for the list in alert_notifications_topics
 *
 * @param int $start Which item to start with (for pagination purposes)
 * @param int $items_per_page How many items to display on each page
 * @param string $sort A string indicating how to sort the results
 * @param int $memID The ID of the member
 * @return array An array of information about the topics a user has subscribed to
 */
function list_getTopicNotifications($start, $items_per_page, $sort, $memID)
{
	global $smcFunc, $scripturl, $user_info, $sourcedir;

	require_once($sourcedir . '/Subs-Notify.php');
	$prefs = getNotifyPrefs($memID);
	$prefs = isset($prefs[$memID]) ? $prefs[$memID] : [];

	// All the topics with notification on...
	$request = $smcFunc['db']->query('', '
		SELECT
			COALESCE(lt.id_msg, COALESCE(lmr.id_msg, -1)) + 1 AS new_from, b.id_board, b.name,
			t.id_topic, ms.subject, ms.id_member, COALESCE(mem.real_name, ms.poster_name) AS real_name_col,
			ml.id_msg_modified, ml.poster_time, ml.id_member AS id_member_updated,
			COALESCE(mem2.real_name, ml.poster_name) AS last_real_name,
			lt.unwatched
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic AND t.approved = {int:is_approved})
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ms.id_member)
			LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = ml.id_member)
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})
		WHERE ln.id_member = {int:selected_member}
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:items_per_page}',
		[
			'current_member' => $user_info['id'],
			'is_approved' => 1,
			'selected_member' => $memID,
			'sort' => $sort,
			'offset' => $start,
			'items_per_page' => $items_per_page,
		]
	);
	$notification_topics = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		censorText($row['subject']);

		$notification_topics[] = [
			'id' => $row['id_topic'],
			'poster_link' => empty($row['id_member']) ? $row['real_name_col'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name_col'] . '</a>',
			'poster_updated_link' => empty($row['id_member_updated']) ? $row['last_real_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_updated'] . '">' . $row['last_real_name'] . '</a>',
			'subject' => $row['subject'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>',
			'new' => $row['new_from'] <= $row['id_msg_modified'],
			'new_from' => $row['new_from'],
			'updated' => timeformat($row['poster_time']),
			'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
			'new_link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new">' . $row['subject'] . '</a>',
			'board_link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'notify_pref' => isset($prefs['topic_notify_' . $row['id_topic']]) ? $prefs['topic_notify_' . $row['id_topic']] : (!empty($prefs['topic_notify']) ? $prefs['topic_notify'] : 0),
			'unwatched' => $row['unwatched'],
		];
	}
	$smcFunc['db']->free_result($request);

	return $notification_topics;
}

/**
 * Gets information about all the boards a user has requested notifications for. Callback for the list in alert_notifications_boards
 *
 * @param int $start Which item to start with (not used here)
 * @param int $items_per_page How many items to show on each page (not used here)
 * @param string $sort A string indicating how to sort the results
 * @param int $memID The ID of the member
 * @return array An array of information about all the boards a user is subscribed to
 */
function list_getBoardNotifications($start, $items_per_page, $sort, $memID)
{
	global $smcFunc, $scripturl, $user_info, $sourcedir;

	require_once($sourcedir . '/Subs-Notify.php');
	$prefs = getNotifyPrefs($memID);
	$prefs = isset($prefs[$memID]) ? $prefs[$memID] : [];

	$request = $smcFunc['db']->query('', '
		SELECT b.id_board, b.name, COALESCE(lb.id_msg, 0) AS board_read, b.id_msg_updated
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board)
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
		WHERE ln.id_member = {int:selected_member}
			AND {query_see_board}
		ORDER BY {raw:sort}',
		[
			'current_member' => $user_info['id'],
			'selected_member' => $memID,
			'sort' => $sort,
		]
	);
	$notification_boards = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
		$notification_boards[] = [
			'id' => $row['id_board'],
			'name' => $row['name'],
			'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'new' => $row['board_read'] < $row['id_msg_updated'],
			'notify_pref' => isset($prefs['board_notify_' . $row['id_board']]) ? $prefs['board_notify_' . $row['id_board']] : (!empty($prefs['board_notify']) ? $prefs['board_notify'] : 0),
		];
	$smcFunc['db']->free_result($request);

	return $notification_boards;
}

/**
 * Loads the theme options for a user
 *
 * @param int $memID The ID of the member
 */
function loadThemeOptions($memID)
{
	global $context, $options, $cur_profile, $smcFunc;

	if (isset($_POST['default_options']))
		$_POST['options'] = isset($_POST['options']) ? $_POST['options'] + $_POST['default_options'] : $_POST['default_options'];

	if ($context['user']['is_owner'])
	{
		$context['member']['options'] = $options;
		if (isset($_POST['options']) && is_array($_POST['options']))
			foreach ($_POST['options'] as $k => $v)
				$context['member']['options'][$k] = $v;
	}
	else
	{
		$container = Container::instance();
		$prefs_manager = $container->instantiate('StoryBB\\User\\PreferenceManager');
		$context['member']['options'] = $prefs_manager->get_preferences_for_user($memID);
	}
}

/**
 * Handles the "ignored boards" section of the profile (if enabled)
 *
 * @param int $memID The ID of the member
 */
function ignoreboards($memID)
{
	global $context, $modSettings, $smcFunc, $cur_profile, $sourcedir;

	// Have the admins enabled this option?
	if (empty($modSettings['allow_ignore_boards']))
		fatal_lang_error('ignoreboards_disallowed', 'user');

	// Find all the boards this user is allowed to see.
	$request = $smcFunc['db']->query('order_by_board_order', '
		SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level,
			'. (!empty($cur_profile['ignore_boards']) ? 'b.id_board IN ({array_int:ignore_boards})' : '0') . ' AS is_ignored
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE {query_see_board}
			AND redirect = {string:empty_string}',
		[
			'ignore_boards' => !empty($cur_profile['ignore_boards']) ? explode(',', $cur_profile['ignore_boards']) : [],
			'empty_string' => '',
		]
	);
	$context['num_boards'] = $smcFunc['db']->num_rows($request);
	$context['categories'] = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// This category hasn't been set up yet..
		if (!isset($context['categories'][$row['id_cat']]))
			$context['categories'][$row['id_cat']] = [
				'id' => $row['id_cat'],
				'name' => $row['cat_name'],
				'boards' => []
			];

		// Set this board up, and let the template know when it's a child.  (indent them..)
		$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = [
			'id' => $row['id_board'],
			'name' => $row['name'],
			'child_level' => $row['child_level'],
			'selected' => (bool) $row['is_ignored'],
		];
	}
	$smcFunc['db']->free_result($request);

	require_once($sourcedir . '/Subs-Boards.php');
	sortCategories($context['categories']);

	// Now, let's sort the list of categories into the boards for templates that like that.
	$temp_boards = [];
	foreach ($context['categories'] as $category)
	{
		// Include a list of boards per category for easy toggling.
		$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);

		$temp_boards[] = [
			'name' => $category['name'],
			'child_ids' => array_keys($category['boards'])
		];
		$temp_boards = array_merge($temp_boards, array_values($category['boards']));
	}

	$max_boards = ceil(count($temp_boards) / 2);
	if ($max_boards == 1)
		$max_boards = 2;

	// Now, alternate them so they can be shown left and right ;).
	$context['board_columns'] = [];
	for ($i = 0; $i < $max_boards; $i++)
	{
		$context['board_columns'][] = $temp_boards[$i];
		if (isset($temp_boards[$i + $max_boards]))
			$context['board_columns'][] = $temp_boards[$i + $max_boards];
		else
			$context['board_columns'][] = [];
	}

	$context['split_categories'] = array_chunk($context['categories'], ceil(count($context['categories']) / 2), true);
	$context['sub_template'] = 'profile_ignoreboards';

	loadThemeOptions($memID);
}

/**
 * Load all the languages for the profile
 * .
 * @return bool Whether or not the forum has multiple languages installed
 */
function profileLoadLanguages()
{
	global $context;

	$context['profile_languages'] = [];

	// Get our languages!
	getLanguages();

	// Setup our languages.
	foreach ($context['languages'] as $lang)
	{
		$context['profile_languages'][$lang['filename']] = strtr($lang['name'], ['-utf8' => '']);
	}
	ksort($context['profile_languages']);

	// Return whether we should proceed with this.
	return count($context['profile_languages']) > 1 ? true : false;
}

/**
 * Handles the "manage groups" section of the profile
 *
 * @return true Always returns true
 */
function profileLoadGroups()
{
	global $cur_profile, $txt, $context, $smcFunc, $user_settings;

	$context['member_groups'] = [
		0 => [
			'id' => 0,
			'name' => $txt['no_primary_membergroup'],
			'is_primary' => $cur_profile['id_group'] == 0,
			'can_be_additional' => false,
			'can_be_primary' => true,
		]
	];
	$curGroups = explode(',', $cur_profile['additional_groups']);

	// Load membergroups, but only those groups the user can assign.
	$request = $smcFunc['db']->query('', '
		SELECT group_name, id_group, hidden
		FROM {db_prefix}membergroups
		WHERE id_group != {int:moderator_group}
			AND is_character = 0' . (allowedTo('admin_forum') ? '' : '
			AND group_type != {int:is_protected}') . '
		ORDER BY group_name',
		[
			'moderator_group' => 3,
			'is_protected' => 1,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// We should skip the administrator group if they don't have the admin_forum permission!
		if ($row['id_group'] == 1 && !allowedTo('admin_forum'))
			continue;

		$context['member_groups'][$row['id_group']] = [
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'is_primary' => $cur_profile['id_group'] == $row['id_group'],
			'is_additional' => in_array($row['id_group'], $curGroups),
			'can_be_additional' => true,
			'can_be_primary' => $row['hidden'] != 2,
		];
	}
	$smcFunc['db']->free_result($request);

	$context['member']['group_id'] = $user_settings['id_group'];

	return true;
}

/**
 * Load key signature context data.
 *
 * @return true Always returns true
 */
function profileLoadSignatureData()
{
	global $modSettings, $context, $txt, $cur_profile, $memberContext;

	// Signature limits.
	list ($sig_limits, $sig_bbc) = explode(':', $modSettings['signature_settings']);
	$sig_limits = explode(',', $sig_limits);

	$context['signature_enabled'] = isset($sig_limits[0]) ? $sig_limits[0] : 0;
	$context['signature_limits'] = [
		'max_length' => isset($sig_limits[1]) ? $sig_limits[1] : 0,
		'max_lines' => isset($sig_limits[2]) ? $sig_limits[2] : 0,
		'max_images' => isset($sig_limits[3]) ? $sig_limits[3] : 0,
		'max_smileys' => isset($sig_limits[4]) ? $sig_limits[4] : 0,
		'max_image_width' => isset($sig_limits[5]) ? $sig_limits[5] : 0,
		'max_image_height' => isset($sig_limits[6]) ? $sig_limits[6] : 0,
		'max_font_size' => isset($sig_limits[7]) ? $sig_limits[7] : 0,
		'bbc' => !empty($sig_bbc) ? explode(',', $sig_bbc) : [],
	];
	// Kept this line in for backwards compatibility!
	$context['max_signature_length'] = $context['signature_limits']['max_length'];
	// Warning message for signature image limits?
	$context['signature_warning'] = '';
	if ($context['signature_limits']['max_image_width'] && $context['signature_limits']['max_image_height'])
		$context['signature_warning'] = sprintf($txt['profile_error_signature_max_image_size'], $context['signature_limits']['max_image_width'], $context['signature_limits']['max_image_height']);
	elseif ($context['signature_limits']['max_image_width'] || $context['signature_limits']['max_image_height'])
		$context['signature_warning'] = sprintf($txt['profile_error_signature_max_image_' . ($context['signature_limits']['max_image_width'] ? 'width' : 'height')], $context['signature_limits'][$context['signature_limits']['max_image_width'] ? 'max_image_width' : 'max_image_height']);

	if (empty($context['do_preview']))
		$context['member']['signature'] = empty($cur_profile['signature']) ? '' : str_replace(['<br>', '<', '>', '"', '\''], ["\n", '&lt;', '&gt;', '&quot;', '&#039;'], $cur_profile['signature']);
	else
	{
		$signature = !empty($_POST['signature']) ? $_POST['signature'] : '';
		$validation = profileValidateSignature($signature);
		if (empty($context['post_errors']))
		{
			loadLanguage('Errors');
			$context['post_errors'] = [];
		}
		$context['post_errors'][] = 'signature_not_yet_saved';
		if ($validation !== true && $validation !== false)
			$context['post_errors'][] = $validation;

		censorText($context['member']['signature']);
		$context['member']['current_signature'] = $context['member']['signature'];
		censorText($signature);
		$context['member']['signature_preview'] = Parser::parse_bbc($signature, true, 'sig' . $memberContext[$context['id_member']]);
		$context['member']['signature'] = $_POST['signature'];
	}

	return true;
}

/**
 * Load avatar context data.
 *
 * @return true Always returns true
 */
function profileLoadAvatarData()
{
	global $context, $cur_profile, $modSettings, $scripturl;

	// Default context.
	if (empty($context['member']['avatar']))
		$context['member']['avatar'] = [];

	$context['member']['avatar'] += [
		'custom' => stristr($cur_profile['avatar'], 'http://') || stristr($cur_profile['avatar'], 'https://') ? $cur_profile['avatar'] : 'http://',
		'selection' => $cur_profile['avatar'] == '' || (stristr($cur_profile['avatar'], 'http://') || stristr($cur_profile['avatar'], 'https://')) ? '' : $cur_profile['avatar'],
		'allow_upload' => allowedTo('profile_upload_avatar') || (!$context['user']['is_owner'] && allowedTo('profile_extra_any')),
		'allow_external' => allowedTo('profile_remote_avatar') || (!$context['user']['is_owner'] && allowedTo('profile_extra_any')),
	];

	if ($cur_profile['avatar'] == '' && $cur_profile['id_attach'] > 0 && $context['member']['avatar']['allow_upload'])
	{
		$context['member']['avatar'] += [
			'choice' => 'upload',
			'server_pic' => 'blank.png',
			'external' => 'http://'
		];
		$context['member']['avatar']['href'] = empty($cur_profile['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $cur_profile['id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $cur_profile['filename'];
	}
	// Use "avatar_original" here so we show what the user entered even if the image proxy is enabled
	elseif ((stristr($cur_profile['avatar'], 'http://') || stristr($cur_profile['avatar'], 'https://')) && $context['member']['avatar']['allow_external'])
		$context['member']['avatar'] += [
			'choice' => 'external',
			'server_pic' => 'blank.png',
			'external' => $cur_profile['avatar_original']
		];
	else
		$context['member']['avatar'] += [
			'choice' => 'none',
			'server_pic' => 'blank.png',
			'external' => 'http://'
		];

	// Second level selected avatar...
	$context['avatar_selected'] = substr(strrchr($context['member']['avatar']['server_pic'], '/'), 1); // @todo remove?
	return !empty($context['member']['avatar']['allow_external']) || !empty($context['member']['avatar']['allow_upload']);
}

/**
 * Save a members group.
 *
 * @param int &$value The ID of the (new) primary group
 * @return true Always returns true
 */
function profileSaveGroups(&$value)
{
	global $profile_vars, $old_profile, $context, $smcFunc, $cur_profile;

	// Do we need to protect some groups?
	if (!allowedTo('admin_forum'))
	{
		$request = $smcFunc['db']->query('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE group_type = {int:is_protected}',
			[
				'is_protected' => 1,
			]
		);
		$protected_groups = [1];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$protected_groups[] = $row['id_group'];
		$smcFunc['db']->free_result($request);

		$protected_groups = array_unique($protected_groups);
	}

	// We can't have users adding anyone to character groups
	$char_groups = [];
	$request = $smcFunc['db']->query('', '
		SELECT id_group
		FROM {db_prefix}membergroups
		WHERE is_character = 1');
	while ($row = $smcFunc['db']->fetch_row($request))
		$char_groups[] = $row[0];
	$smcFunc['db']->free_result($request);

	// No primary character group for you!
	if (in_array($value, $char_groups))
		$value = $old_profile['id_group'];

	// No secondary character groups for you!
	if (isset($_POST['additional_groups']) && is_array($_POST['additional_groups']))
		$_POST['additional_groups'] = array_diff($_POST['additional_groups'], $char_groups);

	// The account page allows the change of your id_group - but not to a protected group!
	if (empty($protected_groups) || count(array_intersect([(int) $value, $old_profile['id_group']], $protected_groups)) == 0)
		$value = (int) $value;
	// ... otherwise it's the old group sir.
	else
		$value = $old_profile['id_group'];

	// Find the additional membergroups (if any)
	if (isset($_POST['additional_groups']) && is_array($_POST['additional_groups']))
	{
		$additional_groups = [];
		foreach ($_POST['additional_groups'] as $group_id)
		{
			$group_id = (int) $group_id;
			if (!empty($group_id) && (empty($protected_groups) || !in_array($group_id, $protected_groups)))
				$additional_groups[] = $group_id;
		}

		// Put the protected groups back in there if you don't have permission to take them away.
		$old_additional_groups = explode(',', $old_profile['additional_groups']);
		foreach ($old_additional_groups as $group_id)
		{
			if (!empty($protected_groups) && in_array($group_id, $protected_groups))
				$additional_groups[] = $group_id;
		}

		if (implode(',', $additional_groups) !== $old_profile['additional_groups'])
		{
			$profile_vars['additional_groups'] = implode(',', $additional_groups);
			$cur_profile['additional_groups'] = implode(',', $additional_groups);
		}
	}

	// Too often, people remove delete their own account, or something.
	if (in_array(1, explode(',', $old_profile['additional_groups'])) || $old_profile['id_group'] == 1)
	{
		$stillAdmin = $value == 1 || (isset($additional_groups) && in_array(1, $additional_groups));

		// If they would no longer be an admin, look for any other...
		if (!$stillAdmin)
		{
			$request = $smcFunc['db']->query('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0)
					AND id_member != {int:selected_member}
				LIMIT 1',
				[
					'admin_group' => 1,
					'selected_member' => $context['id_member'],
				]
			);
			list ($another) = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);

			if (empty($another))
				fatal_lang_error('at_least_one_admin', 'critical');
		}
	}

	// If we are changing group status, update permission cache as necessary.
	if ($value != $old_profile['id_group'] || isset($profile_vars['additional_groups']))
	{
		if ($context['user']['is_owner'])
			$_SESSION['mc']['time'] = 0;
		else
			updateSettings(['settings_updated' => time()]);
	}

	// Announce to any hooks that we have changed groups, but don't allow them to change it.
	call_integration_hook('integrate_profile_profileSaveGroups', [$value, $additional_groups]);

	return true;
}

/**
 * The avatar is incredibly complicated, what with the options... and what not.
 * @todo argh, the avatar here. Take this out of here!
 *
 * @param string &$value What kind of avatar we're expecting. Can be 'none', 'external' or 'upload'
 * @return bool|string False if success (or if memID is empty and password authentication failed), otherwise a string indicating what error occurred
 */
function profileSaveAvatarData(&$value)
{
	global $modSettings, $sourcedir, $smcFunc, $profile_vars, $cur_profile, $context;

	$memID = $context['id_member'];
	if (empty($context['character']['id_character']))
	{
		foreach ($context['member']['characters'] as $id_char => $char) {
			if ($char['is_main']) {
				$context['character']['id_character'] = $id_char;
				break;
			}
		}
	}
	if (empty($memID) && !empty($context['password_auth_failed']))
		return false;
	if (empty($context['character']['id_character']))
		return false;

	require_once($sourcedir . '/ManageAttachments.php');

	// We're going to put this on a nice custom dir.
	$uploadDir = $modSettings['custom_avatar_dir'];
	$id_folder = 1;

	$downloadedExternalAvatar = false;
	if ($value == 'external' && allowedTo('profile_remote_avatar') && (stripos($_POST['userpicpersonal'], 'http://') === 0 || stripos($_POST['userpicpersonal'], 'https://') === 0) && strlen($_POST['userpicpersonal']) > 7 && !empty($modSettings['avatar_download_external']))
	{
		if (!is_writable($uploadDir))
			fatal_lang_error('attachments_no_write', 'critical');

		require_once($sourcedir . '/Subs-Package.php');

		$url = parse_url($_POST['userpicpersonal']);
		$rebuilt_url = $url['scheme'] . '://' . $url['host'] . (empty($url['port']) ? '' : ':' . $url['port']) . str_replace(' ', '%20', trim($url['path']));

		$client = new Client();
		$http_request = $client->get($rebuilt_url);
		$contents = (string) $http_request->getBody();

		$new_filename = $uploadDir . '/' . Attachment::get_new_filename('avatar_tmp_' . $memID);
		if (!empty($contents) && $tmpAvatar = fopen($new_filename, 'wb'))
		{
			fwrite($tmpAvatar, $contents);
			fclose($tmpAvatar);

			$downloadedExternalAvatar = true;
			$_FILES['attachment']['tmp_name'] = $new_filename;
		}
	}

	// Removes whatever attachment there was before updating
	if ($value == 'none')
	{
		$profile_vars['avatar'] = '';

		// Reset the attach ID.
		$cur_profile['id_attach'] = 0;
		$cur_profile['attachment_type'] = 0;
		$cur_profile['filename'] = '';

		removeAttachments(['id_character' => $context['character']['id_character']]);
	}
	elseif ($value == 'external' && allowedTo('profile_remote_avatar') && (stripos($_POST['userpicpersonal'], 'http://') === 0 || stripos($_POST['userpicpersonal'], 'https://') === 0) && empty($modSettings['avatar_download_external']))
	{
		// We need these clean...
		$cur_profile['id_attach'] = 0;
		$cur_profile['attachment_type'] = 0;
		$cur_profile['filename'] = '';

		// Remove any attached avatar...
		removeAttachments(['id_character' => $context['character']['id_character']]);

		$profile_vars['avatar'] = str_replace(' ', '%20', preg_replace('~action(?:=|%3d)(?!dlattach)~i', 'action-', $_POST['userpicpersonal']));

		if ($profile_vars['avatar'] == 'http://' || $profile_vars['avatar'] == 'http:///')
			$profile_vars['avatar'] = '';
		// Trying to make us do something we'll regret?
		elseif (substr($profile_vars['avatar'], 0, 7) != 'http://' && substr($profile_vars['avatar'], 0, 8) != 'https://')
			return 'bad_avatar_invalid_url';
		// Should we check dimensions?
		elseif (!empty($modSettings['avatar_max_height']) || !empty($modSettings['avatar_max_width']))
		{
			// Now let's validate the avatar.
			$sizes = url_image_size($profile_vars['avatar']);

			if (is_array($sizes) && (($sizes[0] > $modSettings['avatar_max_width'] && !empty($modSettings['avatar_max_width'])) || ($sizes[1] > $modSettings['avatar_max_height'] && !empty($modSettings['avatar_max_height']))))
			{
				// Houston, we have a problem. The avatar is too large!!
				if ($modSettings['avatar_action_too_large'] == 'option_refuse')
					return 'bad_avatar_too_large';
				elseif ($modSettings['avatar_action_too_large'] == 'option_download_and_resize')
				{
					// @todo remove this if appropriate
					require_once($sourcedir . '/Subs-Graphics.php');
					if (downloadAvatar($profile_vars['avatar'], $context['character']['id_character'], $modSettings['avatar_max_width'], $modSettings['avatar_max_height']))
					{
						$profile_vars['avatar'] = '';
						$cur_profile['id_attach'] = $modSettings['new_avatar_data']['id'];
						$cur_profile['filename'] = $modSettings['new_avatar_data']['filename'];
						$cur_profile['attachment_type'] = $modSettings['new_avatar_data']['type'];
					}
					else
						return 'bad_avatar';
				}
			}
		}
	}
	elseif (($value == 'upload' && allowedTo('profile_upload_avatar')) || $downloadedExternalAvatar)
	{
		if ((isset($_FILES['attachment']['name']) && $_FILES['attachment']['name'] != '') || $downloadedExternalAvatar)
		{
			// Get the dimensions of the image.
			if (!$downloadedExternalAvatar)
			{
				if (!is_writable($uploadDir))
					fatal_lang_error('attachments_no_write', 'critical');

				$new_filename = $uploadDir . '/' . Attachment::get_new_filename('avatar_tmp_' . $memID);
				if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $new_filename))
				{
					loadLanguage('Post');
					fatal_lang_error('attach_timeout', 'critical');
				}

				$_FILES['attachment']['tmp_name'] = $new_filename;
			}

			$sizes = @getimagesize($_FILES['attachment']['tmp_name']);

			// No size, then it's probably not a valid pic.
			if ($sizes === false)
			{
				@unlink($_FILES['attachment']['tmp_name']);
				return 'bad_avatar';
			}
			// Check whether the image is too large.
			elseif ((!empty($modSettings['avatar_max_width']) && $sizes[0] > $modSettings['avatar_max_width']) || (!empty($modSettings['avatar_max_height']) && $sizes[1] > $modSettings['avatar_max_height']))
			{
				if (!empty($modSettings['avatar_resize_upload']))
				{
					// Attempt to chmod it.
					sbb_chmod($_FILES['attachment']['tmp_name'], 0644);

					// @todo remove this require when appropriate
					require_once($sourcedir . '/Subs-Graphics.php');
					if (!downloadAvatar($_FILES['attachment']['tmp_name'], $context['character']['id_character'], $modSettings['avatar_max_width'], $modSettings['avatar_max_height']))
					{
						@unlink($_FILES['attachment']['tmp_name']);
						return 'bad_avatar';
					}

					// Reset attachment avatar data.
					$cur_profile['id_attach'] = $modSettings['new_avatar_data']['id'];
					$cur_profile['filename'] = $modSettings['new_avatar_data']['filename'];
					$cur_profile['attachment_type'] = $modSettings['new_avatar_data']['type'];
				}

				// Admin doesn't want to resize large avatars, can't do much about it but to tell you to use a different one :(
				else
				{
					@unlink($_FILES['attachment']['tmp_name']);
					return 'bad_avatar_too_large';
				}
			}

			// So far, so good, checks lies ahead!
			elseif (is_array($sizes))
			{
				// Now try to find an infection.
				require_once($sourcedir . '/Subs-Graphics.php');
				if (!checkImageContents($_FILES['attachment']['tmp_name'], !empty($modSettings['avatar_paranoid'])))
				{
					// It's bad. Try to re-encode the contents?
					if (empty($modSettings['avatar_reencode']) || (!reencodeImage($_FILES['attachment']['tmp_name'], $sizes[2])))
					{
						@unlink($_FILES['attachment']['tmp_name']);
						return 'bad_avatar_fail_reencode';
					}
					// We were successful. However, at what price?
					$sizes = @getimagesize($_FILES['attachment']['tmp_name']);
					// Hard to believe this would happen, but can you bet?
					if ($sizes === false)
					{
						@unlink($_FILES['attachment']['tmp_name']);
						return 'bad_avatar';
					}
				}

				$extensions = [
					'1' => 'gif',
					'2' => 'jpg',
					'3' => 'png',
					'6' => 'bmp'
				];

				$extension = isset($extensions[$sizes[2]]) ? $extensions[$sizes[2]] : 'bmp';
				$mime_type = 'image/' . ($extension === 'jpg' ? 'jpeg' : ($extension === 'bmp' ? 'x-ms-bmp' : $extension));
				$destName = 'avatar_' . $context['character']['id_character'] . '_' . time() . '.' . $extension;
				list ($width, $height) = getimagesize($_FILES['attachment']['tmp_name']);
				$file_hash = '';

				// Remove previous attachments this member might have had.
				removeAttachments(['id_character' => $context['character']['id_character']]);

				$cur_profile['id_attach'] = $smcFunc['db']->insert('',
					'{db_prefix}attachments',
					[
						'id_character' => 'int', 'attachment_type' => 'int', 'filename' => 'string', 'file_hash' => 'string', 'fileext' => 'string', 'size' => 'int',
						'width' => 'int', 'height' => 'int', 'mime_type' => 'string', 'id_folder' => 'int',
					],
					[
						$context['character']['id_character'], 1, $destName, $file_hash, $extension, filesize($_FILES['attachment']['tmp_name']),
						(int) $width, (int) $height, $mime_type, $id_folder,
					],
					['id_attach'],
					1
				);

				$cur_profile['filename'] = $destName;
				$cur_profile['attachment_type'] = 1;

				$destinationPath = $uploadDir . '/' . (empty($file_hash) ? $destName : $cur_profile['id_attach'] . '_' . $file_hash . '.dat');
				if (!rename($_FILES['attachment']['tmp_name'], $destinationPath))
				{
					// I guess a man can try.
					removeAttachments(['id_character' => $memID]);
					fatal_lang_error('attach_timeout', 'critical');
				}

				// Attempt to chmod it.
				sbb_chmod($uploadDir . '/' . $destinationPath, 0644);
			}
			$profile_vars['avatar'] = '';

			// Delete any temporary file.
			if (file_exists($_FILES['attachment']['tmp_name']))
				@unlink($_FILES['attachment']['tmp_name']);
		}
		// Selected the upload avatar option and had one already uploaded before or didn't upload one.
		else
			$profile_vars['avatar'] = '';
	}
	else
		$profile_vars['avatar'] = '';

	// Setup the profile variables so it shows things right on display!
	$cur_profile['avatar'] = $profile_vars['avatar'];

	return false;
}

/**
 * Validate the signature
 *
 * @param string &$value The new signature
 * @return bool|string True if the signature passes the checks, otherwise a string indicating what the problem is
 */
function profileValidateSignature(&$value)
{
	global $sourcedir, $modSettings, $txt;

	require_once($sourcedir . '/Subs-Post.php');

	// Admins can do whatever they hell they want!
	if (!allowedTo('admin_forum'))
	{
		// Load all the signature limits.
		list ($sig_limits, $sig_bbc) = explode(':', $modSettings['signature_settings']);
		$sig_limits = explode(',', $sig_limits);
		$disabledTags = !empty($sig_bbc) ? explode(',', $sig_bbc) : [];

		$unparsed_signature = strtr(un_htmlspecialchars($value), ["\r" => '', '&#039' => '\'']);

		// Too many lines?
		if (!empty($sig_limits[2]) && substr_count($unparsed_signature, "\n") >= $sig_limits[2])
		{
			$txt['profile_error_signature_max_lines'] = sprintf($txt['profile_error_signature_max_lines'], $sig_limits[2]);
			return 'signature_max_lines';
		}

		// Too many images?!
		if (!empty($sig_limits[3]) && (substr_count(strtolower($unparsed_signature), '[img') + substr_count(strtolower($unparsed_signature), '<img')) > $sig_limits[3])
		{
			$txt['profile_error_signature_max_image_count'] = sprintf($txt['profile_error_signature_max_image_count'], $sig_limits[3]);
			return 'signature_max_image_count';
		}

		// What about too many smileys!
		$smiley_parsed = $unparsed_signature;
		Parser::parse_smileys($smiley_parsed);
		$smiley_count = substr_count(strtolower($smiley_parsed), '<img') - substr_count(strtolower($unparsed_signature), '<img');
		if (!empty($sig_limits[4]) && $sig_limits[4] == -1 && $smiley_count > 0)
			return 'signature_allow_smileys';
		elseif (!empty($sig_limits[4]) && $sig_limits[4] > 0 && $smiley_count > $sig_limits[4])
		{
			$txt['profile_error_signature_max_smileys'] = sprintf($txt['profile_error_signature_max_smileys'], $sig_limits[4]);
			return 'signature_max_smileys';
		}

		// Maybe we are abusing font sizes?
		if (!empty($sig_limits[7]) && preg_match_all('~\[size=([\d\.]+)?(px|pt|em|x-large|larger)~i', $unparsed_signature, $matches) !== false && isset($matches[2]))
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
				{
					$txt['profile_error_signature_max_font_size'] = sprintf($txt['profile_error_signature_max_font_size'], $limit_broke);
					return 'signature_max_font_size';
				}
			}
		}

		// The difficult one - image sizes! Don't error on this - just fix it.
		if ((!empty($sig_limits[5]) || !empty($sig_limits[6])))
		{
			// Get all BBC tags...
			preg_match_all('~\[img(\s+width=([\d]+))?(\s+height=([\d]+))?(\s+width=([\d]+))?\s*\](?:<br>)*([^<">]+?)(?:<br>)*\[/img\]~i', $unparsed_signature, $matches);
			// ... and all HTML ones.
			preg_match_all('~<img\s+src=(?:")?((?:http://|ftp://|https://|ftps://).+?)(?:")?(?:\s+alt=(?:")?(.*?)(?:")?)?(?:\s?/)?>~i', $unparsed_signature, $matches2, PREG_PATTERN_ORDER);
			// And stick the HTML in the BBC.
			if (!empty($matches2))
			{
				foreach (array_keys($matches2[0]) as $ind)
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

			$replaces = [];
			// Try to find all the images!
			if (!empty($matches))
			{
				foreach ($matches[0] as $key => $image)
				{
					$width = -1;
					$height = -1;

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
						$replaces[$image] = '[img' . ($width != -1 ? ' width=' . round($width) : '') . ($height != -1 ? ' height=' . round($height) : '') . ']' . $matches[7][$key] . '[/img]';
				}
				if (!empty($replaces))
					$value = str_replace(array_keys($replaces), array_values($replaces), $value);
			}
		}

		// Any disabled BBC?
		$disabledSigBBC = implode('|', $disabledTags);
		if (!empty($disabledSigBBC))
		{
			if (preg_match('~\[(' . $disabledSigBBC . '[ =\]/])~i', $unparsed_signature, $matches) !== false && isset($matches[1]))
			{
				$disabledTags = array_unique($disabledTags);
				$txt['profile_error_signature_disabled_bbc'] = sprintf($txt['profile_error_signature_disabled_bbc'], implode(', ', $disabledTags));
				return 'signature_disabled_bbc';
			}
		}
	}

	preparsecode($value);

	// Too long?
	if (!allowedTo('admin_forum') && !empty($sig_limits[1]) && StringLibrary::strpos(str_replace('<br>', "\n", $value)) > $sig_limits[1])
	{
		$_POST['signature'] = trim(StringLibrary::escape(str_replace('<br>', "\n", $value), ENT_QUOTES));
		$txt['profile_error_signature_max_length'] = sprintf($txt['profile_error_signature_max_length'], $sig_limits[1]);
		return 'signature_max_length';
	}

	return true;
}

/**
 * Validate an email address.
 *
 * @param string $email The email address to validate
 * @param int $memID The ID of the member (used to prevent false positives from the current user)
 * @return bool|string True if the email is valid, otherwise a string indicating what the problem is
 */
function profileValidateEmail($email, $memID = 0)
{
	global $smcFunc;

	$email = strtr($email, ['&#039;' => '\'']);

	// Check the name and email for validity.
	if (trim($email) == '')
		return 'no_email';
	if (!filter_var($email, FILTER_VALIDATE_EMAIL))
		return 'bad_email';

	// Email addresses should be and stay unique.
	$request = $smcFunc['db']->query('', '
		SELECT id_member
		FROM {db_prefix}members
		WHERE ' . ($memID != 0 ? 'id_member != {int:selected_member} AND ' : '') . '
			email_address = {string:email_address}
		LIMIT 1',
		[
			'selected_member' => $memID,
			'email_address' => $email,
		]
	);

	if ($smcFunc['db']->num_rows($request) > 0)
		return 'email_taken';
	$smcFunc['db']->free_result($request);

	return true;
}

/**
 * Reload a user's settings.
 */
function profileReloadUser()
{
	global $context, $cur_profile;

	if (isset($_POST['passwrd2']) && $_POST['passwrd2'] != '')
		setLoginCookie(0, $context['id_member'], hash_salt($_POST['passwrd1'], $cur_profile['password_salt']));

	loadUserSettings();
	writeLog();
}

/**
 * Send the user a new activation email if they need to reactivate!
 */
function profileSendActivation()
{
	global $sourcedir, $profile_vars, $context, $scripturl, $smcFunc, $cookiename, $cur_profile, $language, $modSettings;

	require_once($sourcedir . '/Subs-Post.php');

	// Shouldn't happen but just in case.
	if (empty($profile_vars['email_address']))
		return;

	$replacements = [
		'ACTIVATIONLINK' => $scripturl . '?action=activate;u=' . $context['id_member'] . ';code=' . $profile_vars['validation_code'],
		'ACTIVATIONCODE' => $profile_vars['validation_code'],
		'ACTIVATIONLINKWITHOUTCODE' => $scripturl . '?action=activate;u=' . $context['id_member'],
	];

	// Send off the email.
	$emaildata = loadEmailTemplate('activate_reactivate', $replacements, empty($cur_profile['lngfile']) || empty($modSettings['userLanguage']) ? $language : $cur_profile['lngfile']);
	StoryBB\Helper\Mail::send($profile_vars['email_address'], $emaildata['subject'], $emaildata['body'], null, 'reactivate', $emaildata['is_html'], 0);

	// Log the user out.
	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}log_online
		WHERE id_member = {int:selected_member}',
		[
			'selected_member' => $context['id_member'],
		]
	);
	$_SESSION['log_time'] = 0;
	$_SESSION['login_' . $cookiename] = json_encode([0, '', 0]);

	if (isset($_COOKIE[$cookiename]))
		$_COOKIE[$cookiename] = '';

	loadUserSettings();

	$context['user']['is_logged'] = false;
	$context['user']['is_guest'] = true;

	redirectexit('action=sendactivation');
}

/**
 * Function to allow the user to choose group membership etc...
 *
 * @param int $memID The ID of the member
 */
function groupMembership($memID)
{
	global $txt, $user_profile, $context, $smcFunc;

	$curMember = $user_profile[$memID];
	$context['primary_group'] = $curMember['id_group'];

	// Can they manage groups?
	$context['can_manage_membergroups'] = allowedTo('manage_membergroups');
	$context['can_manage_protected'] = allowedTo('admin_forum');
	$context['can_edit_primary'] = $context['can_manage_protected'];
	$context['update_message'] = isset($_GET['msg']) && isset($txt['group_membership_msg_' . $_GET['msg']]) ? $txt['group_membership_msg_' . $_GET['msg']] : '';

	// Get all the groups this user is a member of.
	$groups = explode(',', $curMember['additional_groups']);
	$groups[] = $curMember['id_group'];

	// Ensure the query doesn't croak!
	if (empty($groups))
		$groups = [0];
	// Just to be sure...
	foreach ($groups as $k => $v)
		$groups[$k] = (int) $v;

	// Get all the membergroups they can join.
	$request = $smcFunc['db']->query('', '
		SELECT mg.id_group, mg.group_name, mg.description, mg.group_type, mg.online_color, mg.hidden,
			COALESCE(lgr.id_member, 0) AS pending
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}log_group_requests AS lgr ON (lgr.id_member = {int:selected_member} AND lgr.id_group = mg.id_group AND lgr.status = {int:status_open})
		WHERE (mg.id_group IN ({array_int:group_list})
			OR mg.group_type > {int:nonjoin_group_id})
			AND mg.id_group != {int:moderator_group}
		ORDER BY group_name',
		[
			'group_list' => $groups,
			'selected_member' => $memID,
			'status_open' => 0,
			'nonjoin_group_id' => 1,
			'moderator_group' => 3,
		]
	);
	// This beast will be our group holder.
	$context['groups'] = [
		'member' => [],
		'available' => []
	];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// Can they edit their primary group?
		if (($row['id_group'] == $context['primary_group'] && $row['group_type'] > 1) || ($row['hidden'] != 2 && $context['primary_group'] == 0 && in_array($row['id_group'], $groups)))
			$context['can_edit_primary'] = true;

		// If they can't manage (protected) groups, and it's not publically joinable or already assigned, they can't see it.
		if (((!$context['can_manage_protected'] && $row['group_type'] == 1) || (!$context['can_manage_membergroups'] && $row['group_type'] == 0)) && $row['id_group'] != $context['primary_group'])
			continue;

		$context['groups'][in_array($row['id_group'], $groups) ? 'member' : 'available'][$row['id_group']] = [
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'desc' => $row['description'],
			'color' => $row['online_color'],
			'type' => $row['group_type'],
			'pending' => (bool) $row['pending'],
			'is_primary' => $row['id_group'] == $context['primary_group'],
			'can_be_primary' => $row['hidden'] != 2,
			// Anything more than this needs to be done through account settings for security.
			'can_leave' => $row['id_group'] != 1 && $row['group_type'] > 1 ? true : false,
		];
	}
	$smcFunc['db']->free_result($request);

	// Add registered members on the end.
	$context['groups']['member'][0] = [
		'id' => 0,
		'name' => $txt['regular_members'],
		'desc' => $txt['regular_members_desc'],
		'type' => 0,
		'is_primary' => $context['primary_group'] == 0 ? true : false,
		'can_be_primary' => true,
		'can_leave' => 0,
	];

	// No changing primary one unless you have enough groups!
	if (count($context['groups']['member']) < 2)
		$context['can_edit_primary'] = false;

	// In the special case that someone is requesting membership of a group, setup some special context vars.
	if (isset($_REQUEST['request']) && isset($context['groups']['available'][(int) $_REQUEST['request']]) && $context['groups']['available'][(int) $_REQUEST['request']]['type'] == 2)
		$context['group_request'] = $context['groups']['available'][(int) $_REQUEST['request']];

	$context['highlight_primary'] = isset($context['groups']['member'][$context['primary_group']]);
	$context['sub_template'] = 'profile_group_request';
}

/**
 * This function actually makes all the group changes
 *
 * @param array $profile_vars The profile variables
 * @param array $post_errors Any errors that have occurred
 * @param int $memID The ID of the member
 * @return string What type of change this is - 'primary' if changing the primary group, 'request' if requesting to join a group or 'free' if it's an open group
 */
function groupMembership2($profile_vars, $post_errors, $memID)
{
	global $user_info, $context, $user_profile, $modSettings, $smcFunc;

	// Let's be extra cautious...
	if (!$context['user']['is_owner'] || empty($modSettings['show_group_membership']))
		isAllowedTo('manage_membergroups');
	if (!isset($_REQUEST['gid']) && !isset($_POST['primary']))
		fatal_lang_error('no_access', false);

	checkSession(isset($_GET['gid']) ? 'get' : 'post');

	$old_profile = &$user_profile[$memID];
	$context['can_manage_membergroups'] = allowedTo('manage_membergroups');
	$context['can_manage_protected'] = allowedTo('admin_forum');

	// By default the new primary is the old one.
	$newPrimary = $old_profile['id_group'];
	$addGroups = array_flip(explode(',', $old_profile['additional_groups']));
	$canChangePrimary = $old_profile['id_group'] == 0 ? 1 : 0;
	$changeType = isset($_POST['primary']) ? 'primary' : (isset($_POST['req']) ? 'request' : 'free');

	// One way or another, we have a target group in mind...
	$group_id = isset($_REQUEST['gid']) ? (int) $_REQUEST['gid'] : (int) $_POST['primary'];
	$foundTarget = $changeType == 'primary' && $group_id == 0 ? true : false;

	// Sanity check!!
	if ($group_id == 1)
		isAllowedTo('admin_forum');
	// Protected groups too!
	else
	{
		$request = $smcFunc['db']->query('', '
			SELECT group_type
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT {int:limit}',
			[
				'current_group' => $group_id,
				'limit' => 1,
			]
		);
		list ($is_protected) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		if ($is_protected == 1)
			isAllowedTo('admin_forum');
	}

	// What ever we are doing, we need to determine if changing primary is possible!
	$request = $smcFunc['db']->query('', '
		SELECT id_group, group_type, hidden, group_name
		FROM {db_prefix}membergroups
		WHERE id_group IN ({int:group_list}, {int:current_group})',
		[
			'group_list' => $group_id,
			'current_group' => $old_profile['id_group'],
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// Is this the new group?
		if ($row['id_group'] == $group_id)
		{
			$foundTarget = true;
			$group_name = $row['group_name'];

			// Does the group type match what we're doing - are we trying to request a non-requestable group?
			if ($changeType == 'request' && $row['group_type'] != 2)
				fatal_lang_error('no_access', false);
			// What about leaving a requestable group we are not a member of?
			elseif ($changeType == 'free' && $row['group_type'] == 2 && $old_profile['id_group'] != $row['id_group'] && !isset($addGroups[$row['id_group']]))
				fatal_lang_error('no_access', false);
			elseif ($changeType == 'free' && $row['group_type'] != 3 && $row['group_type'] != 2)
				fatal_lang_error('no_access', false);

			// We can't change the primary group if this is hidden!
			if ($row['hidden'] == 2)
				$canChangePrimary = false;
		}

		// If this is their old primary, can we change it?
		if ($row['id_group'] == $old_profile['id_group'] && ($row['group_type'] > 1 || $context['can_manage_membergroups']) && $canChangePrimary !== false)
			$canChangePrimary = 1;

		// If we are not doing a force primary move, don't do it automatically if current primary is not 0.
		if ($changeType != 'primary' && $old_profile['id_group'] != 0)
			$canChangePrimary = false;

		// If this is the one we are acting on, can we even act?
		if ((!$context['can_manage_protected'] && $row['group_type'] == 1) || (!$context['can_manage_membergroups'] && $row['group_type'] == 0))
			$canChangePrimary = false;
	}
	$smcFunc['db']->free_result($request);

	// Didn't find the target?
	if (!$foundTarget)
		fatal_lang_error('no_access', false);

	// Final security check, don't allow users to promote themselves to admin.
	if ($context['can_manage_membergroups'] && !allowedTo('admin_forum'))
	{
		$request = $smcFunc['db']->query('', '
			SELECT COUNT(permission)
			FROM {db_prefix}permissions
			WHERE id_group = {int:selected_group}
				AND permission = {string:admin_forum}
				AND add_deny = {int:not_denied}',
			[
				'selected_group' => $group_id,
				'not_denied' => 1,
				'admin_forum' => 'admin_forum',
			]
		);
		list ($disallow) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		if ($disallow)
			isAllowedTo('admin_forum');
	}

	// If we're requesting, add the note then return.
	if ($changeType == 'request')
	{
		$request = $smcFunc['db']->query('', '
			SELECT id_member
			FROM {db_prefix}log_group_requests
			WHERE id_member = {int:selected_member}
				AND id_group = {int:selected_group}
				AND status = {int:status_open}',
			[
				'selected_member' => $memID,
				'selected_group' => $group_id,
				'status_open' => 0,
			]
		);
		if ($smcFunc['db']->num_rows($request) != 0)
			fatal_lang_error('profile_error_already_requested_group');
		$smcFunc['db']->free_result($request);

		// Log the request.
		$smcFunc['db']->insert('',
			'{db_prefix}log_group_requests',
			[
				'id_member' => 'int', 'id_group' => 'int', 'time_applied' => 'int', 'reason' => 'string-65534',
				'status' => 'int', 'id_member_acted' => 'int', 'member_name_acted' => 'string', 'time_acted' => 'int', 'act_reason' => 'string',
			],
			[
				$memID, $group_id, time(), $_POST['reason'],
				0, 0, '', 0, '',
			],
			['id_request']
		);

		// Add a background task to handle notifying people of this request
		StoryBB\Task::queue_adhoc('StoryBB\\Task\\Adhoc\\GroupReqNotify', [
			'id_member' => $memID,
			'member_name' => $user_info['name'],
			'id_group' => $group_id,
			'group_name' => $group_name,
			'reason' => $_POST['reason'],
			'time' => time(),
		]);

		return $changeType;
	}
	// Otherwise we are leaving/joining a group.
	elseif ($changeType == 'free')
	{
		// Are we leaving?
		if ($old_profile['id_group'] == $group_id || isset($addGroups[$group_id]))
		{
			if ($old_profile['id_group'] == $group_id)
				$newPrimary = 0;
			else
				unset($addGroups[$group_id]);
		}
		// ... if not, must be joining.
		else
		{
			// Can we change the primary, and do we want to?
			if ($canChangePrimary)
			{
				if ($old_profile['id_group'] != 0)
					$addGroups[$old_profile['id_group']] = -1;
				$newPrimary = $group_id;
			}
			// Otherwise it's an additional group...
			else
				$addGroups[$group_id] = -1;
		}
	}
	// Finally, we must be setting the primary.
	elseif ($canChangePrimary)
	{
		if ($old_profile['id_group'] != 0)
			$addGroups[$old_profile['id_group']] = -1;
		if (isset($addGroups[$group_id]))
			unset($addGroups[$group_id]);
		$newPrimary = $group_id;
	}

	// Finally, we can make the changes!
	foreach (array_keys($addGroups) as $id)
		if (empty($id))
			unset($addGroups[$id]);
	$addGroups = implode(',', array_flip($addGroups));

	// Ensure that we don't cache permissions if the group is changing.
	if ($context['user']['is_owner'])
		$_SESSION['mc']['time'] = 0;
	else
		updateSettings(['settings_updated' => time()]);

	updateMemberData($memID, ['id_group' => $newPrimary, 'additional_groups' => $addGroups]);

	return $changeType;
}
