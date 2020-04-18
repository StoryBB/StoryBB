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

namespace StoryBB\Form\Element;

use Latte\Engine;
use StoryBB\Form\Element\Inputtable;
use StoryBB\Form\Element\Traits;

class Checkbox extends Traits\Base implements Inputtable
{
	use Traits\Labelable;
	use Traits\Requirable;

	public function render(Engine $templater, array $rawdata): string
	{
		$rendercontext = [
			'name' => $this->name,
			'required' => $this->required,
			'checked' => !empty($rawdata[$this->name]),
		];

		return $templater->renderToString('form/element/checkbox.latte', $rendercontext);
	}

	public function get_value_from_raw($data)
	{
		return !empty($data[$this->name]);
	}
}
