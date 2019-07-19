<?php
/**
 * This file contains core of the code for Mentions
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

/**
 * This really is a pseudo class, I couldn't justify having instance of it
 * while mentioning so I just made every method static
 */
class Mentions
{
	/** @var $char Defines the character used to indicate a mention, e.g. @user */
	protected static $char = '@';

	/**
	 * Returns mentions for a specific content
	 *
	 * @static
	 * @access public
	 * @param string $content_type The content type
	 * @param int $content_id The ID of the desired content
	 * @param array $members Whether to limit to a specific sect of members
	 * @return array An array of arrays containing info about each member mentioned
	 */
	public static function getMentionsByContent($content_type, $content_id, array $members = [])
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT mem.id_member, mem.real_name, mem.email_address, mem.id_group, mem.additional_groups,
				mem.lngfile, ment.id_member AS id_mentioned_by, ment.real_name AS mentioned_by_name,
				m.id_character AS dest_chr, chars.character_name AS dest_chr_name, chars.is_main AS dest_is_main,
				m.mentioned_chr AS source_chr, chars_ment.character_name AS source_chr_name, chars_ment.is_main AS source_is_main,
				chars.retired
			FROM {db_prefix}mentions AS m
				INNER JOIN {db_prefix}characters AS chars ON (chars.id_character = m.mentioned_chr)
				INNER JOIN {db_prefix}characters AS chars_ment ON (chars_ment.id_character = m.id_character)
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_mentioned)
				INNER JOIN {db_prefix}members AS ment ON (ment.id_member = m.id_member)
			WHERE content_type = {string:type}
				AND content_id = {int:id}' . (!empty($members) ? '
				AND chars.id_character IN ({array_int:members})' : ''),
			array(
				'type' => $content_type,
				'id' => $content_id,
				'members' => $members,
			)
		);
		$members = [];
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$members[$row['dest_chr']] = array(
				'id_member' => $row['id_member'],
				'dest_chr' => $row['dest_chr'],
				'dest_character_name' => $row['dest_chr_name'],
				'dest_is_main' => $row['dest_is_main'],
				'retired_chr' => $row['retired'],
				'real_name' => $row['real_name'],
				'email_address' => $row['email_address'],
				'groups' => array_unique(array_merge(array($row['id_group']), explode(',', $row['additional_groups']))),
				'mentioned_by' => array(
					'id' => $row['id_mentioned_by'],
					'name' => $row['mentioned_by_name'],
					'source_chr' => $row['source_chr'],
					'source_chr_name' => $row['source_chr_name'],
					'source_is_main' => $row['source_is_main'],
				),
				'lngfile' => $row['lngfile'],
			);
		$smcFunc['db_free_result']($request);

		return $members;
	}

	/**
	 * Inserts mentioned members
	 *
	 * @static
	 * @access public
	 * @param string $content_type The content type
	 * @param int $content_id The ID of the specified content
	 * @param array $members An array of members who have been mentioned
	 * @param int $id_member The ID of the member who mentioned them
	 * @param int $id_character The ID of the character who mentioned them
	 */
	public static function insertMentions($content_type, $content_id, array $members, $id_member, $id_character)
	{
		global $smcFunc;

		call_integration_hook('mention_insert_' . $content_type, array($content_id, &$members));

		foreach ($members as $member)
			$smcFunc['db_insert']('ignore',
				'{db_prefix}mentions',
				array('content_id' => 'int', 'content_type' => 'string', 'id_member' => 'int', 'id_mentioned' => 'int', 'id_character' => 'int', 'mentioned_chr' => 'int', 'time' => 'int'),
				array((int) $content_id, $content_type, $id_member, $member['id_member'], $id_character, $member['id_character'], time()),
				array('content_id', 'content_type', 'id_mentioned')
			);
	}

	/**
	 * Gets appropriate mentions replaced in the body
	 *
	 * @static
	 * @access public
	 * @param string $body The text to look for mentions in
	 * @param array $members An array of arrays containing info about members (each should have 'id' and 'member')
	 * @return string The body with mentions replaced
	 */
	public static function getBody($body, array $members)
	{
		foreach ($members as $member)
		{
			if ($member['is_main'])
				$body = str_ireplace(static::$char . $member['character_name'], '[member=' . $member['id_character'] . ']' . $member['character_name'] . '[/member]', $body);
			else
				$body = str_ireplace(static::$char . $member['character_name'], '[character=' . $member['id_character'] . ']' . $member['character_name'] . '[/character]', $body);
		}

		return $body;
	}

	/**
	 * Takes a piece of text and finds all the mentioned members in it
	 *
	 * @static
	 * @access public
	 * @param string $body The body to get mentions from
	 * @return array An array of arrays containing members who were mentioned (each has 'id_member' and 'real_name')
	 */
	public static function getMentionedMembers($body)
	{
		global $smcFunc;

		$possible_names = self::getPossibleMentions($body);

		if (empty($possible_names) || !allowedTo('mention'))
			return [];

		$request = $smcFunc['db_query']('', '
			SELECT chars.id_character, chars.id_member, chars.character_name,
				chars.is_main, chars.retired
			FROM {db_prefix}characters AS chars
			WHERE character_name IN ({array_string:names})
			ORDER BY LENGTH(character_name) DESC
			LIMIT {int:count}',
			array(
				'names' => $possible_names,
				'count' => count($possible_names),
			)
		);
		$members = [];
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (stripos($body, static::$char . $row['character_name']) === false)
				continue;

			$members[$row['id_character']] = $row;
		}
		$smcFunc['db_free_result']($request);

		return $members;
	}

	/**
	 * Parses a body in order to see if there are any mentions, returns possible mention names
	 *
	 * Names are tagged by "@<username>" format in post, but they can contain
	 * any type of character up to 60 characters length. So we extract, starting from @
	 * up to 60 characters in length (or if we encounter a line break) and make
	 * several combination of strings after splitting it by anything that's not a word and join
	 * by having the first word, first and second word, first, second and third word and so on and
	 * search every name.
	 *
	 * One potential problem with this is something like "@Admin Space" can match
	 * "Admin Space" as well as "Admin", so we sort by length in descending order.
	 * One disadvantage of this is that we can only match by one column, hence I've chosen
	 * real_name since it's the most obvious.
	 *
	 * If there's an @ symbol within the name, it is counted in the ongoing string and a new
	 * combination string is started from it as well in order to account for all the possibilities.
	 * This makes the @ symbol to not be required to be escaped
	 *
	 * @static
	 * @access protected
	 * @param string $body The text to look for mentions in
	 * @return array An array of names of members who have been mentioned
	 */
	protected static function getPossibleMentions($body)
	{
		global $smcFunc;

		// preparse code does a few things which might mess with our parsing
		$body = htmlspecialchars_decode(preg_replace('~<br\s*/?\>~', "\n", str_replace('&nbsp;', ' ', $body)), ENT_QUOTES);

		// Remove quotes, we don't want to get double mentions.
		while (preg_match('~\[quote[^\]]*\](.+?)\[\/quote\]~s', $body))
			$body = preg_replace('~\[quote[^\]]*\](.+?)\[\/quote\]~s', '', $body);

		$matches = [];
		$string = str_split($body);
		$depth = 0;
		foreach ($string as $k => $char)
		{
			if ($char == static::$char && ($k == 0 || trim($string[$k - 1]) == ''))
			{
				$depth++;
				$matches[] = [];
			}
			elseif ($char == "\n")
				$depth = 0;

			for ($i = $depth; $i > 0; $i--)
			{
				if (count($matches[count($matches) - $i]) > 60)
				{
					$depth--;
					continue;
				}
				$matches[count($matches) - $i][] = $char;
			}
		}

		foreach ($matches as $k => $match)
			$matches[$k] = substr(implode('', $match), 1);

		// Names can have spaces, other breaks, or they can't...we try to match every possible
		// combination.
		$names = [];
		foreach ($matches as $match)
		{
			$match = preg_split('/([^\w])/', $match, -1, PREG_SPLIT_DELIM_CAPTURE);
			$count = count($match);
			
			for ($i = 1; $i <= $count; $i++)
				$names[] = $smcFunc['htmlspecialchars']($smcFunc['htmltrim'](implode('', array_slice($match, 0, $i))));
		}

		$names = array_unique($names);

		return $names;
	}
}
