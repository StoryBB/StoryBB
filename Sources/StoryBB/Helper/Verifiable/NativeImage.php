<?php

/**
 * The default native image CAPTCHA.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Verifiable;

use StoryBB\Helper\Verifiable\AbstractVerifiable;
use StoryBB\Helper\Verifiable\UnverifiableException;
use StoryBB\StringLibrary;

class NativeImage extends AbstractVerifiable implements Verifiable
{
	protected $id;
	protected $use_graphic_library;
	protected $image_href;
	protected $text_value;

	public function __construct(string $id)
	{
		global $scripturl, $smcFunc;
		parent::__construct($id);

		$this->use_graphic_library = in_array('gd', get_loaded_extensions());
		$this->image_href = $scripturl . '?action=verificationcode;vid=' . $this->id . ';rand=' . md5(mt_rand());
		$this->text_value = !empty($_REQUEST[$this->id . '_vv']['code']) ? StringLibrary::escape($_REQUEST[$this->id . '_vv']['code']) : '';
	}

	public function is_available(): bool
	{
		global $modSettings;

		return !empty($modSettings['visual_verification_type']);
	}

	public function reset()
	{
		$_SESSION[$this->id . '_vv']['code'] = $this->generate_code();
	}

	protected function generate_code()
	{
		$code = '';

		$captcha_range = array_merge(range('A', 'H'), ['K', 'M', 'N', 'P', 'R'], range('T', 'Y'));
		for ($i = 0; $i < 6; $i++)
		{
			$code .= $captcha_range[array_rand($captcha_range)];
		}

		return $code;
	}

	public function verify()
	{
		global $txt;

		// It should be defined.
		if (empty($_SESSION[$this->id . '_vv']['code']))
		{
			throw new UnverifiableException($txt['no_access']);
		}
		// It should also be provided by the user.
		if (empty($_REQUEST[$this->id . '_vv']['code']))
		{
			throw new UnverifiableException($txt['error_wrong_verification_code']);
		}
		// Or it's just wrong.
		if (strtoupper($_REQUEST[$this->id . '_vv']['code']) !== $_SESSION[$this->id . '_vv']['code'])
		{
			throw new UnverifiableException($txt['error_wrong_verification_code']);
		}
	}

	public function render()
	{
		global $context, $txt;

		loadJavaScriptFile('captcha.js', [], 'sbb_captcha');
		addInlineJavaScript('
			var verification' . $this->id . 'Handle = new sbbCaptcha("' . $this->image_href . '", "' . $this->id . '", ' . ($this->use_graphic_library ? 1 : 0) . ');', true);

		$template = \StoryBB\Template::load_partial('control_verification_nativeimage');
		$phpStr = \StoryBB\Template::compile($template, [], 'control_verification_nativeimage-' . \StoryBB\Template::get_theme_id('partials', 'control_verification_nativeimage'));
		return new \LightnCandy\SafeString(\StoryBB\Template::prepare($phpStr, [
			'verify_id' => $this->id,
			'use_graphic_library' => $this->use_graphic_library,
			'image_href' => $this->image_href,
			'text_value' => $this->text_value,
			'txt' => $txt,
		]));
	}

	public function get_settings(): array
	{
		global $txt, $context, $modSettings;

		$choices = [
			$txt['setting_image_verification_off'],
			$txt['setting_image_verification_vsimple'],
			$txt['setting_image_verification_simple'],
			$txt['setting_image_verification_medium'],
			$txt['setting_image_verification_high'],
			$txt['setting_image_verification_extreme'],
		];

		$_SESSION['visual_verification_code'] = $this->generate_code();

		// Some javascript for CAPTCHA.
		$context['settings_post_javascript'] = '';
		if ($this->use_graphic_library)
		{
			addInlineJavaScript('
			function refreshImages()
			{
				var imageType = document.getElementById(\'visual_verification_type\').value;
				document.getElementById(\'verification_image\').src = \'' . $this->image_href . ';type=\' + imageType;
			}', true);
		}

		// Show the image itself, or text saying we can't.
		if ($this->use_graphic_library)
		{
			$type = !empty($modSettings['visual_verification_type']) ? $modSettings['visual_verification_type'] : 0;
			$postinput = '<br><img src="' . $this->image_href . ';type=' . $type . '" alt="' . $txt['setting_image_verification_sample'] . '" id="verification_image"><br>';
		}
		else
		{
			$postinput = '<br><span class="smalltext">' . $txt['setting_image_verification_nogd'] . '</span>';
		}

		return [
			['titledesc', 'configure_verification_means'],
			'vv' => ['select', 'visual_verification_type', $choices, 'subtext' => $txt['setting_visual_verification_type_desc'], 'onchange' => $this->use_graphic_library ? 'refreshImages();' : '', 'postinput' => $postinput],
		];
	}
}
