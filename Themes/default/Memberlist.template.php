<?php
require_once(__DIR__ . '/helpers/mischelpers.php');
use LightnCandy\LightnCandy;
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2017 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 3
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
	
	$template = file_get_contents(__DIR__ .  "/templates/memberlist_main.hbs");
	if (!$template) {
		die('Member template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
	    'partials' => Array(
	    	'button_strip' => file_get_contents(__DIR__ .  "/partials/button_strip.hbs")
	    ),
	    'helpers' => Array(
	    	'custom_fields' => 'custom_fields_helper',
	    	'text' => 'get_text' //comes from index
	    )
	));
	
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
		'scripturl' => $scripturl,
		'settings' => $settings
	);
	
	$template = file_get_contents(__DIR__ .  "/templates/memberlist_search.hbs");
	if (!$template) {
		die('Member template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
	    'partials' => Array(
	    	'button_strip' => file_get_contents(__DIR__ .  "/partials/button_strip.hbs")
	    ),
	    'helpers' => Array(
	    	'custom_fields' => 'custom_fields_helper',
	    	'text' => 'get_text' //comes from index
	    )
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}
?>