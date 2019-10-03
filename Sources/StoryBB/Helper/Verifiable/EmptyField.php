<?php

/**
 * Implementing an empty-field to trap unwary bots.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Verifiable;

use StoryBB\Helper\Verifiable\AbstractVerifiable;
use StoryBB\Helper\Verifiable\UnverifiableException;

class EmptyField extends AbstractVerifiable implements Verifiable
{
	protected $id;

	public function is_available(): bool
	{
		return true;
	}

	public function reset()
	{
		$terms = ['gadget', 'device', 'uid', 'gid', 'guid', 'uuid', 'unique', 'identifier'];
		$second_terms = ['hash', 'cipher', 'code', 'key', 'unlock', 'bit', 'value'];
		$start = mt_rand(0, 27);
		$hash = substr(md5(time()), $start, 4);
		$_SESSION[$this->id . '_vv']['empty_field'] = $terms[array_rand($terms)] . '-' . $second_terms[array_rand($second_terms)] . '-' . $hash;
	}

	public function verify()
	{
		global $txt;

		// It should be defined.
		if (!isset($_SESSION[$this->id . '_vv']['empty_field']))
		{
			throw new UnverifiableException($txt['no_access']);
		}
		// But the field should be empty...
		if (!empty($_REQUEST[$_SESSION[$this->id . '_vv']['empty_field']]))
		{
			throw new UnverifiableException($txt['error_wrong_verification_answer']);
		}
	}

	public function render()
	{
		global $txt;

		addInlineCss('.vv_special { display:none; }');

		$template = \StoryBB\Template::load_partial('control_verification_emptyfield');
		$phpStr = \StoryBB\Template::compile($template, [], 'control_verification_emptyfield-' . \StoryBB\Template::get_theme_id('partials', 'control_verification_emptyfield'));
		return new \LightnCandy\SafeString(\StoryBB\Template::prepare($phpStr, [
			'verify_id' => $this->id,
			'hidden_input_name' => $_SESSION[$this->id . '_vv']['empty_field'],
			'txt' => $txt,
		]));
	}
}
