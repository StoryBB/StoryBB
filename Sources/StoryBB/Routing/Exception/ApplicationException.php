<?php

/**
 * This is an exception relating to an invalid route in some fashion.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Routing\Exception;

/**
 * This is an exception relating to routing in some fashion.
 */
class ApplicationException extends \StoryBB\Routing\Exception
{
	public function __construct($message = '', $code = 403, Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}
}
