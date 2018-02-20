<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * The template for adding or editing a subscription.
 */
function template_modify_subscription()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=paidsubscribe;sa=modify;sid=', $context['sub_id'], '" method="post">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['paid_' . $context['action_type'] . '_subscription'], '</h3>
			</div>';

	if (!empty($context['disable_groups']))
		echo '
			<div class="information">
				<span class="alert">', $txt['paid_mod_edit_note'], '</span>
			</div>';

	echo '
			<div class="windowbg2">
				<dl class="settings">
					<dt>
						', $txt['paid_mod_name'], ':
					</dt>
					<dd>
						<input type="text" name="name" value="', $context['sub']['name'], '" size="30" class="input_text">
					</dd>
					<dt>
						', $txt['paid_mod_desc'], ':
					</dt>
					<dd>
						<textarea name="desc" rows="3" cols="40">', $context['sub']['desc'], '</textarea>
					</dd>
					<dt>
						<label for="repeatable_check">', $txt['paid_mod_repeatable'], '</label>:
					</dt>
					<dd>
						<input type="checkbox" name="repeatable" id="repeatable_check"', empty($context['sub']['repeatable']) ? '' : ' checked', ' class="input_check">
					</dd>
					<dt>
						<label for="activated_check">', $txt['paid_mod_active'], '</label>:<br><span class="smalltext">', $txt['paid_mod_active_desc'], '</span>
					</dt>
					<dd>
						<input type="checkbox" name="active" id="activated_check"', empty($context['sub']['active']) ? '' : ' checked', ' class="input_check">
					</dd>
				</dl>
				<hr>
				<dl class="settings">
					<dt>
						', $txt['paid_mod_prim_group'], ':<br><span class="smalltext">', $txt['paid_mod_prim_group_desc'], '</span>
					</dt>
					<dd>
						<select name="prim_group"', !empty($context['disable_groups']) ? ' disabled' : '', '>
							<option value="0"', $context['sub']['prim_group'] == 0 ? ' selected' : '', '>', $txt['paid_mod_no_group'], '</option>';

	// Put each group into the box.
	foreach ($context['groups'] as $id => $name)
		echo '
							<option value="', $id, '"', $context['sub']['prim_group'] == $id ? ' selected' : '', '>', $name, '</option>';

	echo '
						</select>
					</dd>
					<dt>
						', $txt['paid_mod_add_groups'], ':<br><span class="smalltext">', $txt['paid_mod_add_groups_desc'], '</span>
					</dt>
					<dd>';

	// Put a checkbox in for each group
	foreach ($context['groups'] as $id => $name)
		echo '
						<label for="addgroup_', $id, '"><input type="checkbox" id="addgroup_', $id, '" name="addgroup[', $id, ']"', in_array($id, $context['sub']['add_groups']) ? ' checked' : '', !empty($context['disable_groups']) ? ' disabled' : '', ' class="input_check">&nbsp;<span class="smalltext">', $name, '</span></label><br>';

	echo '
					</dd>
					<dt>
						', $txt['paid_mod_reminder'], ':<br><span class="smalltext">', $txt['paid_mod_reminder_desc'], ' ', $txt['zero_to_disable'], '</span>
					</dt>
					<dd>
						<input type="number" name="reminder" value="', $context['sub']['reminder'], '" size="6" class="input_text">
					</dd>
					<dt>
						', $txt['paid_mod_email'], ':<br><span class="smalltext">', $txt['paid_mod_email_desc'], '</span>
					</dt>
					<dd>
						<textarea name="emailcomplete" rows="6" cols="40">', $context['sub']['email_complete'], '</textarea>
					</dd>
				</dl>
				<hr>
				<input type="radio" name="duration_type" id="duration_type_fixed" value="fixed"', empty($context['sub']['duration']) || $context['sub']['duration'] == 'fixed' ? ' checked' : '', ' class="input_radio" onclick="toggleDuration(\'fixed\');">
				<strong><label for="duration_type_fixed">', $txt['paid_mod_fixed_price'], '</label></strong>
				<br>
				<div id="fixed_area" ', empty($context['sub']['duration']) || $context['sub']['duration'] == 'fixed' ? '' : 'style="display: none;"', '>
					<fieldset>
						<dl class="settings">
							<dt>
								', $txt['paid_cost'], ' (', str_replace('%1.2f', '', $modSettings['paid_currency_symbol']), '):
							</dt>
							<dd>
								<input type="number" name="cost" value="', empty($context['sub']['cost']['fixed']) ? '0' : $context['sub']['cost']['fixed'], '" size="4" class="input_text">
							</dd>
							<dt>
								', $txt['paid_mod_span'], ':
							</dt>
							<dd>
								<input type="number" name="span_value" value="', $context['sub']['span']['value'], '" size="4" class="input_text">
								<select name="span_unit">
									<option value="D"', $context['sub']['span']['unit'] == 'D' ? ' selected' : '', '>', $txt['paid_mod_span_days'], '</option>
									<option value="W"', $context['sub']['span']['unit'] == 'W' ? ' selected' : '', '>', $txt['paid_mod_span_weeks'], '</option>
									<option value="M"', $context['sub']['span']['unit'] == 'M' ? ' selected' : '', '>', $txt['paid_mod_span_months'], '</option>
									<option value="Y"', $context['sub']['span']['unit'] == 'Y' ? ' selected' : '', '>', $txt['paid_mod_span_years'], '</option>
								</select>
							</dd>
						</dl>
					</fieldset>
				</div>
				<input type="radio" name="duration_type" id="duration_type_flexible" value="flexible"', !empty($context['sub']['duration']) && $context['sub']['duration'] == 'flexible' ? ' checked' : '', ' class="input_radio" onclick="toggleDuration(\'flexible\');">
				<strong><label for="duration_type_flexible">', $txt['paid_mod_flexible_price'], '</label></strong>
				<br>
				<div id="flexible_area" ', !empty($context['sub']['duration']) && $context['sub']['duration'] == 'flexible' ? '' : 'style="display: none;"', '>
					<fieldset>';

	//!! Removed until implemented
	if (!empty($sdflsdhglsdjgs))
		echo '
						<dl class="settings">
							<dt>
								<label for="allow_partial_check">', $txt['paid_mod_allow_partial'], '</label>:<br><span class="smalltext">', $txt['paid_mod_allow_partial_desc'], '</span>
							</dt>
							<dd>
								<input type="checkbox" name="allow_partial" id="allow_partial_check"', empty($context['sub']['allow_partial']) ? '' : ' checked', ' class="input_check">
							</dd>
						</dl>';

	echo '
						<div class="information">
							<strong>', $txt['paid_mod_price_breakdown'], '</strong><br>
							', $txt['paid_mod_price_breakdown_desc'], '
						</div>
						<dl class="settings">
							<dt>
								<strong>', $txt['paid_duration'], '</strong>
							</dt>
							<dd>
								<strong>', $txt['paid_cost'], ' (', preg_replace('~%[df\.\d]+~', '', $modSettings['paid_currency_symbol']), ')</strong>
							</dd>
							<dt>
								', $txt['paid_per_day'], ':
							</dt>
							<dd>
								<input type="number" name="cost_day" value="', empty($context['sub']['cost']['day']) ? '0' : $context['sub']['cost']['day'], '" size="5" class="input_text">
							</dd>
							<dt>
								', $txt['paid_per_week'], ':
							</dt>
							<dd>
								<input type="number" name="cost_week" value="', empty($context['sub']['cost']['week']) ? '0' : $context['sub']['cost']['week'], '" size="5" class="input_text">
							</dd>
							<dt>
								', $txt['paid_per_month'], ':
							</dt>
							<dd>
								<input type="number" name="cost_month" value="', empty($context['sub']['cost']['month']) ? '0' : $context['sub']['cost']['month'], '" size="5" class="input_text">
							</dd>
							<dt>
								', $txt['paid_per_year'], ':
							</dt>
							<dd>
								<input type="number" name="cost_year" value="', empty($context['sub']['cost']['year']) ? '0' : $context['sub']['cost']['year'], '" size="5" class="input_text">
							</dd>
						</dl>
					</fieldset>
				</div>
				<input type="submit" name="save" value="', $txt['paid_settings_save'], '" class="button_submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-pms_token_var'], '" value="', $context['admin-pms_token'], '">
			</div>
		</form>
	</div>';

}

?>