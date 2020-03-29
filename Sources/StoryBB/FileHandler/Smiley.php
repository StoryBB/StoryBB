<?php

/**
 * A class for serving smileys.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\FileHandler;

use DateInterval;
use Datetime;
use StoryBB\Dependency\Database;
use StoryBB\Dependency\Filesystem;
use StoryBB\Dependency\UrlGenerator;
use StoryBB\Routing\NotFoundResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class Smiley implements Servable
{
	use Database;
	use UrlGenerator;
	use Filesystem;

	public static function register_route(RouteCollection $routes): void
	{
		$routes->add('smiley_without_timestamp', new Route('/file/smiley/{id<\d+>}', ['_controller' => [static::class, 'smiley_without_timestamp']]));
		$routes->add('smiley_with_timestamp', new Route('/file/smiley/{id<\d+>}/{timestamp<\d+>}', ['_controller' => [static::class, 'smiley_with_timestamp']]));
	}

	/**
	 * Route for a smiley without an id - to reroute to the version with a timestamp.
	 *
	 * @example URL: /file/smiley/1
	 */
	public function smiley_without_timestamp(int $id): Response
	{
		try
		{
			$file = $this->filesystem()->get_file_details('smiley', $id);
		}
		catch (Exception $e)
		{
			// We didn't have a file of this id?
			return new NotFoundResponse;
		}

		// Otherwise, it's a simple return.
		$url = $this->urlgenerator()->generate('smiley_with_timestamp', [
			'id' => $file['content_id'],
			'timestamp' => $file['timemodified']
		]);
		return new RedirectResponse($url);
	}

	public function smiley_with_timestamp(int $id, int $timestamp): Response
	{
		try
		{
			$file = $this->filesystem()->get_file_details('smiley', $id);
		}
		catch (Exception $e)
		{
			// We didn't have a file of this id?
			return new NotFoundResponse;
		}

		// Did they give the current timestamp?
		if ($timestamp != $file['timemodified'])
		{
			$url = $this->urlgenerator()->generate('smiley_with_timestamp', [
				'id' => $file['content_id'],
				'timestamp' => $file['timemodified']
			]);
			return new RedirectResponse($url);
		}

		// Otherwise, we're serving the file.
		$response = $this->filesystem()->serve($file);

		// Since this isn't a redirect or a not-found, we want long-term caching headers.
		if (!$response->isRedirection() && !$response->isClientError())
		{
			$timemodified = new Datetime('@' . $file['timemodified']);
			$timemodified->add(new DateInterval('P1Y'));
			$response->setExpires($timemodified);
			$response->setPublic();

			$response->setLastModified(new Datetime('@' . $file['timemodified']));
			$response->setEtag(sha1($file['filehash'] . $file['timemodified']));
		}

		return $response;
	}
}
