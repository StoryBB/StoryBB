<?php

/**
 * This file checks files during pull requests to ensure they have correct headers.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

// Stuff we will ignore.
$ignoreFiles = array(
	// Minify Stuff.
	'\./Sources/minify/[A-Za-z0-9/-]+\.php',

	// Language Files are ignored as they don't use the License format.
	'./Themes/default/languages/[A-Za-z0-9]+\[A-Za-z0-9]+\.php',

	// Cache and miscellaneous.
	'\./cache/data_[A-Za-z0-9-_]\.php',
);

// No file? Thats bad.
if (!isset($_SERVER['argv'], $_SERVER['argv'][1]))
	die('Error: No File specified' . "\n");

// The file has to exist.
$currentFile = $_SERVER['argv'][1];
if (!file_exists($currentFile))
	die('Error: File does not exist' . "\n");

// Is this ignored?
foreach ($ignoreFiles as $if)
	if (preg_match('~' . $if . '~i', $currentFile))
		die;

// Lets get the main index.php for $forum_version and $software_year.
$indexFile = fopen('./index.php', 'r');
$indexContents = fread($indexFile, 850);

if (!preg_match('~\$forum_version = \'StoryBB ([^\']+)\';~i', $indexContents, $versionResults))
	die('Error: Could not locate $forum_version' . "\n");
$currentVersion = $versionResults[1];

if (!preg_match('~\$software_year = \'(\d{4})\';~i', $indexContents, $yearResults))
	die('Error: Could not locate $software_year' . "\n");
$currentSoftwareYear = (int) $yearResults[1];

$file = fopen($currentFile, 'r');

// Some files, *cough* ManageServer *cough* have lots of junk before the license, otherwise this could easily be 500.
$contents = fread($file, 4000);

// How the license file should look, in a regex type format.
$match = array(
	0 => ' \* @package StoryBB \(storybb.org\) - A roleplayer\'s forum software' . '[\r]?\n',
	1 => ' \* @copyright \d{4} StoryBB and individual contributors \(see contributors.txt\)' . '[\r]?\n',
	2 => ' \* @license 3-clause BSD \(see accompanying LICENSE file\)' . '[\r]?\n',
	3 => ' \*' . '[\r]?\n',
	4 => ' \* @version',
);

// Just see if the license is there.
if (!preg_match('~' . implode('', $match) . '~i', $contents))
	die('Error: License File is invalid or not found in ' . $currentFile . "\n");

// Check the year is correct.
$yearMatch = $match;
$yearMatch[1] = ' \* @copyright ' . $currentSoftwareYear . ' StoryBB and individual contributors \(see contributors.txt\)' . '[\r]?\n';
if (!preg_match('~' . implode('', $yearMatch) . '~i', $contents))
	die('Error: The software year is incorrect in ' . $currentFile . "\n");

// Check the version is correct.
$versionMatch = $match;
$versionMatch[4] = ' \* @version ' . $currentVersion . '[\r]?\n';
if (!preg_match('~' . implode('', $versionMatch) . '~i', $contents))
	die('Error: The version is incorrect in ' . $currentFile . "\n");

// Special check, ugprade.php, install.php copyright templates.
if (in_array($currentFile, array('./other/upgrade.php', './other/install.php')))
{
	// The code is fairly well into it, just get the entire contents.
	$upgradeFile = file_get_contents($currentFile);

	if (!preg_match('~<li class="copyright"><a href="https?://storybb.org/" title="StoryBB" target="_blank" rel="noopener">StoryBB &copy; (\d{4}), StoryBB project</a></li>~i', $upgradeFile, $upgradeResults))
		die('Error: Could not locate upgrade template copyright $software_year' . "\n");

	if ((int) $upgradeResults[1] != $currentSoftwareYear)
		die('Error: Upgrade template copyright year is invalid' . "\n");
}
