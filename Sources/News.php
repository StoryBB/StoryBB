<?php

/**
 * This file contains the files necessary to display news as an XML feed.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Helper\Parser;

/**
 * Outputs xml data representing recent information or a profile.
 * Can be passed 4 subactions which decide what is output:
 *  'recent' for recent posts,
 *  'news' for news topics,
 *  'members' for recently registered members,
 *  'profile' for a member's profile.
 * To display a member's profile, a user id has to be given. (;u=1)
 * Outputs an rss feed instead of a proprietary one if the 'type' $_GET
 * parameter is 'rss' or 'rss2'.
 * Accessed via ?action=.xml.
 * Does not use any templates, sub templates, or template layers.
 *
 * @uses Stats language file.
 */
function ShowXmlFeed()
{
	global $board, $board_info, $context, $scripturl, $boardurl, $txt, $modSettings, $user_info;
	global $query_this_board, $smcFunc, $forum_version, $settings;

	// If it's not enabled, die.
	if (empty($modSettings['xmlnews_enable']))
		obExit(false);

	loadLanguage('Stats');

	// Default to latest 5.  No more than 255, please.
	$_GET['limit'] = empty($_GET['limit']) || (int) $_GET['limit'] < 1 ? 5 : min((int) $_GET['limit'], 255);

	// Some general metadata for this feed. We'll change some of these values below.
	$feed_meta = array(
		'title' => '',
		'desc' => str_replace('{forum_name}', $context['forum_name'], $txt['xml_rss_desc']),
		'author' => $context['forum_name'],
		'source' => $scripturl,
		'rights' => '© ' . date('Y') . ' ' . $context['forum_name'],
		'icon' => !empty($settings['og_image']) ? $settings['og_image'] : $boardurl . '/favicon.ico',
		'language' => !empty($txt['lang_locale']) ? str_replace("_", "-", substr($txt['lang_locale'], 0, strcspn($txt['lang_locale'], "."))) : 'en',
	);

	// Handle the cases where a board, boards, or category is asked for.
	$query_this_board = 1;
	$context['optimize_msg'] = array(
		'highest' => 'm.id_msg <= b.id_last_msg',
	);
	if (!empty($_REQUEST['c']) && empty($board))
	{
		$_REQUEST['c'] = explode(',', $_REQUEST['c']);
		foreach ($_REQUEST['c'] as $i => $c)
			$_REQUEST['c'][$i] = (int) $c;

		if (count($_REQUEST['c']) == 1)
		{
			$request = $smcFunc['db_query']('', '
				SELECT name
				FROM {db_prefix}categories
				WHERE id_cat = {int:current_category}',
				array(
					'current_category' => (int) $_REQUEST['c'][0],
				)
			);
			list ($feed_meta['title']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			$feed_meta['title'] = ' - ' . strip_tags($feed_meta['title']);
		}

		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.num_posts
			FROM {db_prefix}boards AS b
			WHERE b.id_cat IN ({array_int:current_category_list})
				AND {query_see_board}',
			array(
				'current_category_list' => $_REQUEST['c'],
			)
		);
		$total_cat_posts = 0;
		$boards = [];
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$boards[] = $row['id_board'];
			$total_cat_posts += $row['num_posts'];
		}
		$smcFunc['db_free_result']($request);

		if (!empty($boards))
			$query_this_board = 'b.id_board IN (' . implode(', ', $boards) . ')';

		// Try to limit the number of messages we look through.
		if ($total_cat_posts > 100 && $total_cat_posts > $modSettings['totalMessages'] / 15)
			$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 400 - $_GET['limit'] * 5);
	}
	elseif (!empty($_REQUEST['boards']))
	{
		$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
		foreach ($_REQUEST['boards'] as $i => $b)
			$_REQUEST['boards'][$i] = (int) $b;

		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.num_posts, b.name
			FROM {db_prefix}boards AS b
			WHERE b.id_board IN ({array_int:board_list})
				AND {query_see_board}
			LIMIT {int:limit}',
			array(
				'board_list' => $_REQUEST['boards'],
				'limit' => count($_REQUEST['boards']),
			)
		);

		// Either the board specified doesn't exist or you have no access.
		$num_boards = $smcFunc['db_num_rows']($request);
		if ($num_boards == 0)
			fatal_lang_error('no_board');

		$total_posts = 0;
		$boards = [];
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($num_boards == 1)
				$feed_meta['title'] = ' - ' . strip_tags($row['name']);

			$boards[] = $row['id_board'];
			$total_posts += $row['num_posts'];
		}
		$smcFunc['db_free_result']($request);

		if (!empty($boards))
			$query_this_board = 'b.id_board IN (' . implode(', ', $boards) . ')';

		// The more boards, the more we're going to look through...
		if ($total_posts > 100 && $total_posts > $modSettings['totalMessages'] / 12)
			$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 500 - $_GET['limit'] * 5);
	}
	elseif (!empty($board))
	{
		$request = $smcFunc['db_query']('', '
			SELECT num_posts
			FROM {db_prefix}boards
			WHERE id_board = {int:current_board}
			LIMIT 1',
			array(
				'current_board' => $board,
			)
		);
		list ($total_posts) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$feed_meta['title'] = ' - ' . strip_tags($board_info['name']);
		$feed_meta['source'] .= '?board=' . $board . '.0';

		$query_this_board = 'b.id_board = ' . $board;

		// Try to look through just a few messages, if at all possible.
		if ($total_posts > 80 && $total_posts > $modSettings['totalMessages'] / 10)
			$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 600 - $_GET['limit'] * 5);
	}
	else
	{
		$query_this_board = '{query_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != ' . $modSettings['recycle_board'] : '');
		$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 100 - $_GET['limit'] * 5);
	}

	// Show in rss or proprietary format?
	$xml_format = isset($_GET['type']) && in_array($_GET['type'], array('rss', 'rss2', 'atom')) ? $_GET['type'] : 'rss2';

	// @todo Birthdays?

	// List all the different types of data they can pull.
	$subActions = array(
		'recent' => array('getXmlRecent', 'recent-post'),
		'news' => array('getXmlNews', 'article'),
		'members' => array('getXmlMembers', 'member'),
		'profile' => array('getXmlProfile', null),
	);

	// Easy adding of sub actions
	call_integration_hook('integrate_xmlfeeds', array(&$subActions));

	if (empty($_GET['sa']) || !isset($subActions[$_GET['sa']]))
		$_GET['sa'] = 'recent';

	// We only want some information, not all of it.
	$cachekey = array($xml_format, $_GET['action'], $_GET['limit'], $_GET['sa']);
	foreach (array('board', 'boards', 'c') as $var)
		if (isset($_REQUEST[$var]))
			$cachekey[] = $_REQUEST[$var];
	$cachekey = md5(json_encode($cachekey) . (!empty($query_this_board) ? $query_this_board : ''));
	$cache_t = microtime(true);

	// Get the associative array representing the xml.
	if (!empty($modSettings['cache_enable']) && (!$user_info['is_guest'] || $modSettings['cache_enable'] >= 3))
		$xml_data = cache_get_data('xmlfeed-' . $xml_format . ':' . ($user_info['is_guest'] ? '' : $user_info['id'] . '-') . $cachekey, 240);
	if (empty($xml_data))
	{
		$call = call_helper($subActions[$_GET['sa']][0], true);

		if (!empty($call))
			$xml_data = call_user_func($call, $xml_format);

		if (!empty($modSettings['cache_enable']) && (($user_info['is_guest'] && $modSettings['cache_enable'] >= 3)
		|| (!$user_info['is_guest'] && (microtime(true) - $cache_t > 0.2))))
			cache_put_data('xmlfeed-' . $xml_format . ':' . ($user_info['is_guest'] ? '' : $user_info['id'] . '-') . $cachekey, $xml_data, 240);
	}

	$feed_meta['title'] = $smcFunc['htmlspecialchars'](strip_tags($context['forum_name'])) . (isset($feed_meta['title']) ? $feed_meta['title'] : '');

	// Allow mods to add extra namespaces and tags to the feed/channel
	$namespaces = array(
		'rss' => [],
		'rss2' => array('atom' => 'http://www.w3.org/2005/Atom'),
		'atom' => array('' => 'http://www.w3.org/2005/Atom'),
	);
	$extraFeedTags = array(
		'rss' => [],
		'rss2' => [],
		'atom' => [],
	);

	// Allow mods to specify any keys that need special handling
	$forceCdataKeys = [];
	$nsKeys = [];

	// Remember this, just in case...
	$orig_feed_meta = $feed_meta;

	// If mods want to do somthing with this feed, let them do that now.
	// Provide the feed's data, metadata, namespaces, extra feed-level tags, keys that need special handling, the feed format, and the requested subaction
	call_integration_hook('integrate_xml_data', array(&$xml_data, &$feed_meta, &$namespaces, &$extraFeedTags, &$forceCdataKeys, &$nsKeys, $xml_format, $_GET['sa']));

	// These can't be empty
	foreach (array('title', 'desc', 'source') as $mkey)
		$feed_meta[$mkey] = !empty($feed_meta[$mkey]) ? $feed_meta[$mkey] : $orig_feed_meta[$mkey];

	// Sanitize basic feed metadata values
	foreach ($feed_meta as $mkey => $mvalue)
		$feed_meta[$mkey] = cdata_parse(strip_tags(fix_possible_url($feed_meta[$mkey])));

	$ns_string = '';
	if (!empty($namespaces[$xml_format]))
	{
		foreach ($namespaces[$xml_format] as $nsprefix => $nsurl)
			$ns_string .= ' xmlns' . ($nsprefix !== '' ? ':' : '') . $nsprefix . '="' . $nsurl . '"';
	}

	$extraFeedTags_string = '';
	if (!empty($extraFeedTags[$xml_format]))
	{
		$indent = "\t" . ($xml_format !== 'atom' ? "\t" : '');
		foreach ($extraFeedTags[$xml_format] as $extraTag)
			$extraFeedTags_string .= "\n" . $indent . $extraTag;
	}

	// This is an xml file....
	ob_end_clean();
	ob_start();

	if ($xml_format == 'rss' || $xml_format == 'rss2')
		header('Content-Type: application/rss+xml; charset=UTF-8');
	elseif ($xml_format == 'atom')
		header('Content-Type: application/atom+xml; charset=UTF-8');

	// First, output the xml header.
	echo '<?xml version="1.0" encoding="UTF-8"?' . '>';

	// Are we outputting an rss feed or one with more information?
	if ($xml_format == 'rss' || $xml_format == 'rss2')
	{
		if ($xml_format == 'rss2')
			foreach ($_REQUEST as $var => $val)
				if (in_array($var, array('action', 'sa', 'type', 'board', 'boards', 'c', 'u', 'limit')))
					$url_parts[] = $var . '=' . (is_array($val) ? implode(',', $val) : $val);

		// Start with an RSS 2.0 header.
		echo '
<rss version=', $xml_format == 'rss2' ? '"2.0"' : '"0.92"', ' xml:lang="', strtr($txt['lang_locale'], '_', '-'), '"', $ns_string, '>
	<channel>
		<title>', $feed_meta['title'], '</title>
		<link>', $feed_meta['source'], '</link>
		<description>', $feed_meta['desc'], '</description>',
		!empty($feed_meta['icon']) ? '
		<image>
			<url>' . $feed_meta['icon'] . '</url>
			<title>' . $feed_meta['title'] . '</title>
			<link>' . $feed_meta['source'] . '</link>
		</image>' : '',
		!empty($feed_meta['rights']) ? '
		<copyright>' . $feed_meta['rights'] . '</copyright>' : '',
		!empty($feed_meta['language']) ? '
		<language>' . $feed_meta['language'] . '</language>' : '';

		// RSS2 calls for this.
		if ($xml_format == 'rss2')
			echo '
		<atom:link rel="self" type="application/rss+xml" href="', $scripturl, !empty($url_parts) ? '?' . implode(';', $url_parts) : '', '" />';

		echo $extraFeedTags_string;

		// Output all of the associative array, start indenting with 2 tabs, and name everything "item".
		dumpTags($xml_data, 2, null, $xml_format, $forceCdataKeys, $nsKeys);

		// Output the footer of the xml.
		echo '
	</channel>
</rss>';
	}
	elseif ($xml_format == 'atom')
	{
		foreach ($_REQUEST as $var => $val)
			if (in_array($var, array('action', 'sa', 'type', 'board', 'boards', 'c', 'u', 'limit')))
				$url_parts[] = $var . '=' . (is_array($val) ? implode(',', $val) : $val);

		echo '
<feed', $ns_string, !empty($feed_meta['language']) ? ' xml:lang="' . $feed_meta['language'] . '"' : '', '>
	<title>', $feed_meta['title'], '</title>
	<link rel="alternate" type="text/html" href="', $feed_meta['source'], '" />
	<link rel="self" type="application/atom+xml" href="', $scripturl, !empty($url_parts) ? '?' . implode(';', $url_parts) : '', '" />
	<updated>', gmstrftime('%Y-%m-%dT%H:%M:%SZ'), '</updated>
	<id>', $feed_meta['source'], '</id>
	<subtitle>', $feed_meta['desc'], '</subtitle>
	<generator uri="https://storybb.org" version="', strtr($forum_version, array('StoryBB ' => '')), '">StoryBB</generator>',
	!empty($feed_meta['icon']) ? '
	<icon>' . $feed_meta['icon'] . '</icon>' : '',
	!empty($feed_meta['author']) ? '
	<author>
		<name>' . $feed_meta['author'] . '</name>
	</author>' : '',
	!empty($feed_meta['rights']) ? '
	<rights>' . $feed_meta['rights'] . '</rights>' : '';

		echo $extraFeedTags_string;

		dumpTags($xml_data, 1, null, $xml_format, $forceCdataKeys, $nsKeys);

		echo '
</feed>';
	}

	obExit(false);
}

/**
 * Called from dumpTags to convert data to xml
 * Finds urls for local site and sanitizes them
 *
 * @param string $val A string containing a possible URL
 * @return string $val The string with any possible URLs sanitized
 */
function fix_possible_url($val)
{
	global $modSettings, $context, $scripturl;

	if (substr($val, 0, strlen($scripturl)) != $scripturl)
		return $val;

	call_integration_hook('integrate_fix_url', array(&$val));

	return $val;
}

/**
 * Ensures supplied data is properly encapsulated in cdata xml tags
 * Called from getXmlProfile in News.php
 *
 * @param string $data XML data
 * @param string $ns A namespace prefix for the XML data elements (used by mods, maybe)
 * @param boolean $force If true, enclose the XML data in cdata tags no matter what (used by mods, maybe)
 * @return string The XML data enclosed in cdata tags when necessary
 */
function cdata_parse($data, $ns = '', $force = false)
{
	global $smcFunc;

	// Do we even need to do this?
	if (strpbrk($data, '<>&') == false && $force !== true)
		return $data;

	$cdata = '<![CDATA[';

	for ($pos = 0, $n = $smcFunc['strlen']($data); $pos < $n; null)
	{
		$positions = array(
			$smcFunc['strpos']($data, '&', $pos),
			$smcFunc['strpos']($data, ']', $pos),
		);
		if ($ns != '')
			$positions[] = $smcFunc['strpos']($data, '<', $pos);
		foreach ($positions as $k => $dummy)
		{
			if ($dummy === false)
				unset($positions[$k]);
		}

		$old = $pos;
		$pos = empty($positions) ? $n : min($positions);

		if ($pos - $old > 0)
			$cdata .= $smcFunc['substr']($data, $old, $pos - $old);
		if ($pos >= $n)
			break;

		if ($smcFunc['substr']($data, $pos, 1) == '<')
		{
			$pos2 = $smcFunc['strpos']($data, '>', $pos);
			if ($pos2 === false)
				$pos2 = $n;
			if ($smcFunc['substr']($data, $pos + 1, 1) == '/')
				$cdata .= ']]></' . $ns . ':' . $smcFunc['substr']($data, $pos + 2, $pos2 - $pos - 1) . '<![CDATA[';
			else
				$cdata .= ']]><' . $ns . ':' . $smcFunc['substr']($data, $pos + 1, $pos2 - $pos) . '<![CDATA[';
			$pos = $pos2 + 1;
		}
		elseif ($smcFunc['substr']($data, $pos, 1) == ']')
		{
			$cdata .= ']]>&#093;<![CDATA[';
			$pos++;
		}
		elseif ($smcFunc['substr']($data, $pos, 1) == '&')
		{
			$pos2 = $smcFunc['strpos']($data, ';', $pos);
			if ($pos2 === false)
				$pos2 = $n;
			$ent = $smcFunc['substr']($data, $pos + 1, $pos2 - $pos - 1);

			if ($smcFunc['substr']($data, $pos + 1, 1) == '#')
				$cdata .= ']]>' . $smcFunc['substr']($data, $pos, $pos2 - $pos + 1) . '<![CDATA[';
			elseif (in_array($ent, array('amp', 'lt', 'gt', 'quot')))
				$cdata .= ']]>' . $smcFunc['substr']($data, $pos, $pos2 - $pos + 1) . '<![CDATA[';

			$pos = $pos2 + 1;
		}
	}

	$cdata .= ']]>';

	return strtr($cdata, array('<![CDATA[]]>' => ''));
}

/**
 * Formats data retrieved in other functions into xml format.
 * Additionally formats data based on the specific format passed.
 * This function is recursively called to handle sub arrays of data.
 *
 * @param array $data The array to output as xml data
 * @param int $i The amount of indentation to use.
 * @param array $tag The tag to render.
 * @param string $xml_format The format to use ('atom', 'rss', 'rss2' or empty for plain XML)
 * @param array $forceCdataKeys A list of keys on which to force cdata wrapping (used by mods, maybe)
 * @param array $nsKeys Key-value pairs of namespace prefixes to pass to cdata_parse() (used by mods, maybe)
 */
function dumpTags($data, $i, $tag = null, $xml_format = '', $forceCdataKeys = [], $nsKeys = [])
{
	// Wrap the values of these keys into CDATA tags
	$keysToCdata = array(
		'title',
		'name',
		'description',
		'summary',
		'subject',
		'body',
		'username',
		'signature',
		'position',
		'language',
	);
	if ($xml_format != 'atom')
		$keysToCdata[] = 'category';

	if (!empty($forceCdataKeys))
	{
		$keysToCdata = array_merge($keysToCdata, $forceCdataKeys);
		$keysToCdata = array_unique($keysToCdata);
	}

	// For every array in the data...
	foreach ($data as $element)
	{
		// If a tag was passed, use it instead of the key.
		$key = isset($tag) ? $tag : (isset($element['tag']) ? $element['tag'] : null);
		$val = isset($element['content']) ? $element['content'] : null;
		$attrs = isset($element['attributes']) ? $element['attributes'] : null;

		// Skip it, it's been set to null.
		if ($key === null || ($val === null && $attrs === null))
			continue;

		$forceCdata = in_array($key, $forceCdataKeys);
		$ns = !empty($nsKeys[$key]) ? $nsKeys[$key] : '';

		// First let's indent!
		echo "\n", str_repeat("\t", $i);

		// Beginning tag.
		echo '<', $key;

		if (!empty($attrs))
		{
			foreach ($attrs as $attr_key => $attr_value)
				echo ' ', $attr_key, '="', fix_possible_url($attr_value), '"';
		}

		// If it's empty, simply output an empty element.
		if (empty($val))
		{
			echo ' />';
		}
		else
		{
			echo '>';

			// The element's value.
			if (is_array($val))
			{
				// An array.  Dump it, and then indent the tag.
				dumpTags($val, $i + 1, null, $xml_format, $forceCdataKeys, $nsKeys);
				echo "\n", str_repeat("\t", $i);
			}
			// A string with returns in it.... show this as a multiline element.
			elseif (strpos($val, "\n") !== false)
				echo "\n", in_array($key, $keysToCdata) ? cdata_parse(fix_possible_url($val), $ns, $forceCdata) : fix_possible_url($val), "\n", str_repeat("\t", $i);
			// A simple string.
			else
				echo in_array($key, $keysToCdata) ? cdata_parse(fix_possible_url($val), $ns, $forceCdata) : fix_possible_url($val);

			// Ending tag.
			echo '</', $key, '>';
		}
	}
}

/**
 * Retrieve the list of members from database.
 * The array will be generated to match the format.
 * @todo get the list of members from Subs-Members.
 *
 * @param string $xml_format The format to use. Can be 'atom', 'rss', 'rss2'
 * @return array An array of arrays of feed items. Each array has keys corresponding to the appropriate tags for the specified format.
 */
function getXmlMembers($xml_format)
{
	global $scripturl, $smcFunc;

	if (!allowedTo('view_mlist'))
		return [];

	// Find the most recent members.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name, real_name, date_registered, last_login
		FROM {db_prefix}members
		ORDER BY id_member DESC
		LIMIT {int:limit}',
		array(
			'limit' => $_GET['limit'],
		)
	);
	$data = [];
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Create a GUID for each member using the tag URI scheme
		$guid = 'tag:' . parse_url($scripturl, PHP_URL_HOST) . ',' . gmdate('Y-m-d', $row['date_registered']) . ':member=' . $row['id_member'];

		// Make the data look rss-ish.
		if ($xml_format == 'rss' || $xml_format == 'rss2')
			$data[] = array(
				'tag' => 'item',
				'content' => array(
					array(
						'tag' => 'title',
						'content' => $row['real_name'],
					),
					array(
						'tag' => 'link',
						'content' => $scripturl . '?action=profile;u=' . $row['id_member'],
					),
					array(
						'tag' => 'comments',
						'content' => $scripturl . '?action=pm;sa=send;u=' . $row['id_member'],
					),
					array(
						'tag' => 'pubDate',
						'content' => gmdate('D, d M Y H:i:s \G\M\T', $row['date_registered']),
					),
					array(
						'tag' => 'guid',
						'content' => $guid,
						'attributes' => array(
							'isPermaLink' => 'false',
						),
					),
				),
			);
		elseif ($xml_format == 'atom')
			$data[] = array(
				'tag' => 'entry',
				'content' => array(
					array(
						'tag' => 'title',
						'content' => $row['real_name'],
					),
					array(
						'tag' => 'link',
						'attributes' => array(
							'rel' => 'alternate',
							'type' => 'text/html',
							'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
						),
					),
					array(
						'tag' => 'published',
						'content' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['date_registered']),
					),
					array(
						'tag' => 'updated',
						'content' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['last_login']),
					),
					array(
						'tag' => 'id',
						'content' => $guid,
					),
				),
			);
	}
	$smcFunc['db_free_result']($request);

	return $data;
}

/**
 * Get the latest topics information from a specific board,
 * to display later.
 * The returned array will be generated to match the xml_format.
 * @todo does not belong here
 *
 * @param $xml_format The XML format. Can be 'atom', rss', 'rss2'.
 * @return array An array of arrays of topic data for the feed. Each array has keys corresponding to the tags for the specified format.
 */
function getXmlNews($xml_format)
{
	global $scripturl, $modSettings, $board, $user_info;
	global $query_this_board, $smcFunc, $context, $txt;

	/* Find the latest posts that:
		- are the first post in their topic.
		- are on an any board OR in a specified board.
		- can be seen by this user.
		- are actually the latest posts. */

	$done = false;
	$loops = 0;
	while (!$done)
	{
		$optimize_msg = implode(' AND ', $context['optimize_msg']);
		$request = $smcFunc['db_query']('', '
			SELECT
				m.smileys_enabled, m.poster_time, m.id_msg, m.subject, m.body, m.modified_time,
				m.icon, t.id_topic, t.id_board, t.num_replies,
				b.name AS bname,
				COALESCE(mem.id_member, 0) AS id_member,
				COALESCE(mem.email_address, m.poster_email) AS poster_email,
				COALESCE(mem.real_name, m.poster_name) AS poster_name
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE ' . $query_this_board . (empty($optimize_msg) ? '' : '
				AND {raw:optimize_msg}') . (empty($board) ? '' : '
				AND t.id_board = {int:current_board}') . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . '
			ORDER BY t.id_first_msg DESC
			LIMIT {int:limit}',
			array(
				'current_board' => $board,
				'is_approved' => 1,
				'limit' => $_GET['limit'],
				'optimize_msg' => $optimize_msg,
			)
		);
		// If we don't have $_GET['limit'] results, try again with an unoptimized version covering all rows.
		if ($loops < 2 && $smcFunc['db_num_rows']($request) < $_GET['limit'])
		{
			$smcFunc['db_free_result']($request);
			if (empty($_REQUEST['boards']) && empty($board))
				unset($context['optimize_msg']['lowest']);
			else
				$context['optimize_msg']['lowest'] = 'm.id_msg >= t.id_first_msg';
			$context['optimize_msg']['highest'] = 'm.id_msg <= t.id_last_msg';
			$loops++;
		}
		else
			$done = true;
	}
	$data = [];
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Limit the length of the message, if the option is set.
		if (!empty($modSettings['xmlnews_maxlen']) && $smcFunc['strlen'](str_replace('<br>', "\n", $row['body'])) > $modSettings['xmlnews_maxlen'])
			$row['body'] = strtr($smcFunc['substr'](str_replace('<br>', "\n", $row['body']), 0, $modSettings['xmlnews_maxlen'] - 3), array("\n" => '<br>')) . '...';

		$row['body'] = Parser::parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		censorText($row['body']);
		censorText($row['subject']);

		// Do we want to include any attachments?
		if (!empty($modSettings['attachmentEnable']) && !empty($modSettings['xmlnews_attachments']) && allowedTo('view_attachments', $row['id_board']))
		{
			$attach_request = $smcFunc['db_query']('', '
				SELECT
					a.id_attach, a.filename, COALESCE(a.size, 0) AS filesize, a.mime_type, a.downloads, a.approved, m.id_topic AS topic
				FROM {db_prefix}attachments AS a
					LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				WHERE a.attachment_type = {int:attachment_type}
					AND a.id_msg = {int:message_id}',
				array(
					'message_id' => $row['id_msg'],
					'attachment_type' => 0,
					'is_approved' => 1,
				)
			);
			$loaded_attachments = [];
			while ($attach = $smcFunc['db_fetch_assoc']($attach_request))
			{
				// Include approved attachments only
				if ($attach['approved'])
					$loaded_attachments['attachment_' . $attach['id_attach']] = $attach;
			}
			$smcFunc['db_free_result']($attach_request);

			// Sort the attachments by size to make things easier below
			if (!empty($loaded_attachments))
			{
				uasort($loaded_attachments, function($a, $b) {
					if ($a['filesize'] == $b['filesize'])
						return 0;
					return ($a['filesize'] < $b['filesize']) ? -1 : 1;
				});
			}
			else
				$loaded_attachments = null;
		}
		else
			$loaded_attachments = null;

		// Create a GUID for this topic using the tag URI scheme
		$guid = 'tag:' . parse_url($scripturl, PHP_URL_HOST) . ',' . gmdate('Y-m-d', $row['poster_time']) . ':topic=' . $row['id_topic'];

		// Being news, this actually makes sense in rss format.
		if ($xml_format == 'rss' || $xml_format == 'rss2')
		{
			// Only one attachment allowed in RSS.
			if ($loaded_attachments !== null)
			{
				$attachment = array_pop($loaded_attachments);
				$enclosure = array(
					'url' => fix_possible_url($scripturl . '?action=dlattach;topic=' . $attachment['topic'] . '.0;attach=' . $attachment['id_attach']),
					'length' => $attachment['filesize'],
					'type' => $attachment['mime_type'],
				);
			}
			else
				$enclosure = null;

			$data[] = array(
				'tag' => 'item',
				'content' => array(
					array(
						'tag' => 'title',
						'content' => $row['subject'],
					),
					array(
						'tag' => 'link',
						'content' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
					),
					array(
						'tag' => 'description',
						'content' => $row['body'],
					),
					array(
						'tag' => 'author',
						'content' => (allowedTo('moderate_forum') || $row['id_member'] == $user_info['id']) ? $row['poster_email'] . ' (' . $row['poster_name'] . ')' : null,
					),
					array(
						'tag' => 'comments',
						'content' => $scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
					),
					array(
						'tag' => 'category',
						'content' => $row['bname'],
					),
					array(
						'tag' => 'pubDate',
						'content' => gmdate('D, d M Y H:i:s \G\M\T', $row['poster_time']),
					),
					array(
						'tag' => 'guid',
						'content' => $guid,
						'attributes' => array(
							'isPermaLink' => 'false',
						),
					),
					array(
						'tag' => 'enclosure',
						'attributes' => $enclosure,
					),
				),
			);
		}
		elseif ($xml_format == 'atom')
		{
			// Only one attachment allowed
			if (!empty($loaded_attachments))
			{
				$attachment = array_pop($loaded_attachments);
				$enclosure = array(
					'rel' => 'enclosure',
					'href' => fix_possible_url($scripturl . '?action=dlattach;topic=' . $attachment['topic'] . '.0;attach=' . $attachment['id_attach']),
					'length' => $attachment['filesize'],
					'type' => $attachment['mime_type'],
				);
			}
			else
				$enclosure = null;

			$data[] = array(
				'tag' => 'entry',
				'content' => array(
					array(
						'tag' => 'title',
						'content' => $row['subject'],
					),
					array(
						'tag' => 'link',
						'attributes' => array(
							'rel' => 'alternate',
							'type' => 'text/html',
							'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
						),
					),
					array(
						'tag' => 'summary',
						'attributes' => array('type' => 'html'),
						'content' => $row['body'],
					),
					array(
						'tag' => 'category',
						'attributes' => array('term' => $row['bname']),
					),
					array(
						'tag' => 'author',
						'content' => array(
							array(
								'tag' => 'name',
								'content' => $row['poster_name'],
							),
							array(
								'tag' => 'email',
								'content' => (allowedTo('moderate_forum') || $row['id_member'] == $user_info['id']) ? $row['poster_email'] : null,
							),
							array(
								'tag' => 'uri',
								'content' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : null,
							),
						)
					),
					array(
						'tag' => 'published',
						'content' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['poster_time']),
					),
					array(
						'tag' => 'updated',
						'content' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', empty($row['modified_time']) ? $row['poster_time'] : $row['modified_time']),
					),
					array(
						'tag' => 'id',
						'content' => $guid,
					),
					array(
						'tag' => 'link',
						'attributes' => $enclosure,
					),
				),
			);
		}
	}
	$smcFunc['db_free_result']($request);

	return $data;
}

/**
 * Get the recent topics to display.
 * The returned array will be generated to match the xml_format.
 * @todo does not belong here.
 *
 * @param string $xml_format The XML format. Can be 'atom', 'rss', 'rss2'
 * @return array An array of arrays containing data for the feed. Each array has keys corresponding to the appropriate tags for the specified format.
 */
function getXmlRecent($xml_format)
{
	global $scripturl, $modSettings, $board, $txt;
	global $query_this_board, $smcFunc, $context, $user_info, $sourcedir;

	require_once($sourcedir . '/Subs-Attachments.php');

	$done = false;
	$loops = 0;
	while (!$done)
	{
		$optimize_msg = implode(' AND ', $context['optimize_msg']);
		$request = $smcFunc['db_query']('', '
			SELECT m.id_msg
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			WHERE ' . $query_this_board . (empty($optimize_msg) ? '' : '
				AND {raw:optimize_msg}') . (empty($board) ? '' : '
				AND m.id_board = {int:current_board}') . ($modSettings['postmod_active'] ? '
				AND m.approved = {int:is_approved}' : '') . '
			ORDER BY m.id_msg DESC
			LIMIT {int:limit}',
			array(
				'limit' => $_GET['limit'],
				'current_board' => $board,
				'is_approved' => 1,
				'optimize_msg' => $optimize_msg,
			)
		);
		// If we don't have $_GET['limit'] results, try again with an unoptimized version covering all rows.
		if ($loops < 2 && $smcFunc['db_num_rows']($request) < $_GET['limit'])
		{
			$smcFunc['db_free_result']($request);
			if (empty($_REQUEST['boards']) && empty($board))
				unset($context['optimize_msg']['lowest']);
			else
				$context['optimize_msg']['lowest'] = $loops ? 'm.id_msg >= t.id_first_msg' : 'm.id_msg >= (t.id_last_msg - t.id_first_msg) / 2';
			$loops++;
		}
		else
			$done = true;
	}
	$messages = [];
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$messages[] = $row['id_msg'];
	$smcFunc['db_free_result']($request);

	if (empty($messages))
		return [];

	// Find the most recent posts this user can see.
	$request = $smcFunc['db_query']('', '
		SELECT
			m.smileys_enabled, m.poster_time, m.id_msg, m.subject, m.body, m.id_topic, t.id_board,
			b.name AS bname, t.num_replies, m.id_member, m.icon, mf.id_member AS id_first_member,
			COALESCE(mem.real_name, m.poster_name) AS poster_name, mf.subject AS first_subject,
			COALESCE(memf.real_name, mf.poster_name) AS first_poster_name,
			COALESCE(mem.email_address, m.poster_email) AS poster_email, m.modified_time
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
		WHERE m.id_msg IN ({array_int:message_list})
			' . (empty($board) ? '' : 'AND t.id_board = {int:current_board}') . '
		ORDER BY m.id_msg DESC
		LIMIT {int:limit}',
		array(
			'limit' => $_GET['limit'],
			'current_board' => $board,
			'message_list' => $messages,
		)
	);
	$data = [];
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Limit the length of the message, if the option is set.
		if (!empty($modSettings['xmlnews_maxlen']) && $smcFunc['strlen'](str_replace('<br>', "\n", $row['body'])) > $modSettings['xmlnews_maxlen'])
			$row['body'] = strtr($smcFunc['substr'](str_replace('<br>', "\n", $row['body']), 0, $modSettings['xmlnews_maxlen'] - 3), array("\n" => '<br>')) . '...';

		$row['body'] = Parser::parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		censorText($row['body']);
		censorText($row['subject']);

		// Do we want to include any attachments?
		if (!empty($modSettings['attachmentEnable']) && !empty($modSettings['xmlnews_attachments']) && allowedTo('view_attachments', $row['id_board']))
		{
			$attach_request = $smcFunc['db_query']('', '
				SELECT
					a.id_attach, a.filename, COALESCE(a.size, 0) AS filesize, a.mime_type, a.downloads, a.approved, m.id_topic AS topic
				FROM {db_prefix}attachments AS a
					LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				WHERE a.attachment_type = {int:attachment_type}
					AND a.id_msg = {int:message_id}',
				array(
					'message_id' => $row['id_msg'],
					'attachment_type' => 0,
					'is_approved' => 1,
				)
			);
			$loaded_attachments = [];
			while ($attach = $smcFunc['db_fetch_assoc']($attach_request))
			{
				// Include approved attachments only
				if ($attach['approved'])
					$loaded_attachments['attachment_' . $attach['id_attach']] = $attach;
			}
			$smcFunc['db_free_result']($attach_request);

			// Sort the attachments by size to make things easier below
			if (!empty($loaded_attachments))
			{
				uasort($loaded_attachments, function($a, $b) {
					if ($a['filesize'] == $b['filesize'])
						return 0;
					return ($a['filesize'] < $b['filesize']) ? -1 : 1;
				});
			}
			else
				$loaded_attachments = null;
		}
		else
			$loaded_attachments = null;

		// Create a GUID for this post using the tag URI scheme
		$guid = 'tag:' . parse_url($scripturl, PHP_URL_HOST) . ',' . gmdate('Y-m-d', $row['poster_time']) . ':msg=' . $row['id_msg'];

		// Doesn't work as well as news, but it kinda does..
		if ($xml_format == 'rss' || $xml_format == 'rss2')
		{
			// Only one attachment allowed in RSS.
			if ($loaded_attachments !== null)
			{
				$attachment = array_pop($loaded_attachments);
				$enclosure = array(
					'url' => fix_possible_url($scripturl . '?action=dlattach;topic=' . $attachment['topic'] . '.0;attach=' . $attachment['id_attach']),
					'length' => $attachment['filesize'],
					'type' => $attachment['mime_type'],
				);
			}
			else
				$enclosure = null;

			$data[] = array(
				'tag' => 'item',
				'content' => array(
					array(
						'tag' => 'title',
						'content' => $row['subject'],
					),
					array(
						'tag' => 'link',
						'content' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
					),
					array(
						'tag' => 'description',
						'content' => $row['body'],
					),
					array(
						'tag' => 'author',
						'content' => (allowedTo('moderate_forum') || (!empty($row['id_member']) && $row['id_member'] == $user_info['id'])) ? $row['poster_email'] : null,
					),
					array(
						'tag' => 'category',
						'content' => $row['bname'],
					),
					array(
						'tag' => 'comments',
						'content' => $scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
					),
					array(
						'tag' => 'pubDate',
						'content' => gmdate('D, d M Y H:i:s \G\M\T', $row['poster_time']),
					),
					array(
						'tag' => 'guid',
						'content' => $guid,
						'attributes' => array(
							'isPermaLink' => 'false',
						),
					),
					array(
						'tag' => 'enclosure',
						'attributes' => $enclosure,
					),
				),
			);
		}
		elseif ($xml_format == 'atom')
		{
			// Only one attachment allowed
			if (!empty($loaded_attachments))
			{
				$attachment = array_pop($loaded_attachments);
				$enclosure = array(
					'rel' => 'enclosure',
					'href' => fix_possible_url($scripturl . '?action=dlattach;topic=' . $attachment['topic'] . '.0;attach=' . $attachment['id_attach']),
					'length' => $attachment['filesize'],
					'type' => $attachment['mime_type'],
				);
			}
			else
				$enclosure = null;

			$data[] = array(
				'tag' => 'entry',
				'content' => array(
					array(
						'tag' => 'title',
						'content' => $row['subject'],
					),
					array(
						'tag' => 'link',
						'attributes' => array(
							'rel' => 'alternate',
							'type' => 'text/html',
							'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
						),
					),
					array(
						'tag' => 'summary',
						'attributes' => array('type' => 'html'),
						'content' => $row['body'],
					),
					array(
						'tag' => 'category',
						'attributes' => array('term' => $row['bname']),
					),
					array(
						'tag' => 'author',
						'content' => array(
							array(
								'tag' => 'name',
								'content' => $row['poster_name'],
							),
							array(
								'tag' => 'email',
								'content' => (allowedTo('moderate_forum') || (!empty($row['id_member']) && $row['id_member'] == $user_info['id'])) ? $row['poster_email'] : null,
							),
							array(
								'tag' => 'uri',
								'content' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : null,
							),
						),
					),
					array(
						'tag' => 'published',
						'content' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['poster_time']),
					),
					array(
						'tag' => 'updated',
						'content' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', empty($row['modified_time']) ? $row['poster_time'] : $row['modified_time']),
					),
					array(
						'tag' => 'id',
						'content' => $guid,
					),
					array(
						'tag' => 'link',
						'attributes' => $enclosure,
					),
				),
			);
		}
	}
	$smcFunc['db_free_result']($request);

	return $data;
}

/**
 * Get the profile information for member into an array,
 * which will be generated to match the xml_format.
 * @todo refactor.
 *
 * @param $xml_format The XML format. Can be 'atom', 'rss', 'rss2'
 * @return array An array profile data
 */
function getXmlProfile($xml_format)
{
	global $scripturl, $memberContext, $user_profile, $user_info;

	// You must input a valid user....
	if (empty($_GET['u']) || !loadMemberData((int) $_GET['u']))
		return [];

	// Make sure the id is a number and not "I like trying to hack the database".
	$_GET['u'] = (int) $_GET['u'];
	// Load the member's contextual information!
	if (!loadMemberContext($_GET['u']) || !allowedTo('profile_view'))
		return [];

	// Okay, I admit it, I'm lazy.  Stupid $_GET['u'] is long and hard to type.
	$profile = &$memberContext[$_GET['u']];

	// Create a GUID for this member using the tag URI scheme
	$guid = 'tag:' . parse_url($scripturl, PHP_URL_HOST) . ',' . gmdate('Y-m-d', $user_profile[$profile['id']]['date_registered']) . ':member=' . $profile['id'];

	if ($xml_format == 'rss' || $xml_format == 'rss2')
	{
		$data[] = array(
			'tag' => 'item',
			'content' => array(
				array(
					'tag' => 'title',
					'content' => $profile['name'],
				),
				array(
					'tag' => 'link',
					'content' => $scripturl . '?action=profile;u=' . $profile['id'],
				),
				array(
					'tag' => 'description',
					'content' => isset($profile['group']) ? $profile['group'] : '',
				),
				array(
					'tag' => 'comments',
					'content' => $scripturl . '?action=pm;sa=send;u=' . $profile['id'],
				),
				array(
					'tag' => 'pubDate',
					'content' => gmdate('D, d M Y H:i:s \G\M\T', $user_profile[$profile['id']]['date_registered']),
				),
				array(
					'tag' => 'guid',
					'content' => $guid,
					'attributes' => array(
						'isPermaLink' => 'false',
					),
				),
			)
		);
	}
	elseif ($xml_format == 'atom')
	{
		$data[] = array(
			'tag' => 'entry',
			'content' => array(
				array(
					'tag' => 'title',
					'content' => $profile['name'],
				),
				array(
					'tag' => 'link',
					'attributes' => array(
						'rel' => 'alternate',
						'type' => 'text/html',
						'href' => $scripturl . '?action=profile;u=' . $profile['id'],
					),
				),
				array(
					'tag' => 'summary',
					'attributes' => array('type' => 'html'),
					'content' => isset($profile['group']) ? $profile['group'] : '',
				),
				array(
					'tag' => 'author',
					'content' => array(
						array(
							'tag' => 'name',
							'content' => $profile['name'],
						),
						array(
							'tag' => 'email',
							'content' => $profile['show_email'] ? $profile['email'] : null,
						),
						array(
							'tag' => 'uri',
							'content' => !empty($profile['website']['url']) ? $profile['website']['url'] : null,
						),
					),
				),
				array(
					'tag' => 'published',
					'content' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $user_profile[$profile['id']]['date_registered']),
				),
				array(
					'tag' => 'updated',
					'content' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $user_profile[$profile['id']]['last_login']),
				),
				array(
					'tag' => 'id',
					'content' => $guid,
				),
			)
		);
	}

	// Save some memory.
	unset($profile, $memberContext[$_GET['u']]);

	return $data;
}
