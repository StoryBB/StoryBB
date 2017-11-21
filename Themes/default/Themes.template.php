<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */


/**
 * This lets you reset themes
 */
function template_reset_list()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['themeadmin_reset_title'], '</h3>
		</div>
		<div class="information">
			', $txt['themeadmin_reset_tip'], '
		</div>
		<div id="admin_form_wrapper">';

	// Show each theme.... with X for delete and a link to settings.
	foreach ($context['themes'] as $theme)
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', $theme['name'], '</h3>
			</div>
			<div class="windowbg2 noup">
				<ul>
					<li>
						<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=reset">', $txt['themeadmin_reset_defaults'], '</a> <em class="smalltext">(', $theme['num_default_options'], ' ', $txt['themeadmin_reset_defaults_current'], ')</em>
					</li>
					<li>
						<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=reset;who=1">', $txt['themeadmin_reset_members'], '</a>
					</li>
					<li>
						<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=reset;who=2;', $context['admin-stor_token_var'], '=', $context['admin-stor_token'], '" data-confirm="', $txt['themeadmin_reset_remove_confirm'], '" class="you_sure">', $txt['themeadmin_reset_remove'], '</a> <em class="smalltext">(', $theme['num_members'], ' ', $txt['themeadmin_reset_remove_current'], ')</em>
					</li>
				</ul>
			</div>';
	}

	echo '
		</div>
	</div>';
}

/**
 * This displays the form for setting theme options
 */
function template_set_options()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=theme;th=', $context['theme_settings']['theme_id'], ';sa=reset" method="post" accept-charset="UTF-8">
			<input type="hidden" name="who" value="', $context['theme_options_reset'] ? 1 : 0, '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['theme_options_title'], ' - ', $context['theme_settings']['name'], '</h3>
			</div>
			<div class="information noup">
				', $context['theme_options_reset'] ? $txt['themeadmin_reset_options_info'] : $txt['theme_options_defaults'], '
			</div>
			<div class="windowbg2 noup">';
	echo '
				<dl class="settings">';

	$skeys = array_keys($context['options']);
	$first_option_key = array_shift($skeys);
	$titled_section = false;

	foreach ($context['options'] as $i => $setting)
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

		echo '
					<dt ', $context['theme_options_reset'] ? 'style="width:50%"' : '', '>';

		// Show the change option box ?
		if ($context['theme_options_reset'])
			echo '
						<span class="floatleft"><select name="', !empty($setting['default']) ? 'default_' : '', 'options_master[', $setting['id'], ']" onchange="this.form.options_', $setting['id'], '.disabled = this.selectedIndex != 1;">
							<option value="0" selected>', $txt['themeadmin_reset_options_none'], '</option>
							<option value="1">', $txt['themeadmin_reset_options_change'], '</option>
							<option value="2">', $txt['themeadmin_reset_options_default'], '</option>
						</select>&nbsp;</span>';

		echo '
						<label for="options_', $setting['id'], '">', !$titled_section ? '<b>' : '', $setting['label'], !$titled_section ? '</b>' : '', '</label>';
		if (isset($setting['description']))
			echo '
						<br><span class="smalltext">', $setting['description'], '</span>';
		echo '
					</dt>';

		// display checkbox options
		if ($setting['type'] == 'checkbox')
		{
			echo '
					<dd ', $context['theme_options_reset'] ? 'style="width:40%"' : '', '>
						<input type="hidden" name="' . (!empty($setting['default']) ? 'default_' : '') . 'options[' . $setting['id'] . ']" value="0">
						<input type="checkbox" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="options_', $setting['id'], '"', !empty($setting['value']) ? ' checked' : '', $context['theme_options_reset'] ? ' disabled' : '', ' value="1" class="input_check floatleft">';
		}
		// how about selection lists, we all love them
		elseif ($setting['type'] == 'list')
		{
			echo '
					<dd ', $context['theme_options_reset'] ? 'style="width:40%"' : '', '>
						&nbsp;<select class="floatleft" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="options_', $setting['id'], '"', $context['theme_options_reset'] ? ' disabled' : '', '>';

			foreach ($setting['options'] as $value => $label)
			{
				echo '
							<option value="', $value, '"', $value == $setting['value'] ? ' selected' : '', '>', $label, '</option>';
			}

			echo '
						</select>';
		}
		// a textbox it is then
		else
		{
			echo '
					<dd ', $context['theme_options_reset'] ? 'style="width:40%"' : '', '>';

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


			echo ' name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="options_', $setting['id'], '" value="', $setting['value'], '"', $setting['type'] == 'number' ? ' size="5"' : '', $context['theme_options_reset'] ? ' disabled' : '', ' class="input_text">';
		}

		// end of this defintion
		echo '
					</dd>';
	}

	// close the option page up
	echo '
				</dl>
				<input type="submit" name="submit" value="', $txt['save'], '" class="button_submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-sto_token_var'], '" value="', $context['admin-sto_token'], '">
			</div>
		</form>
	</div>';
}

/**
 * The page for setting and managing theme settings.
 */
function template_set_settings()
{
	global $context, $settings, $scripturl, $txt;

	echo '
	<div id="admin_form_wrapper">
		<form action="', $scripturl, '?action=admin;area=theme;sa=list;th=', $context['theme_settings']['theme_id'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">
					<a href="', $scripturl, '?action=helpadmin;help=theme_settings" onclick="return reqOverlayDiv(this.href);" class="help"><span class="generic_icons help" title="', $txt['help'], '"></span></a> ', $txt['theme_settings'], ' - ', $context['theme_settings']['name'], '
				</h3>
			</div>
			<br>';

	// @todo Why can't I edit the default theme popup.
	if ($context['theme_settings']['theme_id'] != 1)
		echo '
			<div class="cat_bar">
				<h3 class="catbg config_hd">
					', $txt['theme_edit'], '
				</h3>
			</div>
			<div class="windowbg2 noup">
				<ul>
					<li>
						<a href="', $scripturl, '?action=admin;area=theme;th=', $context['theme_settings']['theme_id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=edit;filename=index.template.php">', $txt['theme_edit_index'], '</a>
					</li>
					<li>
						<a href="', $scripturl, '?action=admin;area=theme;th=', $context['theme_settings']['theme_id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=edit;directory=css">', $txt['theme_edit_style'], '</a>
					</li>
				</ul>
			</div>';

	echo '
			<div class="cat_bar">
				<h3 class="catbg config_hd">
					', $txt['theme_url_config'], '
				</h3>
			</div>
			<div class="windowbg2 noup">
				<dl class="settings">
					<dt>
						<label for="theme_name">', $txt['actual_theme_name'], '</label>
					</dt>
					<dd>
						<input type="text" id="theme_name" name="options[name]" value="', $context['theme_settings']['name'], '" size="32" class="input_text">
					</dd>
					<dt>
						<label for="theme_url">', $txt['actual_theme_url'], '</label>
					</dt>
					<dd>
						<input type="text" id="theme_url" name="options[theme_url]" value="', $context['theme_settings']['actual_theme_url'], '" size="50" style="max-width: 100%; width: 50ex;" class="input_text">
					</dd>
					<dt>
						<label for="images_url">', $txt['actual_images_url'], '</label>
					</dt>
					<dd>
						<input type="text" id="images_url" name="options[images_url]" value="', $context['theme_settings']['actual_images_url'], '" size="50" style="max-width: 100%; width: 50ex;" class="input_text">
					</dd>
					<dt>
						<label for="theme_dir">', $txt['actual_theme_dir'], '</label>
					</dt>
					<dd>
						<input type="text" id="theme_dir" name="options[theme_dir]" value="', $context['theme_settings']['actual_theme_dir'], '" size="50" style="max-width: 100%; width: 50ex;" class="input_text">
					</dd>
				</dl>
			</div>';

	// Do we allow theme variants?
	if (!empty($context['theme_variants']))
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg config_hd">
					', $txt['theme_variants'], '
				</h3>
			</div>
			<div class="windowbg2 noup">
				<dl class="settings">
					<dt>
						<label for="variant">', $txt['theme_variants_default'], '</label>:
					</dt>
					<dd>
						<select id="variant" name="options[default_variant]" onchange="changeVariant(this.value)">';

		foreach ($context['theme_variants'] as $key => $variant)
			echo '
							<option value="', $key, '"', $context['default_variant'] == $key ? ' selected' : '', '>', $variant['label'], '</option>';

		echo '
						</select>
					</dd>
					<dt>
						<label for="disable_user_variant">', $txt['theme_variants_user_disable'], '</label>:
					</dt>
					<dd>
						<input type="hidden" name="options[disable_user_variant]" value="0">
						<input type="checkbox" name="options[disable_user_variant]" id="disable_user_variant"', !empty($context['theme_settings']['disable_user_variant']) ? ' checked' : '', ' value="1" class="input_check">
					</dd>
				</dl>
				<img src="', $context['theme_variants'][$context['default_variant']]['thumbnail'], '" id="variant_preview" alt="">
			</div>';
	}

	echo '
			<div class="cat_bar">
				<h3 class="catbg config_hd">
					', $txt['theme_options'], '
				</h3>
			</div>
			<div class="windowbg2 noup">
				<dl class="settings">';

	$skeys = array_keys($context['settings']);
	$first_setting_key = array_shift($skeys);
	$titled_section = false;

	foreach ($context['settings'] as $i => $setting)
	{
		// Is this a separator?
		if (empty($setting) || !is_array($setting))
		{
			// We don't need a separator before the first list element
			if ($i !== $first_setting_key)
				echo '
				</dl>
				<hr>
				<dl class="settings">';

			// Add a fake heading?
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

		echo '
					<dt>
						<label for="', $setting['id'], '">', !$titled_section ? '<b>' : '', $setting['label'], !$titled_section ? '</b>' : '', '</label>:';

		if (isset($setting['description']))
			echo '<br>
						<span class="smalltext">', $setting['description'], '</span>';

		echo '
					</dt>';

		// A checkbox?
		if ($setting['type'] == 'checkbox')
		{
			echo '
					<dd>
						<input type="hidden" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" value="0">
						<input type="checkbox" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="', $setting['id'], '"', !empty($setting['value']) ? ' checked' : '', ' value="1" class="input_check">
					</dd>';
		}
		// A list with options?
		elseif ($setting['type'] == 'list')
		{
			echo '
					<dd>
						<select name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="', $setting['id'], '">';

			foreach ($setting['options'] as $value => $label)
				echo '
							<option value="', $value, '"', $value == $setting['value'] ? ' selected' : '', '>', $label, '</option>';

			echo '
						</select>
					</dd>';
		}
		// A Textarea?
        	elseif ($setting['type'] == 'textarea')
		{
			echo '
					<dd>
						<textarea rows="4" style="width: 95%;" cols="40" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="', $setting['id'], '">', $setting['value'], '</textarea>';
                echo '
                			</dd>';
		}
		// A regular input box, then?
		else
		{
			echo '
					<dd>';

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

			echo ' name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="options_', $setting['id'], '" value="', $setting['value'], '"', $setting['type'] == 'number' ? ' size="5"' : (empty($settings['size']) ? ' size="40"' : ' size="' . $setting['size'] . '"'), ' class="input_text">
					</dd>';
		}
	}

	echo '
				</dl>
				<input type="submit" name="save" value="', $txt['save'], '" class="button_submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-sts_token_var'], '" value="', $context['admin-sts_token'], '">
			</div>
		</form>
	</div>';

	if (!empty($context['theme_variants']))
	{
		echo '
		<script>
		var oThumbnails = {';

		// All the variant thumbnails.
		$count = 1;
		foreach ($context['theme_variants'] as $key => $variant)
		{
			echo '
			\'', $key, '\': \'', $variant['thumbnail'], '\'', (count($context['theme_variants']) == $count ? '' : ',');
			$count++;
		}

		echo '
		}
		</script>';
	}
}

/**
 * Okay, that theme was installed/updated successfully!
 */
function template_installed()
{
	global $context, $scripturl, $txt;

	// The aftermath.
	echo '
	<div id="admincenter">
		<div class="cat_bar">
			<h3 class="catbg">', $context['page_title'], '</h3>
		</div>
		<div class="windowbg">';

	// Oops! there was an error :(
	if (!empty($context['error_message']))
		echo '
			<p>
				', $context['error_message'], '
			</p>';

	// Not much to show except a link back...
	else
		echo '
			<p>
				<a href="', $scripturl, '?action=admin;area=theme;sa=list;th=', $context['installed_theme']['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $context['installed_theme']['name'], '</a> ', $txt['theme_' . (isset($context['installed_theme']['updated']) ? 'updated' : 'installed') . '_message'], '
			</p>
			<p>
				<a href="', $scripturl, '?action=admin;area=theme;sa=admin;', $context['session_var'], '=', $context['session_id'], '">', $txt['back'], '</a>
			</p>';

	echo '
		</div>
	</div>';
}

/**
 * The page allowing you to copy a template from one theme to another.
 */
function template_copy_template()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['themeadmin_edit_filename'], '</h3>
		</div>
		<div class="information">
			', $txt['themeadmin_edit_copy_warning'], '
		</div>
		<div class="windowbg">
			<ul class="theme_options">';

	foreach ($context['available_templates'] as $template)
	{
		echo '
				<li class="flow_hidden windowbg">
					<span class="floatleft">', $template['filename'], $template['already_exists'] ? ' <span class="error">(' . $txt['themeadmin_edit_exists'] . ')</span>' : '', '</span>
					<span class="floatright">';

		if ($template['can_copy'])
			echo '<a href="', $scripturl, '?action=admin;area=theme;th=', $context['theme_id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=copy;template=', $template['value'], '" data-confirm="', $template['already_exists'] ? $txt['themeadmin_edit_overwrite_confirm'] : $txt['themeadmin_edit_copy_confirm'], '" class="you_sure">', $txt['themeadmin_edit_do_copy'], '</a>';
		else
			echo $txt['themeadmin_edit_no_copy'];

		echo '
					</span>
				</li>';
	}

	echo '
			</ul>
		</div>
	</div>';
}

?>