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
	
	$template = file_get_contents(__DIR__ .  "/templates/register_agreement.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'helpers' => Array(
	    )
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

function gen_tabIndexes($start){
	$i = 0;
	while(true) {
		echo "DEBUG: " . $i;
        yield $i++; 
	}
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
		'verification_visual' => Array(
			'verify_context' => $context['controls']['verification'][$verify_id],
			'verify_id' => $verify_id,
			'txt' => $txt,
			'hinput_name' => $_SESSION[$verify_id . '_vv']['empty_field'],
			'quick_reply' => false
		)
	);
	
	$template = file_get_contents(__DIR__ .  "/templates/register_form.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
	    'partials' => Array(
	    	'visual_verification_control' => file_get_contents(__DIR__ .  "/partials/control_visual_verification.hbs")
	    ),
	    'helpers' => Array(
	    	'or' => 'logichelper_or',
	    	'and' => 'logichelper_and',
	    	'eq' => 'logichelper_eq',
	    	'lt' => 'logichelper_lt',
	    	'not' => 'logichelper_not',
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
	        'template_control_verification' => 'template_control_verification'
	    )
	));
	
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
 * Template for giving instructions about COPPA activation.
 */
function template_coppa()
{
	global $context, $txt, $scripturl;
	
	$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl
	);
	
	$template = file_get_contents(__DIR__ .  "/templates/register_coppa.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'helpers' => Array(
	    )
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * An easily printable form for giving permission to access the forum for a minor.
 */
function template_coppa_form()
{
	global $context, $txt;
	
	$data = Array(
		'context' => $context,
		'txt' => $txt
	);
	
	$template = file_get_contents(__DIR__ .  "/templates/register_coppa_form.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'helpers' => Array(
	    )
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * Show a window containing the spoken verification code.
 */
function template_verification_sound()
{
	global $context, $settings, $txt, $modSettings;
	
	$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'settings' => $settings,
		'modSettings' => $modSettings
	);
	
	$template = file_get_contents(__DIR__ .  "/layouts/popup.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'helpers' => Array(
	    )
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	$content = $renderer($data);
	
	$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'content' => $content,
		'id' => ''
	);
	
	$template = file_get_contents(__DIR__ .  "/templates/register_sound_verification.hbs");
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
 * The template for the form allowing an admin to register a user from the admin center.
 */
function template_admin_register()
{
	global $context, $scripturl, $txt, $modSettings;

	$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'modSettings' => $modSettings
	);
	
	$template = file_get_contents(__DIR__ .  "/templates/register_admin.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'helpers' => Array(
	    	'eq' => logichelper_eq,
	    	'gt' => logichelper_gt
	    )
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
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
	
	$template = file_get_contents(__DIR__ .  "/templates/register_edit_agreement.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'helpers' => Array(
	    )
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * Template for editing reserved words.
 */
function template_edit_reserved_words()
{
	global $context, $scripturl, $txt;
		
	$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'editable_agreements' => count($context['editable_agreements']) > 1
	);
	
	$template = file_get_contents(__DIR__ .  "/templates/register_edit_reservedwords.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'helpers' => Array(
	    )
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

?>