<?php

/**
 * The scheduled tasks management page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Admin\System\Tasks;

use StoryBB\App;
use StoryBB\Phrase;
use StoryBB\Controller\Admin\AbstractAdminController;
use StoryBB\Dependency\Database;
use StoryBB\Dependency\RequestVars;
use StoryBB\GenericList\Admin\ScheduledTasksList;
use StoryBB\Routing\Behaviours\Administrative;
use StoryBB\Routing\Behaviours\MaintenanceAccessible;
use StoryBB\Routing\RenderResponse;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class Scheduled extends AbstractAdminController implements Administrative, MaintenanceAccessible
{
	use Database;
	use RequestVars;

	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('system/tasks/scheduled', (new Route('/system/tasks/scheduled', ['_controller' => [static::class, 'execute']])));
		$routes->add('system/tasks/scheduled/toggle_enabled', (new Route('/system/tasks/scheduled/toggle_enabled', ['_controller' => [static::class, 'toggle_enabled']])));
	}

	public function execute(): Response
	{
		$rendercontext = [
			'page_title' => new Phrase('Admin:scheduled_tasks'),
			'list' => App::make(ScheduledTasksList::class),
		];
		return $this->render('admin/generic_list.twig', 'system/tasks/scheduled', $rendercontext);
	}

	public function toggle_enabled(): Response
	{
		$this->assert_session_from_post();

		$request = $this->requestvars();
		$task_id = (int) ($request->request->get('task_id') ?? 0);

		$db = $this->db();
		$result = $db->query('', '
			SELECT class
			FROM {db_prefix}scheduled_tasks
			WHERE id_task = {int:id_task}',
			[
				'id_task' => $task_id,
			]
		);
		$task = $db->fetch_assoc($result);
		$db->free_result($result);

		if (!empty($task) && !empty($task['class']) && class_exists($task['class']))
		{
			$new_state = !empty($request->request->get('new_state'));
			$task_instance = App::make($task['class']);
			$task_instance->set_state($new_state);
		}

		return $this->redirect('system/tasks/scheduled');
	}
}
