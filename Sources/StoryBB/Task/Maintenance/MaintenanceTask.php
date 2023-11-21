<?php

/**
 * Every maintenance task should implement this.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Maintenance;

use StoryBB\Discoverable;
use StoryBB\Phrase;

/**
 * Base class for every adhoc task which also functions as a sort of interface as well.
 */
interface MaintenanceTask extends Discoverable
{
	public function get_name(): Phrase;

	public function get_description(): Phrase;

	public function execute(): bool;
}
