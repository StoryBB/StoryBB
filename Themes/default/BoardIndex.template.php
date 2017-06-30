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
 * The top part of the outer layer of the boardindex
 */
function approval_helper($string, $unapproved_topics, $unapproved_posts)
{
    return new \LightnCandy\SafeString(sprintf($string,
    	$unapproved_topics,
    	$unapproved_posts
    ));
}

function moderators_helper($link_moderators, $txt_moderator, $txt_moderators)
{
	$moderators_string = ( count($link_moderators) == 1 ) ? $txt_moderator.":" : $txt_moderators.":";
	foreach ( $link_moderators as $cur_moderator ) 
	{
		$moderators_string .= $cur_moderator;
	}
	return new \LightnCandy\SafeString($moderators_string);
}

function comma_format_helper($number, $override_decimal_count)
{
	return new \LightnCandy\SafeString(comma_format($number, $override_decimal_count));
}

function template_button_strip_helper($button_strip,$direction,$strip_options)
{
	return new \LightnCandy\SafeString(template_button_strip($button_strip,$direction,$strip_options));
}

function template_boardindex_outer_above()
{
	return template_newsfader();
}

function include_ic_partial($template) {
	$func = 'template_ic_block_' . $template;
	return $func();
}

/**
 * This shows the newsfader
 */
function template_newsfader()
{
	global $context, $settings, $options, $txt;

	// Show the news fader?  (assuming there are things to show...)
	if (!empty($settings['show_newsfader']) && !empty($context['news_lines']))
	{
        $data = Array(
            'context' => $context,
            'txt' => $txt,
            'settings' => $settings,
            'options' => $options
        );

        $template = file_get_contents(__DIR__ . "/layouts/newsfader.hbs");
        if (!$template) {
            die('Template did not load!');
        }

        $phpStr = LightnCandy::compile($template, Array(
            'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
            'helpers' => Array(
            )
        ));

		$renderer = LightnCandy::prepare($phpStr);
		echo $renderer($data);
	}
}

/**
 * This actually displays the board index
 */
function template_main()
{
	global $context, $txt, $scripturl;

    $data = Array(
        'context' => $context,
        'txt' => $txt,
        'scripturl' => $scripturl
    );
    
    $template = file_get_contents(__DIR__."/layouts/main.hbs");
    if (!$template) {
        die('Template did not load!');
    }

    $phpStr = LightnCandy::compile($template, Array(
        'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
        'helpers' => Array(
        	'approvals' => 'approval_helper',
        	'link_moderators' => 'moderators_helper',
        	'comma_format' => 'comma_format_helper',
        	'template_button_strip' => 'template_button_strip_helper',
        	'or' => 'logichelper_or',
        	'and' => 'logichelper_and',
        	'gt' => 'logichelper_gt'
        )
    ));

	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * The lower part of the outer layer of the board index
 */
function template_boardindex_outer_below()
{
	return template_info_center();
}

/**
 * Displays the info center
 */
function template_info_center()
{
	global $context, $options, $txt;
	
	//early-exit:
	if (empty($context['info_center']))
		return;
	
	$data = Array(
        'context' => $context,
        'txt' => $txt,
        'options' => $options
    );
    
    $template = file_get_contents(__DIR__."/templates/board_info_center.hbs");
    if (!$template) {
        die('Template did not load!');
    }

    $phpStr = LightnCandy::compile($template, Array(
        'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
        'helpers' => Array(
        	'partial_helper' => include_ic_partial,
        	'JavaScriptEscape' => JSEscape,
        	'textTemplate' => textTemplate
        )
    ));

	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * The recent posts section of the info center
 */
function template_ic_block_recent()
{
	global $context, $scripturl, $settings, $txt;
	
	$data = Array(
        'context' => $context,
        'txt' => $txt,
        'scripturl' => $scripturl,
        'settings' => $settings
    );
    
    $template = file_get_contents(__DIR__."/templates/board_ic_recent.hbs");
    if (!$template) {
        die('Template did not load!');
    }

    $phpStr = LightnCandy::compile($template, Array(
        'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
        'helpers' => Array(
        	'partial_helper' => include_ic_partial,
        	'JavaScriptEscape' => JSEscape,
        	'textTemplate' => textTemplate
        )
    ));

	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * The calendar section of the info center
 */
function template_ic_block_calendar()
{
	global $context, $scripturl, $txt, $settings;

	// Show information about events, birthdays, and holidays on the calendar.
	echo '
			<div class="sub_bar">
				<h4 class="subbg">
					<a href="', $scripturl, '?action=calendar' . '"><span class="generic_icons calendar"></span> ', $context['calendar_only_today'] ? $txt['calendar_today'] : $txt['calendar_upcoming'], '</a>
				</h4>
			</div>';

	// Holidays like "Christmas", "Chanukah", and "We Love [Unknown] Day" :P.
	if (!empty($context['calendar_holidays']))
		echo '
				<p class="inline holiday"><span>', $txt['calendar_prompt'], '</span> ', implode(', ', $context['calendar_holidays']), '</p>';

	// People's birthdays. Like mine. And yours, I guess. Kidding.
	if (!empty($context['calendar_birthdays']))
	{
		echo '
				<p class="inline">
					<span class="birthday">', $context['calendar_only_today'] ? $txt['birthdays'] : $txt['birthdays_upcoming'], '</span>';
		// Each member in calendar_birthdays has: id, name (person), age (if they have one set?), is_last. (last in list?), and is_today (birthday is today?)
		foreach ($context['calendar_birthdays'] as $member)
			echo '
					<a href="', $scripturl, '?action=profile;u=', $member['id'], '">', $member['is_today'] ? '<strong class="fix_rtl_names">' : '', $member['name'], $member['is_today'] ? '</strong>' : '', isset($member['age']) ? ' (' . $member['age'] . ')' : '', '</a>', $member['is_last'] ? '' : ', ';
		echo '
				</p>';
	}

	// Events like community get-togethers.
	if (!empty($context['calendar_events']))
	{
		echo '
				<p class="inline">
					<span class="event">', $context['calendar_only_today'] ? $txt['events'] : $txt['events_upcoming'], '</span> ';

		// Each event in calendar_events should have:
		//		title, href, is_last, can_edit (are they allowed?), modify_href, and is_today.
		foreach ($context['calendar_events'] as $event)
			echo '
					', $event['can_edit'] ? '<a href="' . $event['modify_href'] . '" title="' . $txt['calendar_edit'] . '"><span class="generic_icons calendar_modify"></span></a> ' : '', $event['href'] == '' ? '' : '<a href="' . $event['href'] . '">', $event['is_today'] ? '<strong>' . $event['title'] . '</strong>' : $event['title'], $event['href'] == '' ? '' : '</a>', $event['is_last'] ? '<br>' : ', ';
		echo '
				</p>';
	}
}

/**
 * The stats section of the info center
 */
function template_ic_block_stats()
{
	global $scripturl, $txt, $context, $settings;

	// Show statistical style information...
	echo '
			<div class="sub_bar">
				<h4 class="subbg">
					<a href="', $scripturl, '?action=stats" title="', $txt['more_stats'], '"><span class="generic_icons stats"></span> ', $txt['forum_stats'], '</a>
				</h4>
			</div>
			<p class="inline">
				', $context['common_stats']['boardindex_total_posts'], '', !empty($settings['show_latest_member']) ? ' - ' . $txt['latest_member'] . ': <strong> ' . $context['common_stats']['latest_member']['link'] . '</strong>' : '', '<br>
				', (!empty($context['latest_post']) ? $txt['latest_post'] . ': <strong>&quot;' . $context['latest_post']['link'] . '&quot;</strong>  (' . $context['latest_post']['time'] . ')<br>' : ''), '
				<a href="', $scripturl, '?action=recent">', $txt['recent_view'], '</a>
			</p>';
}

/**
 * The who's online section of the admin center
 */
function template_ic_block_online()
{
	global $context, $scripturl, $txt, $modSettings, $settings;
	// "Users online" - in order of activity.
	echo '
			<div class="sub_bar">
				<h4 class="subbg">
					', $context['show_who'] ? '<a href="' . $scripturl . '?action=who">' : '', '<span class="generic_icons people"></span> ', $txt['online_users'], '', $context['show_who'] ? '</a>' : '', '
				</h4>
			</div>
			<p class="inline">
				', $context['show_who'] ? '<a href="' . $scripturl . '?action=who">' : '', '<strong>', $txt['online'], ': </strong>', comma_format($context['num_guests']), ' ', $context['num_guests'] == 1 ? $txt['guest'] : $txt['guests'], ', ', comma_format($context['num_users_online']), ' ', $context['num_users_online'] == 1 ? $txt['user'] : $txt['users'];

	// Handle hidden users and buddies.
	$bracketList = array();
	if ($context['show_buddies'])
		$bracketList[] = comma_format($context['num_buddies']) . ' ' . ($context['num_buddies'] == 1 ? $txt['buddy'] : $txt['buddies']);
	if (!empty($context['num_spiders']))
		$bracketList[] = comma_format($context['num_spiders']) . ' ' . ($context['num_spiders'] == 1 ? $txt['spider'] : $txt['spiders']);
	if (!empty($context['num_users_hidden']))
		$bracketList[] = comma_format($context['num_users_hidden']) . ' ' . ($context['num_spiders'] == 1 ? $txt['hidden'] : $txt['hidden_s']);

	if (!empty($bracketList))
		echo ' (' . implode(', ', $bracketList) . ')';

	echo $context['show_who'] ? '</a>' : '', '

				&nbsp;-&nbsp;', $txt['most_online_today'], ': <strong>', comma_format($modSettings['mostOnlineToday']), '</strong>&nbsp;-&nbsp;
				', $txt['most_online_ever'], ': ', comma_format($modSettings['mostOnline']), ' (', timeformat($modSettings['mostDate']), ')<br>';

	// Assuming there ARE users online... each user in users_online has an id, username, name, group, href, and link.
	if (!empty($context['users_online']))
	{
		echo '
				', sprintf($txt['users_active'], $modSettings['lastActive']), ': ', implode(', ', $context['list_users_online']);

		// Showing membergroups?
		if (!empty($settings['show_group_key']) && !empty($context['membergroups']))
			echo '
				<span class="membergroups">' . implode(',&nbsp;', $context['membergroups']) . '</span>';
	}

	echo '
			</p>';
}

?>