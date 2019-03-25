<?php

/**
 * A core for handling OAuth1 connections.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\AccountConnector\Protocol\OAuth1;

use RuntimeException;

class Credentials
{
	protected $identifier;
	protected $secret;

	public function get_identifier(): string
	{
		if ($this->identifier === null)
		{
			throw new RuntimeException('Credentials not yet configured');
		}
		return $this->identifier;
	}

	public function set_identifier(string $identifier)
	{
		$this->identifier = $identifier;
	}

	public function get_secret(): string
	{
		if ($this->secret === null)
		{
			throw new RuntimeException('Credentials not yet configured');
		}
		return $this->secret;
	}

	public function set_secret(string $secret)
	{
		$this->secret = $secret;
	}
}
