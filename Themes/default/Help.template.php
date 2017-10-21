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
 * This displays a help popup thingy
 */
function template_popup()
{
	global $context, $settings, $txt, $modSettings;
	$data = Array(
		'context' => $context,
		'settings' => $settings,
		'txt' => $txt,
		'modsettings' => $modSettings,
		'id' => 'help_popup',
		'content' => $context['help_text']
	);
	
	$template = loadTemplateLayout('popup');

	$phpStr = compileTemplate($template);
	
	$renderer = LightnCandy::prepare($phpStr);
	echo $renderer($data);
}
/**
 * The template for the popup for finding members
 * @todo Is this used?
 */
function template_find_members()
{
	global $context, $settings, $scripturl, $modSettings, $txt;

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<title>', $txt['find_members'], '</title>
		<meta charset="UTF-8">
		<meta name="robots" content="noindex">
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css', $modSettings['browser_cache'] ,'">
		<script src="', $settings['default_theme_url'], '/scripts/script.js', $modSettings['browser_cache'] ,'"></script>
		<script>
			var membersAdded = [];
			function addMember(name)
			{
				var theTextBox = window.opener.document.getElementById("', $context['input_box_name'], '");

				if (name in membersAdded)
					return;

				// If we only accept one name don\'t remember what is there.
				if (', JavaScriptEscape($context['delimiter']), ' != \'null\')
					membersAdded[name] = true;

				if (theTextBox.value.length < 1 || ', JavaScriptEscape($context['delimiter']), ' == \'null\')
					theTextBox.value = ', $context['quote_results'] ? '"\"" + name + "\""' : 'name', ';
				else
					theTextBox.value += ', JavaScriptEscape($context['delimiter']), ' + ', $context['quote_results'] ? '"\"" + name + "\""' : 'name', ';

				window.focus();
			}
		</script>
	</head>
	<body id="help_popup">
		<form action="', $scripturl, '?action=findmember;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8" class="padding description">
			<div class="roundframe">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['find_members'], '</h3>
				</div>
				<div class="padding">
					<strong>', $txt['find_username'], ':</strong><br>
					<input type="text" name="search" id="search" value="', isset($context['last_search']) ? $context['last_search'] : '', '" style="margin-top: 4px; width: 96%;" class="input_text"><br>
					<span class="smalltext"><em>', $txt['find_wildcards'], '</em></span><br>';

	// Only offer to search for buddies if we have some!
	if (!empty($context['show_buddies']))
		echo '
					<span class="smalltext"><label for="buddies"><input type="checkbox" class="input_check" name="buddies" id="buddies"', !empty($context['buddy_search']) ? ' checked' : '', '> ', $txt['find_buddies'], '</label></span><br>';

	echo '
					<div class="padding righttext">
						<input type="submit" value="', $txt['search'], '" class="button_submit">
						<input type="button" value="', $txt['find_close'], '" onclick="window.close();" class="button_submit">
					</div>
				</div>
			</div>
			<br>
			<div class="roundframe">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['find_results'], '</h3>
				</div>';

	if (empty($context['results']))
		echo '
				<p class="error">', $txt['find_no_results'], '</p>';
	else
	{
		echo '
				<ul class="padding">';

		foreach ($context['results'] as $result)
		{
			echo '
					<li class="windowbg">
						<a href="', $result['href'], '" target="_blank" class="new_win"> <span class="generic_icons profile_sm"></span>
						<a href="javascript:void(0);" onclick="addMember(this.innerHTML); return false;">', $result['name'], '</a>
					</li>';
		}

		echo '
				</ul>
				<div class="pagesection">
					', $context['page_index'], '
				</div>';
	}

	echo '

			</div>
			<input type="hidden" name="input" value="', $context['input_box_name'], '">
			<input type="hidden" name="delim" value="', $context['delimiter'], '">
			<input type="hidden" name="quote" value="', $context['quote_results'] ? '1' : '0', '">
		</form>';

	if (empty($context['results']))
		echo '
		<script>
			document.getElementById("search").focus();
		</script>';

	echo '
	</body>
</html>';
}

?>