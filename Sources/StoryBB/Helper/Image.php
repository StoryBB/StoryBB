<?php

/**
 * Image helper.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

class Image
{
	/**
	 * Get the size of a specified image with better error handling.
	 * Uses getimagesize() and some slightly heuristic methods to determine the size of a file.
	 * Attempts to connect to the server first so it won't time out.
	 *
	 * @param string $url The URL of the image
	 * @return array|false The image size as array (width, height), or false on failure
	 */
	public static function get_size_from_url(string $url): ?array
	{
		// Make sure it is a proper URL.
		$url = str_replace(' ', '%20', $url);

		// Can we pull this from the cache... please please?
		if (($temp = cache_get_data('url_image_size-' . md5($url), 240)) !== null)
			return $temp;
		$t = microtime(true);

		// Get the host to pester...
		preg_match('~^\w+://(.+?)/(.*)$~', $url, $match);

		// Can't figure it out, just try the image size.
		if ($url == '' || $url == 'http://' || $url == 'https://')
		{
			return null;
		}
		elseif (!isset($match[1]))
		{
			$size = @getimagesize($url);
		}
		else
		{
			$client = new Client([
				'connect_timeout' => 5,
				'read_timeout' => 5,
				'headers' => [
					'Range' => '0-16383',
				],
			]);
			$response = $client->get($url);
			$response_code = $response->getStatusCode();
			$body = (string) $response->getBody();

			if (in_array($response_code, [200, 206]) && !empty($body))
			{
				return static::get_image_size_from_string($body);
			}
			else
			{
				return null;
			}
		}

		// If we didn't get it, we failed.
		if (!isset($size))
		{
			$size = null;
		}

		// If this took a long time, we may never have to do it again, but then again we might...
		if (microtime(true) - $t > 0.8)
		{
			cache_put_data('url_image_size-' . md5($url), $size, 240);
		}

		// Didn't work.
		return $size;
	}

	/**
	 * Given raw binary data for an image, identify its image size and return.
	 *
	 * @param string $data Raw image bytes as a string
	 * @return array|false Returns array of [width, height] or false if couldn't identify image size
	 */
	public static function get_size_from_string($data): ?array
	{
		if (empty($data)) {
			return null;
		}
		if (strpos($data, 'GIF8') === 0) {
			// It's a GIF. Doesn't really matter which subformat though. Note that things are little endian.
			$width = (ord(substr($data, 7, 1)) << 8) + (ord(substr($data, 6, 1)));
			$height = (ord(substr($data, 9, 1)) << 8) + (ord(substr($data, 8, 1)));
			if (!empty($width)) {
				return [$width, $height];
			}
		}

		if (strpos($data, "\x89PNG") === 0) {
			// Seems to be a PNG. Let's look for the signature of the header chunk, minimum 12 bytes in. PNG max sizes are (signed) 32 bits each way.
			$pos = strpos($data, 'IHDR');
			if ($pos >= 12) {
				$width = (ord(substr($data, $pos + 4, 1)) << 24) + (ord(substr($data, $pos + 5, 1)) << 16) + (ord(substr($data, $pos + 6, 1)) << 8) + (ord(substr($data, $pos + 7, 1)));
				$height = (ord(substr($data, $pos + 8, 1)) << 24) + (ord(substr($data, $pos + 9, 1)) << 16) + (ord(substr($data, $pos + 10, 1)) << 8) + (ord(substr($data, $pos + 11, 1)));
				if ($width > 0 && $height > 0) {
					return [$width, $height];
				}
			}
		}

		if (strpos($data, "\xFF\xD8") === 0)
		{
			// JPEG? Hmm, JPEG is tricky. Well, we found the SOI marker as expected and an APP0 marker, so good chance it is JPEG compliant.
			// Need to step through the file looking for JFIF blocks.
			$pos = 2;
			$filelen = strlen($data);
			while ($pos < $filelen) {
				$length = (ord(substr($data, $pos + 2, 1)) << 8) + (ord(substr($data, $pos + 3, 1)));
				$block = substr($data, $pos, 2);
				if ($block == "\xFF\xC0" || $block == "\xFF\xC2") {
					break;
				}
				$pos += $length + 2;
			}
			if ($pos > 2) {
				// Big endian. SOF block is marker (2 bytes), block size (2 bytes), bits/pixel density (1 byte), image height (2 bytes), image width (2 bytes)
				$width = (ord(substr($data, $pos + 7, 1)) << 8) + (ord(substr($data, $pos + 8, 1)));
				$height = (ord(substr($data, $pos + 5, 1)) << 8) + (ord(substr($data, $pos + 6, 1)));
				if ($width > 0 && $height > 0) {
					return [$width, $height];
				}
			}
		}

		return null;
	}
}
