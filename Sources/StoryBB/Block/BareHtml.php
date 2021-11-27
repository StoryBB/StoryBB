<?php

/**
 * A list of links block.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Block;

/**
 * The recent online block.
 */
class BareHtml extends AbstractBlock implements Block
{
	protected $config;
	protected $content;

	public function __construct($config = [])
	{
		$this->config = $config;
	}

	public function get_name(): string
	{
		return '';
	}

	public function get_default_title(): string
	{
		return '';
	}

	public function get_block_content(): string
	{
		global $txt, $scripturl, $modSettings;

		if ($this->content !== null)
		{
			return $this->content;
		}
		elseif ($this->content === null)
		{
			$this->content = '';
		}

		$this->content = $this->config['content'] ?? '';

		return $this->content;
	}
}
