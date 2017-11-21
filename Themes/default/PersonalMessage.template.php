<?php

use LightnCandy\LightnCandy;
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */


/**
 * Template for reporting a personal message.
 */
function template_report_message()
{
	global $context, $txt, $scripturl;

	echo '
	<form action="', $scripturl, '?action=pm;sa=report;l=', $context['current_label_id'], '" method="post" accept-charset="UTF-8">
		<input type="hidden" name="pmsg" value="', $context['pm_id'], '">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['pm_report_title'], '</h3>
		</div>
		<div class="information">
			', $txt['pm_report_desc'], '
		</div>
		<div class="windowbg">
			<dl class="settings">';

	// If there is more than one admin on the forum, allow the user to choose the one they want to direct to.
	// @todo Why?
	if ($context['admin_count'] > 1)
	{
		echo '
				<dt>
					<strong>', $txt['pm_report_admins'], ':</strong>
				</dt>
				<dd>
					<select name="id_admin">
						<option value="0">', $txt['pm_report_all_admins'], '</option>';
		foreach ($context['admins'] as $id => $name)
			echo '
						<option value="', $id, '">', $name, '</option>';
		echo '
					</select>
				</dd>';
	}

	echo '
				<dt>
					<strong>', $txt['pm_report_reason'], ':</strong>
				</dt>
				<dd>
					<textarea name="reason" rows="4" cols="70" style="width: 80%;"></textarea>
				</dd>
			</dl>
			<div class="righttext">
				<input type="submit" name="report" value="', $txt['pm_report_message'], '" class="button_submit">
			</div>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';
}

/**
 * Little template just to say "Yep, it's been submitted"
 */
function template_report_message_complete()
{
	global $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['pm_report_title'], '</h3>
		</div>
		<div class="windowbg">
			<p>', $txt['pm_report_done'], '</p>
			<a href="', $scripturl, '?action=pm;l=', $context['current_label_id'], '">', $txt['pm_report_return'], '</a>
		</div>';
}

?>