<?php
/**
 * Fetch the latest version info/news from storybb.org.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Schedulable;

use StoryBB\App;
use GuzzleHttp\Client;

/**
 * Fetch the latest version info/news from storybb.org.
 */
class FetchStoryBBFiles extends \StoryBB\Task\AbstractSchedulable implements \StoryBB\Task\Schedulable
{
	/**
	 * Get the human-readable name for this task.
	 * @return string The human readable name.
	 */
	public function get_name(): string
	{
		global $txt;
		return $txt['scheduled_task_fetchStoryBBfiles'];
	}

	/**
	 * Get the human-readable description for this task.
	 * @return string The task description.
	 */
	public function get_description(): string
	{
		global $txt;
		return $txt['scheduled_task_desc_fetchStoryBBfiles'];
	}

	/**
	 * Fetch the latest version info/news from storybb.org.
	 * @return bool True on success
	 */
	public function execute(): bool
	{
		global $txt, $language, $modSettings, $smcFunc;

		// What files do we want to get
		$request = $smcFunc['db']->query('', '
			SELECT id_file, filename, path, parameters
			FROM {db_prefix}admin_info_files',
			[
			]
		);

		$js_files = [];

		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$js_files[$row['id_file']] = [
				'filename' => $row['filename'],
				'path' => $row['path'],
				'parameters' => sprintf($row['parameters'], $language, urlencode($modSettings['time_format']), urlencode(App::SOFTWARE_VERSION)),
			];
		}

		$smcFunc['db']->free_result($request);

		// Just in case we run into a problem.
		loadEssentialThemeData();
		loadLanguage('Errors', $language, false);

		foreach ($js_files as $ID_FILE => $file)
		{
			// Create the url
			$server = empty($file['path']) || (substr($file['path'], 0, 7) != 'http://' && substr($file['path'], 0, 8) != 'https://') ? 'https://storybb.org/' : '';
			$url = $server . (!empty($file['path']) ? $file['path'] : $file['path']) . $file['filename'] . (!empty($file['parameters']) ? '?' . $file['parameters'] : '');

			// Get the file
			$client = new Client();
			$http_request = $client->get($url);
			$file_data = (string) $http_request->getBody();

			// If we got an error - give up - the site might be down. And if we should happen to be coming from elsewhere, let's also make a note of it.
			if (empty($file_data))
			{
				throw new Exception(sprintf($txt['st_cannot_retrieve_file'], $url));
			}

			// Save the file to the database.
			$smcFunc['db']->query('substring', '
				UPDATE {db_prefix}admin_info_files
				SET data = SUBSTRING({string:file_data}, 1, 65534)
				WHERE id_file = {int:id_file}',
				[
					'id_file' => $ID_FILE,
					'file_data' => $file_data,
				]
			);
		}
		return true;
	}
}
