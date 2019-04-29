<?php

/**
 * Provide an autocomplete handler to match membergroups specifically non-post-count groups.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Helper\Autocomplete;

/**
 * Provide an autocomplete handler to match membergroups specifically non-post-count groups.
 */
class NonPostGroup extends Group implements Completable
{
	/** @var bool $post_count_groups Sets out whether post count groups should be included as possible group matches */
	protected $post_count_groups = false;
}
