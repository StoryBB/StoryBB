<?php

/**
 * A high-level stats block.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Block;

/**
 * A high-level stats block.
 */
class MiniStats extends AbstractBlock implements Block
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
		return $txt['forum_stats'];
	}

	public function get_default_title(): string
	{
		return 'txt.forum_stats';
	}

	public function get_block_content(): string
	{
		global $context, $txt, $sourcedir;

		if ($this->content !== null)
		{
			return $this->content;
		}
		elseif ($this->content === null)
		{
			$this->content = '';
		}

		// The last post may already have been loaded, so let's see if we can use it.
		$latest_post = false;
		if (!empty($context['latest_post']))
		{
			$latest_post = [
				'time' => $context['latest_post']['time'],
				'link' => $context['latest_post']['link'],
				'poster' => $context['latest_post']['member'],
			];
		}
		else
		{
			require_once($sourcedir . '/Subs-Recent.php');
			$post = getLastPosts(['number_posts' => 1]);
			if (!empty($post[0]))
			{
				$latest_post = [
					'time' => $post[0]['time'],
					'link' => $post[0]['link'],
					'poster' => $post[0]['poster'],
				];
			}
		}

		$this->content = $this->render('block_mini_stats', [
			'boardindex_total_posts' => $context['common_stats']['boardindex_total_posts'],
			'show_latest_member' => !empty($this->config['show_latest_member']),
			'latest_member' => $context['common_stats']['latest_member'],
			'latest_post' => !empty($latest_post) ? $latest_post : false,
			'txt' => $txt,
		]);
		return $this->content;
	}
}
