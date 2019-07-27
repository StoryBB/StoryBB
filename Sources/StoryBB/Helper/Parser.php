<?php

/**
 * Parse content according to its bbc and smiley content.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

use StoryBB\Helper\TLD;

/**
 * Parse content according to its bbc and smiley content.
 */
class Parser
{
	/**
	 * Parse bulletin board code in a string, as well as smileys optionally.
	 *
	 * - only parses bbc tags which are not disabled in disabledBBC.
	 * - handles basic HTML, if enablePostHTML is on.
	 * - caches the from/to replace regular expressions so as not to reload them every time a string is parsed.
	 * - only parses smileys if smileys is true.
	 * - does nothing if the enableBBC setting is off.
	 * - uses the cache_id as a unique identifier to facilitate any caching it may do.
	 *  -returns the modified message.
	 *
	 * @param string $message The message
	 * @param bool $smileys Whether to parse smileys as well
	 * @param string $cache_id The cache ID
	 * @param array $parse_tags If set, only parses these tags rather than all of them
	 * @return string The parsed message
	 */
	public static function parse_bbc($message, $smileys = true, $cache_id = '', $parse_tags = [])
	{
		global $smcFunc, $txt, $scripturl, $context, $modSettings, $user_info, $sourcedir;
		static $bbc_codes = [], $itemcodes = [], $no_autolink_tags = [];
		static $disabled;

		// Don't waste cycles
		if ($message === '')
			return '';

		// Clean up any cut/paste issues we may have
		if ($message !== false)
		{
			$message = self::sanitizeMSCutPaste($message);
		}

		// If the load average is too high, don't parse the BBC.
		if (!empty($context['load_average']) && !empty($modSettings['bbc']) && $context['load_average'] >= $modSettings['bbc'])
		{
			$context['disabled_parse_bbc'] = true;
			return $message;
		}

		if ($smileys !== null && ($smileys == '1' || $smileys == '0'))
			$smileys = (bool) $smileys;

		if (empty($modSettings['enableBBC']) && $message !== false)
		{
			if ($smileys === true)
				self::parse_smileys($message);

			return $message;
		}

		// If we are not doing every tag then we don't cache this run.
		if (!empty($parse_tags) && !empty($bbc_codes))
		{
			$temp_bbc = $bbc_codes;
			$bbc_codes = [];
		}

		// Ensure $modSettings['tld_regex'] contains a valid regex for the autolinker
		if (!empty($modSettings['autoLinkUrls']) && empty($modSettings['tld_regex']))
			TLD::set_tld_regex(true);

		// Allow mods access before entering the main parse_bbc loop
		call_integration_hook('integrate_pre_parsebbc', array(&$message, &$smileys, &$cache_id, &$parse_tags));

		// Sift out the bbc for a performance improvement.
		if (empty($bbc_codes) || $message === false || !empty($parse_tags))
		{
			if (!empty($modSettings['disabledBBC']))
			{
				$disabled = [];

				$temp = explode(',', strtolower($modSettings['disabledBBC']));

				foreach ($temp as $tag)
					$disabled[trim($tag)] = true;
			}

			/* The following bbc are formatted as an array, with keys as follows:

				tag: the tag's name - should be lowercase!

				type: one of...
					- (missing): [tag]parsed content[/tag]
					- unparsed_equals: [tag=xyz]parsed content[/tag]
					- parsed_equals: [tag=parsed data]parsed content[/tag]
					- unparsed_content: [tag]unparsed content[/tag]
					- closed: [tag], [tag/], [tag /]
					- unparsed_commas: [tag=1,2,3]parsed content[/tag]
					- unparsed_commas_content: [tag=1,2,3]unparsed content[/tag]
					- unparsed_equals_content: [tag=...]unparsed content[/tag]

				parameters: an optional array of parameters, for the form
				  [tag abc=123]content[/tag].  The array is an associative array
				  where the keys are the parameter names, and the values are an
				  array which may contain the following:
					- match: a regular expression to validate and match the value.
					- quoted: true if the value should be quoted.
					- validate: callback to evaluate on the data, which is $data.
					- value: a string in which to replace $1 with the data.
					  either it or validate may be used, not both.
					- optional: true if the parameter is optional.

				test: a regular expression to test immediately after the tag's
				  '=', ' ' or ']'.  Typically, should have a \] at the end.
				  Optional.

				content: only available for unparsed_content, closed,
				  unparsed_commas_content, and unparsed_equals_content.
				  $1 is replaced with the content of the tag.  Parameters
				  are replaced in the form {param}.  For unparsed_commas_content,
				  $2, $3, ..., $n are replaced.

				before: only when content is not used, to go before any
				  content.  For unparsed_equals, $1 is replaced with the value.
				  For unparsed_commas, $1, $2, ..., $n are replaced.

				after: similar to before in every way, except that it is used
				  when the tag is closed.

				disabled_content: used in place of content when the tag is
				  disabled.  For closed, default is '', otherwise it is '$1' if
				  block_level is false, '<div>$1</div>' elsewise.

				disabled_before: used in place of before when disabled.  Defaults
				  to '<div>' if block_level, '' if not.

				disabled_after: used in place of after when disabled.  Defaults
				  to '</div>' if block_level, '' if not.

				block_level: set to true the tag is a "block level" tag, similar
				  to HTML.  Block level tags cannot be nested inside tags that are
				  not block level, and will not be implicitly closed as easily.
				  One break following a block level tag may also be removed.

				trim: if set, and 'inside' whitespace after the begin tag will be
				  removed.  If set to 'outside', whitespace after the end tag will
				  meet the same fate.

				validate: except when type is missing or 'closed', a callback to
				  validate the data as $data.  Depending on the tag's type, $data
				  may be a string or an array of strings (corresponding to the
				  replacement.)

				quoted: when type is 'unparsed_equals' or 'parsed_equals' only,
				  may be not set, 'optional', or 'required' corresponding to if
				  the content may be quoted.  This allows the parser to read
				  [tag="abc]def[esdf]"] properly.

				require_parents: an array of tag names, or not set.  If set, the
				  enclosing tag *must* be one of the listed tags, or parsing won't
				  occur.

				require_children: similar to require_parents, if set children
				  won't be parsed if they are not in the list.

				disallow_children: similar to, but very different from,
				  require_children, if it is set the listed tags will not be
				  parsed inside the tag.

				parsed_tags_allowed: an array restricting what BBC can be in the
				  parsed_equals parameter, if desired.
			*/

			$codes = array(
				array(
					'tag' => 'abbr',
					'type' => 'unparsed_equals',
					'before' => '<abbr title="$1">',
					'after' => '</abbr>',
					'quoted' => 'optional',
					'disabled_after' => ' ($1)',
				),
				array(
					'tag' => 'anchor',
					'type' => 'unparsed_equals',
					'test' => '[#]?([A-Za-z][A-Za-z0-9_\-]*)\]',
					'before' => '<span id="post_$1">',
					'after' => '</span>',
				),
				array(
					'tag' => 'attach',
					'type' => 'unparsed_content',
					'parameters' => array(
						'name' => array('optional' => true),
						'type' => array('optional' => true),
						'alt' => array('optional' => true),
						'title' => array('optional' => true),
						'width' => array('optional' => true, 'match' => '(\d+)'),
						'height' => array('optional' => true, 'match' => '(\d+)'),
					),
					'content' => '$1',
					'validate' => function (&$tag, &$data, $disabled, $params) use ($modSettings, $context, $sourcedir, $txt)
					{
						$returnContext = '';

						// BBC or the entire attachments feature is disabled
						if (empty($modSettings['attachmentEnable']) || !empty($disabled['attach']))
							return $data;

						// Save the attach ID.
						$attachID = $data;

						// Kinda need this.
						require_once($sourcedir . '/Subs-Attachments.php');

						$currentAttachment = parseAttachBBC($attachID);

						// parseAttachBBC will return a string ($txt key) rather than diying with a fatal_error. Up to you to decide what to do.
						if (is_string($currentAttachment))
							return $data = !empty($txt[$currentAttachment]) ? $txt[$currentAttachment] : $currentAttachment;

						if (!empty($currentAttachment['is_image']))
						{
							$alt = ' alt="' . (!empty($params['{alt}']) ? $params['{alt}'] : $currentAttachment['name']) . '"';
							$title = !empty($params['{title}']) ? ' title="' . $params['{title}'] . '"' : '';

							$width = !empty($params['{width}']) ? ' width="' . $params['{width}'] . '"' : '';
							$height = !empty($params['{height}']) ? ' height="' . $params['{height}'] . '"' : '';

							if (empty($width) && empty($height))
							{
								$width = ' width="' . $currentAttachment['width'] . '"';
								$height = ' height="' . $currentAttachment['height'] . '"';
							}

							if ($currentAttachment['thumbnail']['has_thumb'] && empty($params['{width}']) && empty($params['{height}']))
								$returnContext .= '<a href="'. $currentAttachment['href']. ';image" id="link_'. $currentAttachment['id']. '" onclick="'. $currentAttachment['thumbnail']['javascript']. '"><img src="'. $currentAttachment['thumbnail']['href']. '"' . $alt . $title . ' id="thumb_'. $currentAttachment['id']. '" class="atc_img"></a>';
							else
								$returnContext .= '<img src="' . $currentAttachment['href'] . ';image"' . $alt . $title . $width . $height . ' class="bbc_img"/>';
						}

						// No image. Show a link.
						else
							$returnContext .= $currentAttachment['link'];

						// Gotta append what we just did.
						$data = $returnContext;
					},
				),
				array(
					'tag' => 'b',
					'before' => '<strong>',
					'after' => '</strong>',
				),
				array(
					'tag' => 'center',
					'before' => '<div class="centertext">',
					'after' => '</div>',
					'block_level' => true,
				),
				array(
					'tag' => 'character',
					'type' => 'unparsed_equals',
					'before' => '<a href="' . $scripturl . '?action=characters;char=$1" class="mention" data-mention="$1">@',
					'after' => '</a>',
				),
				array(
					'tag' => 'code',
					'type' => 'unparsed_content',
					'content' => '<div class="codeheader"><span class="code floatleft">' . $txt['code'] . '</span> <a class="codeoperation sbb_select_text">' . $txt['code_select'] . '</a></div><code class="bbc_code">$1</code>',
					// @todo Maybe this can be simplified?
					'validate' => isset($disabled['code']) ? null : function (&$tag, &$data, $disabled) use ($context)
					{
						if (!isset($disabled['code']))
						{
							$data = str_replace("<pre style=\"display: inline;\">\t</pre>", "\t", $data);
							$data = str_replace("\t", "<span style=\"white-space: pre;\">\t</span>", $data);

							// Recent Opera bug requiring temporary fix. &nsbp; is needed before </code> to avoid broken selection.
							if ($context['browser']['is_opera'])
								$data .= '&nbsp;';
						}
					},
					'block_level' => true,
				),
				array(
					'tag' => 'code',
					'type' => 'unparsed_equals_content',
					'content' => '<div class="codeheader"><span class="code floatleft">' . $txt['code'] . '</span> ($2) <a class="codeoperation sbb_select_text">' . $txt['code_select'] . '</a></div><code class="bbc_code">$1</code>',
					// @todo Maybe this can be simplified?
					'validate' => isset($disabled['code']) ? null : function (&$tag, &$data, $disabled) use ($context)
					{
						if (!isset($disabled['code']))
						{
							$data[0] = str_replace("<pre style=\"display: inline;\">\t</pre>", "\t", $data);
							$data[0] = str_replace("\t", "<span style=\"white-space: pre;\">\t</span>", $data[0]);

							// Recent Opera bug requiring temporary fix. &nsbp; is needed before </code> to avoid broken selection.
							if ($context['browser']['is_opera'])
								$data[0] .= '&nbsp;';
						}
					},
					'block_level' => true,
				),
				array(
					'tag' => 'color',
					'type' => 'unparsed_equals',
					'test' => '(#[\da-fA-F]{3}|#[\da-fA-F]{6}|[A-Za-z]{1,20}|rgb\((?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\s?,\s?){2}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\))\]',
					'before' => '<span style="color: $1;" class="bbc_color">',
					'after' => '</span>',
				),
				array(
					'tag' => 'email',
					'type' => 'unparsed_content',
					'content' => '<a href="mailto:$1" class="bbc_email">$1</a>',
					// @todo Should this respect guest_hideContacts?
					'validate' => function (&$tag, &$data, $disabled)
					{
						$data = strtr($data, array('<br>' => ''));
					},
				),
				array(
					'tag' => 'email',
					'type' => 'unparsed_equals',
					'before' => '<a href="mailto:$1" class="bbc_email">',
					'after' => '</a>',
					// @todo Should this respect guest_hideContacts?
					'disallow_children' => array('email', 'ftp', 'url', 'iurl'),
					'disabled_after' => ' ($1)',
				),
				array(
					'tag' => 'float',
					'type' => 'unparsed_equals',
					'test' => '(left|right)(\s+max=\d+(?:%|px|em|rem|ex|pt|pc|ch|vw|vh|vmin|vmax|cm|mm|in)?)?\]',
					'before' => '<div $1>',
					'after' => '</div>',
					'validate' => function (&$tag, &$data, $disabled)
					{
						$class = 'class="bbc_float float' . (strpos($data, 'left') === 0 ? 'left' : 'right') . '"';

						if (preg_match('~\bmax=(\d+(?:%|px|em|rem|ex|pt|pc|ch|vw|vh|vmin|vmax|cm|mm|in)?)~', $data, $matches))
							$css = ' style="max-width:' . $matches[1] . (is_numeric($matches[1]) ? 'px' : '') . '"';
						else
							$css = '';

						$data = $class . $css;
					},
					'trim' => 'outside',
					'block_level' => true,
				),
				array(
					'tag' => 'font',
					'type' => 'unparsed_equals',
					'test' => '[A-Za-z0-9_,\-\s]+?\]',
					'before' => '<span style="font-family: $1;" class="bbc_font">',
					'after' => '</span>',
				),
				array(
					'tag' => 'html',
					'type' => 'unparsed_content',
					'content' => '<div>$1</div>',
					'block_level' => true,
					'disabled_content' => '$1',
				),
				array(
					'tag' => 'hr',
					'type' => 'closed',
					'content' => '<hr>',
					'block_level' => true,
				),
				array(
					'tag' => 'i',
					'before' => '<i>',
					'after' => '</i>',
				),
				array(
					'tag' => 'img',
					'type' => 'unparsed_content',
					'parameters' => array(
						'alt' => array('optional' => true),
						'title' => array('optional' => true),
						'width' => array('optional' => true, 'value' => ' width="$1"', 'match' => '(\d+)'),
						'height' => array('optional' => true, 'value' => ' height="$1"', 'match' => '(\d+)'),
					),
					'content' => '<img src="$1" alt="{alt}" title="{title}"{width}{height} class="bbc_img resized">',
					'validate' => function (&$tag, &$data, $disabled)
					{
						global $image_proxy_enabled, $image_proxy_secret, $boardurl;

						$data = strtr($data, array('<br>' => ''));
						$scheme = parse_url($data, PHP_URL_SCHEME);
						if ($image_proxy_enabled)
						{
							if (empty($scheme))
								$data = 'http://' . ltrim($data, ':/');

							if ($scheme != 'https')
								$data = $boardurl . '/proxy.php?request=' . urlencode($data) . '&hash=' . md5($data . $image_proxy_secret);
						}
						elseif (empty($scheme))
							$data = '//' . ltrim($data, ':/');
					},
					'disabled_content' => '($1)',
				),
				array(
					'tag' => 'img',
					'type' => 'unparsed_content',
					'content' => '<img src="$1" alt="" class="bbc_img">',
					'validate' => function (&$tag, &$data, $disabled)
					{
						global $image_proxy_enabled, $image_proxy_secret, $boardurl;

						$data = strtr($data, array('<br>' => ''));
						$scheme = parse_url($data, PHP_URL_SCHEME);
						if ($image_proxy_enabled)
						{
							if (empty($scheme))
								$data = 'http://' . ltrim($data, ':/');

							if ($scheme != 'https')
								$data = $boardurl . '/proxy.php?request=' . urlencode($data) . '&hash=' . md5($data . $image_proxy_secret);
						}
						elseif (empty($scheme))
							$data = '//' . ltrim($data, ':/');
					},
					'disabled_content' => '($1)',
				),
				array(
					'tag' => 'iurl',
					'type' => 'unparsed_content',
					'content' => '<a href="$1" class="bbc_link">$1</a>',
					'validate' => function (&$tag, &$data, $disabled)
					{
						$data = strtr($data, array('<br>' => ''));
						$scheme = parse_url($data, PHP_URL_SCHEME);
						if (empty($scheme))
							$data = '//' . ltrim($data, ':/');
					},
				),
				array(
					'tag' => 'iurl',
					'type' => 'unparsed_equals',
					'quoted' => 'optional',
					'before' => '<a href="$1" class="bbc_link">',
					'after' => '</a>',
					'validate' => function (&$tag, &$data, $disabled)
					{
						if (substr($data, 0, 1) == '#')
							$data = '#post_' . substr($data, 1);
						else
						{
							$scheme = parse_url($data, PHP_URL_SCHEME);
							if (empty($scheme))
								$data = '//' . ltrim($data, ':/');
						}
					},
					'disallow_children' => array('email', 'ftp', 'url', 'iurl'),
					'disabled_after' => ' ($1)',
				),
				array(
					'tag' => 'justify',
					'before' => '<div align="justify">',
					'after' => '</div>',
					'block_level' => true,
				),
				array(
					'tag' => 'left',
					'before' => '<div style="text-align: left;">',
					'after' => '</div>',
					'block_level' => true,
				),
				array(
					'tag' => 'li',
					'before' => '<li>',
					'after' => '</li>',
					'trim' => 'outside',
					'require_parents' => array('list'),
					'block_level' => true,
					'disabled_before' => '',
					'disabled_after' => '<br>',
				),
				array(
					'tag' => 'list',
					'before' => '<ul class="bbc_list">',
					'after' => '</ul>',
					'trim' => 'inside',
					'require_children' => array('li', 'list'),
					'block_level' => true,
				),
				array(
					'tag' => 'list',
					'parameters' => array(
						'type' => array('match' => '(none|disc|circle|square|decimal|decimal-leading-zero|lower-roman|upper-roman|lower-alpha|upper-alpha|lower-greek|upper-greek|lower-latin|upper-latin|hebrew|armenian|georgian|cjk-ideographic|hiragana|katakana|hiragana-iroha|katakana-iroha)'),
					),
					'before' => '<ul class="bbc_list" style="list-style-type: {type};">',
					'after' => '</ul>',
					'trim' => 'inside',
					'require_children' => array('li'),
					'block_level' => true,
				),
				array(
					'tag' => 'ltr',
					'before' => '<bdo dir="ltr">',
					'after' => '</bdo>',
					'block_level' => true,
				),
				array(
					'tag' => 'me',
					'type' => 'unparsed_equals',
					'before' => '<div class="meaction">* $1 ',
					'after' => '</div>',
					'quoted' => 'optional',
					'block_level' => true,
					'disabled_before' => '/me ',
					'disabled_after' => '<br>',
				),
				array(
					'tag' => 'member',
					'type' => 'unparsed_equals',
					'before' => '<a href="' . $scripturl . '?action=profile;u=$1" class="mention" data-mention="$1">@',
					'after' => '</a>',
				),
				array(
					'tag' => 'nobbc',
					'type' => 'unparsed_content',
					'content' => '$1',
				),
				array(
					'tag' => 'pre',
					'before' => '<pre>',
					'after' => '</pre>',
				),
				array(
					'tag' => 'quote',
					'before' => '<blockquote><cite>' . $txt['quote'] . '</cite>',
					'after' => '</blockquote>',
					'trim' => 'both',
					'block_level' => true,
				),
				array(
					'tag' => 'quote',
					'parameters' => array(
						'author' => array('match' => '(.{1,192}?)', 'quoted' => true),
					),
					'before' => '<blockquote><cite>' . $txt['quote_from'] . ': {author}</cite>',
					'after' => '</blockquote>',
					'trim' => 'both',
					'block_level' => true,
				),
				array(
					'tag' => 'quote',
					'type' => 'parsed_equals',
					'before' => '<blockquote><cite>' . $txt['quote_from'] . ': $1</cite>',
					'after' => '</blockquote>',
					'trim' => 'both',
					'quoted' => 'optional',
					// Don't allow everything to be embedded with the author name.
					'parsed_tags_allowed' => array('url', 'iurl', 'ftp'),
					'block_level' => true,
				),
				array(
					'tag' => 'quote',
					'parameters' => array(
						'author' => array('match' => '([^<>]{1,192}?)'),
						'link' => array('match' => '(?:board=\d+;)?((?:topic|threadid)=[\dmsg#\./]{1,40}(?:;start=[\dmsg#\./]{1,40})?|msg=\d+?|action=profile;u=\d+)'),
						'date' => array('match' => '(\d+)', 'validate' => 'timeformat'),
					),
					'before' => '<blockquote><cite><a href="' . $scripturl . '?{link}">' . $txt['quote_from'] . ': {author} ' . $txt['search_on'] . ' {date}</a></cite>',
					'after' => '</blockquote>',
					'trim' => 'both',
					'block_level' => true,
				),
				array(
					'tag' => 'quote',
					'parameters' => array(
						'author' => array('match' => '(.{1,192}?)'),
					),
					'before' => '<blockquote><cite>' . $txt['quote_from'] . ': {author}</cite>',
					'after' => '</blockquote>',
					'trim' => 'both',
					'block_level' => true,
				),
				array(
					'tag' => 'right',
					'before' => '<div style="text-align: right;">',
					'after' => '</div>',
					'block_level' => true,
				),
				array(
					'tag' => 'rtl',
					'before' => '<bdo dir="rtl">',
					'after' => '</bdo>',
					'block_level' => true,
				),
				array(
					'tag' => 's',
					'before' => '<s>',
					'after' => '</s>',
				),
				array(
					'tag' => 'size',
					'type' => 'unparsed_equals',
					'test' => '([1-9][\d]?p[xt]|small(?:er)?|large[r]?|x[x]?-(?:small|large)|medium|(0\.[1-9]|[1-9](\.[\d][\d]?)?)?em)\]',
					'before' => '<span style="font-size: $1;" class="bbc_size">',
					'after' => '</span>',
				),
				array(
					'tag' => 'size',
					'type' => 'unparsed_equals',
					'test' => '[1-7]\]',
					'before' => '<span style="font-size: $1;" class="bbc_size">',
					'after' => '</span>',
					'validate' => function (&$tag, &$data, $disabled)
					{
						$sizes = array(1 => 0.7, 2 => 1.0, 3 => 1.35, 4 => 1.45, 5 => 2.0, 6 => 2.65, 7 => 3.95);
						$data = $sizes[$data] . 'em';
					},
				),
				array(
					'tag' => 'sub',
					'before' => '<sub>',
					'after' => '</sub>',
				),
				array(
					'tag' => 'sup',
					'before' => '<sup>',
					'after' => '</sup>',
				),
				array(
					'tag' => 'table',
					'before' => '<table class="bbc_table">',
					'after' => '</table>',
					'trim' => 'inside',
					'require_children' => array('tr'),
					'block_level' => true,
				),
				array(
					'tag' => 'td',
					'before' => '<td>',
					'after' => '</td>',
					'require_parents' => array('tr'),
					'trim' => 'outside',
					'block_level' => true,
					'disabled_before' => '',
					'disabled_after' => '',
				),
				array(
					'tag' => 'tr',
					'before' => '<tr>',
					'after' => '</tr>',
					'require_parents' => array('table'),
					'require_children' => array('td'),
					'trim' => 'both',
					'block_level' => true,
					'disabled_before' => '',
					'disabled_after' => '',
				),
				array(
					'tag' => 'u',
					'before' => '<u>',
					'after' => '</u>',
				),
				array(
					'tag' => 'url',
					'type' => 'unparsed_content',
					'content' => '<a href="$1" class="bbc_link" target="_blank" rel="noopener">$1</a>',
					'validate' => function (&$tag, &$data, $disabled)
					{
						$data = strtr($data, array('<br>' => ''));
						$scheme = parse_url($data, PHP_URL_SCHEME);
						if (empty($scheme))
							$data = '//' . ltrim($data, ':/');
					},
				),
				array(
					'tag' => 'url',
					'type' => 'unparsed_equals',
					'quoted' => 'optional',
					'before' => '<a href="$1" class="bbc_link" target="_blank" rel="noopener">',
					'after' => '</a>',
					'validate' => function (&$tag, &$data, $disabled)
					{
						$scheme = parse_url($data, PHP_URL_SCHEME);
						if (empty($scheme))
							$data = '//' . ltrim($data, ':/');
					},
					'disallow_children' => array('email', 'ftp', 'url', 'iurl'),
					'disabled_after' => ' ($1)',
				),
			);

			// Inside these tags autolink is not recommendable.
			$no_autolink_tags = array(
				'url',
				'iurl',
				'email',
			);

			// Let mods add new BBC without hassle.
			call_integration_hook('integrate_bbc_codes', array(&$codes, &$no_autolink_tags));

			// This is mainly for the bbc manager, so it's easy to add tags above.  Custom BBC should be added above this line.
			if ($message === false)
			{
				if (isset($temp_bbc))
					$bbc_codes = $temp_bbc;
				usort($codes, function ($a, $b) {
					return strcmp($a['tag'], $b['tag']);
				});
				return $codes;
			}

			// So the parser won't skip them.
			$itemcodes = array(
				'*' => 'disc',
				'@' => 'disc',
				'+' => 'square',
				'x' => 'square',
				'#' => 'square',
				'o' => 'circle',
				'O' => 'circle',
				'0' => 'circle',
			);
			if (!isset($disabled['li']) && !isset($disabled['list']))
			{
				foreach ($itemcodes as $c => $dummy)
					$bbc_codes[$c] = [];
			}

			foreach ($codes as $code)
			{
				// Make it easier to process parameters later
				if (!empty($code['parameters']))
					ksort($code['parameters'], SORT_STRING);

				// If we are not doing every tag only do ones we are interested in.
				if (empty($parse_tags) || in_array($code['tag'], $parse_tags))
					$bbc_codes[substr($code['tag'], 0, 1)][] = $code;
			}
			$codes = null;
		}

		// Shall we take the time to cache this?
		if ($cache_id != '' && !empty($modSettings['cache_enable']) && (($modSettings['cache_enable'] >= 2 && isset($message[1000])) || isset($message[2400])) && empty($parse_tags))
		{
			// It's likely this will change if the message is modified.
			$cache_key = 'parse:' . $cache_id . '-' . md5(md5($message) . '-' . $smileys . (empty($disabled) ? '' : implode(',', array_keys($disabled))) . json_encode($context['browser']) . $txt['lang_locale'] . $user_info['time_offset'] . $user_info['time_format']);

			if (($temp = cache_get_data($cache_key, 240)) != null)
				return $temp;

			$cache_t = microtime(true);
		}

		$open_tags = [];
		$message = strtr($message, array("\n" => '<br>'));

		$alltags = [];
		foreach ($bbc_codes as $section) {
			foreach ($section as $code) {
				$alltags[] = $code['tag'];
			}
		}
		$alltags_regex = '\b' . implode("\b|\b", array_unique($alltags)) . '\b';

		$pos = -1;
		while ($pos !== false)
		{
			$last_pos = isset($last_pos) ? max($pos, $last_pos) : $pos;
			preg_match('~\[/?(?=' . $alltags_regex . ')~i', $message, $matches, PREG_OFFSET_CAPTURE, $pos + 1);
			$pos = isset($matches[0][1]) ? $matches[0][1] : false;

			// Failsafe.
			if ($pos === false || $last_pos > $pos)
				$pos = strlen($message) + 1;

			// Can't have a one letter smiley, URL, or email! (sorry.)
			if ($last_pos < $pos - 1)
			{
				// Make sure the $last_pos is not negative.
				$last_pos = max($last_pos, 0);

				// Pick a block of data to do some raw fixing on.
				$data = substr($message, $last_pos, $pos - $last_pos);

				// Take care of some HTML!
				if (!empty($modSettings['enablePostHTML']) && strpos($data, '&lt;') !== false)
				{
					$data = preg_replace('~&lt;a\s+href=((?:&quot;)?)((?:https?://|ftps?://|mailto:|tel:)\S+?)\\1&gt;(.*?)&lt;/a&gt;~i', '[url=&quot;$2&quot;]$3[/url]', $data);

					// <br> should be empty.
					$empty_tags = array('br', 'hr');
					foreach ($empty_tags as $tag)
						$data = str_replace(array('&lt;' . $tag . '&gt;', '&lt;' . $tag . '/&gt;', '&lt;' . $tag . ' /&gt;'), '<' . $tag . '>', $data);

					// b, u, i, s, pre... basic tags.
					$closable_tags = array('b', 'u', 'i', 's', 'em', 'ins', 'del', 'pre', 'blockquote', 'strong');
					foreach ($closable_tags as $tag)
					{
						$diff = substr_count($data, '&lt;' . $tag . '&gt;') - substr_count($data, '&lt;/' . $tag . '&gt;');
						$data = strtr($data, array('&lt;' . $tag . '&gt;' => '<' . $tag . '>', '&lt;/' . $tag . '&gt;' => '</' . $tag . '>'));

						if ($diff > 0)
							$data = substr($data, 0, -1) . str_repeat('</' . $tag . '>', $diff) . substr($data, -1);
					}

					// Do <img ...> - with security... action= -> action-.
					preg_match_all('~&lt;img\s+src=((?:&quot;)?)((?:https?://|ftps?://)\S+?)\\1(?:\s+alt=(&quot;.*?&quot;|\S*?))?(?:\s?/)?&gt;~i', $data, $matches, PREG_PATTERN_ORDER);
					if (!empty($matches[0]))
					{
						$replaces = [];
						foreach ($matches[2] as $match => $imgtag)
						{
							$alt = empty($matches[3][$match]) ? '' : ' alt=' . preg_replace('~^&quot;|&quot;$~', '', $matches[3][$match]);

							// Remove action= from the URL - no funny business, now.
							if (preg_match('~action(=|%3d)(?!dlattach)~i', $imgtag) != 0)
								$imgtag = preg_replace('~action(?:=|%3d)(?!dlattach)~i', 'action-', $imgtag);

							// Check if the image is larger than allowed.
							if (!empty($modSettings['max_image_width']) && !empty($modSettings['max_image_height']))
							{
								list ($width, $height) = url_image_size($imgtag);

								if (!empty($modSettings['max_image_width']) && $width > $modSettings['max_image_width'])
								{
									$height = (int) (($modSettings['max_image_width'] * $height) / $width);
									$width = $modSettings['max_image_width'];
								}

								if (!empty($modSettings['max_image_height']) && $height > $modSettings['max_image_height'])
								{
									$width = (int) (($modSettings['max_image_height'] * $width) / $height);
									$height = $modSettings['max_image_height'];
								}

								// Set the new image tag.
								$replaces[$matches[0][$match]] = '[img width=' . $width . ' height=' . $height . $alt . ']' . $imgtag . '[/img]';
							}
							else
								$replaces[$matches[0][$match]] = '[img' . $alt . ']' . $imgtag . '[/img]';
						}

						$data = strtr($data, $replaces);
					}
				}

				if (!empty($modSettings['autoLinkUrls']))
				{
					// Are we inside tags that should be auto linked?
					$no_autolink_area = false;
					if (!empty($open_tags))
					{
						foreach ($open_tags as $open_tag)
							if (in_array($open_tag['tag'], $no_autolink_tags))
								$no_autolink_area = true;
					}

					// Don't go backwards.
					// @todo Don't think is the real solution....
					$lastAutoPos = isset($lastAutoPos) ? $lastAutoPos : 0;
					if ($pos < $lastAutoPos)
						$no_autolink_area = true;
					$lastAutoPos = $pos;

					if (!$no_autolink_area)
					{
						// Parse any URLs
						if (!isset($disabled['url']) && strpos($data, '[url') === false)
						{
							$url_regex = '
							(?:
								# IRIs with a scheme (or at least an opening "//")
								(?:
									# URI scheme (or lack thereof for schemeless URLs)
									(?:
										# URL scheme and colon
										\b[a-z][\w\-]+:
										| # or
										# A boundary followed by two slashes for schemeless URLs
										(?<=^|\W)(?=//)
									)

									# IRI "authority" chunk
									(?:
										# 2 slashes for IRIs with an "authority"
										//
										# then a domain name
										(?:
											# Either the reserved "localhost" domain name
											localhost
											| # or
											# a run of Unicode domain name characters and a dot
											[\p{L}\p{M}\p{N}\-.:@]+\.
											# and then a TLD valid in the DNS or the reserved "local" TLD
											(?:'. $modSettings['tld_regex'] .'|local)
										)
										# followed by a non-domain character or end of line
										(?=[^\p{L}\p{N}\-.]|$)

										| # Or, if there is no "authority" per se (e.g. mailto: URLs) ...

										# a run of IRI characters
										[\p{L}\p{N}][\p{L}\p{M}\p{N}\-.:@]+[\p{L}\p{M}\p{N}]
										# and then a dot and a closing IRI label
										\.[\p{L}\p{M}\p{N}\-]+
									)
								)

								| # or

								# Naked domains (e.g. "example.com" in "Go to example.com for an example.")
								(?:
									# Preceded by start of line or a non-domain character
									(?<=^|[^\p{L}\p{M}\p{N}\-:@])

									# A run of Unicode domain name characters (excluding [:@])
									[\p{L}\p{N}][\p{L}\p{M}\p{N}\-.]+[\p{L}\p{M}\p{N}]
									# and then a dot and a valid TLD
									\.' . $modSettings['tld_regex'] . '

									# Followed by either:
									(?=
										# end of line or a non-domain character (excluding [.:@])
										$|[^\p{L}\p{N}\-]
										| # or
										# a dot followed by end of line or a non-domain character (excluding [.:@])
										\.(?=$|[^\p{L}\p{N}\-])
									)
								)
							)

							# IRI path, query, and fragment (if present)
							(?:
								# If any of these parts exist, must start with a single /
								/

								# And then optionally:
								(?:
									# One or more of:
									(?:
										# a run of non-space, non-()<>
										[^\s()<>]+
										| # or
										# balanced parens, up to 2 levels
										\(([^\s()<>]+|(\([^\s()<>]+\)))*\)
									)+

									# End with:
									(?:
										# balanced parens, up to 2 levels
										\(([^\s()<>]+|(\([^\s()<>]+\)))*\)
										| # or
										# not a space or one of these punct char
										[^\s`!()\[\]{};:\'".,<>?«»“”‘’/]
										| # or
										# a trailing slash (but not two in a row)
										(?<!/)/
									)
								)?
							)?
							';

							$data = preg_replace_callback('~' . $url_regex . '~xiu', function ($matches) {
								$url = array_shift($matches);

								$scheme = parse_url($url, PHP_URL_SCHEME);

								if ($scheme == 'mailto')
								{
									$email_address = str_replace('mailto:', '', $url);
									if (!isset($disabled['email']) && filter_var($email_address, FILTER_VALIDATE_EMAIL) !== false)
										return '[email=' . $email_address . ']' . $url . '[/email]';
									else
										return $url;
								}

								// Are we linking a schemeless URL or naked domain name (e.g. "example.com")?
								if (empty($scheme))
									$fullUrl = '//' . ltrim($url, ':/');
								else
									$fullUrl = $url;

								return '[url=&quot;' . str_replace(array('[', ']'), array('&#91;', '&#93;'), $fullUrl) . '&quot;]' . $url . '[/url]';
							}, $data);
						}

						// Next, emails...  Must be careful not to step on enablePostHTML logic above...
						if (!isset($disabled['email']) && strpos($data, '@') !== false && strpos($data, '[email') === false && stripos($data, 'mailto:') === false)
						{
							$email_regex = '
							# Preceded by a non-domain character or start of line
							(?<=^|[^\p{L}\p{M}\p{N}\-\.])

							# An email address
							[\p{L}\p{M}\p{N}_\-.]{1,80}
							@
							[\p{L}\p{M}\p{N}\-.]+
							\.
							'. $modSettings['tld_regex'] . '

							# Followed by either:
							(?=
								# end of line or a non-domain character (excluding the dot)
								$|[^\p{L}\p{M}\p{N}\-]
								| # or
								# a dot followed by end of line or a non-domain character
								\.(?=$|[^\p{L}\p{M}\p{N}\-])
							)';

							$data = preg_replace('~' . $email_regex . '~xiu', '[email]$0[/email]', $data);
						}
					}
				}

				$data = strtr($data, array("\t" => '&nbsp;&nbsp;&nbsp;'));

				// If it wasn't changed, no copying or other boring stuff has to happen!
				if ($data != substr($message, $last_pos, $pos - $last_pos))
				{
					$message = substr($message, 0, $last_pos) . $data . substr($message, $pos);

					// Since we changed it, look again in case we added or removed a tag.  But we don't want to skip any.
					$old_pos = strlen($data) + $last_pos;
					$pos = strpos($message, '[', $last_pos);
					$pos = $pos === false ? $old_pos : min($pos, $old_pos);
				}
			}

			// Are we there yet?  Are we there yet?
			if ($pos >= strlen($message) - 1)
				break;

			$tags = strtolower($message[$pos + 1]);

			if ($tags == '/' && !empty($open_tags))
			{
				$pos2 = strpos($message, ']', $pos + 1);
				if ($pos2 == $pos + 2)
					continue;

				$look_for = strtolower(substr($message, $pos + 2, $pos2 - $pos - 2));

				$to_close = [];
				$block_level = null;

				do
				{
					$tag = array_pop($open_tags);
					if (!$tag)
						break;

					if (!empty($tag['block_level']))
					{
						// Only find out if we need to.
						if ($block_level === false)
						{
							array_push($open_tags, $tag);
							break;
						}

						// The idea is, if we are LOOKING for a block level tag, we can close them on the way.
						if (strlen($look_for) > 0 && isset($bbc_codes[$look_for[0]]))
						{
							foreach ($bbc_codes[$look_for[0]] as $temp)
								if ($temp['tag'] == $look_for)
								{
									$block_level = !empty($temp['block_level']);
									break;
								}
						}

						if ($block_level !== true)
						{
							$block_level = false;
							array_push($open_tags, $tag);
							break;
						}
					}

					$to_close[] = $tag;
				}
				while ($tag['tag'] != $look_for);

				// Did we just eat through everything and not find it?
				if ((empty($open_tags) && (empty($tag) || $tag['tag'] != $look_for)))
				{
					$open_tags = $to_close;
					continue;
				}
				elseif (!empty($to_close) && $tag['tag'] != $look_for)
				{
					if ($block_level === null && isset($look_for[0], $bbc_codes[$look_for[0]]))
					{
						foreach ($bbc_codes[$look_for[0]] as $temp)
							if ($temp['tag'] == $look_for)
							{
								$block_level = !empty($temp['block_level']);
								break;
							}
					}

					// We're not looking for a block level tag (or maybe even a tag that exists...)
					if (!$block_level)
					{
						foreach ($to_close as $tag)
							array_push($open_tags, $tag);
						continue;
					}
				}

				foreach ($to_close as $tag)
				{
					$message = substr($message, 0, $pos) . "\n" . $tag['after'] . "\n" . substr($message, $pos2 + 1);
					$pos += strlen($tag['after']) + 2;
					$pos2 = $pos - 1;

					// See the comment at the end of the big loop - just eating whitespace ;).
					$whitespace_regex = '';
					if (!empty($tag['block_level']))
						$whitespace_regex .= '(&nbsp;|\s)*(<br>)?';
					// Trim one line of whitespace after unnested tags, but all of it after nested ones
					if (!empty($tag['trim']) && $tag['trim'] != 'inside')
						$whitespace_regex .= empty($tag['require_parents']) ? '(&nbsp;|\s)*' : '(<br>|&nbsp;|\s)*';

					if (!empty($whitespace_regex) && preg_match('~' . $whitespace_regex . '~', substr($message, $pos), $matches) != 0)
						$message = substr($message, 0, $pos) . substr($message, $pos + strlen($matches[0]));
				}

				if (!empty($to_close))
				{
					$to_close = [];
					$pos--;
				}

				continue;
			}

			// No tags for this character, so just keep going (fastest possible course.)
			if (!isset($bbc_codes[$tags]))
				continue;

			$inside = empty($open_tags) ? null : $open_tags[count($open_tags) - 1];
			$tag = null;
			foreach ($bbc_codes[$tags] as $possible)
			{
				$pt_strlen = strlen($possible['tag']);

				// Not a match?
				if (strtolower(substr($message, $pos + 1, $pt_strlen)) != $possible['tag'])
					continue;

				$next_c = $message[$pos + 1 + $pt_strlen];

				// A test validation?
				if (isset($possible['test']) && preg_match('~^' . $possible['test'] . '~', substr($message, $pos + 1 + $pt_strlen + 1)) === 0)
					continue;
				// Do we want parameters?
				elseif (!empty($possible['parameters']))
				{
					if ($next_c != ' ')
						continue;
				}
				elseif (isset($possible['type']))
				{
					// Do we need an equal sign?
					if (in_array($possible['type'], array('unparsed_equals', 'unparsed_commas', 'unparsed_commas_content', 'unparsed_equals_content', 'parsed_equals')) && $next_c != '=')
						continue;
					// Maybe we just want a /...
					if ($possible['type'] == 'closed' && $next_c != ']' && substr($message, $pos + 1 + $pt_strlen, 2) != '/]' && substr($message, $pos + 1 + $pt_strlen, 3) != ' /]')
						continue;
					// An immediate ]?
					if ($possible['type'] == 'unparsed_content' && $next_c != ']')
						continue;
				}
				// No type means 'parsed_content', which demands an immediate ] without parameters!
				elseif ($next_c != ']')
					continue;

				// Check allowed tree?
				if (isset($possible['require_parents']) && ($inside === null || !in_array($inside['tag'], $possible['require_parents'])))
					continue;
				elseif (isset($inside['require_children']) && !in_array($possible['tag'], $inside['require_children']))
					continue;
				// If this is in the list of disallowed child tags, don't parse it.
				elseif (isset($inside['disallow_children']) && in_array($possible['tag'], $inside['disallow_children']))
					continue;

				$pos1 = $pos + 1 + $pt_strlen + 1;

				// Quotes can have alternate styling, we do this php-side due to all the permutations of quotes.
				if ($possible['tag'] == 'quote')
				{
					// Start with standard
					$quote_alt = false;
					foreach ($open_tags as $open_quote)
					{
						// Every parent quote this quote has flips the styling
						if ($open_quote['tag'] == 'quote')
							$quote_alt = !$quote_alt;
					}
					// Add a class to the quote to style alternating blockquotes
					$possible['before'] = strtr($possible['before'], array('<blockquote>' => '<blockquote class="bbc_' . ($quote_alt ? 'alternate' : 'standard') . '_quote">'));
				}

				// This is long, but it makes things much easier and cleaner.
				if (!empty($possible['parameters']))
				{
					// Build a regular expression for each parameter for the current tag.
					$preg = [];
					foreach ($possible['parameters'] as $p => $info)
						$preg[] = '(\s+' . $p . '=' . (empty($info['quoted']) ? '' : '&quot;') . (isset($info['match']) ? $info['match'] : '(.+?)') . (empty($info['quoted']) ? '' : '&quot;') . '\s*)' . (empty($info['optional']) ? '' : '?');

					// Extract the string that potentially holds our parameters.
					$blob = preg_split('~\[/?(?:' . $alltags_regex . ')~i', substr($message, $pos));
					$blobs = preg_split('~\]~i', $blob[1]);

					$splitters = implode('=|', array_keys($possible['parameters'])) . '=';

					// Progressively append more blobs until we find our parameters or run out of blobs
					$blob_counter = 1;
					while ($blob_counter <= count($blobs))
					{

						$given_param_string = implode(']', array_slice($blobs, 0, $blob_counter++));

						$given_params = preg_split('~\s(?=(' . $splitters . '))~i', $given_param_string);
						sort($given_params, SORT_STRING);

						$match = preg_match('~^' . implode('', $preg) . '$~i', implode(' ', $given_params), $matches) !== 0;

						if ($match)
							$blob_counter = count($blobs) + 1;
					}

					// Didn't match our parameter list, try the next possible.
					if (!$match)
						continue;

					$params = [];
					for ($i = 1, $n = count($matches); $i < $n; $i += 2)
					{
						$key = strtok(ltrim($matches[$i]), '=');
						if (isset($possible['parameters'][$key]['value']))
							$params['{' . $key . '}'] = strtr($possible['parameters'][$key]['value'], array('$1' => $matches[$i + 1]));
						elseif (isset($possible['parameters'][$key]['validate']))
							$params['{' . $key . '}'] = $possible['parameters'][$key]['validate']($matches[$i + 1]);
						else
							$params['{' . $key . '}'] = $matches[$i + 1];

						// Just to make sure: replace any $ or { so they can't interpolate wrongly.
						$params['{' . $key . '}'] = strtr($params['{' . $key . '}'], array('$' => '&#036;', '{' => '&#123;'));
					}

					foreach ($possible['parameters'] as $p => $info)
					{
						if (!isset($params['{' . $p . '}']))
							$params['{' . $p . '}'] = '';
					}

					$tag = $possible;

					// Put the parameters into the string.
					if (isset($tag['before']))
						$tag['before'] = strtr($tag['before'], $params);
					if (isset($tag['after']))
						$tag['after'] = strtr($tag['after'], $params);
					if (isset($tag['content']))
						$tag['content'] = strtr($tag['content'], $params);

					$pos1 += strlen($given_param_string);
				}
				else
				{
					$tag = $possible;
					$params = [];
				}
				break;
			}

			// Item codes are complicated buggers... they are implicit [li]s and can make [list]s!
			if ($smileys !== false && $tag === null && isset($itemcodes[$message[$pos + 1]]) && $message[$pos + 2] == ']' && !isset($disabled['list']) && !isset($disabled['li']))
			{
				if ($message[$pos + 1] == '0' && !in_array($message[$pos - 1], array(';', ' ', "\t", "\n", '>')))
					continue;

				$tag = $itemcodes[$message[$pos + 1]];

				// First let's set up the tree: it needs to be in a list, or after an li.
				if ($inside === null || ($inside['tag'] != 'list' && $inside['tag'] != 'li'))
				{
					$open_tags[] = array(
						'tag' => 'list',
						'after' => '</ul>',
						'block_level' => true,
						'require_children' => array('li'),
						'disallow_children' => isset($inside['disallow_children']) ? $inside['disallow_children'] : null,
					);
					$code = '<ul class="bbc_list">';
				}
				// We're in a list item already: another itemcode?  Close it first.
				elseif ($inside['tag'] == 'li')
				{
					array_pop($open_tags);
					$code = '</li>';
				}
				else
					$code = '';

				// Now we open a new tag.
				$open_tags[] = array(
					'tag' => 'li',
					'after' => '</li>',
					'trim' => 'outside',
					'block_level' => true,
					'disallow_children' => isset($inside['disallow_children']) ? $inside['disallow_children'] : null,
				);

				// First, open the tag...
				$code .= '<li' . ($tag == '' ? '' : ' type="' . $tag . '"') . '>';
				$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos + 3);
				$pos += strlen($code) - 1 + 2;

				// Next, find the next break (if any.)  If there's more itemcode after it, keep it going - otherwise close!
				$pos2 = strpos($message, '<br>', $pos);
				$pos3 = strpos($message, '[/', $pos);
				if ($pos2 !== false && ($pos2 <= $pos3 || $pos3 === false))
				{
					preg_match('~^(<br>|&nbsp;|\s|\[)+~', substr($message, $pos2 + 4), $matches);
					$message = substr($message, 0, $pos2) . (!empty($matches[0]) && substr($matches[0], -1) == '[' ? '[/li]' : '[/li][/list]') . substr($message, $pos2);

					$open_tags[count($open_tags) - 2]['after'] = '</ul>';
				}
				// Tell the [list] that it needs to close specially.
				else
				{
					// Move the li over, because we're not sure what we'll hit.
					$open_tags[count($open_tags) - 1]['after'] = '';
					$open_tags[count($open_tags) - 2]['after'] = '</li></ul>';
				}

				continue;
			}

			// Implicitly close lists and tables if something other than what's required is in them.  This is needed for itemcode.
			if ($tag === null && $inside !== null && !empty($inside['require_children']))
			{
				array_pop($open_tags);

				$message = substr($message, 0, $pos) . "\n" . $inside['after'] . "\n" . substr($message, $pos);
				$pos += strlen($inside['after']) - 1 + 2;
			}

			// No tag?  Keep looking, then.  Silly people using brackets without actual tags.
			if ($tag === null)
				continue;

			// Propagate the list to the child (so wrapping the disallowed tag won't work either.)
			if (isset($inside['disallow_children']))
				$tag['disallow_children'] = isset($tag['disallow_children']) ? array_unique(array_merge($tag['disallow_children'], $inside['disallow_children'])) : $inside['disallow_children'];

			// Is this tag disabled?
			if (isset($disabled[$tag['tag']]))
			{
				if (!isset($tag['disabled_before']) && !isset($tag['disabled_after']) && !isset($tag['disabled_content']))
				{
					$tag['before'] = !empty($tag['block_level']) ? '<div>' : '';
					$tag['after'] = !empty($tag['block_level']) ? '</div>' : '';
					$tag['content'] = isset($tag['type']) && $tag['type'] == 'closed' ? '' : (!empty($tag['block_level']) ? '<div>$1</div>' : '$1');
				}
				elseif (isset($tag['disabled_before']) || isset($tag['disabled_after']))
				{
					$tag['before'] = isset($tag['disabled_before']) ? $tag['disabled_before'] : (!empty($tag['block_level']) ? '<div>' : '');
					$tag['after'] = isset($tag['disabled_after']) ? $tag['disabled_after'] : (!empty($tag['block_level']) ? '</div>' : '');
				}
				else
					$tag['content'] = $tag['disabled_content'];
			}

			// we use this a lot
			$tag_strlen = strlen($tag['tag']);

			// The only special case is 'html', which doesn't need to close things.
			if (!empty($tag['block_level']) && $tag['tag'] != 'html' && empty($inside['block_level']))
			{
				$n = count($open_tags) - 1;
				while (empty($open_tags[$n]['block_level']) && $n >= 0)
					$n--;

				// Close all the non block level tags so this tag isn't surrounded by them.
				for ($i = count($open_tags) - 1; $i > $n; $i--)
				{
					$message = substr($message, 0, $pos) . "\n" . $open_tags[$i]['after'] . "\n" . substr($message, $pos);
					$ot_strlen = strlen($open_tags[$i]['after']);
					$pos += $ot_strlen + 2;
					$pos1 += $ot_strlen + 2;

					// Trim or eat trailing stuff... see comment at the end of the big loop.
					$whitespace_regex = '';
					if (!empty($tag['block_level']))
						$whitespace_regex .= '(&nbsp;|\s)*(<br>)?';
					if (!empty($tag['trim']) && $tag['trim'] != 'inside')
						$whitespace_regex .= empty($tag['require_parents']) ? '(&nbsp;|\s)*' : '(<br>|&nbsp;|\s)*';
					if (!empty($whitespace_regex) && preg_match('~' . $whitespace_regex . '~', substr($message, $pos), $matches) != 0)
						$message = substr($message, 0, $pos) . substr($message, $pos + strlen($matches[0]));

					array_pop($open_tags);
				}
			}

			// Can't read past the end of the message
			$pos1 = min(strlen($message), $pos1);

			// No type means 'parsed_content'.
			if (!isset($tag['type']))
			{
				// @todo Check for end tag first, so people can say "I like that [i] tag"?
				$open_tags[] = $tag;
				$message = substr($message, 0, $pos) . "\n" . $tag['before'] . "\n" . substr($message, $pos1);
				$pos += strlen($tag['before']) - 1 + 2;
			}
			// Don't parse the content, just skip it.
			elseif ($tag['type'] == 'unparsed_content')
			{
				$pos2 = stripos($message, '[/' . substr($message, $pos + 1, $tag_strlen) . ']', $pos1);
				if ($pos2 === false)
					continue;

				$data = substr($message, $pos1, $pos2 - $pos1);

				if (!empty($tag['block_level']) && substr($data, 0, 4) == '<br>')
					$data = substr($data, 4);

				if (isset($tag['validate']))
					$tag['validate']($tag, $data, $disabled, $params);

				$code = strtr($tag['content'], array('$1' => $data));
				$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + 3 + $tag_strlen);

				$pos += strlen($code) - 1 + 2;
				$last_pos = $pos + 1;

			}
			// Don't parse the content, just skip it.
			elseif ($tag['type'] == 'unparsed_equals_content')
			{
				// The value may be quoted for some tags - check.
				if (isset($tag['quoted']))
				{
					$quoted = substr($message, $pos1, 6) == '&quot;';
					if ($tag['quoted'] != 'optional' && !$quoted)
						continue;

					if ($quoted)
						$pos1 += 6;
				}
				else
					$quoted = false;

				$pos2 = strpos($message, $quoted == false ? ']' : '&quot;]', $pos1);
				if ($pos2 === false)
					continue;

				$pos3 = stripos($message, '[/' . substr($message, $pos + 1, $tag_strlen) . ']', $pos2);
				if ($pos3 === false)
					continue;

				$data = array(
					substr($message, $pos2 + ($quoted == false ? 1 : 7), $pos3 - ($pos2 + ($quoted == false ? 1 : 7))),
					substr($message, $pos1, $pos2 - $pos1)
				);

				if (!empty($tag['block_level']) && substr($data[0], 0, 4) == '<br>')
					$data[0] = substr($data[0], 4);

				// Validation for my parking, please!
				if (isset($tag['validate']))
					$tag['validate']($tag, $data, $disabled, $params);

				$code = strtr($tag['content'], array('$1' => $data[0], '$2' => $data[1]));
				$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos3 + 3 + $tag_strlen);
				$pos += strlen($code) - 1 + 2;
			}
			// A closed tag, with no content or value.
			elseif ($tag['type'] == 'closed')
			{
				$pos2 = strpos($message, ']', $pos);
				$message = substr($message, 0, $pos) . "\n" . $tag['content'] . "\n" . substr($message, $pos2 + 1);
				$pos += strlen($tag['content']) - 1 + 2;
			}
			// This one is sorta ugly... :/
			elseif ($tag['type'] == 'unparsed_commas_content')
			{
				$pos2 = strpos($message, ']', $pos1);
				if ($pos2 === false)
					continue;

				$pos3 = stripos($message, '[/' . substr($message, $pos + 1, $tag_strlen) . ']', $pos2);
				if ($pos3 === false)
					continue;

				// We want $1 to be the content, and the rest to be csv.
				$data = explode(',', ',' . substr($message, $pos1, $pos2 - $pos1));
				$data[0] = substr($message, $pos2 + 1, $pos3 - $pos2 - 1);

				if (isset($tag['validate']))
					$tag['validate']($tag, $data, $disabled, $params);

				$code = $tag['content'];
				foreach ($data as $k => $d)
					$code = strtr($code, array('$' . ($k + 1) => trim($d)));
				$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos3 + 3 + $tag_strlen);
				$pos += strlen($code) - 1 + 2;
			}
			// This has parsed content, and a csv value which is unparsed.
			elseif ($tag['type'] == 'unparsed_commas')
			{
				$pos2 = strpos($message, ']', $pos1);
				if ($pos2 === false)
					continue;

				$data = explode(',', substr($message, $pos1, $pos2 - $pos1));

				if (isset($tag['validate']))
					$tag['validate']($tag, $data, $disabled, $params);

				// Fix after, for disabled code mainly.
				foreach ($data as $k => $d)
					$tag['after'] = strtr($tag['after'], array('$' . ($k + 1) => trim($d)));

				$open_tags[] = $tag;

				// Replace them out, $1, $2, $3, $4, etc.
				$code = $tag['before'];
				foreach ($data as $k => $d)
					$code = strtr($code, array('$' . ($k + 1) => trim($d)));
				$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + 1);
				$pos += strlen($code) - 1 + 2;
			}
			// A tag set to a value, parsed or not.
			elseif ($tag['type'] == 'unparsed_equals' || $tag['type'] == 'parsed_equals')
			{
				// The value may be quoted for some tags - check.
				if (isset($tag['quoted']))
				{
					$quoted = substr($message, $pos1, 6) == '&quot;';
					if ($tag['quoted'] != 'optional' && !$quoted)
						continue;

					if ($quoted)
						$pos1 += 6;
				}
				else
					$quoted = false;

				$pos2 = strpos($message, $quoted == false ? ']' : '&quot;]', $pos1);
				if ($pos2 === false)
					continue;

				$data = substr($message, $pos1, $pos2 - $pos1);

				// Validation for my parking, please!
				if (isset($tag['validate']))
					$tag['validate']($tag, $data, $disabled, $params);

				// For parsed content, we must recurse to avoid security problems.
				if ($tag['type'] != 'unparsed_equals')
					$data = self::parse_bbc($data, !empty($tag['parsed_tags_allowed']) ? false : true, '', !empty($tag['parsed_tags_allowed']) ? $tag['parsed_tags_allowed'] : []);

				$tag['after'] = strtr($tag['after'], array('$1' => $data));

				$open_tags[] = $tag;

				$code = strtr($tag['before'], array('$1' => $data));
				$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + ($quoted == false ? 1 : 7));
				$pos += strlen($code) - 1 + 2;
			}

			// If this is block level, eat any breaks after it.
			if (!empty($tag['block_level']) && substr($message, $pos + 1, 4) == '<br>')
				$message = substr($message, 0, $pos + 1) . substr($message, $pos + 5);

			// Are we trimming outside this tag?
			if (!empty($tag['trim']) && $tag['trim'] != 'outside' && preg_match('~(<br>|&nbsp;|\s)*~', substr($message, $pos + 1), $matches) != 0)
				$message = substr($message, 0, $pos + 1) . substr($message, $pos + 1 + strlen($matches[0]));
		}

		// Close any remaining tags.
		while ($tag = array_pop($open_tags))
			$message .= "\n" . $tag['after'] . "\n";

		// Parse the smileys within the parts where it can be done safely.
		if ($smileys === true)
		{
			$message_parts = explode("\n", $message);
			for ($i = 0, $n = count($message_parts); $i < $n; $i += 2)
				self::parse_smileys($message_parts[$i]);

			$message = implode('', $message_parts);
		}

		// No smileys, just get rid of the markers.
		else
			$message = strtr($message, array("\n" => ''));

		if ($message !== '' && $message[0] === ' ')
			$message = '&nbsp;' . substr($message, 1);

		// Cleanup whitespace.
		$message = strtr($message, array('  ' => ' &nbsp;', "\r" => '', "\n" => '<br>', '<br> ' => '<br>&nbsp;', '&#13;' => "\n"));

		// Allow mods access to what parse_bbc created
		call_integration_hook('integrate_post_parsebbc', array(&$message, &$smileys, &$cache_id, &$parse_tags));

		// Cache the output if it took some time...
		if (isset($cache_key, $cache_t) && microtime(true) - $cache_t > 0.05)
			cache_put_data($cache_key, $message, 240);

		// If this was a force parse revert if needed.
		if (!empty($parse_tags))
		{
			if (empty($temp_bbc))
				$bbc_codes = [];
			else
			{
				$bbc_codes = $temp_bbc;
				unset($temp_bbc);
			}
		}

		return $message;
	}

	/**
	 * Parse smileys in the passed message.
	 *
	 * The smiley parsing function which makes pretty faces appear :).
	 * These are specifically not parsed in code tags [url=mailto:Dad@blah.com]
	 * Caches the smileys from the database or array in memory.
	 * Doesn't return anything, but rather modifies message directly.
	 *
	 * @param string $message The message to parse smileys in
	 */
	public static function parse_smileys(string &$message)
	{
		global $modSettings, $txt, $user_info, $context, $smcFunc;
		static $smileyPregSearch = null, $smileyPregReplacements = [];

		// No smiley set at all?!
		if (trim($message) == '')
			return;


		// If smileyPregSearch hasn't been set, do it now.
		if (empty($smileyPregSearch))
		{
			// Load the smileys in reverse order by length so they don't get parsed wrong.
			if (($temp = cache_get_data('parsing_smileys', 480)) == null)
			{
				$result = $smcFunc['db_query']('', '
					SELECT code, filename, description
					FROM {db_prefix}smileys
					ORDER BY LENGTH(code) DESC',
					array(
					)
				);
				$smileysfrom = [];
				$smileysto = [];
				$smileysdescs = [];
				while ($row = $smcFunc['db_fetch_assoc']($result))
				{
					$smileysfrom[] = $row['code'];
					$smileysto[] = $smcFunc['htmlspecialchars']($row['filename']);
					$smileysdescs[] = $row['description'];
				}
				$smcFunc['db_free_result']($result);

				cache_put_data('parsing_smileys', array($smileysfrom, $smileysto, $smileysdescs), 480);
			}
			else
				list ($smileysfrom, $smileysto, $smileysdescs) = $temp;

			// The non-breaking-space is a complex thing...
			$non_breaking_space = '\x{A0}';

			// This smiley regex makes sure it doesn't parse smileys within code tags (so [url=mailto:David@bla.com] doesn't parse the :D smiley)
			$smileyPregReplacements = [];
			$searchParts = [];
			$smileys_path = $smcFunc['htmlspecialchars']($modSettings['smileys_url'] . '/');

			for ($i = 0, $n = count($smileysfrom); $i < $n; $i++)
			{
				$specialChars = $smcFunc['htmlspecialchars']($smileysfrom[$i], ENT_QUOTES);
				$smileyCode = '<img src="' . $smileys_path . $smileysto[$i] . '" alt="' . strtr($specialChars, array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')). '" title="' . strtr($smcFunc['htmlspecialchars']($smileysdescs[$i]), array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')) . '" class="smiley">';

				$smileyPregReplacements[$smileysfrom[$i]] = $smileyCode;

				$searchParts[] = preg_quote($smileysfrom[$i], '~');
				if ($smileysfrom[$i] != $specialChars)
				{
					$smileyPregReplacements[$specialChars] = $smileyCode;
					$searchParts[] = preg_quote($specialChars, '~');
				}
			}

			$smileyPregSearch = '~(?<=[>:\?\.\s' . $non_breaking_space . '[\]()*\\\;]|(?<![a-zA-Z0-9])\(|^)(' . implode('|', $searchParts) . ')(?=[^[:alpha:]0-9]|$)~u';
		}

		// Replace away!
		$message = preg_replace_callback($smileyPregSearch,
			function ($matches) use ($smileyPregReplacements)
			{
				return $smileyPregReplacements[$matches[1]];
			}, $message);
	}

	/**
	 * Microsoft uses their own character set Code Page 1252 (CP1252), which is a
	 * superset of ISO 8859-1, defining several characters between DEC 128 and 159
	 * that are not normally displayable.  This converts the popular ones that
	 * appear from a cut and paste from windows.
	 *
	 * @param string $string The string
	 * @return string The sanitized string
	 */
	public static function sanitizeMSCutPaste(string $string): string
	{
		global $context;

		if (empty($string))
			return $string;

		// UTF-8 occurences of MS special characters
		$findchars_utf8 = array(
			"\xe2\x80\x9a",	// single low-9 quotation mark
			"\xe2\x80\x9e",	// double low-9 quotation mark
			"\xe2\x80\xa6",	// horizontal ellipsis
			"\xe2\x80\x98",	// left single curly quote
			"\xe2\x80\x99",	// right single curly quote
			"\xe2\x80\x9c",	// left double curly quote
			"\xe2\x80\x9d",	// right double curly quote
			"\xe2\x80\x93",	// en dash
			"\xe2\x80\x94",	// em dash
		);

		// safe replacements
		$replacechars = array(
			',',	// &sbquo;
			',,',	// &bdquo;
			'...',	// &hellip;
			"'",	// &lsquo;
			"'",	// &rsquo;
			'"',	// &ldquo;
			'"',	// &rdquo;
			'-',	// &ndash;
			'--',	// &mdash;
		);

		return str_replace($findchars_utf8, $replacechars, $string);
	}
}
