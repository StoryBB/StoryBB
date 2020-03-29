<?php

/**
 * This class handles constraints.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Schema;

class Constraint
{
	private $from;
	private $to;
	private $relationship;

	private function __construct($fromtable)
	{
		$this->from = $fromtable;
	}

	public static function from($fromtable)
	{
		return new self($fromtable);
	}

	public function to($totable)
	{
		$this->to = $totable;
		// By default we are going from the 'many' side to the 'one' side of a 1:M relation.
		$this->is_Mto1();
		return $this;
	}

	public function from_table()
	{
		return $this->from;
	}

	public function to_table()
	{
		return $this->to;
	}

	public function is_Mto1()
	{
		$this->relationship = 'M:1';
		return $this;
	}

	public function is_1to1()
	{
		$this->relationship = '1:1';
		return $this;
	}

	public function get_relationship_type()
	{
		return $this->relationship;
	}
}
