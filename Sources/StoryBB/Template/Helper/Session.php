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
			'token_url' => 'StoryBB\\Template\\Helper\\Session::token_url',
			'token_form' => 'StoryBB\\Template\\Helper\\Session::token_form',
			'session_id' => 'StoryBB\\Template\\Helper\\Session::session_id',
			'session_var' => 'StoryBB\\Template\\Helper\\Session::session_var',
			'session_url' => 'StoryBB\\Template\\Helper\\Session::session_url',
			'session_form' => 'StoryBB\\Template\\Helper\\Session::session_form',
		]);
	}

	public static function token($string) {
		global $context;
		return isset($context[$string . '_token']) ? $context[$string . '_token'] : '';
	}

	public static function token_var($string) {
		global $context;
		return isset($context[$string . '_token_var']) ? $context[$string . '_token_var'] : '';
	}

	public static function token_url($string) {
		global $context;
		if (!isset($context[$string . '_token_var'], $context[$string . '_token']))
		{
			return '';
		}

		return new \LightnCandy\SafeString( $context[$string . '_token_var'] . '=' . $context[$string . '_token']);
	}

	public static function token_form($string) {
		global $context;
		if (!isset($context[$string . '_token_var'], $context[$string . '_token']))
		{
			return '';
		}

		return new \LightnCandy\SafeString('<input type="hidden" name="' . $context[$string . '_token_var'] . '" value="' . $context[$string . '_token'] . '">');
	}

	public static function session_id() {
		global $context;
		return $context['session_id'];
	}

	public static function session_var() {
		global $context;
		return $context['session_var'];
	}

	public static function session_url() {
		global $context;
		return $context['session_var'] . '=' . $context['session_id'];
	}

	public static function session_form() {
		global $context;
		return new \LightnCandy\SafeString('<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">');
	}
}

?>