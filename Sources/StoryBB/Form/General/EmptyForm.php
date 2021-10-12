<?php

/**
 * Defines an empty form.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
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

/**
 * Defines an empty form.
 */
class EmptyForm extends Base
{
	/**
	 * Defines an empty elements.
	 *
	 * @return void
	 */
	public function define_form(): void
	{
	}
}
