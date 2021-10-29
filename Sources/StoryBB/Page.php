<?php

/**
 * A class for managing the page that we're going to be returning.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB;

use StoryBB\Dependency\UrlGenerator;

class Page
{
	use UrlGenerator;

	protected $meta = [
		'name' => [],
		'property' => [],
	];
	protected $link = [];
	protected $linktree = [];

	public function addMetaName(string $name, string $content): void
	{
		$this->meta['name'][$name] = $content;
	}

	public function removeMetaName(string $name): void
	{
		unset($this->meta['name'][$name]);
	}

	public function getMetaNames(): array
	{
		return $this->meta['name'];
	}

	public function addMetaProperty(string $property, string $content): void
	{
		$this->meta['property'][$property] = $content;
	}

	public function removeMetaProperty(string $property)
	{
		unset($this->meta['property'][$property]);
	}

	public function getMetaProperties(): array
	{
		return $this->meta['property'];
	}

	public function excludeRobots(bool $state = true): void
	{
		if ($state)
		{
			$this->addMetaName('robots', 'noindex');
		}
		else
		{
			$this->removeMetaName('robots');
		}
	}

	public function addLinktree(string $name, string $url): void
	{
		$this->linktree[] = ['name' => $name] + ($url ? ['url' => $url] : []);
	}

	public function getLinktree(): array
	{
		return $this->linktree;
	}

	public function addLink(string $rel, string $href, bool $replace = false): void
	{
		if ($replace)
		{
			$this->link[$rel] = [$href];
		}
		else
		{
			$this->link[$rel][] = $href;
		}
	}

	public function getLink(): array
	{
		$links = [];
		foreach ($this->link as $rel => $hrefs)
		{
			foreach ($hrefs as $href)
			{
				$links[] = (object) ['rel' => $rel, 'href' => $href];
			}
		}
		return $links;
	}

	public function setCanonical($url): void
	{
		$this->addLink('canonical', $url, true);
	}

	public function addSCSSfile(int $theme_id, string $scssfile, int $timestamp)
	{
		$urlgenerator = $this->urlgenerator();
		$options = [
			'theme' => $theme_id,
			'scssfile' => $scssfile,
			'timestamp' => $timestamp,
		];
		$this->addLink('stylesheet', $urlgenerator->generate('css', $options));
	}
}
