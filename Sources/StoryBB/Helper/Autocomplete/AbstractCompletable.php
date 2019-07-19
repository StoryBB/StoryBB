<?php

/**
 * Any autocomplete handlers probably should extend this class.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Autocomplete;

/**
 * Any autocomplete handlers probably should extend this class.
 */
abstract class AbstractCompletable implements Completable
{
	/** @var string $term The search term to be matched to find completions for */
	protected $term = null;

	/** @var string $default Storage of the default value for pre-populated forms */
	protected $default = null;

	/**
	 * Set the term for an existing search to match against.
	 *
	 * @param string $term The raw search term to autocomplete for
	 */
	public function set_search_term(string $term)
	{
		$this->term = $term;
	}

	/**
	 * Escape the usual suspect terms that could come up in an autocomplete query.
	 *
	 * @param string $term Unsanitised term from user
	 * @return string Sanitised term ready for use with $smcFunc later
	 */
	protected function escape_term(string $term): string
	{
		global $smcFunc;
		$term = trim($smcFunc['strtolower']($term)) . '*';
		return strtr($term, [
			'%' => '\%',
			'_' => '\_',
			'*' => '%',
			'?' => '_',
			'&#038;' => '&amp;',
		]);
	}

	/**
	 * Whether the results will be paginated on return.
	 *
	 * @return bool True if can be paginated.
	 */
	public function can_paginate(): bool
	{
		return false;
	}

	/**
	 * Returns the number of results that match the search term.
	 *
	 * @return int Number of matching results
	 */
	public function get_count(): int
	{
		return 0;
	}

	/**
	 * Returns the actual results based on paginated through the filters search results.
	 *
	 * @param int $start Where to start through the results list
	 * @param int $limit How many to retrieve
	 * @return array Array of results matching the search term
	 */
	abstract public function get_results(int $start = null, int $limit = null): array;

	/**
	 * Sets existing values for populating an autocomplete when editing a form.
	 *
	 * @param array $default_value The default value as a series of ids for whichever lookup this is.
	 */
	abstract public function set_values(array $default_value);

	/**
	 * Provides the JavaScript to be embedded into the page to successfully initialise this widget.
	 *
	 * @param string $target The jQuery/JavaScript selector this should be applied to, e.g. #myselect
	 * @param int $maximum The expected maximum of allowed entries; 0 for no limit.
	 * @return string The JavaScript to initialise this widget.
	 */
	public function get_js(string $target, int $maximum = 1): string
	{
		return '';
	}

	/**
	 * Returns the name of the searchtype that would be used by the autocomplete route from the client.
	 * Typically this will match the name of the class but may need to be overridden in some cases.
	 *
	 * @return string Autocomplete type for index.php?action=autocomplete;type=abstractcompletable
	 */
	public function get_searchtype(): string
	{
		return strtolower(substr(static::class, strlen(__NAMESPACE__) + 1));
	}
}
