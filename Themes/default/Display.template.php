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
 * This tempate handles displaying a topic
 */
function template_main()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;
	
	$viewing = $settings['display_who_viewing'] == 1 ? 
					count($context['view_members']) . ' ' . count($context['view_members']) == 1 ? $txt['who_member'] : $txt['members']
				 :
					empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . ((empty($context['view_num_hidden']) || $context['can_moderate_forum']) ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');
				 

	$context['messages'] = [];
	$context['ignoredMsgs'] = [];
	$context['removableMessageIDs'] = [];
	while($message = $context['get_message']()) {
		$context['messages'][] = $message;
		if (!empty($message['is_ignored'])) $context['ignoredMsgs'][] = $message['id'];
		if ($message['can_remove']) $context['removableMessageIDs'][] = $message['id'];
	}
	
	$data = [
		'context' => $context,
		'settings' => $settings,
		'options' => $options,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'modSettings' => $modSettings,
		'viewing' => $viewing
	];

	$template = file_get_contents(__DIR__ .  "/templates/display_main.hbs");
	if (!$template) {
		die('Display main template did not load!');
	}

	$phpStr = LightnCandy::compile($template, [
		'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
		'helpers' => [
			'eq' => logichelper_eq,
			'neq' => logichelper_ne,
			'or' => logichelper_or,
			'and' => logichelper_and,
			'not' => logichelper_not,
			'implode' => implode_comma,
			'JSEscape' => JSEScape,
			'get_text' => get_text,
			'concat' => concat,
			'textTemplate' => textTemplate,
			'breakRow' => breakRow,
			'getLikeText' => getLikeText
		],
		'partials' => [
			'button_strip' => file_get_contents(__DIR__ .  "/partials/button_strip.hbs"),
			'single_post' => file_get_contents(__DIR__ .  "/partials/single_post.hbs"),
			'quickreply' => file_get_contents(__DIR__ .  "/partials/quick_reply.hbs")
		]
	]);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);


	$context['ignoredMsgs'] = array();
	$context['removableMessageIDs'] = array();

	// Show the lower breadcrumbs.
//	theme_linktree();

}

//This is a helper for the like text
function getLikeText($count) {
	global $txt, $context;
	
	$base = 'likes_';
	if ($message['likes']['you'])
	{
		$base = 'you_' . $base;
		$count--;
	}
	$base .= (isset($txt[$base . $count])) ? $count : 'n';
	return sprintf($txt[$base], $scripturl . '?action=likes;sa=view;ltype=msg;like=' . $id . ';' . $context['session_var'] . '=' . $context['session_id'], comma_format($count));
}


/**
 * The template for displaying the quick reply box.
 */
function template_quickreply()
{
	global $context, $modSettings, $scripturl, $options, $txt;
	echo '
		<a id="quickreply"></a>
		<div class="tborder" id="quickreplybox">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['quick_reply'], '
				</h3>
			</div>
			<div id="quickReplyOptions">
				<div class="roundframe">', empty($options['use_editor_quick_reply']) ? '
					<p class="smalltext lefttext">' . $txt['quick_reply_desc'] . '</p>' : '', '
					', $context['is_locked'] ? '<p class="alert smalltext">' . $txt['quick_reply_warning'] . '</p>' : '',
					!empty($context['oldTopicError']) ? '<p class="alert smalltext">' . sprintf($txt['error_old_topic'], $modSettings['oldTopicDays']) . '</p>' : '', '
					', $context['can_reply_approved'] ? '' : '<em>' . $txt['wait_for_approval'] . '</em>', '
					', !$context['can_reply_approved'] && $context['require_verification'] ? '<br>' : '', '
					<form action="', $scripturl, '?board=', $context['current_board'], ';action=post2" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify" onsubmit="submitonce(this);">
						<input type="hidden" name="topic" value="', $context['current_topic'], '">
						<input type="hidden" name="subject" value="', $context['response_prefix'], $context['subject'], '">
						<input type="hidden" name="icon" value="xx">
						<input type="hidden" name="from_qr" value="1">
						<input type="hidden" name="notify" value="', $context['is_marked_notify'] || !empty($options['auto_notify']) ? '1' : '0', '">
						<input type="hidden" name="not_approved" value="', !$context['can_reply_approved'], '">
						<input type="hidden" name="goback" value="', empty($options['return_to_post']) ? '0' : '1', '">
						<input type="hidden" name="last_msg" value="', $context['topic_last_message'], '">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '">';

		// Guests just need more.
		if ($context['user']['is_guest'])
			echo '
						<dl id="post_header">
							<dt>
								', $txt['name'], ':
							</dt>
							<dd>
								<input type="text" name="guestname" size="25" value="', $context['name'], '" tabindex="', $context['tabindex']++, '" class="input_text">
							</dd>
							<dt>
								', $txt['email'], ':
							</dt>
							<dd>
								<input type="email" name="email" size="25" value="', $context['email'], '" tabindex="', $context['tabindex']++, '" class="input_text" required>
							</dd>
						</dl>';

		echo '
						', template_control_richedit($context['post_box_name'], 'smileyBox_message', 'bbcBox_message'), '
						<script>
							function insertQuoteFast(messageid)
							{
								if (window.XMLHttpRequest)
									getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + \'action=quotefast;quote=\' + messageid + \';xml;pb=', $context['post_box_name'], ';mode=\' + (oEditorHandle_', $context['post_box_name'], '.bRichTextEnabled ? 1 : 0), onDocReceived);
								else
									reqWin(smf_prepareScriptUrl(smf_scripturl) + \'action=quotefast;quote=\' + messageid + \';pb=', $context['post_box_name'], ';mode=\' + (oEditorHandle_', $context['post_box_name'], '.bRichTextEnabled ? 1 : 0), 240, 90);
								return false;
							}
							function onDocReceived(XMLDoc)
							{
								var text = \'\';
								for (var i = 0, n = XMLDoc.getElementsByTagName(\'quote\')[0].childNodes.length; i < n; i++)
									text += XMLDoc.getElementsByTagName(\'quote\')[0].childNodes[i].nodeValue;
								$("#', $context['post_box_name'], '").data("sceditor").InsertText(text);

								ajax_indicator(false);
							}
						</script>';

	// Is visual verification enabled?
	if ($context['require_verification'])
	{
		echo '
				<div class="post_verification">
					<strong>', $txt['verification'], ':</strong>
					', template_control_verification($context['visual_verification_id'], 'all'), '
				</div>';
	}

	// Finally, the submit buttons.
	echo '
				<br class="clear_right">
				<span id="post_confirm_buttons">
					', template_control_richedit_buttons($context['post_box_name']), '
				</span>';
		echo '
					</form>
				</div>
			</div>
		</div>
		<br class="clear">';

	// draft autosave available and the user has it enabled?
	if (!empty($context['drafts_autosave']))
		echo '
			<script>
				var oDraftAutoSave = new smf_DraftAutoSave({
					sSelf: \'oDraftAutoSave\',
					sLastNote: \'draft_lastautosave\',
					sLastID: \'id_draft\',', !empty($context['post_box_name']) ? '
					sSceditorID: \'' . $context['post_box_name'] . '\',' : '', '
					sType: \'', 'quick', '\',
					iBoard: ', (empty($context['current_board']) ? 0 : $context['current_board']), ',
					iFreq: ', (empty($modSettings['masterAutoSaveDraftsDelay']) ? 60000 : $modSettings['masterAutoSaveDraftsDelay'] * 1000), '
				});
			</script>';

	echo '
				<script>
					var oQuickReply = new QuickReply({
						bDefaultCollapsed: false,
						iTopicId: ', $context['current_topic'], ',
						iStart: ', $context['start'], ',
						sScriptUrl: smf_scripturl,
						sImagesUrl: smf_images_url,
						sContainerId: "quickReplyOptions",
						sImageId: "quickReplyExpand",
						sClassCollapsed: "toggle_up",
						sClassExpanded: "toggle_down",
						sJumpAnchor: "quickreply",
						bIsFull: true
					});
					var oEditorID = "', $context['post_box_name'], '";
					var oEditorObject = oEditorHandle_', $context['post_box_name'], ';
					var oJumpAnchor = "quickreply";
				</script>';
}

?>