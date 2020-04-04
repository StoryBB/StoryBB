<?php

/**
 * A block for displaying news from StoryBB.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Block;

class StoryBBNews extends AbstractBlock implements Block
{
	protected $config;
	protected $content;

	public function __construct($config = [])
	{
		$this->config = $config;
	}

	public function get_name(): string
	{
		global $txt;
		return $txt['live'];
	}

	public function get_default_title(): string
	{
		return 'Admin/txt.live';
	}

	public function get_block_content(): string
	{
		global $txt, $scripturl, $sourcedir, $user_info;

		require_once($sourcedir . '/Subs-Admin.php');
		loadLanguage('Admin');

		if ($this->content !== null)
		{
			return $this->content;
		}
		elseif ($this->content === null)
		{
			$this->content = '';
		}

		$announcements = [];
		$admin_news = getAdminFile('updates.json');
		if (isset($admin_news['sbbAnnouncements'][$user_info['language']]))
		{
			$announcements = $admin_news['sbbAnnouncements'][$user_info['language']];
		}
		elseif (isset($admin_news['sbbAnnouncements']['en']))
		{
			$announcements = $admin_news['sbbAnnouncements']['en'];
		}

		$this->content = $this->render('block_storybb_news', [
			'txt' => $txt,
			'announcements' => $announcements,
		]);

		return $this->content;
	}
}
