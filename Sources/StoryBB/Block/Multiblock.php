<?php

/**
 * A multiple-block block.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Block;

use StoryBB\Template;

/**
 * A multiple-block block.
 */
class Multiblock extends AbstractBlock implements Block
{
	static $instancecount = 1;
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

		if (empty($this->config['blocks']))
		{
			return;
		}

		$subblock_template = !empty($this->config['subblock_template']) ? $this->config['subblock_template'] : 'block__subbg';

		$this->content = '';
		foreach ($this->config['blocks'] as $block)
		{
			$block_config = !empty($block['config']) ? $block['config'] : [];

			if (!class_exists($block['class']))
			{
				continue;
			}

			$instance = new $block['class']($block_config);

			$partial = Template::load_partial($subblock_template);
			$compiled = Template::compile($partial, [], $subblock_template . Template::get_theme_id('partials', $subblock_template));

			$this->content .= Template::prepare($compiled, [
				'instance' => 'multiblock' . static::$instancecount++,
				'title' => new \LightnCandy\SafeString($instance->get_block_title()),
				'content' => new \LightnCandy\SafeString($instance->get_block_content()),
			]);
		}

		return $this->content;
	}

	public function get_render_template(): string
	{
		return 'block__roundframe_titlebg';
	}
}
