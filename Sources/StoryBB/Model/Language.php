<?php

/**
 * This class handles languages.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Model;

use StoryBB\Model\Theme;

/**
 * This class handles languages.
 */
class Language
{
	/**
	 * Loads a given language file by absolute path, parses it for language entries and returns them.
	 *
	 * @param string $file The absolute path to the file
	 * @return array An array of language entries, split by parent, e.g. ['txt' => [...], 'helptxt' => [...]]
	 */
	public static function get_file_for_editing($file): array
	{
		global $txt, $helptxt, $txtBirthdayEmails, $editortxt;

		$language_items = [];

		// Preserve the existing values so we can restore them after this dirty hackery.
		$old_txt = $txt;
		$old_helptxt = $helptxt;
		$old_txtBirthdayEmails = $txtBirthdayEmails;
		$old_editortxt = $editortxt;

		// Having switched the things we could possibly load, let's now nuke them.
		$txt = [];
		$helptxt = [];
		$txtBirthdayEmails = [];
		$editortxt = [];

		include($file);

		foreach (['txt', 'helptxt', 'txtBirthdayEmails', 'editortxt'] as $var)
		{
			if (!empty($$var))
			{
				$language_items[$var] = $$var;
			}
		}

		// Let's restore them now.
		$txt = $old_txt;
		$helptxt = $old_helptxt;
		$txtBirthdayEmails = $old_txtBirthdayEmails;
		$editortxt = $old_editortxt;

		return $language_items;
	}

	/**
	 * Loads the changes for a specific language file.
	 *
	 */
	public static function get_language_changes(int $theme_id, string $lang_id, string $file_id): array
	{
		global $smcFunc;

		$language_delta = [];

		$result = $smcFunc['db_query']('', '
			SELECT lang_var, lang_key, lang_string, is_multi
			FROM {db_prefix}language_delta
			WHERE id_theme = {int:id_theme}
				AND id_lang = {string:lang_id}
				AND lang_file = {string:file_id}',
			[
				'id_theme' => $theme_id,
				'lang_id' => $lang_id,
				'file_id' => $file_id,
			]
		);
		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			if ($row['is_multi'])
			{
				$row['lang_string'] = @json_decode($row['lang_string'], true);
			}

			$language_delta[$row['lang_var']][$row['lang_key']] = $row['lang_string'];
		}
		$smcFunc['db_free_result']($result);

		return $language_delta;
	}

	public static function cache_language(int $theme_id, string $lang_id, string $lang_file)
	{
		global $settings, $cachedir;

		$overall_language_entries = [];

		// Default English first.
		if ($language_file = self::find_language_file(1, 'en-us', $lang_file))
		{
			$overall_language_entries = self::get_file_for_editing($language_file);
			$language_delta = self::get_language_changes(1, 'en-us', $lang_file);
			foreach ($language_delta as $lang_var => $lang_entries)
			{
				foreach ($lang_entries as $lang_key => $lang_string)
				{
					$overall_language_entries[$lang_var][$lang_key] = $lang_string;
				}
			}
		}

		// Then default whichever language we wanted next.
		if ($lang_id != 'en-us')
		{
			if ($language_file = self::find_language_file(1, $lang_id, $lang_file))
			{
				$this_language_entries = self::get_file_for_editing($language_file);
				foreach ($this_language_entries as $lang_var => $lang_entries)
				{
					foreach ($lang_entries as $lang_key => $lang_string)
					{
						$overall_language_entries[$lang_var][$lang_key] = $lang_string;
					}
				}

				$language_delta = self::get_language_changes(1, $lang_id, $lang_file);
				foreach ($language_delta as $lang_var => $lang_entries)
				{
					foreach ($lang_entries as $lang_key => $lang_string)
					{
						$overall_language_entries[$lang_var][$lang_key] = $lang_string;
					}
				}
			}
		}

		// Now we need to assemble it into something we can use.
		$cachefile = '<?php if (!defined(\'STORYBB\')) die; ';
		foreach ($overall_language_entries as $lang_var => $entries)
		{
			$cachefile .= '$' . $lang_var . ' = array_merge($' . $lang_var . ', ' . var_export($entries, true) . ');';
		}

		if (!file_exists($cachedir . '/lang'))
		{
			mkdir($cachedir . '/lang');
		}
		@file_put_contents($cachedir . '/lang/' . $theme_id . '_' . $lang_id . '_' . $lang_file . '.php', $cachefile);
		if (function_exists('opcache_invalidate'))
		{
			opcache_invalidate($cachedir . '/lang/' . $theme_id . '_' . $lang_id . '_' . $lang_file . '.php', true);
		}
	}

	public static function find_language_file(int $theme_id, string $lang_id, string $file_id): string
	{
		$themes = Theme::get_theme_list();
		// Does the theme exist?
		if (!isset($themes[$theme_id]))
		{
			return '';
		}

		// Does the theme have a language?
		if (!file_exists($themes[$theme_id]['theme_dir'] . '/languages/' . $lang_id))
		{
			return '';
		}

		// Does it have the right language file?
		if (!file_exists($themes[$theme_id]['theme_dir'] . '/languages/' . $lang_id . '/' . $file_id . '.php'))
		{
			return '';
		}

		return $themes[$theme_id]['theme_dir'] . '/languages/' . $lang_id . '/' . $file_id . '.php';
	}

	/**
	 * Delete a language entry.
	 */
	public static function delete_current_entry(int $theme_id, string $lang_id, string $lang_file, string $lang_var, string $lang_key)
	{
		global $smcFunc;

		$result = $smcFunc['db_query']('', '
			DELETE FROM {db_prefix}language_delta
			WHERE id_theme = {int:id_theme}
				AND id_lang = {string:lang_id}
				AND lang_file = {string:lang_file}
				AND lang_var = {string:lang_var}
				AND lang_key = {string:lang_key}',
			[
				'id_theme' => $theme_id,
				'lang_id' => $lang_id,
				'lang_file' => $lang_file,
				'lang_var' => $lang_var,
				'lang_key' => $lang_key,
			]
		);

		self::cache_language($theme_id, $lang_id, $lang_file);
	}

	/**
	 * Save a single language entry.
	 */
	public static function save_single_entry(int $theme_id, string $lang_id, string $lang_file, string $lang_var, string $lang_key, string $entry)
	{
		global $smcFunc;

		$smcFunc['db_insert']('replace',
			'{db_prefix}language_delta',
			['id_theme' => 'int', 'id_lang' => 'string-5', 'lang_file' => 'string-64', 'lang_var' => 'string-20', 'lang_key' => 'string-64', 'lang_string' => 'string-65534', 'is_multi' => 'int'],
			[$theme_id, $lang_id, $lang_file, $lang_var, $lang_key, $entry, 0],
			['id_theme', 'id_lang', 'lang_file', 'lang_var', 'lang_key']
		);

		self::cache_language($theme_id, $lang_id, $lang_file);
	}

	/**
	 * Save a multiple language entry.
	 */
	public static function save_multiple_entry(int $theme_id, string $lang_id, string $lang_file, string $lang_var, string $lang_key, array $entry)
	{
		global $smcFunc;

		$smcFunc['db_insert']('replace',
			'{db_prefix}language_delta',
			['id_theme' => 'int', 'id_lang' => 'string-5', 'lang_file' => 'string-64', 'lang_var' => 'string-20', 'lang_key' => 'string-64', 'lang_string' => 'string-65534', 'is_multi' => 'int'],
			[$theme_id, $lang_id, $lang_file, $lang_var, $lang_key, json_encode($entry), 1],
			['id_theme', 'id_lang', 'lang_file', 'lang_var', 'lang_key']
		);

		self::cache_language($theme_id, $lang_id, $lang_file);
	}
}
