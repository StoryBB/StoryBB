<?php

/**
 * This class provides session and token helpers for StoryBB's templates.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Template\Helper;

/**
 * This class provides session and token helpers for StoryBB's templates.
 */
class Session
{
	/**
	 * List the different helpers available in this class.
	 * @return array Helpers, assocating name to method
	 */
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

	/**
	 * Export the token key for a token
	 * @param string $string The token name to export
	 * @return string The key for the named token
	 */
	public static function token($string)
	{
		global $context;
		return isset($context[$string . '_token']) ? $context[$string . '_token'] : '';
	}

	/**
	 * Export the token value for a token
	 * @param string $string The token name to export
	 * @return string The value for the named token
	 */
	public static function token_var($string)
	{
		global $context;
		return isset($context[$string . '_token_var']) ? $context[$string . '_token_var'] : '';
	}

	/**
	 * Export the token key/value for use in a URL
	 * @param string $string The token name to export
	 * @return string The key=value pair for a URL
	 */
	public static function token_url($string)
	{
		global $context;
		if (!isset($context[$string . '_token_var'], $context[$string . '_token']))
		{
			return '';
		}

		return new \LightnCandy\SafeString( $context[$string . '_token_var'] . '=' . $context[$string . '_token']);
	}

	/**
	 * Export the token key/value for use in a form
	 * @param string $string The token name to export
	 * @return string A fragment of HTML containing a hidden input with the token details
	 */
	public static function token_form($string)
	{
		global $context;
		if (!isset($context[$string . '_token_var'], $context[$string . '_token']))
		{
			return '';
		}

		return new \LightnCandy\SafeString('<input type="hidden" name="' . $context[$string . '_token_var'] . '" value="' . $context[$string . '_token'] . '">');
	}

	/**
	 * Export the session ID
	 * @return string Session ID
	 */
	public static function session_id()
	{
		global $context;
		return $context['session_id'];
	}

	/**
	 * Export the session key
	 * @return string Session key for ID
	 */
	public static function session_var()
	{
		global $context;
		return $context['session_var'];
	}

	/**
	 * Export the session key/value in URL format
	 * @return string sessionkey=sessionid
	 */
	public static function session_url()
	{
		global $context;
		return $context['session_var'] . '=' . $context['session_id'];
	}

	/**
	 * Export the session key/value for use in a form
	 * @return string A fragment of HTML containing a hidden input with the session details
	 */
	public static function session_form()
	{
		global $context;
		return new \LightnCandy\SafeString('<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">');
	}
}
