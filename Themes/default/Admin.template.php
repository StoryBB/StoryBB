<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

use LightnCandy\LightnCandy;

/**
 * Template for showing settings (Of any kind really!)
 */
function template_show_settings()
{
	global $context, $txt, $settings, $scripturl;

	if (!empty($context['saved_successful']))
		echo '
					<div class="infobox">', $txt['settings_saved'], '</div>';
	elseif (!empty($context['saved_failed']))
		echo '
					<div class="errorbox">', sprintf($txt['settings_not_saved'], $context['saved_failed']), '</div>';

	if (!empty($context['settings_pre_javascript']))
		echo '
					<script>', $context['settings_pre_javascript'], '</script>';

	if (!empty($context['settings_insert_above']))
		echo $context['settings_insert_above'];

	echo '
					<div id="admincenter">
						<form id="admin_form_wrapper" action="', $context['post_url'], '" method="post" accept-charset="UTF-8"', !empty($context['force_form_onsubmit']) ? ' onsubmit="' . $context['force_form_onsubmit'] . '"' : '', '>';

	// Is there a custom title?
	if (isset($context['settings_title']))
		echo '
							<div class="cat_bar">
								<h3 class="catbg">', $context['settings_title'], '</h3>
							</div>';

	// Have we got a message to display?
	if (!empty($context['settings_message']))
		echo '
							<div class="information">', $context['settings_message'], '</div>';

	// Now actually loop through all the variables.
	$is_open = false;
	foreach ($context['config_vars'] as $config_var)
	{
		// Is it a title or a description?
		if (is_array($config_var) && ($config_var['type'] == 'title' || $config_var['type'] == 'desc'))
		{
			// Not a list yet?
			if ($is_open)
			{
				$is_open = false;
				echo '
									</dl>
							</div>';
			}

			// A title?
			if ($config_var['type'] == 'title')
			{
				echo '
							<div class="cat_bar">
								<h3 class="', !empty($config_var['class']) ? $config_var['class'] : 'catbg', '"', !empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '"' : '', '>
									', ($config_var['help'] ? '<a href="' . $scripturl . '?action=helpadmin;help=' . $config_var['help'] . '" onclick="return reqOverlayDiv(this.href);" class="help"><span class="generic_icons help" title="' . $txt['help'] . '"></span></a>' : ''), '
									', $config_var['label'], '
								</h3>
							</div>';
			}
			// A description?
			else
			{
				echo '
							<div class="information noup">
								', $config_var['label'], '
							</div>';
			}

			continue;
		}

		// Not a list yet?
		if (!$is_open)
		{
			$is_open = true;
			echo '
							<div class="windowbg2 noup">
								<dl class="settings">';
		}

		// Hang about? Are you pulling my leg - a callback?!
		if (is_array($config_var) && $config_var['type'] == 'callback')
		{
			if (function_exists('template_callback_' . $config_var['name']))
				call_user_func('template_callback_' . $config_var['name']);

			continue;
		}

		if (is_array($config_var))
		{
			// First off, is this a span like a message?
			if (in_array($config_var['type'], array('message', 'warning')))
			{
				echo '
									<dd', $config_var['type'] == 'warning' ? ' class="alert"' : '', (!empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '_dd"' : ''), '>
										', $config_var['label'], '
									</dd>';
			}
			// Otherwise it's an input box of some kind.
			else
			{
				echo '
									<dt', is_array($config_var) && !empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '"' : '', '>';

				// Some quick helpers...
				$javascript = $config_var['javascript'];
				$disabled = !empty($config_var['disabled']) ? ' disabled' : '';
				$subtext = !empty($config_var['subtext']) ? '<br><span class="smalltext"> ' . $config_var['subtext'] . '</span>' : '';

				// Various HTML5 input types that are basically enhanced textboxes
				$text_types = array('color', 'date', 'datetime', 'datetime-local', 'email', 'month', 'time');

				// Show the [?] button.
				if ($config_var['help'])
					echo '
							<a id="setting_', $config_var['name'], '_help" href="', $scripturl, '?action=helpadmin;help=', $config_var['help'], '" onclick="return reqOverlayDiv(this.href);"><span class="generic_icons help" title="', $txt['help'], '"></span></a> ';

				echo '
										<a id="setting_', $config_var['name'], '"></a> <span', ($config_var['disabled'] ? ' style="color: #777777;"' : ($config_var['invalid'] ? ' class="error"' : '')), '><label for="', $config_var['name'], '">', $config_var['label'], '</label>', $subtext, ($config_var['type'] == 'password' ? '<br><em>' . $txt['admin_confirm_password'] . '</em>' : ''), '</span>
									</dt>
									<dd', (!empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '_dd"' : ''), '>',
										$config_var['preinput'];

				// Show a check box.
				if ($config_var['type'] == 'check')
					echo '
										<input type="checkbox"', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '"', ($config_var['value'] ? ' checked' : ''), ' value="1" class="input_check">';
				// Escape (via htmlspecialchars.) the text box.
				elseif ($config_var['type'] == 'password')
					echo '
										<input type="password"', $disabled, $javascript, ' name="', $config_var['name'], '[0]"', ($config_var['size'] ? ' size="' . $config_var['size'] . '"' : ''), ' value="*#fakepass#*" onfocus="this.value = \'\'; this.form.', $config_var['name'], '.disabled = false;" class="input_password"><br>
										<input type="password" disabled id="', $config_var['name'], '" name="', $config_var['name'], '[1]"', ($config_var['size'] ? ' size="' . $config_var['size'] . '"' : ''), ' class="input_password">';
				// Show a selection box.
				elseif ($config_var['type'] == 'select')
				{
					echo '
										<select name="', $config_var['name'], '" id="', $config_var['name'], '" ', $javascript, $disabled, (!empty($config_var['multiple']) ? ' multiple="multiple"' : ''), (!empty($config_var['multiple']) && !empty($config_var['size']) ? ' size="' . $config_var['size'] . '"' : ''), '>';
					foreach ($config_var['data'] as $option)
						echo '
											<option value="', $option[0], '"', (!empty($config_var['value']) && ($option[0] == $config_var['value'] || (!empty($config_var['multiple']) && in_array($option[0], $config_var['value']))) ? ' selected' : ''), '>', $option[1], '</option>';
					echo '
										</select>';
				}
				// List of boards? This requires getBoardList() having been run and the results in $context['board_list'].
				elseif ($config_var['type'] == 'boards')
				{
					$first = true;
					echo '
										<a href="#" class="board_selector">[ ', $txt['select_boards_from_list'], ' ]</a>
										<fieldset>
												<legend class="board_selector"><a href="#">', $txt['select_boards_from_list'], '</a></legend>';
					foreach ($context['board_list'] as $id_cat => $cat)
					{
						if (!$first)
							echo '
											<hr>';
						echo '
											<strong>', $cat['name'], '</strong>
											<ul>';
						foreach ($cat['boards'] as $id_board => $brd)
							echo '
												<li><label><input type="checkbox" name="', $config_var['name'], '[', $brd['id'], ']" value="1" class="input_check"', in_array($brd['id'], $config_var['value']) ? ' checked' : '', '> ', $brd['child_level'] > 0 ? str_repeat('&nbsp; &nbsp;', $brd['child_level']) : '', $brd['name'], '</label></li>';

						echo '
											</ul>';
						$first = false;
					}
					echo '
											</fieldset>';
				}
				// Text area?
				elseif ($config_var['type'] == 'large_text')
					echo '
											<textarea rows="', (!empty($config_var['size']) ? $config_var['size'] : (!empty($config_var['rows']) ? $config_var['rows'] : 4)), '" cols="', (!empty($config_var['cols']) ? $config_var['cols'] : 30), '" ', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '">', $config_var['value'], '</textarea>';
				// Permission group?
				elseif ($config_var['type'] == 'permissions')
					theme_inline_permissions($config_var['name']);
				// BBC selection?
				elseif ($config_var['type'] == 'bbc')
				{
					echo '
											<fieldset id="', $config_var['name'], '">
												<legend>', $txt['bbcTagsToUse_select'], '</legend>
													<ul>';

					foreach ($context['bbc_columns'] as $bbcColumn)
					{
						foreach ($bbcColumn as $bbcTag)
							echo '
														<li class="list_bbc floatleft">
															<input type="checkbox" name="', $config_var['name'], '_enabledTags[]" id="tag_', $config_var['name'], '_', $bbcTag['tag'], '" value="', $bbcTag['tag'], '"', !in_array($bbcTag['tag'], $context['bbc_sections'][$config_var['name']]['disabled']) ? ' checked' : '', ' class="input_check"> <label for="tag_', $config_var['name'], '_', $bbcTag['tag'], '">', $bbcTag['tag'], '</label>', $bbcTag['show_help'] ? ' (<a href="' . $scripturl . '?action=helpadmin;help=tag_' . $bbcTag['tag'] . '" onclick="return reqOverlayDiv(this.href);">?</a>)' : '', '
														</li>';
					}
					echo '							</ul>
												<input type="checkbox" id="bbc_', $config_var['name'], '_select_all" onclick="invertAll(this, this.form, \'', $config_var['name'], '_enabledTags\');"', $context['bbc_sections'][$config_var['name']]['all_selected'] ? ' checked' : '', ' class="input_check"> <label for="bbc_', $config_var['name'], '_select_all"><em>', $txt['bbcTagsToUse_select_all'], '</em></label>
											</fieldset>';
				}
				// A simple message?
				elseif ($config_var['type'] == 'var_message')
					echo '
											<div', !empty($config_var['name']) ? ' id="' . $config_var['name'] . '"' : '', '>', $config_var['var_message'], '</div>';
				// Assume it must be a text box
				else
				{
					// Figure out the exact type - use "number" for "float" and "int".
					$type = in_array($config_var['type'], $text_types) ? $config_var['type'] : ($config_var['type'] == 'int' || $config_var['type'] == 'float' ? 'number' : 'text');

					// Extra options for float/int values - how much to decrease/increase by, the min value and the max value
					// The step - only set if incrementing by something other than 1 for int or 0.1 for float
					$step = isset($config_var['step']) ? ' step="' . $config_var['step'] . '"' : ($config_var['type'] == 'float' ? ' step="0.1"' : '');

					// Minimum allowed value for this setting. StoryBB forces a default of 0 if not specified in the settings
					$min = isset($config_var['min']) ? ' min="' . $config_var['min'] . '"' : '';

					// Maximum allowed value for this setting.
					$max = isset($config_var['max']) ? ' max="' . $config_var['max'] . '"' : '';

					echo '
											<input type="', $type, '"', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '" value="', $config_var['value'], '"', ($config_var['size'] ? ' size="' . $config_var['size'] . '"' : ''), ' class="input_text"', $min . $max . $step, '>';
				}

				echo isset($config_var['postinput']) ? '
											' . $config_var['postinput'] : '',
										'</dd>';
			}
		}

		else
		{
			// Just show a separator.
			if ($config_var == '')
				echo '
								</dl>
								<hr>
								<dl class="settings">';
			else
				echo '
									<dd>
										<strong>' . $config_var . '</strong>
									</dd>';
		}
	}

	if ($is_open)
		echo '
								</dl>';

	if (empty($context['settings_save_dont_show']))
		echo '
								<input type="submit" value="', $txt['save'], '"', (!empty($context['save_disabled']) ? ' disabled' : ''), (!empty($context['settings_save_onclick']) ? ' onclick="' . $context['settings_save_onclick'] . '"' : ''), ' class="button_submit">';

	if ($is_open)
		echo '
							</div>';


	// At least one token has to be used!
	if (isset($context['admin-ssc_token']))
		echo '
							<input type="hidden" name="', $context['admin-ssc_token_var'], '" value="', $context['admin-ssc_token'], '">';

	if (isset($context['admin-dbsc_token']))
		echo '
							<input type="hidden" name="', $context['admin-dbsc_token_var'], '" value="', $context['admin-dbsc_token'], '">';

	if (isset($context['admin-mp_token']))
		echo '
							<input type="hidden" name="', $context['admin-mp_token_var'], '" value="', $context['admin-mp_token'], '">';

	echo '
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						</form>
					</div>';

	if (!empty($context['settings_post_javascript']))
		echo '
					<script>
					', $context['settings_post_javascript'], '
					</script>';

	if (!empty($context['settings_insert_below']))
		echo $context['settings_insert_below'];

	// We may have added a board listing. If we did, we need to make it work.
	addInlineJavascript('
	$("legend.board_selector").closest("fieldset").hide();
	$("a.board_selector").click(function(e) {
		e.preventDefault();
		$(this).hide().next("fieldset").show();
	});
	$("fieldset legend.board_selector a").click(function(e) {
		e.preventDefault();
		$(this).closest("fieldset").hide().prev("a").show();
	});
	', true);
}

/**
 * This little beauty shows questions and answer from the captcha type feature.
 */
function template_callback_question_answer_list()
{
	global $txt, $context;

	foreach ($context['languages'] as $lang_id => $lang)
	{
		$lang_id = strtr($lang_id, array('-utf8' => ''));
		$lang['name'] = strtr($lang['name'], array('-utf8' => ''));

		echo '
						<dt id="qa_dt_', $lang_id, '" class="qa_link">
							<a href="javascript:void(0);">[ ', $lang['name'], ' ]</a>
						</dt>
						<fieldset id="qa_fs_', $lang_id, '" class="qa_fieldset">
							<legend><a href="javascript:void(0);">', $lang['name'], '</a></legend>
							<dl class="settings">
								<dt>
									<strong>', $txt['setup_verification_question'], '</strong>
								</dt>
								<dd>
									<strong>', $txt['setup_verification_answer'], '</strong>
								</dd>';

		if (!empty($context['qa_by_lang'][$lang_id]))
			foreach ($context['qa_by_lang'][$lang_id] as $q_id)
			{
				$question = $context['question_answers'][$q_id];
				echo '
								<dt>
									<input type="text" name="question[', $lang_id, '][', $q_id, ']" value="', $question['question'], '" size="50" class="input_text verification_question">
								</dt>
								<dd>';
				foreach ($question['answers'] as $answer)
					echo '
									<input type="text" name="answer[', $lang_id, '][', $q_id, '][]" value="', $answer, '" size="50" class="input_text verification_answer">';

				echo '
									<div class="qa_add_answer"><a href="javascript:void(0);" onclick="return addAnswer(this);">[ ', $txt['setup_verification_add_answer'], ' ]</a></div>
								</dd>';
			}

		echo '
								<dt class="qa_add_question"><a href="javascript:void(0);">[ ', $txt['setup_verification_add_more'], ' ]</a></dt>
							</dl>
						</fieldset>';
	}
}

/**
 * Retrieves info from the php_info function, scrubs and preps it for display
 */
function template_php_info()
{
	global $context, $txt;

	echo '
					<div id="admin_form_wrapper">
						<div id="section_header" class="cat_bar">
							<h3 class="catbg">',
								$txt['phpinfo_settings'], '
							</h3>
						</div>';

	// for each php info area
	foreach ($context['pinfo'] as $area => $php_area)
	{
		echo '
						<table id="', str_replace(' ', '_', $area), '" class="table_grid">
							<thead>
								<tr class="title_bar">
									<th class="equal_table" scope="col"></th>
									<th class="centercol equal_table" scope="col"><strong>', $area, '</strong></th>
									<th class="equal_table" scope="col"></th>
								</tr>
							</thead>
							<tbody>';

		$localmaster = true;

		// and for each setting in this category
		foreach ($php_area as $key => $setting)
		{
			// start of a local / master setting (3 col)
			if (is_array($setting))
			{
				if ($localmaster)
				{
					// heading row for the settings section of this categorys settings
					echo '
								<tr class="title_bar">
									<td class="equal_table"><strong>', $txt['phpinfo_itemsettings'], '</strong></td>
									<td class="equal_table"><strong>', $txt['phpinfo_localsettings'], '</strong></td>
									<td class="equal_table"><strong>', $txt['phpinfo_defaultsettings'], '</strong></td>
								</tr>';
					$localmaster = false;
				}

				echo '
								<tr class="windowbg">
									<td class="equal_table">', $key, '</td>';

				foreach ($setting as $key_lm => $value)
				{
					echo '
									<td class="equal_table">', $value, '</td>';
				}
				echo '
								</tr>';
			}
			// just a single setting (2 col)
			else
			{
				echo '
								<tr class="windowbg">
									<td class="equal_table">', $key, '</td>
									<td colspan="2">', $setting, '</td>
								</tr>';
			}
		}
		echo '
							</tbody>
						</table>
						<br>';
	}

	echo '
					</div>';
}

/**
 *
 */
function template_clean_cache_button_above()
{
}

/**
 * Content shown below the clean cache button?
 */
function template_clean_cache_button_below()
{
	global $txt, $scripturl, $context;

	echo '
					<div class="cat_bar">
						<h3 class="catbg">', $txt['maintain_cache'], '</h3>
					</div>
					<div class="windowbg2 noup">
						<form action="', $scripturl, '?action=admin;area=maintain;sa=routine;activity=cleancache" method="post" accept-charset="UTF-8">
							<p>', $txt['maintain_cache_info'], '</p>
							<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit"></span>
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
							<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '">
						</form>
					</div>';
}

?>