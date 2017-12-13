<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * The admin member list.
 */
function template_admin_browse()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">';

	echo generic_list_helper('approve_list');

	// If we have lots of outstanding members try and make the admin's life easier.
	if ($context['approve_list']['total_num_items'] > 20)
	{
		echo '
		<br>
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=viewmembers" method="post" accept-charset="UTF-8" name="postFormOutstanding" id="postFormOutstanding" onsubmit="return onOutstandingSubmit();">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['admin_browse_outstanding'], '</h3>
			</div>
			<script>
				function onOutstandingSubmit()
				{
					if (document.forms.postFormOutstanding.todo.value == "")
						return;

					var message = "";
					if (document.forms.postFormOutstanding.todo.value.indexOf("delete") != -1)
						message = "', $txt['admin_browse_w_delete'], '";
					else if (document.forms.postFormOutstanding.todo.value.indexOf("reject") != -1)
						message = "', $txt['admin_browse_w_reject'], '";
					else if (document.forms.postFormOutstanding.todo.value == "remind")
						message = "', $txt['admin_browse_w_remind'], '";
					else
						message = "', $context['browse_type'] == 'approve' ? $txt['admin_browse_w_approve'] : $txt['admin_browse_w_activate'], '";

					if (confirm(message + " ', $txt['admin_browse_outstanding_warn'], '"))
						return true;
					else
						return false;
				}
			</script>

			<div class="windowbg">
				<dl class="settings">
					<dt>
						', $txt['admin_browse_outstanding_days_1'], ':
					</dt>
					<dd>
						<input type="text" name="time_passed" value="14" maxlength="4" size="3" class="input_text"> ', $txt['admin_browse_outstanding_days_2'], '.
					</dd>
					<dt>
						', $txt['admin_browse_outstanding_perform'], ':
					</dt>
					<dd>
						<select name="todo">
							', $context['browse_type'] == 'activate' ? '
							<option value="ok">' . $txt['admin_browse_w_activate'] . '</option>' : '', '
							<option value="okemail">', $context['browse_type'] == 'approve' ? $txt['admin_browse_w_approve'] : $txt['admin_browse_w_activate'], ' ', $txt['admin_browse_w_email'], '</option>', $context['browse_type'] == 'activate' ? '' : '
							<option value="require_activation">' . $txt['admin_browse_w_approve_require_activate'] . '</option>', '
							<option value="reject">', $txt['admin_browse_w_reject'], '</option>
							<option value="rejectemail">', $txt['admin_browse_w_reject'], ' ', $txt['admin_browse_w_email'], '</option>
							<option value="delete">', $txt['admin_browse_w_delete'], '</option>
							<option value="deleteemail">', $txt['admin_browse_w_delete'], ' ', $txt['admin_browse_w_email'], '</option>', $context['browse_type'] == 'activate' ? '
							<option value="remind">' . $txt['admin_browse_w_remind'] . '</option>' : '', '
						</select>
					</dd>
				</dl>
				<input type="submit" value="', $txt['admin_browse_outstanding_go'], '" class="button_submit">
				<input type="hidden" name="type" value="', $context['browse_type'], '">
				<input type="hidden" name="sort" value="', $context['approve_list']['sort']['id'], '">
				<input type="hidden" name="start" value="', $context['approve_list']['start'], '">
				<input type="hidden" name="orig_filter" value="', $context['current_filter'], '">
				<input type="hidden" name="sa" value="approve">', !empty($context['approve_list']['sort']['desc']) ? '
				<input type="hidden" name="desc" value="1">' : '', '
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';
	}

	echo '
	</div>';
}

?>