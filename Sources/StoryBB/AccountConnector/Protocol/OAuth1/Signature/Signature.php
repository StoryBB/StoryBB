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

use StoryBB\AccountConnector\Protocol\OAuth1\ClientCredentials;
use StoryBB\AccountConnector\Protocol\OAuth1\Credentials;

trait Signature
{
	protected $client_credentials;

	protected $temporary_credentials;

	public function set_client_credentials(ClientCredentials $credentials)
	{
		$this->client_credentials = $credentials;
	}

	public function set_temporary_credentials(Credentials $credentials)
	{
		$this->temporary_credentials = $credentials;
	}

	protected function get_key(): string
	{
		$key = rawurlencode($this->client_credentials->get_secret()) . '&';
		if ($this->temporary_credentials !== null) {
			$key .= rawurlencode($this->temporary_credentials->get_secret());
		}
		return $key;
	}

	protected function get_base_string(string $url, $method = 'POST', array $params = []): string
	{
		// First we add the method.
		$signature[] = rawurlencode($method);

		// Now we decompose the URL we received down, because any querystring params need to be mixed with our new params.
		$urlparts = parse_url($url);
		if (empty($urlparts['scheme']))
		{
			throw new RuntimeException('Invalid URL with no scheme: ' . $url);
		}
		if (empty($urlparts['host']))
		{
			throw new RuntimeException('Invalid URL with no host: ' . $url);
		}
		$desturl = $urlparts['scheme'] . '://' . $urlparts['host'] . (isset($urlparts['path']) ? $urlparts['path'] : '/');
		$signature[] = rawurlencode($desturl);

		// Now let's get things out of the query string if there were any.
		if (!empty($urlparts['query']))
		{
			parse_str($urlparts['query'], $qs_data);
			$params = array_merge($qs_data, $parameters);
		}

		// Now we need to encode everything in a nicely normalised way.
		array_walk_recursive($params, function (&$k, &$v) {
			$k = rawurlencode(rawurldecode($k));
			$v = rawurlencode(rawurldecode($v));
		});

		// Sort into key order once safely encoded.
		ksort($params);

		$signature[] = $this->build_query_string($params);

		return implode('&', $signature);
	}

	protected function build_query_string1($data, $queryParams = false, $prevKey = '')
	{
		if ($initial = (false === $queryParams)) {
			$queryParams = array();
		}

		foreach ($data as $key => $value) {
			if ($prevKey) {
				$key = $prevKey.'['.$key.']'; // Handle multi-dimensional array
			}
			if (is_array($value)) {
				$queryParams = $this->queryStringFromData($value, $queryParams, $key);
			} else {
				$queryParams[] = rawurlencode($key.'='.$value); // join with equals sign
			}
		}

		if ($initial) {
			return implode('%26', $queryParams); // join with ampersand
		}

		return $queryParams;
	}

	protected function build_query_string(array $data, $params = false, string $previous_key = '')
	{
		// Did we pass something in from a higher level?
		$first = false;
		if ($params === false)
		{
			$first = true;
			$params = [];
		}

		// Step through each key and work out what we're doing with it.
		foreach ($data as $k => $v) {
			// We may have had a key from the previous level for multi-dimensional arrays.
			if ($previous_key) {
				$k = $previous_key . '[' . $k . ']';
			}

			if (is_array($v)) {
				$params = $this->build_query_string($v, $params, $k);
			} else {
				$params[] = rawurlencode($k . '=' . $v);
			}
		}

		// If this is our original loop, we want to join everything up with &.
		// If not, we want to return just this iteration's worth.
		return $first ? implode('%26', $params) : $params;
	}
}
