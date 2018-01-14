<?php

/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

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

?>