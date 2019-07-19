<?php

/**
 * Support functions for timezones.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

/**
 * Support functions for timezones.
 */
class Timezone
{

	/**
	 * Get a list of timezones.
	 *
	 * @param string $when An optional date or time for which to calculate the timezone offset values. May be a Unix timestamp or any string that strtotime() can understand. Defaults to 'now'.
	 * @return array An array of timezone info.
	 */
	public static function list_timezones($when = 'now')
	{
		global $smcFunc, $modSettings;
		static $timezones = null, $lastwhen = null;

		// No point doing this over if we already did it once
		if (!empty($timezones) && $when == $lastwhen)
			return $timezones;
		else
			$lastwhen = $when;

		// Parseable datetime string?
		if (is_int($timestamp = strtotime($when)))
			$when = $timestamp;

		// A Unix timestamp?
		elseif (is_numeric($when))
			$when = intval($when);

		// Invalid value? Just get current Unix timestamp.
		else
			$when = time();

		// We'll need these too
		$date_when = date_create('@' . $when);
		$later = (int) date_format(date_add($date_when, date_interval_create_from_date_string('1 year')), 'U');

		// Prefer and give custom descriptions for these time zones
		// If the description is left empty, it will be filled in with the names of matching cities
		$timezone_descriptions = array(
			'America/Adak' => 'Aleutian Islands',
			'Pacific/Marquesas' => 'Marquesas Islands',
			'Pacific/Gambier' => 'Gambier Islands',
			'America/Anchorage' => 'Alaska',
			'Pacific/Pitcairn' => 'Pitcairn Islands',
			'America/Los_Angeles' => 'Pacific Time (USA, Canada)',
			'America/Denver' => 'Mountain Time (USA, Canada)',
			'America/Phoenix' => 'Mountain Time (no DST)',
			'America/Chicago' => 'Central Time (USA, Canada)',
			'America/Belize' => 'Central Time (no DST)',
			'America/New_York' => 'Eastern Time (USA, Canada)',
			'America/Atikokan' => 'Eastern Time (no DST)',
			'America/Halifax' => 'Atlantic Time (Canada)',
			'America/Anguilla' => 'Atlantic Time (no DST)',
			'America/St_Johns' => 'Newfoundland',
			'America/Chihuahua' => 'Chihuahua, Mazatlan',
			'Pacific/Easter' => 'Easter Island',
			'Atlantic/Stanley' => 'Falkland Islands',
			'America/Miquelon' => 'Saint Pierre and Miquelon',
			'America/Argentina/Buenos_Aires' => 'Buenos Aires',
			'America/Sao_Paulo' => 'Brasilia Time',
			'America/Araguaina' => 'Brasilia Time (no DST)',
			'America/Godthab' => 'Greenland',
			'America/Noronha' => 'Fernando de Noronha',
			'Atlantic/Reykjavik' => 'Greenwich Mean Time (no DST)',
			'Europe/London' => '',
			'Europe/Berlin' => 'Central European Time',
			'Europe/Helsinki' => 'Eastern European Time',
			'Africa/Brazzaville' => 'Brazzaville, Lagos, Porto-Novo',
			'Asia/Jerusalem' => 'Jerusalem',
			'Europe/Moscow' => '',
			'Africa/Khartoum' => 'Eastern Africa Time',
			'Asia/Riyadh' => 'Arabia Time',
			'Asia/Kolkata' => 'India, Sri Lanka',
			'Asia/Yekaterinburg' => 'Yekaterinburg, Tyumen',
			'Asia/Dhaka' => 'Astana, Dhaka',
			'Asia/Rangoon' => 'Yangon/Rangoon',
			'Indian/Christmas' => 'Christmas Island',
			'Antarctica/DumontDUrville' => 'Dumont D\'Urville Station',
			'Antarctica/Vostok' => 'Vostok Station',
			'Australia/Lord_Howe' => 'Lord Howe Island',
			'Pacific/Guadalcanal' => 'Solomon Islands',
			'Pacific/Norfolk' => 'Norfolk Island',
			'Pacific/Noumea' => 'New Caledonia',
			'Pacific/Auckland' => 'Auckland, McMurdo Station',
			'Pacific/Kwajalein' => 'Marshall Islands',
			'Pacific/Chatham' => 'Chatham Islands',
		);

		// Should we put time zones from certain countries at the top of the list?
		$priority_countries = !empty($modSettings['timezone_priority_countries']) ? explode(',', $modSettings['timezone_priority_countries']) : [];
		$priority_tzids = [];
		foreach ($priority_countries as $country)
		{
			$country_tzids = @timezone_identifiers_list(DateTimeZone::PER_COUNTRY, strtoupper(trim($country)));
			if (!empty($country_tzids))
				$priority_tzids = array_merge($priority_tzids, $country_tzids);
		}

		// Process the preferred timezones first, then the rest.
		$tzids = array_keys($timezone_descriptions) + array_diff(timezone_identifiers_list(), array_keys($timezone_descriptions));

		// Idea here is to get exactly one representative identifier for each and every unique set of time zone rules.
		foreach ($tzids as $tzid)
		{
			// We don't want UTC right now
			if ($tzid == 'UTC')
				continue;

			$tz = timezone_open($tzid);

			// First, get the set of transition rules for this tzid
			$tzinfo = timezone_transitions_get($tz, $when, $later);

			// Use the entire set of transition rules as the array *key* so we can avoid duplicates
			$tzkey = serialize($tzinfo);

			// Next, get the geographic info for this tzid
			$tzgeo = timezone_location_get($tz);

			// Don't overwrite our preferred tzids
			if (empty($zones[$tzkey]['tzid']))
			{
				$zones[$tzkey]['tzid'] = $tzid;
				$zones[$tzkey]['abbr'] = self::fix_tz_abbrev($tzid, $tzinfo[0]['abbr']);
			}

			// A time zone from a prioritized country?
			if (in_array($tzid, $priority_tzids))
				$priority_zones[$tzkey] = true;

			// Keep track of the location and offset for this tzid
			$tzid_parts = explode('/', $tzid);
			$zones[$tzkey]['locations'][] = str_replace(array('St_', '_'), array('St. ', ' '), array_pop($tzid_parts));
			$offsets[$tzkey] = $tzinfo[0]['offset'];
			$longitudes[$tzkey] = empty($longitudes[$tzkey]) ? $tzgeo['longitude'] : $longitudes[$tzkey];
		}

		// Sort by offset then longitude
		array_multisort($offsets, SORT_ASC, SORT_NUMERIC, $longitudes, SORT_ASC, SORT_NUMERIC, $zones);

		// Build the final array of formatted values
		$priority_timezones = [];
		$timezones = [];
		foreach ($zones as $tzkey => $tzvalue)
		{
			date_timezone_set($date_when, timezone_open($tzvalue['tzid']));

			if (!empty($timezone_descriptions[$tzvalue['tzid']]))
				$desc = $timezone_descriptions[$tzvalue['tzid']];
			else
				$desc = implode(', ', array_unique($tzvalue['locations']));

			if (isset($priority_zones[$tzkey]))
				$priority_timezones[$tzvalue['tzid']] = $tzvalue['abbr'] . ' - ' . $desc . ' [UTC' . date_format($date_when, 'P') . ']';
			else
				$timezones[$tzvalue['tzid']] = $tzvalue['abbr'] . ' - ' . $desc . ' [UTC' . date_format($date_when, 'P') . ']';
		}

		$timezones = array_merge(
			$priority_timezones,
			array('' => '(Forum Default)', 'UTC' => 'UTC - Coordinated Universal Time'),
			$timezones
		);

		return $timezones;
	}

	/**
	 * Reformats certain time zone abbreviations to look better.
	 *
	 * Some of PHP's time zone abbreviations are just numerical offsets from UTC, e.g. '+04'
	 * These look weird and are kind of useless, so we make them look better.
	 *
	 * @param string $tzid The Olsen time zome identifier for a time zone.
	 * @param string $tz_abbrev The abbreviation PHP provided for this time zone.
	 * @return string The fixed version of $tz_abbrev.
	 */
	public static function fix_tz_abbrev($tzid, $tz_abbrev)
	{
		// Is this abbreviation just a numerical offset?
		if (strspn($tz_abbrev, '+-') > 0)
		{
			// To get on this list, a time zone must be historically stable and must not observe daylight saving time
			$missing_tz_abbrs = array(
				'Antarctica/Casey' => 'CAST',
				'Antarctica/Davis' => 'DAVT',
				'Antarctica/DumontDUrville' => 'DDUT',
				'Antarctica/Mawson' => 'MAWT',
				'Antarctica/Rothera' => 'ART',
				'Antarctica/Syowa' => 'SYOT',
				'Antarctica/Vostok' => 'VOST',
				'Asia/Almaty' => 'ALMT',
				'Asia/Aqtau' => 'ORAT',
				'Asia/Aqtobe' => 'AQTT',
				'Asia/Ashgabat' => 'TMT',
				'Asia/Bishkek' => 'KGT',
				'Asia/Colombo' => 'IST',
				'Asia/Dushanbe' => 'TJT',
				'Asia/Oral' => 'ORAT',
				'Asia/Qyzylorda' => 'QYZT',
				'Asia/Samarkand' => 'UZT',
				'Asia/Tashkent' => 'UZT',
				'Asia/Tbilisi' => 'GET',
				'Asia/Yerevan' => 'AMT',
				'Europe/Istanbul' => 'TRT',
				'Europe/Minsk' => 'MSK',
				'Indian/Kerguelen' => 'TFT',
			);

			if (!empty($missing_tz_abbrs[$tzid]))
				$tz_abbrev = $missing_tz_abbrs[$tzid];
			else
			{
				// Russia likes to experiment with time zones often, and names them as offsets from Moscow
				$tz_location = timezone_location_get(timezone_open($tzid));
				if ($tz_location['country_code'] == 'RU')
				{
					$msk_offset = intval($tz_abbrev) - 3;
					$tz_abbrev = 'MSK' . (!empty($msk_offset) ? sprintf('%+0d', $msk_offset) : '');
				}
			}

			// Still no good? We'll just mark it as a UTC offset
			if (strspn($tz_abbrev, '+-') > 0)
			{
				$tz_abbrev = intval($tz_abbrev);
				$tz_abbrev = 'UTC' . (!empty($tz_abbrev) ? sprintf('%+0d', $tz_abbrev) : '');
			}
		}

		return $tz_abbrev;
	}
}
