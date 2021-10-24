<?php

/**
 * Return the value absolutely unfiltered for a generic list column.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

use StoryBB\App;
use StoryBB\Dependency\CurrentUser;
use StoryBB\Dependency\SiteSettings;
use StoryBB\Phrase;

class Formatter
{
	use CurrentUser;
	use SiteSettings;

	public function avatar(array $data): array
	{
		$site_settings = $this->sitesettings();
		$boardurl = App::get_global_config_item('boardurl');
		$image_proxy_enabled = App::get_global_config_item('image_proxy_enabled');
		$image_proxy_secret = App::get_global_config_item('image_proxy_secret');

		$images_url = App::container()->get('current_theme')->images_url;

		// Set a nice default var.
		$image = '';

		// So it's stored in the member table?
		if (!empty($data['avatar']))
		{
			// Using ssl?
			if ($site_settings->force_ssl && $image_proxy_enabled && stripos($data['avatar'], 'http://') !== false)
				$image = strtr($boardurl, ['http://' => 'https://']) . '/proxy.php?request=' . urlencode($data['avatar']) . '&hash=' . md5($data['avatar'] . $image_proxy_secret);

			// Just a plain external url.
			else
				$image = (stristr($data['avatar'], 'http://') || stristr($data['avatar'], 'https://')) ? $data['avatar'] : '';
		}

		// Perhaps this user has an attachment as avatar...
		elseif (!empty($data['filename']))
			$image = $site_settings->custom_avatar_url . '/' . $data['filename'];

		// Right... no avatar... use our default image.
		else
			$image = $images_url . '/default.png';

		// At this point in time $image has to be filled... thus a check for !empty() is still needed.
		if (!empty($image))
		{

			if (!empty($data['display_name']))
			{
				$display_name = new Phrase('General:avatar_of', [$data['display_name']]);
			}
			elseif (!empty($data['is_guest']))
			{
				$display_name = new Phrase('General:guest');
			}
			return [
				'name' => !empty($data['avatar']) ? $data['avatar'] : '',
				'image' => '<img class="avatar" src="' . $image . '"' . (!empty($display_name) ? ' alt="' . $display_name . '"' : '') . ' />',
				'href' => $image,
				'url' => $image,
			];
		}
		// Fallback to make life easier for everyone...
		else
			return [
				'name' => '',
				'image' => '',
				'href' => '',
				'url' => '',
			];
	}
}
