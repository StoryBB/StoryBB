<?php

/**
 * A recent posts block.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Block;

/**
 * The recent posts block.
 */
class RecentPosts extends AbstractBlock implements Block
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
		return $txt['recent_posts'];
	}

	public function get_default_title(): string
	{
		return 'txt.recent_posts';
	}

	public function get_block_content(): string
	{
		global $user_info, $txt;

		if ($this->content !== null)
		{
			return $this->content;
		}
		elseif ($this->content === null)
		{
			$this->content = '';
		}

		if (empty($this->config['number_recent_posts']))
		{
			return $this->content;
		}

		$latestPostOptions = [
			'number_posts' => $this->config['number_recent_posts'],
		];

		$latest_posts = cache_quick_get('block-recent_posts:' . $this->config['number_recent_posts'] . ':' . md5($user_info['query_wanna_see_board'] . $user_info['language']), null, [$this, 'cache_recent_posts'], [$latestPostOptions]);

		$this->content = $this->template('recent_posts', [
			'latest_posts' => $latest_posts,
		]);
		return $this->content;
	}

	public function cache_recent_posts($latestPostOptions): array
	{
		global $sourcedir;

		require_once($sourcedir . '/Subs-Recent.php');

		return [
			'data' => getLastPosts($latestPostOptions),
			'expires' => time() + 60,
			'post_retri_eval' => '
				foreach ($cache_block[\'data\'] as $k => $post)
				{
					$cache_block[\'data\'][$k][\'time\'] = timeformat($post[\'raw_timestamp\']);
					$cache_block[\'data\'][$k][\'timestamp\'] = forum_time(true, $post[\'raw_timestamp\']);
				}',
		];
	}
}
