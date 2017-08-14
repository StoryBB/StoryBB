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
 * This is just the basic "login" form.
 */
function template_login()
{
	global $context, $settings, $scripturl, $modSettings, $txt;
	
	$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'settings' => $settings,
		'modSettings' => $modSettings,
		'ajax_nonssl' => !empty($context['from_ajax']) && (empty($modSettings['force_ssl']) || $modSettings['force_ssl'] == 2)
	);
	
	$template = file_get_contents(__DIR__ .  "/templates/login_main.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'helpers' => []
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * TFA authentication form
 */
function template_login_tfa()
{
	global $context, $scripturl, $modSettings, $txt;
		
	$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'modSettings' => $modSettings,
		'SESSION' => $_SESSION
	);
	
	$template = file_get_contents(__DIR__ .  "/templates/login_tfa.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'helpers' => []
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * Tell a guest to get lost or login!
 */
function template_kick_guest()
{
	global $context, $settings, $scripturl, $modSettings, $txt;
	
	$data = Array(
		'context' => $context,
		'settings' => $settings,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'modSettings' => $modSettings
	);
	
	$template = file_get_contents(__DIR__ .  "/templates/login_kick_guest.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'helpers' => [
	    	'template' => textTemplate,
	    	'concat' => concat
	    ]
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * This is for maintenance mode.
 */
function template_maintenance()
{
	global $context, $settings, $txt, $modSettings;
		
	$data = Array(
		'context' => $context,
		'settings' => $settings,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'modSettings' => $modSettings
	);
	
	$template = file_get_contents(__DIR__ .  "/templates/login_maintenance.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'helpers' => [
	    	'template' => 'textTemplate'
	    ]
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * This is for the security stuff - makes administrators login every so often.
 */
function template_admin_login()
{
	global $context, $settings, $scripturl, $txt, $modSettings;
		
	$data = Array(
		'context' => $context,
		'settings' => $settings,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'modSettings' => $modSettings,
		'action' => !empty($modSettings['force_ssl']) && $modSettings['force_ssl'] < 2 ? strtr($scripturl, array('http://' => 'https://')) : $scripturl
	);
	
	$template = file_get_contents(__DIR__ .  "/templates/login_admin.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'helpers' => [
	    	'template' => 'textTemplate'
	    ]
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * Activate your account manually?
 */
function template_retry_activate()
{
	//login_manual_activate
	global $context, $txt, $scripturl;
			
	$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl
	);
	
	$template = file_get_contents(__DIR__ .  "/templates/login_manual_activate.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'helpers' => [
	    	'template' => 'textTemplate'
	    ]
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * The form for resending the activation code.
 */
function template_resend()
{
	global $context, $txt, $scripturl;

	$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl
	);
	
	$template = file_get_contents(__DIR__ .  "/templates/login_resend.hbs");
	if (!$template) {
		die('Template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'helpers' => [
	    	'template' => 'textTemplate'
	    ]
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

?>