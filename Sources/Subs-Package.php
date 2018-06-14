<?php

/**
 * This file's central purpose of existence is that of making the package
 * manager work nicely.  It contains functions for handling tar.gz and zip
 * files, as well as a simple xml parser to handle the xml package stuff.
 * Not to mention a few functions to make file handling easier.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Checks if the forum version matches any of the available versions from the package install xml.
 * - supports comma separated version numbers, with or without whitespace.
 * - supports lower and upper bounds. (1.0-1.2)
 * - returns true if the version matched.
 *
 * @param string $version The forum version
 * @param string $versions The versions that this package will install on
 * @return bool Whether the version matched
 */
function matchPackageVersion($version, $versions)
{
	// Make sure everything is lowercase and clean of spaces and unpleasant history.
	$version = str_replace(array(' ', '2.0rc1-1'), array('', '2.0rc1.1'), strtolower($version));
	$versions = explode(',', str_replace(array(' ', '2.0rc1-1'), array('', '2.0rc1.1'), strtolower($versions)));

	// Perhaps we do accept anything?
	if (in_array('all', $versions))
		return true;

	// Loop through each version.
	foreach ($versions as $for)
	{
		// Wild card spotted?
		if (strpos($for, '*') !== false)
			$for = str_replace('*', '0dev0', $for) . '-' . str_replace('*', '999', $for);

		// Do we have a range?
		if (strpos($for, '-') !== false)
		{
			list ($lower, $upper) = explode('-', $for);

			// Compare the version against lower and upper bounds.
			if (version_compare($version, $lower) > -1 && version_compare($version, $upper) < 1)
				return true;
		}
		// Otherwise check if they are equal...
		elseif (version_compare($version, $for) === 0)
			return true;
	}

	return false;
}

/**
 * Deletes a directory, and all the files and direcories inside it.
 * requires access to delete these files.
 *
 * @param string $dir A directory
 * @param bool $delete_dir If false, only deletes everything inside the directory but not the directory itself
 */
function deltree($dir, $delete_dir = true)
{
	global $package_ftp;

	if (!file_exists($dir))
		return;

	$current_dir = @opendir($dir);
	if ($current_dir == false)
	{
		if ($delete_dir && isset($package_ftp))
		{
			$ftp_file = strtr($dir, array($_SESSION['pack_ftp']['root'] => ''));
			if (!is_dir($dir))
				$package_ftp->chmod($ftp_file, 0777);
			$package_ftp->unlink($ftp_file);
		}

		return;
	}

	while ($entryname = readdir($current_dir))
	{
		if (in_array($entryname, array('.', '..')))
			continue;

		if (is_dir($dir . '/' . $entryname))
			deltree($dir . '/' . $entryname);
		else
		{
			// Here, 755 doesn't really matter since we're deleting it anyway.
			if (isset($package_ftp))
			{
				$ftp_file = strtr($dir . '/' . $entryname, array($_SESSION['pack_ftp']['root'] => ''));

				if (!is_writable($dir . '/' . $entryname))
					$package_ftp->chmod($ftp_file, 0777);
				$package_ftp->unlink($ftp_file);
			}
			else
			{
				if (!is_writable($dir . '/' . $entryname))
					sbb_chmod($dir . '/' . $entryname, 0777);
				unlink($dir . '/' . $entryname);
			}
		}
	}

	closedir($current_dir);

	if ($delete_dir)
	{
		if (isset($package_ftp))
		{
			$ftp_file = strtr($dir, array($_SESSION['pack_ftp']['root'] => ''));
			if (!is_writable($dir . '/' . $entryname))
				$package_ftp->chmod($ftp_file, 0777);
			$package_ftp->unlink($ftp_file);
		}
		else
		{
			if (!is_writable($dir))
				sbb_chmod($dir, 0777);
			@rmdir($dir);
		}
	}
}

/**
 * Creates the specified tree structure with the mode specified.
 * creates every directory in path until it finds one that already exists.
 *
 * @param string $strPath The path
 * @param int $mode The permission mode for CHMOD (0666, etc.)
 * @return bool True if successful, false otherwise
 */
function mktree($strPath, $mode)
{
	global $package_ftp;

	if (is_dir($strPath))
	{
		if (!is_writable($strPath) && $mode !== false)
		{
			if (isset($package_ftp))
				$package_ftp->chmod(strtr($strPath, array($_SESSION['pack_ftp']['root'] => '')), $mode);
			else
				sbb_chmod($strPath, $mode);
		}

		$test = @opendir($strPath);
		if ($test)
		{
			closedir($test);
			return is_writable($strPath);
		}
		else
			return false;
	}
	// Is this an invalid path and/or we can't make the directory?
	if ($strPath == dirname($strPath) || !mktree(dirname($strPath), $mode))
		return false;

	if (!is_writable(dirname($strPath)) && $mode !== false)
	{
		if (isset($package_ftp))
			$package_ftp->chmod(dirname(strtr($strPath, array($_SESSION['pack_ftp']['root'] => ''))), $mode);
		else
			sbb_chmod(dirname($strPath), $mode);
	}

	if ($mode !== false && isset($package_ftp))
		return $package_ftp->create_dir(strtr($strPath, array($_SESSION['pack_ftp']['root'] => '')));
	elseif ($mode === false)
	{
		$test = @opendir(dirname($strPath));
		if ($test)
		{
			closedir($test);
			return true;
		}
		else
			return false;
	}
	else
	{
		@mkdir($strPath, $mode);
		$test = @opendir($strPath);
		if ($test)
		{
			closedir($test);
			return true;
		}
		else
			return false;
	}
}

/**
 * Copies one directory structure over to another.
 * requires the destination to be writable.
 *
 * @param string $source The directory to copy
 * @param string $destination The directory to copy $source to
 */
function copytree($source, $destination)
{
	global $package_ftp;

	if (!file_exists($destination) || !is_writable($destination))
		mktree($destination, 0755);
	if (!is_writable($destination))
		mktree($destination, 0777);

	$current_dir = opendir($source);
	if ($current_dir == false)
		return;

	while ($entryname = readdir($current_dir))
	{
		if (in_array($entryname, array('.', '..')))
			continue;

		if (isset($package_ftp))
			$ftp_file = strtr($destination . '/' . $entryname, array($_SESSION['pack_ftp']['root'] => ''));

		if (is_file($source . '/' . $entryname))
		{
			if (isset($package_ftp) && !file_exists($destination . '/' . $entryname))
				$package_ftp->create_file($ftp_file);
			elseif (!file_exists($destination . '/' . $entryname))
				@touch($destination . '/' . $entryname);
		}

		package_chmod($destination . '/' . $entryname);

		if (is_dir($source . '/' . $entryname))
			copytree($source . '/' . $entryname, $destination . '/' . $entryname);
		elseif (file_exists($destination . '/' . $entryname))
			package_put_contents($destination . '/' . $entryname, package_get_contents($source . '/' . $entryname));
		else
			copy($source . '/' . $entryname, $destination . '/' . $entryname);
	}

	closedir($current_dir);
}

/**
 * Get the physical contents of a packages file
 *
 * @param string $filename The package file
 * @return string The contents of the specified file
 */
function package_get_contents($filename)
{
	global $package_cache, $modSettings;

	if (!isset($package_cache))
	{

		$mem_check = setMemoryLimit('128M');

		// Windows doesn't seem to care about the memory_limit.
		if (!empty($modSettings['package_disable_cache']) || $mem_check || stripos(PHP_OS, 'win') !== false)
			$package_cache = array();
		else
			$package_cache = false;
	}

	if (strpos($filename, 'Packages/') !== false || $package_cache === false || !isset($package_cache[$filename]))
		return file_get_contents($filename);
	else
		return $package_cache[$filename];
}

/**
 * Writes data to a file, almost exactly like the file_put_contents() function.
 * uses FTP to create/chmod the file when necessary and available.
 * uses text mode for text mode file extensions.
 * returns the number of bytes written.
 *
 * @param string $filename The name of the file
 * @param string $data The data to write to the file
 * @param bool $testing Whether we're just testing things
 * @return int The length of the data written (in bytes)
 */
function package_put_contents($filename, $data, $testing = false)
{
	global $package_ftp, $package_cache, $modSettings;
	static $text_filetypes = array('php', 'txt', '.js', 'css', 'vbs', 'tml', 'htm');

	if (!isset($package_cache))
	{
		// Try to increase the memory limit - we don't want to run out of ram!
		$mem_check = setMemoryLimit('128M');

		if (!empty($modSettings['package_disable_cache']) || $mem_check || stripos(PHP_OS, 'win') !== false)
			$package_cache = array();
		else
			$package_cache = false;
	}

	if (isset($package_ftp))
		$ftp_file = strtr($filename, array($_SESSION['pack_ftp']['root'] => ''));

	if (!file_exists($filename) && isset($package_ftp))
		$package_ftp->create_file($ftp_file);
	elseif (!file_exists($filename))
		@touch($filename);

	package_chmod($filename);

	if (!$testing && (strpos($filename, 'Packages/') !== false || $package_cache === false))
	{
		$fp = @fopen($filename, in_array(substr($filename, -3), $text_filetypes) ? 'w' : 'wb');

		// We should show an error message or attempt a rollback, no?
		if (!$fp)
			return false;

		fwrite($fp, $data);
		fclose($fp);
	}
	elseif (strpos($filename, 'Packages/') !== false || $package_cache === false)
		return strlen($data);
	else
	{
		$package_cache[$filename] = $data;

		// Permission denied, eh?
		$fp = @fopen($filename, 'r+');
		if (!$fp)
			return false;
		fclose($fp);
	}

	return strlen($data);
}

/**
 * Flushes the cache from memory to the filesystem
 *
 * @param bool $trash
 */
function package_flush_cache($trash = false)
{
	global $package_ftp, $package_cache;
	static $text_filetypes = array('php', 'txt', '.js', 'css', 'vbs', 'tml', 'htm');

	if (empty($package_cache))
		return;

	// First, let's check permissions!
	foreach ($package_cache as $filename => $data)
	{
		if (isset($package_ftp))
			$ftp_file = strtr($filename, array($_SESSION['pack_ftp']['root'] => ''));

		if (!file_exists($filename) && isset($package_ftp))
			$package_ftp->create_file($ftp_file);
		elseif (!file_exists($filename))
			@touch($filename);

		$result = package_chmod($filename);

		// if we are not doing our test pass, then lets do a full write check
		// bypass directories when doing this test
		if ((!$trash) && !is_dir($filename))
		{
			// acid test, can we really open this file for writing?
			$fp = ($result) ? fopen($filename, 'r+') : $result;
			if (!$fp)
			{
				// We should have package_chmod()'d them before, no?!
				trigger_error('package_flush_cache(): some files are still not writable', E_USER_WARNING);
				return;
			}
			fclose($fp);
		}
	}

	if ($trash)
	{
		$package_cache = array();
		return;
	}

	// Write the cache to disk here.
	// Bypass directories when doing so - no data to write & the fopen will crash.
	foreach ($package_cache as $filename => $data)
	{
		if (!is_dir($filename)) 
		{
			$fp = fopen($filename, in_array(substr($filename, -3), $text_filetypes) ? 'w' : 'wb');
			fwrite($fp, $data);
			fclose($fp);
		}
	}

	$package_cache = array();
}

/**
 * Try to make a file writable.
 *
 * @param string $filename The name of the file
 * @param string $perm_state The permission state - can be either 'writable' or 'execute'
 * @param bool $track_change Whether to track this change
 * @return boolean True if it worked, false if it didn't
 */
function package_chmod($filename, $perm_state = 'writable', $track_change = false)
{
	global $package_ftp;

	if (file_exists($filename) && is_writable($filename) && $perm_state == 'writable')
		return true;

	// Start off checking without FTP.
	if (!isset($package_ftp) || $package_ftp === false)
	{
		for ($i = 0; $i < 2; $i++)
		{
			$chmod_file = $filename;

			// Start off with a less aggressive test.
			if ($i == 0)
			{
				// If this file doesn't exist, then we actually want to look at whatever parent directory does.
				$subTraverseLimit = 2;
				while (!file_exists($chmod_file) && $subTraverseLimit)
				{
					$chmod_file = dirname($chmod_file);
					$subTraverseLimit--;
				}

				// Keep track of the writable status here.
				$file_permissions = @fileperms($chmod_file);
			}
			else
			{
				// This looks odd, but it's an attempt to work around PHP suExec.
				if (!file_exists($chmod_file) && $perm_state == 'writable')
				{
					$file_permissions = @fileperms(dirname($chmod_file));

					mktree(dirname($chmod_file), 0755);
					@touch($chmod_file);
					sbb_chmod($chmod_file, 0755);
				}
				else
					$file_permissions = @fileperms($chmod_file);
			}

			// This looks odd, but it's another attempt to work around PHP suExec.
			if ($perm_state != 'writable')
				sbb_chmod($chmod_file, $perm_state == 'execute' ? 0755 : 0644);
			else
			{
				if (!@is_writable($chmod_file))
					sbb_chmod($chmod_file, 0755);
				if (!@is_writable($chmod_file))
					sbb_chmod($chmod_file, 0777);
				if (!@is_writable(dirname($chmod_file)))
					sbb_chmod($chmod_file, 0755);
				if (!@is_writable(dirname($chmod_file)))
					sbb_chmod($chmod_file, 0777);
			}

			// The ultimate writable test.
			if ($perm_state == 'writable')
			{
				$fp = is_dir($chmod_file) ? @opendir($chmod_file) : @fopen($chmod_file, 'rb');
				if (@is_writable($chmod_file) && $fp)
				{
					if (!is_dir($chmod_file))
						fclose($fp);
					else
						closedir($fp);

					// It worked!
					if ($track_change)
						$_SESSION['pack_ftp']['original_perms'][$chmod_file] = $file_permissions;

					return true;
				}
			}
			elseif ($perm_state != 'writable' && isset($_SESSION['pack_ftp']['original_perms'][$chmod_file]))
				unset($_SESSION['pack_ftp']['original_perms'][$chmod_file]);
		}

		// If we're here we're a failure.
		return false;
	}
	// Otherwise we do have FTP?
	elseif ($package_ftp !== false && !empty($_SESSION['pack_ftp']))
	{
		$ftp_file = strtr($filename, array($_SESSION['pack_ftp']['root'] => ''));

		// This looks odd, but it's an attempt to work around PHP suExec.
		if (!file_exists($filename) && $perm_state == 'writable')
		{
			$file_permissions = @fileperms(dirname($filename));

			mktree(dirname($filename), 0755);
			$package_ftp->create_file($ftp_file);
			$package_ftp->chmod($ftp_file, 0755);
		}
		else
			$file_permissions = @fileperms($filename);

		if ($perm_state != 'writable')
		{
			$package_ftp->chmod($ftp_file, $perm_state == 'execute' ? 0755 : 0644);
		}
		else
		{
			if (!@is_writable($filename))
				$package_ftp->chmod($ftp_file, 0777);
			if (!@is_writable(dirname($filename)))
				$package_ftp->chmod(dirname($ftp_file), 0777);
		}

		if (@is_writable($filename))
		{
			if ($track_change)
				$_SESSION['pack_ftp']['original_perms'][$filename] = $file_permissions;

			return true;
		}
		elseif ($perm_state != 'writable' && isset($_SESSION['pack_ftp']['original_perms'][$filename]))
			unset($_SESSION['pack_ftp']['original_perms'][$filename]);
	}

	// Oh dear, we failed if we get here.
	return false;
}

/**
 * Get the contents of a URL, irrespective of allow_url_fopen.
 *
 * - reads the contents of an http or ftp address and retruns the page in a string
 * - will accept up to 3 page redirections (redirectio_level in the function call is private)
 * - if post_data is supplied, the value and length is posted to the given url as form data
 * - URL must be supplied in lowercase
 *
 * @param string $url The URL
 * @param string $post_data The data to post to the given URL
 * @param bool $keep_alive Whether to send keepalive info
 * @param int $redirection_level How many levels of redirection
 * @return string|false The fetched data or false on failure
 */
function fetch_web_data($url, $post_data = '', $keep_alive = false, $redirection_level = 0)
{
	global $webmaster_email, $sourcedir;
	static $keep_alive_dom = null, $keep_alive_fp = null;

	preg_match('~^(http|ftp)(s)?://([^/:]+)(:(\d+))?(.+)$~', $url, $match);

	// An FTP url. We should try connecting and RETRieving it...
	if (empty($match[1]))
		return false;
	elseif ($match[1] == 'ftp')
	{
		// Establish a connection and attempt to enable passive mode.
		$ftp = new \StoryBB\Helper\FTP(($match[2] ? 'ssl://' : '') . $match[3], empty($match[5]) ? 21 : $match[5], 'anonymous', $webmaster_email);
		if ($ftp->error !== false || !$ftp->passive())
			return false;

		// I want that one *points*!
		fwrite($ftp->connection, 'RETR ' . $match[6] . "\r\n");

		// Since passive mode worked (or we would have returned already!) open the connection.
		$fp = @fsockopen($ftp->pasv['ip'], $ftp->pasv['port'], $err, $err, 5);
		if (!$fp)
			return false;

		// The server should now say something in acknowledgement.
		$ftp->check_response(150);

		$data = '';
		while (!feof($fp))
			$data .= fread($fp, 4096);
		fclose($fp);

		// All done, right?  Good.
		$ftp->check_response(226);
		$ftp->close();
	}
	// More likely a standard HTTP URL, first try to use cURL if available
	elseif (isset($match[1]) && $match[1] === 'http' && function_exists('curl_init'))
	{
		// Include the file containing the curl_fetch_web_data class.
		require_once($sourcedir . '/Class-CurlFetchWeb.php');

		$fetch_data = new curl_fetch_web_data();
		$fetch_data->get_url_data($url, $post_data);

		// no errors and a 200 result, then we have a good dataset, well we at least have data ;)
		if ($fetch_data->result('code') == 200 && !$fetch_data->result('error'))
			$data = $fetch_data->result('body');
		else
			return false;
	}
	// This is more likely; a standard HTTP URL.
	elseif (isset($match[1]) && $match[1] == 'http')
	{
		if ($keep_alive && $match[3] == $keep_alive_dom)
			$fp = $keep_alive_fp;
		if (empty($fp))
		{
			// Open the socket on the port we want...
			$fp = @fsockopen(($match[2] ? 'ssl://' : '') . $match[3], empty($match[5]) ? ($match[2] ? 443 : 80) : $match[5], $err, $err, 5);
			if (!$fp)
				return false;
		}

		if ($keep_alive)
		{
			$keep_alive_dom = $match[3];
			$keep_alive_fp = $fp;
		}

		// I want this, from there, and I'm not going to be bothering you for more (probably.)
		if (empty($post_data))
		{
			fwrite($fp, 'GET ' . ($match[6] !== '/' ? str_replace(' ', '%20', $match[6]) : '') . ' HTTP/1.0' . "\r\n");
			fwrite($fp, 'Host: ' . $match[3] . (empty($match[5]) ? ($match[2] ? ':443' : '') : ':' . $match[5]) . "\r\n");
			fwrite($fp, 'User-Agent: PHP/StoryBB' . "\r\n");
			if ($keep_alive)
				fwrite($fp, 'Connection: Keep-Alive' . "\r\n\r\n");
			else
				fwrite($fp, 'Connection: close' . "\r\n\r\n");
		}
		else
		{
			fwrite($fp, 'POST ' . ($match[6] !== '/' ? $match[6] : '') . ' HTTP/1.0' . "\r\n");
			fwrite($fp, 'Host: ' . $match[3] . (empty($match[5]) ? ($match[2] ? ':443' : '') : ':' . $match[5]) . "\r\n");
			fwrite($fp, 'User-Agent: PHP/StoryBB' . "\r\n");
			if ($keep_alive)
				fwrite($fp, 'Connection: Keep-Alive' . "\r\n");
			else
				fwrite($fp, 'Connection: close' . "\r\n");
			fwrite($fp, 'Content-Type: application/x-www-form-urlencoded' . "\r\n");
			fwrite($fp, 'Content-Length: ' . strlen($post_data) . "\r\n\r\n");
			fwrite($fp, $post_data);
		}

		$response = fgets($fp, 768);

		// Redirect in case this location is permanently or temporarily moved.
		if ($redirection_level < 3 && preg_match('~^HTTP/\S+\s+30[127]~i', $response) === 1)
		{
			$header = '';
			$location = '';
			while (!feof($fp) && trim($header = fgets($fp, 4096)) != '')
				if (strpos($header, 'Location:') !== false)
					$location = trim(substr($header, strpos($header, ':') + 1));

			if (empty($location))
				return false;
			else
			{
				if (!$keep_alive)
					fclose($fp);
				return fetch_web_data($location, $post_data, $keep_alive, $redirection_level + 1);
			}
		}

		// Make sure we get a 200 OK.
		elseif (preg_match('~^HTTP/\S+\s+20[01]~i', $response) === 0)
			return false;

		// Skip the headers...
		while (!feof($fp) && trim($header = fgets($fp, 4096)) != '')
		{
			if (preg_match('~content-length:\s*(\d+)~i', $header, $match) != 0)
				$content_length = $match[1];
			elseif (preg_match('~connection:\s*close~i', $header) != 0)
			{
				$keep_alive_dom = null;
				$keep_alive = false;
			}

			continue;
		}

		$data = '';
		if (isset($content_length))
		{
			while (!feof($fp) && strlen($data) < $content_length)
				$data .= fread($fp, $content_length - strlen($data));
		}
		else
		{
			while (!feof($fp))
				$data .= fread($fp, 4096);
		}

		if (!$keep_alive)
			fclose($fp);
	}
	else
	{
		// Umm, this shouldn't happen?
		trigger_error('fetch_web_data(): Bad URL', E_USER_NOTICE);
		$data = false;
	}

	return $data;
}
