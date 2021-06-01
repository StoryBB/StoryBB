<?php

/**
 * Displays the profile popup page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Template;

class ProfilePopup extends AbstractProfileController
{
	public function display_action()
	{
		global $context, $scripturl, $txt, $db_show_debug;

		// We do not want to output debug information here.
		$db_show_debug = false;

		// We only want to output our little layer here.
		Template::set_layout('raw');
		Template::remove_all_layers();

		$context['sub_template'] = 'profile_popup';

		// This list will pull from the master list wherever possible. Hopefully it should be clear what does what.
		$profile_items = [
			[
				'item' => 'info_summary',
				'title' => $txt['popup_summary'],
				'icon' => 'administration',
			],
			[
				'item' => 'account_settings',
				'icon' => 'maintain',
			],
			[
				'item' => 'bookmarks',
				'title' => $txt['popup_bookmarks'],
				'icon' => 'bookmark',
			],
			[
				'item' => 'show_drafts',
				'title' => $txt['popup_drafts'],
				'icon' => 'modify_button',
			],
			[
				'item' => 'posts',
				'title' => $txt['popup_showposts'],
				'icon' => 'posts',
			],
			[
				'item' => 'forum_profile',
				'title' => $txt['forumprofile'],
				'icon' => 'members',
			],
			[
				'item' => 'alert_preferences',
				'icon' => 'mail',
			],
			[
				'item' => 'preferences',
				'icon' => 'features',
			],
			[
				'item' => 'ignored_boards',
				'icon' => 'boards',
			],
			[
				'item' => 'ignored_people',
				'title' => $txt['popup_ignore'],
				'icon' => 'frenemy',
			],
			[
				'item' => 'group_membership',
				'icon' => 'people',
			],
			[
				'item' => 'paid_subscriptions',
				'icon' => 'paid',
			],
		];

		//call_integration_hook('integrate_profile_popup', [&$profile_items]);

		// Now check if these items are available
		$context['profile_items'] = [];
		foreach ($profile_items as $menu_item)
		{
			if (($item = $this->navigation->find_item_by_id($menu_item['item'])) && $item->is_visible())
			{
				$context['profile_items'][] = [
					'icon' => $menu_item['icon'] ?? '',
					'url' => $item->get_url(['action' => 'profile']),
					'title' => $menu_item['title'] ?? $item->label,
				];
			}
		}
	}
}
