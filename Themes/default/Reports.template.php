<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

use LightnCandy\LightnCandy;

/**
 * Choose which type of report to run?
 */
function template_report_type()
{
	global $context, $scripturl, $txt;
	
	$data = [
		'context' => $context,
		'scripturl' => $scripturl,
		'txt' => $txt
	];

	$template = file_get_contents(__DIR__ .  "/templates/report_type.hbs");
	if (!$template) {
		die('Select report type template did not load!');
	}

	$phpStr = LightnCandy::compile($template, [
		'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
		'helpers' => [],
	]);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * This is the standard template for showing reports.
 */
function template_main()
{
	global $context, $txt;
	
	$data = [
		'context' => $context,
		'txt' => $txt
	];

	$template = file_get_contents(__DIR__ .  "/templates/report.hbs");
	if (!$template) {
		die('Report template did not load!');
	}

	$phpStr = LightnCandy::compile($template, [
		'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
	    'partials' => Array(
	    	'button_strip' => file_get_contents(__DIR__ .  "/partials/button_strip.hbs")
	    ),
		'helpers' => [
			'eq' => 'logichelper_eq',
			'and' => 'logichelper_and',
		],
	]);
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * Header of the print page!
 */
function template_print_above()
{
	global $context, $settings, $modSettings;

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="UTF-8">
		<title>', $context['page_title'], '</title>
		<link rel="stylesheet" href="', $settings['default_theme_url'], '/css/report.css', $modSettings['browser_cache'], '">
	</head>
	<body>';
}

/**
 * The main print page
 */
function template_print()
{
	global $context;

	// Go through each table!
	foreach ($context['tables'] as $table)
	{
		echo '
		<div style="overflow: visible;', $table['max_width'] != 'auto' ? ' width: ' . $table['max_width'] . 'px;' : '', '">
			<table class="bordercolor">';

		if (!empty($table['title']))
			echo '
				<tr class="title_bar">
					<td colspan="', $table['column_count'], '">
						', $table['title'], '
					</td>
				</tr>';

		// Now do each row!
		$row_number = 0;
		foreach ($table['data'] as $row)
		{
			if ($row_number == 0 && !empty($table['shading']['top']))
				echo '
				<tr class="titlebg">';
			else
				echo '
				<tr class="windowbg">';

			// Now do each column!!
			$column_number = 0;
			foreach ($row as $data)
			{
				// If this is a special separator, skip over!
				if (!empty($data['separator']) && $column_number == 0)
				{
					echo '
					<td colspan="', $table['column_count'], '" class="smalltext">
						<strong>', $data['v'], ':</strong>
					</td>';
					break;
				}

				// Shaded?
				if ($column_number == 0 && !empty($table['shading']['left']))
					echo '
					<td class="titlebg ', $table['align']['shaded'], 'text"', $table['width']['shaded'] != 'auto' ? ' width="' . $table['width']['shaded'] . '"' : '', '>
						', $data['v'] == $table['default_value'] ? '' : ($data['v'] . (empty($data['v']) ? '' : ':')), '
					</td>';
				else
					echo '
					<td class="centertext" ', $table['width']['normal'] != 'auto' ? ' width="' . $table['width']['normal'] . '"' : '', !empty($data['style']) ? ' style="' . $data['style'] . '"' : '', '>
						', $data['v'], '
					</td>';

				$column_number++;
			}

			echo '
				</tr>';

			$row_number++;
		}
		echo '
			</table>
		</div><br>';
	}
}

/**
 * Footer of the print page.
 */
function template_print_below()
{
	echo '
		<div class="copyright">', theme_copyright(), '</div>
	</body>
</html>';
}

?>