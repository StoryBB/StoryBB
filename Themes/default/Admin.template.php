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
 * This little beauty shows questions and answer from the captcha type feature.
 */
function template_callback_question_answer_list()
{
	global $txt, $context;

	$template = loadTemplatePartial('admin_setting_question_answer_list');
	$phpStr = compileTemplate($template);
	$renderer = LightnCandy::prepare($phpStr);
	echo $renderer([
		'txt' => $txt,
		'context' => $context,
	]);
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