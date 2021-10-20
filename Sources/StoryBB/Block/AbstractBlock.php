<?php

/**
 * An abstract helper block.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Block;

use StoryBB\Container;
use StoryBB\Template;

/**
 * An abstract helper block.
 */
abstract class AbstractBlock
{
	protected $config;

	public function get_configuration(): array
	{
		return $this->config;
	}

	protected function render(string $partialname, array $blockcontext): string
	{
		$template = Template::load_partial($partialname);
		$phpStr = Template::compile($template, [], $partialname . Template::get_theme_id('partials', $partialname));
		return Template::prepare($phpStr, $blockcontext);
	}

	protected function template(string $partialname, array $blockcontext): string
	{
		$container = Container::instance();
		$twig = $container->get('templaterenderer');
		$template = $twig->load('@partials/blocks/' . $partialname . '.twig');
		return $template->render($blockcontext);
	}

	public function get_block_title(): string
	{
		global $txt;

		$title = !empty($this->config['title']) ? $this->config['title'] : $this->get_default_title();

		if (preg_match('/([a-z0-9_]+\/)?txt\.([a-z0-9_]+)/i', $title, $match))
		{
			if (!empty($match[1]))
			{
				$match[1] = rtrim($match[1], '/');
				loadLanguage($match[1]);
			}
			if (isset($match[2]) && isset($txt[$match[2]]))
			{
				return $txt[$match[2]];
			}
		}

		return $title;
	}

	public function get_render_template(): string
	{
		return 'block__catbg';
	}

	public function get_blocktype(): string
	{
		$classname = get_class($this);
		return strtolower(substr(strrchr($classname, '\\'), 1));
	}
}
