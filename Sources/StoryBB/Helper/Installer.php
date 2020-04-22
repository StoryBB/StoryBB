<?php

/**
 * Support functions for installation.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

use StoryBB\App;
use StoryBB\Database\DatabaseAdapter;
use StoryBB\Dependency\Database;
use StoryBB\Dependency\Filesystem;

/**
 * Support functions for managing files.
 */
class Installer
{
	use Database;
	use Filesystem;

	public function upload_favicon(): int
	{
		$filesystem = $this->filesystem();

		$filesystem->copy_physical_file(App::get_root_path() . '/install_resources/favicon.ico', 'favicon.ico', 'image/x-icon', 'favicon', 0);

		return 1;
	}

	public function upload_smileys(): int
	{
		$db = $this->db();
		$filesystem = $this->filesystem();
		$smileydir = App::get_root_path() . '/Smileys';

		// First, get the smileys.
		$request = $db->query('', '
			SELECT id_smiley, filename
			FROM {db_prefix}smileys
			ORDER BY id_smiley');

		$mimetypes = [
			'gif' => 'image/gif',
			'jpg' => 'image/jpeg',
			'png' => 'image/png',
			'svg' => 'image/svg+xml',
		];

		$db_queries = 1;
		while ($row = $db->fetch_assoc($request))
		{
			$db_queries++;
			$ext = strtolower(substr(strrchr($row['filename'], '.'), 1));
			$mimetype = $mimetypes[$ext];
			$filesystem->copy_physical_file($smileydir . '/' . $row['filename'], $row['filename'], $mimetype, 'smiley', $row['id_smiley']);
		}

		return $db_queries;
	}

	public function add_standard_blocks(): int
	{
		$db = $this->db();

		$visibility = [
			'action' => ['forum'],
		];
		$configuration = [
			'title' => 'txt.info_center',
			'template' => 'block__roundframe_titlebg',
			'subblock_template' => 'block__subbg',
			'collapsible' => true,
			'blocks' => [
				[
					'class' => 'StoryBB\\Block\\RecentPosts',
					'config' => [
						'number_recent_posts' => 10,
						'icon' => 'history',
					],
				],
				[
					'class' => 'StoryBB\\Block\\MiniStats',
					'config' => [
						'show_latest_member' => true,
						'icon' => 'stats',
					],
				],
				[
					'class' => 'StoryBB\\Block\\WhosOnline',
					'config' => [
						'show_group_key' => true,
						'icon' => 'people',
					],
				]
			]
		];

		$db->insert(DatabaseAdapter::INSERT_INSERT,
			'{db_prefix}block_instances',
			['class' => 'string', 'visibility' => 'string', 'configuration' => 'string', 'region' => 'string', 'position' => 'int', 'active' => 'int'],
			['StoryBB\\Block\\Multiblock', json_encode($visibility), json_encode($configuration), 'after-content', 1, 1],
			['id_instance'],
			DatabaseAdapter::RETURN_NOTHING
		);

		return 1;
	}

	public function add_admin_blocks(): int
	{
		$db = $this->db();

		$visibility = [
			'action' => ['admin'],
		];

		$blocks = [
			[
				'StoryBB\\Block\\StoryBBNews',
				json_encode($visibility),
				json_encode([]),
				'admin-home',
				1,
				1,
			],
			[
				'StoryBB\\Block\\SupportInformation',
				json_encode($visibility),
				json_encode([]),
				'admin-home',
				2,
				1,
			]
		];

		$db->insert(DatabaseAdapter::INSERT_INSERT,
			'{db_prefix}block_instances',
			['class' => 'string', 'visibility' => 'string', 'configuration' => 'string', 'region' => 'string', 'position' => 'int', 'active' => 'int'],
			$blocks,
			['id_instance'],
			DatabaseAdapter::RETURN_NOTHING
		);

		return 1;
	}

	public function add_default_user_preferences(): int
	{
		$db = $this->db();

		$db->insert(DatabaseAdapter::INSERT_INSERT,
			'{db_prefix}user_preferences',
			['id_member' => 'int', 'preference' => 'string', 'value' => 'string'],
			[
				[0, 'posts_apply_ignore_list', '1'],
				[0, 'return_to_post', 1],
				[0, 'show_avatars', 1],
				[0, 'show_signatures', 1],
			],
			['id_preference'],
			DatabaseAdapter::RETURN_NOTHING
		);
	}
}
