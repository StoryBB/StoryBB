<?php

/**
 * Bookmarks topics.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Model\Bookmark;

function Bookmark()
{
	global $user_info, $smcFunc, $topic;

	checkSession('get');

	$userid = (int) $user_info['id'];
	$topicid = (int) $topic;

	$msg = (int) ($_GET['msg'] ?? 0);

	if (Bookmark::is_bookmarked($userid, $topic))
	{
		Bookmark::unbookmark_topics($userid, [$topic]);
	}
	else
	{
		Bookmark::bookmark_topics($userid, [$topic]);
	}

	redirectexit('msg=' . $msg);
}
