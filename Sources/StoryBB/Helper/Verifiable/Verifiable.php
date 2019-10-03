<?php

/**
 * Verifiables (CAPTCHA types) need to implement this interface.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Verifiable;

use StoryBB\Discoverable;

interface Verifiable extends Discoverable
{
	public function __construct(string $id);

	public function is_available(): bool;

	public function reset();

	public function verify();

	public function render();

	public function get_settings(): array;

	public function put_settings(&$save_vars);
}
