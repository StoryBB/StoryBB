<?php

/**
 * A class for serving 404s.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Routing;

use Symfony\Component\HttpFoundation\Response;

/**
 * Represents a response of 'not found' in the system.
 */
class NotFoundResponse extends Response
{
	public function __construct(?string $content = '', int $status = 404, array $headers = [])
	{
		parent::__construct('', $status, $headers);

		if (empty($content))
		{
			$content = $this->placeholder_content();
		}
		$this->setContent($content);
		$this->setStatusCode($status);
		$this->setProtocolVersion('1.0');
	}

	protected function placeholder_content() : string
	{
		return '<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8" />

		<title>Not Found</title>
	</head>
	<body>
		Not Found.
	</body>
</html>';
	}
}
