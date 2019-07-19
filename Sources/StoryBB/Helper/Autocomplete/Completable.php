<?php

/**
 * Any autocomplete handlers must implement this interface.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Autocomplete;

/**
 * Any autocomplete handlers must implement this interface.
 */
interface Completable
{
	/**
	 * Set the term for an existing search to match against.
	 *
	 * @param string $term The raw search term to autocomplete for
	 */
	public function set_search_term(string $term);

	/**
	 * Whether the results will be paginated on return.
	 *
	 * @return bool True if can be paginated.
	 */
	public function can_paginate(): bool;

	/**
	 * Returns the number of results that match the search term.
	 *
	 * @return int Number of matching results
	 */
	public function get_count(): int;

	/**
	 * Returns the actual results based on paginated through the filters search results.
	 *
	 * @param int $start Where to start through the results list
	 * @param int $limit How many to retrieve
	 * @return array Array of results matching the search term
	 */
	public function get_results(int $start = null, int $limit = null): array;

	/**
	 * Sets existing values for populating an autocomplete when editing a form.
	 *
	 * @param array $default_value The default value as a series of ids for whichever lookup this is.
	 */
	public function set_values(array $default_value);

	/**
	 * Provides the JavaScript to be embedded into the page to successfully initialise this widget.
	 *
	 * @param string $target The jQuery/JavaScript selector this should be applied to, e.g. #myselect
	 * @param int $maximum The expected maximum of allowed entries; 0 for no limit.
	 * @return string The JavaScript to initialise this widget.
	 */
	public function get_js(string $target, int $maximum = 1): string;
}
