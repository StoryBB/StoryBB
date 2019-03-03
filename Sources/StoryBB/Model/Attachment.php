<?php

/**
 * This class handles attachments.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Model;

/**
 * This class handles attachments.
 */
class Attachment
{
	const ATTACHMENT_STANDARD = 0;
	const ATTACHMENT_AVATAR = 1;
	const ATTACHMENT_THUMBNAIL = 3;
	const ATTACHMENT_EXPORT = 4;

	public static function get_new_filename($filename)
	{
		return sha1(md5($filename . time()) . mt_rand());
	}
}
