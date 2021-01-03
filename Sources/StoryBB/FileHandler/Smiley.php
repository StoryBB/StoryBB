<?php

/**
 * A class for serving smileys.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\FileHandler;

use DateInterval;
use Datetime;
use StoryBB\Controller\Unloggable;
use StoryBB\Dependency\Database;
use StoryBB\Dependency\Filesystem;
use StoryBB\Dependency\UrlGenerator;
use StoryBB\Routing\NotFoundResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class Smiley implements Servable, Unloggable
{
	use Database;
	use UrlGenerator;
	use Filesystem;

	public static function register_route(RouteCollection $routes): void
	{
		$routes->add('smiley', new Route('/file/smiley/{id<\d+>}/{timestamp<\d+>?0}', ['_controller' => [static::class, 'action_smiley']]));
	}

	public function action_smiley(int $id, int $timestamp): Response
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
			$url = $this->urlgenerator()->generate('smiley', [
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
