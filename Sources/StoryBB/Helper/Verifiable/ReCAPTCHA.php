<?php

/**
 * The ReCAPTCHA implementation.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Verifiable;

use StoryBB\Helper\Verifiable\AbstractVerifiable;
use StoryBB\Helper\Verifiable\UnverifiableException;
use GuzzleHttp\Client;

class ReCAPTCHA extends AbstractVerifiable implements Verifiable
{
	protected $id;
	protected $secret_key;

	public function __construct(string $id)
	{
		global $modSettings;

		parent::__construct($id);
		$this->secret_key = $modSettings['recaptcha_secret_key'] ?? '';
	}

	public function is_available(): bool
	{
		global $modSettings;

		return !empty($modSettings['recaptcha_enabled']) && !empty($modSettings['recaptcha_site_key']) && !empty($modSettings['recaptcha_secret_key']);
	}

	public function reset()
	{
		return; // There is nothing to do as this is all client/3rd-party handled.
	}

	public function verify()
	{
		global $user_info, $txt;

		// No answer supplied at all?
		if (empty($_POST['g-recaptcha-response']))
		{
			throw new UnverifiableException($txt['error_recaptcha_not_complete']);
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
				return;
			}
		}

		throw new UnverifiableException($txt['error_recaptcha_not_complete']);
	}

	public function render()
	{
		global $modSettings, $txt;

		$template = \StoryBB\Template::load_partial('control_verification_recaptcha');
		$phpStr = \StoryBB\Template::compile($template, [], 'control_verification_recaptcha-' . \StoryBB\Template::get_theme_id('partials', 'control_verification_recaptcha'));
		return new \LightnCandy\SafeString(\StoryBB\Template::prepare($phpStr, [
			'verify_id' => $this->id,
			'recaptcha_site_key' => $modSettings['recaptcha_site_key'],
			'recaptcha_theme' => !empty($modSettings['recaptcha_theme']) && $modSettings['recaptcha_theme'] == 'dark' ? 'dark' : 'light',
			'txt' => $txt,
		]));
	}

	public function get_settings(): array
	{
		global $txt;
		$options = [
			'light' => $txt['recaptcha_theme_light'],
			'dark' => $txt['recaptcha_theme_dark'],
		];
		return [
			['titledesc', 'recaptcha_configure'],
			['check', 'recaptcha_enabled', 'subtext' => $txt['recaptcha_enable_desc']],
			['text', 'recaptcha_site_key', 'subtext' => $txt['recaptcha_site_key_desc']],
			['text', 'recaptcha_secret_key', 'subtext' => $txt['recaptcha_secret_key_desc']],
			['select', 'recaptcha_theme', $options],
		];
	}
}
