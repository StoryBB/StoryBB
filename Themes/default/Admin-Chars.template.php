<?php

function template_membergroup_badges()
{
	global $scripturl, $context, $txt;

	echo '
		<form method="post" action="', $scripturl, '?action=admin;area=membergroups;sa=badges;', $context['session_var'], '=', $context['session_id'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['char_group_level_acct'], '</h3>
			</div>
			<div class="windowbg2">
				<ul class="sortable">';

	foreach ($context['groups']['accounts'] as $group)
		display_group($group);

	echo '
				</ul>
			</div>

			<div class="cat_bar">
				<h3 class="catbg">', $txt['char_group_level_char'], '</h3>
			</div>
			<div class="windowbg2">
				<ul class="sortable">';

	foreach ($context['groups']['characters'] as $group)
		display_group($group);

	echo '
				</ul>
			</div>
			<input type="submit" value="', $txt['save'], '" class="button_submit">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<div class="clear"></div>
		</form>';
}

function display_group($group)
{
	global $txt, $settings;
	static $order = 1;

	echo '
					<li class="character_group">';

	if (!empty($group['online_color']))
		echo '
						<div class="group_name"><span style="color:', $group['online_color'], '">', $group['group_name'], '</span></div>';
	else
		echo '
						<div class="group_name">', $group['group_name'], '</div>';

	if (!empty($group['parsed_icons']))
		echo '
						<div class="group_icons">', $group['parsed_icons'], '</div>';
	else
		echo '
						<div class="group_icons">', $txt['no_badge'], '</div>';

	echo '
						<img src="', $settings['default_images_url'] . '/toggle.png" class="handle">';

	echo '
						<input type="hidden" name="group[', $group['id_group'], ']" value="', $group['id_group'], '">
					</li>';
	$order++;
}

function template_char_templates()
{
	global $context, $txt, $scripturl, $settings;
	echo '
		<form method="post" action="', $scripturl, '?action=admin;area=templates;sa=reorder;', $context['session_var'], '=', $context['session_id'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['char_templates'], '</h3>
			</div>
			<div class="windowbg2">';

	if (!empty($context['char_templates']))
	{
		echo '
				<ul class="sortable">';
		foreach ($context['char_templates'] as $id_template => $template)
		{
			echo '
					<li class="character_group">
						<div class="group_name"><a href="', $scripturl, '?action=admin;area=templates;sa=edit;template_id=', $id_template, '">', $template['template_name'], '</a></div>
						<img src="', $settings['default_images_url'], '/toggle.png" class="handle">
						<input type="hidden" name="template[', $id_template, ']" value="', $id_template, '">
					</li>';
		}
		echo '
				</ul>';
	} else {
		echo $txt['char_templates_none'];
	}

	echo '
			</div>
			<div class="floatright">
				<a href="', $scripturl, '?action=admin;area=templates;sa=add" class="button">', $txt['char_templates_add'], '</a>
				<input type="submit" name="save" value="', $txt['save'], '" class="button_submit">
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<div class="clear"></div>
		</form>';
}

function template_char_template_edit()
{
	global $context, $txt, $scripturl;
	echo '
		<form method="post" action="', $scripturl, '?action=admin;area=templates;sa=save;', $context['session_var'], '=', $context['session_id'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['char_templates'], '</h3>
			</div>
			<div class="windowbg2">
				 ', $txt['char_template_name'], ' <input type="text" name="template_name" value="', $context['template_name'], '"><br><br>';

	template_control_richedit('message', null, 'bbcBox');
	echo '
				<br>
				<div>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="template_id" value="', $context['template_id'], '">
					<input type="submit" value="', $txt['save'], '" class="button_submit">
				</div>
			</div>
		</form>';
}

?>