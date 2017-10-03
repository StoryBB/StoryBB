<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Add or edit a search engine spider.
 */
function template_spider_edit()
{
	global $context, $scripturl, $txt;
	echo '
	<div id="admincenter">
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=sengines;sa=editspiders;sid=', $context['spider']['id'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', $context['page_title'], '</h3>
			</div>
			<div class="information noup">
				', $txt['add_spider_desc'], '
			</div>
			<div class="windowbg2 noup">
				<dl class="settings">
					<dt>
						<strong><label for="spider_name">', $txt['spider_name'], ':</label></strong><br>
						<span class="smalltext">', $txt['spider_name_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="spider_name" id="spider_name" value="', $context['spider']['name'], '" class="input_text">
					</dd>
					<dt>
						<strong><label for="spider_agent">', $txt['spider_agent'], ':</label></strong><br>
						<span class="smalltext">', $txt['spider_agent_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="spider_agent" id="spider_agent" value="', $context['spider']['agent'], '" class="input_text">
					</dd>
					<dt>
						<strong><label for="spider_ip">', $txt['spider_ip_info'], ':</label></strong><br>
						<span class="smalltext">', $txt['spider_ip_info_desc'], '</span>
					</dt>
					<dd>
						<textarea name="spider_ip" id="spider_ip" rows="4" cols="20">', $context['spider']['ip_info'], '</textarea>
					</dd>
				</dl>
				<hr>
				<input type="submit" name="save" value="', $context['page_title'], '" class="button_submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-ses_token_var'], '" value="', $context['admin-ses_token'], '">
			</div>
		</form>
	</div>';
}

/**
 * Show... spider... logs...
 */
function template_show_spider_logs()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">';

	// Standard fields.
	template_show_list('spider_logs');

	echo '
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=sengines;sa=logs" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['spider_logs_delete'], '</h3>
			</div>
			<div class="windowbg2 noup">
				<p>
					', $txt['spider_logs_delete_older'], '
					<input type="text" name="older" id="older" value="7" size="3" class="input_text">
					', $txt['spider_logs_delete_day'], '
				</p>
				<input type="submit" name="delete_entries" value="', $txt['spider_logs_delete_submit'], '" onclick="if (document.getElementById(\'older\').value &lt; 1 &amp;&amp; !confirm(\'' . addcslashes($txt['spider_logs_delete_confirm'], "'") . '\')) return false; return true;" class="button_submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-sl_token_var'], '" value="', $context['admin-sl_token'], '">
			</div>
		</form>
	</div>';
}

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