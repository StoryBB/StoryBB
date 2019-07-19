<?php

/**
 * This class handles validation against ReCAPTCHA.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Verification;

use GuzzleHttp\Client;

/**
 * This class handles validation against ReCAPTCHA.
 */
class Recaptcha
{
	/**
	 * @var string Holds the secret key needed for ReCAPTCHA.
	 */
	private $secret_key;

	/**
	 * Creates the instance for ReCAPTCHA.
	 *
	 * @param string $secret_key The secret key to be used with this instance of ReCAPTCHA.
	 */
	public function __construct($secret_key)
	{
		$this->secret_key = $secret_key;
	}

	/**
	 * Verifies the CAPTCHA value given by the user when completing the ReCAPTCHA instance.
	 *
	 * @return bool True if the CAPTCHA was completed successfully.
	 */
	public function verify()
	{
		global $user_info, $sourcedir;

		// No answer supplied at all?
		if (empty($_POST['g-recaptcha-response']))
		{
			return false;
		}

		$client = new Client();
		$response = $client->post('https://www.google.com/recaptcha/api/siteverify', [
			'form_params' => [
				'secret' => $this->secret_key,
				'response' => $_POST['g-recaptcha-response'],
				'remoteip' => $user_info['ip'],
			]
		]);
		$body = (string) $response->getBody();

		if (!empty($body))
		{
			$json = json_decode($body);
			if (!empty($json) && !empty($json->success))
			{
				return true;
			}
		}

		return false;
	}
}
