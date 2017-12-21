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
 * Before showing users a registration form, show them the registration agreement.
 */
function template_registration_agreement()
{
	global $context, $scripturl, $txt;
	
	$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl
	);
	
	$template = loadTemplateFile('register_agreement');

	$phpStr = compileTemplate($template);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * Before registering - get their information.
 */
function template_registration_form()
{
	global $context, $scripturl, $txt, $modSettings;
	
	//Preprocessing: sometimes we're given eval strings to make options for custom fields.
	//WE REALLY SHOULDN'T DO THIS.
	//But for now:
	if (!empty($context['profile_fields'])) {
		foreach ($context['profile_fields'] as $key => $field) {
			if ($field['type'] == 'select' && !is_array($field['options'])) {
				$field['options'] = eval($field['options']);
			}
		}
	}

	$verify_id = $context['visual_verification_id'];
		
	$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'modSettings' => $modSettings,
	);
	
	$template = loadTemplateFile('register_form');

	$phpStr = compileTemplate($template, [
	    'helpers' => Array(
	    	'profile_callback_helper' => function ($field) {
	            if ($field['type'] == 'callback')
				{
					if (isset($field['callback_func']) && function_exists('template_profile_' . $field['callback_func']))
					{
						$callback_func = 'template_profile_' . $field['callback_func'];
						$callback_func();
					}
				}
	        },
	        'makeHTTPS' => function($url) { 
	        	return strtr($url, array('http://' => 'https://'));
	        },
	        'field_isText' => function($type) {
	        	return in_array($type, array('int', 'float', 'text', 'password'));
	        },
	    )
	]);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * After registration... all done ;).
 */
function template_after()
{
	global $context;

	// Not much to see here, just a quick... "you're now registered!" or what have you.
	return '
		<div id="registration_success">
			<div class="cat_bar">
				<h3 class="catbg">' . $context['title'] . '</h3>
			</div>
			<div class="windowbg noup">
				<p>' . $context['description'] . '</p>
			</div>
		</div>';
}

/**
 * Form for editing the agreement shown for people registering to the forum.
 */
function template_edit_agreement()
{
	global $context, $scripturl, $txt;
	
	$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'editable_agreements' => count($context['editable_agreements']) > 1
	);
	
	$template = loadTemplateFile('register_edit_agreement');

	$phpStr = compileTemplate($template);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

?>