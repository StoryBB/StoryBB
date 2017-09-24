<?php

use LightnCandy\LightnCandy;
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */
 
 function custom_fields_helper($field, $options) {
 	return new \LightnCandy\SafeString('<td class="righttext">' . $options[$field] . '</td>');
 }

/**
 * Displays a sortable listing of all members registered on the forum.
 */
function template_main()
{
	global $context, $settings, $scripturl, $txt;
	
	$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'settings' => $settings
	);
	
	$template = loadTemplateFile('memberlist_main');

	$phpStr = compileTemplate($template, [
	    'helpers' => [
	    	'custom_fields' => 'custom_fields_helper',
	    	'text' => 'get_text' //comes from index
	    ]
	]);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

function checkDefaultOption($defaults, $item) {
	return in_array($defaults, $item) ? 'checked' : '';
}

/**
 * A page allowing people to search the member list.
 */
function template_search()
{
	global $context, $scripturl, $txt;
	$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl
	);
	
	$template = loadTemplateFile('memberlist_search');

	$phpStr = compileTemplate($template, [
	    'helpers' => [
	    	'custom_fields' => 'custom_fields_helper',
	    	'text' => 'get_text' //comes from index
	    ]
	]);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

?>