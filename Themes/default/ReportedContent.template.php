<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Template for viewing and managing a specific report about a user's profile
 */
function template_viewmemberreport()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="modcenter">
		<form action="', $scripturl, '?action=moderate;area=reportedmembers;sa=handlecomment;rid=', $context['report']['id'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">
					', sprintf($txt['mc_viewmemberreport'], $context['report']['user']['link']), '
				</h3>
			</div>
			<div class="title_bar">
				<h3 class="titlebg">
					<span class="floatleft">
						', sprintf($txt['mc_memberreport_summary'], $context['report']['num_reports'], $context['report']['last_updated']), '
					</span>
					<span class="floatright">';

		// Make the buttons.
		$close_button = create_button('close', $context['report']['closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close', $context['report']['closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close');
		$ignore_button = create_button('ignore', 'mc_reportedp_ignore', 'mc_reportedp_ignore');
		$unignore_button = create_button('ignore', 'mc_reportedp_unignore', 'mc_reportedp_unignore');

		echo '
						<a href="', $scripturl, '?action=moderate;area=reportedmembers;sa=handle;ignore=', (int) !$context['report']['ignore'], ';rid=', $context['report']['id'], ';', $context['session_var'], '=', $context['session_id'], ';', $context['mod-report-ignore_token_var'], '=', $context['mod-report-ignore_token'], '" class="button', (!$context['report']['ignore'] ? ' you_sure' : ''), '"', (!$context['report']['ignore'] ? ' data-confirm="' . $txt['mc_reportedp_ignore_confirm'] . '"' : ''), '>', $context['report']['ignore'] ? $unignore_button : $ignore_button, '</a>
						<a href="', $scripturl, '?action=moderate;area=reportedmembers;sa=handle;closed=', (int) !$context['report']['closed'], ';rid=', $context['report']['id'], ';', $context['session_var'], '=', $context['session_id'], ';', $context['mod-report-closed_token_var'], '=', $context['mod-report-closed_token'], '"  class="button">', $close_button, '</a>
					</span>
				</h3>
			</div>
			<br>
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mc_memberreport_whoreported_title'], '</h3>
			</div>';

	foreach ($context['report']['comments'] as $comment)
		echo '
			<div class="windowbg">
				<p class="smalltext">', sprintf($txt['mc_modreport_whoreported_data'], $comment['member']['link'] . (empty($comment['member']['id']) && !empty($comment['member']['ip']) ? ' (' . $comment['member']['ip'] . ')' : ''), $comment['time']), '</p>
				<p>', $comment['message'], '</p>
			</div>';

	echo '
			<br>
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mc_modreport_mod_comments'], '</h3>
			</div>
				<div>';

	if (empty($context['report']['mod_comments']))
		echo '
				<div class="information">
					<p class="centertext">', $txt['mc_modreport_no_mod_comment'], '</p>
				</div>';

	foreach ($context['report']['mod_comments'] as $comment)
	{
		echo '
					<div class="title_bar">
						<h3 class="titlebg">', $comment['member']['link'], ':  <em class="smalltext">(', $comment['time'], ')</em>', ($comment['can_edit'] ? '<span class="floatright"><a href="' . $scripturl . '?action=moderate;area=reportedmembers;sa=editcomment;rid=' . $context['report']['id'] . ';mid=' . $comment['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '"  class="button">' . $txt['mc_reportedp_comment_edit'] . '</a> <a href="' . $scripturl . '?action=moderate;area=reportedmembers;sa=handlecomment;rid=' . $context['report']['id'] . ';mid=' . $comment['id'] . ';delete;' . $context['session_var'] . '=' . $context['session_id'] . ';' . $context['mod-reportC-delete_token_var'] . '=' . $context['mod-reportC-delete_token'] . '"  class="button you_sure" data-confirm="' . $txt['mc_reportedp_delete_confirm'] . '">' . $txt['mc_reportedp_comment_delete'] . '</a></span>' : ''), '</h3>
					</div>';

		echo '
						<div class="windowbg2">
							<p>', $comment['message'], '</p>
						</div>';
	}

	echo '
					<div class="cat_bar">
						<h3 class="catbg">
							<span class="floatleft">
								', $txt['mc_reportedp_new_comment'], '
							</span>
						</h3>
					</div>
					<textarea rows="2" cols="60" style="width: 60%;" name="mod_comment"></textarea>
					<div class="padding">
						<input type="submit" name="add_comment" value="', $txt['mc_modreport_add_mod_comment'], '" class="button_submit">
						<input type="hidden" name="', $context['mod-reportC-add_token_var'], '" value="', $context['mod-reportC-add_token'], '">
					</div>
				</div>
			<br>';

	echo StoryBB\Template\Helper\Controls::genericlist('moderation_actions_list');

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>';
}

?>