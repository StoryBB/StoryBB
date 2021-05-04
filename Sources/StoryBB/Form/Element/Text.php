<?php

/**
 * Represents a textbox in a form.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Form\Element;

use Latte\Engine;
use StoryBB\Form\Element\Inputtable;
use StoryBB\Form\Element\Traits;

/**
 * Represents a textbox in a form.
 */
class Text extends Traits\Base implements Inputtable
{
	use Traits\Labelable;
	use Traits\Requirable;

	/**
	 * Take the current element, and return the formatted HTML.
	 *
	 * @param Latte\Engine $templater The form template render engine
	 * @param array $rawdata The submitted raw data for the format
	 * @return string The final HTML for this element
	 */
	public function render(Engine $templater, array $rawdata): string
	{
		$rendercontext = [
			'name' => $this->name,
			'required' => $this->required,
			'value' => $rawdata[$this->name] ?? '',
		];

		return $templater->renderToString('form/element/text.latte', $rendercontext);
	}
}
