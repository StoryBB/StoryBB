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

function comma_format_helper($number, $override_decimal_count = false)
{
	return new \LightnCandy\SafeString(comma_format($number, $override_decimal_count));
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

        $template = file_get_contents(__DIR__ . "/templates/newsfader.hbs");
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
	global $context, $txt, $scripturl, $options, $settings;

    $data = Array(
        'context' => $context,
        'txt' => $txt,
        'scripturl' => $scripturl,
        'options' => $options,
        'settings' => $settings,
    );
    
    $template = file_get_contents(__DIR__."/templates/board_main.hbs");
    if (!$template) {
        die('Template did not load!');
    }

    $phpStr = LightnCandy::compile($template, Array(
        'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
	    'partials' => Array(
	    	'button_strip' => file_get_contents(__DIR__ .  "/partials/button_strip.hbs"),
            'board_info_center' => file_get_contents(__DIR__ . "/templates/board_info_center.hbs")
	    ),
        'helpers' => Array(
        	'approvals' => 'approval_helper',
        	'link_moderators' => 'moderators_helper',
        	'comma_format' => 'comma_format_helper',
        	'or' => 'logichelper_or',
        	'and' => 'logichelper_and',
        	'gt' => 'logichelper_gt',
            'textTemplate' => 'textTemplate',
            'partial_helper' => 'include_ic_partial',
            'json' => 'stringhelper_json'
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
	global $context, $options, $txt, $settings, $modSettings;
	
	//early-exit:
//	if (empty($context['info_center']))
//		return;

	$data = Array(
        'context' => $context,
        'txt' => $txt,
        'options' => $options,
        'settings' => $settings,
        'modSettings' => $modSettings,
    );
    
    $template = file_get_contents(__DIR__."/templates/board_info_center.hbs");
    if (!$template) {
        die('Template did not load!');
    }

    $phpStr = LightnCandy::compile($template, Array(
        'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
        'helpers' => Array(
        	'partial_helper' => 'include_ic_partial',
        	'JavaScriptEscape' => 'JSEscape',
        	'textTemplate' => 'textTemplate'
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
    
    $template = file_get_contents(__DIR__."/partials/board_ic_recent.hbs");
    if (!$template) {
        die('Template did not load!');
    }

    $phpStr = LightnCandy::compile($template, Array(
        'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
        'helpers' => Array(
        	'partial_helper' => 'include_ic_partial',
        	'JavaScriptEscape' => 'JSEscape',
        	'textTemplate' => 'textTemplate'
        )
    ));

	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * The stats section of the info center
 */
function template_ic_block_stats()
{
	global $scripturl, $txt, $context, $settings;
	$data = Array(
        'context' => $context,
        'txt' => $txt,
        'scripturl' => $scripturl,
        'settings' => $settings
    );
    
    $template = file_get_contents(__DIR__."/partials/board_ic_stats.hbs");
    if (!$template) {
        die('Template did not load!');
    }

    $phpStr = LightnCandy::compile($template, Array(
        'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
        'helpers' => Array(
        )
    ));

	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * The who's online section of the admin center
 */
function template_ic_block_online()
{
	global $context, $scripturl, $txt, $modSettings, $settings;
	
	$data = [
        'context' => $context,
        'scripturl' => $scripturl,
        'txt' => $txt,
        'modSettings' => $modSettings,
        'settings' => $settings,
    ];
    
    $template = file_get_contents(__DIR__."/partials/board_ic_online.hbs");
    if (!$template) {
        die('Template did not load!');
    }

    $phpStr = LightnCandy::compile($template, Array(
        'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
        'helpers' => Array(
        	'and' => 'logichelper_and',
        	'eq' => 'logichelper_eq',
        	'implode' => 'implode_sep',
        	'textTemplate' => 'textTemplate',
            'comma_format' => 'comma_format_helper'
        )
    ));

	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

?>