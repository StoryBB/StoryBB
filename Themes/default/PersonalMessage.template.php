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
 * Here we allow the user to setup labels, remove labels and change rules for labels (i.e, do quite a bit)
 */
function template_labels()
{
	global $context, $scripturl, $txt;

	echo '
	<form action="', $scripturl, '?action=pm;sa=manlabels" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['pm_manage_labels'], '</h3>
		</div>
		<div class="information">
			', $txt['pm_labels_desc'], '
		</div>
		<table class="table_grid">
		<thead>
			<tr class="title_bar">
				<th class="lefttext">
					', $txt['pm_label_name'], '
				</th>
				<th class="centertext table_icon">';

	if (count($context['labels']) > 2)
		echo '
					<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);">';

	echo '
				</th>
			</tr>
		</thead>
		<tbody>';
	if (count($context['labels']) < 2)
		echo '
			<tr class="windowbg">
				<td colspan="2">', $txt['pm_labels_no_exist'], '</td>
			</tr>';
	else
	{
		foreach ($context['labels'] as $label)
		{
			if ($label['id'] == -1)
				continue;

				echo '
			<tr class="windowbg">
				<td>
					<input type="text" name="label_name[', $label['id'], ']" value="', $label['name'], '" size="30" maxlength="30" class="input_text">
				</td>
				<td class="table_icon"><input type="checkbox" class="input_check" name="delete_label[', $label['id'], ']"></td>
			</tr>';
		}
	}
	echo '
		</tbody>
		</table>';

	if (!count($context['labels']) < 2)
		echo '
		<div class="padding">
			<input type="submit" name="save" value="', $txt['save'], '" class="button_submit">
			<input type="submit" name="delete" value="', $txt['quickmod_delete_selected'], '" data-confirm="', $txt['pm_labels_delete'] ,'" class="button_submit you_sure">
		</div>';

	echo '
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>
	<br class="clear">
	<form action="', $scripturl, '?action=pm;sa=manlabels" method="post" accept-charset="UTF-8" style="margin-top: 1ex;">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['pm_label_add_new'], '</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<strong><label for="add_label">', $txt['pm_label_name'], '</label>:</strong>
				</dt>
				<dd>
					<input type="text" id="add_label" name="label" value="" size="30" maxlength="30" class="input_text">
				</dd>
			</dl>
			<input type="submit" name="add" value="', $txt['pm_label_add_new'], '" class="button_submit">
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form><br>';
}

/**
 * Template for reporting a personal message.
 */
function template_report_message()
{
	global $context, $txt, $scripturl;

	echo '
	<form action="', $scripturl, '?action=pm;sa=report;l=', $context['current_label_id'], '" method="post" accept-charset="UTF-8">
		<input type="hidden" name="pmsg" value="', $context['pm_id'], '">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['pm_report_title'], '</h3>
		</div>
		<div class="information">
			', $txt['pm_report_desc'], '
		</div>
		<div class="windowbg">
			<dl class="settings">';

	// If there is more than one admin on the forum, allow the user to choose the one they want to direct to.
	// @todo Why?
	if ($context['admin_count'] > 1)
	{
		echo '
				<dt>
					<strong>', $txt['pm_report_admins'], ':</strong>
				</dt>
				<dd>
					<select name="id_admin">
						<option value="0">', $txt['pm_report_all_admins'], '</option>';
		foreach ($context['admins'] as $id => $name)
			echo '
						<option value="', $id, '">', $name, '</option>';
		echo '
					</select>
				</dd>';
	}

	echo '
				<dt>
					<strong>', $txt['pm_report_reason'], ':</strong>
				</dt>
				<dd>
					<textarea name="reason" rows="4" cols="70" style="width: 80%;"></textarea>
				</dd>
			</dl>
			<div class="righttext">
				<input type="submit" name="report" value="', $txt['pm_report_message'], '" class="button_submit">
			</div>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';
}

/**
 * Little template just to say "Yep, it's been submitted"
 */
function template_report_message_complete()
{
	global $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['pm_report_title'], '</h3>
		</div>
		<div class="windowbg">
			<p>', $txt['pm_report_done'], '</p>
			<a href="', $scripturl, '?action=pm;l=', $context['current_label_id'], '">', $txt['pm_report_return'], '</a>
		</div>';
}

/**
 * Manage rules.
 */
function template_rules()
{
	global $context, $txt, $scripturl;

	echo '
	<form action="', $scripturl, '?action=pm;sa=manrules" method="post" accept-charset="UTF-8" name="manRules" id="manrules">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['pm_manage_rules'], '</h3>
		</div>
		<div class="information">
			', $txt['pm_manage_rules_desc'], '
		</div>
		<table class="table_grid">
		<thead>
			<tr class="title_bar">
				<th class="lefttext">
					', $txt['pm_rule_title'], '
				</th>
				<th class="centertext table_icon">';

	if (!empty($context['rules']))
		echo '
					<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check">';

	echo '
				</th>
			</tr>
		</thead>
		<tbody>';

	if (empty($context['rules']))
		echo '
			<tr class="windowbg">
				<td colspan="2">
					', $txt['pm_rules_none'], '
				</td>
			</tr>';

	foreach ($context['rules'] as $rule)
	{
		echo '
			<tr class="windowbg">
				<td>
					<a href="', $scripturl, '?action=pm;sa=manrules;add;rid=', $rule['id'], '">', $rule['name'], '</a>
				</td>
				<td class="table_icon">
					<input type="checkbox" name="delrule[', $rule['id'], ']" class="input_check">
				</td>
			</tr>';
	}

	echo '
		</tbody>
		</table>
		<div class="righttext">
			<a class="button_link" href="', $scripturl, '?action=pm;sa=manrules;add;rid=0">', $txt['pm_add_rule'], '</a>';

	if (!empty($context['rules']))
		echo '
			[<a href="', $scripturl, '?action=pm;sa=manrules;apply;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['pm_js_apply_rules_confirm'], '\');">', $txt['pm_apply_rules'], '</a>]';

	if (!empty($context['rules']))
		echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="submit" name="delselected" value="', $txt['pm_delete_selected_rule'], '" data-confirm="', $txt['pm_js_delete_rule_confirm'] ,'" class="button_submit smalltext you_sure">';

	echo '
		</div>
	</form>';

}

/**
 * Template for adding/editing a rule.
 */
function template_add_rule()
{
	global $context, $txt, $scripturl;

	echo '
	<script>
			var criteriaNum = 0;
			var actionNum = 0;
			var groups = new Array()
			var labels = new Array()';

	foreach ($context['groups'] as $id => $title)
		echo '
			groups[', $id, '] = "', addslashes($title), '";';

	foreach ($context['labels'] as $label)
		if ($label['id'] != -1)
			echo '
			labels[', ($label['id']), '] = "', addslashes($label['name']), '";';

	echo '
			function addCriteriaOption()
			{
				if (criteriaNum == 0)
				{
					for (var i = 0; i < document.forms.addrule.elements.length; i++)
						if (document.forms.addrule.elements[i].id.substr(0, 8) == "ruletype")
							criteriaNum++;
				}
				criteriaNum++

				setOuterHTML(document.getElementById("criteriaAddHere"), \'<br><select name="ruletype[\' + criteriaNum + \']" id="ruletype\' + criteriaNum + \'" onchange="updateRuleDef(\' + criteriaNum + \'); rebuildRuleDesc();"><option value="">', addslashes($txt['pm_rule_criteria_pick']), ':<\' + \'/option><option value="mid">', addslashes($txt['pm_rule_mid']), '<\' + \'/option><option value="gid">', addslashes($txt['pm_rule_gid']), '<\' + \'/option><option value="sub">', addslashes($txt['pm_rule_sub']), '<\' + \'/option><option value="msg">', addslashes($txt['pm_rule_msg']), '<\' + \'/option><option value="bud">', addslashes($txt['pm_rule_bud']), '<\' + \'/option><\' + \'/select>&nbsp;<span id="defdiv\' + criteriaNum + \'" style="display: none;"><input type="text" name="ruledef[\' + criteriaNum + \']" id="ruledef\' + criteriaNum + \'" onkeyup="rebuildRuleDesc();" value="" class="input_text"><\' + \'/span><span id="defseldiv\' + criteriaNum + \'" style="display: none;"><select name="ruledefgroup[\' + criteriaNum + \']" id="ruledefgroup\' + criteriaNum + \'" onchange="rebuildRuleDesc();"><option value="">', addslashes($txt['pm_rule_sel_group']), '<\' + \'/option>';

	foreach ($context['groups'] as $id => $group)
		echo '<option value="', $id, '">', strtr($group, array("'" => "\'")), '<\' + \'/option>';

	echo '<\' + \'/select><\' + \'/span><span id="criteriaAddHere"><\' + \'/span>\');
			}

			function addActionOption()
			{
				if (actionNum == 0)
				{
					for (var i = 0; i < document.forms.addrule.elements.length; i++)
						if (document.forms.addrule.elements[i].id.substr(0, 7) == "acttype")
							actionNum++;
				}
				actionNum++

				setOuterHTML(document.getElementById("actionAddHere"), \'<br><select name="acttype[\' + actionNum + \']" id="acttype\' + actionNum + \'" onchange="updateActionDef(\' + actionNum + \'); rebuildRuleDesc();"><option value="">', addslashes($txt['pm_rule_sel_action']), ':<\' + \'/option><option value="lab">', addslashes($txt['pm_rule_label']), '<\' + \'/option><option value="del">', addslashes($txt['pm_rule_delete']), '<\' + \'/option><\' + \'/select>&nbsp;<span id="labdiv\' + actionNum + \'" style="display: none;"><select name="labdef[\' + actionNum + \']" id="labdef\' + actionNum + \'" onchange="rebuildRuleDesc();"><option value="">', addslashes($txt['pm_rule_sel_label']), '<\' + \'/option>';

	foreach ($context['labels'] as $label)
		if ($label['id'] != -1)
			echo '<option value="', ($label['id']), '">', addslashes($label['name']), '<\' + \'/option>';

	echo '<\' + \'/select><\' + \'/span><span id="actionAddHere"><\' + \'/span>\');
			}

			// Rebuild the rule description!
			function rebuildRuleDesc()
			{
				// Start with nothing.
				var text = "";
				var joinText = "";
				var actionText = "";
				var hadBuddy = false;
				var foundCriteria = false;
				var foundAction = false;
				var curNum, curVal, curDef;

				for (var i = 0; i < document.forms.addrule.elements.length; i++)
				{
					if (document.forms.addrule.elements[i].id.substr(0, 8) == "ruletype")
					{
						if (foundCriteria)
							joinText = document.getElementById("logic").value == \'and\' ? ', JavaScriptEscape(' ' . $txt['pm_readable_and'] . ' '), ' : ', JavaScriptEscape(' ' . $txt['pm_readable_or'] . ' '), ';
						else
							joinText = \'\';
						foundCriteria = true;

						curNum = document.forms.addrule.elements[i].id.match(/\d+/);
						curVal = document.forms.addrule.elements[i].value;
						if (curVal == "gid")
							curDef = document.getElementById("ruledefgroup" + curNum).value.php_htmlspecialchars();
						else if (curVal != "bud")
							curDef = document.getElementById("ruledef" + curNum).value.php_htmlspecialchars();
						else
							curDef = "";

						// What type of test is this?
						if (curVal == "mid" && curDef)
							text += joinText + ', JavaScriptEscape($txt['pm_readable_member']), '.replace("{MEMBER}", curDef);
						else if (curVal == "gid" && curDef && groups[curDef])
							text += joinText + ', JavaScriptEscape($txt['pm_readable_group']), '.replace("{GROUP}", groups[curDef]);
						else if (curVal == "sub" && curDef)
							text += joinText + ', JavaScriptEscape($txt['pm_readable_subject']), '.replace("{SUBJECT}", curDef);
						else if (curVal == "msg" && curDef)
							text += joinText + ', JavaScriptEscape($txt['pm_readable_body']), '.replace("{BODY}", curDef);
						else if (curVal == "bud" && !hadBuddy)
						{
							text += joinText + ', JavaScriptEscape($txt['pm_readable_buddy']), ';
							hadBuddy = true;
						}
					}
					if (document.forms.addrule.elements[i].id.substr(0, 7) == "acttype")
					{
						if (foundAction)
							joinText = ', JavaScriptEscape(' ' . $txt['pm_readable_and'] . ' '), ';
						else
							joinText = "";
						foundAction = true;

						curNum = document.forms.addrule.elements[i].id.match(/\d+/);
						curVal = document.forms.addrule.elements[i].value;
						if (curVal == "lab")
							curDef = document.getElementById("labdef" + curNum).value.php_htmlspecialchars();
						else
							curDef = "";

						// Now pick the actions.
						if (curVal == "lab" && curDef && labels[curDef])
							actionText += joinText + ', JavaScriptEscape($txt['pm_readable_label']), '.replace("{LABEL}", labels[curDef]);
						else if (curVal == "del")
							actionText += joinText + ', JavaScriptEscape($txt['pm_readable_delete']), ';
					}
				}

				// If still nothing make it default!
				if (text == "" || !foundCriteria)
					text = "', $txt['pm_rule_not_defined'], '";
				else
				{
					if (actionText != "")
						text += ', JavaScriptEscape(' ' . $txt['pm_readable_then'] . ' '), ' + actionText;
					text = ', JavaScriptEscape($txt['pm_readable_start']), ' + text + ', JavaScriptEscape($txt['pm_readable_end']), ';
				}

				// Set the actual HTML!
				setInnerHTML(document.getElementById("ruletext"), text);
			}
	</script>';

	echo '
	<form action="', $scripturl, '?action=pm;sa=manrules;save;rid=', $context['rid'], '" method="post" accept-charset="UTF-8" name="addrule" id="addrule" class="flow_hidden">
		<div class="cat_bar">
			<h3 class="catbg">', $context['rid'] == 0 ? $txt['pm_add_rule'] : $txt['pm_edit_rule'], '</h3>
		</div>
		<div class="windowbg">
			<dl class="addrules">
				<dt class="floatleft">
					<strong>', $txt['pm_rule_name'], ':</strong><br>
					<span class="smalltext">', $txt['pm_rule_name_desc'], '</span>
				</dt>
				<dd class="floatleft">
					<input type="text" name="rule_name" value="', empty($context['rule']['name']) ? $txt['pm_rule_name_default'] : $context['rule']['name'], '" size="50" class="input_text">
				</dd>
			</dl>
			<fieldset>
				<legend>', $txt['pm_rule_criteria'], '</legend>';

	// Add a dummy criteria to allow expansion for none js users.
	$context['rule']['criteria'][] = array('t' => '', 'v' => '');

	// For each criteria print it out.
	$isFirst = true;
	foreach ($context['rule']['criteria'] as $k => $criteria)
	{
		if (!$isFirst && $criteria['t'] == '')
			echo '<div id="removeonjs1">';
		elseif (!$isFirst)
			echo '<br>';

		echo '
				<select name="ruletype[', $k, ']" id="ruletype', $k, '" onchange="updateRuleDef(', $k, '); rebuildRuleDesc();">
					<option value="">', $txt['pm_rule_criteria_pick'], ':</option>';

		foreach (array('mid', 'gid', 'sub', 'msg', 'bud') as $cr)
			echo '
					<option value="', $cr, '"', $criteria['t'] == $cr ? ' selected' : '', '>', $txt['pm_rule_' . $cr], '</option>';

		echo '
				</select>
				<span id="defdiv', $k, '" ', !in_array($criteria['t'], array('gid', 'bud')) ? '' : 'style="display: none;"', '>
					<input type="text" name="ruledef[', $k, ']" id="ruledef', $k, '" onkeyup="rebuildRuleDesc();" value="', in_array($criteria['t'], array('mid', 'sub', 'msg')) ? $criteria['v'] : '', '" class="input_text">
				</span>
				<span id="defseldiv', $k, '" ', $criteria['t'] == 'gid' ? '' : 'style="display: none;"', '>
					<select name="ruledefgroup[', $k, ']" id="ruledefgroup', $k, '" onchange="rebuildRuleDesc();">
						<option value="">', $txt['pm_rule_sel_group'], '</option>';

		foreach ($context['groups'] as $id => $group)
			echo '
						<option value="', $id, '"', $criteria['t'] == 'gid' && $criteria['v'] == $id ? ' selected' : '', '>', $group, '</option>';
		echo '
					</select>
				</span>';

		// If this is the dummy we add a means to hide for non js users.
		if ($isFirst)
			$isFirst = false;
		elseif ($criteria['t'] == '')
			echo '</div>';
	}

	echo '
				<span id="criteriaAddHere"></span><br>
				<a href="#" onclick="addCriteriaOption(); return false;" id="addonjs1" style="display: none;">(', $txt['pm_rule_criteria_add'], ')</a>
				<br><br>
				', $txt['pm_rule_logic'], ':
				<select name="rule_logic" id="logic" onchange="rebuildRuleDesc();">
					<option value="and"', $context['rule']['logic'] == 'and' ? ' selected' : '', '>', $txt['pm_rule_logic_and'], '</option>
					<option value="or"', $context['rule']['logic'] == 'or' ? ' selected' : '', '>', $txt['pm_rule_logic_or'], '</option>
				</select>
			</fieldset>
			<fieldset>
				<legend>', $txt['pm_rule_actions'], '</legend>';

	// As with criteria - add a dummy action for "expansion".
	$context['rule']['actions'][] = array('t' => '', 'v' => '');

	// Print each action.
	$isFirst = true;
	foreach ($context['rule']['actions'] as $k => $action)
	{
		if (!$isFirst && $action['t'] == '')
			echo '<div id="removeonjs2">';
		elseif (!$isFirst)
			echo '<br>';

		echo '
				<select name="acttype[', $k, ']" id="acttype', $k, '" onchange="updateActionDef(', $k, '); rebuildRuleDesc();">
					<option value="">', $txt['pm_rule_sel_action'] , ':</option>
					<option value="lab"', $action['t'] == 'lab' ? ' selected' : '', '>', $txt['pm_rule_label'] , '</option>
					<option value="del"', $action['t'] == 'del' ? ' selected' : '', '>', $txt['pm_rule_delete'] , '</option>
				</select>
				<span id="labdiv', $k, '">
					<select name="labdef[', $k, ']" id="labdef', $k, '" onchange="rebuildRuleDesc();">
						<option value="">', $txt['pm_rule_sel_label'], '</option>';
		foreach ($context['labels'] as $label)
			if ($label['id'] != -1)
				echo '
						<option value="', ($label['id']), '"', $action['t'] == 'lab' && $action['v'] == $label['id'] ? ' selected' : '', '>', $label['name'], '</option>';

		echo '
					</select>
				</span>';

		if ($isFirst)
			$isFirst = false;
		elseif ($action['t'] == '')
			echo '
			</div>';
	}

	echo '
					<span id="actionAddHere"></span><br>
					<a href="#" onclick="addActionOption(); return false;" id="addonjs2" style="display: none;">(', $txt['pm_rule_add_action'], ')</a>
				</fieldset>
			<div class="cat_bar">
				<h3 class="catbg">', $txt['pm_rule_description'], '</h3>
			</div>
			<div class="information">
				<div id="ruletext">', $txt['pm_rule_js_disabled'], '</div>
			</div>
			<div class="righttext">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="submit" name="save" value="', $txt['pm_rule_save'], '" class="button_submit">
			</div>
		</div>
	</form>';

	// Now setup all the bits!
		echo '
	<script>';

	foreach ($context['rule']['criteria'] as $k => $c)
		echo '
			updateRuleDef(', $k, ');';

	foreach ($context['rule']['actions'] as $k => $c)
		echo '
			updateActionDef(', $k, ');';

	echo '
			rebuildRuleDesc();';

	// If this isn't a new rule and we have JS enabled remove the JS compatibility stuff.
	if ($context['rid'])
		echo '
			document.getElementById("removeonjs1").style.display = "none";
			document.getElementById("removeonjs2").style.display = "none";';

	echo '
			document.getElementById("addonjs1").style.display = "";
			document.getElementById("addonjs2").style.display = "";';

	echo '
		</script>';
}

/**
 * Template for showing all of a user's PM drafts.
 */
function template_showPMDrafts()
{
	global $context, $scripturl, $txt;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="generic_icons inbox"></span> ', $txt['drafts_show'], '
			</h3>
		</div>
		<div class="pagesection">
			<span>', $context['page_index'], '</span>
		</div>';

	// No drafts? Just show an informative message.
	if (empty($context['drafts']))
		echo '
		<div class="windowbg2 centertext">
			', $txt['draft_none'], '
		</div>';
	else
	{
		// For every draft to be displayed, give it its own div, and show the important details of the draft.
		foreach ($context['drafts'] as $draft)
		{
			echo '
				<div class="windowbg">
					<div class="counter">', $draft['counter'], '</div>
					<div class="topic_details">
						<h5><strong>', $draft['subject'], '</strong>&nbsp;';

			echo '
						</h5>
						<span class="smalltext">&#171;&nbsp;<strong>', $txt['draft_saved_on'], ':</strong> ', sprintf($txt['draft_days_ago'], $draft['age']), (!empty($draft['remaining']) ? ', ' . sprintf($txt['draft_retain'], $draft['remaining']) : ''), '&#187;</span><br>
						<span class="smalltext">&#171;&nbsp;<strong>', $txt['to'], ':</strong> ', implode(', ', $draft['recipients']['to']), '&nbsp;&#187;</span><br>
						<span class="smalltext">&#171;&nbsp;<strong>', $txt['pm_bcc'], ':</strong> ', implode(', ', $draft['recipients']['bcc']), '&nbsp;&#187;</span>
					</div>
					<div class="list_posts">
						', $draft['body'], '
					</div>
					<ul class="quickbuttons">
						<li><a href="', $scripturl, '?action=pm;sa=showpmdrafts;id_draft=', $draft['id_draft'], ';', $context['session_var'], '=', $context['session_id'], '"><span class="generic_icons modifybutton"></span>', $txt['draft_edit'], '</a></li>
						<li><a href="', $scripturl, '?action=pm;sa=showpmdrafts;delete=', $draft['id_draft'], ';', $context['session_var'], '=', $context['session_id'], '" data-confirm="', $txt['draft_remove'] ,'?" class="you_sure"><span class="generic_icons remove_button"></span>', $txt['draft_delete'], '</a></li>
					</ul>
				</div>';
		}
	}

	// Show page numbers.
	echo '
		<div class="pagesection">
			<span>', $context['page_index'], '</span>
		</div>';
}

?>