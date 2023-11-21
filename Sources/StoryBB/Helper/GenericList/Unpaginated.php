<?php

/**
 * Generic paginated list.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\GenericList;

use StoryBB\App;
use StoryBB\Phrase;
use StoryBB\Dependency\Database;
use StoryBB\Dependency\TemplateRenderer;
use StoryBB\Routing\RenderResponse;

abstract class Unpaginated
{
	use Database;
	use TemplateRenderer;

	protected $base_url;
	protected $columns;

	public function __construct(string $url = '')
	{
		$this->base_url = $url;
	}

	public function get_id()
	{
		return strtolower(str_replace('\\', '_', get_class($this)));
	}

	abstract public function get_title(): Phrase;

	/**
	 * Returns a generator or an array.
	 *
	 * @return Generator|array Yields a row from the database.
	 */
	abstract public function get_items();

	abstract public function configure_columns(): void;

	public function render(): string
	{
		$this->configure_columns();

		$rendercontext = [
			'id' => $this->get_id(),
			'title' => $this->get_title(),
			'headings' => [],
			'rows' => [],
		];
		foreach ($this->columns as $column)
		{
			$rendercontext['headings'][] = $column->get_label();
		}

		foreach ($this->get_items() as $row)
		{
			$table_row = [];
			foreach ($this->columns as $column_id => $column)
			{
				$table_row[$column_id] = $column->get_value($row, $column_id);
			}
			$rendercontext['rows'][] = $table_row;
		}

		return $this->templaterenderer()->load('components/admin/generic-list.twig')->render($rendercontext);
	}

	public function __toString(): string
	{
		return $this->render();
	}
}
