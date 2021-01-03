<?php
/**
 * Converts the given schema definition into a formatted PlantUML output.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB project
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Schema\Exporter;

use StoryBB\Schema\Schema;

class PlantUML
{
	private $schema = null;
	private $uml = [];
	private $tablegroups = [];

	public function __construct(Schema $schema)
	{
		$this->schema = $schema;
		$this->tablegroups = $schema->get_all_tablegroups();
	}

	private function build_uml()
	{
		$this->uml = [];
		$this->uml[] = '@startuml';

		$this->add_style_rules();

		// First add all the tables.
		foreach ($this->tablegroups as $tablegroup)
		{
			$this->add_tables_from_group(new $tablegroup);
		}
		// Then, to avoid confusing PlantUML, we'll add the constraints afterwards.
		foreach ($this->tablegroups as $tablegroup)
		{
			$this->add_constraints_from_group(new $tablegroup);
		}

		$this->uml[] = '@enduml';
	}

	private function add_style_rules()
	{
		// First, append the very generic definitions for tables.
		$this->uml[] = '!define table(x) class x << (T,#FFAAAA) >>';
		$this->uml[] = '!define primary(x) <u>x</u>';
		$this->uml[] = '';

		// Now we add the stylings for the different 
		$this->uml[] = 'skinparam class {';
		foreach ($this->tablegroups as $class)
		{
			$tablegroup = new $class;
			$colour_scheme = $tablegroup->plantuml_colour_scheme();
			$stereotype = '<<' . $tablegroup->group_description() . '>>';
			if (isset($colour_scheme['background']))
			{
				$this->uml[] = '  BackgroundColor' . $stereotype . ' ' . $colour_scheme['background'];
			}
			if (isset($colour_scheme['border']))
			{
				$this->uml[] = '  BorderColor' . $stereotype . ' ' . $colour_scheme['border'];
			}
			if (isset($colour_scheme['text']))
			{
				$this->uml[] = '  AttributeFontColor' . $stereotype . ' ' . $colour_scheme['text'];
				$this->uml[] = '  FontColor' . $stereotype . ' ' . $colour_scheme['text'];
				$this->uml[] = '  StereotypeFontColor' . $stereotype . ' ' . $colour_scheme['text'];
			}
		}
		$this->uml[] = '}';
	}

	private function add_tables_from_group($tablegroup)
	{
		// Let's make a container for each tablegroup.
		$this->uml[] = '';
		$this->uml[] = 'together {';
		foreach ($tablegroup::return_tables() as $table)
		{
			$this->uml[] = '  table(' . $table->get_table_name() . ') <<' . $tablegroup->group_description() . '>> {';
			foreach ($table->get_columns() as $column_name => $column)
			{
				if ($column->is_primary())
				{
					$this->uml[] = '    primary(' . $column_name . '): ' . $column->get_simple_type();
				}
				else
				{
					$this->uml[] = '    ' . $column_name . ': ' . $column->get_simple_type();
				}
			}

			$this->uml[] = '  }';
		}
		$this->uml[] = '}';
	}

	private function add_constraints_from_group($tablegroup)
	{
		$colour_scheme = $tablegroup::plantuml_colour_scheme();
		foreach ($tablegroup::return_tables() as $table)
		{
			foreach ($table->get_constraints() as $constraint)
			{
				// The syntax for constraints is from('table.column') but PlantUML wants table::column.
				$from = str_replace('.', '::', $constraint->from_table());
				$to = str_replace('.', '::', $constraint->to_table());

				$relationship = $constraint->get_relationship_type();

				$frommarker = '-';
				$tomarker = '-';
				if ($relationship == 'M:1')
				{
					$frommarker = '}';
					$tomarker = '-';
				}

				if (!empty($colour_scheme['border']))
				{
					$this->uml[] = $from . ' ' . $frommarker . '-[#' . $colour_scheme['border'] . ']' . $tomarker . ' ' . $to;
				}
				else
				{
					$this->uml[] = $from . ' ' . $frommarker . '-' . $tomarker . ' ' . $to;
				}
			}
		}
	}

	public function output(): string
	{
		$this->build_uml();
		return implode("\n", $this->uml);
	}
}
