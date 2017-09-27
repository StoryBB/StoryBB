<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

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

?>