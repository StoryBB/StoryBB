<?php

/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Search;

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Class search_api
 */
abstract class API implements API_Interface
{
	/**
	 * @var string The last version of SMF that this was tested on. Helps protect against API changes.
	 */
	public $version_compatible = 'StoryBB 3.0 Alpha 1';

	/**
	 * @var string The minimum SMF version that this will work with
	 */
	public $min_smf_version = 'StoryBB 3.0 Alpha 1';

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

?>