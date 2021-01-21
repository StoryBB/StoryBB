<?php

/**
 * Displays the warnings for a user.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

class WarningListing extends AbstractProfileController
{
	public function display_action()
	{
		global $modSettings, $context, $sourcedir, $txt, $scripturl;

		$memID = $this->params['u'];

		// Firstly, can we actually even be here?
		if (!($context['user']['is_owner'] && allowedTo('view_warning_own')) && !allowedTo('view_warning_any') && !allowedTo('issue_warning') && !allowedTo('moderate_forum'))
			fatal_lang_error('no_access', false);

		// Make sure things which are disabled stay disabled.
		$modSettings['warning_watch'] = !empty($modSettings['warning_watch']) ? $modSettings['warning_watch'] : 110;
		$modSettings['warning_moderate'] = !empty($modSettings['warning_moderate']) ? $modSettings['warning_moderate'] : 110;
		$modSettings['warning_mute'] = !empty($modSettings['warning_mute']) ? $modSettings['warning_mute'] : 110;

		// Let's use a generic list to get all the current warnings, and use the issue warnings grab-a-granny thing.
		require_once($sourcedir . '/Subs-List.php');

		$listOptions = [
			'id' => 'view_warnings',
			'title' => $txt['profile_viewwarning_previous_warnings'],
			'items_per_page' => $modSettings['defaultMaxListItems'],
			'no_items_label' => $txt['profile_viewwarning_no_warnings'],
			'base_href' => $scripturl . '?action=profile;area=view_warnings;u=' . $memID,
			'default_sort_col' => 'log_time',
			'get_items' => [
				'function' => ['StoryBB\\Helper\\Warning', 'list_getUserWarnings'],
				'params' => [
					$memID,
				],
			],
			'get_count' => [
				'function' => ['StoryBB\\Helper\\Warning', 'list_getUserWarningCount'],
				'params' => [
					$memID,
				],
			],
			'columns' => [
				'log_time' => [
					'header' => [
						'value' => $txt['profile_warning_previous_time'],
					],
					'data' => [
						'db' => 'time',
					],
					'sort' => [
						'default' => 'lc.log_time DESC',
						'reverse' => 'lc.log_time',
					],
				],
				'reason' => [
					'header' => [
						'value' => $txt['profile_warning_previous_reason'],
						'style' => 'width: 50%;',
					],
					'data' => [
						'db' => 'reason',
					],
				],
				'level' => [
					'header' => [
						'value' => $txt['profile_warning_previous_level'],
					],
					'data' => [
						'db' => 'counter',
					],
					'sort' => [
						'default' => 'lc.counter DESC',
						'reverse' => 'lc.counter',
					],
				],
			],
			'additional_rows' => [
				[
					'position' => 'after_title',
					'value' => $txt['profile_viewwarning_desc'],
					'class' => 'smalltext',
					'style' => 'padding: 2ex;',
				],
			],
		];

		// Create the list for viewing.
		require_once($sourcedir . '/Subs-List.php');
		createList($listOptions);

		// Create some common text bits for the template.
		$context['level_effects'] = [
			0 => '',
			$modSettings['warning_watch'] => $txt['profile_warning_effect_own_watched'],
			$modSettings['warning_moderate'] => $txt['profile_warning_effect_own_moderated'],
			$modSettings['warning_mute'] => $txt['profile_warning_effect_own_muted'],
		];
		$context['current_level'] = 0;
		foreach ($context['level_effects'] as $limit => $dummy)
			if ($context['member']['warning'] >= $limit)
				$context['current_level'] = $limit;

		$context['current_level_effects'] = $context['level_effects'][$context['current_level']];

		// Convert levels to classes
		$context['warning_classes'] = [
			0 => 'nothing',
			$modSettings['warning_watch'] => 'watch',
			$modSettings['warning_moderate'] => 'moderate',
			$modSettings['warning_mute'] => 'mute',
		];

		// Work out the starting color.
		$context['current_class'] = $context['warning_classes'][0];
		foreach ($context['warning_classes'] as $limit => $color)
			if ($context['member']['warning'] >= $limit)
				$context['current_class'] = $color;

		$context['sub_template'] = 'profile_warning_view';
	}
}
