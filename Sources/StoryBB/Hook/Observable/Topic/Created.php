<?php

/**
 * This hook runs when a topic is created.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook\Observable\Topic;

/**
 * This hook runs when a topic is created.
 */
class Created extends \StoryBB\Hook\Observable
{
	protected $vars = [];

	public function __construct($msgOptions, $topicOptions, $posterOptions)
	{
		$this->vars = [
			'msgOptions' => $msgOptions,
			'topicOptions' => $topicOptions,
			'posterOptions' => $posterOptions,
		];
	}
}
