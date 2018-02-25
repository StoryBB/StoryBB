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

	echo StoryBB\Template\Helper\Controls::genericlist('view_warnings');
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


?>