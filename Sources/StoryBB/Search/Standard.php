<?php

/**
 * Provides basic search functionality to StoryBB
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Search;

/**
 * Standard non full index, non custom index search
 */
class Standard extends AbstractSearchable implements Searchable
{
	/**
	 * Returns the name of the search index.
	 */
	public function getName(): string
	{
		global $txt;
		loadLanguage('Search');
		return $txt['search_index_none'];
	}

	/**
	 * Check whether the specific search operation can be performed by this API.
	 * The operations are the functions listed in the interface, if not supported
	 * they need not be declared
	 *
	 * @param string $methodName The method
	 * @param array $query_params Any parameters for the query
	 * @return boolean Whether or not the specified method is supported
	 */
	public function supportsMethod($methodName, $query_params = null)
	{
		// Always fall back to the standard search method.
		return false;
	}

	/**
	 * Whether this method is valid for implementation or not
	 *
	 * @return bool Whether or not this method is valid
	 */
	public function isValid()
	{
		return true;
	}
}
