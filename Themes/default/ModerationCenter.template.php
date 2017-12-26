<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Show a list of all the unapproved posts
 */
function template_unapproved_posts()
{
	global $options, $context, $txt, $scripturl;

	// Just a big table of it all really...
	echo '
	<div id="modcenter">
	<form action="', $scripturl, '?action=moderate;area=postmod;start=', $context['start'], ';sa=', $context['current_view'], '" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['mc_unapproved_posts'], '</h3>
		</div>';

	// Make up some buttons
	$approve_button = create_button('approve', 'approve', 'approve');
	$remove_button = create_button('delete', 'remove_message', 'remove');

	// No posts?
	if (empty($context['unapproved_items']))
		echo '
		<div class="windowbg2">
			<p class="centertext">', $txt['mc_unapproved_' . $context['current_view'] . '_none_found'], '</p>
		</div>';
	else
		echo '
			<div class="pagesection floatleft">
				', $context['page_index'], '
			</div>';

	foreach ($context['unapproved_items'] as $item)
	{
		echo '
		<div class="windowbg clear">
			<div class="counter">', $item['counter'], '</div>
			<div class="topic_details">
				<h5><strong>', $item['category']['link'], ' / ', $item['board']['link'], ' / ', $item['link'], '</strong></h5>
				<span class="smalltext"><strong>', $txt['mc_unapproved_by'], ' ', $item['poster']['link'], ' ', $txt['on'], ':</strong> ', $item['time'], '</span>
			</div>
			<div class="list_posts">
				<div class="post">', $item['body'], '</div>
			</div>
			<span class="floatright">
				<a href="', $scripturl, '?action=moderate;area=postmod;sa=', $context['current_view'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], ';approve=', $item['id'], '">', $approve_button, '</a>';

		if ($item['can_delete'])
			echo '
			', $context['menu_separator'], '
				<a href="', $scripturl, '?action=moderate;area=postmod;sa=', $context['current_view'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], ';delete=', $item['id'], '">', $remove_button, '</a>';

		echo '
				<input type="checkbox" name="item[]" value="', $item['id'], '" checked class="input_check"> ';

		echo '
			</span>
		</div>';
	}

	echo '
		<div class="pagesection">';

	echo '
			<div class="floatright">
				<select name="do" onchange="if (this.value != 0 &amp;&amp; confirm(\'', $txt['mc_unapproved_sure'], '\')) submit();">
					<option value="0">', $txt['with_selected'], ':</option>
					<option value="0" disabled>-------------------</option>
					<option value="approve">&nbsp;--&nbsp;', $txt['approve'], '</option>
					<option value="delete">&nbsp;--&nbsp;', $txt['delete'], '</option>
				</select>
				<noscript><input type="submit" name="mc_go" value="', $txt['go'], '" class="button_submit"></noscript>
			</div>';

	if (!empty($context['unapproved_items']))
		echo '
			<div class="floatleft">
				<div class="pagelinks">', $context['page_index'], '</div>
			</div>';

	echo '
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>
	</div>';
}

/**
 * Callback function for showing a watched users post in the table.
 *
 * @param array $post An array of data about the post.
 * @return string An array of HTML for showing the post info.
 */
function template_user_watch_post_callback($post)
{
	global $scripturl, $context, $txt, $delete_button;

	// We'll have a delete please bob.
	if (empty($delete_button))
		$delete_button = create_button('delete', 'remove_message', 'remove', 'class="centericon"');

	$output_html = '
					<div>
						<div class="floatleft">
							<strong><a href="' . $scripturl . '?topic=' . $post['id_topic'] . '.' . $post['id'] . '#msg' . $post['id'] . '">' . $post['subject'] . '</a></strong> ' . $txt['mc_reportedp_by'] . ' <strong>' . $post['author_link'] . '</strong>
						</div>
						<div class="floatright">';

	if ($post['can_delete'])
		$output_html .= '
							<a href="' . $scripturl . '?action=moderate;area=userwatch;sa=post;delete=' . $post['id'] . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" data-confirm="' . $txt['mc_watched_users_delete_post'] . '" class="you_sure">' . $delete_button . '</a>
							<input type="checkbox" name="delete[]" value="' . $post['id'] . '" class="input_check">';

	$output_html .= '
						</div>
					</div><br>
					<div class="smalltext">
						&#171; ' . $txt['mc_watched_users_posted'] . ': ' . $post['poster_time'] . ' &#187;
					</div>
					<hr>
					' . $post['body'];

	return $output_html;
}

/**
 * Add or edit a warning template.
 */
function template_warn_template()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="modcenter">
		<form action="', $scripturl, '?action=moderate;area=warnings;sa=templateedit;tid=', $context['id_template'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', $context['page_title'], '</h3>
			</div>
			<div class="information">
				', $txt['mc_warning_template_desc'], '
			</div>
			<div class="windowbg">
				<div class="errorbox"', empty($context['warning_errors']) ? ' style="display: none"' : '', ' id="errors">
					<dl>
						<dt>
							<strong id="error_serious">', $txt['error_while_submitting'], '</strong>
						</dt>
						<dd class="error" id="error_list">
							', empty($context['warning_errors']) ? '' : implode('<br>', $context['warning_errors']), '
						</dd>
					</dl>
				</div>
				<div id="box_preview"', !empty($context['template_preview']) ? '' : ' style="display:none"', '>
					<dl class="settings">
						<dt>
							<strong>', $txt['preview'], '</strong>
						</dt>
						<dd id="template_preview">
							', !empty($context['template_preview']) ? $context['template_preview'] : '', '
						</dd>
					</dl>
				</div>
				<dl class="settings">
					<dt>
						<strong><label for="template_title">', $txt['mc_warning_template_title'], '</label>:</strong>
					</dt>
					<dd>
						<input type="text" id="template_title" name="template_title" value="', $context['template_data']['title'], '" size="30" class="input_text">
					</dd>
					<dt>
						<strong><label for="template_body">', $txt['profile_warning_notify_body'], '</label>:</strong><br>
						<span class="smalltext">', $txt['mc_warning_template_body_desc'], '</span>
					</dt>
					<dd>
						<textarea id="template_body" name="template_body" rows="10" cols="45" class="smalltext">', $context['template_data']['body'], '</textarea>
					</dd>
				</dl>';

	if ($context['template_data']['can_edit_personal'])
		echo '
				<input type="checkbox" name="make_personal" id="make_personal"', $context['template_data']['personal'] ? ' checked' : '', ' class="input_check">
					<label for="make_personal">
						<strong>', $txt['mc_warning_template_personal'], '</strong>
					</label>
					<br>
					<span class="smalltext">', $txt['mc_warning_template_personal_desc'], '</span>
					<br>';

	echo '
				<hr>
				<input type="submit" name="preview" id="preview_button" value="', $txt['preview'], '" class="button_submit">
				<input type="submit" name="save" value="', $context['page_title'], '" class="button_submit">
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['mod-wt_token_var'], '" value="', $context['mod-wt_token'], '">
		</form>
	</div>

	<script>
		$(document).ready(function() {
			$("#preview_button").click(function() {
				return ajax_getTemplatePreview();
			});
		});

		function ajax_getTemplatePreview ()
		{
			$.ajax({
				type: "POST",
				url: "' . $scripturl . '?action=xmlhttp;sa=previews;xml",
				data: {item: "warning_preview", title: $("#template_title").val(), body: $("#template_body").val(), user: $(\'input[name="u"]\').attr("value")},
				context: document.body,
				success: function(request){
					$("#box_preview").css({display:""});
					$("#template_preview").html($(request).find(\'body\').text());
					if ($(request).find("error").text() != \'\')
					{
						$("#errors").css({display:""});
						var errors_html = \'\';
						var errors = $(request).find(\'error\').each(function() {
							errors_html += $(this).text() + \'<br>\';
						});

						$(document).find("#error_list").html(errors_html);
					}
					else
					{
						$("#errors").css({display:"none"});
						$("#error_list").html(\'\');
					}
				return false;
				},
			});
			return false;
		}
	</script>';
}

?>