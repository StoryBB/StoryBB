<?php
/**
 * Tries to protect against this folder having a directory index.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

// Look for Settings.php....
if (file_exists(dirname(dirname(__FILE__)) . '/Settings.php'))
{
	// Found it!
	require(dirname(dirname(__FILE__)) . '/Settings.php');
	header('Location: ' . $boardurl);
}
// Can't find it... just forget it.
else
	exit;
