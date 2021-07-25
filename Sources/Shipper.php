<?php

/**
 * Calculates the intersections of characters and threads.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Model\TopicPrefix;

/**
 * Calculates the intersections of characters and threads.
 */
function Shipper()
{
	global $smcFunc, $context, $txt, $scripturl;

	$topics = [];
	$characters = [];
	$topic_starters = [];

	$request = $smcFunc['db']->query('', '
		SELECT t.id_topic, m.id_character, t.id_first_msg, MAX(chars.is_main) AS is_ooc
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
			INNER JOIN {db_prefix}characters AS chars ON (m.id_character = chars.id_character)
		WHERE {query_see_board}
			AND b.in_character = 1
		GROUP BY t.id_topic, m.id_character, t.id_first_msg');
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// If any of the topic participants are participating in an OOC capacity, ignore it.
		if ($row['is_ooc'])
		{
			continue;
		}

		// Collate the topics 
		$topics[$row['id_topic']]['characters'][$row['id_character']] = true;
		$topics[$row['id_topic']]['first_msg'] = $row['id_first_msg'];
		$topic_starters[$row['id_first_msg']] = $row['id_topic'];
		$characters[$row['id_character']] = false;
	}
	$smcFunc['db']->free_result($request);

	// Filter out topics that only have one character in them.
	foreach ($topics as $id_topic => $topic)
	{
		if (count($topic['characters']) < 2)
		{
			unset ($topics[$id_topic]);
		}
	}

	if (empty($topics))
	{
		fatal_lang_error('not_found', false);
	}

	// Fill in the topic IDs.
	$request = $smcFunc['db']->query('', '
		SELECT id_topic, subject
		FROM {db_prefix}messages
		WHERE id_msg IN ({array_int:msgs})',
		[
			'msgs' => array_keys($topic_starters),
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		if (!isset($topics[$row['id_topic']]))
		{
			continue;
		}
		censorText($row['subject']);
		$topics[$row['id_topic']]['subject'] = $row['subject'];
	}
	$smcFunc['db']->free_result($request);

	$prefixes = TopicPrefix::get_prefixes_for_topic_list(array_keys($topics));

	// Fill in the characters.
	$request = $smcFunc['db']->query('', '
		SELECT id_member, id_character, character_name
		FROM {db_prefix}characters
		WHERE id_character IN ({array_int:characters})',
		[
			'characters' => array_keys($characters),
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$characters[$row['id_character']] = $row;
	}
	$smcFunc['db']->free_result($request);

	// Now go figure out the pairings.
	$pairings = [];
	foreach ($topics as $id_topic => $topic)
	{
		foreach (permute_characters(array_keys($topic['characters'])) as $participants)
		{
			asort($participants);
			$pairings[implode('_', $participants)][] = $id_topic;
		}
	}

	$context['ships'] = [];
	$context['participating_characters'] = [];

	foreach ($pairings as $participants => $topics_participated)
	{
		$participants = explode('_', $participants);

		$ship = [
			'characters' => [],
			'topics' => [],
		];

		foreach ($participants as $id_character)
		{
			$ship['characters'][$id_character] = $characters[$id_character]['character_name'];
		}

		uasort($ship['characters'], function ($a, $b) {
			return strcasecmp($a, $b);
		});

		$ship['label'] = implode('', $ship['characters']);

		foreach ($topics_participated as $topic)
		{
			$ship['topics'][$topic] = [
				'subject' => $topics[$topic]['subject'],
				'first_msg' => $topics[$topic]['first_msg'],
				'topic_href' => $scripturl . '?topic=' . $topic . '.0',
				'prefixes' => $prefixes[$topic] ?? [],
			];
		}

		if (count($ship['topics']) < 2)
		{
			continue;
		}

		foreach ($ship['characters'] as $id_character => $character_name)
		{
			$context['participating_characters'][$id_character] = $character_name;
		}

		uasort($ship['topics'], function ($a, $b) {
			return $a['first_msg'] <=> $b['first_msg'];
		});

		$context['ships'][] = $ship;
	}

	usort($context['ships'], function($a, $b) {
		return strcasecmp($a['label'], $b['label']);
	});

	uasort($context['participating_characters'], function($a, $b) {
		return strcasecmp($a, $b);
	});

	$context['page_title'] = $txt['shippers'];
	$context['meta_description'] = $txt['shipper_description'];
	$context['sub_template'] = 'shipper';
}

function permute_characters(array $characters): array
{
	for ($i = 0, $n = count($characters); $i < $n; $i++)
	{
		for ($j = $i + 1; $j < $n; $j++)
		{
			$pairings[] = [$characters[$i], $characters[$j]];
		}
	}

	return $pairings;
}
