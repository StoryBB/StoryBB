<?php

/**
 * This class provides generic controls helpers for StoryBB's templates.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Template\Helper;

class Controls
{
	public static function _list()
	{
		return ([
			'captcha' => 'StoryBB\\Template\\Helper\\Controls::captcha',
			'richtexteditor' => 'StoryBB\\Template\\Helper\\Controls::richtexteditor',
			'richtextbuttons' => 'StoryBB\\Template\\Helper\\Controls::richedit_buttons',
			'genericlist' => 'StoryBB\\Template\\Helper\\Controls::genericlist',
		]);
	}

	public static function captcha($verify_id)
	{
		global $context, $txt;

		if (empty($context['controls']['verification'][$verify_id]))
			return '';

		$verify_context = &$context['controls']['verification'][$verify_id];
		$verify_context['total_items'] = count($verify_context['questions']) + ($verify_context['show_visual'] || $verify_context['can_recaptcha'] ? 1 : 0);
		$verify_context['hidden_input_name'] = $verify_context['empty_field'] ? $_SESSION[$verify_id . '_vv']['empty_field'] : '';

		$template = StoryBB\Template::load_partial('control_visual_verification');
		$phpStr = StoryBB\Template::compile($template, [], 'visual_verification');
		return new \LightnCandy\SafeString(StoryBB\Template::prepare($phpStr, [
			'verify_id' => $verify_id,
			'verify_context' => $verify_context,
			'txt' => $txt,
		]));	
	}

	public static function richtexteditor($editor_id, $smileyContainer = null, $bbcContainer = null) {
		global $context, $settings, $modSettings;

		if (empty($context['controls']['richedit'][$editor_id]))
			return '';

		$template = StoryBB\Template::load_partial('control_richedit');
		$phpStr = StoryBB\Template::compile($template, [], 'richedit');
		return new \LightnCandy\SafeString(StoryBB\Template::prepare($phpStr, [
			'editor_id' => $editor_id,
			'editor_context' => $context['controls']['richedit'][$editor_id],
			'context' => $context,
			'settings' => $settings,
			'modSettings' => $modSettings,
			'smileyContainer' => $smileyContainer,
			'bbcContainer' => $bbcContainer,
		]));
	}

	public static function richedit_buttons($editor_id) {
		global $context, $settings, $modSettings, $txt;

		if (empty($context['controls']['richedit'][$editor_id]))
			return '';

		$template = StoryBB\Template::load_partial('control_richedit_buttons');
		$phpStr = StoryBB\Template::compile($template, [], 'richedit_buttons');
		return new \LightnCandy\SafeString(StoryBB\Template::prepare($phpStr, [
			'editor_id' => $editor_id,
			'editor_context' => $context['controls']['richedit'][$editor_id],
			'context' => $context,
			'settings' => $settings,
			'modSettings' => $modSettings,
			'txt' => $txt,
		]));
	}

	public static function genericlist($list_id = null)
	{
		global $context;
		// Get a shortcut to the current list.
		$list_id = $list_id === null ? (!empty($context['default_list']) ? $context['default_list'] : '') : $list_id;
		if (empty($list_id) || empty($context[$list_id]))
			return;
		$cur_list = &$context[$list_id];
		$cur_list['list_id'] = $list_id;
		
		$template = StoryBB\Template::load_partial('generic_list');
		$phpStr = StoryBB\Template::compile($template, [], 'genericlist');
		return new \LightnCandy\SafeString(StoryBB\Template::prepare($phpStr, [
			'context' => $context,
			'cur_list' => $cur_list,
			'headerCount' => count($cur_list['headers'])
		]));
	}
}

?>