<?php

use LightnCandy\LightnCandy;

/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

function include_ic_partial($template) {
    $func = 'template_ic_block_' . $template;
	return $func();
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
    
    $template = loadTemplateFile('board_main');

    $phpStr = compileTemplate($template, [
        'helpers' => [
            'partial_helper' => 'include_ic_partial',
            'jsontext' => 'stringhelper_string_json'
        ]
    ]);

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
    
    $template = loadTemplatePartial('board_ic_recent');

    $phpStr = compileTemplate($template);

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
    
    $template = loadTemplatePartial('board_ic_stats');

    $phpStr = compileTemplate($template);

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
    
    $template = loadTemplatePartial('board_ic_online');

    $phpStr = compileTemplate($template);

	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

?>