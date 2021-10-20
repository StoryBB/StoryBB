<?php

/**
 * A multiple-block block.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Block;

use StoryBB\App;
use StoryBB\Template;
use StoryBB\Dependency\TemplateRenderer;

/**
 * A multiple-block block.
 */
class Multiblock extends AbstractBlock implements Block
{
	use TemplateRenderer;

	protected static $instancecount = 1;
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
			return '';
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

			$instance = App::make($block['class'], $block_config);

			$partial = '@partials/block_containers/' . $subblock_template . '.twig';

			$this->content .= $this->templaterenderer()->render($partial, [
				'instance' => 'multiblock' . static::$instancecount++,
				'title' => $instance->get_block_title(),
				'content' => $instance->get_block_content(),
				'blocktype' => $instance->get_blocktype(),
				'icon' => !empty($block_config['icon']) ? $block_config['icon'] : '',
				'fa_icon' => !empty($block_config['fa-icon']) ? $block_config['fa-icon'] : '',
				'collapsible' => false,
				'collapsed' => false,
			]);
		}

		return $this->content;
	}

	public function get_render_template(): string
	{
		return !empty($this->config['template']) ? $this->config['template'] : 'block__catbg';
	}
}
