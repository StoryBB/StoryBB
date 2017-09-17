<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */


/**
 * The search results page.
 */
function template_results()
{
	global $context, $settings, $options, $txt, $scripturl, $message;

	if (empty($context['topics']) || !empty($context['search_ignored']))
	{
		echo '
	<div id="search_results">
		<div class="cat_bar">
			<h3 class="catbg">
				', $txt['search_adjust_query'], '
			</h3>
		</div>
		<div class="roundframe">';

		if (!empty($context['search_ignored']))
			echo '
			<p>', $txt['search_warning_ignored_word' . (count($context['search_ignored']) == 1 ? '' : 's')], ': ', implode(', ', $context['search_ignored']), '</p>';

		echo '
			<form action="', $scripturl, '?action=search2" method="post" accept-charset="UTF-8">
				<dl class="settings">
					<dt class="righttext">
						<strong>', $txt['search_for'], ':</strong>
					</dt>
					<dd>
						<input type="text" name="search"', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' maxlength="', $context['search_string_limit'], '" size="40" class="input_text">
					</dd>
				</dl>
				<div class="flow_auto" >
					<input type="submit" name="edit_search" value="', $txt['search_adjust_submit'], '" class="button_submit">
					<input type="hidden" name="searchtype" value="', !empty($context['search_params']['searchtype']) ? $context['search_params']['searchtype'] : 0, '">
					<input type="hidden" name="userspec" value="', !empty($context['search_params']['userspec']) ? $context['search_params']['userspec'] : '', '">
					<input type="hidden" name="show_complete" value="', !empty($context['search_params']['show_complete']) ? 1 : 0, '">
					<input type="hidden" name="subject_only" value="', !empty($context['search_params']['subject_only']) ? 1 : 0, '">
					<input type="hidden" name="minage" value="', !empty($context['search_params']['minage']) ? $context['search_params']['minage'] : '0', '">
					<input type="hidden" name="maxage" value="', !empty($context['search_params']['maxage']) ? $context['search_params']['maxage'] : '9999', '">
					<input type="hidden" name="sort" value="', !empty($context['search_params']['sort']) ? $context['search_params']['sort'] : 'relevance', '">
				</div>';
		if (!empty($context['search_params']['brd']))
			foreach ($context['search_params']['brd'] as $board_id)
				echo '
				<input type="hidden" name="brd[', $board_id, ']" value="', $board_id, '">';

		echo '
			</form>
		</div>
	</div><br>';
	}

	if ($context['compact'])
	{
		// Quick moderation.
		echo '
	<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="UTF-8" name="topicForm">';

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="floatright">
					<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check">
				</span>
				<span class="generic_icons filter"></span>&nbsp;', $txt['mlist_search_results'], ':&nbsp;', $context['search_params']['search'], '
			</h3>
		</div>';

		// was anything even found?
		if (!empty($context['topics']))
		echo'
		<div class="pagesection">
			<span>', $context['page_index'], '</span>
		</div>';
		else
			echo '
			<div class="roundframe">', $txt['find_no_results'], '</div>';

		// while we have results to show ...
		while ($topic = $context['get_topics']())
		{

			echo '
			<div class="', $topic['css_class'], '">
				<div class="flow_auto">';

			foreach ($topic['matches'] as $message)
			{
				echo '
					<div class="topic_details floatleft" style="width: 94%">
						<div class="counter">', $message['counter'], '</div>
						<h5>', $topic['board']['link'], ' / <a href="', $scripturl, '?topic=', $topic['id'], '.msg', $message['id'], '#msg', $message['id'], '">', $message['subject_highlighted'], '</a></h5>
						<span class="smalltext">&#171;&nbsp;',$txt['by'], '&nbsp;<strong>', $message['member']['link'], '</strong>&nbsp;', $txt['on'], '&nbsp;<em>', $message['time'], '</em>&nbsp;&#187;</span>
					</div>
					<div class="floatright">
						<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check">
					</div>';

				if ($message['body_highlighted'] != '')
					echo '
					<br class="clear">
					<div class="list_posts double_height">', $message['body_highlighted'], '</div>';
			}

			echo '
				</div>
			</div>';

		}
		if (!empty($context['topics']))
		echo '
		<div class="pagesection">
			<span>', $context['page_index'], '</span>
		</div>';

		if (!empty($context['topics']))
		{
			echo '
			<div style="padding: 4px;">
				<div class="floatright flow_auto">
					<select class="qaction" name="qaction"', $context['can_move'] ? ' onchange="this.form.move_to.disabled = (this.options[this.selectedIndex].value != \'move\');"' : '', '>
						<option value="">--------</option>';

			foreach ($context['qmod_actions'] as $qmod_action)
				if ($context['can_' . $qmod_action])
					echo '
							<option value="' . $qmod_action . '">' . $txt['quick_mod_' . $qmod_action] . '</option>';

			echo '
					</select>';

			if ($context['can_move'])
				echo '
				<span id="quick_mod_jump_to">&nbsp;</span>';

			echo '
					<input type="hidden" name="redirect_url" value="', $scripturl . '?action=search2;params=' . $context['params'], '">
					<input type="submit" value="', $txt['quick_mod_go'], '" onclick="return this.form.qaction.value != \'\' &amp;&amp; confirm(\'', $txt['quickmod_confirm'], '\');" class="button_submit" style="float: none;font-size: .8em;"/>
				</div>
			</div>';
		}


		echo '
			<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
		</form>';

	}
	else
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="generic_icons filter"></span>&nbsp;', $txt['mlist_search_results'], ':&nbsp;', $context['search_params']['search'], '
			</h3>
		</div>
		<div class="pagesection">
			<span>', $context['page_index'], '</span>
		</div>';

		if (empty($context['topics']))
			echo '
		<div class="information">(', $txt['search_no_results'], ')</div>';

		while ($topic = $context['get_topics']())
		{
			foreach ($topic['matches'] as $message)
			{
				echo '
				<div class="', $topic['css_class'], '">
					<div class="counter">', $message['counter'], '</div>
					<div class="topic_details">
						<h5>', $topic['board']['link'], ' / <a href="', $scripturl, '?topic=', $topic['id'], '.', $message['start'], ';topicseen#msg', $message['id'], '">', $message['subject_highlighted'], '</a></h5>
						<span class="smalltext">&#171;&nbsp;', $txt['message'], ' ', $txt['by'], ' <strong>', $message['member']['link'], ' </strong>', $txt['on'], '&nbsp;<em>', $message['time'], '</em>&nbsp;&#187;</span>
					</div>
					<div class="list_posts">', $message['body_highlighted'], '</div>';

			if ($topic['can_reply'])
				echo '
						<ul class="quickbuttons">';

				// If they *can* reply?
			if ($topic['can_reply'])
				echo '
							<li><a href="', $scripturl . '?action=post;topic=' . $topic['id'] . '.' . $message['start'], '"><span class="generic_icons reply_button"></span>', $txt['reply'], '</a></li>';

				// If they *can* quote?
			if ($topic['can_quote'])
				echo '
							<li><a href="', $scripturl . '?action=post;topic=' . $topic['id'] . '.' . $message['start'] . ';quote=' . $message['id'] . '"><span class="generic_icons quote"></span>', $txt['quote_action'], '</a></li>';

			if ($topic['can_reply'])
				echo '
						</ul>';
			echo '
					<br class="clear">
				</div>';
			}
		}

		echo '
		<div class="pagesection">
			<span>', $context['page_index'], '</span>
		</div>';
	}

	// Show a jump to box for easy navigation.
	echo '
		<br class="clear">
		<div class="smalltext righttext" id="search_jump_to">&nbsp;</div>
		<script>';

	if (!empty($context['topics']) && $context['can_move'])
		echo '
				if (typeof(window.XMLHttpRequest) != "undefined")
					aJumpTo[aJumpTo.length] = new JumpTo({
						sContainerId: "quick_mod_jump_to",
						sClassName: "qaction",
						sJumpToTemplate: "%dropdown_list%",
						sCurBoardName: "', $context['jump_to']['board_name'], '",
						sBoardChildLevelIndicator: "==",
						sBoardPrefix: "=> ",
						sCatSeparator: "-----------------------------",
						sCatPrefix: "",
						bNoRedirect: true,
						bDisabled: true,
						sCustomName: "move_to"
					});';

	echo '
			if (typeof(window.XMLHttpRequest) != "undefined")
				aJumpTo[aJumpTo.length] = new JumpTo({
					sContainerId: "search_jump_to",
					sJumpToTemplate: "<label class=\"smalltext jump_to\" for=\"%select_id%\">', $context['jump_to']['label'], '<" + "/label> %dropdown_list%",
					iCurBoardId: 0,
					iCurBoardChildLevel: 0,
					sCurBoardName: "', $context['jump_to']['board_name'], '",
					sBoardChildLevelIndicator: "==",
					sBoardPrefix: "=> ",
					sCatSeparator: "-----------------------------",
					sCatPrefix: "",
					sGoButtonLabel: "', $txt['quick_mod_go'], '"
				});
		</script>';

}

?>