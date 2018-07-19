<?php

/**
 * Any autocomplete handlers must implement this interface.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Helper\Autocomplete;

interface Completable
{
	public function set_search_term(string $term);

	public function can_paginate(): bool;

	public function get_count(): int;

	public function get_results(int $start = null, int $limit = null): array;

	public function get_js(): string;
}
