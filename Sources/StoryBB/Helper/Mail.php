<?php

/**
 * This class handles the mail processing.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

/**
 * This class handles the mail processing.
 */
class Mail
{
	/**
	 * This function sends an email to the specified recipient(s).
	 * It uses the mail_type settings and webmaster_email variable.
	 *
	 * @param array $to The email(s) to send to
	 * @param string $subject Email subject, expected to have entities, and slashes, but not be parsed
	 * @param string $message Email body, expected to have slashes, no htmlentities
	 * @param string $from The address to use for replies
	 * @param string $message_id If specified, it will be used as local part of the Message-ID header.
	 * @param bool $send_html Whether or not the message is HTML vs. plain text
	 * @param int $priority The priority of the message
	 * @param bool $hotmail_fix Whether to apply the "hotmail fix"
	 * @param bool $is_private Whether this is private
	 * @return boolean Whether ot not the email was sent properly.
	 */
	public static function send($to, $subject, $message, $from = null, $message_id = null, $send_html = false, $priority = 3, $hotmail_fix = null, $is_private = false)
	{
		global $webmaster_email, $context, $modSettings, $txt, $scripturl;

		// Use sendmail if it's set or if no SMTP server is set.
		$use_sendmail = empty($modSettings['mail_type']) || $modSettings['smtp_host'] == '';

		// Line breaks need to be \r\n only in windows or for SMTP.
		$line_break = $context['server']['is_windows'] || !$use_sendmail ? "\r\n" : "\n";

		// So far so good.
		$mail_result = true;

		// If the recipient list isn't an array, make it one.
		$to_array = is_array($to) ? $to : array($to);

		// Make sure we actually have email addresses to send this to
		foreach ($to_array as $k => $v)
		{
			// This should never happen, but better safe than sorry
			if (trim($v) == '')
			{
				unset($to_array[$k]);
			}
		}

		// Nothing left? Nothing else to do
		if (empty($to_array))
			return true;

		// Once upon a time, Hotmail could not interpret non-ASCII mails.
		// In honour of those days, it's still called the 'hotmail fix'.
		if ($hotmail_fix === null)
		{
			$hotmail_to = [];
			foreach ($to_array as $i => $to_address)
			{
				if (preg_match('~@(att|comcast|bellsouth)\.[a-zA-Z\.]{2,6}$~i', $to_address) === 1)
				{
					$hotmail_to[] = $to_address;
					$to_array = array_diff($to_array, array($to_address));
				}
			}

			// Call this function recursively for the hotmail addresses.
			if (!empty($hotmail_to))
				$mail_result = self::send($hotmail_to, $subject, $message, $from, $message_id, $send_html, $priority, true, $is_private);

			// The remaining addresses no longer need the fix.
			$hotmail_fix = false;

			// No other addresses left? Return instantly.
			if (empty($to_array))
				return $mail_result;
		}

		// Get rid of entities.
		$subject = un_htmlspecialchars($subject);
		// Make the message use the proper line breaks.
		$message = str_replace(array("\r", "\n"), array('', $line_break), $message);

		// Make sure hotmail mails are sent as HTML so that HTML entities work.
		if ($hotmail_fix && !$send_html)
		{
			$send_html = true;
			$message = strtr($message, array($line_break => '<br>' . $line_break));
			$message = preg_replace('~(' . preg_quote($scripturl, '~') . '(?:[?/][\w\-_%\.,\?&;=#]+)?)~', '<a href="$1">$1</a>', $message);
		}

		list (, $from_name) = self::mimespecialchars(addcslashes($from !== null ? $from : $context['forum_name'], '<>()\'\\"'), true, $hotmail_fix, $line_break);
		list (, $subject) = self::mimespecialchars($subject, true, $hotmail_fix, $line_break);

		// Construct the mail headers...
		$headers = 'From: ' . $from_name . ' <' . (empty($modSettings['mail_from']) ? $webmaster_email : $modSettings['mail_from']) . '>' . $line_break;
		$headers .= $from !== null ? 'Reply-To: <' . $from . '>' . $line_break : '';
		$headers .= 'Return-Path: ' . (empty($modSettings['mail_from']) ? $webmaster_email : $modSettings['mail_from']) . $line_break;
		$headers .= 'Date: ' . gmdate('D, d M Y H:i:s') . ' -0000' . $line_break;

		if ($message_id !== null && empty($modSettings['mail_no_message_id']))
			$headers .= 'Message-ID: <' . md5($scripturl . microtime()) . '-' . $message_id . strstr(empty($modSettings['mail_from']) ? $webmaster_email : $modSettings['mail_from'], '@') . '>' . $line_break;
		$headers .= 'X-Mailer: StoryBB' . $line_break;

		// Pass this to the integration before we start modifying the output -- it'll make it easier later.
		if (in_array(false, call_integration_hook('integrate_outgoing_email', array(&$subject, &$message, &$headers, &$to_array)), true))
			return false;

		// Save the original message...
		$orig_message = $message;

		// The mime boundary separates the different alternative versions.
		$mime_boundary = 'StoryBB-' . md5($message . time());

		// Using mime, as it allows to send a plain unencoded alternative.
		$headers .= 'Mime-Version: 1.0' . $line_break;
		$headers .= 'Content-Type: multipart/alternative; boundary="' . $mime_boundary . '"' . $line_break;
		$headers .= 'Content-Transfer-Encoding: 7bit' . $line_break;

		// Sending HTML?  Let's plop in some basic stuff, then.
		if ($send_html)
		{
			$no_html_message = un_htmlspecialchars(strip_tags(strtr($orig_message, array('</title>' => $line_break))));

			// But, then, dump it and use a plain one for dinosaur clients.
			list(, $plain_message) = self::mimespecialchars($no_html_message, false, true, $line_break);
			$message = $plain_message . $line_break . '--' . $mime_boundary . $line_break;

			// This is the plain text version.  Even if no one sees it, we need it for spam checkers.
			list($charset, $plain_charset_message, $encoding) = self::mimespecialchars($no_html_message, false, false, $line_break);
			$message .= 'Content-Type: text/plain; charset=' . $charset . $line_break;
			$message .= 'Content-Transfer-Encoding: ' . $encoding . $line_break . $line_break;
			$message .= $plain_charset_message . $line_break . '--' . $mime_boundary . $line_break;

			// This is the actual HTML message, prim and proper.  If we wanted images, they could be inlined here (with multipart/related, etc.)
			list($charset, $html_message, $encoding) = self::mimespecialchars($orig_message, false, $hotmail_fix, $line_break);
			$message .= 'Content-Type: text/html; charset=' . $charset . $line_break;
			$message .= 'Content-Transfer-Encoding: ' . ($encoding == '' ? '7bit' : $encoding) . $line_break . $line_break;
			$message .= $html_message . $line_break . '--' . $mime_boundary . '--';
		}
		// Text is good too.
		else
		{
			// Send a plain message first, for the older web clients.
			list(, $plain_message) = self::mimespecialchars($orig_message, false, true, $line_break);
			$message = $plain_message . $line_break . '--' . $mime_boundary . $line_break;

			// Now add an encoded message using the forum's character set.
			list ($charset, $encoded_message, $encoding) = self::mimespecialchars($orig_message, false, false, $line_break);
			$message .= 'Content-Type: text/plain; charset=' . $charset . $line_break;
			$message .= 'Content-Transfer-Encoding: ' . $encoding . $line_break . $line_break;
			$message .= $encoded_message . $line_break . '--' . $mime_boundary . '--';
		}

		// Are we using the mail queue, if so this is where we butt in...
		if ($priority != 0)
			return AddMailQueue(false, $to_array, $subject, $message, $headers, $send_html, $priority, $is_private);

		// If it's a priority mail, send it now - note though that this should NOT be used for sending many at once.
		elseif (!empty($modSettings['mail_limit']))
		{
			list ($last_mail_time, $mails_this_minute) = @explode('|', $modSettings['mail_recent']);
			if (empty($mails_this_minute) || time() > $last_mail_time + 60)
				$new_queue_stat = time() . '|' . 1;
			else
				$new_queue_stat = $last_mail_time . '|' . ((int) $mails_this_minute + 1);

			updateSettings(array('mail_recent' => $new_queue_stat));
		}

		// SMTP or sendmail?
		if ($use_sendmail)
		{
			$subject = strtr($subject, array("\r" => '', "\n" => ''));
			if (!empty($modSettings['mail_strip_carriage']))
			{
				$message = strtr($message, array("\r" => ''));
				$headers = strtr($headers, array("\r" => ''));
			}

			foreach ($to_array as $to)
			{
				if (!mail(strtr($to, array("\r" => '', "\n" => '')), $subject, $message, $headers))
				{
					log_error(sprintf($txt['mail_send_unable'], $to), 'mail');
					$mail_result = false;
				}

				// Wait, wait, I'm still sending here!
				@set_time_limit(300);
				if (function_exists('apache_reset_timeout'))
					@apache_reset_timeout();
			}
		}
		else
			$mail_result = $mail_result && self::send_smtp($to_array, $subject, $message, $headers);

		// Everything go smoothly?
		return $mail_result;
	}
	/**
	 * Prepare text strings for sending as email body or header.
	 * In case there are higher ASCII characters in the given string, this
	 * function will attempt the transport method 'quoted-printable'.
	 * Otherwise the transport method '7bit' is used.
	 *
	 * @param string $string The string
	 * @param bool $with_charset Whether we're specifying a charset ($custom_charset must be set here)
	 * @param bool $hotmail_fix Whether to apply the hotmail fix  (all higher ASCII characters are converted to HTML entities to assure proper display of the mail)
	 * @param string $line_break The linebreak
	 * @param string $custom_charset If set, it uses this character set
	 * @return array An array containing the character set, the converted string and the transport method.
	 */
	public static function mimespecialchars($string, $with_charset = true, $hotmail_fix = false, $line_break = "\r\n", $custom_charset = null)
	{
		global $context;

		$charset = $custom_charset !== null ? $custom_charset : 'UTF-8';

		// This is the fun part....
		if (preg_match_all('~&#(\d{3,8});~', $string, $matches) !== 0 && !$hotmail_fix)
		{
			// Let's, for now, assume there are only &#021;'ish characters.
			$simple = true;

			foreach ($matches[1] as $entity)
				if ($entity > 128)
					$simple = false;
			unset($matches);

			if ($simple)
				$string = preg_replace_callback('~&#(\d{3,8});~', function($m)
				{
					return chr("$m[1]");
				}, $string);
			else
			{
				$string = preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $string);

				// Unicode, baby.
				$charset = 'UTF-8';
			}
		}

		// Convert all special characters to HTML entities...just for Hotmail :-\
		if ($hotmail_fix)
		{
			$entityConvert = function($m)
			{
				$c = $m[1];
				if (strlen($c) === 1 && ord($c[0]) <= 0x7F)
					return $c;
				elseif (strlen($c) === 2 && ord($c[0]) >= 0xC0 && ord($c[0]) <= 0xDF)
					return "&#" . (((ord($c[0]) ^ 0xC0) << 6) + (ord($c[1]) ^ 0x80)) . ";";
				elseif (strlen($c) === 3 && ord($c[0]) >= 0xE0 && ord($c[0]) <= 0xEF)
					return "&#" . (((ord($c[0]) ^ 0xE0) << 12) + ((ord($c[1]) ^ 0x80) << 6) + (ord($c[2]) ^ 0x80)) . ";";
				elseif (strlen($c) === 4 && ord($c[0]) >= 0xF0 && ord($c[0]) <= 0xF7)
					return "&#" . (((ord($c[0]) ^ 0xF0) << 18) + ((ord($c[1]) ^ 0x80) << 12) + ((ord($c[2]) ^ 0x80) << 6) + (ord($c[3]) ^ 0x80)) . ";";
				else
					return "";
			};

			// Convert all 'special' characters to HTML entities.
			return array($charset, preg_replace_callback('~([\x80-\x{10FFFF}])~u', $entityConvert, $string), '7bit');
		}

		// We don't need to mess with the subject line if no special characters were in it..
		elseif (!$hotmail_fix && preg_match('~([^\x09\x0A\x0D\x20-\x7F])~', $string) === 1)
		{
			// Base64 encode.
			$string = base64_encode($string);

			// Show the characterset and the transfer-encoding for header strings.
			if ($with_charset)
				$string = '=?' . $charset . '?B?' . $string . '?=';

			// Break it up in lines (mail body).
			else
				$string = chunk_split($string, 76, $line_break);

			return array($charset, $string, 'base64');
		}

		else
			return array($charset, $string, '7bit');
	}

	/**
	 * Sends mail, like mail() but over SMTP.
	 * It expects no slashes or entities.
	 * @internal
	 *
	 * @param array $mail_to_array Array of strings (email addresses)
	 * @param string $subject Email subject
	 * @param string $message Email message
	 * @param string $headers Email headers
	 * @return boolean Whether it sent or not.
	 */
	public static function send_smtp($mail_to_array, $subject, $message, $headers)
	{
		global $modSettings, $webmaster_email, $txt;

		$modSettings['smtp_host'] = trim($modSettings['smtp_host']);

		// Try POP3 before SMTP?
		// @todo There's no interface for this yet.
		if ($modSettings['mail_type'] == 3 && $modSettings['smtp_username'] != '' && $modSettings['smtp_password'] != '')
		{
			$socket = fsockopen($modSettings['smtp_host'], 110, $errno, $errstr, 2);
			if (!$socket && (substr($modSettings['smtp_host'], 0, 5) == 'smtp.' || substr($modSettings['smtp_host'], 0, 11) == 'ssl://smtp.'))
				$socket = fsockopen(strtr($modSettings['smtp_host'], array('smtp.' => 'pop.')), 110, $errno, $errstr, 2);

			if ($socket)
			{
				fgets($socket, 256);
				fputs($socket, 'USER ' . $modSettings['smtp_username'] . "\r\n");
				fgets($socket, 256);
				fputs($socket, 'PASS ' . base64_decode($modSettings['smtp_password']) . "\r\n");
				fgets($socket, 256);
				fputs($socket, 'QUIT' . "\r\n");

				fclose($socket);
			}
		}

		// Try to connect to the SMTP server... if it doesn't exist, only wait three seconds.
		if (!$socket = fsockopen($modSettings['smtp_host'], empty($modSettings['smtp_port']) ? 25 : $modSettings['smtp_port'], $errno, $errstr, 3))
		{
			// Maybe we can still save this?  The port might be wrong.
			if (substr($modSettings['smtp_host'], 0, 4) == 'ssl:' && (empty($modSettings['smtp_port']) || $modSettings['smtp_port'] == 25))
			{
				if ($socket = fsockopen($modSettings['smtp_host'], 465, $errno, $errstr, 3))
					log_error($txt['smtp_port_ssl']);
			}

			// Unable to connect!  Don't show any error message, but just log one and try to continue anyway.
			if (!$socket)
			{
				log_error($txt['smtp_no_connect'] . ': ' . $errno . ' : ' . $errstr);
				return false;
			}
		}

		// Wait for a response of 220, without "-" continuer.
		if (!self::server_parse(null, $socket, '220'))
			return false;

		// Try and determine the servers name, fall back to the mail servers if not found
		$helo = false;
		if (function_exists('gethostname') && gethostname() !== false)
			$helo = gethostname();
		elseif (function_exists('php_uname'))
			$helo = php_uname('n');
		elseif (array_key_exists('SERVER_NAME', $_SERVER) && !empty($_SERVER['SERVER_NAME']))
			$helo = $_SERVER['SERVER_NAME'];

		if (empty($helo))
			$helo = $modSettings['smtp_host'];

		// SMTP = 1, SMTP - STARTTLS = 2
		if (in_array($modSettings['mail_type'], array(1, 2)) && $modSettings['smtp_username'] != '' && $modSettings['smtp_password'] != '')
		{
			// EHLO could be understood to mean encrypted hello...
			if (self::server_parse('EHLO ' . $helo, $socket, null, $response) == '250')
			{
				// Are we using STARTTLS and does the server support STARTTLS?
				if ($modSettings['mail_type'] == 2 && preg_match("~250( |-)STARTTLS~mi", $response))
				{
					// Send STARTTLS to enable encryption
					if (!self::server_parse('STARTTLS', $socket, '220'))
						return false;
					// Enable the encryption
					if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT))
						return false;
					// Send the EHLO command again
					if (!self::server_parse('EHLO ' . $helo, $socket, null) == '250')
						return false;
				}

				if (!self::server_parse('AUTH LOGIN', $socket, '334'))
					return false;
				// Send the username and password, encoded.
				if (!self::server_parse(base64_encode($modSettings['smtp_username']), $socket, '334'))
					return false;
				// The password is already encoded ;)
				if (!self::server_parse($modSettings['smtp_password'], $socket, '235'))
					return false;
			}
			elseif (!self::server_parse('HELO ' . $helo, $socket, '250'))
				return false;
		}
		else
		{
			// Just say "helo".
			if (!self::server_parse('HELO ' . $helo, $socket, '250'))
				return false;
		}

		// Fix the message for any lines beginning with a period! (the first is ignored, you see.)
		$message = strtr($message, array("\r\n" . '.' => "\r\n" . '..'));

		// !! Theoretically, we should be able to just loop the RCPT TO.
		$mail_to_array = array_values($mail_to_array);
		foreach ($mail_to_array as $i => $mail_to)
		{
			// Reset the connection to send another email.
			if ($i != 0)
			{
				if (!self::server_parse('RSET', $socket, '250'))
					return false;
			}

			// From, to, and then start the data...
			if (!self::server_parse('MAIL FROM: <' . (empty($modSettings['mail_from']) ? $webmaster_email : $modSettings['mail_from']) . '>', $socket, '250'))
				return false;
			if (!self::server_parse('RCPT TO: <' . $mail_to . '>', $socket, '250'))
				return false;
			if (!self::server_parse('DATA', $socket, '354'))
				return false;
			fputs($socket, 'Subject: ' . $subject . "\r\n");
			if (strlen($mail_to) > 0)
				fputs($socket, 'To: <' . $mail_to . '>' . "\r\n");
			fputs($socket, $headers . "\r\n\r\n");
			fputs($socket, $message . "\r\n");

			// Send a ., or in other words "end of data".
			if (!self::server_parse('.', $socket, '250'))
				return false;

			// Almost done, almost done... don't stop me just yet!
			@set_time_limit(300);
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();
		}
		fputs($socket, 'QUIT' . "\r\n");
		fclose($socket);

		return true;
	}

	/**
	 * Parse a message to the SMTP server.
	 * Sends the specified message to the server, and checks for the
	 * expected response.
	 * @internal
	 *
	 * @param string $message The message to send
	 * @param resource $socket Socket to send on
	 * @param string $code The expected response code
	 * @param string $response The response from the SMTP server
	 * @return bool Whether it responded as such.
	 */
	private static function server_parse($message, $socket, $code, &$response = null)
	{
		global $txt;

		if ($message !== null)
			fputs($socket, $message . "\r\n");

		// No response yet.
		$server_response = '';

		while (substr($server_response, 3, 1) != ' ')
		{
			if (!($server_response = fgets($socket, 256)))
			{
				// @todo Change this message to reflect that it may mean bad user/password/server issues/etc.
				log_error($txt['smtp_bad_response']);
				return false;
			}
			$response .= $server_response;
		}

		if ($code === null)
			return substr($server_response, 0, 3);

		if (substr($server_response, 0, 3) != $code)
		{
			log_error($txt['smtp_error'] . $server_response);
			return false;
		}

		return true;
	}
}
