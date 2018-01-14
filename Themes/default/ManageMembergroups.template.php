<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Templatine for viewing the members of a group.
 */
function template_group_members()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;group=', $context['group']['id'], '" method="post" accept-charset="UTF-8" id="view_group">
			<div class="cat_bar">
				<h3 class="catbg">', $context['page_title'], '</h3>
			</div>
			<div class="windowbg2">
				<dl class="settings">
					<dt>
						<strong>', $txt['name'], ':</strong>
					</dt>
					<dd>
						<span ', $context['group']['online_color'] ? 'style="color: ' . $context['group']['online_color'] . ';"' : '', '>', $context['group']['name'], '</span> ', $context['group']['icons'], '
					</dd>';
	//Any description to show?
	if (!empty($context['group']['description']))
		echo '
					<dt>
						<strong>' . $txt['membergroups_members_description'] . ':</strong>
					</dt>
					<dd>
						', $context['group']['description'], '
					</dd>';

	echo '
					<dt>
						<strong>', $txt['membergroups_members_top'], ':</strong>
					</dt>
					<dd>
						', $context['total_members'], '
					</dd>';
	// Any group moderators to show?
	if (!empty($context['group']['moderators']))
	{
		$moderators = array();
		foreach ($context['group']['moderators'] as $moderator)
			$moderators[] = '<a href="' . $scripturl . '?action=profile;u=' . $moderator['id'] . '">' . $moderator['name'] . '</a>';

		echo '
					<dt>
						<strong>', $txt['membergroups_members_group_moderators'], ':</strong>
					</dt>
					<dd>
						', implode(', ', $moderators), '
					</dd>';
	}

	echo '
				</dl>
			</div>

			<br>
			<div class="cat_bar">
				<h3 class="catbg">', $txt['membergroups_members_group_members'], '</h3>
			</div>
			<br>
			<div class="pagesection">', $context['page_index'], '</div>
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=name', $context['sort_by'] == 'name' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['name'], $context['sort_by'] == 'name' ? ' <span class="generic_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a></th>';

	if ($context['group']['is_character'])
		echo '
						<th>', rtrim($txt['char_name'], ':'), '</th>';

	if ($context['can_send_email'])
		echo '
						<th><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=email', $context['sort_by'] == 'email' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['email'], $context['sort_by'] == 'email' ? ' <span class="generic_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a></th>';

	echo '
						<th><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=active', $context['sort_by'] == 'active' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['membergroups_members_last_active'], $context['sort_by'] == 'active' ? '<span class="generic_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a></th>
						<th><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=registered', $context['sort_by'] == 'registered' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['date_registered'], $context['sort_by'] == 'registered' ? '<span class="generic_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a></th>
						<th ', empty($context['group']['assignable']) ? ' colspan="2"' : '', '><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=posts', $context['sort_by'] == 'posts' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['posts'], $context['sort_by'] == 'posts' ? ' <span class="generic_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a></th>';
	if (!empty($context['group']['assignable']))
		echo '
						<th style="width: 4%"><input type="checkbox" class="input_check" onclick="invertAll(this, this.form);"></th>';
	echo '
					</tr>
				</thead>
				<tbody>';

	if (empty($context['members']))
		echo '
					<tr class="windowbg">
						<td colspan="', $context['group']['is_character'] ? 7 : 6, '">', $txt['membergroups_members_no_members'], '</td>
					</tr>';

	foreach ($context['members'] as $member)
	{
		echo '
					<tr class="windowbg">
						<td>', $member['name'], '</td>';
		if (!empty($member['character']))
			echo '
						<td>
							', $member['character'], '
						</td>';

		if ($context['can_send_email'])
		{
			echo '
						<td>
								<a href="mailto:', $member['email'], '">', $member['email'], '</a>
						</td>';
		}

		echo '
						<td>', $member['last_online'], '</td>
						<td>', $member['registered'], '</td>
						<td', empty($context['group']['assignable']) ? ' colspan="2"' : '', '>', $member['posts'], '</td>';
		if (!empty($context['group']['assignable']))
		{
			if ($context['group']['is_character'])
				echo '
						<td style="width: 4%"><input type="checkbox" name="rem[]" value="', $member['id_character'], '" class="input_check" /></td>';
			else
				echo '
						<td style="width: 4%"><input type="checkbox" name="rem[]" value="', $member['id'], '" class="input_check" ', ($context['user']['id'] == $member['id'] && $context['group']['id'] == 1 ? 'onclick="if (this.checked) return confirm(\'' . $txt['membergroups_members_deadmin_confirm'] . '\')" ' : ''), '/></td>';
		}

		echo '
					</tr>';
	}

	echo '
				</tbody>
			</table>';

	if (!empty($context['group']['assignable']))
		echo '
			<div class="floatright">
				<input type="submit" name="remove" value="', $txt['membergroups_members_remove'], '" class="button_submit ">
			</div>';

	echo '
			<div class="pagesection flow_hidden">
				<div class="floatleft">', $context['page_index'], '</div>
			</div>
			<br>';

	if (!empty($context['group']['assignable']))
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['membergroups_members_add_title'], '</h3>
			</div>
			<div class="windowbg2">
				<dl class="settings">
					<dt>
						<strong><label for="toAdd">', $txt['membergroups_members_add_desc'], ':</label></strong>
					</dt>
					<dd>
						<input type="text" name="toAdd" id="toAdd" value="" class="input_text">
						<div id="toAddItemContainer"></div>
					</dd>
				</dl>
				<input type="submit" name="add" value="', $txt['membergroups_members_add'], '" class="button_submit">
			</div>';
	}

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['mod-mgm_token_var'], '" value="', $context['mod-mgm_token'], '">
		</form>
	</div>';

	if (!empty($context['group']['assignable']))
		echo '
		<script>
			var oAddMemberSuggest = new smc_AutoSuggest({
				sSelf: \'oAddMemberSuggest\',
				sSessionId: \'', $context['session_id'], '\',
				sSessionVar: \'', $context['session_var'], '\',
				sSuggestId: \'to_suggest\',
				sControlId: \'toAdd\',
				sItemTemplate: \'<input type="hidden" name="%post_name%[]" value="%item_id%"><a href="%item_href%" class="extern" onclick="window.open(this.href, \\\'_blank\\\'); return false;">%item_name%</a>&nbsp;<span class="generic_icons delete" title="%delete_text%" onclick="return %self%.deleteAddedItem(\\\'%item_id%\\\');"></span>\',
				sSearchType: \'' . ($context['group']['is_character'] ? 'character' : 'member') . '\',
				sPostName: \'member_add\',
				sURLMask: \'action=profile;u=%item_id%\',
				sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
				bItemList: true,
				sItemListContainerId: \'toAddItemContainer\'
			});
		</script>';
}

?>