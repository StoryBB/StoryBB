<?php

/**
 * The routine maintenance tasks page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Admin\System\Maintenance;

use StoryBB\App;
use StoryBB\Phrase;
use StoryBB\ClassManager;
use StoryBB\Controller\Admin\AbstractAdminController;
use StoryBB\Routing\Behaviours\Administrative;
use StoryBB\Routing\Behaviours\MaintenanceAccessible;
use StoryBB\Routing\RenderResponse;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class Routine extends AbstractAdminController implements Administrative, MaintenanceAccessible
{
	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('system/maintenance/routine', (new Route('/system/maintenance/routine', ['_controller' => [static::class, 'execute']])));
	}

	public function execute(): Response
	{
		$container = App::container();
		$tasks = ClassManager::get_classes_implementing('StoryBB\\Task\\Maintenance\\MaintenanceTask');
		$instances = [];

		$rendercontext = [
			'page_title' => new Phrase('Admin:routine_maintenance'),
			'tasks' => [],
			'route' => $container->get('adminurlgenerator')->generate('system/maintenance/routine'),
		];

		foreach ($tasks as $task)
		{
			$task_instance = App::make($task);
			$classname = strtolower(substr(strrchr($task, '\\'), 1));
			$rendercontext['tasks'][$classname] = [
				'name' => $task_instance->get_name(),
				'description' => $task_instance->get_description(),
			];
			$instances[$classname] = $task_instance;
		}

		$request = App::container()->get('requestvars');
		if ($request->getMethod() == 'POST')
		{
			$session = $container->get('session');
			if (!$request->request->has($session->get('session_var')) || $request->request->get($session->get('session_var')) !== $session->get('session_value'))
			{
				return new RedirectResponse($rendercontext['route']);
			}
			if (!$request->request->has('execute') || !isset($rendercontext['tasks'][$request->request->get('execute')]))
			{
				return new RedirectResponse($rendercontext['route']);
			}

			$instances[$request->request->get('execute')]->execute();

			return new RedirectResponse($rendercontext['route']);
		}

		return $this->render('admin/system/maintenance/routine.twig', 'system/maintenance/routine', $rendercontext);
	}
}
