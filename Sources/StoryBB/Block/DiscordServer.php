<?php

/**
 * Discord server block.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Block;

use StoryBB\StringLibrary;
use GuzzleHttp\Client;
use Exception;

/**
 * The recent posts block.
 */
class DiscordServer extends AbstractBlock implements Block
{
	protected $config;
	protected $content;

	public function __construct($config = [])
	{
		$this->config = $config;
	}

	public function get_name(): string
	{
		global $txt;
		return $txt['online_in_discord'];
	}

	public function get_default_title(): string
	{
		return 'txt.online_in_discord';
	}

	public function get_block_content(): string
	{
		global $user_info, $txt;

		if ($this->content !== null)
		{
			return $this->content;
		}
		elseif ($this->content === null)
		{
			$this->content = '';
		}

		if (empty($this->config['server']))
		{
			return $this->content;
		}

		$avatars = !empty($this->config['avatars']);

		$server_url = 'https://discord.com/api/guilds/' . $this->config['server'] . '/widget.json';
		$client = new Client();

		try
		{
			$http_request = $client->get($server_url);

			switch ($http_request->getStatusCode())
			{
				case 200:
					$contents = (string) $http_request->getBody();
					break;
				case 403:
					// Widget not configured.
					return $this->content;
					break;
				case 429:
					// Widget rate-limited.
					return $this->content;
					break;
				default:
					// Something else went wrong.
					return $this->content;
					break;
			}
		}
		catch (Exception $e)
		{
			return $this->content;
		}

		if (empty($contents))
		{
			return $this->content;
		}

		$server_response = @json_decode($contents, true);

		$online = [];
		foreach ($server_response['members'] as $member)
		{
			$online[] = [
				'name' => StringLibrary::escape($member['username']),
				'avatar' => $avatars ? $member['avatar_url'] : false,
			];
		}

		$this->content = $this->render('block_discord_server', [
			'invite_link' => !empty($server_response['instant_invite']) ? $server_response['instant_invite'] : false,
			'online_string' => numeric_context('num_users_online', count($online)),
			'online' => $online,
			'txt' => $txt,
		]);
		return $this->content;
	}
}
