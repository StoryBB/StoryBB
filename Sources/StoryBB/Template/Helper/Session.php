<?php

/**
 * This class provides session and token helpers for StoryBB's templates.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Template\Helper;

class Session
{
	public static function _list()
	{
		return ([
			'token' => 'StoryBB\\Template\\Helper\\Session::token',
			'token_var' => 'StoryBB\\Template\\Helper\\Session::token_var',
			'token_form' => 'StoryBB\\Template\\Helper\\Session::token_form',
		]);
	}

	function token($string) {
		global $context;
		return isset($context[$string . '_token']) ? $context[$string . '_token'] : '';
	}

	function token_var($string) {
		global $context;
		return isset($context[$string . '_token_var']) ? $context[$string . '_token_var'] : '';
	}

	function token_form($string) {
		global $context;
		if (!isset($context[$string . '_token_var'], $context[$string . '_token']))
		{
			return '';
		}

		return new \LightnCandy\SafeString('<input type="hidden" name="' . $context[$string . '_token_var'] . '" value="' . $context[$string . '_token'] . '">');
	}
}

?>