<?php

/**
 * Feed handler for recent posts.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Feed;

use StoryBB\Helper\Parser;
use StoryBB\StringLibrary;
use DateTime;
use DateTimeInterface;

/**
 * Feed for recent posts.
 */
class RecentPosts implements Feedable
{
	use CategoryBoardFilter;

	/** @var array $feed Storage of the feed's details. */
	protected $feed = [];

	/** @var int $limit The max number of items in the field. */
	protected $limit = 5;

	/**
	 * Constructor.
	 *
	 * @param array $feed Accepts whatever feed generic details the caller wants to pass.
	 */
	public function __construct(array $feed, int $limit)
	{
		$this->feed = $feed;
		$this->limit = $limit;
	}

	/**
	 * Returns an identifier the feed would normally expect to be referenced by in the URL.
	 *
	 * @return string Shortname/identifier for this feed.
	 */
	public static function get_identifier(): string
	{
		return 'recent';
	}

	/**
	 * Gets the data for this feed in a generic format.
	 *
	 * @return array The feed data.
	 */
	public function get_data(): array
	{
		global $scripturl, $modSettings, $board, $user_info;
		global $smcFunc, $context, $txt;

		/* Find the latest posts that:
			- are the first post in their topic.
			- are on an any board OR in a specified board.
			- can be seen by this user.
			- are actually the latest posts. */

		$this->get_category_filter();

		$max_timestamp = 0;

		$done = false;
		$loops = 0;
		while (!$done)
		{
			$optimize_msg = implode(' AND ', $this->optimize_msg);
			$request = $smcFunc['db']->query('', '
				SELECT m.id_msg
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				WHERE ' . $this->query_this_board . (empty($optimize_msg) ? '' : '
					AND {raw:optimize_msg}') . (empty($board) ? '' : '
					AND m.id_board = {int:current_board}') . '
					AND m.approved = {int:is_approved}
				ORDER BY m.id_msg DESC
				LIMIT {int:limit}',
				[
					'limit' => $this->limit,
					'current_board' => $board,
					'is_approved' => 1,
					'optimize_msg' => $optimize_msg,
				]
			);
			// If we don't have $_GET['limit'] results, try again with an unoptimized version covering all rows.
			if ($loops < 2 && $smcFunc['db']->num_rows($request) < $_GET['limit'])
			{
				$smcFunc['db']->free_result($request);
				if (empty($_REQUEST['boards']) && empty($board))
				{
					unset($this->optimize_msg['lowest']);
				}
				else
				{
					$this->optimize_msg['lowest'] = $loops ? 'm.id_msg >= t.id_first_msg' : 'm.id_msg >= (t.id_last_msg - t.id_first_msg) / 2';
				}
				$loops++;
			}
			else
				$done = true;
		}
		$messages = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$messages[] = $row['id_msg'];
		}
		$smcFunc['db']->free_result($request);

		if (empty($messages))
		{
			return $this->feed;
		}

		// Find the most recent posts this user can see.
		$request = $smcFunc['db']->query('', '
			SELECT
				m.smileys_enabled, m.poster_time, m.id_msg, m.subject, m.body, m.id_topic, t.id_board,
				b.name AS bname, t.num_replies, m.id_member, m.modified_time,
				COALESCE(mem.id_member, 0) AS id_member,
				COALESCE(chars.id_character, 0) AS id_character,
				COALESCE(chars.is_main, 0) AS is_main,
				COALESCE(mem.email_address, m.poster_email) AS poster_email,
				COALESCE(chars.character_name, mem.real_name, m.poster_name) AS poster_name
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				LEFT JOIN {db_prefix}characters AS chars ON (chars.id_character = m.id_character)
			WHERE m.id_msg IN ({array_int:message_list})
				' . (empty($board) ? '' : 'AND t.id_board = {int:current_board}') . '
			ORDER BY m.id_msg DESC
			LIMIT {int:limit}',
			[
				'limit' => $this->limit,
				'current_board' => $board,
				'message_list' => $messages,
			]
		);
		$data = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// Limit the length of the message, if the option is set.
			if (!empty($modSettings['xmlnews_maxlen']) && StringLibrary::strlen(str_replace('<br>', "\n", $row['body'])) > $modSettings['xmlnews_maxlen'])
				$row['body'] = strtr(StringLibrary::substr(str_replace('<br>', "\n", $row['body']), 0, $modSettings['xmlnews_maxlen'] - 3), ["\n" => '<br>']) . '...';

			$row['body'] = Parser::parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

			censorText($row['body']);
			censorText($row['subject']);

			// Create a GUID for this post using the tag URI scheme
			$guid = 'tag:' . parse_url($scripturl, PHP_URL_HOST) . ',' . gmdate('Y-m-d', $row['poster_time']) . ':msg=' . $row['id_msg'];

			$published_time = new DateTime('@' . $row['poster_time']);
			$updated_time = new DateTime('@' . (!empty($row['modified_time']) ? $row['modified_time'] : $row['poster_time']));

			$this->feed['items'][] = [
				'id' => $guid,
				'title' => html_entity_decode($row['subject'], ENT_QUOTES, 'UTF-8'),
				'description' => $row['body'],
				'content_link' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				'categories' => [$row['bname']],
				'author' => [
					'name' => $row['poster_name'],
					'email' => $row['id_member'] && (allowedTo('moderate_forum') || $row['id_member'] == $user_info['id']) ? $row['poster_email'] : null,
					'link' => $row['id_member'] && (allowedTo('profile_view') || $row['id_member'] == $user_info['id']) ? $scripturl . '?action=profile;u=' . $row['id_member'] . ($row['id_character'] && !$row['is_main'] ? ';area=characters;char=' . $row['id_character'] : '') : null,
				],
				'comment_url' => $scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
				'published_time' => [
					'timestamp' => $row['poster_time'],
					'rss' => $published_time->format(DateTimeInterface::RSS),
					'atom' => $published_time->format(DateTimeInterface::ATOM),
				],
				'updated_time' => [
					'timestamp' => !empty($row['modified_time']) ? $row['modified_time'] : $row['poster_time'],
					'rss' => $updated_time->format(DateTimeInterface::RSS),
					'atom' => $updated_time->format(DateTimeInterface::ATOM),
				],
			];

			if ($row['poster_time'] > $max_timestamp)
			{
				$max_timestamp = $row['poster_time'];
			}

			$this->feed['most_recent_content'] = [
				'timestamp' => $max_timestamp,
				'rss' => (new DateTime('@' . $max_timestamp))->format(DateTimeInterface::RSS),
				'atom' => (new DateTime('@' . $max_timestamp))->format(DateTimeInterface::ATOM),
			];

			$this->feed['generated_time'] = [
				'timestamp' => time(),
				'rss' => (new DateTime)->format(DateTimeInterface::RSS),
				'atom' => (new DateTime)->format(DateTimeInterface::ATOM),
			];
		}
		$smcFunc['db']->free_result($request);

		return $this->feed;
	}
}
