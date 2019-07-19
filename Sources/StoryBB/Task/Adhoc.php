<?php

/**
 * Base class for every adhoc task which also functions as a sort of interface as well.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task;

/**
 * Base class for every adhoc task which also functions as a sort of interface as well.
 */
abstract class Adhoc
{
	/**
	 * @var array Holds the details for the task
	 */
	protected $_details;

	/**
	 * The constructor.
	 * @param array $details The details for the task
	 */
	public function __construct($details)
	{
		$this->_details = $details;
	}

	/**
	 * The function to actually execute a task
	 * @return mixed
	 */
	abstract public function execute();
}
