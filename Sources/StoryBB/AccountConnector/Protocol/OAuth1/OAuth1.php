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

use StoryBB\AccountConnector\Protocol\OAuth1\ClientCredentials;
use StoryBB\AccountConnector\Protocol\OAuth1\Credentials;
use RuntimeException;
use DateTime;
use GuzzleHttp\Client;

abstract class OAuth1
{
	const URL_TEMPORARY_CREDENTIALS = 'https://example.com/oauth1/request_token';
	const URL_AUTHORIZATION = 'https://example.com/oauth1/authorize';
	const URL_TOKEN_CREDENTIALS = 'https://example.com/oauth1/access_token';
	const PROVIDER_ID = 'abstract';

	abstract public function sign(string $uri, array $parameters = array(), string $method = 'POST'): string;
	abstract public function get_signature_method(): string;

	abstract public function set_client_credentials(ClientCredentials $credentials);

	abstract public function set_temporary_credentials(Credentials $credentials);

	public function __construct(array $credentials)
	{
		if (!isset($credentials['consumer_key'], $credentials['secret_key']))
		{
			throw new RuntimeException('Consumer key or secret key not provided.');
		}
		$client_credentials = new ClientCredentials;
		$client_credentials->set_identifier($credentials['consumer_key']);
		$client_credentials->set_secret($credentials['secret_key']);

		$this->set_client_credentials($client_credentials);
	}

	/**
	 * The OAuth flow looks something like this:
	 * 0. User indicates they want to log in with a network via action=connect&connector=socialnetworkname
	 * 1. StoryBB requests temporary credentials from social network, then sends users to login
	 * 2. Assuming user logs in successfully, take our temporary credentials plus the login token and exchange for real token
	 * 3. User is now authenticated.
	 *
	 * This function should be taken as a generic entry point and do all the state management stuff.
	 */
	abstract public function evaluate_state();

	public function get_temporary_credentials(): Credentials
	{
		global $scripturl;

		$url = static::URL_TEMPORARY_CREDENTIALS;

		// Build the OAuth Authorization header.
		$params = $this->get_oauth_params();
		$params['oauth_callback'] = $scripturl . '?action=connect&connector=' . static::PROVIDER_ID;
		$params['oauth_signature'] = $this->sign($url, $params, 'POST');
		$header = $this->normalise_oauth_header($params);

		$client = new Client([
			'base_url' => $url,
		]);
		try
		{
			$response = $client->post($url, [
				'headers' => [
					'Authorization' => $header,
				],
			]);
			$body = (string) $response->getBody();
		}
		catch (\Exception $e)
		{
			throw $e; // Dirty debug step.
		}

		$body = (string) $response->getBody();
		parse_str($body, $data);

		if (!isset($data['oauth_callback_confirmed']) || $data['oauth_callback_confirmed'] != 'true')
		{
			throw new RuntimeException('Could not identify retrieve credentials');
		}

		$credentials = new Credentials;
		$credentials->set_identifier($data['oauth_token']);
		$credentials->set_secret($data['oauth_token_secret']);

		return $credentials;
	}

	public function get_actual_credentials(Credentials $temporary_credentials, string $oauth_token, string $oauth_verifier): Credentials
	{
		$this->set_temporary_credentials($temporary_credentials);

		// First, we compare the OAuth token that came back from the server with what we already had stored.
		// This helps prevent MITM between us and the OAuth provider.
		if ($temporary_credentials->get_identifier() !== $oauth_token)
		{
			throw new RuntimeException('OAuth token mismatch');
		}

		$url = static::URL_TOKEN_CREDENTIALS;
		$body = [
			'oauth_verifier' => $oauth_verifier,
		];

		$params = $this->get_oauth_params();
		$params['oauth_token'] = $temporary_credentials->get_identifier();
		$params['oauth_signature'] = $this->sign($url, array_merge($params, $body), 'POST');

		$headers = [
			'Authorization' => $this->normalise_oauth_header($params),
		];

		$client = new Client([
			'base_url' => $url,
		]);

		try
		{
			$response = $client->post($url, [
				'headers' => $headers,
				'form_params' => $body,
			]);
			$token = (string) $response->getBody();
		}
		catch (\Exception $e)
		{
			throw $e; // Dirty debug hack.
		}

		$body = (string) $response->getBody();
		parse_str($body, $data);

		if (!isset($data['oauth_token']) || !isset($data['oauth_token_secret']))
		{
			throw new RuntimeException('Could not identify retrieve credentials');
		}

		$credentials = new Credentials;
		$credentials->set_identifier($data['oauth_token']);
		$credentials->set_secret($data['oauth_token_secret']);

		return $credentials;
	}

	protected function get_oauth_params()
	{
		return [
			'oauth_consumer_key' => $this->client_credentials->get_identifier(),
			'oauth_nonce' => $this->create_nonce(),
			'oauth_signature_method' => $this->get_signature_method(),
			'oauth_timestamp' => (new DateTime)->format('U'),
			'oauth_version' => '1.0',
		];
	}

	protected function normalise_oauth_header(array $params): string
	{
		$values = [];
		foreach ($params as $k => $v)
		{
			$values[] = rawurlencode($k) . '="' . rawurlencode($v) . '"';
		}

        return 'OAuth ' . implode(', ', $values);
	}

	/**
	 * Generate a random one-time string for the OAuth exchange as per RFC5849,
	 * section 3.3; no requirement is given for CSPRNG entropy.
	 *
	 * @param int $length Length of the nonce
	 * @return string
	 */
	protected function create_nonce(int $length = 32): string
	{
		$pool = array_merge(range(0, 9), range('a', 'z'), range('A', 'Z'));

		$nonce = [];
		for ($i = 0; $i < $length; $i++) {
			$nonce[] = $pool[array_rand($pool)];
		}
		return implode('', $nonce);
	}

	public function login(Credentials $temporary_credentials)
	{
		$url = $this->get_authorisation_url($temporary_credentials);
		redirectexit($url);
	}

	public function get_authorisation_url(Credentials $temporary_credentials): string
	{
		$params = [
			'oauth_token' => $temporary_credentials->get_identifier(),
		];

		return $this->build_url(static::URL_AUTHORIZATION, $params);
	}

	protected function build_url(string $url, array $params): string
	{
		return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
	}
}
