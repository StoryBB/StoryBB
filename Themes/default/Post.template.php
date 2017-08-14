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
 * The main template for the post page.
 */
function template_main()
{
	global $context, $options, $txt, $scripturl, $modSettings, $counter;
	
		$ignored_posts = array();
		foreach ($context['previous_posts'] as $post)
		{
			$ignoring = false;
			if (!empty($post['is_ignored']))
				$ignored_posts[] = $ignoring = $post['id'];
		}
				
	
	$data = [
		'context' => $context,
		'options' => $options,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'modSettings' => $modSettings,
		'ignored_posts' => $ignored_posts,
		'counter' =>  empty($counter) ? 0 : $counter,
		'editor_context' => &$context['controls']['richedit'][context.post_box_name]
	];

	$template = file_get_contents(__DIR__ .  "/templates/post_main.hbs");
	if (!$template) {
		die('Display main template did not load!');
	}

	$phpStr = LightnCandy::compile($template, [
		'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
		'helpers' => [
			'browser' => isBrowser,
			'jsEscape' => JavaScriptEscape,
			'textTemplate' => textTemplate,
			'concat' => concat,
			'numeric' => function($x) { return is_numeric($x);},
			'neq' => logichelper_ne,
			'eq' => logichelper_eq,
			'or' => logichelper_or,
			'and' => logichelper_and,
			'gt' => logichelper_gt,
			'not' => logichelper_not,
			'formatKb' => function($size) {
				return comma_format(round(max($size, 1028) / 1028), 0);
			},
			'sizeLimit' => function() { return $modSettings.attachmentSizeLimit * 1028; },
			'getNumItems' => getNumItems,
			'implode_sep' => implode_sep
		],
		'partials' => [
			'control_richedit' => file_get_contents(__DIR__ .  "/partials/control_richedit.hbs")
		]
	]);
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * The template for the AJAX quote feature
 */
function template_quotefast()
{
	global $context, $settings, $txt, $modSettings;

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="UTF-8">
		<title>', $txt['retrieving_quote'], '</title>
		<script src="', $settings['default_theme_url'], '/scripts/script.js', $modSettings['browser_cache'] ,'"></script>
	</head>
	<body>
		', $txt['retrieving_quote'], '
		<div id="temporary_posting_area" style="display: none;"></div>
		<script>';

	if ($context['close_window'])
		echo '
			window.close();';
	else
	{
		// Lucky for us, Internet Explorer has an "innerText" feature which basically converts entities <--> text. Use it if possible ;).
		echo '
			var quote = \'', $context['quote']['text'], '\';
			var stage = \'createElement\' in document ? document.createElement("DIV") : document.getElementById("temporary_posting_area");

			if (\'DOMParser\' in window && !(\'opera\' in window))
			{
				var xmldoc = new DOMParser().parseFromString("<temp>" + \'', $context['quote']['mozilla'], '\'.replace(/\n/g, "_SMF-BREAK_").replace(/\t/g, "_SMF-TAB_") + "</temp>", "text/xml");
				quote = xmldoc.childNodes[0].textContent.replace(/_SMF-BREAK_/g, "\n").replace(/_SMF-TAB_/g, "\t");
			}
			else if (\'innerText\' in stage)
			{
				setInnerHTML(stage, quote.replace(/\n/g, "_SMF-BREAK_").replace(/\t/g, "_SMF-TAB_").replace(/</g, "&lt;").replace(/>/g, "&gt;"));
				quote = stage.innerText.replace(/_SMF-BREAK_/g, "\n").replace(/_SMF-TAB_/g, "\t");
			}

			if (\'opera\' in window)
				quote = quote.replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&quot;/g, \'"\').replace(/&amp;/g, "&");

			window.opener.onReceiveOpener(quote);

			window.focus();
			setTimeout("window.close();", 400);';
	}
	echo '
		</script>
	</body>
</html>';
}

/**
 * The form for sending out an announcement
 */
function template_announce()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="announcement">
		<form action="', $scripturl, '?action=announce;sa=send" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['announce_title'], '</h3>
			</div>
			<div class="information">
				', $txt['announce_desc'], '
			</div>
			<div class="windowbg2">
				<p>
					', $txt['announce_this_topic'], ' <a href="', $scripturl, '?topic=', $context['current_topic'], '.0">', $context['topic_subject'], '</a>
				</p>
				<ul>';

	foreach ($context['groups'] as $group)
		echo '
					<li>
						<label for="who_', $group['id'], '"><input type="checkbox" name="who[', $group['id'], ']" id="who_', $group['id'], '" value="', $group['id'], '" checked class="input_check"> ', $group['name'], '</label> <em>(', $group['member_count'], ')</em>
					</li>';

	echo '
					<li>
						<label for="checkall"><input type="checkbox" id="checkall" class="input_check" onclick="invertAll(this, this.form);" checked> <em>', $txt['check_all'], '</em></label>
					</li>
				</ul>
				<hr>
				<div id="confirm_buttons">
					<input type="submit" value="', $txt['post'], '" class="button_submit">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="topic" value="', $context['current_topic'], '">
					<input type="hidden" name="move" value="', $context['move'], '">
					<input type="hidden" name="goback" value="', $context['go_back'], '">
				</div>
				<br class="clear_right">
			</div>
		</form>
	</div>
	<br>';
}

/**
 * The confirmation/progress page, displayed after the admin has clicked the button to send the announcement.
 */
function template_announcement_send()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="announcement">
		<form action="' . $scripturl . '?action=announce;sa=send" method="post" accept-charset="UTF-8" name="autoSubmit" id="autoSubmit">
			<div class="windowbg2">
				<p>', $txt['announce_sending'], ' <a href="', $scripturl, '?topic=', $context['current_topic'], '.0" target="_blank" class="new_win">', $context['topic_subject'], '</a></p>
				<div class="progress_bar">
					<div class="full_bar">', $context['percentage_done'], '% ', $txt['announce_done'], '</div>
					<div class="green_percent" style="width: ', $context['percentage_done'], '%;">&nbsp;</div>
				</div>
				<hr>
				<div id="confirm_buttons">
					<input type="submit" name="b" value="', $txt['announce_continue'], '" class="button_submit">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="topic" value="', $context['current_topic'], '">
					<input type="hidden" name="move" value="', $context['move'], '">
					<input type="hidden" name="goback" value="', $context['go_back'], '">
					<input type="hidden" name="start" value="', $context['start'], '">
					<input type="hidden" name="membergroups" value="', $context['membergroups'], '">
				</div>
				<br class="clear_right">
			</div>
		</form>
	</div>
	<br>
		<script>
			var countdown = 2;
			doAutoSubmit();

			function doAutoSubmit()
			{
				if (countdown == 0)
					document.forms.autoSubmit.submit();
				else if (countdown == -1)
					return;

				document.forms.autoSubmit.b.value = "', $txt['announce_continue'], ' (" + countdown + ")";
				countdown--;

				setTimeout("doAutoSubmit();", 1000);
			}
		</script>';
}

?>