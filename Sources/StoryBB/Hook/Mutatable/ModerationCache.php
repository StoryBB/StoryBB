<?php

/**
 * This hook runs when the moderation cache is rebuilt.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 * @deprecated This will eventually be replaced by the revised controller/routing setup.
 */

namespace StoryBB\Hook\Mutatable;

/**
 * This hook runs when the moderation cache is rebuilt.
 */
class ModerationCache extends \StoryBB\Hook\Mutatable
{
	protected $vars = [];

	public function __construct(array &$modcache)
	{
		$this->vars = [
			'modcache' => &$modcache,
		];
	}
}
