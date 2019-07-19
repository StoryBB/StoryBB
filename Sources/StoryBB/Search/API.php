<?php

/**
 * Generic search class, all search backends should extend this class.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Search;

/**
 * The generic search class has a number of functions, most backends should override most of these.
 */
abstract class API implements API_Interface
{
	/**
	 * @var string The last version of StoryBB that this was tested on. Helps protect against API changes.
	 */
	public $version_compatible = 'StoryBB 3.0 Alpha 1';

	/**
	 * @var string The minimum StoryBB version that this will work with
	 */
	public $min_sbb_version = 'StoryBB 3.0 Alpha 1';

	/**
	 * @var bool Whether or not it's supported
	 */
	public $is_supported = true;

	/**
	 * {@inheritDoc}
	 */
	public function isValid()
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function searchSort($a, $b)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepareIndexes($word, array &$wordsSearch, array &$wordsExclude, $isExcluded)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function indexedWordQuery(array $words, array $search_data)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function postCreated(array &$msgOptions, array &$topicOptions, array &$posterOptions)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function postModified(array &$msgOptions, array &$topicOptions, array &$posterOptions)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function postRemoved($id_msg)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function topicsRemoved(array $topics)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function topicsMoved(array $topics, $board_to)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function searchQuery(array $query_params, array $searchWords, array $excludedIndexWords, array &$participants, array &$searchArray)
	{
	}
}
