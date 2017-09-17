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
	
	$template = loadTemplateFile('login_tfa');

	$phpStr = compileTemplate($template);
	
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
	
	$template = loadTemplateFile('login_admin');

	$phpStr = compileTemplate($template);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

?>