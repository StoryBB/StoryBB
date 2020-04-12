<?php

/**
 * A class for managing the page that we're going to be returning.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB;

class Page
{
	protected $meta = [
		'name' => [],
		'property' => [],
	];
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
}