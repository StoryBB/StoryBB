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

use StoryBB\Container;
use StoryBB\Dependency\Database;
use StoryBB\Dependency\RequestVars;
use StoryBB\Dependency\Session;
use StoryBB\StringLibrary;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class Login implements Routable
{
	use Database;
	use RequestVars;
	use Session;

	/** @var $login_errors Any errors shown in the login form. */
	protected $login_errors = [];

	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('login', (new Route('/login', ['_controller' => [static::class, 'login_form']])));
		$routes->add('login_login', (new Route('/login/login', ['_controller' => [static::class, 'do_login']]))->setMethods(['POST']));
	}

	public function login_form(): Response
	{
		var_dump($this->login_errors);
		$container = Container::instance();
		var_dump($container->get('request'));
		die;
	}

	public function do_login(): Response
	{
		$request = $this->requestvars();

		$container = Container::instance();

		$username = $request->request->get('user', '');
		$password = $request->request->get('passwrd', '');
		$stayloggedin = (bool) $request->request->get('cookieneverexp', false);

		if (!$username)
		{
			$this->login_errors[] = 'need_username';
			return $this->login_form();
		}
		if (preg_match('~[<>&"\'=\\\]~', preg_replace('~(&#(\\d{1,7}|x[0-9a-fA-F]{1,6});)~', '', $username)))
		{
			$this->login_errors[] = 'error_invalid_characters_username';
			return $this->login_form();
		}

		if (StringLibrary::strlen($username) > 80)
		{
			$username = StringLibrary::substr($username, 0, 80);
		}

		if (!$password)
		{
			$this->login_errors[] = 'no_password';
			return $this->login_form();
		}

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
			return $this->login_form();
		}

		if (empty($user['auth']))
		{
			$user['auth'] = 'StoryBB\\Auth\\Bcrypt';
		}
		$auth = $container->instantiate($user['auth']);

		if ($auth->validate($username, $password, $user['passwd']))
		{
			$lifetime = $stayloggedin ? 189216000 : 0; // 6 years, or life of session.
			$this->session()->migrate(true, $lifetime);

			$this->session()->set('userid', $user['id_member']);

			return new RedirectResponse('/');
		}

		$this->login_errors[] = 'incorrect_password';
		return $this->login_form();
	}
}
