<?php
use LightnCandy\LightnCandy;
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Before showing users a registration form, show them the registration agreement.
 */
function template_registration_agreement()
{
	global $context, $scripturl, $txt;
	
	$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl
	);
	
	$template = file_get_contents(__DIR__ .  "/templates/register_agreement.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'helpers' => Array(
	    )
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

function gen_tabIndexes($start){
	$i = 0;
	while(true) {
		echo "DEBUG: " . $i;
        yield $i++; 
	}
}

/**
 * Before registering - get their information.
 */
function template_registration_form()
{
	global $context, $scripturl, $txt, $modSettings;
	
	//Preprocessing: sometimes we're given eval strings to make options for custom fields.
	//WE REALLY SHOULDN'T DO THIS.
	//But for now:
	foreach ($context['profile_fields'] as $key => $field) {
		if ($field['type'] == 'select' && !is_array($field['options'])) {
			$field['options'] = eval($field['options']);
		}
	}
		
	$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'modSettings' => $modSettings,
		'verification_visual' => Array(
			'verify_context' => $context['controls']['verification'][0],
			'verify_id' => 0,
			'txt' => $txt,
			'hinput_name' => $_SESSION[$verify_id . '_vv']['empty_field'],
			'quick_reply' => false
		)
	);
	
	$template = file_get_contents(__DIR__ .  "/templates/register_form.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
	    'partials' => Array(
	    	'visual_verification_control' => file_get_contents(__DIR__ .  "/partials/visual_verification_control.hbs")
	    ),
	    'helpers' => Array(
	    	'or' => logichelper_or,
	    	'and' => logichelper_and,
	    	'eq' => logichelper_eq,
	    	'not' => logichelper_not,
	    	'profile_callback_helper' => function ($field) {
	            if ($field['type'] == 'callback')
				{
					if (isset($field['callback_func']) && function_exists('template_profile_' . $field['callback_func']))
					{
						$callback_func = 'template_profile_' . $field['callback_func'];
						$callback_func();
					}
				}
	        },
	        'field_isText' => function($type) {
	        	return in_array($type, array('int', 'float', 'text', 'password'));
	        },
	        'template_control_verification' => template_control_verification
	    )
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * After registration... all done ;).
 */
function template_after()
{
	global $context;

	// Not much to see here, just a quick... "you're now registered!" or what have you.
	echo '
		<div id="registration_success">
			<div class="cat_bar">
				<h3 class="catbg">', $context['title'], '</h3>
			</div>
			<div class="windowbg noup">
				<p>', $context['description'], '</p>
			</div>
		</div>';
}

/**
 * Template for giving instructions about COPPA activation.
 */
function template_coppa()
{
	global $context, $txt, $scripturl;

	// Formulate a nice complicated message!
	echo '
			<div class="title_bar title_top">
				<h3 class="titlebg">', $context['page_title'], '</h3>
			</div>
			<div id="coppa" class="roundframe noup">
				<p>', $context['coppa']['body'], '</p>
				<p>
					<span><a href="', $scripturl, '?action=coppa;form;member=', $context['coppa']['id'], '" target="_blank" class="new_win">', $txt['coppa_form_link_popup'], '</a> | <a href="', $scripturl, '?action=coppa;form;dl;member=', $context['coppa']['id'], '">', $txt['coppa_form_link_download'], '</a></span>
				</p>
				<p>', $context['coppa']['many_options'] ? $txt['coppa_send_to_two_options'] : $txt['coppa_send_to_one_option'], '</p>';

	// Can they send by post?
	if (!empty($context['coppa']['post']))
	{
		echo '
				<h4>1) ', $txt['coppa_send_by_post'], '</h4>
				<div class="coppa_contact">
					', $context['coppa']['post'], '
				</div>';
	}

	// Can they send by fax??
	if (!empty($context['coppa']['fax']))
	{
		echo '
				<h4>', !empty($context['coppa']['post']) ? '2' : '1', ') ', $txt['coppa_send_by_fax'], '</h4>
				<div class="coppa_contact">
					', $context['coppa']['fax'], '
				</div>';
	}

	// Offer an alternative Phone Number?
	if ($context['coppa']['phone'])
	{
		echo '
				<p>', $context['coppa']['phone'], '</p>';
	}
	echo '
			</div>';
}

/**
 * An easily printable form for giving permission to access the forum for a minor.
 */
function template_coppa_form()
{
	global $context, $txt;

	// Show the form (As best we can)
	echo '
		<table style="width: 100%; padding: 3px; border: 0" class="tborder">
			<tr>
				<td>', $context['forum_contacts'], '</td>
			</tr><tr>
				<td class="righttext">
					<em>', $txt['coppa_form_address'], '</em>: ', $context['ul'], '<br>
					', $context['ul'], '<br>
					', $context['ul'], '<br>
					', $context['ul'], '
				</td>
			</tr><tr>
				<td class="righttext">
					<em>', $txt['coppa_form_date'], '</em>: ', $context['ul'], '
					<br><br>
				</td>
			</tr><tr>
				<td>
					', $context['coppa_body'], '
				</td>
			</tr>
		</table>
		<br>';
}

/**
 * Show a window containing the spoken verification code.
 */
function template_verification_sound()
{
	global $context, $settings, $txt, $modSettings;

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="UTF-8">
		<title>', $txt['visual_verification_sound'], '</title>
		<meta name="robots" content="noindex">
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css', $modSettings['browser_cache'], '">
		<style>';

	// Just show the help text and a "close window" link.
	echo '
		</style>
	</head>
	<body style="margin: 1ex;">
		<div class="windowbg description" style="text-align: center;">';
	if (isBrowser('is_ie') || isBrowser('is_ie11'))
		echo '
			<object classid="clsid:22D6F312-B0F6-11D0-94AB-0080C74C7E95" type="audio/x-wav">
				<param name="AutoStart" value="1">
				<param name="FileName" value="', $context['verification_sound_href'], '">
			</object>';
	else
		echo '
			<audio src="', $context['verification_sound_href'], '" controls>
				<object type="audio/x-wav" data="', $context['verification_sound_href'], '">
					<a href="', $context['verification_sound_href'], '" rel="nofollow">', $context['verification_sound_href'], '</a>
				</object>
			</audio>';
	echo '
		<br>
		<a href="', $context['verification_sound_href'], ';sound" rel="nofollow">', $txt['visual_verification_sound_again'], '</a><br>
		<a href="', $context['verification_sound_href'], '" rel="nofollow">', $txt['visual_verification_sound_direct'], '</a><br><br>
		<a href="javascript:self.close();">', $txt['visual_verification_sound_close'], '</a><br>
		</div>
	</body>
</html>';
}

/**
 * The template for the form allowing an admin to register a user from the admin center.
 */
function template_admin_register()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<div id="admin_form_wrapper">
			<form id="postForm" action="', $scripturl, '?action=admin;area=regcenter" method="post" accept-charset="UTF-8" name="postForm">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['admin_browse_register_new'], '</h3>
				</div>
				<div id="register_screen" class="windowbg2 noup">';

	if (!empty($context['registration_done']))
		echo '
					<div class="infobox">
						', $context['registration_done'], '
					</div>';

	echo '
					<dl class="register_form" id="admin_register_form">
						<dt>
							<strong><label for="user_input">', $txt['admin_register_username'], ':</label></strong>
							<span class="smalltext">', $txt['admin_register_username_desc'], '</span>
						</dt>
						<dd>
							<input type="text" name="user" id="user_input" tabindex="', $context['tabindex']++, '" size="30" maxlength="25" class="input_text">
						</dd>
						<dt>
							<strong><label for="email_input">', $txt['admin_register_email'], ':</label></strong>
							<span class="smalltext">', $txt['admin_register_email_desc'], '</span>
						</dt>
						<dd>
							<input type="text" name="email" id="email_input" tabindex="', $context['tabindex']++, '" size="30" class="input_text">
						</dd>
						<dt>
							<strong><label for="password_input">', $txt['admin_register_password'], ':</label></strong>
							<span class="smalltext">', $txt['admin_register_password_desc'], '</span>
						</dt>
						<dd>
							<input type="password" name="password" id="password_input" tabindex="', $context['tabindex']++, '" size="30" class="input_password" onchange="onCheckChange();">
						</dd>';

	if (!empty($context['member_groups']))
	{
		echo '
						<dt>
							<strong><label for="group_select">', $txt['admin_register_group'], ':</label></strong>
							<span class="smalltext">', $txt['admin_register_group_desc'], '</span>
						</dt>
						<dd>
							<select name="group" id="group_select" tabindex="', $context['tabindex']++, '">';

		foreach ($context['member_groups'] as $id => $name)
			echo '
								<option value="', $id, '">', $name, '</option>';

		echo '
							</select>
						</dd>';
	}

	// If there is any field marked as required, show it here!
	if (!empty($context['custom_fields_required']) && !empty($context['custom_fields']))
		foreach ($context['custom_fields'] as $field)
			if ($field['show_reg'] > 1)
				echo '
						<dt>
							<strong', !empty($field['is_error']) ? ' class="red"' : '', '>', $field['name'], ':</strong>
							<span class="smalltext">', $field['desc'], '</span>
						</dt>
						<dd>', str_replace('name="', 'tabindex="' . $context['tabindex']++ . '" name="', $field['input_html']), '</dd>';

	echo '
						<dt>
							<strong><label for="emailPassword_check">', $txt['admin_register_email_detail'], ':</label></strong>
							<span class="smalltext">', $txt['admin_register_email_detail_desc'], '</span>
						</dt>
						<dd>
							<input type="checkbox" name="emailPassword" id="emailPassword_check" tabindex="', $context['tabindex']++, '" checked disabled class="input_check">
						</dd>
						<dt>
							<strong><label for="emailActivate_check">', $txt['admin_register_email_activate'], ':</label></strong>
						</dt>
						<dd>
							<input type="checkbox" name="emailActivate" id="emailActivate_check" tabindex="', $context['tabindex']++, '"', !empty($modSettings['registration_method']) && $modSettings['registration_method'] == 1 ? ' checked' : '', ' onclick="onCheckChange();" class="input_check">
						</dd>
					</dl>
					<div class="flow_auto">
						<input type="submit" name="regSubmit" value="', $txt['register'], '" tabindex="', $context['tabindex']++, '" class="button_submit">
						<input type="hidden" name="sa" value="register">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						<input type="hidden" name="', $context['admin-regc_token_var'], '" value="', $context['admin-regc_token'], '">
					</div>
				</div>
			</form>
		</div>
	</div>
	<br class="clear">';
}

/**
 * Form for editing the agreement shown for people registering to the forum.
 */
function template_edit_agreement()
{
	global $context, $scripturl, $txt;

	if (!empty($context['saved_successful']))
		echo '
					<div class="infobox">', $txt['settings_saved'], '</div>';
	elseif (!empty($context['could_not_save']))
		echo '
					<div class="errorbox">', $txt['admin_agreement_not_saved'], '</div>';

	// Just a big box to edit the text file ;).
	echo '
		<div id="admin_form_wrapper">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['registration_agreement'], '</h3>
			</div>';

	// Warning for if the file isn't writable.
	if (!empty($context['warning']))
		echo '
			<p class="error">', $context['warning'], '</p>';

	echo '
			<div class="windowbg2 noup" id="registration_agreement">';

	// Is there more than one language to choose from?
	if (count($context['editable_agreements']) > 1)
	{
		echo '
				<div class="cat_bar">
					<h3 class="catbg">', $txt['language_configuration'], '</h3>
				</div>
				<div class="information">
					<form action="', $scripturl, '?action=admin;area=regcenter" id="change_reg" method="post" accept-charset="UTF-8" style="display: inline;">
						<strong>', $txt['admin_agreement_select_language'], ':</strong>&nbsp;
						<select name="agree_lang" onchange="document.getElementById(\'change_reg\').submit();" tabindex="', $context['tabindex']++, '">';

		foreach ($context['editable_agreements'] as $file => $name)
			echo '
							<option value="', $file, '"', $context['current_agreement'] == $file ? ' selected' : '', '>', $name, '</option>';

		echo '
						</select>
						<div class="righttext">
							<input type="hidden" name="sa" value="agreement">
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
							<input type="hidden" name="', $context['admin-rega_token_var'], '" value="', $context['admin-rega_token'], '">
							<input type="submit" name="change" value="', $txt['admin_agreement_select_language_change'], '" tabindex="', $context['tabindex']++, '" class="button_submit">
						</div>
					</form>
				</div>';
	}



	// Show the actual agreement in an oversized text box.
	echo '
				<form action="', $scripturl, '?action=admin;area=regcenter" method="post" accept-charset="UTF-8">
					<textarea cols="70" rows="20" name="agreement" id="agreement">', $context['agreement'], '</textarea>
					<p>
						<label for="requireAgreement"><input type="checkbox" name="requireAgreement" id="requireAgreement"', $context['require_agreement'] ? ' checked' : '', ' tabindex="', $context['tabindex']++, '" value="1" class="input_check"> ', $txt['admin_agreement'], '.</label>
					</p>
					<input type="submit" value="', $txt['save'], '" tabindex="', $context['tabindex']++, '" class="button_submit">
					<input type="hidden" name="agree_lang" value="', $context['current_agreement'], '">
					<input type="hidden" name="sa" value="agreement">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="', $context['admin-rega_token_var'], '" value="', $context['admin-rega_token'], '">
				</form>
			</div>
		</div>';
}

/**
 * Template for editing reserved words.
 */
function template_edit_reserved_words()
{
	global $context, $scripturl, $txt;

	if (!empty($context['saved_successful']))
		echo '
	<div class="infobox">', $txt['settings_saved'], '</div>';

	echo '
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=regcenter" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['admin_reserved_set'], '</h3>
			</div>
			<div class="windowbg2 noup">
				<h4>', $txt['admin_reserved_line'], '</h4>
				<textarea cols="30" rows="6" name="reserved" id="reserved">', implode("\n", $context['reserved_words']), '</textarea>
				<dl class="settings">
					<dt>
						<label for="matchword">', $txt['admin_match_whole'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="matchword" id="matchword" tabindex="', $context['tabindex']++, '"', $context['reserved_word_options']['match_word'] ? ' checked' : '', ' class="input_check">
					</dd>
					<dt>
						<label for="matchcase">', $txt['admin_match_case'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="matchcase" id="matchcase" tabindex="', $context['tabindex']++, '"', $context['reserved_word_options']['match_case'] ? ' checked' : '', ' class="input_check">
					</dd>
					<dt>
						<label for="matchuser">', $txt['admin_check_user'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="matchuser" id="matchuser" tabindex="', $context['tabindex']++, '"', $context['reserved_word_options']['match_user'] ? ' checked' : '', ' class="input_check">
					</dd>
					<dt>
						<label for="matchname">', $txt['admin_check_display'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="matchname" id="matchname" tabindex="', $context['tabindex']++, '"', $context['reserved_word_options']['match_name'] ? ' checked' : '', ' class="input_check">
					</dd>
				</dl>
				<div class="flow_auto">
					<input type="submit" value="', $txt['save'], '" name="save_reserved_names" tabindex="', $context['tabindex']++, '" style="margin: 1ex;" class="button_submit">
					<input type="hidden" name="sa" value="reservednames">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="', $context['admin-regr_token_var'], '" value="', $context['admin-regr_token'], '">
				</div>
			</div>
		</form>';
}

?>