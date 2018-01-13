<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */


/**
 * Adding a new smiley.
 */
function template_addsmiley()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=smileys;sa=addsmiley" method="post" accept-charset="UTF-8" name="smileyForm" id="smileyForm" enctype="multipart/form-data">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['smileys_add_method'], '</h3>
			</div>
			<div class="windowbg2">
				<ul>
					<li>
						<label for="method-existing"><input type="radio" onclick="switchType();" name="method" id="method-existing" value="existing" checked class="input_radio"> ', $txt['smileys_add_existing'], '</label>
					</li>
					<li>
						<label for="method-upload"><input type="radio" onclick="switchType();" name="method" id="method-upload" value="upload" class="input_radio"> ', $txt['smileys_add_upload'], '</label>
					</li>
				</ul>
				<br>
				<fieldset id="ex_settings">
					<dl class="settings">
						<dt>
							<img src="', $modSettings['smileys_url'], '/', $modSettings['smiley_sets_default'], '/', $context['filenames'][0]['id'], '" id="preview" alt="">
						</dt>
						<dd>
							', $txt['smiley_preview_using'], ': <select name="set" onchange="updatePreview();selectMethod(\'existing\');">';

		foreach ($context['smiley_sets'] as $smiley_set)
			echo '
							<option value="', $smiley_set['path'], '"', $context['selected_set'] == $smiley_set['path'] ? ' selected' : '', '>', $smiley_set['name'], '</option>';

		echo '
							</select>
						</dd>
						<dt>
							<strong><label for="smiley_filename">', $txt['smileys_filename'], '</label>: </strong>
						</dt>
						<dd>';
	if (empty($context['filenames']))
		echo '
							<input type="text" name="smiley_filename" id="smiley_filename" value="', $context['current_smiley']['filename'], '" onchange="selectMethod(\'existing\');" class="input_text">';
	else
	{
		echo '
								<select name="smiley_filename" id="smiley_filename" onchange="updatePreview();selectMethod(\'existing\');">';
		foreach ($context['filenames'] as $filename)
			echo '
								<option value="', $filename['id'], '"', $filename['selected'] ? ' selected' : '', '>', $filename['id'], '</option>';
		echo '
							</select>';
	}

	echo '
						</dd>
					</dl>
				</fieldset>
				<fieldset id="ul_settings" style="display: none;">
					<dl class="settings">
						<dt>
							<strong>', $txt['smileys_add_upload_choose'], ':</strong><br>
							<span class="smalltext">', $txt['smileys_add_upload_choose_desc'], '</span>
						</dt>
						<dd>
							<input type="file" name="uploadSmiley" id="uploadSmiley" onchange="selectMethod(\'upload\');" class="input_file">
						</dd>
						<dt>
							<strong><label for="sameall">', $txt['smileys_add_upload_all'], ':</label></strong>
						</dt>
						<dd>
							<input type="checkbox" name="sameall" id="sameall" checked class="input_check" onclick="swapUploads(); selectMethod(\'upload\');">
						</dd>
					</dl>
				</fieldset>

				<dl id="uploadMore" style="display: none;" class="settings">';
	foreach ($context['smiley_sets'] as $smiley_set)
		echo '
					<dt>
						', sprintf($txt['smileys_add_upload_for'], '<strong>' . $smiley_set['name'] . '</strong>'), ':
					</dt>
					<dd>
						<input type="file" name="individual_', $smiley_set['name'], '" onchange="selectMethod(\'upload\');" class="input_file">
					</dd>';
	echo '
				</dl>
			</div>
			<div class="cat_bar">
				<h3 class="catbg">', $txt['smiley_new'], '</h3>
			</div>
			<div class="windowbg2">
				<dl class="settings">
					<dt>
						<strong><label for="smiley_code">', $txt['smileys_code'], '</label>: </strong>
					</dt>
					<dd>
						<input type="text" name="smiley_code" id="smiley_code" value="" class="input_text">
					</dd>
					<dt>
						<strong><label for="smiley_description">', $txt['smileys_description'], '</label>: </strong>
					</dt>
					<dd>
						<input type="text" name="smiley_description" id="smiley_description" value="" class="input_text">
					</dd>
					<dt>
						<strong><label for="smiley_location">', $txt['smileys_location'], '</label>: </strong>
					</dt>
					<dd>
						<select name="smiley_location" id="smiley_location">
							<option value="0" selected>
								', $txt['smileys_location_form'], '
							</option>
							<option value="1">
								', $txt['smileys_location_hidden'], '
							</option>
							<option value="2">
								', $txt['smileys_location_popup'], '
							</option>
						</select>
					</dd>
				</dl>
				<input type="submit" name="smiley_save" value="', $txt['smileys_save'], '" class="button_submit">
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>';
}

/**
 * Ordering smileys.
 */
function template_setorder()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">';

	foreach ($context['smileys'] as $location)
	{
		echo '
		<form action="', $scripturl, '?action=admin;area=smileys;sa=editsmileys" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', $location['title'], '</h3>
			</div>
			<div class="information noup">
				', $location['description'], '
			</div>
			<div class="windowbg2">
				<strong>', empty($context['move_smiley']) ? $txt['smileys_move_select_smiley'] : $txt['smileys_move_select_destination'], '...</strong><br>';
		foreach ($location['rows'] as $row)
		{
			if (!empty($context['move_smiley']))
				echo '
					<a href="', $scripturl, '?action=admin;area=smileys;sa=setorder;location=', $location['id'], ';source=', $context['move_smiley'], ';row=', $row[0]['row'], ';reorder=1;', $context['session_var'], '=', $context['session_id'], '"><span class="generic_icons select_below" title="', $txt['smileys_move_here'], '"></span></a>';

			foreach ($row as $smiley)
			{
				if (empty($context['move_smiley']))
					echo '<a href="', $scripturl, '?action=admin;area=smileys;sa=setorder;move=', $smiley['id'], '"><img src="', $modSettings['smileys_url'], '/', $modSettings['smiley_sets_default'], '/', $smiley['filename'], '" style="padding: 2px; border: 0px solid black;" alt="', $smiley['description'], '"></a>';
				else
					echo '<img src="', $modSettings['smileys_url'], '/', $modSettings['smiley_sets_default'], '/', $smiley['filename'], '" style="padding: 2px;', $smiley['selected'] ? ' border: 2px solid red' : '', ';" alt="', $smiley['description'], '"><a href="', $scripturl, '?action=admin;area=smileys;sa=setorder;location=', $location['id'], ';source=', $context['move_smiley'], ';after=', $smiley['id'], ';reorder=1;', $context['session_var'], '=', $context['session_id'], '" title="', $txt['smileys_move_here'], '"><span class="generic_icons select_below" title="', $txt['smileys_move_here'], '"></span></a>';
			}

			echo '
				<br>';
		}
		if (!empty($context['move_smiley']))
			echo '
				<a href="', $scripturl, '?action=admin;area=smileys;sa=setorder;location=', $location['id'], ';source=', $context['move_smiley'], ';row=', $location['last_row'], ';reorder=1;', $context['session_var'], '=', $context['session_id'], '"><span class="generic_icons select_below" title="', $txt['smileys_move_here'], '"></span></a>';
		echo '
			</div>
		<input type="hidden" name="reorder" value="1">
	</form>';
	}

	echo '
	</div>';
}

?>