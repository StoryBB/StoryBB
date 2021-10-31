<?php

/**
 * Parse content according to its bbc and smiley content.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

use StoryBB\Container;
use StoryBB\Helper\Bbcode\AbstractParser;
use StoryBB\Helper\TLD;
use StoryBB\Hook\Mutatable;
use StoryBB\StringLibrary;

/**
 * Parse content according to its bbc and smiley content.
 */
class Parser
{
	/**
	 * Parse bulletin board code in a string, as well as smileys optionally.
	 *
	 * - only parses bbc tags which are not disabled in disabledBBC.
	 * - handles basic HTML, if enablePostHTML is on.
	 * - caches the from/to replace regular expressions so as not to reload them every time a string is parsed.
	 * - only parses smileys if smileys is true.
	 * - does nothing if the enableBBC setting is off.
	 * - uses the cache_id as a unique identifier to facilitate any caching it may do.
	 *  -returns the modified message.
	 *
	 * @param string $message The message
	 * @param bool $smileys Whether to parse smileys as well
	 * @param string $cache_id The cache ID
	 * @param array $parse_tags If set, only parses these tags rather than all of them
	 * @return string The parsed message
	 */
	public static function parse_bbc(string $message, $smileys = true, $cache_id = '', $parse_tags = [])
	{
		global $txt, $scripturl, $context, $modSettings, $user_info, $sourcedir;
		static $bbc_codes = [], $itemcodes = [], $no_autolink_tags = [];
		static $disabled;

		// Don't waste cycles
		if ($message === '')
			return '';

		// Clean up any cut/paste issues we may have
		$message = self::sanitizeMSCutPaste($message);

		// If the load average is too high, don't parse the BBC.
		if (!empty($context['load_average']) && !empty($modSettings['loadavg_bbc']) && $context['load_average'] >= $modSettings['loadavg_bbc'])
		{
			$context['disabled_parse_bbc'] = true;
			return $message;
		}

		if ($smileys !== null && ($smileys == '1' || $smileys == '0'))
			$smileys = (bool) $smileys;

		if (empty($modSettings['enableBBC']))
		{
			if ($smileys === true)
				self::parse_smileys($message);

			return $message;
		}

		// If we are not doing every tag then we don't cache this run.
		if (!empty($parse_tags) && !empty($bbc_codes))
		{
			$temp_bbc = $bbc_codes;
			$bbc_codes = [];
		}

		// Ensure $modSettings['tld_regex'] contains a valid regex for the autolinker
		if (!empty($modSettings['autoLinkUrls']) && empty($modSettings['tld_regex']))
			TLD::set_tld_regex(true);

		// Allow mods access before entering the main parse_bbc loop
		call_integration_hook('integrate_pre_parsebbc', [&$message, &$smileys, &$cache_id, &$parse_tags]);

		// Sift out the bbc for a performance improvement.
		if (empty($bbc_codes) || !empty($parse_tags))
		{
			if ($txt === null)
			{
				loadLanguage('General');
			}

			if (!empty($modSettings['disabledBBC']))
			{
				$disabled = [];

				$temp = explode(',', strtolower($modSettings['disabledBBC']));

				foreach ($temp as $tag)
					$disabled[trim($tag)] = true;
			}

			[$codes, $no_autolink_tags] = AbstractParser::bbcode_definitions();

			// So the parser won't skip them.
			$itemcodes = [
				'*' => 'disc',
				'@' => 'disc',
				'+' => 'square',
				'x' => 'square',
				'#' => 'square',
				'o' => 'circle',
				'O' => 'circle',
				'0' => 'circle',
			];
			if (!isset($disabled['li']) && !isset($disabled['list']))
			{
				foreach (array_keys($itemcodes) as $c)
					$bbc_codes[$c] = [];
			}

			foreach ($codes as $code)
			{
				// Make it easier to process parameters later
				if (!empty($code['parameters']))
					ksort($code['parameters'], SORT_STRING);

				// If we are not doing every tag only do ones we are interested in.
				if (empty($parse_tags) || in_array($code['tag'], $parse_tags))
					$bbc_codes[substr($code['tag'], 0, 1)][] = $code;
			}
			$codes = null;
		}

		// Shall we take the time to cache this?
		if ($cache_id != '' && !empty($modSettings['cache_enable']) && (($modSettings['cache_enable'] >= 2 && isset($message[1000])) || isset($message[2400])) && empty($parse_tags))
		{
			// It's likely this will change if the message is modified.
			$cache_key = 'parse:' . $cache_id . '-' . md5(md5($message) . '-' . $smileys . (empty($disabled) ? '' : implode(',', array_keys($disabled))) . json_encode($context['browser']) . $txt['lang_locale'] . $user_info['time_offset'] . $user_info['time_format']);

			if (($temp = cache_get_data($cache_key, 240)) != null)
				return $temp;

			$cache_t = microtime(true);
		}

		$open_tags = [];
		$message = strtr($message, ["\n" => '<br>']);

		$alltags = [];
		foreach ($bbc_codes as $section) {
			foreach ($section as $code) {
				$alltags[] = $code['tag'];
			}
		}
		$alltags_regex = '\b' . implode("\b|\b", array_unique($alltags)) . '\b';

		$pos = -1;
		while ($pos !== false)
		{
			$last_pos = isset($last_pos) ? max($pos, $last_pos) : $pos;
			preg_match('~\[/?(?=' . $alltags_regex . ')~i', $message, $matches, PREG_OFFSET_CAPTURE, $pos + 1);
			$pos = isset($matches[0][1]) ? $matches[0][1] : false;

			// Failsafe.
			if ($pos === false || $last_pos > $pos)
				$pos = strlen($message) + 1;

			// Can't have a one letter smiley, URL, or email! (sorry.)
			if ($last_pos < $pos - 1)
			{
				// Make sure the $last_pos is not negative.
				$last_pos = max($last_pos, 0);

				// Pick a block of data to do some raw fixing on.
				$data = substr($message, $last_pos, $pos - $last_pos);

				// Take care of some HTML!
				if (!empty($modSettings['enablePostHTML']) && strpos($data, '&lt;') !== false)
				{
					$data = preg_replace('~&lt;a\s+href=((?:&quot;)?)((?:https?://|ftps?://|mailto:|tel:)\S+?)\\1&gt;(.*?)&lt;/a&gt;~i', '[url=&quot;$2&quot;]$3[/url]', $data);

					// <br> should be empty.
					$empty_tags = ['br', 'hr'];
					foreach ($empty_tags as $tag)
						$data = str_replace(['&lt;' . $tag . '&gt;', '&lt;' . $tag . '/&gt;', '&lt;' . $tag . ' /&gt;'], '<' . $tag . '>', $data);

					// b, u, i, s, pre... basic tags.
					$closable_tags = ['b', 'u', 'i', 's', 'em', 'ins', 'del', 'pre', 'blockquote', 'strong'];
					foreach ($closable_tags as $tag)
					{
						$diff = substr_count($data, '&lt;' . $tag . '&gt;') - substr_count($data, '&lt;/' . $tag . '&gt;');
						$data = strtr($data, ['&lt;' . $tag . '&gt;' => '<' . $tag . '>', '&lt;/' . $tag . '&gt;' => '</' . $tag . '>']);

						if ($diff > 0)
							$data = substr($data, 0, -1) . str_repeat('</' . $tag . '>', $diff) . substr($data, -1);
					}

					// Do <img ...> - with security... action= -> action-.
					preg_match_all('~&lt;img\s+src=((?:&quot;)?)((?:https?://|ftps?://)\S+?)\\1(?:\s+alt=(&quot;.*?&quot;|\S*?))?(?:\s?/)?&gt;~i', $data, $matches, PREG_PATTERN_ORDER);
					if (!empty($matches[0]))
					{
						$replaces = [];
						foreach ($matches[2] as $match => $imgtag)
						{
							$alt = empty($matches[3][$match]) ? '' : ' alt=' . preg_replace('~^&quot;|&quot;$~', '', $matches[3][$match]);

							// Check if the image is larger than allowed.
							if (!empty($modSettings['max_image_width']) && !empty($modSettings['max_image_height']))
							{
								list ($width, $height) = url_image_size($imgtag);

								if (!empty($modSettings['max_image_width']) && $width > $modSettings['max_image_width'])
								{
									$height = (int) (($modSettings['max_image_width'] * $height) / $width);
									$width = $modSettings['max_image_width'];
								}

								if (!empty($modSettings['max_image_height']) && $height > $modSettings['max_image_height'])
								{
									$width = (int) (($modSettings['max_image_height'] * $width) / $height);
									$height = $modSettings['max_image_height'];
								}

								// Set the new image tag.
								$replaces[$matches[0][$match]] = '[img width=' . $width . ' height=' . $height . $alt . ']' . $imgtag . '[/img]';
							}
							else
								$replaces[$matches[0][$match]] = '[img' . $alt . ']' . $imgtag . '[/img]';
						}

						$data = strtr($data, $replaces);
					}
				}

				if (!empty($modSettings['autoLinkUrls']))
				{
					// Are we inside tags that should be auto linked?
					$no_autolink_area = false;
					if (!empty($open_tags))
					{
						foreach ($open_tags as $open_tag)
							if (in_array($open_tag['tag'], $no_autolink_tags))
								$no_autolink_area = true;
					}

					// Don't go backwards.
					// @todo Don't think is the real solution....
					$lastAutoPos = isset($lastAutoPos) ? $lastAutoPos : 0;
					if ($pos < $lastAutoPos)
						$no_autolink_area = true;
					$lastAutoPos = $pos;

					if (!$no_autolink_area)
					{
						// Parse any URLs
						if (!isset($disabled['url']) && strpos($data, '[url') === false)
						{
							$url_regex = '
							(?:
								# IRIs with a scheme (or at least an opening "//")
								(?:
									# URI scheme (or lack thereof for schemeless URLs)
									(?:
										# URL scheme and colon
										\b[a-z][\w\-]+:
										| # or
										# A boundary followed by two slashes for schemeless URLs
										(?<=^|\W)(?=//)
									)

									# IRI "authority" chunk
									(?:
										# 2 slashes for IRIs with an "authority"
										//
										# then a domain name
										(?:
											# Either the reserved "localhost" domain name
											localhost
											| # or
											# a run of Unicode domain name characters and a dot
											[\p{L}\p{M}\p{N}\-.:@]+\.
											# and then a TLD valid in the DNS or the reserved "local" TLD
											(?:'. $modSettings['tld_regex'] .'|local)
										)
										# followed by a non-domain character or end of line
										(?=[^\p{L}\p{N}\-.]|$)

										| # Or, if there is no "authority" per se (e.g. mailto: URLs) ...

										# a run of IRI characters
										[\p{L}\p{N}][\p{L}\p{M}\p{N}\-.:@]+[\p{L}\p{M}\p{N}]
										# and then a dot and a closing IRI label
										\.[\p{L}\p{M}\p{N}\-]+
									)
								)

								| # or

								# Naked domains (e.g. "example.com" in "Go to example.com for an example.")
								(?:
									# Preceded by start of line or a non-domain character
									(?<=^|[^\p{L}\p{M}\p{N}\-:@])

									# A run of Unicode domain name characters (excluding [:@])
									[\p{L}\p{N}][\p{L}\p{M}\p{N}\-.]+[\p{L}\p{M}\p{N}]
									# and then a dot and a valid TLD
									\.' . $modSettings['tld_regex'] . '

									# Followed by either:
									(?=
										# end of line or a non-domain character (excluding [.:@])
										$|[^\p{L}\p{N}\-]
										| # or
										# a dot followed by end of line or a non-domain character (excluding [.:@])
										\.(?=$|[^\p{L}\p{N}\-])
									)
								)
							)

							# IRI path, query, and fragment (if present)
							(?:
								# If any of these parts exist, must start with a single /
								/

								# And then optionally:
								(?:
									# One or more of:
									(?:
										# a run of non-space, non-()<>
										[^\s()<>]+
										| # or
										# balanced parens, up to 2 levels
										\(([^\s()<>]+|(\([^\s()<>]+\)))*\)
									)+

									# End with:
									(?:
										# balanced parens, up to 2 levels
										\(([^\s()<>]+|(\([^\s()<>]+\)))*\)
										| # or
										# not a space or one of these punct char
										[^\s`!()\[\]{};:\'".,<>?«»“”‘’/]
										| # or
										# a trailing slash (but not two in a row)
										(?<!/)/
									)
								)?
							)?
							';

							$data = preg_replace_callback('~' . $url_regex . '~xiu', function ($matches) {
								$url = array_shift($matches);

								$scheme = parse_url($url, PHP_URL_SCHEME);

								if ($scheme == 'mailto')
								{
									$email_address = str_replace('mailto:', '', $url);
									if (!isset($disabled['email']) && filter_var($email_address, FILTER_VALIDATE_EMAIL) !== false)
										return '[email=' . $email_address . ']' . $url . '[/email]';
									else
										return $url;
								}

								// Are we linking a schemeless URL or naked domain name (e.g. "example.com")?
								if (empty($scheme))
									$fullUrl = '//' . ltrim($url, ':/');
								else
									$fullUrl = $url;

								return '[url=&quot;' . str_replace(['[', ']'], ['&#91;', '&#93;'], $fullUrl) . '&quot;]' . $url . '[/url]';
							}, $data);
						}

						// Next, emails...  Must be careful not to step on enablePostHTML logic above...
						if (!isset($disabled['email']) && strpos($data, '@') !== false && strpos($data, '[email') === false && stripos($data, 'mailto:') === false)
						{
							$email_regex = '
							# Preceded by a non-domain character or start of line
							(?<=^|[^\p{L}\p{M}\p{N}\-\.])

							# An email address
							[\p{L}\p{M}\p{N}_\-.]{1,80}
							@
							[\p{L}\p{M}\p{N}\-.]+
							\.
							'. $modSettings['tld_regex'] . '

							# Followed by either:
							(?=
								# end of line or a non-domain character (excluding the dot)
								$|[^\p{L}\p{M}\p{N}\-]
								| # or
								# a dot followed by end of line or a non-domain character
								\.(?=$|[^\p{L}\p{M}\p{N}\-])
							)';

							$data = preg_replace('~' . $email_regex . '~xiu', '[email]$0[/email]', $data);
						}
					}
				}

				$data = strtr($data, ["\t" => '&nbsp;&nbsp;&nbsp;']);

				// If it wasn't changed, no copying or other boring stuff has to happen!
				if ($data != substr($message, $last_pos, $pos - $last_pos))
				{
					$message = substr($message, 0, $last_pos) . $data . substr($message, $pos);

					// Since we changed it, look again in case we added or removed a tag.  But we don't want to skip any.
					$old_pos = strlen($data) + $last_pos;
					$pos = strpos($message, '[', $last_pos);
					$pos = $pos === false ? $old_pos : min($pos, $old_pos);
				}
			}

			// Are we there yet?  Are we there yet?
			if ($pos >= strlen($message) - 1)
				break;

			$tags = strtolower($message[$pos + 1]);

			if ($tags == '/' && !empty($open_tags))
			{
				$pos2 = strpos($message, ']', $pos + 1);
				if ($pos2 == $pos + 2)
					continue;

				$look_for = strtolower(substr($message, $pos + 2, $pos2 - $pos - 2));

				$to_close = [];
				$block_level = null;

				do
				{
					$tag = array_pop($open_tags);
					if (!$tag)
						break;

					if (!empty($tag['block_level']))
					{
						// Only find out if we need to.
						if ($block_level === false)
						{
							array_push($open_tags, $tag);
							break;
						}

						// The idea is, if we are LOOKING for a block level tag, we can close them on the way.
						if (strlen($look_for) > 0 && isset($bbc_codes[$look_for[0]]))
						{
							foreach ($bbc_codes[$look_for[0]] as $temp)
								if ($temp['tag'] == $look_for)
								{
									$block_level = !empty($temp['block_level']);
									break;
								}
						}

						if ($block_level !== true)
						{
							$block_level = false;
							array_push($open_tags, $tag);
							break;
						}
					}

					$to_close[] = $tag;
				}
				while ($tag['tag'] != $look_for);

				// Did we just eat through everything and not find it?
				if ((empty($open_tags) && (empty($tag) || $tag['tag'] != $look_for)))
				{
					$open_tags = $to_close;
					continue;
				}
				elseif (!empty($to_close) && $tag['tag'] != $look_for)
				{
					if ($block_level === null && isset($look_for[0], $bbc_codes[$look_for[0]]))
					{
						foreach ($bbc_codes[$look_for[0]] as $temp)
							if ($temp['tag'] == $look_for)
							{
								$block_level = !empty($temp['block_level']);
								break;
							}
					}

					// We're not looking for a block level tag (or maybe even a tag that exists...)
					if (!$block_level)
					{
						foreach ($to_close as $tag)
							array_push($open_tags, $tag);
						continue;
					}
				}

				foreach ($to_close as $tag)
				{
					$message = substr($message, 0, $pos) . "\n" . $tag['after'] . "\n" . substr($message, $pos2 + 1);
					$pos += strlen($tag['after']) + 2;
					$pos2 = $pos - 1;

					// See the comment at the end of the big loop - just eating whitespace ;).
					$whitespace_regex = '';
					if (!empty($tag['block_level']))
						$whitespace_regex .= '(&nbsp;|\s)*(<br>)?';
					// Trim one line of whitespace after unnested tags, but all of it after nested ones
					if (!empty($tag['trim']) && $tag['trim'] != 'inside')
						$whitespace_regex .= empty($tag['require_parents']) ? '(&nbsp;|\s)*' : '(<br>|&nbsp;|\s)*';

					if (!empty($whitespace_regex) && preg_match('~' . $whitespace_regex . '~', substr($message, $pos), $matches) != 0)
						$message = substr($message, 0, $pos) . substr($message, $pos + strlen($matches[0]));
				}

				if (!empty($to_close))
				{
					$to_close = [];
					$pos--;
				}

				continue;
			}

			// No tags for this character, so just keep going (fastest possible course.)
			if (!isset($bbc_codes[$tags]))
				continue;

			$inside = empty($open_tags) ? null : $open_tags[count($open_tags) - 1];
			$tag = null;
			foreach ($bbc_codes[$tags] as $possible)
			{
				$pt_strlen = strlen($possible['tag']);

				// Not a match?
				if (strtolower(substr($message, $pos + 1, $pt_strlen)) != $possible['tag'])
					continue;

				$next_c = $message[$pos + 1 + $pt_strlen];

				// A test validation?
				if (isset($possible['test']) && preg_match('~^' . $possible['test'] . '~', substr($message, $pos + 1 + $pt_strlen + 1)) === 0)
					continue;
				// Do we want parameters?
				elseif (!empty($possible['parameters']))
				{
					if ($next_c != ' ')
						continue;
				}
				elseif (isset($possible['type']))
				{
					// Do we need an equal sign?
					if (in_array($possible['type'], ['unparsed_equals', 'unparsed_commas', 'unparsed_commas_content', 'unparsed_equals_content', 'parsed_equals']) && $next_c != '=')
						continue;
					// Maybe we just want a /...
					if ($possible['type'] == 'closed' && $next_c != ']' && substr($message, $pos + 1 + $pt_strlen, 2) != '/]' && substr($message, $pos + 1 + $pt_strlen, 3) != ' /]')
						continue;
					// An immediate ]?
					if ($possible['type'] == 'unparsed_content' && $next_c != ']')
						continue;
				}
				// No type means 'parsed_content', which demands an immediate ] without parameters!
				elseif ($next_c != ']')
					continue;

				// Check allowed tree?
				if (isset($possible['require_parents']) && ($inside === null || !in_array($inside['tag'], $possible['require_parents'])))
					continue;
				elseif (isset($inside['require_children']) && !in_array($possible['tag'], $inside['require_children']))
					continue;
				// If this is in the list of disallowed child tags, don't parse it.
				elseif (isset($inside['disallow_children']) && in_array($possible['tag'], $inside['disallow_children']))
					continue;

				$pos1 = $pos + 1 + $pt_strlen + 1;

				// Quotes can have alternate styling, we do this php-side due to all the permutations of quotes.
				if ($possible['tag'] == 'quote')
				{
					// Start with standard
					$quote_alt = false;
					foreach ($open_tags as $open_quote)
					{
						// Every parent quote this quote has flips the styling
						if ($open_quote['tag'] == 'quote')
							$quote_alt = !$quote_alt;
					}
					// Add a class to the quote to style alternating blockquotes
					$possible['before'] = strtr($possible['before'], ['<blockquote>' => '<blockquote class="bbc_' . ($quote_alt ? 'alternate' : 'standard') . '_quote">']);
				}

				// This is long, but it makes things much easier and cleaner.
				if (!empty($possible['parameters']))
				{
					// Build a regular expression for each parameter for the current tag.
					$preg = [];
					foreach ($possible['parameters'] as $p => $info)
						$preg[] = '(\s+' . $p . '=' . (empty($info['quoted']) ? '' : '&quot;') . (isset($info['match']) ? $info['match'] : '(.+?)') . (empty($info['quoted']) ? '' : '&quot;') . '\s*)' . (empty($info['optional']) ? '' : '?');

					// Extract the string that potentially holds our parameters.
					$blob = preg_split('~\[/?(?:' . $alltags_regex . ')~i', substr($message, $pos));
					$blobs = preg_split('~\]~i', $blob[1]);

					$splitters = implode('=|', array_keys($possible['parameters'])) . '=';

					// Progressively append more blobs until we find our parameters or run out of blobs
					$blob_counter = 1;
					while ($blob_counter <= count($blobs))
					{

						$given_param_string = implode(']', array_slice($blobs, 0, $blob_counter++));

						$given_params = preg_split('~\s(?=(' . $splitters . '))~i', $given_param_string);
						sort($given_params, SORT_STRING);

						$match = preg_match('~^' . implode('', $preg) . '$~i', implode(' ', $given_params), $matches) !== 0;

						if ($match)
							$blob_counter = count($blobs) + 1;
					}

					// Didn't match our parameter list, try the next possible.
					if (!$match)
						continue;

					$params = [];
					for ($i = 1, $n = count($matches); $i < $n; $i += 2)
					{
						$key = strtok(ltrim($matches[$i]), '=');
						if (isset($possible['parameters'][$key]['value']))
							$params['{' . $key . '}'] = strtr($possible['parameters'][$key]['value'], ['$1' => $matches[$i + 1]]);
						elseif (isset($possible['parameters'][$key]['validate']))
							$params['{' . $key . '}'] = $possible['parameters'][$key]['validate']($matches[$i + 1]);
						else
							$params['{' . $key . '}'] = $matches[$i + 1];

						// Just to make sure: replace any $ or { so they can't interpolate wrongly.
						$params['{' . $key . '}'] = strtr($params['{' . $key . '}'], ['$' => '&#036;', '{' => '&#123;']);
					}

					foreach ($possible['parameters'] as $p => $info)
					{
						if (!isset($params['{' . $p . '}']))
							$params['{' . $p . '}'] = '';
					}

					$tag = $possible;

					// Put the parameters into the string.
					if (isset($tag['before']))
						$tag['before'] = strtr($tag['before'], $params);
					if (isset($tag['after']))
						$tag['after'] = strtr($tag['after'], $params);
					if (isset($tag['content']))
						$tag['content'] = strtr($tag['content'], $params);

					$pos1 += strlen($given_param_string);
				}
				else
				{
					$tag = $possible;
					$params = [];
				}
				break;
			}

			// Item codes are complicated buggers... they are implicit [li]s and can make [list]s!
			if ($smileys !== false && $tag === null && isset($itemcodes[$message[$pos + 1]]) && $message[$pos + 2] == ']' && !isset($disabled['list']) && !isset($disabled['li']))
			{
				if ($message[$pos + 1] == '0' && !in_array($message[$pos - 1], [';', ' ', "\t", "\n", '>']))
					continue;

				$tag = $itemcodes[$message[$pos + 1]];

				// First let's set up the tree: it needs to be in a list, or after an li.
				if ($inside === null || ($inside['tag'] != 'list' && $inside['tag'] != 'li'))
				{
					$open_tags[] = [
						'tag' => 'list',
						'after' => '</ul>',
						'block_level' => true,
						'require_children' => ['li'],
						'disallow_children' => isset($inside['disallow_children']) ? $inside['disallow_children'] : null,
					];
					$code = '<ul class="bbc_list">';
				}
				// We're in a list item already: another itemcode?  Close it first.
				elseif ($inside['tag'] == 'li')
				{
					array_pop($open_tags);
					$code = '</li>';
				}
				else
					$code = '';

				// Now we open a new tag.
				$open_tags[] = [
					'tag' => 'li',
					'after' => '</li>',
					'trim' => 'outside',
					'block_level' => true,
					'disallow_children' => isset($inside['disallow_children']) ? $inside['disallow_children'] : null,
				];

				// First, open the tag...
				$code .= '<li' . ($tag == '' ? '' : ' type="' . $tag . '"') . '>';
				$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos + 3);
				$pos += strlen($code) - 1 + 2;

				// Next, find the next break (if any.)  If there's more itemcode after it, keep it going - otherwise close!
				$pos2 = strpos($message, '<br>', $pos);
				$pos3 = strpos($message, '[/', $pos);
				if ($pos2 !== false && ($pos2 <= $pos3 || $pos3 === false))
				{
					preg_match('~^(<br>|&nbsp;|\s|\[)+~', substr($message, $pos2 + 4), $matches);
					$message = substr($message, 0, $pos2) . (!empty($matches[0]) && substr($matches[0], -1) == '[' ? '[/li]' : '[/li][/list]') . substr($message, $pos2);

					$open_tags[count($open_tags) - 2]['after'] = '</ul>';
				}
				// Tell the [list] that it needs to close specially.
				else
				{
					// Move the li over, because we're not sure what we'll hit.
					$open_tags[count($open_tags) - 1]['after'] = '';
					$open_tags[count($open_tags) - 2]['after'] = '</li></ul>';
				}

				continue;
			}

			// Implicitly close lists and tables if something other than what's required is in them.  This is needed for itemcode.
			if ($tag === null && $inside !== null && !empty($inside['require_children']))
			{
				array_pop($open_tags);

				$message = substr($message, 0, $pos) . "\n" . $inside['after'] . "\n" . substr($message, $pos);
				$pos += strlen($inside['after']) - 1 + 2;
			}

			// No tag?  Keep looking, then.  Silly people using brackets without actual tags.
			if ($tag === null)
				continue;

			// Propagate the list to the child (so wrapping the disallowed tag won't work either.)
			if (isset($inside['disallow_children']))
				$tag['disallow_children'] = isset($tag['disallow_children']) ? array_unique(array_merge($tag['disallow_children'], $inside['disallow_children'])) : $inside['disallow_children'];

			// Is this tag disabled?
			if (isset($disabled[$tag['tag']]))
			{
				if (!isset($tag['disabled_before']) && !isset($tag['disabled_after']) && !isset($tag['disabled_content']))
				{
					$tag['before'] = !empty($tag['block_level']) ? '<div>' : '';
					$tag['after'] = !empty($tag['block_level']) ? '</div>' : '';
					$tag['content'] = isset($tag['type']) && $tag['type'] == 'closed' ? '' : (!empty($tag['block_level']) ? '<div>$1</div>' : '$1');
				}
				elseif (isset($tag['disabled_before']) || isset($tag['disabled_after']))
				{
					$tag['before'] = isset($tag['disabled_before']) ? $tag['disabled_before'] : (!empty($tag['block_level']) ? '<div>' : '');
					$tag['after'] = isset($tag['disabled_after']) ? $tag['disabled_after'] : (!empty($tag['block_level']) ? '</div>' : '');
				}
				else
					$tag['content'] = $tag['disabled_content'];
			}

			// we use this a lot
			$tag_strlen = strlen($tag['tag']);

			// The only special case is 'html', which doesn't need to close things.
			if (!empty($tag['block_level']) && $tag['tag'] != 'html' && empty($inside['block_level']))
			{
				$n = count($open_tags) - 1;
				while (empty($open_tags[$n]['block_level']) && $n >= 0)
					$n--;

				// Close all the non block level tags so this tag isn't surrounded by them.
				for ($i = count($open_tags) - 1; $i > $n; $i--)
				{
					$message = substr($message, 0, $pos) . "\n" . $open_tags[$i]['after'] . "\n" . substr($message, $pos);
					$ot_strlen = strlen($open_tags[$i]['after']);
					$pos += $ot_strlen + 2;
					$pos1 += $ot_strlen + 2;

					// Trim or eat trailing stuff... see comment at the end of the big loop.
					$whitespace_regex = '';
					if (!empty($tag['block_level']))
						$whitespace_regex .= '(&nbsp;|\s)*(<br>)?';
					if (!empty($tag['trim']) && $tag['trim'] != 'inside')
						$whitespace_regex .= empty($tag['require_parents']) ? '(&nbsp;|\s)*' : '(<br>|&nbsp;|\s)*';
					if (!empty($whitespace_regex) && preg_match('~' . $whitespace_regex . '~', substr($message, $pos), $matches) != 0)
						$message = substr($message, 0, $pos) . substr($message, $pos + strlen($matches[0]));

					array_pop($open_tags);
				}
			}

			// Can't read past the end of the message
			$pos1 = min(strlen($message), $pos1);

			// No type means 'parsed_content'.
			if (!isset($tag['type']))
			{
				if (isset($tag['validate']))
				{
					$data = '';
					$tag['validate']($tag, $data, $disabled, $params);
				}

				// @todo Check for end tag first, so people can say "I like that [i] tag"?
				$open_tags[] = $tag;
				$message = substr($message, 0, $pos) . "\n" . $tag['before'] . "\n" . substr($message, $pos1);
				$pos += strlen($tag['before']) - 1 + 2;
			}
			// Don't parse the content, just skip it.
			elseif ($tag['type'] == 'unparsed_content')
			{
				$pos2 = stripos($message, '[/' . substr($message, $pos + 1, $tag_strlen) . ']', $pos1);
				if ($pos2 === false)
					continue;

				$data = substr($message, $pos1, $pos2 - $pos1);

				if (!empty($tag['block_level']) && substr($data, 0, 4) == '<br>')
					$data = substr($data, 4);

				if (isset($tag['validate']))
					$tag['validate']($tag, $data, $disabled, $params);

				$code = strtr($tag['content'], ['$1' => $data]);
				$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + 3 + $tag_strlen);

				$pos += strlen($code) - 1 + 2;
				$last_pos = $pos + 1;

			}
			// Don't parse the content, just skip it.
			elseif ($tag['type'] == 'unparsed_equals_content')
			{
				// The value may be quoted for some tags - check.
				if (isset($tag['quoted']))
				{
					$quoted = substr($message, $pos1, 6) == '&quot;';
					if ($tag['quoted'] != 'optional' && !$quoted)
						continue;

					if ($quoted)
						$pos1 += 6;
				}
				else
					$quoted = false;

				$pos2 = strpos($message, $quoted == false ? ']' : '&quot;]', $pos1);
				if ($pos2 === false)
					continue;

				$pos3 = stripos($message, '[/' . substr($message, $pos + 1, $tag_strlen) . ']', $pos2);
				if ($pos3 === false)
					continue;

				$data = [
					substr($message, $pos2 + ($quoted == false ? 1 : 7), $pos3 - ($pos2 + ($quoted == false ? 1 : 7))),
					substr($message, $pos1, $pos2 - $pos1)
				];

				if (!empty($tag['block_level']) && substr($data[0], 0, 4) == '<br>')
					$data[0] = substr($data[0], 4);

				// Validation for my parking, please!
				if (isset($tag['validate']))
					$tag['validate']($tag, $data, $disabled, $params);

				$code = strtr($tag['content'], ['$1' => $data[0], '$2' => $data[1]]);
				$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos3 + 3 + $tag_strlen);
				$pos += strlen($code) - 1 + 2;
			}
			// A closed tag, with no content or value.
			elseif ($tag['type'] == 'closed')
			{
				$pos2 = strpos($message, ']', $pos);
				$message = substr($message, 0, $pos) . "\n" . $tag['content'] . "\n" . substr($message, $pos2 + 1);
				$pos += strlen($tag['content']) - 1 + 2;
			}
			// This one is sorta ugly... :/
			elseif ($tag['type'] == 'unparsed_commas_content')
			{
				$pos2 = strpos($message, ']', $pos1);
				if ($pos2 === false)
					continue;

				$pos3 = stripos($message, '[/' . substr($message, $pos + 1, $tag_strlen) . ']', $pos2);
				if ($pos3 === false)
					continue;

				// We want $1 to be the content, and the rest to be csv.
				$data = explode(',', ',' . substr($message, $pos1, $pos2 - $pos1));
				$data[0] = substr($message, $pos2 + 1, $pos3 - $pos2 - 1);

				if (isset($tag['validate']))
					$tag['validate']($tag, $data, $disabled, $params);

				$code = $tag['content'];
				foreach ($data as $k => $d)
					$code = strtr($code, ['$' . ($k + 1) => trim($d)]);
				$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos3 + 3 + $tag_strlen);
				$pos += strlen($code) - 1 + 2;
			}
			// This has parsed content, and a csv value which is unparsed.
			elseif ($tag['type'] == 'unparsed_commas')
			{
				$pos2 = strpos($message, ']', $pos1);
				if ($pos2 === false)
					continue;

				$data = explode(',', substr($message, $pos1, $pos2 - $pos1));

				if (isset($tag['validate']))
					$tag['validate']($tag, $data, $disabled, $params);

				// Fix after, for disabled code mainly.
				foreach ($data as $k => $d)
					$tag['after'] = strtr($tag['after'], ['$' . ($k + 1) => trim($d)]);

				$open_tags[] = $tag;

				// Replace them out, $1, $2, $3, $4, etc.
				$code = $tag['before'];
				foreach ($data as $k => $d)
					$code = strtr($code, ['$' . ($k + 1) => trim($d)]);
				$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + 1);
				$pos += strlen($code) - 1 + 2;
			}
			// A tag set to a value, parsed or not.
			elseif ($tag['type'] == 'unparsed_equals' || $tag['type'] == 'parsed_equals')
			{
				// The value may be quoted for some tags - check.
				if (isset($tag['quoted']))
				{
					$quoted = substr($message, $pos1, 6) == '&quot;';
					if ($tag['quoted'] != 'optional' && !$quoted)
						continue;

					if ($quoted)
						$pos1 += 6;
				}
				else
					$quoted = false;

				$pos2 = strpos($message, $quoted == false ? ']' : '&quot;]', $pos1);
				if ($pos2 === false)
					continue;

				$data = substr($message, $pos1, $pos2 - $pos1);

				// Validation for my parking, please!
				if (isset($tag['validate']))
					$tag['validate']($tag, $data, $disabled, $params);

				// For parsed content, we must recurse to avoid security problems.
				if ($tag['type'] != 'unparsed_equals')
					$data = self::parse_bbc($data, !empty($tag['parsed_tags_allowed']) ? false : true, '', !empty($tag['parsed_tags_allowed']) ? $tag['parsed_tags_allowed'] : []);

				$tag['after'] = strtr($tag['after'], ['$1' => $data]);

				$open_tags[] = $tag;

				$code = strtr($tag['before'], ['$1' => $data]);
				$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + ($quoted == false ? 1 : 7));
				$pos += strlen($code) - 1 + 2;
			}

			// If this is block level, eat any breaks after it.
			if (!empty($tag['block_level']) && substr($message, $pos + 1, 4) == '<br>')
				$message = substr($message, 0, $pos + 1) . substr($message, $pos + 5);

			// Are we trimming outside this tag?
			if (!empty($tag['trim']) && $tag['trim'] != 'outside' && preg_match('~(<br>|&nbsp;|\s)*~', substr($message, $pos + 1), $matches) != 0)
				$message = substr($message, 0, $pos + 1) . substr($message, $pos + 1 + strlen($matches[0]));
		}

		// Close any remaining tags.
		while ($tag = array_pop($open_tags))
			$message .= "\n" . $tag['after'] . "\n";

		// Parse the smileys within the parts where it can be done safely.
		if ($smileys === true)
		{
			$message_parts = explode("\n", $message);
			for ($i = 0, $n = count($message_parts); $i < $n; $i += 2)
				self::parse_smileys($message_parts[$i]);

			$message = implode('', $message_parts);
		}

		// No smileys, just get rid of the markers.
		else
			$message = strtr($message, ["\n" => '']);

		if ($message !== '' && $message[0] === ' ')
			$message = '&nbsp;' . substr($message, 1);

		// Cleanup whitespace.
		$message = strtr($message, ['  ' => ' &nbsp;', "\r" => '', "\n" => '<br>', '<br> ' => '<br>&nbsp;', '&#13;' => "\n"]);

		// Remove any content identified as needing to be removed.
		$message = preg_replace('~<sbb___strip>.*?</sbb___strip>~', '', $message);

		// Allow mods access to what parse_bbc created
		call_integration_hook('integrate_post_parsebbc', [&$message, &$smileys, &$cache_id, &$parse_tags]);

		// Cache the output if it took some time...
		if (isset($cache_key, $cache_t) && microtime(true) - $cache_t > 0.05)
			cache_put_data($cache_key, $message, 240);

		// If this was a force parse revert if needed.
		if (!empty($parse_tags))
		{
			if (empty($temp_bbc))
				$bbc_codes = [];
			else
			{
				$bbc_codes = $temp_bbc;
				unset($temp_bbc);
			}
		}

		return $message;
	}

	/**
	 * Parse smileys in the passed message.
	 *
	 * The smiley parsing function which makes pretty faces appear :).
	 * These are specifically not parsed in code tags [url=mailto:Dad@blah.com]
	 * Caches the smileys from the database or array in memory.
	 * Doesn't return anything, but rather modifies message directly.
	 *
	 * @param string $message The message to parse smileys in
	 */
	public static function parse_smileys(string &$message)
	{
		static $smileyPregSearch = null, $smileyPregReplacements = [];

		// No smiley set at all?!
		if (trim($message) == '')
			return;

		// If smileyPregSearch hasn't been set, do it now.
		if (empty($smileyPregSearch))
		{
			// Load the smileys in reverse order by length so they don't get parsed wrong.
			if (($temp = cache_get_data('parsing_smileys', 480)) == null)
			{
				$container = Container::instance();
				$smiley_helper = $container->get('smileys');

				// Get all the smileys.
				$smileys = $smiley_helper->get_smileys();
				$linearsmileys = [];
				// Split out the multiple code variants into a single master list.
				foreach ($smileys as $smiley)
				{
					$codes = explode("\n", $smiley['code']);
					foreach ($codes as $code)
					{
						$linearsmileys[] = [trim($code), $smiley['url'], $smiley['description']];
					}
				}
				// Sort the smileys by longest code first.
				uasort($linearsmileys, function($a, $b) {
					return StringLibrary::strlen($b[0]) <=> StringLibrary::strlen($a[0]);
				});
				// Pack into three arrays for the rest of the logic to use.
				foreach ($linearsmileys as $smiley)
				{
					$smileysfrom[] = $smiley[0];
					$smileysto[] = $smiley[1];
					$smileysdescs[] = $smiley[2];
				}

				cache_put_data('parsing_smileys', [$smileysfrom, $smileysto, $smileysdescs], 480);
			}
			else
				list ($smileysfrom, $smileysto, $smileysdescs) = $temp;

			// The non-breaking-space is a complex thing...
			$non_breaking_space = '\x{A0}';

			// This smiley regex makes sure it doesn't parse smileys within code tags (so [url=mailto:David@bla.com] doesn't parse the :D smiley)
			$smileyPregReplacements = [];
			$searchParts = [];

			for ($i = 0, $n = count($smileysfrom); $i < $n; $i++)
			{
				$specialChars = StringLibrary::escape($smileysfrom[$i], ENT_QUOTES);
				$smileyCode = '<img src="' . $smileysto[$i] . '" alt="' . strtr($specialChars, [':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;']). '" title="' . strtr(StringLibrary::escape($smileysdescs[$i]), [':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;']) . '" class="smiley">';

				$smileyPregReplacements[$smileysfrom[$i]] = $smileyCode;

				$searchParts[] = preg_quote($smileysfrom[$i], '~');
				if ($smileysfrom[$i] != $specialChars)
				{
					$smileyPregReplacements[$specialChars] = $smileyCode;
					$searchParts[] = preg_quote($specialChars, '~');
				}
			}

			$smileyPregSearch = '~(?<=[>:\?\.\s' . $non_breaking_space . '[\]()*\\\;]|(?<![a-zA-Z0-9])\(|^)(' . implode('|', $searchParts) . ')(?=[^[:alpha:]0-9]|$)~u';
		}

		// Replace away!
		$message = preg_replace_callback($smileyPregSearch,
			function ($matches) use ($smileyPregReplacements)
			{
				return $smileyPregReplacements[$matches[1]];
			}, $message);
	}

	/**
	 * Microsoft uses their own character set Code Page 1252 (CP1252), which is a
	 * superset of ISO 8859-1, defining several characters between DEC 128 and 159
	 * that are not normally displayable.  This converts the popular ones that
	 * appear from a cut and paste from windows.
	 *
	 * @param string $string The string
	 * @return string The sanitized string
	 */
	public static function sanitizeMSCutPaste(string $string): string
	{
		if (empty($string))
			return $string;

		// UTF-8 occurences of MS special characters
		$findchars_utf8 = [
			"\xe2\x80\x9a",	// single low-9 quotation mark
			"\xe2\x80\x9e",	// double low-9 quotation mark
			"\xe2\x80\xa6",	// horizontal ellipsis
			"\xe2\x80\x98",	// left single curly quote
			"\xe2\x80\x99",	// right single curly quote
			"\xe2\x80\x9c",	// left double curly quote
			"\xe2\x80\x9d",	// right double curly quote
			"\xe2\x80\x93",	// en dash
			"\xe2\x80\x94",	// em dash
		];

		// safe replacements
		$replacechars = [
			',',	// &sbquo;
			',,',	// &bdquo;
			'...',	// &hellip;
			"'",	// &lsquo;
			"'",	// &rsquo;
			'"',	// &ldquo;
			'"',	// &rdquo;
			'-',	// &ndash;
			'--',	// &mdash;
		];

		return str_replace($findchars_utf8, $replacechars, $string);
	}
}
