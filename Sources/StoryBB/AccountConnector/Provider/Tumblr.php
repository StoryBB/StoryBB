<?php

/**
 * A library for making .wav files for the CAPTCHA.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\AccountConnector\Provider;

use StoryBB\AccountConnector\Protocol\OAuth1\OAuth1;
use StoryBB\AccountConnector\Protocol\OAuth1\Signature\HMACSignature;
use RuntimeException;

class Tumblr extends OAuth1
{
	use HMACSignature;

	const URL_TEMPORARY_CREDENTIALS = 'https://www.tumblr.com/oauth/request_token';
	const URL_AUTHORIZATION = 'https://www.tumblr.com/oauth/authorize';
	const URL_TOKEN_CREDENTIALS = 'https://www.tumblr.com/oauth/access_token';
	const PROVIDER_ID = 'tumblr';

	public function evaluate_state()
	{
		if (isset($_SESSION['token_credentials']))
		{
			// Step 3: We're all logged in.
		}
		elseif (isset($_GET['oauth_token']) && isset($_GET['oauth_verifier']))
		{
			// Step 2: We've come back from the OAuth login, let's match this up with our stuff.
			if (empty($_SESSION['temp_credentials']))
			{
				throw new RuntimeException('No temporary credentials in session');
			}
			$temporary_credentials = $_SESSION['temp_credentials'];
			$_SESSION['token_credentials'] = $this->get_actual_credentials($temporary_credentials, $_GET['oauth_token'], $_GET['oauth_verifier']);
			redirectexit('action=connect&connector=tumblr');
		}
		else
		{
			// Step 1: We don't have temporary credentials, let's fix that.
			if (!isset($_SESSION['temp_credentials']))
			{
				$_SESSION['temp_credentials'] = $this->get_temporary_credentials();
			}
			$this->login($_SESSION['temp_credentials']);
		}
	}

	public function get_provider_key()
	{
		
	}
}
