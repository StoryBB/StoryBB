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

namespace StoryBB\AccountConnector\Protocol\OAuth1\Signature;

use StoryBB\AccountConnector\Protocol\OAuth1\Signature\Signature;

trait HMACSignature
{
	use Signature;

	public function sign(string $uri, array $parameters = array(), string $method = 'POST'): string
	{
		$base = $this->get_base_string($uri, $method, $parameters);
		$hash = hash_hmac('sha1', $base, $this->get_key(), true);
		return base64_encode($hash);
	}

	public function get_signature_method(): string
	{
		return 'HMAC-SHA1';
	}
}
