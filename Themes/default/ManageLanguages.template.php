<?php

/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Edit language entries. Note that this doesn't always work because of PHP's max_post_vars setting.
 */
function template_modify_language_entries()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=languages;sa=editlang;lid=', $context['lang_id'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['edit_languages'], '
				</h3>
			</div>
			<div id="editlang_desc" class="information">
				', $txt['edit_language_entries_primary'], '
			</div>';

	// Not writable?
	if (!empty($context['lang_file_not_writable_message']))
	{
		// Oops, show an error for ya.
		echo '
			<div class="errorbox">
				', $context['lang_file_not_writable_message'], '
			</div>';
	}

	// Show the language entries
	echo '
			<div class="windowbg">
				<fieldset>
					<legend>', $context['primary_settings']['name'], '</legend>
					<dl class="settings">
						<dt>
							<label for="locale">', $txt['languages_locale'], ':</label>
						</dt>
						<dd>
							<input type="text" name="locale" id="locale" size="20" value="', $context['primary_settings']['locale'], '"', (empty($context['file_entries']) ? '' : ' disabled'), ' class="input_text">
						</dd>
						<dt>
							<label for="rtl">', $txt['languages_rtl'], ':</label>
						</dt>
						<dd>
							<input type="checkbox" name="rtl" id="rtl"', $context['primary_settings']['rtl'] ? ' checked' : '', ' class="input_check"', (empty($context['file_entries']) ? '' : ' disabled'), '>
						</dd>
					</dl>
				</fieldset>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-mlang_token_var'], '" value="', $context['admin-mlang_token'], '">
				<input type="submit" name="save_main" value="', $txt['save'], '"', $context['lang_file_not_writable_message'] || !empty($context['file_entries']) ? ' disabled' : '', ' class="button_submit">';

	// Allow deleting entries.
	if ($context['lang_id'] != 'english')
	{
		// English can't be deleted though.
		echo '
					<input type="submit" name="delete_main" value="', $txt['delete'], '"', $context['lang_file_not_writable_message'] || !empty($context['file_entries']) ? ' disabled' : '', ' onclick="confirm(\'', $txt['languages_delete_confirm'], '\');" class="button_submit">';
	}

	echo '
			</div>
		</form>

		<form action="', $scripturl, '?action=admin;area=languages;sa=editlang;lid=', $context['lang_id'], ';entries" id="entry_form" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['edit_language_entries'], '
				</h3>
			</div>
			<div id="taskpad" class="floatright">
				', $txt['edit_language_entries_file'], ':
					<select name="tfid" onchange="if (this.value != -1) document.forms.entry_form.submit();">
						<option value="-1">&nbsp;</option>';
	foreach ($context['possible_files'] as $id_theme => $theme)
	{
		echo '
						<optgroup label="', $theme['name'], '">';

		foreach ($theme['files'] as $file)
		{
			echo '
							<option value="', $id_theme, '+', $file['id'], '"', $file['selected'] ? ' selected' : '', '> =&gt; ', $file['name'], '</option>';
		}

		echo '
						</optgroup>';
	}

	echo '
					</select>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="', $context['admin-mlang_token_var'], '" value="', $context['admin-mlang_token'], '">
					<input type="submit" value="', $txt['go'], '" class="button_submit" style="float: none"/>
			</div>
			<br class="clear">';

	// Is it not writable?
	// Show an error.
	if (!empty($context['entries_not_writable_message']))
		echo '
			<div class="errorbox">
				', $context['entries_not_writable_message'], '
			</div>';

	// Already have some file entries?
	if (!empty($context['file_entries']))
	{
		echo '
			<div class="windowbg2">
				<dl class="settings">';

		$cached = array();
		foreach ($context['file_entries'] as $entry)
		{
			// Do it in two's!
			if (empty($cached))
			{
				$cached = $entry;
				continue;
			}

			echo '
					<dt>
						<span class="smalltext">', $cached['key'], '</span>
					</dt>
					<dd>
						<span class="smalltext">', $entry['key'], '</span>
					</dd>
					<dt>
						<input type="hidden" name="comp[', $cached['key'], ']" value="', $cached['value'], '">
						<textarea name="entry[', $cached['key'], ']" cols="40" rows="', $cached['rows'] < 2 ? 2 : $cached['rows'], '" style="width: 96%;">', $cached['value'], '</textarea>
					</dt>
					<dd>
						<input type="hidden" name="comp[', $entry['key'], ']" value="', $entry['value'], '">
						<textarea name="entry[', $entry['key'], ']" cols="40" rows="', $entry['rows'] < 2 ? 2 : $entry['rows'], '" style="width: 96%;">', $entry['value'], '</textarea>
					</dd>';
			$cached = array();
		}

		// Odd number?
		if (!empty($cached))
		{
			// Alternative time
			echo '

					<dt>
						<span class="smalltext">', $cached['key'], '</span>
					</dt>
					<dd>
					</dd>
					<dt>
						<input type="hidden" name="comp[', $cached['key'], ']" value="', $cached['value'], '">
						<textarea name="entry[', $cached['key'], ']" cols="40" rows="2" style="width: 96%;">', $cached['value'], '</textarea>
					</dt>
					<dd>
					</dd>';
		}

		echo '
				</dl>
				<input type="submit" name="save_entries" value="', $txt['save'], '"', !empty($context['entries_not_writable_message']) ? ' disabled' : '', ' class="button_submit">';

		echo '
			</div>';
	}
	echo '
		</form>
	</div>';
}

?>