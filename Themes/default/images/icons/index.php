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
// Try to handle it with the upper level index.php. (it should know what to do.)
if (file_exists(dirname(dirname(__FILE__)) . '/index.php'))
	include (dirname(dirname(__FILE__)) . '/index.php');
else
	exit;
