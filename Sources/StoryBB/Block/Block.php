<?php

/**
 * Defines the methods required to be implemented by a block.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Block;

use StoryBB\Discoverable;

/**
 * These methods should all be implemented for a search backend to successfully a block.
 */
interface Block extends Discoverable
{
	public function __construct($config = []);

	public function get_name(): string;

	public function get_default_title(): string;

	public function get_block_title(): string;

	public function get_block_content(): string;

	public function get_render_template(): string;
}
