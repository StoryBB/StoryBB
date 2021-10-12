<?php

/**
 * Defines the login form.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Form\Admin;

use StoryBB\Form\Base;
use StoryBB\Form\Element\Checkbox;
use StoryBB\Form\Element\Password;
use StoryBB\Form\Element\Text;
use StoryBB\Form\Element\Buttons;
use StoryBB\Form\Rule\ValidUsername;

/**
 * Defines the login form.
 */
class Login extends Base
{
	/** @var int CSRF_TOKEN_EXPIRY Login tokens should be valid for 20 minutes at a time. */
	const CSRF_TOKEN_EXPIRY = 1200;

	/**
	 * Defines the general form elements.
	 *
	 * @return void
	 */
	public function define_form(): void
	{
		$general = $this->add_section('general')->label('General:login');

		$general->add(new Text('user'))->label('General:username')->required()->add_validation_rule(new ValidUsername);
		$general->add(new Password('passwrd'))->label('General:password')->required();
		$general->add(new Buttons('submit'))->choices(['login' => 'General:login']);
	}
}
