<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */


/**
 * Show... spider... stats...
 */
function template_show_spider_stats()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">';

	// Standard fields.
	template_show_list('spider_stat_list');

	echo '
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=sengines;sa=stats" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['spider_logs_delete'], '</h3>
			</div>
			<div class="windowbg2 noup">
				<p>
					', sprintf($txt['spider_stats_delete_older'], '<input type="text" name="older" id="older" value="90" size="3" class="input_text">'), '
				</p>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-ss_token_var'], '" value="', $context['admin-ss_token'], '">
				<input type="submit" name="delete_entries" value="', $txt['spider_logs_delete_submit'], '" onclick="if (document.getElementById(\'older\').value &lt; 1 &amp;&amp; !confirm(\'' . addcslashes($txt['spider_logs_delete_confirm'], "'") . '\')) return false; return true;" class="button_submit">
				<br>
			</div>
		</form>
	</div>';
}

?>