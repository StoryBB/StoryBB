<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * The main sub template - for theme administration.
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
	';
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
						';

		echo '
						';
		if (isset($setting['description']))
			echo '
						<br><span class="smalltext">', $setting['description'], '</span>';
		echo '
					</dt>';

		// display checkbox options
		if ($setting['type'] == 'checkbox')
		{
			echo '
					';
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

?>