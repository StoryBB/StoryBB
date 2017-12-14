<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Template for the topic maintenance tasks.
 */
function template_maintain_topics()
{
	global $scripturl, $txt, $context, $settings, $modSettings;

	// Bit of javascript for showing which boards to prune in an otherwise hidden list.
	echo '
		<script>
			var rotSwap = false;
			function swapRot()
			{
				rotSwap = !rotSwap;

				// Toggle icon
				document.getElementById("rotIcon").src = smf_images_url + (rotSwap ? "/selected_open.png" : "/selected.png");
				setInnerHTML(document.getElementById("rotText"), rotSwap ? ', JavaScriptEscape($txt['maintain_old_choose']), ' : ', JavaScriptEscape($txt['maintain_old_all']), ');

				// Toggle panel
				$("#rotPanel").slideToggle(300);

				// Toggle checkboxes
				var rotPanel = document.getElementById(\'rotPanel\');
				var oBoardCheckBoxes = rotPanel.getElementsByTagName(\'input\');
				for (var i = 0; i < oBoardCheckBoxes.length; i++)
				{
					if (oBoardCheckBoxes[i].type.toLowerCase() == "checkbox")
						oBoardCheckBoxes[i].checked = !rotSwap;
				}
			}
		</script>';

	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['maintain_old'], '</h3>
		</div>
		<div class="windowbg2 noup">
			<div class="flow_auto">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=topics;activity=pruneold" method="post" accept-charset="UTF-8">';

	// The otherwise hidden "choose which boards to prune".
	echo '
					<p>
						<a id="rotLink"></a>', $txt['maintain_old_since_days1'], '<input type="number" name="maxdays" value="30" size="3">', $txt['maintain_old_since_days2'], '
					</p>
					<p>
						<label for="delete_type_nothing"><input type="radio" name="delete_type" id="delete_type_nothing" value="nothing" class="input_radio"> ', $txt['maintain_old_nothing_else'], '</label><br>
						<label for="delete_type_moved"><input type="radio" name="delete_type" id="delete_type_moved" value="moved" class="input_radio" checked> ', $txt['maintain_old_are_moved'], '</label><br>
						<label for="delete_type_locked"><input type="radio" name="delete_type" id="delete_type_locked" value="locked" class="input_radio"> ', $txt['maintain_old_are_locked'], '</label><br>
					</p>
					<p>
						<label for="delete_old_not_sticky"><input type="checkbox" name="delete_old_not_sticky" id="delete_old_not_sticky" class="input_check" checked> ', $txt['maintain_old_are_not_stickied'], '</label><br>
					</p>
					<p>
						<a href="#rotLink" onclick="swapRot();"><img src="', $settings['images_url'], '/selected.png" alt="+" id="rotIcon"></a> <a href="#rotLink" onclick="swapRot();" id="rotText" style="font-weight: bold;">', $txt['maintain_old_all'], '</a>
					</p>
					<div style="display: none;" id="rotPanel" class="flow_hidden">
						<div class="floatleft" style="width: 49%">';

	// This is the "middle" of the list.
	$middle = ceil(count($context['categories']) / 2);

	$i = 0;
	foreach ($context['categories'] as $category)
	{
		echo '
							<fieldset>
								<legend>', $category['name'], '</legend>
								<ul>';

		// Display a checkbox with every board.
		foreach ($category['boards'] as $board)
			echo '
									<li style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'] * 1.5, 'em;"><label for="boards_', $board['id'], '"><input type="checkbox" name="boards[', $board['id'], ']" id="boards_', $board['id'], '" checked class="input_check">', $board['name'], '</label></li>';

		echo '
								</ul>
							</fieldset>';

		// Increase $i, and check if we're at the middle yet.
		if (++$i == $middle)
			echo '
						</div>
						<div class="floatright" style="width: 49%;">';
	}

	echo '
						</div>
					</div>
					<input type="submit" value="', $txt['maintain_old_remove'], '" data-confirm="', $txt['maintain_old_confirm'], '" class="button_submit you_sure">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '">
				</form>
			</div>
		</div>

		<div class="cat_bar">
			<h3 class="catbg">', $txt['maintain_old_drafts'], '</h3>
		</div>
		<div class="windowbg2 noup">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=topics;activity=olddrafts" method="post" accept-charset="UTF-8">
				<p>', $txt['maintain_old_drafts_days'], '&nbsp;<input type="number" name="draftdays" value="', (!empty($modSettings['drafts_keep_days']) ? $modSettings['drafts_keep_days'] : 30), '" size="3">&nbsp;', $txt['days_word'], '</p>
				<input type="submit" value="', $txt['maintain_old_remove'], '" data-confirm="', $txt['maintain_old_drafts_confirm'], '" class="button_submit you_sure">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '">
			</form>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['move_topics_maintenance'], '</h3>
		</div>
		<div class="windowbg2 noup">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=topics;activity=massmove" method="post" accept-charset="UTF-8">
				<p><label for="id_board_from">', $txt['move_topics_from'], ' </label>
				<select name="id_board_from" id="id_board_from">
					<option disabled>(', $txt['move_topics_select_board'], ')</option>';

	// From board
	foreach ($context['categories'] as $category)
	{
		echo '
					<optgroup label="', $category['name'], '">';

		foreach ($category['boards'] as $board)
			echo '
						<option value="', $board['id'], '"> ', str_repeat('==', $board['child_level']), '=&gt;&nbsp;', $board['name'], '</option>';

		echo '
					</optgroup>';
	}

	echo '
				</select>
				<label for="id_board_to">', $txt['move_topics_to'], '</label>
				<select name="id_board_to" id="id_board_to">
					<option disabled>(', $txt['move_topics_select_board'], ')</option>';

	// To board
	foreach ($context['categories'] as $category)
	{
		echo '
					<optgroup label="', $category['name'], '">';

		foreach ($category['boards'] as $board)
			echo '
						<option value="', $board['id'], '"> ', str_repeat('==', $board['child_level']), '=&gt;&nbsp;', $board['name'], '</option>';

		echo '
					</optgroup>';
	}
	echo '
				</select></p>
				<p>
					', $txt['move_topics_older_than'], '<input type="number" name="maxdays" value="30" size="3">', $txt['manageposts_days'], '&nbsp;(', $txt['move_zero_all'], ')
				</p>
				<p>
					<label for="move_type_locked"><input type="checkbox" name="move_type_locked" id="move_type_locked" class="input_check" checked> ', $txt['move_type_locked'], '</label><br>
					<label for="move_type_sticky"><input type="checkbox" name="move_type_sticky" id="move_type_sticky" class="input_check"> ', $txt['move_type_sticky'], '</label><br>
				</p>
				<input type="submit" value="', $txt['move_topics_now'], '" onclick="if (document.getElementById(\'id_board_from\').options[document.getElementById(\'id_board_from\').selectedIndex].disabled || document.getElementById(\'id_board_from\').options[document.getElementById(\'id_board_to\').selectedIndex].disabled) return false; var confirmText = \'', $txt['move_topics_confirm'] . '\'; return confirm(confirmText.replace(/%board_from%/, document.getElementById(\'id_board_from\').options[document.getElementById(\'id_board_from\').selectedIndex].text.replace(/^=+&gt;&nbsp;/, \'\')).replace(/%board_to%/, document.getElementById(\'id_board_to\').options[document.getElementById(\'id_board_to\').selectedIndex].text.replace(/^=+&gt;&nbsp;/, \'\')));" class="button_submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '">
			</form>
		</div>
	</div>';
}

?>