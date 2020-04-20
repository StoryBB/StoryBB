<?php

/**
 * The help page handler.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller;

use StoryBB\App;
use StoryBB\Container;
use StoryBB\Dependency\Database;
use StoryBB\Dependency\RequestVars;
use StoryBB\Dependency\Session;
use StoryBB\Dependency\UrlGenerator;
use StoryBB\Routing\RenderResponse;
use StoryBB\StringLibrary;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class Login implements Routable
{
	use Database;
	use RequestVars;
	use Session;
	use UrlGenerator;

	/** @var $login_errors Any errors shown in the login form. */
	protected $login_errors = [];

	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('login', (new Route('/login', ['_controller' => [static::class, 'login_form']])));
		$routes->add('login_login', (new Route('/login/login', ['_controller' => [static::class, 'do_login']]))->setMethods(['POST']));
	}

	public function login_form(): Response
	{
		//var_dump($this->login_errors);
		$container = Container::instance();

		$form = $container->instantiate('StoryBB\\Form\\General\\Login', $this->urlgenerator()->generate('login_login'));

		if ($this->requestvars()->headers->get('x-requested-with') == 'XMLHttpRequest')
		{
			return (new Response)->setContent($form->render());
		}

		return $this->return_login_form($form);
	}

	protected function return_login_form($form): Response
	{
		$container = Container::instance();
		return ($container->instantiate(RenderResponse::class))->render('login_form.latte', [
			'form' => $form->render(),
			'login_errors' => $this->login_errors,
		]);
	}

	public function do_login(): Response
	{
		// Are they already logged in?
		if ($this->session()->get('userid'))
		{
			return new RedirectResponse('/');
		}

		$request = $this->requestvars();

		$container = Container::instance();

		$form = $container->instantiate('StoryBB\\Form\\General\\Login', $this->urlgenerator()->generate('login_login'));

		$formdata = $form->get_data();

		if (!$formdata)
		{
			return $this->return_login_form($form);
		}

		$username = $formdata['user'];
		$password = $formdata['passwrd'];
		$stayloggedin = $formdata['cookieneverexp'];

		// @todo validate with integrations

		// Load the user.
		// @todo Replace with User entity when we have a User entity.
		$db = $this->db();
		$request = $db->query('', '
			SELECT passwd, id_member, id_group, is_activated, additional_groups, password_salt, passwd_flood, auth
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
			$this->login_errors[] = 'incorrect_password';
			return $this->return_login_form($form);
		}

		if (empty($user['auth']))
		{
			$user['auth'] = 'StoryBB\\Auth\\Bcrypt';
		}
		$auth = $container->instantiate($user['auth']);

		if ($auth->validate($username, $password, $user['passwd']))
		{
			$redirect = new RedirectResponse($this->get_post_login_redirect());

			if ($stayloggedin)
			{
				$persist = $container->instantiate('StoryBB\\Session\\Persistence');
				$key = $persist->create_for_user($user['id_member']);
				$token = $user['id_member'] . ':' . base64_encode($key);
				$redirect->headers->setCookie(Cookie::create(App::get_global_config_item('cookiename') . '_persist', $token, strtotime('+1 month')));
			}

			$this->session()->migrate(true, 3600);

			$this->session()->set('userid', $user['id_member']);

			return $redirect;
		}

		$this->login_errors[] = 'incorrect_password';
		return $this->return_login_form($form);
	}

	protected function get_post_login_redirect()
	{
		$redirecturl = $this->session()->get('login_url', '/');
		// Needs to be a real URL.
		if (!isset($_SESSION['login_url']) || (strpos($_SESSION['login_url'], 'http://') === false && strpos($_SESSION['login_url'], 'https://') === false))
		{
			$redirecturl = '/';
		}

		return $redirecturl;
	}
}
