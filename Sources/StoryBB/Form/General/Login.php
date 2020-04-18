<?php

/**
 * A base interface for form elements to implement.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Form\General;

use StoryBB\Form\Base;
use StoryBB\Form\Element\Checkbox;
use StoryBB\Form\Element\Password;
use StoryBB\Form\Element\Text;
use StoryBB\Form\Element\Buttons;
use StoryBB\Form\Rule\ValidUsername;

class Login extends Base
{
	public function define_form()
	{
		$general = $this->add_section('general')->label('General:login');

		$general->add(new Text('user'))->label('General:username')->required()->add_validation_rule(new ValidUsername);
		$general->add(new Password('passwrd'))->label('General:password')->required();
		$general->add(new Checkbox('cookieneverexp'))->label('General:always_logged_in');
		$general->add(new Buttons('submit'))->choices(['login' => 'General:login']);
	}
}
