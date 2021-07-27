<?php

/**
 * A recent online block.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Block;

use StoryBB\Model\Group;

/**
 * The recent online block.
 */
class RecentlyOnline extends AbstractBlock implements Block
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
		return $txt['recently_online'];
	}

	public function get_default_title(): string
	{
		return 'txt.recently_online';
	}

	public function get_block_content(): string
	{
		global $txt, $scripturl, $modSettings;

		if ($this->content !== null)
		{
			return $this->content;
		}
		elseif ($this->content === null)
		{
			$this->content = '';
		}

		$display = !empty($this->config['display']) && in_array($this->config['display'], ['avatar', 'name', 'both']) ? $this->config['display'] : 'both';
		$scope = !empty($this->config['show']) && $this->config['show'] == 'account' ? 'account' : 'character';
		$order = !empty($this->config['order']) && in_array($this->config['order'], ['name', 'oldest', 'newest']) ? $this->config['order'] : 'name';

		if (empty($this->config['since']))
		{
			$since = time() - 86400;
		}
		else
		{
			$since = time() - $this->config['since'];
		}

		$this->content = $this->render('block_recently_online', [
			'online' => $scope == 'account' ? $this->get_accounts($since, $order) : $this->get_characters($since, $order),
			'display' => $display,
			'txt' => $txt,
			'scripturl' => $scripturl,
			'modSettings' => $modSettings,
		]);
		return $this->content;
	}

	protected function get_accounts(int $since, string $order): array
	{
		global $smcFunc;

		switch ($order)
		{
			case 'oldest':
				$order_by = 'mem.last_login ASC';
				break;
			case 'newest':
				$order_by = 'mem.last_login DESC';
				break;
			case 'name':
			default:
				$order_by = 'chars.character_name';
				break;
		}

		return $this->format_results($smcFunc['db']->query('', '
			SELECT mem.id_member, chars.id_character, chars.is_main, chars.character_name, chars.avatar, COALESCE(a.id_attach, 0) AS id_attach, a.filename
			FROM {db_prefix}members AS mem
			INNER JOIN {db_prefix}characters AS chars ON (chars.id_member = mem.id_member AND chars.is_main = {int:is_main})
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_character = chars.id_character AND a.attachment_type = 1)
			WHERE mem.last_login > {int:since}
			ORDER BY ' . $order_by, [
				'since' => $since,
				'is_main' => 1,
			])
		);
	}

	protected function get_characters(int $since, string $order): array
	{
		global $smcFunc;

		switch ($order)
		{
			case 'oldest':
				$order_by = 'chars.last_active ASC';
				break;
			case 'newest':
				$order_by = 'chars.last_active DESC';
				break;
			case 'name':
			default:
				$order_by = 'chars.character_name';
				break;
		}

		return $this->format_results($smcFunc['db']->query('', '
			SELECT mem.id_member, chars.id_character, chars.is_main, chars.character_name, chars.avatar, COALESCE(a.id_attach, 0) AS id_attach, a.filename
			FROM {db_prefix}members AS mem
			INNER JOIN {db_prefix}characters AS chars ON (chars.id_member = mem.id_member AND chars.is_main != {int:is_main})
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_character = chars.id_character AND a.attachment_type = 1)
			WHERE chars.last_active > {int:since}
			ORDER BY ' . $order_by, [
				'since' => $since,
				'is_main' => 1,
			])
		);
	}

	protected function format_results($result)
	{
		global $smcFunc, $scripturl;
		$online = [];

		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			$online[] = [
				'avatar' => set_avatar_data([
					'avatar' => $row['avatar'],
					'filename' => $row['filename'],
				]),
				'name' => $row['character_name'],
				'link' => !empty($row['is_main']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : $scripturl . '?action=profile;u=' . $row['id_member'] . ';area=characters;char=' . $row['id_character'],
			];
		}
		$smcFunc['db']->free_result($result);

		return $online;
	}
}
