<?php

/**
 * Abstract profile controller (hybrid style)
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Helper\Navigation\Navigation;

abstract class AbstractProfileController
{
	protected $navigation;
	protected $params;

	public function __construct(Navigation $nav, array $params)
	{
		$this->navigation = $nav;
		$this->params = $params;
	}

	abstract public function display_action();

	public function use_generic_save_message()
	{
		global $context, $txt, $cur_profile;

		$memID = $this->params['u'];

		// Let them know it worked!
		session_flash('success', $context['user']['is_owner'] ? $txt['profile_updated_own'] : sprintf($txt['profile_updated_else'], $cur_profile['member_name']));

		// Invalidate any cached data.
		cache_put_data('member_data-profile-' . $memID, null, 0);
	}
}
