<?php

/**
 * A library for making .wav files for the CAPTCHA.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

/**
 * A simple .wav maker.
 */
class Wave
{
	/**
	 * Creates a wave file that spells the letters of $word.
	 * Tries the user's language first, and defaults to English.
	 * Used by VerificationCode() (Register.php).
	 *
	 * @param string $word
	 * @return boolean false on failure
	 */
	public static function create(string $word)
	{
		global $settings, $user_info;

		// Allow max 2 requests per 20 seconds.
		if (($ip = cache_get_data('wave_file/' . $user_info['ip'], 20)) > 2 || ($ip2 = cache_get_data('wave_file/' . $user_info['ip2'], 20)) > 2)
			self::fail();

		cache_put_data('wave_file/' . $user_info['ip'], $ip ? $ip + 1 : 1, 20);
		cache_put_data('wave_file/' . $user_info['ip2'], $ip2 ? $ip2 + 1 : 1, 20);

		// Fixate randomization for this word.
		$tmp = unpack('n', md5($word . session_id()));
		mt_srand(end($tmp));

		// Try to see if there's a sound font in the user's language.
		if (file_exists($settings['default_theme_dir'] . '/languages/' . $user_info['language'] . '/sound/a.wav'))
			$sound_language = $user_info['language'];

		// English should be there.
		elseif (file_exists($settings['default_theme_dir'] . '/languages/en-us/sound/a.wav'))
			$sound_language = 'en-us';

		// Guess not...
		else
			self::fail();

		// File names are in lower case so lets make sure that we are only using a lower case string
		$word = strtolower($word);

		// Loop through all letters of the word $word.
		$sound_word = '';
		for ($i = 0, $wordlen = strlen($word); $i < $wordlen; $i++)
		{
			$sound_letter = implode('', file($settings['default_theme_dir'] . '/languages/' . $sound_language . '/' . $word[$i] . '.wav'));
			if (strpos($sound_letter, 'data') === false)
				self::fail();

			$sound_letter = substr($sound_letter, strpos($sound_letter, 'data') + 8);
			switch ($word[$i] === 's' ? 0 : mt_rand(0, 2))
			{
				case 0 : for ($j = 0, $n = strlen($sound_letter); $j < $n; $j++)
						for ($k = 0, $m = round(mt_rand(15, 25) / 10); $k < $m; $k++)
							$sound_word .= $word[$i] === 's' ? $sound_letter[$j] : chr(mt_rand(max(ord($sound_letter[$j]) - 1, 0x00), min(ord($sound_letter[$j]) + 1, 0xFF)));
				break;

				case 1:
					for ($j = 0, $n = strlen($sound_letter) - 1; $j < $n; $j += 2)
						$sound_word .= (mt_rand(0, 3) == 0 ? '' : $sound_letter[$j]) . (mt_rand(0, 3) === 0 ? $sound_letter{$j + 1} : $sound_letter[$j]) . (mt_rand(0, 3) === 0 ? $sound_letter[$j] : $sound_letter{$j + 1}) . $sound_letter{$j + 1} . (mt_rand(0, 3) == 0 ? $sound_letter{$j + 1} : '');
					$sound_word .= str_repeat($sound_letter[$n], 2);
				break;

				case 2:
					$shift = 0;
					for ($j = 0, $n = strlen($sound_letter); $j < $n; $j++)
					{
						if (mt_rand(0, 10) === 0)
							$shift += mt_rand(-3, 3);
						for ($k = 0, $m = round(mt_rand(15, 25) / 10); $k < $m; $k++)
							$sound_word .= chr(min(max(ord($sound_letter[$j]) + $shift, 0x00), 0xFF));
					}
				break;

			}

			$sound_word .= str_repeat(chr(0x80), mt_rand(10000, 10500));
		}

		$data_size = strlen($sound_word);
		$file_size = $data_size + 0x24;
		$sample_rate = 16000;

		// Disable compression.
		ob_end_clean();
		header('Content-Encoding: none');

		// Output the wav.
		header('Content-type: audio/x-wav');
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
		header('Content-Length: ' . ($file_size + 0x08));

		echo pack('nnVnnnnnnnnVVnnnnV', 0x5249, 0x4646, $file_size, 0x5741, 0x5645, 0x666D, 0x7420, 0x1000, 0x0000, 0x0100, 0x0100, $sample_rate, $sample_rate, 0x0100, 0x0800, 0x6461, 0x7461, $data_size), $sound_word;

		// Noting more to add.
		die();
	}

	/**
	 * End execution with an appropriate header if the creation failed.
	 */
	public static function fail()
	{
		header('HTTP/1.1 400 Bad Request');
		die;
	}
}
