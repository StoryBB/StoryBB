<?php

/**
 * This interface represents Discord integration.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Integration;

use StoryBB\Container;
use StoryBB\Hook\Integratable;
use StoryBB\Hook\Integratable\BoardDetails;
use StoryBB\Helper\Parser;
use StoryBB\Task;
use GuzzleHttp\Client;

class Discord implements Integration
{
	use BoardDetails;

	const NAME = 'Discord';
	const ICON = 'fab fa-discord';

	public function topic_created_settings(): array
	{
		global $txt;

		return [
			'webhook_url' => [
				'label' => $txt['integration_discord_webhook_url'],
				'sublabel' => $txt['integration_discord_webhook_url_sublabel'],
				'default' => '',
				'type' => 'url',
				'required' => true,
			],
			'message' => [
				'label' => $txt['integration_discord_topic_created_message'],
				'sublabel' => $txt['integration_discord_message_sublabel'],
				'default' => $txt['integration_discord_topic'],
				'type' => 'text',
			],
			'boards' => [
				'label' => $txt['integration_discord_topic_boards'],
				'default' => [],
				'type' => 'boards',
			],
			'embed_link' => [
				'label' => $txt['integration_discord_embed_link'],
				'default' => 1,
				'type' => 'boolean',
			],
			'embed_colour' => [
				'label' => $txt['integration_discord_embed_colour'],
				'default' => '',
				'type' => 'color',
			],
		];
	}

	public function topic_created(Integratable $integration, array $config): bool
	{
		global $txt;

		if (!$this->check_board_rules($integration, $config))
		{
			return true;
		}

		$message = $config['message'] ?? $txt['integration_discord_topic'];
		$message = str_replace(['{$subject}', '{$link}'], [$integration->topic_subject, $integration->topic_link], $message);

		$embeds = $this->prepare_post_embed($integration, $config);

		Task::queue_adhoc('StoryBB\\Task\\Adhoc\\DiscordWebhook', [
			'config' => $config,
			'message' => $message,
			'embeds' => $embeds,
			'msgid' => (int) $integration->msgOptions['id'],
		], 90);

		return true;
	}

	public function reply_created_settings(): array
	{
		global $txt;

		return [
			'webhook_url' => [
				'label' => $txt['integration_discord_webhook_url'],
				'sublabel' => $txt['integration_discord_webhook_url_sublabel'],
				'default' => '',
				'type' => 'url',
				'required' => true,
			],
			'message' => [
				'label' => $txt['integration_discord_reply_created_message'],
				'sublabel' => $txt['integration_discord_message_sublabel'],
				'default' => $txt['integration_discord_reply'],
				'type' => 'text',
			],
			'boards' => [
				'label' => $txt['integration_discord_reply_boards'],
				'default' => [],
				'type' => 'boards',
			],
			'embed_link' => [
				'label' => $txt['integration_discord_embed_link'],
				'default' => 1,
				'type' => 'boolean',
			],
			'embed_colour' => [
				'label' => $txt['integration_discord_embed_colour'],
				'default' => '',
				'type' => 'color',
			],
		];
	}

	public function reply_created(Integratable $integration, array $config): bool
	{
		global $txt;

		if (!$this->check_board_rules($integration, $config))
		{
			return true;
		}

		$message = $config['message'] ?? $txt['integration_discord_reply'];
		$message = str_replace(['{$subject}', '{$link}'], [$integration->topic_subject, $integration->topic_link], $message);

		$embeds = $this->prepare_post_embed($integration, $config);

		Task::queue_adhoc('StoryBB\\Task\\Adhoc\\DiscordWebhook', [
			'config' => $config,
			'message' => $message,
			'embeds' => $embeds,
			'msgid' => (int) $integration->msgOptions['id'],
		], 90);

		return true;
	}

	public function character_approved_settings(): array
	{
		global $txt;

		return [
			'webhook_url' => [
				'label' => $txt['integration_discord_webhook_url'],
				'sublabel' => $txt['integration_discord_webhook_url_sublabel'],
				'default' => '',
				'type' => 'url',
				'required' => true,
			],
			'message' => [
				'label' => $txt['integration_discord_character_approved_message'],
				'sublabel' => $txt['integration_discord_character_approved_message_sublabel'],
				'default' => $txt['integration_discord_new_character'],
				'type' => 'text',
			],
			'embed_colour' => [
				'label' => $txt['integration_discord_embed_colour'],
				'default' => '',
				'type' => 'color',
			],
		];
	}

	public function character_approved(Integratable $integration, array $config): bool
	{
		global $txt, $context;

		$message = $config['message'] ?? $txt['integration_discord_new_character'];
		$message = str_replace(['{$character_name}', '{$character_link}', '{$character_sheet_link}'], [$integration->character['username'], $integration->character['url'], $integration->character_sheet], $message);

		$embeds = [
			[
				'title' => $integration->character['username'],
				'url' => $integration->character['url'],
				'description' => $this->preview_post_content($integration->sheet_body, 200),
				'thumbnail' => [
					'url' => $integration->character['avatar'],
				],
			]
		];
		if (!empty($config['embed_colour']))
		{
			$embeds[0]['color'] = hexdec(substr($config['embed_colour'], 1));
		}

		return $this->send_webhook($config['webhook_url'], $message, $context['forum_name'], $this->get_icon_url(), $embeds);
	}

	protected function check_board_rules(Integratable $integration, array $config): bool
	{
		// No boards rules? Apply every time then.
		if (!isset($config['boards']))
		{
			return true;
		}

		if (isset($config['boards']))
		{
			// If it's a board in the known list of boards, run with it.
			if (in_array($integration->topicOptions['board'], $config['boards']))
			{
				return true;
			}
			else
			{
				// Otherwise find out if the user has picked 'all IC' or 'all OOC' and if we're in that type of board.
				$ooc = in_array('ooc', $config['boards']);
				$ic = in_array('ic', $config['boards']);

				if ($ooc || $ic)
				{
					$board_is_ic = $this->is_character_board((int) $integration->topicOptions['board']);
					if (($board_is_ic && $ic) || (!$board_is_ic && $ooc))
					{
						return true;
					}
				}
			}
		}

		return false;
	}

	public function send_webhook(string $url, string $message, ?string $username = null, ?string $avatar = null, ?array $embeds = []): bool
	{
		$client = new Client([
			'base_uri' => $url,
			'timeout' => 2,
		]);

		$opts = [
			'content' => $message,
			'embeds' => $embeds ?? [],
		];
		if ($username)
		{
			$opts['username'] = $username;
		}
		if ($avatar)
		{
			$opts['avatar_url'] = $avatar;
		}

		try
		{
			$client->post($url, [
				'json' => $opts,
			]);
		}
		catch (Exception $e)
		{
			error_log($e->getMessage());
			return false;
		}

		return true;
	}

	protected function prepare_post_embed(Integratable $integration, array $config): array
	{
		if (empty($config['embed_link']))
		{
			return [];
		}
		$embeds = [
			[
				'title' => $integration->topic_subject,
				'url' => $integration->topic_link,
				'description' => $this->preview_post_content($integration->msgOptions['body']),
			]
		];

		if (!empty($config['embed_colour']))
		{
			$embeds[0]['color'] = hexdec(substr($config['embed_colour'], 1));
		}
		if ($icon = $this->get_icon_url())
		{
			$embeds[0]['thumbnail']['url'] = $icon;
		}

		return $embeds;
	}

	protected static function preview_post_content(string $content, int $preview_length = 300): string
	{
		$content = strip_tags(preg_replace('/<br ?\/?>/i', "\n", Parser::parse_bbc($content)));
		$content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

		return shorten_subject($content, $preview_length);
	}

	public function get_icon_url(): ?string
	{
		global $modSettings;

		if (!empty($modSettings['favicon_cache']))
		{
			$favicons = json_decode($modSettings['favicon_cache'], true);

			$container = Container::instance();
			$urlgenerator = $container->get('urlgenerator');

			$sizes = [
				7 => [192, 192],
				3 => [180, 180],
				5 => [167, 167],
				4 => [152, 152],
				6 => [128, 128],
			];

			foreach ($sizes as $favicon_id => $size)
			{
				if (isset($favicons['favicon_' . $favicon_id]))
				{
					return $urlgenerator->generate('favicon', ['id' => $favicon_id, 'timestamp' => $favicons['favicon_' . $favicon_id]]);
				}
			}
		}

		return null;
	}
}
