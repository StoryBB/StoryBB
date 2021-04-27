<?php

/**
 * This interface represents some kind of feed.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Feed;

use StoryBB\Discoverable;

/**
 * This interface represents some kind of feed.
 */
interface Feedable extends Discoverable
{
	/**
	 * Constructor.
	 *
	 * @param array $feed Accepts whatever feed generic details the caller wants to pass.
	 */
	public function __construct(array $feed, int $limit);

	/**
	 * Returns an identifier the feed would normally expect to be referenced by in the URL.
	 *
	 * @return string Shortname/identifier for this feed.
	 */
	public static function get_identifier(): string;

	/**
	 * Gets the data for this feed in a generic format.
	 *
	 * @return array The feed data.
	 */
	public function get_data(): array;

	/**
	 * Gets a list of variables that are relevant to building this feed's URL.
	 *
	 * @return array An array of keys in $_GET.
	 */
	public function get_vars(): array;
}
