<?php

/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * THe page that shows which permissions profile applies to each board
 */
function template_by_board()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=permissions;sa=board" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['permissions_boards'], '</h3>
			</div>
			<div class="information">
				', $txt['permissions_boards_desc'], '
			</div>

			<div class="cat_bar">
				<h3 id="board_permissions" class="catbg flow_hidden">
					<span class="perm_name floatleft">', $txt['board_name'], '</span>
					<span class="perm_profile floatleft">', $txt['permission_profile'], '</span>';
					echo '
				</h3>
			</div>
			<div class="windowbg2 noup">';

	foreach ($context['categories'] as $category)
	{
		echo '
			<div class="sub_bar">
				<h3 class="subbg">', $category['name'], '</h3>
			</div>';

		if (!empty($category['boards']))
			echo '
				<ul class="perm_boards flow_hidden">';

		foreach ($category['boards'] as $board)
		{

			echo '
					<li class="flow_hidden">
						<span class="perm_board floatleft">
							<a href="', $scripturl, '?action=admin;area=manageboards;sa=board;boardid=', $board['id'], ';rid=permissions;', $context['session_var'], '=', $context['session_id'], '">', str_repeat('-', $board['child_level']), ' ', $board['name'], '</a>
						</span>
						<span class="perm_boardprofile floatleft">';

			if ($context['edit_all'])
			{
				echo '
							<select name="boardprofile[', $board['id'], ']">';

				foreach ($context['profiles'] as $id => $profile)
					echo '
								<option value="', $id, '"', $id == $board['profile'] ? ' selected' : '', '>', $profile['name'], '</option>';

				echo '
							</select>';
			}
			else
				echo '
							<a href="', $scripturl, '?action=admin;area=permissions;sa=index;pid=', $board['profile'], ';', $context['session_var'], '=', $context['session_id'], '">', $board['profile_name'], '</a>';

			echo '
						</span>
					</li>';
		}

		if (!empty($category['boards']))
			echo '
				</ul>';
	}

	if ($context['edit_all'])
		echo '
			<input type="submit" name="save_changes" value="', $txt['save'], '" class="button_submit">';
	else
		echo '
			<a class="button_link" href="', $scripturl, '?action=admin;area=permissions;sa=board;edit;', $context['session_var'], '=', $context['session_id'], '">', $txt['permissions_board_all'], '</a>';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['admin-mpb_token_var'], '" value="', $context['admin-mpb_token'], '">
			</div>
		</form>
	</div>';
}

/**
 * Edit permission profiles (predefined).
 */
function template_edit_profiles()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admin_form_wrapper">
		<form action="', $scripturl, '?action=admin;area=permissions;sa=profiles" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['permissions_profile_edit'], '</h3>
			</div>

			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th>', $txt['permissions_profile_name'], '</th>
						<th>', $txt['permissions_profile_used_by'], '</th>
						<th class="table_icon"', !empty($context['show_rename_boxes']) ? ' style="display:none"' : '', '>', $txt['delete'], '</th>
					</tr>
				</thead>
				<tbody>';

	foreach ($context['profiles'] as $profile)
	{
		echo '
					<tr class="windowbg">
						<td>';

		if (!empty($context['show_rename_boxes']) && $profile['can_edit'])
			echo '
							<input type="text" name="rename_profile[', $profile['id'], ']" value="', $profile['name'], '" class="input_text">';
		else
			echo '
							<a href="', $scripturl, '?action=admin;area=permissions;sa=index;pid=', $profile['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $profile['name'], '</a>';

		echo '
						</td>
						<td>
							', !empty($profile['boards_text']) ? $profile['boards_text'] : $txt['permissions_profile_used_by_none'], '
						</td>
						<td', !empty($context['show_rename_boxes']) ? ' style="display:none"' : '', '>
							<input type="checkbox" name="delete_profile[]" value="', $profile['id'], '" ', $profile['can_delete'] ? '' : 'disabled', ' class="input_check">
						</td>
					</tr>';
	}

	echo '
				</tbody>
			</table>
			<div class="flow_auto righttext padding">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-mpp_token_var'], '" value="', $context['admin-mpp_token'], '">';

	if ($context['can_edit_something'])
		echo '
				<input type="submit" name="rename" value="', empty($context['show_rename_boxes']) ? $txt['permissions_profile_rename'] : $txt['permissions_commit'], '" class="button_submit">';

	echo '
				<input type="submit" name="delete" value="', $txt['quickmod_delete_selected'], '" class="button_submit" ', !empty($context['show_rename_boxes']) ? ' style="display:none"' : '', '/>
			</div>
		</form>
		<br>
		<form action="', $scripturl, '?action=admin;area=permissions;sa=profiles" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['permissions_profile_new'], '</h3>
			</div>
			<div class="windowbg2 noup">
				<dl class="settings">
					<dt>
						<strong>', $txt['permissions_profile_name'], ':</strong>
					</dt>
					<dd>
						<input type="text" name="profile_name" value="" class="input_text">
					</dd>
					<dt>
						<strong>', $txt['permissions_profile_copy_from'], ':</strong>
					</dt>
					<dd>
						<select name="copy_from">';

	foreach ($context['profiles'] as $id => $profile)
		echo '
							<option value="', $id, '">', $profile['name'], '</option>';

	echo '
						</select>
					</dd>
				</dl>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-mpp_token_var'], '" value="', $context['admin-mpp_token'], '">
				<input type="submit" name="create" value="', $txt['permissions_profile_new_create'], '" class="button_submit">
			</div>
		</form>
	</div>';
}

/**
 * Modify a group's permissions
 */
function template_modify_group()
{
	global $context, $scripturl, $txt, $modSettings;

	// Cannot be edited?
	if (!$context['profile']['can_modify'])
	{
		echo '
		<div class="errorbox">
			', sprintf($txt['permission_cannot_edit'], $scripturl . '?action=admin;area=permissions;sa=profiles'), '
		</div>';
	}
	else
	{
		echo '
		<script>
			window.smf_usedDeny = false;

			function warnAboutDeny()
			{
				if (window.smf_usedDeny)
					return confirm("', $txt['permissions_deny_dangerous'], '");
				else
					return true;
			}
		</script>';
	}

	echo '
	<div id="admincenter">
		<form id="permissions" action="', $scripturl, '?action=admin;area=permissions;sa=modify2;group=', $context['group']['id'], ';pid=', $context['profile']['id'], '" method="post" accept-charset="UTF-8" name="permissionForm" onsubmit="return warnAboutDeny();">';

	if ($context['group']['id'] != -1)
		echo '
			<div class="information">
				', $txt['permissions_option_desc'], '
			</div>';

	echo '
			<div class="cat_bar">
				<h3 class="catbg">';
	if ($context['permission_type'] == 'board')
		echo '
				', $txt['permissions_local_for'], ' &quot;', $context['group']['name'], '&quot; ', $txt['permissions_on'], ' &quot;', $context['profile']['name'], '&quot;';
	else
		echo '
				', $context['permission_type'] == 'membergroup' ? $txt['permissions_general'] : $txt['permissions_board'], ' - &quot;', $context['group']['name'], '&quot;';
	echo '
				</h3>
			</div>';

	// Draw out the main bits.
	template_modify_group_display($context['permission_type']);

	// If this is general permissions also show the default profile.
	if ($context['permission_type'] == 'membergroup')
	{
		echo '
			<br>
			<div class="cat_bar">
				<h3 class="catbg">', $txt['permissions_board'], '</h3>
			</div>
			<div class="information">
				', $txt['permissions_board_desc'], '
			</div>';

		template_modify_group_display('board');
	}

	if ($context['profile']['can_modify'])
		echo '
			<div class="padding">
				<input type="submit" value="', $txt['permissions_commit'], '" class="button_submit">
			</div>';

	foreach ($context['hidden_perms'] as $hidden_perm)
		echo '
			<input type="hidden" name="perm[', $hidden_perm[0], '][', $hidden_perm[1], ']" value="', $hidden_perm[2], '">';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['admin-mp_token_var'], '" value="', $context['admin-mp_token'], '">
		</form>
	</div>';
}

/**
 * The way of looking at permissions.
 *
 * @param string $type The permissions type
 */
function template_modify_group_display($type)
{
	global $context, $settings, $scripturl, $txt, $modSettings;

	$permission_type = &$context['permissions'][$type];
	$disable_field = $context['profile']['can_modify'] ? '' : 'disabled ';

	foreach ($permission_type['columns'] as $column)
	{
		echo '
					<table class="table_grid half_content">';

		foreach ($column as $permissionGroup)
		{
			if (empty($permissionGroup['permissions']))
				continue;

			// Are we likely to have something in this group to display or is it all hidden?
			$has_display_content = false;
			if (!$permissionGroup['hidden'])
			{
				// Before we go any further check we are going to have some data to print otherwise we just have a silly heading.
				foreach ($permissionGroup['permissions'] as $permission)
					if (!$permission['hidden'])
						$has_display_content = true;

				if ($has_display_content)
				{
					echo '
						<tr class="title_bar">
							<th></th>
							<th', $context['group']['id'] == -1 ? ' colspan="2"' : '', ' class="smalltext">', $permissionGroup['name'], '</th>';

					if ($context['group']['id'] != -1)
						echo '
							<th>', $txt['permissions_option_own'], '</th>
							<th>', $txt['permissions_option_any'], '</th>';

						echo '
						</tr>';
				}
			}

			foreach ($permissionGroup['permissions'] as $permission)
			{
				if (!$permission['hidden'] && !$permissionGroup['hidden'])
				{
					echo '
						<tr class="windowbg">
							<td>
								', $permission['show_help'] ? '<a href="' . $scripturl . '?action=helpadmin;help=permissionhelp_' . $permission['id'] . '" onclick="return reqOverlayDiv(this.href);" class="help"><span class="generic_icons help" title="' . $txt['help'] . '"></span></a>' : '', '
							</td>
							<td class="lefttext full_width">', $permission['name'], '</td><td>';

					if ($permission['has_own_any'])
					{
						// Guests can't do their own thing.
						if ($context['group']['id'] != -1)
						{
							echo '
								<select name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']" ', $disable_field, '>';

							foreach (array('on', 'off', 'deny') as $c)
									echo '
									<option ', $permission['own']['select'] == $c ? ' selected' : '', ' value="', $c, '">', $txt['permissions_option_' . $c], '</option>';
							echo '
								</select>';

						echo '
							</td>
							<td>';
						}

						if ($context['group']['id'] == -1)
							echo '
								<input type="checkbox" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']"', $permission['any']['select'] == 'on' ? ' checked="checked"' : '', ' value="on" class="input_check" ', $disable_field, '/>';
						else
						{
							echo '
								<select name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']" ', $disable_field, '>';

							foreach (array('on', 'off', 'deny') as $c)
								echo '
									<option ', $permission['any']['select'] == $c ? ' selected' : '', ' value="', $c, '">', $txt['permissions_option_' . $c], '</option>';
							echo '
								</select>';
						}
					}
					else
					{
						if ($context['group']['id'] != -1)
							echo '
							</td>
							<td>';

						if ($context['group']['id'] == -1)
							echo '
								<input type="checkbox" name="perm[', $permission_type['id'], '][', $permission['id'], ']"', $permission['select'] == 'on' ? ' checked="checked"' : '', ' value="on" class="input_check" ', $disable_field, '/>';
						else
						{
							echo '
								<select name="perm[', $permission_type['id'], '][', $permission['id'], ']" ', $disable_field, '>';

							foreach (array('on', 'off', 'deny') as $c)
								echo '
									<option ', $permission['select'] == $c ? ' selected' : '', ' value="', $c, '">', $txt['permissions_option_' . $c], '</option>';
							echo '
								</select>';
						}
					}
					echo '
							</td>
						</tr>';
				}
			}
		}
		echo '
					</table>';
	}

	echo '
				<br class="clear">';
}

/**
 * A form for displaying inline permissions, such as on a settings page.
 */
function template_inline_permissions()
{
	global $context, $txt, $modSettings;

	// This looks really weird, but it keeps things nested properly...
	echo '
											<fieldset id="', $context['current_permission'], '">
												<legend><a href="javascript:void(0);" onclick="document.getElementById(\'', $context['current_permission'], '\').style.display = \'none\';document.getElementById(\'', $context['current_permission'], '_groups_link\').style.display = \'block\'; return false;" class="toggle_up"> ', $txt['avatar_select_permission'], '</a></legend>
												<div class="information">', $txt['permissions_option_desc'], '</div>
												<dl class="settings">
													<dt>
														<span class="perms"><strong>', $txt['permissions_option_on'], '</strong></span>
														<span class="perms"><strong>', $txt['permissions_option_off'], '</strong></span>
														<span class="perms red"><strong>', $txt['permissions_option_deny'], '</strong></span>
													</dt>
													<dd>
													</dd>';
	foreach ($context['member_groups'] as $group)
	{
		echo '
													<dt>
														<span class="perms"><input type="radio" name="', $context['current_permission'], '[', $group['id'], ']" value="on"', $group['status'] == 'on' ? ' checked' : '', ' class="input_radio"></span>
														<span class="perms"><input type="radio" name="', $context['current_permission'], '[', $group['id'], ']" value="off"', $group['status'] == 'off' ? ' checked' : '', ' class="input_radio"></span>
														<span class="perms"><input type="radio" name="', $context['current_permission'], '[', $group['id'], ']" value="deny"', $group['status'] == 'deny' ? ' checked' : '', ' class="input_radio"></span>
													</dt>
													<dd>
														<span', $group['is_postgroup'] ? ' style="font-style: italic;"' : '', '>', $group['name'], '</span>
													</dd>';
	}

	echo '
												</dl>
											</fieldset>

											<a href="javascript:void(0);" onclick="document.getElementById(\'', $context['current_permission'], '\').style.display = \'block\'; document.getElementById(\'', $context['current_permission'], '_groups_link\').style.display = \'none\'; return false;" id="', $context['current_permission'], '_groups_link" style="display: none;" class="toggle_down"> ', $txt['avatar_select_permission'], '</a>

											<script>
												document.getElementById("', $context['current_permission'], '").style.display = "none";
												document.getElementById("', $context['current_permission'], '_groups_link").style.display = "";
											</script>';
}

?>