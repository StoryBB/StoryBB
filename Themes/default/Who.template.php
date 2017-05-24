<?php
require(__DIR__ . '/../../vendor/autoload.php');
require(__DIR__ . '/helpers/logichelpers.php');
require(__DIR__ . '/helpers/stringhelpers.php');
use LightnCandy\LightnCandy;
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2017 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 3
 */

/**
 * This handles the Who's Online page
 */
function template_main()
{
	global $context, $settings, $scripturl, $txt;

	// Display the table header and linktree.
	echo '
	<div class="main_section" id="whos_online">
		<form action="', $scripturl, '?action=who" method="post" id="whoFilter" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['who_title'], '</h3>
			</div>
			<div id="mlist">
				<div class="pagesection">
					<div class="pagelinks floatleft">', $context['page_index'], '</div>';
	echo '
					<div class="selectbox floatright" id="upper_show">', $txt['who_show1'], '
						<select name="show_top" onchange="document.forms.whoFilter.show.value = this.value; document.forms.whoFilter.submit();">';

	foreach ($context['show_methods'] as $value => $label)
		echo '
							<option value="', $value, '" ', $value == $context['show_by'] ? ' selected' : '', '>', $label, '</option>';
	echo '
						</select>
						<noscript>
							<input type="submit" name="submit_top" value="', $txt['go'], '" class="button_submit">
						</noscript>
					</div>
				</div>
				<table class="table_grid">
					<thead>
						<tr class="title_bar">
							<th scope="col" class="lefttext" style="width: 40%;"><a href="', $scripturl, '?action=who;start=', $context['start'], ';show=', $context['show_by'], ';sort=user', $context['sort_direction'] != 'down' && $context['sort_by'] == 'user' ? '' : ';asc', '" rel="nofollow">', $txt['who_user'], $context['sort_by'] == 'user' ? '<span class="generic_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a></th>
							<th scope="col" class="lefttext time" style="width: 10%;"><a href="', $scripturl, '?action=who;start=', $context['start'], ';show=', $context['show_by'], ';sort=time', $context['sort_direction'] == 'down' && $context['sort_by'] == 'time' ? ';asc' : '', '" rel="nofollow">', $txt['who_time'], $context['sort_by'] == 'time' ? '<span class="generic_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a></th>
							<th scope="col" class="lefttext half_table">', $txt['who_action'], '</th>
						</tr>
					</thead>
					<tbody>';

	foreach ($context['members'] as $member)
	{
		echo '
						<tr class="windowbg">
							<td>';

		// Guests can't be messaged.
		if (!$member['is_guest'])
		{
			echo '
								<span class="contact_info floatright">
									', $context['can_send_pm'] ? '<a href="' . $member['online']['href'] . '" title="' . $member['online']['text'] . '">' : '', $settings['use_image_buttons'] ? '<span class="' . ($member['online']['is_online'] == 1 ? 'on' : 'off') . '" title="' . $member['online']['text'] . '"></span>' : $member['online']['label'], $context['can_send_pm'] ? '</a>' : '', '
								</span>';
		}

		echo '
								<span class="member', $member['is_hidden'] ? ' hidden' : '', '">
									', $member['is_guest'] ? $member['name'] : '<a href="' . $member['href'] . '" title="' . $txt['profile_of'] . ' ' . $member['name'] . '"' . (empty($member['color']) ? '' : ' style="color: ' . $member['color'] . '"') . '>' . $member['name'] . '</a>', '
								</span>';

		if (!empty($member['ip']))
			echo '
								(<a href="' . $scripturl . '?action=', ($member['is_guest'] ? 'trackip' : 'profile;area=tracking;sa=ip;u=' . $member['id']), ';searchip=' . $member['ip'] . '">' . $member['ip'] . '</a>)';

		echo '
							</td>
							<td class="time">', $member['time'], '</td>
							<td>', $member['action'], '</td>
						</tr>';
	}

	// No members?
	if (empty($context['members']))
	{
		echo '
						<tr class="windowbg">
							<td colspan="3">
							', $txt['who_no_online_' . ($context['show_by'] == 'guests' || $context['show_by'] == 'spiders' ? $context['show_by'] : 'members')], '
							</td>
						</tr>';
	}

	echo '
					</tbody>
				</table>
				<div class="pagesection" id="lower_pagesection">
					<div class="pagelinks floatleft" id="lower_pagelinks">', $context['page_index'], '</div>';

	echo '
					<div class="selectbox floatright">', $txt['who_show1'], '
						<select name="show" onchange="document.forms.whoFilter.submit();">';

	foreach ($context['show_methods'] as $value => $label)
		echo '
							<option value="', $value, '" ', $value == $context['show_by'] ? ' selected' : '', '>', $label, '</option>';
	echo '
						</select>
						<noscript>
							<input type="submit" value="', $txt['go'], '" class="button_submit">
						</noscript>
					</div>
				</div>
			</div>
		</form>
	</div>';
}

/**
 * This displays a nice credits page
 */
function template_credits()
{
	global $context, $txt;

	$data = [
		'context' => $context,
		'txt' => $txt,
	];

	$template = file_get_contents(__DIR__ .  "/templates/credits.hbs");
	if (!$template) {
		die('Member template did not load!');
	}

	$phpStr = LightnCandy::compile($template, [
		'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
		'helpers' => [
			'or' => 'logichelper_or',
			'implode_and' => 'implode_and',
		],
	]);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

?>