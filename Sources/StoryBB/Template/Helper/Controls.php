<?php

/**
 * This class provides generic controls helpers for StoryBB's templates.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Template\Helper;

/**
 * This class provides generic controls helpers for StoryBB's templates.
 */
class Controls
{
	/** @var $menu_context Local storage for a menu to be rendered by the template */
	protected static $menu_context;

	/**
	 * List the different helpers available in this class.
	 * @return array Helpers, assocating name to method
	 */
	public static function _list()
	{
		return ([
			'captcha' => 'StoryBB\\Template\\Helper\\Controls::captcha',
			'richtexteditor' => 'StoryBB\\Template\\Helper\\Controls::richtexteditor',
			'richtextbuttons' => 'StoryBB\\Template\\Helper\\Controls::richedit_buttons',
			'genericlist' => 'StoryBB\\Template\\Helper\\Controls::genericlist',
			'genericmenucontext' => 'StoryBB\\Template\\Helper\\Controls::genericmenucontext',
		]);
	}

	/**
	 * Return the HTML necessary for a CAPTCHA to be diplayed.
	 * @param string $verify_id The internal ID of a CAPTCHA
	 * @return string|SafeString A string to be exported to display the CAPTCHA
	 */
	public static function captcha($verify_id)
	{
		global $context, $txt;

		if (empty($context['controls']['verification'][$verify_id]))
			return '';

		$verify_context = &$context['controls']['verification'][$verify_id];
		$verify_context['total_items'] = count($verify_context['questions']) + ($verify_context['show_visual'] || $verify_context['can_recaptcha'] ? 1 : 0);
		$verify_context['hidden_input_name'] = $verify_context['empty_field'] ? $_SESSION[$verify_id . '_vv']['empty_field'] : '';

		$template = StoryBB\Template::load_partial('control_visual_verification');
		$phpStr = StoryBB\Template::compile($template, [], 'visual_verification-' . \StoryBB\Template::get_theme_id('partials', 'control_visual_verification'));
		return new \LightnCandy\SafeString(StoryBB\Template::prepare($phpStr, [
			'verify_id' => $verify_id,
			'verify_context' => $verify_context,
			'txt' => $txt,
		]));	
	}

	/**
	 * Return the HTML necessary to render a rich text editor widget
	 * @param string $editor_id The editor ID to refer to this widget
	 * @param string $smileyContainer The ID of the HTML element where smileys should be rendered
	 * @param string $bbcContainer The ID of the HTML element where BBC should be rendered
	 * @return string|SafeString A string to be exported to display the WYSIWYG editor
	 */
	public static function richtexteditor($editor_id, $smileyContainer = null, $bbcContainer = null)
	{
		global $context, $settings, $modSettings;

		if (empty($context['controls']['richedit'][$editor_id]))
			return '';

		$template = StoryBB\Template::load_partial('control_richedit');
		$phpStr = StoryBB\Template::compile($template, [], 'richedit-' . \StoryBB\Template::get_theme_id('partials', 'control_richedit'));
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

	/**
	 * Return the HTML buttons attached to a rich text editor form
	 * @param string $editor_id The editor ID to which the buttons should be attached
	 * @return string|SafeString A string to be exported to display the form buttons
	 */
	public static function richedit_buttons($editor_id)
	{
		global $context, $settings, $modSettings, $txt;

		if (empty($context['controls']['richedit'][$editor_id]))
			return '';

		$template = StoryBB\Template::load_partial('control_richedit_buttons');
		$phpStr = StoryBB\Template::compile($template, [], 'richedit_buttons-' . \StoryBB\Template::get_theme_id('partials', 'control_richedit_buttons'));
		return new \LightnCandy\SafeString(StoryBB\Template::prepare($phpStr, [
			'editor_id' => $editor_id,
			'editor_context' => $context['controls']['richedit'][$editor_id],
			'context' => $context,
			'settings' => $settings,
			'modSettings' => $modSettings,
			'txt' => $txt,
		]));
	}

	/**
	 * Return the HTML buttons attached to a generic list
	 * @param string $list_id The ID of the list to be displayed
	 * @return string|SafeString A string to be exported to display the generic list
	 */
	public static function genericlist($list_id = null)
	{
		global $context;
		// Get a shortcut to the current list.
		$list_id = $list_id === null ? (!empty($context['default_list']) ? $context['default_list'] : '') : $list_id;
		if (empty($list_id) || empty($context[$list_id]))
			return;
		$cur_list = &$context[$list_id];
		$cur_list['list_id'] = $list_id;
		
		$template = \StoryBB\Template::load_partial('generic_list');
		$phpStr = \StoryBB\Template::compile($template, [], 'genericlist-' . \StoryBB\Template::get_theme_id('partials', 'generic_list'));
		return new \LightnCandy\SafeString(\StoryBB\Template::prepare($phpStr, [
			'context' => $context,
			'cur_list' => $cur_list,
			'headerCount' => count($cur_list['headers'])
		]));
	}

	/**
	 * Initialises a generic menu in a template ready to hand off to partials
	 */
	public static function genericmenucontext()
	{
		global $context;

		$context['cur_menu_id'] = isset($context['cur_menu_id']) ? $context['cur_menu_id'] + 1 : 1;
		$context['current_menu_context'] = &$context['menu_data_' . $context['cur_menu_id']];
		$tab_context = &$context['current_menu_context']['tab_data'];

		// Run through the menu looking for whether we've set up menu tabs or not and whether we need to.
		if (empty($context['tabs']))
		{
			foreach ($context['current_menu_context']['sections'] as $section)
			{
				foreach ($section['areas'] as $area)
				{
					if (!empty($area['selected']) && empty($context['tabs']))
					{
						$context['tabs'] = isset($area['subsections']) ? $area['subsections'] : [];
					}
				}
			}
		}

		// Exactly how many tabs do we have?
		if (!empty($context['tabs']))
		{
			foreach ($context['tabs'] as $id => $tab)
			{
				// Can this not be accessed?
				if (!empty($tab['disabled']))
				{
					$tab_context['tabs'][$id]['disabled'] = true;
					continue;
				}

				// Did this not even exist - or do we not have a label?
				if (!isset($tab_context['tabs'][$id]))
					$tab_context['tabs'][$id] = array('label' => $tab['label']);
				elseif (!isset($tab_context['tabs'][$id]['label']))
					$tab_context['tabs'][$id]['label'] = $tab['label'];

				// Has a custom URL defined in the main admin structure?
				if (isset($tab['url']) && !isset($tab_context['tabs'][$id]['url']))
					$tab_context['tabs'][$id]['url'] = $tab['url'];

				// Any additional paramaters for the url?
				if (isset($tab['add_params']) && !isset($tab_context['tabs'][$id]['add_params']))
					$tab_context['tabs'][$id]['add_params'] = $tab['add_params'];

				// Has it been deemed selected?
				if (!empty($tab['is_selected']))
					$tab_context['tabs'][$id]['is_selected'] = true;

				// Does it have its own help?
				if (!empty($tab['help']))
					$tab_context['tabs'][$id]['help'] = $tab['help'];

				// Is this the last one?
				if (!empty($tab['is_last']) && !isset($tab_context['override_last']))
					$tab_context['tabs'][$id]['is_last'] = true;
			}

			// Find the selected tab
			foreach ($tab_context['tabs'] as $sa => $tab)
			{
				if (!empty($tab['is_selected']) || (isset($context['current_menu_context']['current_subsection']) && $context['current_menu_context']['current_subsection'] == $sa))
				{
					$selected_tab = $tab;
					$tab_context['tabs'][$sa]['is_selected'] = true;
				}
			}
		}
		$context['selected_tab'] = !empty($selected_tab) ? $selected_tab : '';
	}
}
