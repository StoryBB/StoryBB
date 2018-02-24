<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

use LightnCandy\LightnCandy;

/**
 * Minor stuff shown above the main profile - mostly used for error messages and showing that the profile update was successful.
 */
function template_profile_above()
{
	global $context;

	// Prevent Chrome from auto completing fields when viewing/editing other members profiles
	if (isBrowser('is_chrome') && !$context['user']['is_owner'])
		echo '
	<script>
		disableAutoComplete();
	</script>';

	// If an error occurred while trying to save previously, give the user a clue!
	echo '
					', template_error_message();

	// If the profile was update successfully, let the user know this.
	if (!empty($context['profile_updated']))
		echo '
					<div class="infobox">
						', $context['profile_updated'], '
					</div>';
}

/**
 * Template for any HTML needed below the profile (closing off divs/tables, etc.)
 */
function template_profile_below()
{
}

/**
 * Template for showing the ignore list of the current user.
 */
function template_editIgnoreList()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="edit_buddies">
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				', $txt['editIgnoreList'], '
			</h3>
		</div>
		<table class="table_grid">
			<tr class="title_bar">
				<th scope="col" class="quarter_table">', $txt['name'], '</th>
				<th scope="col">', $txt['status'], '</th>';

	if (allowedTo('moderate_forum'))
		echo '
				<th scope="col">', $txt['email'], '</th>';

	echo '
				<th scope="col">', $txt['ignore_remove'], '</th>
			</tr>';

	// If they don't have anyone on their ignore list, don't list it!
	if (empty($context['ignore_list']))
		echo '
			<tr class="windowbg">
				<td colspan="', allowedTo('moderate_forum') ? '4' : '3', '"><strong>', $txt['no_ignore'], '</strong></td>
			</tr>';

	// Now loop through each buddy showing info on each.
	foreach ($context['ignore_list'] as $member)
	{
		echo '
			<tr class="windowbg">
				<td>', $member['link'], '</td>
				<td><a href="', $member['online']['href'], '"><span class="' . ($member['online']['is_online'] == 1 ? 'on' : 'off') . '" title="' . $member['online']['text'] . '"></span></a></td>';

		if ($member['show_email'])
			echo '
				<td><a href="mailto:' . $member['email'] . '" rel="nofollow"><span class="generic_icons mail icon" title="' . $txt['email'] . ' ' . $member['name'] . '"></span></a></td>';
		echo '
				<td><a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=lists;sa=ignore;remove=', $member['id'], ';', $context['session_var'], '=', $context['session_id'], '"><span class="generic_icons delete" title="', $txt['ignore_remove'], '"></span></a></td>
			</tr>';
	}

	echo '
		</table>
	</div>';

	// Add to the ignore list?
	echo '
	<form action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=lists;sa=ignore" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['ignore_add'], '</h3>
		</div>
		<div class="information">
			<dl class="settings">
				<dt>
					<label for="new_buddy"><strong>', $txt['who_member'], ':</strong></label>
				</dt>
				<dd>
					<input type="text" name="new_ignore" id="new_ignore" size="25" class="input_text">
				</dd>
			</dl>
		</div>';

	if (!empty($context['token_check']))
		echo '
		<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

	echo '
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<input type="submit" value="', $txt['ignore_add_button'], '" class="button_submit">
	</form>
	<script>
		var oAddIgnoreSuggest = new smc_AutoSuggest({
			sSelf: \'oAddIgnoreSuggest\',
			sSessionId: \'', $context['session_id'], '\',
			sSessionVar: \'', $context['session_var'], '\',
			sSuggestId: \'new_ignore\',
			sControlId: \'new_ignore\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
			bItemList: false
		});
	</script>';
}

/**
 * Template for editing profile options.
 */
function template_edit_options()
{
	global $context, $scripturl, $txt, $modSettings;

	// The main header!
	// because some browsers ignore autocomplete=off and fill username in display name and/ or email field, fake them out.
	$url = !empty($context['profile_custom_submit_url']) ? $context['profile_custom_submit_url'] : $scripturl . '?action=profile;area=' . $context['menu_item_selected'] . ';u=' . $context['id_member'];
	$url = $context['require_password'] && !empty($modSettings['force_ssl']) && $modSettings['force_ssl'] < 2 ? strtr($url, array('http://' => 'https://')) : $url;

	echo '
		<form action="', $url, '" method="post" accept-charset="UTF-8" name="creator" id="creator" enctype="multipart/form-data"', ($context['menu_item_selected'] == 'account' ? ' autocomplete="off"' : ''), '>
			<div style="position:absolute; top:-100px;"><input type="text" id="autocompleteFakeName"/><input type="password" id="autocompleteFakePassword"/></div>
			<div class="cat_bar">
				<h3 class="catbg profile_hd">';

		// Don't say "Profile" if this isn't the profile...
		if (!empty($context['profile_header_text']))
			echo '
					', $context['profile_header_text'];
		else
			echo '
					', $txt['profile'];

		echo '
				</h3>
			</div>';

	// Have we some description?
	if ($context['page_desc'])
		echo '
			<p class="information">', $context['page_desc'], '</p>';

	echo '
			<div class="roundframe">';

	// Any bits at the start?
	if (!empty($context['profile_prehtml']))
		echo '
				<div>', $context['profile_prehtml'], '</div>';

	if (!empty($context['profile_fields']))
		echo '
				<dl class="settings">';

	// Start the big old loop 'of love.
	$lastItem = 'hr';
	foreach ($context['profile_fields'] as $key => $field)
	{
		// We add a little hack to be sure we never get more than one hr in a row!
		if ($lastItem == 'hr' && $field['type'] == 'hr')
			continue;

		$lastItem = $field['type'];
		if ($field['type'] == 'hr')
		{
			echo '
				</dl>
				<hr>
				<dl class="settings">';
		}
		elseif ($field['type'] == 'callback')
		{
			if (isset($field['callback_func']) && function_exists('template_profile_' . $field['callback_func']))
			{
				$callback_func = 'template_profile_' . $field['callback_func'];
				$callback_func();
			}
		}
		else
		{
			echo '
					<dt>
						<strong', !empty($field['is_error']) ? ' class="error"' : '', '>', $field['type'] !== 'label' ? '<label for="' . $key . '">' : '', $field['label'], $field['type'] !== 'label' ? '</label>' : '', '</strong>';

			// Does it have any subtext to show?
			if (!empty($field['subtext']))
				echo '
						<br>
						<span class="smalltext">', $field['subtext'], '</span>';

			echo '
					</dt>
					<dd>';

			// Want to put something infront of the box?
			if (!empty($field['preinput']))
				echo '
						', $field['preinput'];

			// What type of data are we showing?
			if ($field['type'] == 'label')
				echo '
						', $field['value'];

			// Maybe it's a text box - very likely!
			elseif (in_array($field['type'], array('int', 'float', 'text', 'password', 'color', 'date', 'datetime', 'datetime-local', 'email', 'month', 'number', 'time', 'url')))
			{
				if ($field['type'] == 'int' || $field['type'] == 'float')
					$type = 'number';
				else
					$type = $field['type'];
				$step = $field['type'] == 'float' ? ' step="0.1"' : '';


				echo '
						<input type="', $type, '" name="', $key, '" id="', $key, '" size="', empty($field['size']) ? 30 : $field['size'], '" value="', $field['value'], '" ', $field['input_attr'], ' class="input_', $field['type'] == 'password' ? 'password' : 'text', '"', $step, '>';
			}
			// You "checking" me out? ;)
			elseif ($field['type'] == 'check')
				echo '
						<input type="hidden" name="', $key, '" value="0"><input type="checkbox" name="', $key, '" id="', $key, '"', !empty($field['value']) ? ' checked' : '', ' value="1" class="input_check" ', $field['input_attr'], '>';

			// Always fun - select boxes!
			elseif ($field['type'] == 'select')
			{
				echo '
						<select name="', $key, '" id="', $key, '">';

				if (isset($field['options']))
				{
					// Is this some code to generate the options?
					if (!is_array($field['options']))
						$field['options'] = $field['options']();
					// Assuming we now have some!
					if (is_array($field['options']))
						foreach ($field['options'] as $value => $name)
							echo '
								<option value="', $value, '"', $value == $field['value'] ? ' selected' : '', '>', $name, '</option>';
				}

				echo '
						</select>';
			}

			// Something to end with?
			if (!empty($field['postinput']))
				echo '
							', $field['postinput'];

			echo '
					</dd>';
		}
	}

	if (!empty($context['profile_fields']))
		echo '
				</dl>';

	// Are there any custom profile fields - if so print them!
	if (!empty($context['custom_fields']))
	{
		if ($lastItem != 'hr')
			echo '
				<hr>';

		echo '
				<dl class="settings">';

		foreach ($context['custom_fields'] as $field)
		{
			echo '
					<dt>
						<strong>', $field['name'], ': </strong><br>
						<span class="smalltext">', $field['desc'], '</span>
					</dt>
					<dd>
						', $field['input_html'], '
					</dd>';
		}

		echo '
					</dl>';

	}

	// Any closing HTML?
	if (!empty($context['profile_posthtml']))
		echo '
				<div>', $context['profile_posthtml'], '</div>';

	// Only show the password box if it's actually needed.
	if ($context['require_password'])
		echo '
				<dl class="settings">
					<dt>
						<strong', isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : '', '><label for="oldpasswrd">', $txt['current_password'], ': </label></strong><br>
						<span class="smalltext">', $txt['required_security_reasons'], '</span>
					</dt>
					<dd>
						<input type="password" name="oldpasswrd" id="oldpasswrd" size="20" style="margin-right: 4ex;" class="input_password">
					</dd>
				</dl>';

	// The button shouldn't say "Change profile" unless we're changing the profile...
	if (!empty($context['submit_button_text']))
		echo '
				<input type="submit" name="save" value="', $context['submit_button_text'], '" class="button_submit">';
	else
		echo '
				<input type="submit" name="save" value="', $txt['change_profile'], '" class="button_submit">';

	if (!empty($context['token_check']))
		echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="u" value="', $context['id_member'], '">
				<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
			</div>
		</form>';
}

/**
 * Personal Message settings.
 */
function template_profile_pm_settings()
{
	global $context, $modSettings, $txt;

	echo '
								<dt>
									<label for="view_newest_pm_first">', $txt['recent_pms_at_top'], '</label>
								</dt>
								<dd>
										<input type="hidden" name="default_options[view_newest_pm_first]" value="0">
										<input type="checkbox" name="default_options[view_newest_pm_first]" id="view_newest_pm_first" value="1"', !empty($context['member']['options']['view_newest_pm_first']) ? ' checked' : '', ' class="input_check">
								</dd>
						</dl>
						<hr>
						<dl class="settings">
								<dt>
										<label for="pm_receive_from">', $txt['pm_receive_from'], '</label>
								</dt>
								<dd>
										<select name="pm_receive_from" id="pm_receive_from">
												<option value="0"', empty($context['receive_from']) || (empty($modSettings['enable_buddylist']) && $context['receive_from'] < 3) ? ' selected' : '', '>', $txt['pm_receive_from_everyone'], '</option>';

	if (!empty($modSettings['enable_buddylist']))
		echo '
												<option value="1"', !empty($context['receive_from']) && $context['receive_from'] == 1 ? ' selected' : '', '>', $txt['pm_receive_from_ignore'], '</option>
												<option value="2"', !empty($context['receive_from']) && $context['receive_from'] == 2 ? ' selected' : '', '>', $txt['pm_receive_from_buddies'], '</option>';

	echo '
												<option value="3"', !empty($context['receive_from']) && $context['receive_from'] > 2 ? ' selected' : '', '>', $txt['pm_receive_from_admins'], '</option>
										</select>
								</dd>
						</dl>
						<hr>
						<dl class="settings">
								<dt>
										<label for="pm_remove_inbox_label">', $txt['pm_remove_inbox_label'], '</label>
								</dt>
								<dd>
										<input type="hidden" name="default_options[pm_remove_inbox_label]" value="0">
										<input type="checkbox" name="default_options[pm_remove_inbox_label]" id="pm_remove_inbox_label" value="1"', !empty($context['member']['options']['pm_remove_inbox_label']) ? ' checked' : '', ' class="input_check">
								</dd>';

}

/**
 * Template for showing theme settings. Note: template_options() actually adds the theme specific options.
 */
function template_profile_theme_settings()
{
	global $context, $modSettings, $txt;

	$skeys = array_keys($context['theme_options']);
	$first_option_key = array_shift($skeys);
	$titled_section = false;

	foreach ($context['theme_options'] as $i => $setting)
	{
		// Just spit out separators and move on
		if (empty($setting) || !is_array($setting))
		{
			// Insert a separator (unless this is the first item in the list)
			if ($i !== $first_option_key)
				echo '
				</dl>
				<hr>
				<dl class="settings">';

			// Should we give a name to this section?
			if (is_string($setting) && !empty($setting))
			{
				$titled_section = true;
				echo '
					<dt><b>' . $setting . '</b></dt><dd></dd>';
			}
			else
				$titled_section = false;

			continue;
		}

		// Is this disabled?
		if (($setting['id'] == 'topics_per_page' || $setting['id'] == 'messages_per_page') && !empty($modSettings['disableCustomPerPage']))
			continue;
		elseif ($setting['id'] == 'show_no_censored' && empty($modSettings['allow_no_censored']))
			continue;
		elseif ($setting['id'] == 'posts_apply_ignore_list' && empty($modSettings['enable_buddylist']))
			continue;
		elseif ($setting['id'] == 'wysiwyg_default' && !empty($modSettings['disable_wysiwyg']))
			continue;
		elseif ($setting['id'] == 'topics_per_page' && !empty($modSettings['disableCustomPerPage']))
			continue;
		elseif ($setting['id'] == 'drafts_autosave_enabled' && (empty($modSettings['drafts_autosave_enabled']) || (empty($modSettings['drafts_post_enabled']) && empty($modSettings['drafts_pm_enabled']))))
			continue;
		elseif ($setting['id'] == 'drafts_show_saved_enabled' && (empty($modSettings['drafts_show_saved_enabled']) || (empty($modSettings['drafts_post_enabled']) && empty($modSettings['drafts_pm_enabled']))))
			continue;

		if (!isset($setting['type']) || $setting['type'] == 'bool')
			$setting['type'] = 'checkbox';
		elseif ($setting['type'] == 'int' || $setting['type'] == 'integer')
			$setting['type'] = 'number';
		elseif ($setting['type'] == 'string')
			$setting['type'] = 'text';

		if (isset($setting['options']))
			$setting['type'] = 'list';

		echo '
					<dt>
						<label for="', $setting['id'], '">', !$titled_section ? '<b>' : '', $setting['label'], !$titled_section ? '</b>' : '', '</label>';

		if (isset($setting['description']))
			echo '
						<br><span class="smalltext">', $setting['description'], '</span>';
		echo '
					</dt>
					<dd>';

		// display checkbox options
		if ($setting['type'] == 'checkbox')
		{
			echo '
						<input type="hidden" name="default_options[' . $setting['id'] . ']" value="0">
						<input type="checkbox" name="default_options[', $setting['id'], ']" id="', $setting['id'], '"', !empty($context['member']['options'][$setting['id']]) ? ' checked' : '', ' value="1" class="input_check">';
		}
		// how about selection lists, we all love them
		elseif ($setting['type'] == 'list')
		{
			echo '
						&nbsp;<select class="floatleft" name="default_options[', $setting['id'], ']" id="', $setting['id'], '"', '>';

			foreach ($setting['options'] as $value => $label)
			{
				echo '
							<option value="', $value, '"', $value == $context['member']['options'][$setting['id']] ? ' selected' : '', '>', $label, '</option>';
			}

			echo '
						</select>';
		}
		// a textbox it is then
		else
		{
			if (isset($setting['type']) && $setting['type'] == 'number')
			{
				$min = isset($setting['min']) ? ' min="' . $setting['min'] . '"' : ' min="0"';
				$max = isset($setting['max']) ? ' max="' . $setting['max'] . '"' : '';
				$step = isset($setting['step']) ? ' step="' . $setting['step'] . '"' : '';

				echo '
						<input type="number"', $min . $max . $step;
			}
			else if (isset($setting['type']) && $setting['type'] == 'url')
			{
				echo'
						<input type="url"';
			}
			else
			{
				echo '
						<input type="text"';
			}

			echo ' name="default_options[', $setting['id'], ']" id="', $setting['id'], '" value="', isset($context['member']['options'][$setting['id']]) ? $context['member']['options'][$setting['id']] : $setting['value'], '"', $setting['type'] == 'number' ? ' size="5"' : '', ' class="input_text">';
		}

		// end of this defintion
		echo '
					</dd>';
	}
}

/**
 * The template for configuring alerts
 */
function template_alert_configuration()
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', $txt['alert_prefs'], '
			</h3>
		</div>
		<p class="information">', (empty($context['description']) ? $txt['alert_prefs_desc'] : $context['description']), '</p>
		<form action="', $scripturl, '?', $context['action'], '" id="admin_form_wrapper" method="post" accept-charset="UTF-8" id="notify_options" class="flow_hidden">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['notification_general'], '
				</h3>
			</div>
			<div class="windowbg2 noup">
				<dl class="settings">';

	// Allow notification on announcements to be disabled?
	if (!empty($modSettings['allow_disableAnnounce']))
		echo '
					<dt>
						<label for="notify_announcements">', $txt['notify_important_email'], '</label>
					</dt>
					<dd>
						<input type="hidden" name="notify_announcements" value="0">
						<input type="checkbox" id="notify_announcements" name="notify_announcements" value="1"', !empty($context['member']['notify_announcements']) ? ' checked' : '', ' class="input_check">
					</dd>';

	if (!empty($modSettings['enable_ajax_alerts']))
		echo '
					<dt>
						<label for="notify_send_body">', $txt['notify_alert_timeout'], '</label>
					</dt>
					<dd>
						<input type="number" size="4" id="notify_alert_timeout" name="opt_alert_timeout" min="0" value="', $context['member']['alert_timeout'], '" class="input_text">
					</dd>
		';

	echo '
				</dl>
			</div>
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['notify_what_how'], '
				</h3>
			</div>
			<table class="table_grid">';

	foreach ($context['alert_types'] as $alert_group => $alerts)
	{
		echo '
				<tr class="title_bar">
					<th>', $txt['alert_group_' . $alert_group], '</th>
					<th>', $txt['receive_alert'], '</th>
					<th>', $txt['receive_mail'], '</th>
				</tr>
				<tr class="windowbg">';
		if (isset($context['alert_group_options'][$alert_group]))
		{
			foreach ($context['alert_group_options'][$alert_group] as $opts)
			{
				echo '
				<tr class="windowbg">
					<td colspan="3">';
				$label = $txt['alert_opt_' . $opts[1]];
				$label_pos = isset($opts['label']) ? $opts['label'] : '';
				if ($label_pos == 'before')
					echo '
					<label for="opt_', $opts[1], '">', $label, '</label>';

				$this_value = isset($context['alert_prefs'][$opts[1]]) ? $context['alert_prefs'][$opts[1]] : 0;
				switch ($opts[0])
				{
					case 'check':
						echo '
						<input type="checkbox" name="opt_', $opts[1], '" id="opt_', $opts[1], '"', $this_value ? ' checked' : '', '>';
						break;
					case 'select':
						echo '
						<select name="opt_', $opts[1], '" id="opt_', $opts[1], '">';
						foreach ($opts['opts'] as $k => $v)
							echo '
							<option value="', $k, '"', $this_value == $k ? ' selected' : '', '>', $v, '</option>';
						echo '
						</select>';
						break;
				}

				if ($label_pos == 'after')
					echo '
					<label for="opt_', $opts[1], '">', $label, '</label>';

				echo '
					</td>
				</tr>';
			}
		}

		foreach ($alerts as $alert_id => $alert_details)
		{
			echo '
				<tr class="windowbg">
					<td>', $txt['alert_' . $alert_id], isset($alert_details['help']) ? '<a href="' . $scripturl . '?action=helpadmin;help=' . $alert_details['help'] . '" onclick="return reqOverlayDiv(this.href);" class="help floatright"><span class="generic_icons help" title="' . $txt['help'] . '"></span></a>' : '', '</td>';

			foreach ($context['alert_bits'] as $type => $bitmask)
			{
				echo '
					<td class="centercol">';
				$this_value = isset($context['alert_prefs'][$alert_id]) ? $context['alert_prefs'][$alert_id] : 0;
				switch ($alert_details[$type])
				{
					case 'always':
						echo '
						<input type="checkbox" checked disabled>';
						break;
					case 'yes':
						echo '
						<input type="checkbox" name="', $type, '_', $alert_id, '"', ($this_value & $bitmask) ? ' checked' : '', '>';
						break;
					case 'never':
						echo '
						<input type="checkbox" disabled>';
						break;
				}
				echo '
					</td>';
			}

			echo '
				</tr>';
		}
	}

	echo '
			</table>
			<br>
			<div>
				<input id="notify_submit" type="submit" name="notify_submit" value="', $txt['notify_save'], '" class="button_submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">', !empty($context['token_check']) ? '
				<input type="hidden" name="' . $context[$context['token_check'] . '_token_var'] . '" value="' . $context[$context['token_check'] . '_token'] . '">' : '', '
				<input type="hidden" name="u" value="', $context['id_member'], '">
				<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
			</div>
		</form>
		<br>';
}

/**
 * Template for choosing group membership.
 */
function template_groupMembership()
{
	global $context, $scripturl, $txt;

	// The main containing header.
	echo '
		<form action="', $scripturl, '?action=profile;area=groupmembership;save" method="post" accept-charset="UTF-8" name="creator" id="creator">
			<div class="cat_bar">
				<h3 class="catbg profile_hd">
					', $txt['profile'], '
				</h3>
			</div>
			<p class="information">', $txt['groupMembership_info'], '</p>';

	// Do we have an update message?
	if (!empty($context['update_message']))
		echo '
			<div class="infobox">
				', $context['update_message'], '.
			</div>';

	echo '
		<div id="groups">';

	// Requesting membership to a group?
	if (!empty($context['group_request']))
	{
		echo '
			<div class="groupmembership">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['request_group_membership'], '</h3>
				</div>
				<div class="roundframe">
					', $txt['request_group_membership_desc'], ':
					<textarea name="reason" rows="4" style="width: 99%;"></textarea>
					<div class="righttext" style="margin: 0.5em 0.5% 0 0.5%;">
						<input type="hidden" name="gid" value="', $context['group_request']['id'], '">
						<input type="submit" name="req" value="', $txt['submit_request'], '" class="button_submit">
					</div>
				</div>
			</div>';
	}
	else
	{
		echo '
			<div class="title_bar">
				<h3 class="titlebg">', $txt['current_membergroups'], '</h3>
			</div>';

		foreach ($context['groups']['member'] as $group)
		{
			echo '
					<div class="windowbg" id="primdiv_', $group['id'], '">';

				if ($context['can_edit_primary'])
					echo '
						<input type="radio" name="primary" id="primary_', $group['id'], '" value="', $group['id'], '"', $group['is_primary'] ? ' checked' : '', ' onclick="highlightSelected(\'primdiv_' . $group['id'] . '\');"', $group['can_be_primary'] ? '' : ' disabled', ' class="input_radio">';

				echo '
						<label for="primary_', $group['id'], '"><strong>', (empty($group['color']) ? $group['name'] : '<span style="color: ' . $group['color'] . '">' . $group['name'] . '</span>'), '</strong>', (!empty($group['desc']) ? '<br><span class="smalltext">' . $group['desc'] . '</span>' : ''), '</label>';

				// Can they leave their group?
				if ($group['can_leave'])
					echo '
						<a href="' . $scripturl . '?action=profile;save;u=' . $context['id_member'] . ';area=groupmembership;' . $context['session_var'] . '=' . $context['session_id'] . ';gid=' . $group['id'] . ';', $context[$context['token_check'] . '_token_var'], '=', $context[$context['token_check'] . '_token'], '">' . $txt['leave_group'] . '</a>';

				echo '
					</div>';
		}

		if ($context['can_edit_primary'])
			echo '
			<div class="padding righttext">
				<input type="submit" value="', $txt['make_primary'], '" class="button_submit">
			</div>';

		// Any groups they can join?
		if (!empty($context['groups']['available']))
		{
			echo '
					<div class="title_bar">
						<h3 class="titlebg">', $txt['available_groups'], '</h3>
					</div>';

			foreach ($context['groups']['available'] as $group)
			{
				echo '
					<div class="windowbg">
						<strong>', (empty($group['color']) ? $group['name'] : '<span style="color: ' . $group['color'] . '">' . $group['name'] . '</span>'), '</strong>', (!empty($group['desc']) ? '<br><span class="smalltext">' . $group['desc'] . '</span>' : ''), '';

				if ($group['type'] == 3)
					echo '
						<a href="', $scripturl, '?action=profile;save;u=', $context['id_member'], ';area=groupmembership;', $context['session_var'], '=', $context['session_id'], ';gid=', $group['id'], ';', $context[$context['token_check'] . '_token_var'], '=', $context[$context['token_check'] . '_token'], '" class="button floatright">', $txt['join_group'], '</a>';
				elseif ($group['type'] == 2 && $group['pending'])
					echo '
						<span class="floatright">', $txt['approval_pending'], '</span>';
				elseif ($group['type'] == 2)
					echo '
						<a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=groupmembership;request=', $group['id'], '" class="button floatright">', $txt['request_group'], '</a>';

				echo '
					</div>';
			}
		}

		// Javascript for the selector stuff.
		echo '
		<script>
		var prevClass = "";
		var prevDiv = "";
		function highlightSelected(box)
		{
			if (prevClass != "")
			{
				prevDiv.className = prevClass;
			}
			prevDiv = document.getElementById(box);
			prevClass = prevDiv.className;

			prevDiv.className = "windowbg";
		}';
		if (isset($context['groups']['member'][$context['primary_group']]))
			echo '
		highlightSelected("primdiv_' . $context['primary_group'] . '");';
		echo '
	</script>';
	}

	echo '
		</div>';

	if (!empty($context['token_check']))
		echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="u" value="', $context['id_member'], '">
			</form>';
}

/**
 * Template for managing ignored boards
 */
function template_ignoreboards()
{
	global $context, $txt, $scripturl;
	// The main containing header.
	echo '
	<form action="', $scripturl, '?action=profile;area=ignoreboards;save" method="post" accept-charset="UTF-8" name="creator" id="creator">
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				', $txt['profile'], '
			</h3>
		</div>
		<p class="information">', $txt['ignoreboards_info'], '</p>
		<div class="windowbg2">
			<div class="flow_hidden">
				<ul class="ignoreboards floatleft">';

	$i = 0;
	$limit = ceil($context['num_boards'] / 2);
	foreach ($context['categories'] as $category)
	{
		if ($i == $limit)
		{
			echo '
				</ul>
				<ul class="ignoreboards floatright">';

			$i++;
		}

		echo '
					<li class="category">
						<a href="javascript:void(0);" onclick="selectBoards([', implode(', ', $category['child_ids']), '], \'creator\'); return false;">', $category['name'], '</a>
						<ul>';

		foreach ($category['boards'] as $board)
		{
			if ($i == $limit)
				echo '
						</ul>
					</li>
				</ul>
				<ul class="ignoreboards floatright">
					<li class="category">
						<ul>';

			echo '
							<li class="board" style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em;">
								<label for="ignore_brd', $board['id'], '"><input type="checkbox" id="brd', $board['id'], '" name="ignore_brd[', $board['id'], ']" value="', $board['id'], '"', $board['selected'] ? ' checked' : '', ' class="input_check"> ', $board['name'], '</label>
							</li>';

			$i++;
		}

		echo '
						</ul>
					</li>';
	}

	echo '
				</ul>';

	// Show the standard "Save Settings" profile button.
	template_profile_save();

	echo '
			</div>
		</div>
	</form>
	<br>';
}

/**
 * Simply loads some theme variables common to several warning templates.
 */
function template_load_warning_variables()
{
	global $modSettings, $context;

	$context['warningBarWidth'] = 200;
	// Setup the colors - this is a little messy for theming.
	$context['colors'] = array(
		0 => 'green',
		$modSettings['warning_watch'] => 'darkgreen',
		$modSettings['warning_moderate'] => 'orange',
		$modSettings['warning_mute'] => 'red',
	);

	// Work out the starting color.
	$context['current_color'] = $context['colors'][0];
	foreach ($context['colors'] as $limit => $color)
		if ($context['member']['warning'] >= $limit)
			$context['current_color'] = $color;
}

// Show all warnings of a user?
function template_viewWarning()
{
	global $context, $txt;

	template_load_warning_variables();

	echo '
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				', sprintf($txt['profile_viewwarning_for_user'], $context['member']['name']), '
			</h3>
		</div>
		<p class="information">', $txt['viewWarning_help'], '</p>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<strong>', $txt['profile_warning_name'], ':</strong>
				</dt>
				<dd>
					', $context['member']['name'], '
				</dd>
				<dt>
					<strong>', $txt['profile_warning_level'], ':</strong>
				</dt>
				<dd>
					<div>
						<div>
							<div style="font-size: 8pt; height: 12pt; width: ', $context['warningBarWidth'], 'px; border: 1px solid black; background-color: white; padding: 1px; position: relative;">
								<div id="warning_text" style="padding-top: 1pt; width: 100%; z-index: 2; color: black; position: absolute; text-align: center; font-weight: bold;">', $context['member']['warning'], '%</div>
								<div id="warning_progress" style="width: ', $context['member']['warning'], '%; height: 12pt; z-index: 1; background-color: ', $context['current_color'], ';">&nbsp;</div>
							</div>
						</div>
					</div>
				</dd>';

		// There's some impact of this?
		if (!empty($context['level_effects'][$context['current_level']]))
			echo '
				<dt>
					<strong>', $txt['profile_viewwarning_impact'], ':</strong>
				</dt>
				<dd>
					', $context['level_effects'][$context['current_level']], '
				</dd>';

		echo '
			</dl>
		</div>';

	echo generic_list_helper('view_warnings');
}

// Show a lovely interface for issuing warnings.
function template_issueWarning()
{
	global $context, $scripturl, $txt;

	template_load_warning_variables();

	echo '
	<script>
		// Disable notification boxes as required.
		function modifyWarnNotify()
		{
			disable = !document.getElementById(\'warn_notify\').checked;
			document.getElementById(\'warn_sub\').disabled = disable;
			document.getElementById(\'warn_body\').disabled = disable;
			document.getElementById(\'warn_temp\').disabled = disable;
			document.getElementById(\'new_template_link\').style.display = disable ? \'none\' : \'\';
			document.getElementById(\'preview_button\').style.display = disable ? \'none\' : \'\';
		}

		// Warn template.
		function populateNotifyTemplate()
		{
			index = document.getElementById(\'warn_temp\').value;
			if (index == -1)
				return false;

			// Otherwise see what we can do...';

	foreach ($context['notification_templates'] as $k => $type)
		echo '
			if (index == ', $k, ')
				document.getElementById(\'warn_body\').value = "', strtr($type['body'], array('"' => "'", "\n" => '\\n', "\r" => '')), '";';

	echo '
		}

		function updateSlider(slideAmount)
		{
			// Also set the right effect.
			effectText = "";';

	foreach ($context['level_effects'] as $limit => $text)
		echo '
			if (slideAmount >= ', $limit, ')
				effectText = "', $text, '";';

	echo '
			setInnerHTML(document.getElementById(\'cur_level_div\'), slideAmount + \'% (\' + effectText + \')\');
		}
	</script>';

	echo '
	<form action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=issuewarning" method="post" class="flow_hidden" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				', $context['user']['is_owner'] ? $txt['profile_warning_level'] : $txt['profile_issue_warning'], '
			</h3>
		</div>';

	if (!$context['user']['is_owner'])
		echo '
		<p class="information">', $txt['profile_warning_desc'], '</p>';

	echo '
		<div class="windowbg">
			<dl class="settings">';

	if (!$context['user']['is_owner'])
		echo '
				<dt>
					<strong>', $txt['profile_warning_name'], ':</strong>
				</dt>
				<dd>
					<strong>', $context['member']['name'], '</strong>
				</dd>';

	echo '
				<dt>
					<strong>', $txt['profile_warning_level'], ':</strong>';

	// Is there only so much they can apply?
	if ($context['warning_limit'])
		echo '
					<br><span class="smalltext">', sprintf($txt['profile_warning_limit_attribute'], $context['warning_limit']), '</span>';

	echo '
				</dt>
				<dd>
					0% <input name="warning_level" id="warning_level" type="range" min="0" max="100" step="5" value="', $context['member']['warning'], '" onchange="updateSlider(this.value)" /> 100%
					<div class="clear_left">', $txt['profile_warning_impact'], ': <span id="cur_level_div">', $context['member']['warning'], '% (', $context['level_effects'][$context['current_level']], ')</span></div>
				</dd>';

	if (!$context['user']['is_owner'])
	{
		echo '
				<dt>
					<strong>', $txt['profile_warning_reason'], ':</strong><br>
					<span class="smalltext">', $txt['profile_warning_reason_desc'], '</span>
				</dt>
				<dd>
					<input type="text" name="warn_reason" id="warn_reason" value="', $context['warning_data']['reason'], '" size="50" style="width: 80%;" class="input_text">
				</dd>
			</dl>
			<hr>
			<div id="box_preview"', !empty($context['warning_data']['body_preview']) ? '' : ' style="display:none"', '>
				<dl class="settings">
					<dt>
						<strong>', $txt['preview'], '</strong>
					</dt>
					<dd id="body_preview">
						', !empty($context['warning_data']['body_preview']) ? $context['warning_data']['body_preview'] : '', '
					</dd>
				</dl>
				<hr>
			</div>
			<dl class="settings">
				<dt>
					<strong><label for="warn_notify">', $txt['profile_warning_notify'], ':</label></strong>
				</dt>
				<dd>
					<input type="checkbox" name="warn_notify" id="warn_notify" onclick="modifyWarnNotify();"', $context['warning_data']['notify'] ? ' checked' : '', ' class="input_check">
				</dd>
				<dt>
					<strong><label for="warn_sub">', $txt['profile_warning_notify_subject'], ':</label></strong>
				</dt>
				<dd>
					<input type="text" name="warn_sub" id="warn_sub" value="', empty($context['warning_data']['notify_subject']) ? $txt['profile_warning_notify_template_subject'] : $context['warning_data']['notify_subject'], '" size="50" style="width: 80%;" class="input_text">
				</dd>
				<dt>
					<strong><label for="warn_temp">', $txt['profile_warning_notify_body'], ':</label></strong>
				</dt>
				<dd>
					<select name="warn_temp" id="warn_temp" disabled onchange="populateNotifyTemplate();" style="font-size: x-small;">
						<option value="-1">', $txt['profile_warning_notify_template'], '</option>
						<option value="-1" disabled>------------------------------</option>';

		foreach ($context['notification_templates'] as $id_template => $template)
			echo '
						<option value="', $id_template, '">', $template['title'], '</option>';

		echo '
					</select>
					<span class="smalltext" id="new_template_link" style="display: none;">[<a href="', $scripturl, '?action=moderate;area=warnings;sa=templateedit;tid=0" target="_blank" class="new_win">', $txt['profile_warning_new_template'], '</a>]</span><br>
					<textarea name="warn_body" id="warn_body" cols="40" rows="8" style="min-width: 50%; max-width: 99%;">', $context['warning_data']['notify_body'], '</textarea>
				</dd>';
	}
	echo '
			</dl>
			<div class="righttext">';

	if (!empty($context['token_check']))
		echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="button" name="preview" id="preview_button" value="', $txt['preview'], '" class="button_submit">
				<input type="submit" name="save" value="', $context['user']['is_owner'] ? $txt['change_profile'] : $txt['profile_warning_issue'], '" class="button_submit">
			</div>
		</div>
	</form>';

	// Previous warnings?
	echo generic_list_helper('view_warnings');

	echo '
	<script>';

	if (!$context['user']['is_owner'])
		echo '
		modifyWarnNotify();
		$(document).ready(function() {
			$("#preview_button").click(function() {
				return ajax_getTemplatePreview();
			});
		});

		function ajax_getTemplatePreview ()
		{
			$.ajax({
				type: "POST",
				url: "' . $scripturl . '?action=xmlhttp;sa=previews;xml",
				data: {item: "warning_preview", title: $("#warn_sub").val(), body: $("#warn_body").val(), issuing: true},
				context: document.body,
				success: function(request){
					$("#box_preview").css({display:""});
					$("#body_preview").html($(request).find(\'body\').text());
					if ($(request).find("error").text() != \'\')
					{
						$("#profile_error").css({display:""});
						var errors_html = \'<ul class="list_errors">\';
						var errors = $(request).find(\'error\').each(function() {
							errors_html += \'<li>\' + $(this).text() + \'</li>\';
						});
						errors_html += \'</ul>\';

						$("#profile_error").html(errors_html);
					}
					else
					{
						$("#profile_error").css({display:"none"});
						$("#error_list").html(\'\');
					}
				return false;
				},
			});
			return false;
		}';

	echo '
	</script>';
}

/**
 * Template to show for deleting a user's account - now with added delete post capability!
 */
function template_deleteAccount()
{
	global $context, $scripturl, $txt;

	// The main containing header.
	echo '
		<form action="', $scripturl, '?action=profile;area=deleteaccount;save" method="post" accept-charset="UTF-8" name="creator" id="creator">
			<div class="cat_bar">
				<h3 class="catbg profile_hd">
					', $txt['deleteAccount'], '
				</h3>
			</div>';

	// If deleting another account give them a lovely info box.
	if (!$context['user']['is_owner'])
		echo '
			<p class="information">', $txt['deleteAccount_desc'], '</p>';
	echo '
			<div class="windowbg2">';

	// If they are deleting their account AND the admin needs to approve it - give them another piece of info ;)
	if ($context['needs_approval'])
		echo '
				<div class="errorbox">', $txt['deleteAccount_approval'], '</div>';

	// If the user is deleting their own account warn them first - and require a password!
	if ($context['user']['is_owner'])
	{
		echo '
				<div class="alert">', $txt['own_profile_confirm'], '</div>
				<div>
					<strong', (isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : ''), '>', $txt['current_password'], ': </strong>
					<input type="password" name="oldpasswrd" size="20" class="input_password">&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="submit" value="', $txt['yes'], '" class="button_submit">';

		if (!empty($context['token_check']))
			echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

		echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="u" value="', $context['id_member'], '">
					<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
				</div>';
	}
	// Otherwise an admin doesn't need to enter a password - but they still get a warning - plus the option to delete lovely posts!
	else
	{
		echo '
				<div class="alert">', $txt['deleteAccount_warning'], '</div>';

		// Only actually give these options if they are kind of important.
		if ($context['can_delete_posts'])
		{
			echo '
				<div>
					<label for="deleteVotes"><input type="checkbox" name="deleteVotes" id="deleteVotes" value="1" class="input_check"> ', $txt['deleteAccount_votes'], ':</label><br>
					<label for="deletePosts"><input type="checkbox" name="deletePosts" id="deletePosts" value="1" class="input_check"> ', $txt['deleteAccount_posts'], ':</label>
					<select name="remove_type">
						<option value="posts">', $txt['deleteAccount_all_posts'], '</option>
						<option value="topics">', $txt['deleteAccount_topics'], '</option>
					</select>';

			if ($context['show_perma_delete'])
				echo '
					<br><label for="perma_delete"><input type="checkbox" name="perma_delete" id="perma_delete" value="1" class="input_check">', $txt['deleteAccount_permanent'], ':</label>';

			echo '
				</div>';
		}

		echo '
				<div>
					<label for="deleteAccount"><input type="checkbox" name="deleteAccount" id="deleteAccount" value="1" class="input_check" onclick="if (this.checked) return confirm(\'', $txt['deleteAccount_confirm'], '\');"> ', $txt['deleteAccount_member'], '.</label>
				</div>
				<div>
					<input type="submit" value="', $txt['delete'], '" class="button_submit">';

		if (!empty($context['token_check']))
			echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

		echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="u" value="', $context['id_member'], '">
					<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
				</div>';
	}
	echo '
			</div>
			<br>
		</form>';
}

/**
 * Template for the password box/save button stuck at the bottom of every profile page.
 */
function template_profile_save()
{
	global $context, $txt;

	echo '

					<hr>';

	// Only show the password box if it's actually needed.
	if ($context['require_password'])
		echo '
					<dl class="settings">
						<dt>
							<strong', isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : '', '>', $txt['current_password'], ': </strong><br>
							<span class="smalltext">', $txt['required_security_reasons'], '</span>
						</dt>
						<dd>
							<input type="password" name="oldpasswrd" size="20" style="margin-right: 4ex;" class="input_password">
						</dd>
					</dl>';

	echo '
					<div class="righttext">';

		if (!empty($context['token_check']))
			echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '">';

	echo '
						<input type="submit" value="', $txt['change_profile'], '" class="button_submit">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						<input type="hidden" name="u" value="', $context['id_member'], '">
						<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
					</div>';
}

/**
 * Small template for showing an error message upon a save problem in the profile.
 */
function template_error_message()
{
	global $context, $txt;

	echo '
		<div class="errorbox" ', empty($context['post_errors']) ? 'style="display:none" ' : '', 'id="profile_error">';

	if (!empty($context['post_errors']))
	{
		echo '
			<span>', !empty($context['custom_error_title']) ? $context['custom_error_title'] : $txt['profile_errors_occurred'], ':</span>
			<ul id="list_errors">';

		// Cycle through each error and display an error message.
		foreach ($context['post_errors'] as $error)
			echo '
				<li>', isset($txt['profile_error_' . $error]) ? $txt['profile_error_' . $error] : $error, '</li>';

		echo '
			</ul>';
	}

	echo '
		</div>';
}

/**
 * Display a load of drop down selectors for allowing the user to change group.
 */
function template_profile_group_manage()
{
	global $context, $txt, $scripturl;

	echo '
							<dt>
								<strong>', $txt['primary_membergroup'], ': </strong><br>
								<span class="smalltext"><a href="', $scripturl, '?action=helpadmin;help=moderator_why_missing" onclick="return reqOverlayDiv(this.href);"><span class="generic_icons help"></span> ', $txt['moderator_why_missing'], '</a></span>
							</dt>
							<dd>
								<select name="id_group" ', ($context['user']['is_owner'] && $context['member']['group_id'] == 1 ? 'onchange="if (this.value != 1 &amp;&amp; !confirm(\'' . $txt['deadmin_confirm'] . '\')) this.value = 1;"' : ''), '>';

		// Fill the select box with all primary member groups that can be assigned to a member.
		foreach ($context['member_groups'] as $member_group)
			if (!empty($member_group['can_be_primary']))
				echo '
									<option value="', $member_group['id'], '"', $member_group['is_primary'] ? ' selected' : '', '>
										', $member_group['name'], '
									</option>';
		echo '
								</select>
							</dd>
							<dt>
								<strong>', $txt['additional_membergroups'], ':</strong>
							</dt>
							<dd>
								<span id="additional_groupsList">
									<input type="hidden" name="additional_groups[]" value="0">';

		// For each membergroup show a checkbox so members can be assigned to more than one group.
		foreach ($context['member_groups'] as $member_group)
			if ($member_group['can_be_additional'])
				echo '
									<label for="additional_groups-', $member_group['id'], '"><input type="checkbox" name="additional_groups[]" value="', $member_group['id'], '" id="additional_groups-', $member_group['id'], '"', $member_group['is_additional'] ? ' checked' : '', ' class="input_check"> ', $member_group['name'], '</label><br>';
		echo '
								</span>
								<a href="javascript:void(0);" onclick="document.getElementById(\'additional_groupsList\').style.display = \'block\'; document.getElementById(\'additional_groupsLink\').style.display = \'none\'; return false;" id="additional_groupsLink" style="display: none;" class="toggle_down">', $txt['additional_membergroups_show'], '</a>
								<script>
									document.getElementById("additional_groupsList").style.display = "none";
									document.getElementById("additional_groupsLink").style.display = "";
								</script>
							</dd>';

}

/**
 * Callback function for entering a birthdate!
 */
function template_profile_birthdate()
{
	global $txt, $context;

	// Just show the pretty box!
	echo '
							<dt>
								<strong>', $txt['dob'], ':</strong><br>
								<span class="smalltext">', $txt['dob_year'], ' - ', $txt['dob_month'], ' - ', $txt['dob_day'], '</span>
							</dt>
							<dd>
								<input type="text" name="bday3" size="4" maxlength="4" value="', $context['member']['birth_date']['year'], '" class="input_text"> -
								<input type="text" name="bday1" size="2" maxlength="2" value="', $context['member']['birth_date']['month'], '" class="input_text"> -
								<input type="text" name="bday2" size="2" maxlength="2" value="', $context['member']['birth_date']['day'], '" class="input_text">
							</dd>';
}

/**
 * Show the signature editing box?
 */
function template_profile_signature_modify()
{
	global $txt, $context;

	echo '
							<dt id="current_signature" style="display:none">
								<strong>', $txt['current_signature'], ':</strong>
							</dt>
							<dd id="current_signature_display" style="display:none">
								<hr>
							</dd>';
	echo '
							<dt id="preview_signature" style="display:none">
								<strong>', $txt['signature_preview'], ':</strong>
							</dt>
							<dd id="preview_signature_display" style="display:none">
								<hr>
							</dd>';

	echo '
							<dt>
								<strong>', $txt['signature'], ':</strong><br>
								<span class="smalltext">', $txt['sig_info'], '</span>
							</dt>
							<dd>
								<textarea class="editor" onkeyup="calcCharLeft();" id="signature" name="signature" rows="5" cols="50" style="min-width: 50%; max-width: 99%;">', $context['member']['signature'], '</textarea><br>';

	// If there is a limit at all!
	if (!empty($context['signature_limits']['max_length']))
		echo '
								<span class="smalltext">', sprintf($txt['max_sig_characters'], $context['signature_limits']['max_length']), ' <span id="signatureLeft">', $context['signature_limits']['max_length'], '</span></span><br>';

	if (!empty($context['show_preview_button']))
		echo '
								<input type="button" name="preview_signature" id="preview_button" value="', $txt['preview_signature'], '" class="button_submit">';

	if ($context['signature_warning'])
		echo '
								<span class="smalltext">', $context['signature_warning'], '</span>';

	// Some javascript used to count how many characters have been used so far in the signature.
	echo '
								<script>
									var maxLength = ', $context['signature_limits']['max_length'], ';

									$(document).ready(function() {
										calcCharLeft();
										$("#preview_button").click(function() {
											return ajax_getSignaturePreview(true);
										});
									});
								</script>
							</dd>';
}

/**
 * Template for selecting an avatar
 */
function template_profile_avatar_select()
{
	global $context, $txt, $modSettings, $settings;

	// Start with the upper menu
	echo '
							<dt>
								<strong id="personal_picture"><label for="avatar_upload_box">', $txt['personal_picture'], '</label></strong>
								', empty($modSettings['gravatarOverride']) ? '<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_none" value="none"' . ($context['member']['avatar']['choice'] == 'none' ? ' checked="checked"' : '') . ' class="input_radio" /><label for="avatar_choice_none"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>' . $txt['no_avatar'] . '</label><br />' : '', '
								', !empty($context['member']['avatar']['allow_external']) ? '<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_external" value="external"' . ($context['member']['avatar']['choice'] == 'external' ? ' checked="checked"' : '') . ' class="input_radio" /><label for="avatar_choice_external"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>' . $txt['my_own_pic'] . '</label><br />' : '', '
								', !empty($context['member']['avatar']['allow_upload']) ? '<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_upload" value="upload"' . ($context['member']['avatar']['choice'] == 'upload' ? ' checked="checked"' : '') . ' class="input_radio" /><label for="avatar_choice_upload"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>' . $txt['avatar_will_upload'] . '</label><br />' : '', '
								', !empty($context['member']['avatar']['allow_gravatar']) ? '<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_gravatar" value="gravatar"' . ($context['member']['avatar']['choice'] == 'gravatar' ? ' checked="checked"' : '') . ' class="input_radio" /><label for="avatar_choice_gravatar"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>' . $txt['use_gravatar'] . '</label>' : '', '
							</dt>
							<dd>';

	echo '
								<div>
									<div><img id="avatar" src="', !empty($context['member']['avatar']['allow_external']) && $context['member']['avatar']['choice'] == 'external' ? $context['member']['avatar']['external'] : $settings['images_url'] . '/blank.png', '" alt="Do Nothing"></div>
									<script>
										var avatar = document.getElementById("avatar");
										var cat = document.getElementById("cat");
										var selavatar = "' . $context['avatar_selected'] . '";
										var size = avatar.alt.substr(3, 2) + " " + avatar.alt.substr(0, 2) + String.fromCharCode(117, 98, 116);
										var file = document.getElementById("file");
										var maxHeight = ', !empty($modSettings['avatar_max_height']) ? $modSettings['avatar_max_height'] : 0, ';
										var maxWidth = ', !empty($modSettings['avatar_max_width']) ? $modSettings['avatar_max_width'] : 0, ';

										previewExternalAvatar(avatar.src)

									</script>
								</div>';

	// If the user can link to an off server avatar, show them a box to input the address.
	if (!empty($context['member']['avatar']['allow_external']))
	{
		echo '
								<div id="avatar_external">
									<div class="smalltext">', $txt['avatar_by_url'], '</div>', !empty($modSettings['avatar_action_too_large']) && $modSettings['avatar_action_too_large'] == 'option_download_and_resize' ? template_max_size() : '', '
									<input type="text" name="userpicpersonal" size="45" value="', ((stristr($context['member']['avatar']['external'], 'http://') || stristr($context['member']['avatar']['external'], 'https://')) ? $context['member']['avatar']['external'] : 'http://'), '" onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'external\');" onchange="if (typeof(previewExternalAvatar) != \'undefined\') previewExternalAvatar(this.value);" class="input_text" />
								</div>';
	}

	// If the user is able to upload avatars to the server show them an upload box.
	if (!empty($context['member']['avatar']['allow_upload']))
	{
		echo '
								<div id="avatar_upload">
									<input type="file" size="44" name="attachment" id="avatar_upload_box" value="" onchange="readfromUpload(this)"  onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'upload\');" class="input_file" accept="image/gif, image/jpeg, image/jpg, image/png">', template_max_size(), '
									', (!empty($context['member']['avatar']['id_attach']) ? '<br><img src="' . $context['member']['avatar']['href'] . (strpos($context['member']['avatar']['href'], '?') === false ? '?' : '&amp;') . 'time=' . time() . '" alt="" id="attached_image"><input type="hidden" name="id_attach" value="' . $context['member']['avatar']['id_attach'] . '">' : ''), '
								</div>';
	}

	// if the user is able to use Gravatar avatars show then the image preview
	if (!empty($context['member']['avatar']['allow_gravatar']))
	{
		echo '
								<div id="avatar_gravatar">
									<img src="' . $context['member']['avatar']['href'] . '" alt="" />';

		if (empty($modSettings['gravatarAllowExtraEmail']))
			echo '
									<div class="smalltext">', $txt['gravatar_noAlternateEmail'], '</div>';
		else
		{
			// Depending on other stuff, the stored value here might have some odd things in it from other areas.
			if ($context['member']['avatar']['external'] == $context['member']['email'])
				$textbox_value = '';
			else
				$textbox_value = $context['member']['avatar']['external'];

			echo '
									<div class="smalltext">', $txt['gravatar_alternateEmail'], '</div>
									<input type="text" name="gravatarEmail" id="gravatarEmail" size="45" value="', $textbox_value, '" class="input_text" />';
		}
		echo '
								</div>';
	}

	echo '
								<script>
									', !empty($context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "' . ($context['member']['avatar']['choice'] == 'external' ? '' : 'none') . '";' : '', '
									', !empty($context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "' . ($context['member']['avatar']['choice'] == 'upload' ? '' : 'none') . '";' : '', '
									', !empty($context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "' . ($context['member']['avatar']['choice'] == 'gravatar' ? '' : 'none') . '";' : '', '

									function swap_avatar(type)
									{
										switch(type.id)
										{
											case "avatar_choice_external":
												', !empty($context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "";' : '', '
												', !empty($context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "none";' : '', '
												break;
											case "avatar_choice_upload":
												', !empty($context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "";' : '', '
												', !empty($context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "none";' : '', '
												break;
											case "avatar_choice_none":
												', !empty($context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "none";' : '', '
												break;
											case "avatar_choice_gravatar":
												', !empty($context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "";' : '', '
												', ($context['member']['avatar']['external'] == $context['member']['email'] || strstr($context['member']['avatar']['external'], 'http://')) ?
												'document.getElementById("gravatarEmail").value = "";' : '', '
												break;
										}
									}
								</script>
							</dd>';
}

/**
 * This is just a really little helper to avoid duplicating code unnecessarily
 */
function template_max_size()
{
	global $modSettings, $txt;

	$w = !empty($modSettings['avatar_max_width']) ? comma_format($modSettings['avatar_max_width']) : 0;
	$h = !empty($modSettings['avatar_max_height']) ? comma_format($modSettings['avatar_max_height']) : 0;

	$suffix = (!empty($w) ? 'w' : '') . (!empty($h) ? 'h' : '');
	if (empty($suffix))
		return;

	echo '
									<div class="smalltext">', sprintf($txt['avatar_max_size_' . $suffix], $w, $h), '</div>';
}

/**
 * Select the time format!
 */
function template_profile_timeformat_modify()
{
	global $context, $txt, $scripturl, $settings;

	echo '
							<dt>
								<strong><label for="easyformat">', $txt['time_format'], ':</label></strong><br>
								<a href="', $scripturl, '?action=helpadmin;help=time_format" onclick="return reqOverlayDiv(this.href);" class="help"><span class="generic_icons help" title="', $txt['help'], '"></span></a>
								<span class="smalltext">&nbsp;<label for="time_format">', $txt['date_format'], '</label></span>
							</dt>
							<dd>
								<select name="easyformat" id="easyformat" onchange="document.forms.creator.time_format.value = this.options[this.selectedIndex].value;" style="margin-bottom: 4px;">';
	// Help the user by showing a list of common time formats.
	foreach ($context['easy_timeformats'] as $time_format)
		echo '
									<option value="', $time_format['format'], '"', $time_format['format'] == $context['member']['time_format'] ? ' selected' : '', '>', $time_format['title'], '</option>';
	echo '
								</select><br>
								<input type="text" name="time_format" id="time_format" value="', $context['member']['time_format'], '" size="30" class="input_text">
							</dd>';
}

/**
 * Template for picking a theme
 */
function template_profile_theme_pick()
{
	global $txt, $context, $scripturl;

	echo '
							<dt>
								<strong>', $txt['current_theme'], ':</strong>
							</dt>
							<dd>
								', $context['member']['theme']['name'], ' [<a href="', $scripturl, '?action=theme;sa=pick;u=', $context['id_member'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['change'], '</a>]
							</dd>';
}

/**
 * Smiley set picker.
 */
function template_profile_smiley_pick()
{
	global $txt, $context, $modSettings, $settings;

	echo '
							<dt>
								<strong><label for="smiley_set">', $txt['smileys_current'], ':</label></strong>
							</dt>
							<dd>
								<select name="smiley_set" id="smiley_set" onchange="document.getElementById(\'smileypr\').src = this.selectedIndex == 0 ? \'', $settings['images_url'], '/blank.png\' : \'', $modSettings['smileys_url'], '/\' + (this.selectedIndex != 1 ? this.options[this.selectedIndex].value : \'', !empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default'], '\') + \'/smiley.gif\';">';
	foreach ($context['smiley_sets'] as $set)
		echo '
									<option value="', $set['id'], '"', $set['selected'] ? ' selected' : '', '>', $set['name'], '</option>';
	echo '
								</select> <img id="smileypr" class="centericon" src="', $context['member']['smiley_set']['id'] != 'none' ? $modSettings['smileys_url'] . '/' . ($context['member']['smiley_set']['id'] != '' ? $context['member']['smiley_set']['id'] : (!empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default'])) . '/smiley.gif' : $settings['images_url'] . '/blank.png', '" alt=":)"  style="padding-left: 20px;">
							</dd>';
}

/**
 * Simple template for showing the 2FA area when editing a profile.
 */
function template_profile_tfa()
{
	global $context, $txt, $scripturl, $modSettings;

	echo '
							<dt>
								<strong>', $txt['tfa_profile_label'], ':</strong>
								<br /><div class="smalltext">', $txt['tfa_profile_desc'], '</div>
							</dt>
							<dd>';
	if (!$context['tfa_enabled'] && $context['user']['is_owner'])
		echo '
								<a href="', !empty($modSettings['force_ssl']) && $modSettings['force_ssl'] < 2 ? strtr($scripturl, array('http://' => 'https://')) : $scripturl, '?action=profile;area=tfasetup" id="enable_tfa">', $txt['tfa_profile_enable'], '</a>';
	elseif (!$context['tfa_enabled'])
		echo '
								', $txt['tfa_profile_disabled'];
	else
		echo '
							', sprintf($txt['tfa_profile_enabled'], $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=tfasetup;disable');
	echo '
							</dd>';
}

?>