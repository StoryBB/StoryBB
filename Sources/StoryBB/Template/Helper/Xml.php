<?php

/**
 * This class provide a generic XML helper for StoryBB's templates.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Template\Helper;

class Xml
{
	public static function _list()
	{
		return ([
			'xml' => 'StoryBB\\Template\\Helper\\Xml::xml',
		]);
	}

	public static function xml($xml_data, $parent_ident)
	{
		$recursive = \StoryBB\Template\Helper\Xml::get_recursive_function();
		return new \LightnCandy\SafeString($recursive($xml_data, $parent_ident, '', -1, $recursive));
	}

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

?>