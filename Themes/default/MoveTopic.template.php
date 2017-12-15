<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Show an interface for selecting which board to move a post to.
 */
function template_move()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="move_topic" class="lower_padding">
		<form action="', $scripturl, '?action=movetopic2;current_board=' . $context['current_board'] . ';topic=', $context['current_topic'], '.0" method="post" accept-charset="UTF-8" onsubmit="submitonce(this);">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['move_topic'], '</h3>
			</div>
			<div class="windowbg centertext">
				<div class="move_topic">
					<dl class="settings">
						<dt>
							<strong>', $txt['move_to'], ':</strong>
						</dt>
						<dd>
							<select name="toboard">';

	foreach ($context['categories'] as $category)
	{
		echo '
								<optgroup label="', $category['name'], '">';

		foreach ($category['boards'] as $board)
			echo '
									<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', $board['id'] == $context['current_board'] ? ' disabled' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level']-1) . '=&gt; ' : '', $board['name'], '</option>';
		echo '
								</optgroup>';
	}

	echo '
							</select>
						</dd>';

	// Disable the reason textarea when the postRedirect checkbox is unchecked...
	echo '
					</dl>
					<label for="reset_subject"><input type="checkbox" name="reset_subject" id="reset_subject" onclick="document.getElementById(\'subjectArea\').style.display = this.checked ? \'block\' : \'none\';" class="input_check"> ', $txt['movetopic_change_subject'], '.</label><br>
   					<fieldset id="subjectArea" style="display: none;">
						<dl class="settings">
							<dt><strong>', $txt['movetopic_new_subject'], ':</strong></dt>
							<dd><input type="text" name="custom_subject" size="30" value="', $context['subject'], '" class="input_text"></dd>
						</dl>
						<label for="enforce_subject"><input type="checkbox" name="enforce_subject" id="enforce_subject" class="input_check"> ', $txt['movetopic_change_all_subjects'], '.</label>
					</fieldset>';

	// Stick our "create a redirection topic" template in here...
	template_redirect_options('move');

    echo '
					<input type="submit" value="', $txt['move_topic'], '" onclick="return submitThisOnce(this);" accesskey="s" class="button_submit">
				</div>
			</div>';

	if ($context['back_to_topic'])
		echo '
			<input type="hidden" name="goback" value="1">';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '">
		</form>
	</div>';
}

/**
 * Redirection topic options
 *
 * @var string $type What type of topic this is for - currently 'merge' or 'move'. Used to display appropriate text strings...
 */
function template_redirect_options($type)
{
    global $txt, $context, $modSettings;

    echo '
					<label for="postRedirect"><input type="checkbox" name="postRedirect" id="postRedirect"', $context['is_approved'] ? ' checked' : '', ' onclick="', $context['is_approved'] ? '' : 'if (this.checked && !confirm(\'' . $txt[$type . '_topic_unapproved_js'] . '\')) return false; ', 'document.getElementById(\'reasonArea\').style.display = this.checked ? \'block\' : \'none\';" class="input_check"> ', $txt['post_redirection'], '.</label>
					<fieldset id="reasonArea" style="margin-top: 1ex;', $context['is_approved'] ? '' : 'display: none;', '">
						<dl class="settings">
							<dt>
								', $txt[$type . '_why'], '
							</dt>
							<dd>
								<textarea name="reason">', $txt[$type . 'topic_default'], '</textarea>
							</dd>
							<dt>
								<label for="redirect_topic">', $txt[$type . 'topic_redirect'], '</label>
							</dt>
							<dd>
								<input type="checkbox" name="redirect_topic" id="redirect_topic" checked class="input_check">
							</dd>';
	if (!empty($modSettings['allow_expire_redirect']))
	{
		echo '
							<dt>
								', $txt['redirect_topic_expires'], '
							</dt>
							<dd>
								<select name="redirect_expires">
									<option value="0">', $txt['never'], '</option>
									<option value="1440">', $txt['one_day'], '</option>
									<option value="10080" selected>', $txt['one_week'], '</option>
									<option value="20160">', $txt['two_weeks'], '</option>
									<option value="43200">', $txt['one_month'], '</option>
									<option value="86400">', $txt['two_months'], '</option>
								</select>
							</dd>';
	}
	else
	{
		echo '
							<input type="hidden" name="redirect_expires" value="0">';
	}

	echo '
						</dl>
					</fieldset>';
}

/**
 * Extra options related to merging topics.
 */
function template_merge_extra_options()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="merge_topics">
		<form action="', $scripturl, '?action=mergetopics;sa=execute;" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['merge_topic_list'], '</h3>
			</div>
			<table class="bordercolor table_grid">
				<thead>
					<tr class="title_bar">
						<th scope="col" style="width:10px;">', $txt['merge_check'], '</th>
						<th scope="col" class="lefttext">', $txt['subject'], '</th>
						<th scope="col" class="lefttext">', $txt['started_by'], '</th>
						<th scope="col" class="lefttext">', $txt['last_post'], '</th>
						<th scope="col" style="width:20px;">' . $txt['merge_include_notifications'] . '</th>
					</tr>
				</thead>
				<tbody>';
		foreach ($context['topics'] as $topic)
			echo '
					<tr class="windowbg">
						<td>
							<input type="checkbox" class="input_check" name="topics[]" value="' . $topic['id'] . '" checked>
						</td>
						<td>
							<a href="' . $scripturl . '?topic=' . $topic['id'] . '.0" target="_blank" class="new_win">' . $topic['subject'] . '</a>
						</td>
						<td>
							', $topic['started']['link'], '<br>
							<span class="smalltext">', $topic['started']['time'], '</span>
						</td>
						<td>
							' . $topic['updated']['link'] . '<br>
							<span class="smalltext">', $topic['updated']['time'], '</span>
						</td>
						<td>
							<input type="checkbox" class="input_check" name="notifications[]" value="' . $topic['id'] . '" checked>
						</td>
					</tr>';
		echo '
				</tbody>
			</table>
			<br>
			<div class="windowbg">';

	echo '
				<fieldset id="merge_subject" class="merge_options">
					<legend>', $txt['merge_select_subject'], '</legend>
					<select name="subject" onchange="this.form.custom_subject.style.display = (this.options[this.selectedIndex].value != 0) ? \'none\': \'\' ;">';
	foreach ($context['topics'] as $topic)
		echo '
						<option value="', $topic['id'], '"' . ($topic['selected'] ? ' selected' : '') . '>', $topic['subject'], '</option>';
	echo '
						<option value="0">', $txt['merge_custom_subject'], ':</option>
					</select>
					<br><input type="text" name="custom_subject" size="60" id="custom_subject" class="input_text custom_subject" style="display: none;">
					<br>
					<label for="enforce_subject"><input type="checkbox" class="input_check" name="enforce_subject" id="enforce_subject" value="1"> ', $txt['movetopic_change_all_subjects'], '</label>
				</fieldset>';

	// Show an option to create a redirection topic as well...
	template_redirect_options('merge');

	if (!empty($context['boards']) && count($context['boards']) > 1)
	{
		echo '
				<fieldset id="merge_board" class="merge_options">
					<legend>', $txt['merge_select_target_board'], '</legend>
					<ul>';
		foreach ($context['boards'] as $board)
			echo '
						<li>
							<input type="radio" name="board" value="' . $board['id'] . '"' . ($board['selected'] ? ' checked' : '') . ' class="input_radio"> ' . $board['name'] . '
						</li>';
		echo '
					</ul>
				</fieldset>';
	}
	if (!empty($context['polls']))
	{
		echo '
				<fieldset id="merge_poll" class="merge_options">
					<legend>' . $txt['merge_select_poll'] . '</legend>
					<ul>';
		foreach ($context['polls'] as $poll)
			echo '
						<li>
							<input type="radio" name="poll" value="' . $poll['id'] . '"' . ($poll['selected'] ? ' checked' : '') . ' class="input_radio"> ' . $poll['question'] . ' (' . $txt['topic'] . ': <a href="' . $scripturl . '?topic=' . $poll['topic']['id'] . '.0" target="_blank" class="new_win">' . $poll['topic']['subject'] . '</a>)
						</li>';
		echo '
						<li>
							<input type="radio" name="poll" value="-1" class="input_radio"> (' . $txt['merge_no_poll'] . ')
						</li>
					</ul>
				</fieldset>';
	}

	echo '
				<div class="auto_flow">
					<input type="submit" value="' . $txt['merge'] . '" class="button_submit">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="sa" value="execute">
				</div>
			</div>
		</form>
	</div>';
}

?>