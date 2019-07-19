<?php

/**
 * This class handles the TLD regex processing for linking to URLs.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

use StoryBB\Task;
use GuzzleHttp\Client;

/**
 * This class handles the TLD regex processing for linking to URLs.
 */
class TLD
{
	/**
	 * Creates an optimized regex to match all known top level domains.
	 *
	 * The optimized regex is stored in $modSettings['tld_regex'].
	 *
	 * To update the stored version of the regex to use the latest list of valid TLDs from iana.org, set
	 * the $update parameter to true. Updating can take some time, based on network connectivity, so it
	 * should normally only be done by calling this function from a background or scheduled task.
	 *
	 * If $update is not true, but the regex is missing or invalid, the regex will be regenerated from a
	 * hard-coded list of TLDs. This regenerated regex will be overwritten on the next scheduled update.
	 *
	 * @param bool $update If true, fetch and process the latest offical list of TLDs from iana.org.
	 */
	public static function set_tld_regex($update = false)
	{
		global $sourcedir, $smcFunc, $modSettings;
		static $done = false;

		// If we don't need to do anything, don't
		if (!$update && $done)
			return;

		// Should we get a new copy of the official list of TLDs?
		if ($update)
		{
			$client = new Client();
			$http_request = $client->get('https://data.iana.org/TLD/tlds-alpha-by-domain.txt');
			$tlds = (string) $http_request->getBody();

			// If the Internet Assigned Numbers Authority can't be reached, the Internet is gone. We're probably running on a server hidden in a bunker deep underground to protect it from marauding bandits roaming on the surface. We don't want to waste precious electricity on pointlessly repeating background tasks, so we'll wait until the next regularly scheduled update to see if civilization has been restored.
			if (empty($tlds))
				$postapocalypticNightmare = true;
		}
		// If we aren't updating and the regex is valid, we're done
		elseif (!empty($modSettings['tld_regex']) && @preg_match('~' . $modSettings['tld_regex'] . '~', null) !== false)
		{
			$done = true;
			return;
		}

		// If we successfully got an update, process the list into an array
		if (!empty($tlds))
		{
			// Clean $tlds and convert it to an array
			$tlds = array_filter(explode("\n", strtolower($tlds)), function($line) {
				$line = trim($line);
				if (empty($line) || strpos($line, '#') !== false || strpos($line, ' ') !== false)
					return false;
				else
					return true;
			});

			// Convert Punycode to Unicode
			$tlds = array_map(function ($input) {
				$prefix = 'xn--';
				$safe_char = 0xFFFC;
				$base = 36;
				$tmin = 1;
				$tmax = 26;
				$skew = 38;
				$damp = 700;
				$output_parts = [];

				$input = str_replace(strtoupper($prefix), $prefix, $input);

				$enco_parts = (array) explode('.', $input);

				foreach ($enco_parts as $encoded)
				{
					if (strpos($encoded, $prefix) !== 0 || strlen(trim(str_replace($prefix, '', $encoded))) == 0)
					{
						$output_parts[] = $encoded;
						continue;
					}

					$is_first = true;
					$bias = 72;
					$idx = 0;
					$char = 0x80;
					$decoded = [];
					$output = '';
					$delim_pos = strrpos($encoded, '-');

					if ($delim_pos > strlen($prefix))
					{
						for ($k = strlen($prefix); $k < $delim_pos; ++$k)
						{
							$decoded[] = ord($encoded{$k});
						}
					}

					$deco_len = count($decoded);
					$enco_len = strlen($encoded);

					for ($enco_idx = $delim_pos ? ($delim_pos + 1) : 0; $enco_idx < $enco_len; ++$deco_len)
					{
						for ($old_idx = $idx, $w = 1, $k = $base; 1; $k += $base)
						{
							$cp = ord($encoded{$enco_idx++});
							$digit = ($cp - 48 < 10) ? $cp - 22 : (($cp - 65 < 26) ? $cp - 65 : (($cp - 97 < 26) ? $cp - 97 : $base));
							$idx += $digit * $w;
							$t = ($k <= $bias) ? $tmin : (($k >= $bias + $tmax) ? $tmax : ($k - $bias));

							if ($digit < $t)
								break;

							$w = (int) ($w * ($base - $t));
						}

						$delta = $idx - $old_idx;
						$delta = intval($is_first ? ($delta / $damp) : ($delta / 2));
						$delta += intval($delta / ($deco_len + 1));

						for ($k = 0; $delta > (($base - $tmin) * $tmax) / 2; $k += $base)
							$delta = intval($delta / ($base - $tmin));

						$bias = intval($k + ($base - $tmin + 1) * $delta / ($delta + $skew));
						$is_first = false;
						$char += (int) ($idx / ($deco_len + 1));
						$idx %= ($deco_len + 1);

						if ($deco_len > 0)
						{
							for ($i = $deco_len; $i > $idx; $i--)
								$decoded[$i] = $decoded[($i - 1)];
						}
						$decoded[$idx++] = $char;
					}

					foreach ($decoded as $k => $v)
					{
						// 7bit are transferred literally
						if ($v < 128)
							$output .= chr($v);

						// 2 bytes
						elseif ($v < (1 << 11))
							$output .= chr(192 + ($v >> 6)) . chr(128 + ($v & 63));

						// 3 bytes
						elseif ($v < (1 << 16))
							$output .= chr(224 + ($v >> 12)) . chr(128 + (($v >> 6) & 63)) . chr(128 + ($v & 63));

						// 4 bytes
						elseif ($v < (1 << 21))
							$output .= chr(240 + ($v >> 18)) . chr(128 + (($v >> 12) & 63)) . chr(128 + (($v >> 6) & 63)) . chr(128 + ($v & 63));

						//  'Conversion from UCS-4 to UTF-8 failed: malformed input at byte '.$k
						else
							$output .= $safe_char;
					}

					$output_parts[] = $output;
				}

				return implode('.', $output_parts);
			}, $tlds);
		}
		// Otherwise, use the 2012 list of gTLDs and ccTLDs for now and schedule a background update
		else
		{
			$tlds = array('com', 'net', 'org', 'edu', 'gov', 'mil', 'aero', 'asia', 'biz', 'cat',
				'coop', 'info', 'int', 'jobs', 'mobi', 'museum', 'name', 'post', 'pro', 'tel',
				'travel', 'xxx', 'ac', 'ad', 'ae', 'af', 'ag', 'ai', 'al', 'am', 'an', 'ao', 'aq',
				'ar', 'as', 'at', 'au', 'aw', 'ax', 'az', 'ba', 'bb', 'bd', 'be', 'bf', 'bg', 'bh',
				'bi', 'bj', 'bm', 'bn', 'bo', 'br', 'bs', 'bt', 'bv', 'bw', 'by', 'bz', 'ca', 'cc',
				'cd', 'cf', 'cg', 'ch', 'ci', 'ck', 'cl', 'cm', 'cn', 'co', 'cr', 'cs', 'cu', 'cv',
				'cx', 'cy', 'cz', 'dd', 'de', 'dj', 'dk', 'dm', 'do', 'dz', 'ec', 'ee', 'eg', 'eh',
				'er', 'es', 'et', 'eu', 'fi', 'fj', 'fk', 'fm', 'fo', 'fr', 'ga', 'gb', 'gd', 'ge',
				'gf', 'gg', 'gh', 'gi', 'gl', 'gm', 'gn', 'gp', 'gq', 'gr', 'gs', 'gt', 'gu', 'gw',
				'gy', 'hk', 'hm', 'hn', 'hr', 'ht', 'hu', 'id', 'ie', 'il', 'im', 'in', 'io', 'iq',
				'ir', 'is', 'it', 'ja', 'je', 'jm', 'jo', 'jp', 'ke', 'kg', 'kh', 'ki', 'km', 'kn',
				'kp', 'kr', 'kw', 'ky', 'kz', 'la', 'lb', 'lc', 'li', 'lk', 'lr', 'ls', 'lt', 'lu',
				'lv', 'ly', 'ma', 'mc', 'md', 'me', 'mg', 'mh', 'mk', 'ml', 'mm', 'mn', 'mo', 'mp',
				'mq', 'mr', 'ms', 'mt', 'mu', 'mv', 'mw', 'mx', 'my', 'mz', 'na', 'nc', 'ne', 'nf',
				'ng', 'ni', 'nl', 'no', 'np', 'nr', 'nu', 'nz', 'om', 'pa', 'pe', 'pf', 'pg', 'ph',
				'pk', 'pl', 'pm', 'pn', 'pr', 'ps', 'pt', 'pw', 'py', 'qa', 're', 'ro', 'rs', 'ru',
				'rw', 'sa', 'sb', 'sc', 'sd', 'se', 'sg', 'sh', 'si', 'sj', 'sk', 'sl', 'sm', 'sn',
				'so', 'sr', 'ss', 'st', 'su', 'sv', 'sx', 'sy', 'sz', 'tc', 'td', 'tf', 'tg', 'th',
				'tj', 'tk', 'tl', 'tm', 'tn', 'to', 'tp', 'tr', 'tt', 'tv', 'tw', 'tz', 'ua', 'ug',
				'uk', 'us', 'uy', 'uz', 'va', 'vc', 've', 'vg', 'vi', 'vn', 'vu', 'wf', 'ws', 'ye',
				'yt', 'yu', 'za', 'zm', 'zw');

			// Schedule a background update, unless civilization has collapsed and/or we are having connectivity issues.
			$schedule_update = empty($postapocalypticNightmare);
		}

		// Get an optimized regex to match all the TLDs
		$tld_regex = self::build_regex($tlds);

		// Remember the new regex in $modSettings
		updateSettings(array('tld_regex' => $tld_regex));

		// Schedule a background update if we need one
		if (!empty($schedule_update))
		{
			Task::queue_adhoc('StoryBB\\Task\\Adhoc\\UpdateTldRegex');
		}

		// Redundant repetition is redundant
		$done = true;
	}

	/**
	 * Creates optimized regular expressions from an array of strings.
	 *
	 * An optimized regex built using this function will be much faster than a simple regex built using
	 * `implode('|', $strings)` --- anywhere from several times to several orders of magnitude faster.
	 *
	 * However, the time required to build the optimized regex is approximately equal to the time it
	 * takes to execute the simple regex. Therefore, it is only worth calling this function if the
	 * resulting regex will be used more than once.
	 *
	 * Because PHP places an upper limit on the allowed length of a regex, very large arrays of $strings
	 * may not fit in a single regex. Normally, the excess strings will simply be dropped. However, if
	 * the $returnArray parameter is set to true, this function will build as many regexes as necessary
	 * to accomodate everything in $strings and return them in an array. You will need to iterate
	 * through all elements of the returned array in order to test all possible matches.
	 *
	 * @param array $strings An array of strings to make a regex for.
	 * @param string $delim An optional delimiter character to pass to preg_quote().
	 * @param bool $returnArray If true, returns an array of regexes.
	 * @return string|array One or more regular expressions to match any of the input strings.
	 */
	public static function build_regex($strings, $delim = null, $returnArray = false)
	{
		global $smcFunc;

			if (($string_encoding = mb_detect_encoding(implode(' ', $strings))) !== false)
			{
			// Save the current encoding just in case.
				$current_encoding = mb_internal_encoding();
				mb_internal_encoding($string_encoding);
			}

		// This recursive function creates the index array from the strings
		$add_string_to_index = function ($string, $index) use (&$add_string_to_index)
		{
			static $depth = 0;
			$depth++;

			$first = mb_substr($string, 0, 1);

			if (empty($index[$first]))
				$index[$first] = [];

			if (mb_strlen($string) > 1)
			{
				// Sanity check on recursion
				if ($depth > 99)
					$index[$first][mb_substr($string, 1)] = '';

				else
					$index[$first] = $add_string_to_index(mb_substr($string, 1), $index[$first]);
			}
			else
				$index[$first][''] = '';

			$depth--;
			return $index;
		};

		// This recursive function turns the index array into a regular expression
		$index_to_regex = function (&$index, $delim) use (&$index_to_regex)
		{
			static $depth = 0;
			$depth++;

			// Absolute max length for a regex is 32768, but we might need wiggle room
			$max_length = 30000;

			$regex = [];
			$length = 0;

			foreach ($index as $key => $value)
			{
				$key_regex = preg_quote($key, $delim);
				$new_key = $key;

				if (empty($value))
					$sub_regex = '';
				else
				{
					$sub_regex = $index_to_regex($value, $delim);

					if (count(array_keys($value)) == 1)
					{
						$new_key_array = explode('(?'.'>', $sub_regex);
						$new_key .= $new_key_array[0];
					}
					else
						$sub_regex = '(?'.'>' . $sub_regex . ')';
				}

				if ($depth > 1)
					$regex[$new_key] = $key_regex . $sub_regex;
				else
				{
					if (($length += strlen($key_regex) + 1) < $max_length || empty($regex))
					{
						$regex[$new_key] = $key_regex . $sub_regex;
						unset($index[$key]);
					}
					else
						break;
				}
			}

			// Sort by key length and then alphabetically
			uksort($regex, function($k1, $k2) {
				$l1 = mb_strlen($k1);
				$l2 = mb_strlen($k2);

				if ($l1 == $l2)
					return strcmp($k1, $k2) > 0 ? 1 : -1;
				else
					return $l1 > $l2 ? -1 : 1;
			});

			$depth--;
			return implode('|', $regex);
		};

		// Now that the functions are defined, let's do this thing
		$index = [];
		$regex = '';

		foreach ($strings as $string)
			$index = $add_string_to_index($string, $index);

		if ($returnArray === true)
		{
			$regex = [];
			while (!empty($index))
				$regex[] = '(?'.'>' . $index_to_regex($index, $delim) . ')';
		}
		else
			$regex = '(?'.'>' . $index_to_regex($index, $delim) . ')';

		// Restore PHP's internal character encoding to whatever it was originally
		if (!empty($current_encoding))
			mb_internal_encoding($current_encoding);

		return $regex;
	}	
}
