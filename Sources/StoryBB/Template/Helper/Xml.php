<?php

/**
 * This class provide a generic XML helper for StoryBB's templates.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Template\Helper;

/**
 * This class provide a generic XML helper for StoryBB's templates.
 */
class Xml
{
	/**
	 * List the different helpers loaded by default in this class.
	 * @return array Helpers, assocating name to method
	 */
	public static function _list()
	{
		return ([
			'xml' => 'StoryBB\\Template\\Helper\\Xml::export_xml',
		]);
	}

	/**
	 * Export an array of XML data into a template
	 * @param mixed $xml_data The XML data to export
	 * @param string $parent_ident The parent tag to export into
	 * @return SafeString The final XML, fully processed in a safe-for-template format
	 */
	public static function export_xml($xml_data, $parent_ident)
	{
		$recursive = \StoryBB\Template\Helper\Xml::get_recursive_function();
		return new \LightnCandy\SafeString($recursive($xml_data, $parent_ident, '', -1, $recursive));
	}

	/**
	 * Exports a function to recursively descend into XML for use with xml()
	 * @return closure Recursive descender
	 */
	public static function get_recursive_function()
	{
		return function($xml_data, $parent_ident, $child_ident, $level, $recursive)
		{
			$level++;

			$string = "\n" . str_repeat("\t", $level) . '<' . $parent_ident . '>';

			foreach ($xml_data as $key => $data)
			{
				// A group?
				if (is_array($data) && isset($data['identifier']))
					$string .= $recursive($data['children'], $key, $data['identifier'], $level, $recursive);
				// An item...
				elseif (is_array($data) && isset($data['value']))
				{
					$string .= "\n" . str_repeat("\t", $level) . '<' . $child_ident;

					if (!empty($data['attributes']))
						foreach ($data['attributes'] as $k => $v)
							$string .= ' ' . $k . '="' . $v . '"';
					$string .= '><![CDATA[' . cleanXml($data['value']) . ']]></' . $child_ident . '>';
				}

			}

			return $string . "\n" . str_repeat("\t", $level) . '</' . $parent_ident . '>';
		};
	}
}
