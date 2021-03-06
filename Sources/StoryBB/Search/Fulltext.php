<?php

/**
 * Provides searching using full text indexes on the database
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Search;

use StoryBB\StringLibrary;

/**
 * Class fulltext_search
 * Used for fulltext index searching
 */
class Fulltext extends AbstractSearchableOld implements SearchableOld
{
	/**
	 * @var array Which words are banned
	 */
	protected $bannedWords = [];

	/**
	 * @var int The minimum word length
	 */
	protected $min_word_length = 4;

	/**
	 * @var array Which databases support this method?
	 */
	protected $supported_databases = ['mysql'];

	/**
	 * The constructor function
	 */
	public function __construct()
	{
		global $modSettings, $db_type;

		// Is this database supported?
		if (!in_array($db_type, $this->supported_databases))
		{
			$this->is_supported = false;
			return;
		}

		$this->bannedWords = empty($modSettings['search_banned_words']) ? [] : explode(',', $modSettings['search_banned_words']);
		$this->min_word_length = $this->_getMinWordLength();
	}

	/**
	 * Whether this method is valid for implementation or not
	 *
	 * @return bool Whether or not this method is valid
	 */
	public function isValid()
	{
		return true; // @todo?
	}

	/**
	 * Returns the name of the search index.
	 */
	public function getName(): string
	{
		global $txt;
		loadLanguage('Search');
		return $txt['search_method_fulltext_index'];
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
		switch ($methodName)
		{
			case 'searchSort':
			case 'prepareIndexes':
			case 'indexedWordQuery':
				return true;
			break;

			// All other methods, too bad dunno you.
			default:
				return false;
			break;
		}
	}

	/**
	 * What is the minimum word length full text supports?
	 *
	 * @return int The minimum word length
	 */
	protected function _getMinWordLength()
	{
		global $smcFunc;

		// Try to determine the minimum number of letters for a fulltext search.
		$request = $smcFunc['db']->query('max_fulltext_length', '
			SHOW VARIABLES
			LIKE {string:fulltext_minimum_word_length}',
			[
				'fulltext_minimum_word_length' => 'ft_min_word_len',
			]
		);
		if ($request !== false && $smcFunc['db']->num_rows($request) == 1)
		{
			list (, $min_word_length) = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);
		}
		// 4 is the MySQL default...
		else
			$min_word_length = 4;

		return $min_word_length;
	}

	/**
	 * Callback function for usort used to sort the fulltext results.
	 * the order of sorting is: large words, small words, large words that
	 * are excluded from the search, small words that are excluded.
	 *
	 * @param string $a Word A
	 * @param string $b Word B
	 * @return int An integer indicating how the words should be sorted
	 */
	public function searchSort($a, $b)
	{
		global $excludedWords;

		$x = StringLibrary::strlen($a) - (in_array($a, $excludedWords) ? 1000 : 0);
		$y = StringLibrary::strlen($b) - (in_array($b, $excludedWords) ? 1000 : 0);

		return $x < $y ? 1 : ($x > $y ? -1 : 0);
	}

	/**
	 * Callback while preparing indexes for searching
	 *
	 * @param string $word A word to index
	 * @param array $wordsSearch Search words
	 * @param array $wordsExclude Words to exclude
	 * @param bool $isExcluded Whether the specfied word should be excluded
	 */
	public function prepareIndexes($word, array &$wordsSearch, array &$wordsExclude, $isExcluded)
	{
		global $modSettings;

		$subwords = text2words($word, null, false);

		if (empty($modSettings['search_force_index']))
		{
			// A boolean capable search engine and not forced to only use an index, we may use a non indexed search
			// this is harder on the server so we are restrictive here
			if (count($subwords) > 1 && preg_match('~[.:@$]~', $word))
			{
				// using special characters that a full index would ignore and the remaining words are short which would also be ignored
				if ((StringLibrary::strlen(current($subwords)) < $this->min_word_length) && (StringLibrary::strlen(next($subwords)) < $this->min_word_length))
				{
					$wordsSearch['words'][] = trim($word, "/*- ");
					$wordsSearch['complex_words'][] = count($subwords) === 1 ? $word : '"' . $word . '"';
				}
			}
			elseif (StringLibrary::strlen(trim($word, "/*- ")) < $this->min_word_length)
			{
				// short words have feelings too
				$wordsSearch['words'][] = trim($word, "/*- ");
				$wordsSearch['complex_words'][] = count($subwords) === 1 ? $word : '"' . $word . '"';
			}
		}

		$fulltextWord = count($subwords) === 1 ? $word : '"' . $word . '"';
		$wordsSearch['indexed_words'][] = $fulltextWord;
		if ($isExcluded)
			$wordsExclude[] = $fulltextWord;
	}

	/**
	 * Search for indexed words.
	 *
	 * @param array $words An array of words
	 * @param array $search_data An array of search data
	 * @return mixed
	 */
	public function indexedWordQuery(array $words, array $search_data)
	{
		global $modSettings, $smcFunc;

		$query_select = [
			'id_msg' => 'm.id_msg',
		];
		$query_where = [];
		$query_params = $search_data['params'];

		if ($query_params['id_search'])
			$query_select['id_search'] = '{int:id_search}';

		$count = 0;
		if (empty($modSettings['search_simple_fulltext']))
			foreach ($words['words'] as $regularWord)
			{
				$query_where[] = 'm.body' . (in_array($regularWord, $query_params['excluded_words']) ? ' NOT' : '') . (empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? ' LIKE ' : 'RLIKE') . '{string:complex_body_' . $count . '}';
				$query_params['complex_body_' . $count++] = empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? '%' . strtr($regularWord, ['_' => '\\_', '%' => '\\%']) . '%' : '[[:<:]]' . addcslashes(preg_replace(['/([\[\]$.+*?|{}()])/'], ['[$1]'], $regularWord), '\\\'') . '[[:>:]]';
			}

		if ($query_params['user_query'])
			$query_where[] = '{raw:user_query}';
		if ($query_params['board_query'])
			$query_where[] = 'm.id_board {raw:board_query}';

		if ($query_params['topic'])
			$query_where[] = 'm.id_topic = {int:topic}';
		if ($query_params['min_msg_id'])
			$query_where[] = 'm.id_msg >= {int:min_msg_id}';
		if ($query_params['max_msg_id'])
			$query_where[] = 'm.id_msg <= {int:max_msg_id}';

		$count = 0;
		if (!empty($query_params['excluded_phrases']) && empty($modSettings['search_force_index']))
			foreach ($query_params['excluded_phrases'] as $phrase)
			{
				$query_where[] = 'subject NOT ' . (empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? ' LIKE ' : 'RLIKE') . '{string:exclude_subject_phrase_' . $count . '}';
				$query_params['exclude_subject_phrase_' . $count++] = empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? '%' . strtr($phrase, ['_' => '\\_', '%' => '\\%']) . '%' : '[[:<:]]' . addcslashes(preg_replace(['/([\[\]$.+*?|{}()])/'], ['[$1]'], $phrase), '\\\'') . '[[:>:]]';
			}
		$count = 0;
		if (!empty($query_params['excluded_subject_words']) && empty($modSettings['search_force_index']))
			foreach ($query_params['excluded_subject_words'] as $excludedWord)
			{
				$query_where[] = 'subject NOT ' . (empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? ' LIKE ' : 'RLIKE') . '{string:exclude_subject_words_' . $count . '}';
				$query_params['exclude_subject_words_' . $count++] = empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? '%' . strtr($excludedWord, ['_' => '\\_', '%' => '\\%']) . '%' : '[[:<:]]' . addcslashes(preg_replace(['/([\[\]$.+*?|{}()])/'], ['[$1]'], $excludedWord), '\\\'') . '[[:>:]]';
			}

		if (!empty($modSettings['search_simple_fulltext']))
		{
			$query_where[] = 'MATCH (body) AGAINST ({string:body_match})';
			$query_params['body_match'] = implode(' ', array_diff($words['indexed_words'], $query_params['excluded_index_words']));
		}
		else
		{
			$query_params['boolean_match'] = '';

			// remove any indexed words that are used in the complex body search terms
			$words['indexed_words'] = array_diff($words['indexed_words'], $words['complex_words']);

			foreach ($words['indexed_words'] as $fulltextWord)
				$query_params['boolean_match'] .= (in_array($fulltextWord, $query_params['excluded_index_words']) ? '-' : '+') . $fulltextWord . ' ';

			$query_params['boolean_match'] = substr($query_params['boolean_match'], 0, -1);

			// if we have bool terms to search, add them in
			if ($query_params['boolean_match']) {
				$query_where[] = 'MATCH (body) AGAINST ({string:boolean_match} IN BOOLEAN MODE)';
			}

		}

		$ignoreRequest = $smcFunc['db']->query('insert_into_log_messages_fulltext', ($smcFunc['db']->support_ignore() ? ( '
			INSERT IGNORE INTO {db_prefix}' . $search_data['insert_into'] . '
				(' . implode(', ', array_keys($query_select)) . ')') : '') . '
			SELECT ' . implode(', ', $query_select) . '
			FROM {db_prefix}messages AS m
			WHERE ' . implode('
				AND ', $query_where) . (empty($search_data['max_results']) ? '' : '
			LIMIT ' . ($search_data['max_results'] - $search_data['indexed_results'])),
			$query_params
		);

		return $ignoreRequest;
	}

	/**
	 * Callback when a post is created
	 * @see createPost()
	 *
	 * @todo fix
	 * @param array $msgOptions An array of post data
	 * @param array $topicOptions An array of topic data
	 * @param array $posterOptions An array of info about the person who made this post
	 * @return void
	 */
	public function postCreated(array &$msgOptions, array &$topicOptions, array &$posterOptions)
	{
		return;
	}

	/**
	 * Callback when a post is modified
	 * @see modifyPost()
	 *
	 * @todo fix
	 * @param array $msgOptions An array of post data
	 * @param array $topicOptions An array of topic data
	 * @param array $posterOptions An array of info about the person who made this post
	 * @return void
	 */
	public function postModified(array &$msgOptions, array &$topicOptions, array &$posterOptions)
	{
		return;
	}

	/**
	 * Callback when a post is removed
	 *
	 * @todo fix
	 * @param int $id_msg The ID of the post that was removed
	 * @return void
	 */
	public function postRemoved($id_msg)
	{
		return;
	}

	/**
	 * Callback when a topic is removed
	 *
	 * @todo fix
	 * @param array $topics The ID(s) of the removed topic(s)
	 * @return void
	 */
	public function topicsRemoved(array $topics)
	{
		return;
	}

	/**
	 * Callback when a topic is moved
	 *
	 * @todo fix
	 * @param array $topics The ID(s) of the moved topic(s)
	 * @param int $board_to The board that the topics were moved to
	 * @return void
	 */
	public function topicsMoved(array $topics, $board_to)
	{
		return;
	}

	/**
	 * Callback for actually performing the search query
	 *
	 * @todo fix
	 * @param array $query_params An array of parameters for the query
	 * @param array $searchWords The words that were searched for
	 * @param array $excludedIndexWords Indexed words that should be excluded
	 * @param array $participants
	 * @param array $searchArray
	 * @return mixed
	 */
	public function searchQuery(array $query_params, array $searchWords, array $excludedIndexWords, array &$participants, array &$searchArray)
	{
		return '';
	}
}
