<?php
/**
 * String Helpers for StoryBB
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Given an array of strings, combine them with commas, e.g. "X, Y, Z".
 *
 * @param $array Array of strings to implode with commas
 * @return string Combined string
 */
function implode_comma($array)
{
	return implode(', ', $array);
}

function implode_sep($array, $sep)
{
	return implode($sep, $array);
}

function array2js($array) {
	return new \LightnCandy\SafeString(json_encode(array_values($array)));
}

/**
 * Given an array of strings, combine them in a 'more readable' way,
 * e.g. "X and Y", "X, Y and Z"
 *
 * @param $array Array of strings to nicely implode
 * @return string Combined string
 */
function implode_and($array)
{
	global $txt;
	loadLanguage('Who');

	if (count($array) <= 2)
	{
		return implode(' ' . $txt['credits_and'] . ' ', $array);
	}
	else
	{
		$last = array_pop($array);
		// And this should have an Oxford comma because @RaceProUK said so.
		return implode(', ', $array) . ', ' . $txt['credits_and'] . ' ' . $last;
	}
}

function stringhelper_string_json($data) {
	return json_encode((string) $data);
}

function JSEscape($string)
{
	global $scripturl;

	return '\'' . strtr($string, array(
		"\r" => '',
		"\n" => '\\n',
		"\t" => '\\t',
		'\\' => '\\\\',
		'\'' => '\\\'',
		'</' => '<\' + \'/',
		'<script' => '<scri\'+\'pt',
		'<body>' => '<bo\'+\'dy>',
		'<a href' => '<a hr\'+\'ef',
		$scripturl => '\' + smf_scripturl + \'',
	)) . '\'';
}

?>