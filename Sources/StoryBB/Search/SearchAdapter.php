<?php

/**
 * Defines the methods required to be implemented by a search backend.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Search;

use StoryBB\Discoverable;

/**
 * These methods should all be implemented for a search backend to successfully implement content searching.
 */
interface SearchAdapter extends Discoverable
{
	public function add_content(Indexable $indexable): bool;

	public function update_content(Indexable $indexable, bool $upsert = true): bool;

	public function delete_content(Indexable $indexable): bool;
}
