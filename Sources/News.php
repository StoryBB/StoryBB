<?php

/**
 * This file contains the files necessary to display news as an XML feed.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\App;
use StoryBB\ClassManager;
use StoryBB\Helper\Parser;
use StoryBB\StringLibrary;
use StoryBB\Template;

/**
 * Outputs xml data representing recent information or a profile.
 * Can be passed subactions which decide what is output:
 *  'recent' for recent posts,
 *  'news' for news topics,
 * Accessed via ?action=.xml.
 *
 * @uses Stats language file.
 */
function ShowXmlFeed()
{
	global $board, $board_info, $context, $scripturl, $boardurl, $txt, $modSettings, $user_info;
	global $query_this_board, $smcFunc, $settings;

	// If it's not enabled, die.
	if (empty($modSettings['xmlnews_enable']))
		obExit(false);

	loadLanguage('Stats');

	// Start out with some defaults for the feed.
	$feed_template = [
		'title' => $context['forum_name'],
		'description' => str_replace('{forum_name}', $context['forum_name'], $txt['xml_rss_desc']),
		'source' => $scripturl,
		'author' => $context['forum_name'],
		'rights' => "\xC2\xA9 " . date('Y') . ' ' . $context['forum_name'],
		'icon' => !empty($settings['og_image']) ? $settings['og_image'] : '',
		'language' => !empty($txt['lang_locale']) ? str_replace("_", "-", substr($txt['lang_locale'], 0, strcspn($txt['lang_locale'], "."))) : 'en',
		'generator' => [
			'name' => 'StoryBB',
			'version' => App::SOFTWARE_VERSION,
			'year' => App::SOFTWARE_YEAR,
			'url' => 'https://storybb.org/',
		],
		'items' => [],
	];

	// Default to latest 5.  No more than 255, please.
	$_GET['limit'] = empty($_GET['limit']) || (int) $_GET['limit'] < 1 ? 5 : min((int) $_GET['limit'], 255);

	// Show in rss or proprietary format?
	$xml_format = isset($_GET['type']) && in_array($_GET['type'], ['rss2', 'atom']) ? $_GET['type'] : 'rss2';

	$subActions = [];

	foreach (ClassManager::get_classes_implementing('StoryBB\\Feed\\Feedable') as $class)
	{
		$subActions[$class::get_identifier()] = $class;
	}

	if (empty($_GET['sa']) || !isset($subActions[$_GET['sa']]))
	{
		$_GET['sa'] = isset($subActions['recent']) ? 'recent' : array_keys($subactions)[0];
	}

	$feedobj = new $subActions[$_GET['sa']]($feed_template, $_GET['limit']);
	$context['feed'] = $feedobj->get_data();

	// These can't be empty
	foreach (['title', 'description', 'source'] as $mkey)
	{
		$context['feed'][$mkey] = !empty($context['feed'][$mkey]) ? $context['feed'][$mkey] : $feed_template[$mkey];
	}

	$url_parts = [];
	$possible_vars = array_merge(['action', 'sa', 'type'], $feedobj->get_vars(), ['limit']);
	foreach ($_REQUEST as $var => $val)
	{
		if (in_array($var, $possible_vars))
		{
			$url_parts[] = $var . '=' . (is_array($val) ? implode(',', $val) : $val);
		}
	}
	$context['feed']['url'] = $scripturl . (!empty($url_parts) ? '?' . implode(';', $url_parts) : '');

	Template::set_layout('raw');
	Template::add_helper([
		'cdata' => function($item) {
			return new \LightnCandy\SafeString(cdata_parse($item));
		}
	]);

	// Are we outputting an rss feed or one with more information?
	if ($xml_format == 'rss2')
	{
		header('Content-Type: application/rss+xml; charset=UTF-8', true);
		$context['sub_template'] = 'xml_feed_rss';
	}
	elseif ($xml_format == 'atom')
	{
		header('Content-Type: application/atom+xml; charset=UTF-8', true);
		$context['sub_template'] = 'xml_feed_atom';
	}
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
	// Do we even need to do this?
	if (strpbrk($data, '<>&') == false && $force !== true)
		return $data;

	$cdata = '<![CDATA[';

	for ($pos = 0, $n = StringLibrary::strlen($data); $pos < $n; null)
	{
		$positions = [
			StringLibrary::strpos($data, '&', $pos),
			StringLibrary::strpos($data, ']', $pos),
		];
		if ($ns != '')
			$positions[] = StringLibrary::strpos($data, '<', $pos);
		foreach ($positions as $k => $dummy)
		{
			if ($dummy === false)
				unset($positions[$k]);
		}

		$old = $pos;
		$pos = empty($positions) ? $n : min($positions);

		if ($pos - $old > 0)
			$cdata .= StringLibrary::substr($data, $old, $pos - $old);
		if ($pos >= $n)
			break;

		if (StringLibrary::substr($data, $pos, 1) == '<')
		{
			$pos2 = StringLibrary::strpos($data, '>', $pos);
			if ($pos2 === false)
				$pos2 = $n;
			if (StringLibrary::substr($data, $pos + 1, 1) == '/')
				$cdata .= ']]></' . $ns . ':' . StringLibrary::substr($data, $pos + 2, $pos2 - $pos - 1) . '<![CDATA[';
			else
				$cdata .= ']]><' . $ns . ':' . StringLibrary::substr($data, $pos + 1, $pos2 - $pos) . '<![CDATA[';
			$pos = $pos2 + 1;
		}
		elseif (StringLibrary::substr($data, $pos, 1) == ']')
		{
			$cdata .= ']]>&#093;<![CDATA[';
			$pos++;
		}
		elseif (StringLibrary::substr($data, $pos, 1) == '&')
		{
			$pos2 = StringLibrary::strpos($data, ';', $pos);
			if ($pos2 === false)
				$pos2 = $n;
			$ent = StringLibrary::substr($data, $pos + 1, $pos2 - $pos - 1);

			if (StringLibrary::substr($data, $pos + 1, 1) == '#')
				$cdata .= ']]>' . StringLibrary::substr($data, $pos, $pos2 - $pos + 1) . '<![CDATA[';
			elseif (in_array($ent, ['amp', 'lt', 'gt', 'quot']))
				$cdata .= ']]>' . StringLibrary::substr($data, $pos, $pos2 - $pos + 1) . '<![CDATA[';

			$pos = $pos2 + 1;
		}
	}

	$cdata .= ']]>';

	return strtr($cdata, ['<![CDATA[]]>' => '']);
}
