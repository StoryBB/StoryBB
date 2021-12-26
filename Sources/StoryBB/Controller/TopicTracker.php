<?php

/**
 * Displays the character topic tracker page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller;

use StoryBB\App;
use StoryBB\Model\TopicCollection;
use StoryBB\Model\TopicPrefix;
use StoryBB\Phrase;
use StoryBB\Routing\Behaviours\Routable;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class TopicTracker implements Routable
{
	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('topictracker', (new Route('/topic-tracker', ['_function' => [static::class, 'display_action']])));
	}

	public static function display_action()
	{
		global $context, $txt, $smcFunc, $scripturl, $memberContext;

		is_not_guest();

		loadLanguage('Profile');

		loadMemberData($context['user']['id'], false, 'profile');
		loadMemberContext($context['user']['id']);
		$context['user']['characters'] = $memberContext[$context['user']['id']]['characters'];

		$url = App::container()->get('urlgenerator');

		$context['page_title'] = $txt['topic_tracker'];
		$context['linktree'][] = [
			'url' => $url->generate('topictracker'),
			'name' => $txt['topic_tracker'],
		];
		$context['sub_template'] = 'topic_tracker';

		$context['time_ago_options'] = [
			'1week' => ['timestamp' => strtotime('-1 week'), 'label' => $txt['topic_tracker_last_post_1week']],
			'1month' => ['timestamp' => strtotime('-1 month'), 'label' => $txt['topic_tracker_last_post_1month']],
			'3months' => ['timestamp' => strtotime('-3 months'), 'label' => $txt['topic_tracker_last_post_3months']],
			'6months' => ['timestamp' => strtotime('-6 months'), 'label' => $txt['topic_tracker_last_post_6months']],
			'1year' => ['timestamp' => strtotime('-1 year'), 'label' => $txt['topic_tracker_last_post_1year']],
			'morethan1year' => ['timestamp' => 1, 'label' => $txt['topic_tracker_last_post_morethan1year']],
		];

		$character_ids = [];

		foreach ($context['user']['characters'] as $character)
		{
			if (empty($character['is_main']) && !$character['retired'])
			{
				$character_ids[] = $character['id_character'];
			}
		}

		// They shouldn't be able to get here if they have no non-OOC characters but just in case...
		if (empty($character_ids))
		{
			fatal_lang_error('no_access', false);
		}

		// First, step through and set it up.
		foreach ($context['user']['characters'] as $id_character => $character)
		{
			if (!empty($character['is_main']))
			{
				continue;
			}
			if ($character['retired'])
			{
				continue;
			}

			$context['user']['characters'][$id_character]['topics'] = [];
		}

		// Now get all the base topic information.
		$topic_ids = [];

		$request = $smcFunc['db']->query('', '
			SELECT t.id_topic, chars.id_character
			FROM {db_prefix}characters AS chars
			INNER JOIN {db_prefix}messages AS m ON (m.id_character = chars.id_character)
			INNER JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
			WHERE chars.id_character IN ({array_int:characters})
			GROUP BY chars.id_character, t.id_topic
			ORDER BY chars.id_character, t.id_topic',
			[
				'characters' => $character_ids,
			]
		);

		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$topic_ids[$row['id_topic']] = $row['id_topic'];
			$context['user']['characters'][$row['id_character']]['topics'][$row['id_topic']] = [
				'id_topic' => $row['id_topic'],
			];
		}

		$smcFunc['db']->free_result($request);

		$request = $smcFunc['db']->query('', '
			SELECT t.id_topic, chars.id_character
			FROM {db_prefix}topic_invites AS ti
			INNER JOIN {db_prefix}topics AS t ON (ti.id_topic = t.id_topic)
			INNER JOIN {db_prefix}characters AS chars ON (ti.id_character = chars.id_character)
			WHERE chars.id_character IN ({array_int:characters})
			GROUP BY chars.id_character, t.id_topic
			ORDER BY chars.id_character, t.id_topic',
			[
				'characters' => $character_ids,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$topic_ids[$row['id_topic']] = $row['id_topic'];
		}

		$smcFunc['db']->free_result($request);

		// If there are no topic ids, there's nothing else to fetch.
		if (empty($topic_ids))
		{
			return;
		}

		// We also need to get all the prefixes.
		$prefixes = TopicPrefix::get_prefixes_for_topic_list(array_keys($topic_ids));

		// And topic participants.
		$participants = TopicCollection::get_participants_for_topic_list(array_keys($topic_ids));

		// And things like the board these topics are in, plus first/last poster, and whether there are new posts in them.
		$topic_data = [];
		$request = $smcFunc['db']->query('', '
			SELECT
				COALESCE(lt.id_msg, COALESCE(lmr.id_msg, -1)) + 1 AS new_from, b.id_board, b.name, b.slug AS board_slug, t.locked, t.finished,
				t.id_topic, ms.subject, ms.id_member, COALESCE(chars.character_name, ms.poster_name) AS real_name_col,
				ml.id_msg_modified, ml.id_member AS id_member_updated,
				COALESCE(chars2.character_name, ml.poster_name) AS last_real_name,
				lt.unwatched, chars.is_main AS started_ooc, chars2.is_main AS updated_ooc,
				chars.id_character AS started_char, chars2.id_character AS updated_char,
				chars.avatar AS first_member_avatar, af.filename AS first_member_filename,
				chars2.avatar AS last_member_avatar, al.filename AS last_member_filename,
				t.id_first_msg, t.id_last_msg, t.num_replies,
				ms.poster_time AS first_poster_time, ml.poster_time AS last_poster_time
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ms.id_member)
				LEFT JOIN {db_prefix}characters AS chars ON (ms.id_character = chars.id_character AND chars.id_member = mem.id_member)
				LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = ml.id_member)
				LEFT JOIN {db_prefix}characters AS chars2 ON (chars2.id_character = ml.id_character AND chars2.id_member = mem2.id_member)
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})
				LEFT JOIN {db_prefix}attachments AS af ON (af.id_character = ms.id_character AND af.attachment_type = 1)
				LEFT JOIN {db_prefix}attachments AS al ON (al.id_character = ml.id_character AND al.attachment_type = 1)
			WHERE t.id_topic IN ({array_int:topic_ids})
				AND b.in_character = {int:in_character}',
			[
				'current_member' => $context['user']['id'],
				'is_approved' => 1,
				'topic_ids' => $topic_ids,
				'in_character' => 1,
			]
		);

		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$classes = 'topic-ic';
			if (!empty($row['locked']))
			{
				$classes .= ' locked';
			}
			if (!empty($row['finished']))
			{
				$classes .= ' finished';
			}

			if ($row['id_member_updated'] == $context['user']['id'])
			{
				$classes .= ' lastme';
			}
			else
			{
				$classes .= ' lastnotme';
			}
			$classes .= ' lastupdated' . $row['id_member_updated'] . '-' . $context['user']['id'];

			foreach ($context['time_ago_options'] as $class => $time)
			{
				if ($row['last_poster_time'] > $time['timestamp'])
				{
					$classes .= ' lp-' . $class;
					break;
				}
			}

			if ($row['new_from'] <= $row['id_msg_modified'])
			{
				$classes .= ' unread';
			}
			else
			{
				$classes .= ' nounread';
			}

			censorText($row['subject']);
			$topic_data[$row['id_topic']] = [
				'board' => [
					'id' => $row['id_board'],
					'name' => $row['name'],
					'href' => $url->generate('board', ['board_slug' => $row['board_slug']]),
				],
				'subject' => $row['subject'],
				'replies' => comma_format($row['num_replies']),
				'new' => $row['new_from'] <= $row['id_msg_modified'],
				'new_from' => $row['new_from'],
				'is_locked' => !empty($row['locked']),
				'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
				'new_link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new">' . $row['subject'] . '</a>',
				'first_post' => [
					'id' => $row['id_first_msg'],
					'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>',
					'time' => timeformat($row['first_poster_time']),
					'timestamp' => forum_time(true, $row['first_poster_time']),
					'member' => [
						'link' => empty($row['id_member']) ? $row['real_name_col'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . (empty($row['started_ooc']) && !empty($row['started_char']) ? ';area=characters;char=' . $row['started_char'] : '') . '">' . $row['real_name_col'] . '</a>',
					],
					'preview' => '',
				],
				'last_post' => [
					'id' => $row['id_last_msg'],
					'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_last_msg'] . '#msg' . $row['id_last_msg'],
					'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_last_msg'] . '#msg' . $row['id_last_msg'] . '">' . $row['subject'],
					'time' => timeformat($row['last_poster_time']),
					'timestamp' => forum_time(true, $row['last_poster_time']),
					'member' => [
						'link' => empty($row['id_member_updated']) ? $row['last_real_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_updated'] . (empty($row['updated_ooc']) && !empty($row['updated_char']) ? ';area=characters;char=' . $row['updated_char'] : '') . '">' . $row['last_real_name'] . '</a>',
					],
					'preview' => '',
				],
				'prefixes' => $prefixes[$row['id_topic']] ?? [],
				'participants' => $participants[$row['id_topic']] ?? [],
				'css_class' => $classes,
				'approved' => true, // We only filter on approved topics.
			];
		}
		$smcFunc['db']->free_result($request);

		// Now to join it all together.
		foreach ($context['user']['characters'] as $id_character => $character)
		{
			if (!isset($character['topics']))
			{
				continue;
			}

			foreach ($character['topics'] as $character_topic_id => $character_topic)
			{
				if (!isset($topic_data[$character_topic_id]))
				{
					unset($context['user']['characters'][$id_character]['topics'][$character_topic_id]);
					continue;
				}

				$context['user']['characters'][$id_character]['topics'][$character_topic_id] = $topic_data[$character_topic_id];
			}
		}

		// Now step through each of the characters looking for topics they were merely invited to, but have not actually posted yet.
		foreach ($participants as $character_topic_id => $characters_in_topic)
		{
			foreach ($characters_in_topic as $id_character => $character)
			{
				// We have no data about this topic? Skip it.
				if (!isset($topic_data[$character_topic_id]))
				{
					continue;
				}

				// If this character is already in the topic, skip it.
				if (isset($context['user']['characters'][$id_character]['topics'][$character_topic_id]))
				{
					continue;
				}

				// If we're not dealing with an invite, skip.
				if (empty($character['invite']))
				{
					continue;
				}

				$context['user']['characters'][$id_character]['topics'][$character_topic_id] = $topic_data[$character_topic_id];
				$context['user']['characters'][$id_character]['topics'][$character_topic_id]['invite'] = true;
			}
		}

		foreach ($context['user']['characters'] as $id_character => $character)
		{
			if (empty($character['topics']))
			{
				continue;
			}
			foreach ($character['topics'] as $id_topic => $topic)
			{
				$context['user']['characters'][$id_character]['topics'][$id_topic]['css_class'] .= (!empty($topic['invite']) ? ' invitedtopic' : ' postedtopic');
			}
		}
	}
}
