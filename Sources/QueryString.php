<?php

/**
 * This file does a lot of important stuff.  Mainly, this means it handles
 * the query string, request variables, and session management.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Helper\IP;
use StoryBB\StringLibrary;

/**
 * Clean the request variables - add html entities to GET.
 *
 * What it does:
 * - cleans the request variables (ENV, GET, POST, COOKIE, SERVER) and
 * - makes sure the query string was parsed correctly.
 * - handles the URLs passed by the queryless URLs option.
 * - makes sure, regardless of php.ini, everything has slashes.
 * - sets up $board, $topic, and $scripturl and $_REQUEST['start'].
 * - determines, or rather tries to determine, the client's IP.
 */

function cleanRequest()
{
	global $board, $topic, $boardurl, $scripturl, $modSettings, $smcFunc, $context;

	// Makes it easier to refer to things this way.
	$scripturl = $boardurl . '/index.php';

	// These keys shouldn't be set...ever.
	if (isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS']))
		die('Invalid request variable.');

	// Same goes for numeric keys.
	foreach (array_merge(array_keys($_POST), array_keys($_GET), array_keys($_FILES)) as $key)
		if (is_numeric($key))
			die('Numeric request keys are invalid.');

	// Numeric keys in cookies are less of a problem. Just unset those.
	foreach (array_keys($_COOKIE) as $key)
		if (is_numeric($key))
			unset($_COOKIE[$key]);

	// Get the correct query string.  It may be in an environment variable...
	if (!isset($_SERVER['QUERY_STRING']))
		$_SERVER['QUERY_STRING'] = getenv('QUERY_STRING');

	// It seems that sticking a URL after the query string is mighty common, well, it's evil - don't.
	if (strpos($_SERVER['QUERY_STRING'], 'http') === 0)
	{
		header('HTTP/1.1 400 Bad Request');
		die;
	}

	// Are we going to need to parse the ; out?
	if (strpos(ini_get('arg_separator.input'), ';') === false && !empty($_SERVER['QUERY_STRING']))
	{
		// Get rid of the old one! You don't know where it's been!
		$_GET = [];

		// Was this redirected? If so, get the REDIRECT_QUERY_STRING.
		// Do not urldecode() the querystring.
		$_SERVER['QUERY_STRING'] = substr($_SERVER['QUERY_STRING'], 0, 5) === 'url=/' ? $_SERVER['REDIRECT_QUERY_STRING'] : $_SERVER['QUERY_STRING'];

		// Replace ';' with '&' and '&something&' with '&something=&'.  (this is done for compatibility...)
		parse_str(preg_replace('/&(\w+)(?=&|$)/', '&$1=', strtr($_SERVER['QUERY_STRING'], [';?' => '&', ';' => '&', '%00' => '', "\0" => ''])), $_GET);
	}
	elseif (strpos(ini_get('arg_separator.input'), ';') !== false)
	{
		// Search engines will send action=profile%3Bu=1, which confuses PHP.
		foreach ($_GET as $k => $v)
		{
			if ((string) $v === $v && strpos($k, ';') !== false)
			{
				$temp = explode(';', $v);
				$_GET[$k] = $temp[0];

				for ($i = 1, $n = count($temp); $i < $n; $i++)
				{
					@list ($key, $val) = @explode('=', $temp[$i], 2);
					if (!isset($_GET[$key]))
						$_GET[$key] = $val;
				}
			}

			// This helps a lot with integration!
			if (strpos($k, '?') === 0)
			{
				$_GET[substr($k, 1)] = $v;
				unset($_GET[$k]);
			}
		}
	}

	// There's no query string, but there is a URL... try to get the data from there.
	if (!empty($_SERVER['REQUEST_URI']))
	{
		// Remove the .html, assuming there is one.
		if (substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], '.'), 4) == '.htm')
			$request = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '.'));
		else
			$request = $_SERVER['REQUEST_URI'];

		// Replace 'index.php/a,b,c/d/e,f' with 'a=b,c&d=&e=f' and parse it into $_GET.
		if (strpos($request, basename($scripturl) . '/') !== false)
		{
			parse_str(substr(preg_replace('/&(\w+)(?=&|$)/', '&$1=', strtr(preg_replace('~/([^,/]+),~', '/$1=', substr($request, strpos($request, basename($scripturl)) + strlen(basename($scripturl)))), '/', '&')), 1), $temp);
			$_GET += $temp;
		}
	}

	// Add entities to GET.  This is kinda like the slashes on everything else.
	$_GET = htmlspecialchars__recursive($_GET);

	// Let's not depend on the ini settings... why even have COOKIE in there, anyway?
	$_REQUEST = $_POST + $_GET;

	// @todo this is a compatibility hack to make existing stuff still work.
	if (isset($context['routing']['board']))
	{
		$_REQUEST['board'] = $context['routing']['board'];
	}
	if (isset($context['routing']['topic']))
	{
		$_REQUEST['topic'] = $context['routing']['topic'];
	}
	if (isset($context['routing']['board_slug']))
	{
		$request = $smcFunc['db']->query('', '
			SELECT id_board, slug
			FROM {db_prefix}boards
			WHERE slug = {string:slug}',
			[
				'slug' => $context['routing']['board_slug'],
			]
		);
		if ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$context['routing']['board'] = (int) $row['id_board'];
			$_REQUEST['board'] = $context['routing']['board'];
			if (isset($context['routing']['start']))
			{
				$_REQUEST['start'] = $context['routing']['start'];
			}
		}
		else
		{
			unset ($context['routing']['board']);
			unset ($_REQUEST['board'], $_GET['board'], $_POST['board']);
		}
		$smcFunc['db']->free_result($request);
	}

	// Make sure $board and $topic are numbers.
	if (isset($_REQUEST['board']))
	{
		// Make sure its a string and not something else like an array
		$_REQUEST['board'] = (string) $_REQUEST['board'];

		// Now make absolutely sure it's a number.
		$board = (int) $_REQUEST['board'];
		$_REQUEST['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

		// This is for "Who's Online" because it might come via POST - and it should be an int here.
		$_GET['board'] = $board;
	}
	// Well, $board is going to be a number no matter what.
	else
		$board = 0;

	// We've got topic!
	if (isset($_REQUEST['topic']))
	{
		// Make sure its a string and not something else like an array
		$_REQUEST['topic'] = (string) $_REQUEST['topic'];

		// Slash means old, beta style, formatting.  That's okay though, the link should still work.
		if (strpos($_REQUEST['topic'], '/') !== false)
			list ($_REQUEST['topic'], $_REQUEST['start']) = explode('/', $_REQUEST['topic']);
		// Dots are useful and fun ;).  This is ?topic=1.15.
		elseif (strpos($_REQUEST['topic'], '.') !== false)
			list ($_REQUEST['topic'], $_REQUEST['start']) = explode('.', $_REQUEST['topic']);

		// Topic should always be an integer
		$topic = $_GET['topic'] = $_REQUEST['topic'] = (int) $_REQUEST['topic'];

		// Start could be a lot of things...
		// ... empty ...
		if (empty($_REQUEST['start']))
		{
			$_REQUEST['start'] = 0;
		}
		// ... a simple number ...
		elseif (is_numeric($_REQUEST['start']))
		{
			$_REQUEST['start'] = (int) $_REQUEST['start'];
		}
		// ... or a specific message ...
		elseif (strpos($_REQUEST['start'], 'msg') === 0)
		{
			$virtual_msg = (int) substr($_REQUEST['start'], 3);
			$_REQUEST['start'] = $virtual_msg === 0 ? 0 : 'msg' . $virtual_msg;
		}
		// ... or whatever is new ...
		elseif (strpos($_REQUEST['start'], 'new') === 0)
		{
			$_REQUEST['start'] = 'new';
		}
		// ... or since a certain time ...
		elseif (strpos($_REQUEST['start'], 'from') === 0)
		{
			$timestamp = (int) substr($_REQUEST['start'], 4);
			$_REQUEST['start'] = $timestamp === 0 ? 0 : 'from' . $timestamp;
		}
		// ... or something invalid, in which case we reset it to 0.
		else
			$_REQUEST['start'] = 0;
	}
	else
		$topic = 0;

	// There should be a $_REQUEST['start'], some at least.  If you need to default to other than 0, use $_GET['start'].
	if (empty($_REQUEST['start']) || $_REQUEST['start'] < 0 || (int) $_REQUEST['start'] > 2147473647)
		$_REQUEST['start'] = 0;

	// The action needs to be a string and not an array or anything else
	if (isset($_REQUEST['action']))
		$_REQUEST['action'] = (string) $_REQUEST['action'];
	if (isset($_GET['action']))
		$_GET['action'] = (string) $_GET['action'];

	// Some mail providers like to encode semicolons in activation URLs...
	if (!empty($_REQUEST['action']) && substr($_SERVER['QUERY_STRING'], 0, 18) == 'action=activate%3b')
	{
		header('Location: ' . $scripturl . '?' . str_replace('%3b', ';', $_SERVER['QUERY_STRING']));
		exit;
	}

	// Make sure we have a valid REMOTE_ADDR.
	if (!isset($_SERVER['REMOTE_ADDR']))
	{
		$_SERVER['REMOTE_ADDR'] = '';
		// A new magic variable to indicate we think this is command line.
		$_SERVER['is_cli'] = true;
	}
	// Perhaps we have a IPv6 address.
	elseif (IP::is_valid($_SERVER['REMOTE_ADDR']))
	{
		$_SERVER['REMOTE_ADDR'] = preg_replace('~^::ffff:(\d+\.\d+\.\d+\.\d+)~', '\1', $_SERVER['REMOTE_ADDR']);
	}

	// Try to calculate their most likely IP for those people behind proxies (And the like).
	$_SERVER['BAN_CHECK_IP'] = $_SERVER['REMOTE_ADDR'];

	// If we haven't specified how to handle Reverse Proxy IP headers, lets do what we always used to do.
	if (!isset($modSettings['proxy_ip_header']))
		$modSettings['proxy_ip_header'] = 'autodetect';

	// Which headers are we going to check for Reverse Proxy IP headers?
	if ($modSettings['proxy_ip_header'] == 'disabled')
		$reverseIPheaders = [];
	elseif ($modSettings['proxy_ip_header'] == 'autodetect')
		$reverseIPheaders = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP'];
	else
		$reverseIPheaders = [$modSettings['proxy_ip_header']];

	// Find the user's IP address. (but don't let it give you 'unknown'!)
	foreach ($reverseIPheaders as $proxyIPheader)
	{
		// Ignore if this is not set.
		if (!isset($_SERVER[$proxyIPheader]))
			continue;

		if (!empty($modSettings['proxy_ip_servers']))
		{
			foreach (explode(',', $modSettings['proxy_ip_servers']) as $proxy)
				if ($proxy == $_SERVER['REMOTE_ADDR'] || matchIPtoCIDR($_SERVER['REMOTE_ADDR'], $proxy))
					continue;
		}

		// If there are commas, get the last one.. probably.
		if (strpos($_SERVER[$proxyIPheader], ',') !== false)
		{
			$ips = array_reverse(explode(', ', $_SERVER[$proxyIPheader]));

			// Go through each IP...
			foreach ($ips as $i => $ip)
			{
				// Make sure it's in a valid range...
				if (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)~', $ip) != 0 && preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)~', $_SERVER['REMOTE_ADDR']) == 0)
				{
					if (!IP::is_valid_ipv6($_SERVER[$proxyIPheader]) || preg_match('~::ffff:\d+\.\d+\.\d+\.\d+~', $_SERVER[$proxyIPheader]) !== 0)
					{
						$_SERVER[$proxyIPheader] = preg_replace('~^::ffff:(\d+\.\d+\.\d+\.\d+)~', '\1', $_SERVER[$proxyIPheader]);

						// Just incase we have a legacy IPv4 address.
						// @ TODO: Convert to IPv6.
						if (preg_match('~^((([1]?\d)?\d|2[0-4]\d|25[0-5])\.){3}(([1]?\d)?\d|2[0-4]\d|25[0-5])$~', $_SERVER[$proxyIPheader]) === 0)
							continue;
					}

					continue;
				}

				// Otherwise, we've got an IP!
				$_SERVER['BAN_CHECK_IP'] = trim($ip);
				break;
			}
		}
		// Otherwise just use the only one.
		elseif (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)~', $_SERVER[$proxyIPheader]) == 0 || preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)~', $_SERVER['REMOTE_ADDR']) != 0)
			$_SERVER['BAN_CHECK_IP'] = $_SERVER[$proxyIPheader];
		elseif (!IP::is_valid_ipv6($_SERVER[$proxyIPheader]) || preg_match('~::ffff:\d+\.\d+\.\d+\.\d+~', $_SERVER[$proxyIPheader]) !== 0)
		{
			$_SERVER[$proxyIPheader] = preg_replace('~^::ffff:(\d+\.\d+\.\d+\.\d+)~', '\1', $_SERVER[$proxyIPheader]);

			// Just incase we have a legacy IPv4 address.
			// @ TODO: Convert to IPv6.
			if (preg_match('~^((([1]?\d)?\d|2[0-4]\d|25[0-5])\.){3}(([1]?\d)?\d|2[0-4]\d|25[0-5])$~', $_SERVER[$proxyIPheader]) === 0)
				continue;
		}
	}

	// Make sure we know the URL of the current request.
	if (empty($_SERVER['REQUEST_URI']))
		$_SERVER['REQUEST_URL'] = $scripturl . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
	elseif (preg_match('~^([^/]+//[^/]+)~', $scripturl, $match) == 1)
		$_SERVER['REQUEST_URL'] = $match[1] . $_SERVER['REQUEST_URI'];
	else
		$_SERVER['REQUEST_URL'] = $_SERVER['REQUEST_URI'];

	// And make sure HTTP_USER_AGENT is set.
	$_SERVER['HTTP_USER_AGENT'] = isset($_SERVER['HTTP_USER_AGENT']) ? StringLibrary::escape($smcFunc['db']->unescape_string($_SERVER['HTTP_USER_AGENT']), ENT_QUOTES) : '';

	// Some final checking.
	if (!IP::is_valid($_SERVER['BAN_CHECK_IP']))
		$_SERVER['BAN_CHECK_IP'] = '';
	if ($_SERVER['REMOTE_ADDR'] == 'unknown')
		$_SERVER['REMOTE_ADDR'] = '';
}

/**
 * Converts IPv6s to numbers.  This makes ban checks much easier.
 *
 * @param string $ip The IP address to be converted
 * @return array An array containing the expanded IP parts
 */
function convertIPv6toInts($ip)
{
	static $expanded = [];

	// Check if we have done this already.
	if (isset($expanded[$ip]))
		return $expanded[$ip];

	// Expand the IP out.
	$expanded_ip = explode(':', expandIPv6($ip));

	$new_ip = [];
	foreach ($expanded_ip as $int)
		$new_ip[] = hexdec($int);

	// Save this incase of repeated use.
	$expanded[$ip] = $new_ip;

	return $expanded[$ip];
}

/**
 * Expands a IPv6 address to its full form.
 *
 * @param string $addr The IPv6 address
 * @param bool $strict_check Whether to check the length of the expanded address for compliance
 * @return string|bool The expanded IPv6 address or false if $strict_check is true and the result isn't valid
 */
function expandIPv6($addr, $strict_check = true)
{
	static $converted = [];

	// Check if we have done this already.
	if (isset($converted[$addr]))
		return $converted[$addr];

	// Check if there are segments missing, insert if necessary.
	if (strpos($addr, '::') !== false)
	{
		$part = explode('::', $addr);
		$part[0] = explode(':', $part[0]);
		$part[1] = explode(':', $part[1]);
		$missing = [];

		for ($i = 0, $n = (8 - (count($part[0]) + count($part[1]))); $i < $n; $i++)
			array_push($missing, '0000');

		$part = array_merge($part[0], $missing, $part[1]);
	}
	else
		$part = explode(':', $addr);

	// Pad each segment until it has 4 digits.
	foreach ($part as &$p)
		while (strlen($p) < 4)
			$p = '0' . $p;

	unset($p);

	// Join segments.
	$result = implode(':', $part);

	// Save this incase of repeated use.
	$converted[$addr] = $result;

	// Quick check to make sure the length is as expected.
	if (!$strict_check || strlen($result) == 39)
		return $result;
	else
		return false;
}


/**
 * Detect if a IP is in a CIDR address
 * - returns true or false
 *
 * @param string $ip_address IP address to check
 * @param string $cidr_address CIDR address to verify
 * @return bool Whether the IP matches the CIDR
*/
function matchIPtoCIDR($ip_address, $cidr_address)
{
	list ($cidr_network, $cidr_subnetmask) = preg_split('/', $cidr_address);
	return (ip2long($ip_address) & (~((1 << (32 - $cidr_subnetmask)) - 1))) == ip2long($cidr_network);
}

/**
 * Adds slashes to the array/variable.
 * What it does:
 * - returns the var, as an array or string, with escapes as required.
 * - importantly escapes all keys and values!
 * - calls itself recursively if necessary.
 *
 * @param array|string $var A string or array of strings to escape
 * @deprecated Is this even called?
 * @return array|string The escaped string or array of escaped strings
 */
function escapestring__recursive($var)
{
	global $smcFunc;

	if (!is_array($var))
		return $smcFunc['db']->escape_string($var);

	// Reindex the array with slashes.
	$new_var = [];

	// Add slashes to every element, even the indexes!
	foreach ($var as $k => $v)
		$new_var[$smcFunc['db']->escape_string($k)] = escapestring__recursive($v);

	return $new_var;
}

/**
 * Adds html entities to the array/variable.  Uses two underscores to guard against overloading.
 * What it does:
 * - adds entities (&quot;, &lt;, &gt;) to the array or string var.
 * - importantly, does not effect keys, only values.
 * - calls itself recursively if necessary.
 *
 * @param array|string $var The string or array of strings to add entites to
 * @param int $level Which level we're at within the array (if called recursively)
 * @return array|string The string or array of strings with entities added
 */
function htmlspecialchars__recursive($var, $level = 0)
{
	if (!is_array($var))
		return StringLibrary::escape($var, ENT_QUOTES);

	// Add the htmlspecialchars to every element.
	foreach ($var as $k => $v)
		$var[$k] = $level > 25 ? null : htmlspecialchars__recursive($v, $level + 1);

	return $var;
}

/**
 * Removes url stuff from the array/variable.  Uses two underscores to guard against overloading.
 * What it does:
 * - takes off url encoding (%20, etc.) from the array or string var.
 * - importantly, does it to keys too!
 * - calls itself recursively if there are any sub arrays.
 *
 * @param array|string $var The string or array of strings to decode
 * @param int $level Which level we're at within the array (if called recursively)
 * @return array|string The decoded string or array of decoded strings
 */
function urldecode__recursive($var, $level = 0)
{
	if (!is_array($var))
		return urldecode($var);

	// Reindex the array...
	$new_var = [];

	// Add the htmlspecialchars to every element.
	foreach ($var as $k => $v)
		$new_var[urldecode($k)] = $level > 25 ? null : urldecode__recursive($v, $level + 1);

	return $new_var;
}
/**
 * Unescapes any array or variable.  Uses two underscores to guard against overloading.
 * What it does:
 * - unescapes, recursively, from the array or string var.
 * - effects both keys and values of arrays.
 * - calls itself recursively to handle arrays of arrays.
 *
 * @param array|string $var The string or array of strings to unescape
 * @return array|string The unescaped string or array of unescaped strings
 */
function unescapestring__recursive($var)
{
	global $smcFunc;

	if (!is_array($var))
		return $smcFunc['db']->unescape_string($var);

	// Reindex the array without slashes, this time.
	$new_var = [];

	// Strip the slashes from every element.
	foreach ($var as $k => $v)
		$new_var[$smcFunc['db']->unescape_string($k)] = unescapestring__recursive($v);

	return $new_var;
}

/**
 * Remove slashes recursively.  Uses two underscores to guard against overloading.
 * What it does:
 * - removes slashes, recursively, from the array or string var.
 * - effects both keys and values of arrays.
 * - calls itself recursively to handle arrays of arrays.
 *
 * @param array|string $var The string or array of strings to strip slashes from
 * @param int $level = 0 What level we're at within the array (if called recursively)
 * @return array|string The string or array of strings with slashes stripped
 */
function stripslashes__recursive($var, $level = 0)
{
	if (!is_array($var))
		return stripslashes($var);

	// Reindex the array without slashes, this time.
	$new_var = [];

	// Strip the slashes from every element.
	foreach ($var as $k => $v)
		$new_var[stripslashes($k)] = $level > 25 ? null : stripslashes__recursive($v, $level + 1);

	return $new_var;
}

/**
 * Trim a string including the HTML space, character 160.  Uses two underscores to guard against overloading.
 * What it does:
 * - trims a string or an the var array using html characters as well.
 * - does not effect keys, only values.
 * - may call itself recursively if needed.
 *
 * @param array|string $var The string or array of strings to trim
 * @param int $level = 0 How deep we're at within the array (if called recursively)
 * @return array|string The trimmed string or array of trimmed strings
 */
function htmltrim__recursive($var, $level = 0)
{
	// Remove spaces (32), tabs (9), returns (13, 10, and 11), nulls (0), and hard spaces. (160)
	if (!is_array($var))
	{
		return StringLibrary::htmltrim($var);
	}

	// Go through all the elements and remove the whitespace.
	foreach ($var as $k => $v)
	{
		$var[$k] = $level > 25 ? null : htmltrim__recursive($v, $level + 1);
	}

	return $var;
}

/**
 * Clean up the XML to make sure it doesn't contain invalid characters.
 * What it does:
 * - removes invalid XML characters to assure the input string being
 * - parsed properly.
 *
 * @param string $string The string to clean
 * @return string The cleaned string
 */
function cleanXml($string)
{
	// https://www.w3.org/TR/2000/REC-xml-20001006#NT-Char
	return preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x19\x{FFFE}\x{FFFF}]~u', '', $string);
}

/**
 * Escapes (replaces) characters in strings to make them safe for use in javascript
 *
 * @param string $string The string to escape
 * @return string The escaped string
 */
function JavaScriptEscape($string)
{
	return StoryBB\Template\Helper\Text::jsEscape($string);
}
