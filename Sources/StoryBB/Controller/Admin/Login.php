<?php

/**
 * The help page handler.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Admin;

use Exception;
use StoryBB\App;
use StoryBB\Container;
use StoryBB\Routing\Behaviours\Administrative;
use StoryBB\Routing\Behaviours\MaintenanceAccessible;
use StoryBB\Dependency\Session;
use StoryBB\Dependency\Database;
use StoryBB\Dependency\RequestVars;
use StoryBB\Dependency\CurrentUser;
use StoryBB\Phrase;
use StoryBB\Dependency\AdminUrlGenerator;
use StoryBB\Routing\RenderResponse;
use StoryBB\StringLibrary;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class Login extends AbstractAdminController implements Administrative, MaintenanceAccessible
{
	use Session;
	use CurrentUser;
	use Database;
	use RequestVars;
	use AdminUrlGenerator;

	/** @var $login_errors Any errors shown in the login form. */
	protected $login_errors = [];

	public function requires_permissions(): array
	{
		return [];
	}

	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('login', (new Route('/login', ['_controller' => [static::class, 'login_form']])));
		$routes->add('login_login', (new Route('/login/login', ['_controller' => [static::class, 'do_login']]))->setMethods(['POST']));
	}

	public function login_form(): Response
	{
		$container = Container::instance();
		if ($this->currentuser()->is_authenticated())
		{
			return new RedirectResponse($this->adminurlgenerator()->generate('dashboard'));
		}

		$form = $container->instantiate('StoryBB\\Form\\Admin\\Login', $this->adminurlgenerator()->generate('login_login'));

		return $this->return_login_form($form);
	}

	protected function return_login_form($form): Response
	{
		$container = Container::instance();
		return ($container->instantiate(RenderResponse::class))->render('admin/login_form.twig', [
			'form' => $form->render(),
			'login_errors' => $this->login_errors,
		]);
	}

	public function do_login(): Response
	{
		// Are they already logged in?
		if ($this->currentuser()->is_authenticated())
		{
			return new RedirectResponse($this->adminurlgenerator()->generate('dashboard'));
		}

		$request = $this->requestvars();

		$container = Container::instance();

		$form = $container->instantiate('StoryBB\\Form\\Admin\\Login', $this->adminurlgenerator()->generate('login_login'));

		$formdata = $form->get_data();

		if (!$formdata)
		{
			return $this->return_login_form($form);
		}

		$username = $formdata['user'];
		$password = $formdata['passwrd'];

		// @todo validate with integrations

		// @todo spamProtection('login');

		// Load the user.
		// @todo Replace with User entity when we have a User entity.
		$db = $this->db();
		$request = $db->query('', '
			SELECT passwd, id_member, id_group, is_activated, additional_groups, password_salt, passwd_flood, auth, member_name
			FROM {db_prefix}members
			WHERE ' . ($db->is_case_sensitive() ? 'LOWER(member_name) = LOWER({string:user_name})' : 'member_name = {string:user_name}') . '
			LIMIT 1',
			[
				'user_name' => $username,
			]
		);
		$user = $db->fetch_assoc($request);
		$db->free_result($request);

		if (empty($user))
		{
			$this->login_errors[] = new Phrase('Login:incorrect_password');
			return $this->return_login_form($form);
		}

		if (empty($user['auth']))
		{
			$user['auth'] = 'StoryBB\\Auth\\Bcrypt';
		}
		$auth = $container->instantiate($user['auth']);

		if ($auth->validate($username, $password, $user['passwd']))
		{
			if ($user['is_activated'] != 1)
			{
				// @todo better error message than this
				// @todo also validate the user has at least one admin permission to be here
				$this->login_errors[] = sprintf(new Phrase('Login:activate_not_completed'), App::get_global_config_item('boardurl') . '/index.php?action=activate;sa=resend;u=' . $user['id_member']);
				return $this->return_login_form($form);
			}

			$redirect = new RedirectResponse($this->adminurlgenerator()->generate('dashboard'));

			$this->session()->migrate(true, 3600);

			$this->session()->set('userid', $user['id_member']);

			return $redirect;
		}

		// @todo validatePasswordFlood

		$this->login_errors[] = new Phrase('Login:incorrect_password');
		return $this->return_login_form($form);
	}
}
