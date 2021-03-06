<?php
/**
 * This file implements general purpose functions.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}
 * Parts of this file are copyright (c)2004-2006 by Daniel HAHLER - {@link http://thequod.de/contact}.
 * Parts of this file are copyright (c)2005-2006 by PROGIDISTRI - {@link http://progidistri.com/}.
 *
 * {@internal License choice
 * - If you have received this file as part of a package, please find the license.txt file in
 *   the same folder or the closest folder above for complete license terms.
 * - If you have received this file individually (e-g: from http://evocms.cvs.sourceforge.net/)
 *   then you must choose one of the following licenses before using the file:
 *   - GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *   - Mozilla Public License 1.1 (MPL) - http://www.opensource.org/licenses/mozilla1.1.php
 * }}
 *
 * {@internal Open Source relicensing agreement:
 * Daniel HAHLER grants Francois PLANQUE the right to license
 * Daniel HAHLER's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 *
 * PROGIDISTRI S.A.S. grants Francois PLANQUE the right to license
 * PROGIDISTRI S.A.S.'s contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package evocore
 *
 * @todo dh> Refactor into smaller chunks/files. We should avoid using a "huge" misc early!
 *       - _debug.funcs.php
 *       - _formatting.funcs.php
 *       - _date.funcs.php
 *       - ?
 *       NOTE: Encapsulation functions into classes would allow using autoloading (http://php.net/autoload) in PHP5..!
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author cafelog (team)
 * @author blueyed: Daniel HAHLER.
 * @author fplanque: Francois PLANQUE.
 * @author jeffbearer: Jeff BEARER.
 * @author sakichan: Nobuo SAKIYAMA.
 * @author vegarg: Vegar BERG GULDAL.
 * @author mbruneau: Marc BRUNEAU / PROGIDISTRI
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * Dependencies
 */
load_funcs('antispam/model/_antispam.funcs.php');

// @todo sam2kb> Move core functions get_admin_skins, get_filenames, cleardir_r, rmdir_r and some other
// to a separate file, and split files_Module from _core_Module
load_funcs('files/model/_file.funcs.php');


/**
 * Call a method for all modules in a row
 */
function modules_call_method( $method_name )
{
	global $modules;

	foreach( $modules as $module )
	{
		$Module = & $GLOBALS[$module.'_Module'];
		$Module->{$method_name}();
	}
}


/**
 * @deprecated kept only for plugin backward compatibility (core is being modified to call getters directly)
 * To be removed, maybe in b2evo v5.
 *
 * @return DataObjectCache
 */
function & get_Cache( $objectName )
{
	global $Plugins;
	global $$objectName;

	if( isset( $$objectName ) )
	{	// Cache already exists:
		return $$objectName;
	}

	$func_name = 'get_'.$objectName;

	if( function_exists($func_name) )
	{
		return $func_name();
	}
	else
	{
		debug_die( 'getCache(): Unknown Cache type get function:'.$func_name.'()' );
	}
}


/**
 * Load functions file
 */
function load_funcs( $funcs_path )
{
	global $inc_path;
	require_once $inc_path.$funcs_path;
}


/**
 * Shutdown function: save HIT and update session!
 *
 * This is registered in _main.inc.php with register_shutdown_function()
 * This is called by PHP at the end of the script.
 *
 * NOTE: before PHP 4.1 nothing can be echoed here any more, but the minimum PHP requirement for b2evo is PHP 4.3
 */
function shutdown()
{
	/**
	 * @var Hit
	 */
	global $Hit;

	/**
	 * @var Session
	 */
	global $Session;

	global $Settings;
	global $Debuglog;

	global $Timer;
	global $shutdown_count_item_views;

	// Try forking a background process and let the parent return as fast as possbile.
	if( is_callable('pcntl_fork') && function_exists('posix_kill') && defined('STDIN') )
	{
		if( $pid = pcntl_fork() )
			return; // Parent

		function shutdown_kill()
		{
			posix_kill(posix_getpid(), SIGHUP);
		}

		if ( ob_get_level() )
		{	// Discard the output buffer and close
			ob_end_clean();
		}

		fclose(STDIN);  // Close all of the standard
		fclose(STDOUT); // file descriptors as we
		fclose(STDERR); // are running as a daemon.

		register_shutdown_function('shutdown_kill');

		if( posix_setsid() < 0 )
			return;

		if( $pid = pcntl_fork() )
			return;     // Parent

		// Now running as a daemon. This process will even survive
		// an apachectl stop.
	}

	$Timer->resume('shutdown');

	// echo '*** SHUTDOWN FUNC KICKING IN ***';

	// fp> do we need special processing if we are in CLI mode?  probably earlier actually
	// if( ! $is_cli )

	// Note: it might be useful at some point to do special processing if the script has been aborted or has timed out
	// connection_aborted()
	// connection_status()

	# $shutdown_count_item_views is obsolete
	assert( empty($shutdown_count_item_views) );

	// Save the current HIT:
	$Hit->log();

	// Update the SESSION:
	$Session->dbsave();

	// Get updates here instead of slowing down normal display of the dashboard
	load_funcs( 'dashboard/model/_dashboard.funcs.php' );
	b2evonet_get_updates();

	// Auto pruning of old HITS, old SESSIONS and potentially MORE analytics data:
	if( $Settings->get( 'auto_prune_stats_mode' ) == 'page' )
	{ // Autopruning is requested
		load_class('sessions/model/_hitlist.class.php', 'Hitlist' );
		Hitlist::dbprune(); // will prune once per day, according to Settings
	}

	// Calling debug_info() here will produce complete data but it will be after </html> hence invalid.
	// Then again, it's for debug only, so it shouldn't matter that much.
	debug_info();

	// Update the SESSION again, at the very end:
	// (e.g. "Debuglogs" may have been removed in debug_info())
	$Session->dbsave();

	$Timer->pause('shutdown');
}


/***** Formatting functions *****/

/**
 * Format a string/content for being output
 *
 * @author fplanque
 * @todo htmlspecialchars() takes a charset argument, which we could provide ("utf-8")
 *       (=> utf8_htmlspecialchars(), but it does not seem to be required really - since only some chars get replaced..?!)
 * @param string raw text
 * @param string format, can be one of the following
 * - raw: do nothing
 * - htmlbody: display in HTML page body: allow full HTML
 * - entityencoded: Special mode for RSS 0.92: allow full HTML but escape it
 * - htmlhead: strips out HTML (mainly for use in Title)
 * - htmlattr: use as an attribute: escapes quotes, strip tags
 * - formvalue: use as a form value: escapes quotes and < > but leaves code alone
 * - text: use as plain-text, e.g. for ascii-mails
 * - xml: use in an XML file: strip HTML tags
 * - xmlattr: use as an attribute: strips tags and escapes quotes
 * @return string formatted text
 */
function format_to_output( $content, $format = 'htmlbody' )
{
	global $Plugins;

	switch( $format )
	{
		case 'raw':
			// do nothing!
			break;

		case 'htmlbody':
			// display in HTML page body: allow full HTML
			$content = convert_chars($content, 'html');
			break;

		case 'urlencoded':
			// Encode string to be passed as part of an URL
			$content = rawurlencode( $content );
			break;

		case 'entityencoded':
			// Special mode for RSS 0.92: apply renders and allow full HTML but escape it
			$content = convert_chars($content, 'html');
			$content = htmlspecialchars( $content, ENT_QUOTES );
			break;

		case 'htmlhead':
			// strips out HTML (mainly for use in Title)
			$content = strip_tags($content);
			$content = convert_chars($content, 'html');
			break;

		case 'htmlattr':
			// use as an attribute: strips tags and escapes quotes
			// TODO: dh> why not just htmlspecialchars?fp> because an attribute can never contain a tag? dh> well, "onclick='return 1<2;'" would get stripped, too. I'm just saying: why mess with it, when we can just use htmlspecialchars.. fp>ok
			$content = strip_tags($content);
			$content = convert_chars($content, 'html');
			$content = str_replace( array('"', "'"), array('&quot;', '&#039;'), $content );
			break;

		case 'htmlspecialchars':
		case 'formvalue':
			// use as a form value: escapes &, quotes and < > but leaves code alone
			$content = htmlspecialchars( $content, ENT_QUOTES );  // Handles &, ", ', < and >
			break;

		case 'xml':
			// use in an XML file: strip HTML tags
			$content = strip_tags($content);
			$content = convert_chars($content, 'xml');
			break;

		case 'xmlattr':
			// use as an attribute: strips tags and escapes quotes
			$content = strip_tags($content);
			$content = convert_chars($content, 'xml');
			$content = str_replace( array('"', "'"), array('&quot;', '&#039;'), $content );
			break;

		case 'text':
			// use as plain-text, e.g. for ascii-mails
			$content = strip_tags( $content );
			$trans_tbl = get_html_translation_table( HTML_ENTITIES );
			$trans_tbl = array_flip( $trans_tbl );
			$content = strtr( $content, $trans_tbl );
			$content = preg_replace( '/[ \t]+/', ' ', $content);
			$content = trim($content);
			break;

		default:
			debug_die( 'Output format ['.$format.'] not supported.' );
	}

	return $content;
}


/*
 * autobrize(-)
 */
function autobrize($content) {
	$content = callback_on_non_matching_blocks( $content, '~<code>.+?</code>~is', 'autobrize_callback' );
	return $content;
}

/**
 * Adds <br>'s to non code blocks
 *
 * @param string $content
 * @return string content with <br>'s added
 */
function autobrize_callback( $content )
{
	$content = preg_replace("/<br>\n/", "\n", $content);
	$content = preg_replace("/<br \/>\n/", "\n", $content);
	$content = preg_replace("/(\015\012)|(\015)|(\012)/", "<br />\n", $content);
	return($content);
}

/*
 * unautobrize(-)
 */
function unautobrize($content)
{
	$content = callback_on_non_matching_blocks( $content, '~<code>.+?</code>~is', 'unautobrize_callback' );
	return $content;
}

/**
 * Removes <br>'s from non code blocks
 *
 * @param string $content
 * @return string content with <br>'s removed
 */
function unautobrize_callback( $content )
{
	$content = preg_replace("/<br>\n/", "\n", $content);   //for PHP versions before 4.0.5
	$content = preg_replace("/<br \/>\n/", "\n", $content);
	return($content);
}

/**
 * Add leading zeroes to a number when necessary.
 *
 * @param string The original number.
 * @param integer How many digits shall the number have?
 * @return string The padded number.
 */
function zeroise( $number, $threshold )
{
	return str_pad( $number, $threshold, '0', STR_PAD_LEFT );
}


/**
 * Crop string to maxlen with "…" (default tail) at the end if needed.
 *
 * If $format is not "raw", we make sure to not cut in the middle of an
 * HTML entity, so that strmaxlen('1&amp;2', 3, NULL, 'formvalue') will not
 * become/stay '1&amp;&hellip;'.
 *
 * @param string
 * @param int Maximum length
 * @param string Tail to use, when string gets cropped. Its length gets
 *               substracted from the total length (with HTML entities
 *               being decoded). Default is "…".
 * @param string Format, see {@link format_to_output()}
 * @param boolean Crop at whitespace, if possible?
 *        (any word split at the end will get its head removed)
 * @return string
 */
function strmaxlen( $str, $maxlen = 50, $tail = NULL, $format = 'raw', $cut_at_whitespace = false  )
{
	if( is_null($tail) )
	{
		$tail = '…';
	}

	$str = rtrim($str);

	if( evo_strlen( $str ) > $maxlen )
	{
		// Replace all HTML entities by a single char. html_entity_decode for example
		// would not handle &hellip;.
		$tail_for_length = preg_replace('~&\w+?;~', '.', $tail);
		$tail_length = evo_strlen( html_entity_decode($tail_for_length) );
		$len = $maxlen-$tail_length;
		if( $len < 1 )
		{ // special case; $tail length is >= $maxlen
			$len = 0;
		}
		$str_cropped = evo_substr( $str, 0, $len );
		if( $format != 'raw' )
		{ // if the format isn't raw we make sure that we do not cut in the middle of an HTML entity
			$maxlen_entity = 7; # "&amp;" is 5, min 3!
			$str_inspect = evo_substr($str_cropped, 1-$maxlen_entity);
			$pos_amp = strpos($str_inspect, '&');
			if( $pos_amp !== false )
			{ // there's an ampersand at the end of the cropped string
				$look_until = $pos_amp;
				$str_cropped_len = evo_strlen($str_cropped);
				if( $str_cropped_len < $maxlen_entity )
				{ // we have to look at least for the length of an entity
					$look_until += $maxlen_entity-$str_cropped_len;
				}
				if( strpos(evo_substr($str, $len, $look_until), ';') !== false )
				{
					$str_cropped = evo_substr( $str, 0, $len-evo_strlen($str_inspect)+$pos_amp);
				}
			}
		}

		if( $cut_at_whitespace )
		{
			$i = evo_strlen($str_cropped);
			while( $i && ($c = evo_substr($str_cropped, $i-1, 1)) && ! ctype_space($c) )
			{
				$i--;
			}
			if( $i )
			{
				$str_cropped = evo_substr($str_cropped, 0, $i);
			}
		}

		$str = format_to_output(rtrim($str_cropped), $format);
		$str .= $tail;

		return $str;
	}
	else
	{
		return format_to_output($str, $format);
	}
}


/**
 * Crop string to maxwords preserving tags.
 *
 * @param string
 * @param int Maximum number words
 * @param mixed array Optional parameters
 * @return string
 */
function strmaxwords( $str, $maxwords = 50, $params = array() )
{
	$params = array_merge( array(
			'continued_link' => '',
			'continued_text' => '…',
			'always_continue' => false,
		), $params );
	$open = false;
	$have_seen_non_whitespace = false;
	$end = evo_strlen( $str );
	for( $i = 0; $i < $end; $i++ )
	{
		switch( $char = $str[$i] )
		{
			case '<' :	// start of a tag
				$open = true;
				break;
			case '>' : // end of a tag
				$open = false;
				break;

			case ctype_space($char):
				if( ! $open )
				{ // it's a word gap
					// Eat any other whitespace.
					while( isset($str[$i+1]) && ctype_space($str[$i+1]) )
					{
						$i++;
					}
					if( isset($str[$i+1]) && $have_seen_non_whitespace )
					{ // only decrement words, if there's a non-space char left.
						--$maxwords;
					}
				}
				break;

			default:
				$have_seen_non_whitespace = true;
				break;
		}
		if( $maxwords < 1 ) break;
	}

	// restrict content to required number of words and balance the tags out
	$str = balance_Tags( evo_substr( $str, 0, $i ) );

	if( $params['always_continue'] || $maxwords == false )
	{ // we want a continued text
		if( $params['continued_link'] )
		{ // we have a url
			$str .= ' <a href="'.$params['continued_link'].'">'.$params['continued_text'].'</a>';
		}
		else
		{ // we don't have a url
			$str .= ' '.$params['continued_text'];
		}
	}
	// remove empty tags
	$str = preg_replace( '~<([\s]+?)[^>]*?></\1>~is', '', $str );

	return $str;
}


/**
 * Convert entities to &#nnnn; unicode references if output is not HTML (eg XML)
 *
 * Preserves < > and quotes.
 *
 * NOTE: this is much lighter in whissip then in b2evo.
 *
 * fplanque: simplified
 * sakichan: pregs instead of loop
 * @param string String (UTF-8)
 */
function convert_chars( $content, $flag = 'html' )
{
	if( $flag == 'html' )
	{ // we can use entities
		// Convert & chars that are not used in an entity
		$content = preg_replace('/&(?![#A-Za-z0-9]{2,20};)/', '&amp;', $content);
	}
	else
	{ // unicode, xml...
		// Convert & chars that are not used in an entity
		$content = preg_replace('/&(?![#A-Za-z0-9]{2,20};)/', '&#38;', $content);
	}

	return( $content );
}


/**
 * Get number of bytes in $string. This works around mbstring.func_overload, if
 * activated for strlen/mb_strlen.
 * @param string
 * @return int
 */
function evo_bytes( $string )
{
	$fo = ini_get('mbstring.func_overload');
	if( $fo && $fo & 2 && function_exists('mb_strlen') )
	{ // overloading of strlen is enabled
		return mb_strlen( $string, 'ASCII' );
	}
	return strlen($string);
}


/**
 * mbstring wrapper for strtolower function
 *
 * fp> TODO: instead of those "when used" ifs, it would make more sense to redefine
 * mb_strtolower beforehand if it doesn"t exist (it would then just be a fallback
 * to the strtolower + a Debuglog->add() )
 *
 * @param string
 * @return string
 */
function evo_strtolower( $string )
{
	return mb_strtolower( $string, 'utf-8' );
}


/**
 * mbstring wrapper for strlen function
 *
 * @param string String (utf8)
 * @return string
 */
function evo_strlen( $string )
{
	return mb_strlen( $string, 'utf-8' );
}


/**
 * mbstring wrapper for substr function
 *
 * @param string String (utf8)
 * @param int start position
 * @param int string length
 * @return string
 */
function evo_substr( $string, $start = 0, $length = '#' )
{
	if( ! $length )
	{ // make mb_substr and substr behave consistently (mb_substr returns string for length=0)
		return '';
	}
	if( $length == '#' )
	{
		$length = evo_strlen($string);
	}

	return mb_substr( $string, $start, $length, 'utf8' );
}


/**
 * Split $text into blocks by using $pattern and call $callback on the non-matching blocks.
 *
 * The non-matching block's text is the first param to $callback and additionally $params gets passed.
 *
 * This gets used to make links clickable or replace smilies.
 *
 * E.g., to replace only in non-HTML tags, call it like:
 * <code>callback_on_non_matching_blocks( $text, '~<[^>]*>~s', 'your_callback' );</code>
 *
 * {@internal This function gets tested in misc.funcs.simpletest.php.}}
 *
 * @param string Text to handle
 * @param string Regular expression pattern that defines blocks to exclude.
 * @param callback Function name or object/method array to use as callback.
 *               Each non-matching block gets passed as first param, additional params may be
 *               passed with $params.
 * @param array Of additional ("static") params to $callback.
 * @return string
 */
function callback_on_non_matching_blocks( $text, $pattern, $callback, $params = array() )
{
	if( preg_match_all( $pattern, $text, $matches, PREG_OFFSET_CAPTURE | PREG_PATTERN_ORDER ) )
	{ // $pattern matches, call the callback method on each non-matching block
		$pos = 0;
		$new_r = '';

		foreach( $matches[0] as $l_matching )
		{
			$pos_match = $l_matching[1];
			$non_match = substr( $text, $pos, ($pos_match - $pos) );

			// Callback:
			$callback_params = $params;
			array_unshift( $callback_params, $non_match );
			$new_r .= call_user_func_array( $callback, $callback_params );

			$new_r .= $l_matching[0];
			$pos += strlen($non_match)+strlen($l_matching[0]);
		}

		// Callback:
		$callback_params = $params;
		array_unshift( $callback_params, substr( $text, $pos ) );
		#pre_dump( $matches, $callback_params );
		$new_r .= call_user_func_array( $callback, $callback_params );

		return $new_r;
	}

	$callback_params = $params;
	array_unshift( $callback_params, $text );
	return call_user_func_array( $callback, $callback_params );
}


/**
 * Make links clickable in a given text.
 *
 * It replaces only text which is not between <a> tags already.
 *
 * @todo dh> this should not replace links in tags! currently fails for something
 *           like '<img src=" http://example.com/" />' (not usual though!)
 * fp> I am trying to address this by not replacing anything inside tags
 * fp> This should be replaced by a clean state machine (one single variable for current state)
 *
 * {@internal This function gets tested in misc.funcs.simpletest.php.}}
 *
 * @return string
 */
function make_clickable( $text, $moredelim = '&amp;', $callback = 'make_clickable_callback' )
{
	$r = '';
	$inside_tag = false;
	$in_a_tag = false;
	$in_tag_quote = false;
	$from_pos = 0;
	$i = 0;
	$n = strlen($text);

	// Not using callback_on_non_matching_blocks(), because it requires
	// wellformed HTML and the implementation below should be
	// faster and less memory intensive (tested for some example content)
	while( $i < $n )
	{	// Go through each char in string... (we will fast forward from tag to tag)
		if( $inside_tag )
		{	// State: We're currently inside some tag:
			switch( $text[$i] )
			{
				case '>':
					if( $in_tag_quote )
					{ // This is in a quoted string so it doesn't really matter...
						break;
					}
					// end of tag:
					$inside_tag = false;
					$r .= substr($text, $from_pos, $i-$from_pos+1);
					$from_pos = $i+1;
					// $r .= '}';
					break;

				case '"':
				case '\'':
					// This is the beginning or the end of a quoted string:
					if( ! $in_tag_quote )
					{
						$in_tag_quote = $text[$i];
					}
					elseif( $in_tag_quote == $text[$i] )
					{
						$in_tag_quote = false;
					}
					break;
			}
		}
		elseif( $in_a_tag )
		{	// In a link but no longer inside <a>...</a> tag or any other embedded tag like <strong> or whatever
			switch( $text[$i] )
			{
				case '<':
					if( strtolower(substr($text, $i+1, 3)) == '/a>' )
					{	// Ok, this is the end tag of the link:
						// $r .= substr($text, $from_pos, $i-$from_pos+4);
						// $from_pos = $i+4;
						$i += 4;
						// pre_dump( 'END A TAG: '.substr($text, $from_pos, $i-$from_pos) );
						$r .= substr($text, $from_pos, $i-$from_pos);
						$from_pos = $i;
						$in_a_tag = false;
						$in_tag_quote = false;
					}
					break;
			}
		}
		else
		{ // State: we're not currently in any tag:
			// Find next tag opening:
			$i = strpos($text, '<', $i);
			if( $i === false )
			{ // No more opening tags:
				break;
			}

			$inside_tag = true;
			$in_tag_quote = false;
			// s$r .= '{'.$text[$i+1];

			if( ($text[$i+1] == 'a' || $text[$i+1] == 'A') && ctype_space($text[$i+2]) )
			{ // opening "A" tag
				$in_a_tag = true;
			}

			// Make the text before the opening < clickable:
			if( is_array($callback) )
			{
				$r .= $callback[0]->$callback[1]( substr($text, $from_pos, $i-$from_pos), $moredelim );
			}
			else
			{
				$r .= $callback( substr($text, $from_pos, $i-$from_pos), $moredelim );
			}
			$from_pos = $i;

			// $i += 2;
		}

		$i++;
	}

	// the remaining part:
	if( $in_a_tag )
	{ // may happen for invalid html:
		$r .= substr($text, $from_pos);
	}
	else
	{	// Make remplacements in the remaining part:
		if( is_array($callback) )
		{
			$r .= $callback[0]->$callback[1]( substr($text, $from_pos), $moredelim );
		}
		else
		{
			$r .= $callback( substr($text, $from_pos), $moredelim );
		}
	}

	return $r;
}


/**
 * Callback function for {@link make_clickable()}.
 *
 * original function: phpBB, extended here for AIM & ICQ
 * fplanque restricted :// to http:// and mailto://
 * Fixed to not include trailing dot and comma.
 *
 * fp> I'm thinking of moving this into the autolinks plugin (only place where it's used)
 *     and break it up into something more systematic.
 *
 * @return string The clickable text.
 */
function make_clickable_callback( $text, $moredelim = '&amp;' )
{
	$pattern_domain = '([a-z0-9\-]+\.[a-z0-9\-.\~]+)'; // a domain name (not very strict)
	$text = preg_replace(
		/* Tblue> I removed the double quotes from the first RegExp because
				  it made URLs in tag attributes clickable.
				  See http://forums.b2evolution.net/viewtopic.php?p=92073 */
		array( '#(^|[\s>])(https?|mailto)://([^<>{}\s]+[^.,<>{}\s])#i',
			'#(^|[\s>])aim:([^,<\s]+)#i',
			'#(^|[\s>])icq:(\d+)#i',
			'#(^|[\s>])www\.'.$pattern_domain.'((?:/[^<\s]*)?[^.,\s])#i',
			'#(^|[\s>])([a-z0-9\-_.]+?)@'.$pattern_domain.'([^.,<\s]+)#i', ),
		array( '$1<a href="$2://$3">$2://$3</a>',
			'$1<a href="aim:goim?screenname=$2$3'.$moredelim.'message='.rawurlencode(T_('Hello')).'">$2$3</a>',
			'$1<a href="http://wwp.icq.com/scripts/search.dll?to=$2">$2</a>',
			'$1<a href="http://www.$2$3$4">www.$2$3$4</a>',
			'$1<a href="mailto:$2@$3$4">$2@$3$4</a>', ),
		$text );

	return $text;
}


/***** // Formatting functions *****/

/**
 * Convert timestamp to MySQL/ISO format.
 *
 * @param integer UNIX timestamp
 * @return string Date formatted as "Y-m-d H:i:s"
 */
function date2mysql( $ts )
{
	return date( 'Y-m-d H:i:s', $ts );
}

/**
 * Convert a MYSQL date to a UNIX timestamp.
 *
 * @param string Date formatted as "Y-m-d H:i:s"
 * @return integer UNIX timestamp
 */
function mysql2timestamp( $m )
{
	return mktime(substr($m,11,2),substr($m,14,2),substr($m,17,2),substr($m,5,2),substr($m,8,2),substr($m,0,4));
}

/**
 * Convert a MYSQL date -- WITHOUT the time -- to a UNIX timestamp
 */
function mysql2datestamp( $m )
{
	return mktime( 0, 0, 0, substr($m,5,2), substr($m,8,2), substr($m,0,4) );
}

/**
 * Format a MYSQL date to current locale date format.
 *
 * @param string MYSQL date YYYY-MM-DD HH:MM:SS
 */
function mysql2localedate( $mysqlstring )
{
	return mysql2date( locale_datefmt(), $mysqlstring );
}

function mysql2localetime( $mysqlstring )
{
	return mysql2date( locale_timefmt(), $mysqlstring );
}

function mysql2localedatetime( $mysqlstring )
{
	return mysql2date( locale_datefmt().' '.locale_timefmt(), $mysqlstring );
}

function mysql2localedatetime_spans( $mysqlstring, $datefmt = NULL, $timefmt = NULL )
{
	if( is_null( $datefmt ) )
	{
		$datefmt = locale_datefmt();
	}
	if( is_null( $timefmt ) )
	{
		$timefmt = locale_timefmt();
	}

	return '<span class="date">'
					.mysql2date( $datefmt, $mysqlstring )
					.'</span> <span class="time">'
					.mysql2date( $timefmt, $mysqlstring )
					.'</span>';
}


/**
 * Format a MYSQL date.
 *
 * @param string enhanced format string
 * @param string MYSQL date YYYY-MM-DD HH:MM:SS
 * @param boolean true to use GM time
 */
function mysql2date( $dateformatstring, $mysqlstring, $useGM = false )
{
	$m = $mysqlstring;
	if( empty($m) || ($m == '0000-00-00 00:00:00' ) )
		return false;

	// Get a timestamp:
	$unixtimestamp = mysql2timestamp( $m );

	return date_i18n( $dateformatstring, $unixtimestamp, $useGM );
}


/**
 * Date internationalization: same as date() formatting but with i18n support.
 *
 * @todo dh> support for MySQL date format instead of $unixtimestamp? This would simplify callees, where currently mktime() is used.
 * @param string enhanced format string
 * @param integer UNIX timestamp
 * @param boolean true to use GM time
 */
function date_i18n( $dateformatstring, $unixtimestamp, $useGM = false )
{
	global $month, $month_abbrev, $weekday, $weekday_abbrev, $weekday_letter;
	global $localtimenow, $time_difference;

	if( $dateformatstring == 'isoZ' )
	{ // full ISO 8601 format
		$dateformatstring = 'Y-m-d\TH:i:s\Z';
	}

	if( $useGM )
	{ // We want a Greenwich Meridian time:
		// TODO: dh> what's the point of the substraction? UNIX timestamp should contain no time_difference in the first place?! Otherwise it should be substracted for !$useGM, too.
		// TODO: dh> Why does $useGM do not get the special symbols handling?
		$r = gmdate($dateformatstring, ($unixtimestamp - $time_difference));
	}
	else
	{ // We want default timezone time:

		/*
		Special symbols:
			'b': wether it's today (1) or not (0)
			'l': weekday
			'D': weekday abbrev
			'e': weekday letter
			'F': month
			'M': month abbrev
		*/

		#echo $dateformatstring, '<br />';

		// protect special symbols, that date() would need proper locale set for
		$protected_dateformatstring = preg_replace( '/(?<!\\\)([blDeFM])/', '@@@\\\$1@@@', $dateformatstring );

		#echo $protected_dateformatstring, '<br />';

		$r = date( $protected_dateformatstring, $unixtimestamp );

		if( $protected_dateformatstring != $dateformatstring )
		{ // we had special symbols, replace them

			$istoday = ( date('Ymd',$unixtimestamp) == date('Ymd',$localtimenow) ) ? '1' : '0';
			$datemonth = date('m', $unixtimestamp);
			$dateweekday = date('w', $unixtimestamp);

			// replace special symbols
			$r = str_replace( array(
						'@@@b@@@',
						'@@@l@@@',
						'@@@D@@@',
						'@@@e@@@',
						'@@@F@@@',
						'@@@M@@@',
						),
					array( $istoday,
						trim(T_($weekday[$dateweekday])),
						trim(T_($weekday_abbrev[$dateweekday])),
						trim(T_($weekday_letter[$dateweekday])),
						trim(T_($month[$datemonth])),
						trim(T_($month_abbrev[$datemonth])) ),
					$r );
		}
	}

	return $r;
}


/**
 * Add given # of days to a timestamp
 *
 * @param integer timestamp
 * @param integer days
 * @return integer timestamp
 */
function date_add_days( $timestamp, $days )
{
	return mktime( date('H',$timestamp), date('m',$timestamp), date('s',$timestamp),
								date('m',$timestamp), date('d',$timestamp)+$days, date('Y',$timestamp)  );
}

/**
 * Format dates into a string in a way similar to sprintf()
 */
function date_sprintf( $string, $timestamp )
{
	global $date_sprintf_timestamp;
	$date_sprintf_timestamp = $timestamp;

	return preg_replace_callback( '/%\{(.*?)\}/', 'date_sprintf_callback', $string );
}

function date_sprintf_callback( $matches )
{
	global $date_sprintf_timestamp;

	return date_i18n( $matches[1], $date_sprintf_timestamp );
}



/**
 *
 * @param integer year
 * @param integer month (0-53)
 * @param integer 0 for sunday, 1 for monday
 */
function get_start_date_for_week( $year, $week, $startofweek )
{
	$new_years_date = mktime( 0, 0, 0, 1, 1, $year );
	$weekday = date('w', $new_years_date);
	// echo '<br> 1st day is a: '.$weekday;

	// How many days until start of week:
	$days_to_new_week = (7 - $weekday + $startofweek) % 7;
	// echo '<br> days to new week: '.$days_to_new_week;

	// We now add the required number of days to find the 1st sunday/monday in the year:
	//$first_week_start_date = $new_years_date + $days_to_new_week * 86400;
	//echo '<br> 1st week starts on '.date( 'Y-m-d H:i:s', $first_week_start_date );

	// We add the number of requested weeks:
	// This will fail when passing to Daylight Savings Time: $date = $first_week_start_date + (($week-1) * 604800);
	$date = mktime( 0, 0, 0, 1, $days_to_new_week + 1 + ($week-1) * 7, $year );
	// echo '<br> week '.$week.' starts on '.date( 'Y-m-d H:i:s', $date );

	return $date;
}



/**
 * Get start and end day of a week, based on day f the week and start-of-week
 *
 * Used by Calendar
 *
 * @param date
 * @param integer 0 for Sunday, 1 for Monday
 */
function get_weekstartend( $date, $startOfWeek )
{
	while( date('w', $date) <> $startOfWeek )
	{
		// echo '<br />'.date('Y-m-d H:i:s w', $date).' - '.$startOfWeek;
		// mktime is needed so calculations work for DST enabling. Example: March 30th 2008, start of week 0 sunday
		$date = mktime( 0, 0, 0, date('m',$date), date('d',$date)-1, date('Y',$date) );
	}
	// echo '<br />'.date('Y-m-d H:i:s w', $date).' = '.$startOfWeek;
	$week['start'] = $date;
	$week['end']   = $date + 604800; // 7 days

	// pre_dump( 'weekstartend: ', date( 'Y-m-d', $week['start'] ), date( 'Y-m-d', $week['end'] ) );

	return( $week );
}


/**
 * Get datetime rounded to lower minute. This is meant to remove seconds and
 * leverage MySQL's query cache by having SELECT queries remain identical for 60 seconds instead of just 1.
 *
 * @param integer UNIX timestamp
 * @param string Format (defaults to "Y-m-d H:i"). Use "U" for UNIX timestamp.
 */
function remove_seconds($timestamp, $format = 'Y-m-d H:i')
{
	return date($format, floor($timestamp/60)*60);
}


/**
 * Validate variable
 *
 * @param string param name
 * @param string validator function name
 * @param boolean true if variable value can't be empty
 * @param custom error message
 * @return boolean true if OK
 */
function param_validate( $variable, $validator, $required = false, $custom_msg = NULL )
{
	/* Tblue> Note: is_callable() does not check whether a function is
	 *        disabled (http://www.php.net/manual/en/function.is-callable.php#79151).
	 */
	if( ! is_callable( $validator ) )
	{
		debug_die( 'Validator function '.$validator.'() is not callable!' );
	}

	if( ! isset( $GLOBALS[$variable] ) )
	{	// Variable not set, we cannot handle this using the validator function...
		if( $required )
		{	// Add error:
			param_check_not_empty( $variable );
			return false;
		}

		return true;
	}

	if( $GLOBALS[$variable] === '' && ! $required )
	{	// Variable is empty or not set. That's fine since it isn't required:
		return true;
	}

	$msg = $validator( $GLOBALS[$variable] );

	if( !empty( $msg ) )
	{
		if( !empty( $custom_msg ) )
		{
			$msg = $custom_msg;
		}

		param_error( $variable, $msg );
		return false;
	}

	return true;
}


/**
 * Checks if the param is a decimal number
 *
 * @param string decimal to check
 * @return boolean true if OK
 */
function is_decimal( $decimal )
{
	return preg_match( '#^[0-9]*(\.[0-9]+)?$#', $decimal );
}


/**
 * Checks if the param is an integer (no float, e.g. 3.14).
 *
 * @param string number to check
 * @return boolean true if OK
 */
function is_number( $number )
{
	return preg_match( '#^[0-9]+$#', $number );
}


/**
 * Check that email address looks valid.
 *
 * @param string email address to check
 * @param string Format to use ('simple', 'rfc')
 *    'simple'/'single':
 *      Single email address.
 *    'rfc':
 *      Full email address, may include name (RFC2822)
 *      - example@example.org
 *      - Me <example@example.org>
 *      - "Me" <example@example.org>
 * @param boolean Return the match or boolean
 *
 * @return bool|array Either true/false or the match (see {@link $return_match})
 */
function is_email( $email, $format = 'simple', $return_match = false )
{
	#$chars = "/^([a-z0-9_]|\\-|\\.)+@(([a-z0-9_]|\\-)+\\.)+[a-z]{2,4}\$/i";

	switch( $format )
	{
		case 'rfc':
		case 'rfc2822':
			/**
			 * Regexp pattern converted from: http://www.regexlib.com/REDetails.aspx?regexp_id=711
			 * Extended to allow escaped quotes.
			 */
			$pattern_email = '/^
				(
					(?>[a-zA-Z\d!\#$%&\'*+\-\/=?^_`{|}~]+\x20*
						|"( \\\" | (?=[\x01-\x7f])[^"\\\] | \\[\x01-\x7f] )*"\x20*)* # Name
					(<)
				)?
				(
					(?!\.)(?>\.?[a-zA-Z\d!\#$%&\'*+\-\/=?^_`{|}~]+)+
					|"( \\\" | (?=[\x01-\x7f])[^"\\\] | \\[\x01-\x7f] )* " # quoted mailbox name
				)
				@
				(
					((?!-)[a-zA-Z\d\-]+(?<!-)\.)+[a-zA-Z]{2,}
					|
					\[(
						( (?(?<!\[)\.)(25[0-5] | 2[0-4]\d | [01]?\d?\d) ){4}
						|
						[a-zA-Z\d\-]*[a-zA-Z\d]:( (?=[\x01-\x7f])[^\\\[\]] | \\[\x01-\x7f] )+
					)\]
				)
				(?(3)>) # match ">" if it was there
				$/x';
			break;

		case 'simple':
		default:
			// '/^\S+@[^\.\s]\S*\.[a-z]{2,}$/i'
			$pattern_email = '~^(([_a-z0-9-]+)(\.[_a-z0-9-]+)*@([a-z0-9][a-z0-9-]*)(\.[a-z0-9-]+)*(\.[a-z]{2,}))$~i';
			break;
	}

	if( strpos( $email, '@' ) !== false && strpos( $email, '.' ) !== false )
	{
		if( $return_match )
		{
			preg_match( $pattern_email, $email, $match );
			return $match;
		}
		else
		{
			return (bool)preg_match( $pattern_email, $email );
		}
	}
	else
	{
		return $return_match ? array() : false;
	}
}


/**
 * Checks if the phone number is valid
 *
 * @param string phone number to check
 * @return boolean true if OK
 */
function is_phone( $phone )
{
	return preg_match( '|^\+?[\-*#/(). 0-9]+$|', $phone );
}


/**
 * Checks if the url is valid
 *
 * @param string url to check
 * @return boolean true if OK
 */
function is_url( $url )
{
	if( validate_url( $url, 'posting', false ) )
	{
		return false;
	}

	return true;
}


/**
 * Checks if the word is valid
 *
 * @param string word to check
 * @return boolean true if OK
 */
function is_word( $word )
{
	return preg_match( '#^[A-Za-z]+$#', $word );
}


/**
 * Check if the login is valid
 *
 * @param string login
 * @return boolean true if OK
 */
function user_exists( $login )
{
	global $DB;

	$SQL = new SQL();
	$SQL->SELECT( 'COUNT(*)' );
	$SQL->FROM( 'T_users' );
	$SQL->WHERE( 'user_login = "'.$DB->escape($login).'"' );

	$var = $DB->get_var( $SQL->get() );
	return $var > 0 ? true : false; // PHP4 compatibility
}


/**
 * Are we running on a Windows server?
 */
function is_windows()
{
	return ( strtoupper(substr(PHP_OS,0,3)) == 'WIN' );
}


/**
 * Get all "a" tags from the given content
 *  
 * @param string content
 * @return array all <a../a> part from the given content
 */
function get_atags( $content )
{
	$tag = 'a';
	$regexp = '{<'.$tag.'[^>]*>(.*?)</'.$tag.'>}';

	preg_match_all( $regexp, $content, $result );
	return $result[0];
}


/**
 * Get all "img" tags from the given content
 *  
 * @param string content
 * @return array all <img../img> part from the given content
 */
function get_imgtags( $content )
{
	$tag = 'img';
	$regexp = '{<'.$tag.'[^>]*[ (</'.$tag.'>) | (/>) ]}';

	preg_match_all( $regexp, $content, $result );
	return $result[0];
}


/**
 * Get all urls from the given content
 *  
 * @param string content
 * @return array all url from content
 */
function get_urls( $content )
{
	$regexp = '^(?#Protocol)(?:(?:ht|f)tp(?:s?)\:\/\/|~\/|\/)?(?#Username:Password)(?:\w+:\w+@)?(?#Subdomains)(?:(?:[-\w]+\.)+(?#TopLevel Domains)(?:com|org|net|gov|mil|biz|info|mobi|name|aero|jobs|museum|travel|[a-z]{2,4}))(?#Port)(?::[\d]{1,5})?(?#Directories)(?:(?:(?:\/(?:[-\w~!$+|.,;=]|%[a-f\d]{2})+)+|\/)+|\?|#)?(?#Query)(?:(?:\?(?:[-\w~!$+|.,;*:]|%[a-f\d{2}])+=?(?:[-\w~!$+|.,;*:=]|%[a-f\d]{2})*)(?:&(?:[-\w~!$+|.,;*:]|%[a-f\d{2}])+=?(?:[-\w~!$+|.,;*:=]|%[a-f\d]{2})*)*)*(?#Anchor)(?:#(?:[-\w~!$+|.,;*:=]|%[a-f\d]{2})*)?^';

	preg_match_all( $regexp, $content, $result );
	return $result[0];
}


function xmlrpc_getposttitle($content)
{
	global $post_default_title;
	if (preg_match('/<title>(.+?)<\/title>/is', $content, $matchtitle))
	{
		$post_title = $matchtitle[1];
	}
	else
	{
		$post_title = $post_default_title;
	}
	return($post_title);
}


/**
 * Also used by post by mail
 *
 * @deprecated by xmlrpc_getpostcategories()
 */
function xmlrpc_getpostcategory($content)
{
	if (preg_match('/<category>([0-9]+?)<\/category>/is', $content, $matchcat))
	{
		return $matchcat[1];
	}

	return false;
}


/**
 * Extract categories out of "<category>" tag from $content.
 *
 * NOTE: w.bloggar sends something like "<category>00000013,00000001,00000004,</category>" to
 *       blogger.newPost.
 *
 * @return false|array
 */
function xmlrpc_getpostcategories($content)
{
	$cats = array();

	if( preg_match('~<category>(\d+\s*(,\s*\d*)*)</category>~i', $content, $match) )
	{
		$cats = preg_split('~\s*,\s*~', $match[1], -1, PREG_SPLIT_NO_EMPTY);
		foreach( $cats as $k => $v )
		{
			$cats[$k] = (int)$v;
		}
	}

	return $cats;
}


/*
 * xmlrpc_removepostdata(-)
 */
function xmlrpc_removepostdata($content)
{
	$content = preg_replace('/<title>(.*?)<\/title>/si', '', $content);
	$content = preg_replace('/<category>(.*?)<\/category>/si', '', $content);
	$content = trim($content);
	return($content);
}


/**
 * Echo the XML-RPC call Result and optionally log into file
 *
 * @param object XMLRPC response object
 * @param boolean true to echo
 * @param mixed File resource or == '' for no file logging.
 */
function xmlrpc_displayresult( $result, $display = true, $log = '' )
{
	if( ! $result )
	{ // We got no response:
		if( $display ) echo T_('No response!')."<br />\n";
		return false;
	}

	if( $result->faultCode() )
	{ // We got a remote error:
		if( $display ) echo T_('Remote error'), ': ', $result->faultString(), ' (', $result->faultCode(), ")<br />\n";
		debug_fwrite($log, $result->faultCode().' -- '.$result->faultString());
		return false;
	}

	// We'll display the response:
	$val = $result->value();
	$value = xmlrpc_decode_recurse($result->value());

	if( is_array($value) )
	{
		$out = '';
		foreach($value as $l_value)
		{
			$out .= ' ['.$l_value.'] ';
		}
	}
	else
	{
		$out = $value;
	}

	debug_fwrite($log, $out);

	if( $display ) echo T_('Response').': '.$out."<br />\n";

	return $value;
}


/**
 * Log the XML-RPC call Result into LOG object
 *
 * @param object XMLRPC response object
 * @param Log object to add messages to
 * @return boolean true = success, false = error
 */
function xmlrpc_logresult( $result, & $message_Log, $log_payload = true )
{
	if( ! $result )
	{ // We got no response:
		$message_Log->add( T_('No response!'), 'error' );
		return false;
	}

	if( $result->faultCode() )
	{ // We got a remote error:
		$message_Log->add( T_('Remote error').': '.$result->faultString().' ('.$result->faultCode().')', 'error' );
		return false;
	}

	// We got a response:
	$val = $result->value();
	$value = xmlrpc_decode_recurse($result->value());

	if( is_array($value) )
	{
		$out = '';
		foreach($value as $l_value)
		{
			$out .= ' ['.$l_value.'] ';
		}
	}
	else
	{
		$out = $value;
	}

	if( $log_payload )
	{
		$message_Log->add( T_('Response').': '.$out, 'success' );
	}

	return true;
}



function debug_fopen($filename, $mode) {
	global $debug;
	if ($debug == 1 && ( !empty($filename) ) )
	{
		$fp = fopen($filename, $mode);
		return $fp;
	} else {
		return false;
	}
}

function debug_fwrite($fp, $string)
{
	global $debug;
	if( $debug && $fp )
	{
		fwrite($fp, $string);
	}
}

function debug_fclose($fp)
{
	global $debug;
	if( $debug && $fp )
	{
		fclose($fp);
	}
}



/**
 * Wrap pre tag around {@link var_dump()} for better debugging.
 *
 * @param $var__var__var__var__,... mixed variable(s) to dump
 * @return true
 */
function pre_dump( $var__var__var__var__ )
{
	global $is_cli;

	#echo 'pre_dump(): '.debug_get_backtrace(); // see where a pre_dump() comes from

	$func_num_args = func_num_args();
	$count = 0;

	if( ! empty($is_cli) )
	{ // CLI, no encoding of special chars:
		$count = 0;
		foreach( func_get_args() as $lvar )
		{
			var_dump($lvar);

			$count++;
			if( $count < $func_num_args )
			{ // Put newline between arguments
				echo "\n";
			}
		}
	}
	elseif( function_exists('xdebug_var_dump') )
	{ // xdebug already does fancy displaying:

		// no limits:
		$old_var_display_max_children = ini_set('xdebug.var_display_max_children', -1); // default: 128
		$old_var_display_max_data = ini_set('xdebug.var_display_max_data', -1); // max string length; default: 512
		$old_var_display_max_depth = ini_set('xdebug.var_display_max_depth', -1); // default: 3

		echo "\n<div style=\"padding:1ex;border:1px solid #00f;text-align:left\">\n";
		foreach( func_get_args() as $lvar )
		{
			xdebug_var_dump($lvar);

			$count++;
			if( $count < $func_num_args )
			{ // Put HR between arguments
				echo "<hr />\n";
			}
		}
		echo '</div>';

		// restore xdebug settings:
		ini_set('xdebug.var_display_max_children', $old_var_display_max_children);
		ini_set('xdebug.var_display_max_data', $old_var_display_max_data);
		ini_set('xdebug.var_display_max_depth', $old_var_display_max_depth);
	}
	else
	{
		$orig_html_errors = ini_set('html_errors', 0); // e.g. xdebug would use fancy html, if this is on; we catch (and use) xdebug explicitly above, but just in case

		echo "\n<pre style=\"padding:1ex;border:1px solid #00f;text-align:left;\">\n";
		foreach( func_get_args() as $lvar )
		{
			ob_start();
			var_dump($lvar); // includes "\n"; do not use var_export() because it does not detect recursion by design
			$buffer = ob_get_contents();
			ob_end_clean();
			echo htmlspecialchars($buffer);

			$count++;
			if( $count < $func_num_args )
			{ // Put HR between arguments
				echo "<hr />\n";
			}
		}
		echo "</pre>\n";
		ini_set('html_errors', $orig_html_errors);
	}
	flush();
	return true;
}


/**
 * Get a function trace from {@link debug_backtrace()} as html table.
 *
 * Adopted from {@link http://us2.php.net/manual/de/function.debug-backtrace.php#47644}.
 *
 * @todo dh> Add support for $is_cli = true (e.g. in case of MySQL error)
 *
 * @param integer|NULL Get the last x entries from the stack (after $ignore_from is applied). Anything non-numeric means "all".
 * @param array After a key/value pair matches a stack entry, this and the rest is ignored.
 *        For example, array('class' => 'DB') would exclude everything after the stack
 *        "enters" class DB and everything that got called afterwards.
 *        array('function' => 'debug_get_backtrace') would ignore the call to this function
 *        itself.
 *        You can also give an array of arrays which means that every condition in
 *        one of the given array must match.
 * @param integer Number of stack entries to include, after $ignore_from matches.
 * @return string HTML table
 */
function debug_get_backtrace( $limit_to_last = NULL, $ignore_from = array(), $offset_ignore_from = 0 )
{
	if( ! function_exists( 'debug_backtrace' ) ) // PHP 4.3.0
	{
		return 'Function debug_backtrace() is not available!';
	}

	$r = '';

	$backtrace = debug_backtrace();
	$count_ignored = 0; // remember how many have been ignored
	$limited = false;   // remember if we have limited to $limit_to_last

	if( $ignore_from )
	{	// we want to ignore from a certain point
		$trace_length = 0;
		$break_because_of_offset = false;

		for( $i = count($backtrace); $i > 0; $i-- )
		{	// Search the backtrace from behind (first call).
			$l_stack = & $backtrace[$i-1];

			if( $break_because_of_offset && $offset_ignore_from < 1 )
			{ // we've respected the offset, but need to break now
				break; // ignore from here
			}

			foreach( $ignore_from as $l_ignore_key => $l_ignore_value )
			{	// Check if we want to ignore from here
				if( is_array($l_ignore_value) )
				{	// It's an array - all must match
					foreach( $l_ignore_value as $l_ignore_mult_key => $l_ignore_mult_val )
					{
						if( !isset($l_stack[$l_ignore_mult_key]) /* not set with this stack entry */
							|| strcasecmp($l_stack[$l_ignore_mult_key], $l_ignore_mult_val) /* not this value (case-insensitive) */ )
						{
							continue 2; // next ignore setting, because not all match.
						}
					}
					if( $offset_ignore_from-- > 0 )
					{
						$break_because_of_offset = true;
						break;
					}
					break 2; // ignore from here
				}
				elseif( isset($l_stack[$l_ignore_key])
					&& !strcasecmp($l_stack[$l_ignore_key], $l_ignore_value) /* is equal case-insensitive */ )
				{
					if( $offset_ignore_from-- > 0 )
					{
						$break_because_of_offset = true;
						break;
					}
					break 2; // ignore from here
				}
			}
			$trace_length++;
		}

		$count_ignored = count($backtrace) - $trace_length;

		$backtrace = array_slice( $backtrace, 0-$trace_length ); // cut off ignored ones
	}

	$count_backtrace = count($backtrace);
	if( is_numeric($limit_to_last) && $limit_to_last < $count_backtrace )
	{	// we want to limit to a maximum number
		$limited = true;
		$backtrace = array_slice( $backtrace, 0, $limit_to_last );
		$count_backtrace = $limit_to_last;
	}

	$r .= '<div style="padding:1ex; margin-bottom:1ex; text-align:left; color:#000; background-color:#ddf">
					<h3>Backtrace:</h3>'."\n";
	if( $count_backtrace )
	{
		$r .= '<ol style="font-family:monospace;">';

		$i = 0;
		foreach( $backtrace as $l_trace )
		{
			if( ++$i == $count_backtrace )
			{
				$r .= '<li style="padding:0.5ex 0;">';
			}
			else
			{
				$r .= '<li style="padding:0.5ex 0; border-bottom:1px solid #77d;">';
			}
			$args = array();
			if( isset($l_trace['args']) && is_array( $l_trace['args'] ) )
			{	// Prepare args:
				foreach( $l_trace['args'] as $l_arg )
				{
					$l_arg_type = gettype($l_arg);
					switch( $l_arg_type )
					{
						case 'integer':
						case 'double':
							$args[] = $l_arg;
							break;
						case 'string':
							$args[] = '"'.strmaxlen(str_replace("\n", '\n', $l_arg), 255, NULL, 'htmlspecialchars').'"';
							break;
						case 'array':
							$args[] = 'Array('.count($l_arg).')';
							break;
						case 'object':
							$args[] = 'Object('.get_class($l_arg).')';
							break;
						case 'resource':
							$args[] = htmlspecialchars((string)$l_arg);
							break;
						case 'boolean':
							$args[] = $l_arg ? 'true' : 'false';
							break;
						default:
							$args[] = $l_arg_type;
					}
				}
			}

			$call = "<strong>\n";
			if( isset($l_trace['class']) )
			{
				$call .= htmlspecialchars($l_trace['class']);
			}
			if( isset($l_trace['type']) )
			{
				$call .= htmlspecialchars($l_trace['type']);
			}
			$call .= htmlspecialchars($l_trace['function'])."( </strong>\n";
			if( $args )
			{
				$call .= ' '.implode( ', ', $args ).' ';
			}
			$call .='<strong>)</strong>';

			$r .= $call."<br />\n";

			$r .= '<strong>';
			if( isset($l_trace['file']) )
			{
				$r .= 'File: </strong> '.htmlspecialchars($l_trace['file']);
			}
			else
			{
				$r .= '[runtime created function]</strong>';
			}
			if( isset($l_trace['line']) )
			{
				$r .= ' on line '.$l_trace['line'];
			}

			$r .= "</li>\n";
		}
		$r .= '</ol>';
	}
	else
	{
		$r .= '<p>No backtrace available.</p>';
	}

	// Extra notes, might be to much, but explains why we stopped at some point. Feel free to comment it out or remove it.
	$notes = array();
	if( $count_ignored )
	{
		$notes[] = 'Ignored last: '.$count_ignored;
	}
	if( $limited )
	{
		$notes[] = 'Limited to'.( $count_ignored ? ' remaining' : '' ).': '.$limit_to_last;
	}
	if( $notes )
	{
		$r .= '<p class="small">'.implode( ' - ', $notes ).'</p>';
	}

	$r .= "</div>\n";

	return $r;
}


/**
 * Outputs Unexpected Error message. When in debug mode it also prints a backtrace.
 *
 * This should be used instead of die() everywhere.
 * This should NOT be used instead of exit() anywhere.
 * Dying means the application has encontered an unexpected situation,
 * i-e: something that should never occur during normal operation.
 * Examples: database broken, user changed URL by hand...
 *
 * @param string Message to output
 * @param array Additional params
 *        - "status" (Default: '500 Internal Server Error')
 */
function debug_die( $additional_info = '', $params = array() )
{
	global $debug, $baseurl;
	global $log_app_errors, $app_name, $is_cli;

	$params = array_merge( array(
		'status' => '500 Internal Server Error',
		), $params );

	if( $is_cli )
	{ // Command line interface, e.g. in cron_exec.php:
		echo '== '.T_('An unexpected error has occurred!')." ==\n";
		echo T_('If this error persists, please report it to the administrator.')."\n";
		echo T_('Additional information about this error:')."\n";
		echo strip_tags( $additional_info )."\n\n";
	}
	else
	{
		// Attempt to output an error header (will not work if the output buffer has already flushed once):
		// This should help preventing indexing robots from indexing the error :P
		if( ! headers_sent() )
		{
			load_funcs('_core/_template.funcs.php');
			headers_content_mightcache( 'text/html', 0 );		// Do NOT cache error messages! (Users would not see they fixed them)
			$status_header = $_SERVER['SERVER_PROTOCOL'].' '.$params['status'];
			header($status_header);
		}

		echo '<div style="background-color: #fdd; padding: 1ex; margin-bottom: 1ex;">';
		echo '<h3 style="color:#f00;">'.T_('An unexpected error has occurred!').'</h3>';
		echo '<p>'.T_('If this error persists, please report it to the administrator.').'</p>';
		echo '<p><a href="'.$baseurl.'">'.T_('Go back to home page').'</a></p>';
		echo '</div>';

		if( ! empty( $additional_info ) )
		{
			echo '<div style="background-color: #ddd; padding: 1ex; margin-bottom: 1ex;">';
			echo '<h3>'.T_('Additional information about this error:').'</h3>';
			echo $additional_info;
			echo '</div>';
		}
	}

	if( $log_app_errors > 1 || $debug )
	{ // Prepare backtrace
		$backtrace = debug_get_backtrace();

		if( $log_app_errors > 1 || $is_cli )
		{
			$backtrace_cli = trim(strip_tags($backtrace));
		}
	}

	if( $log_app_errors )
	{ // Log error through PHP's logging facilities:
		$log_message = $app_name.' error: ';
		if( ! empty($additional_info) )
		{
			$log_message .= trim( strip_tags($additional_info) );
		}
		else
		{
			$log_message .= 'No info specified in debug_die()';
		}

		// Get file and line info:
		$file = 'Unknown';
		$line = 'Unknown';
		if( function_exists('debug_backtrace') /* PHP 4.3 */ )
		{ // get the file and line
			foreach( debug_backtrace() as $v )
			{
				if( isset($v['function']) && $v['function'] == 'debug_die' )
				{
					$file = isset($v['file']) ? $v['file'] : 'Unknown';
					$line = isset($v['line']) ? $v['line'] : 'Unknown';
					break;
				}
			}
		}
		$log_message .= ' in '.$file.' at line '.$line;

		if( $log_app_errors > 1 )
		{ // Append backtrace:
			// indent after newlines:
			$backtrace_cli = preg_replace( '~(\S)(\n)(\S)~', '$1  $2$3', $backtrace_cli );
			$log_message .= "\nBacktrace:\n".$backtrace_cli;
		}
		$log_message .= "\nREQUEST_URI:  ".( isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '-' );
		$log_message .= "\nHTTP_REFERER: ".( isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '-' );

		error_log( str_replace("\n", ' / ', $log_message), 0 /* PHP's system logger */ );
	}


	// DEBUG OUTPUT:
	if( $debug )
	{
		if( $is_cli )
			echo $backtrace_cli;
		else
			echo $backtrace;
	}

	// EXIT:
	if( ! $is_cli )
	{ // Attempt to keep the html valid (but it doesn't really matter anyway)
		echo '</body></html>';
	}

	die(1);	// Error code 1. Note: This will still call the shutdown function.
}


/**
 * Outputs Bad request Error message. When in debug mode it also prints a backtrace.
 *
 * This should be used when a bad user input is detected.
 *
 * @param string Message to output (HTML)
 */
function bad_request_die( $additional_info = '' )
{
	global $debug, $baseurl;

	// Attempt to output an error header (will not work if the output buffer has already flushed once):
	// This should help preventing indexing robots from indexing the error :P
	if( ! headers_sent() )
	{
		load_funcs('_core/_template.funcs.php');
		headers_content_mightcache( 'text/html', 0 );		// Do NOT cache error messages! (Users would not see they fixed them)
		header('HTTP/1.0 400 Bad Request');
	}

	echo '<div style="background-color: #fdd; padding: 1ex; margin-bottom: 1ex;">';
	echo '<h3 style="color:#f00;">'.T_('Bad Request!').'</h3>';
	echo '<p>'.T_('The parameters of your request are invalid.').'</p>';
	echo '<p>'.T_('If you have obtained this error by clicking on a link INSIDE of this site, please report the bad link to the administrator.').'</p>';
	echo '<p><a href="'.$baseurl.'">'.T_('Go back to home page').'</a></p>';
	echo '</div>';

	if( !empty( $additional_info ) )
	{
		echo '<div style="background-color: #ddd; padding: 1ex; margin-bottom: 1ex;">';
		echo '<h3>'.T_('Additional information about this error:').'</h3>';
		echo $additional_info;
		echo '</div>';
	}

	if( $debug )
	{
		echo debug_get_backtrace();
	}

	// Attempt to keep the html valid (but it doesn't really matter anyway)
	echo '</body></html>';

	die(2); // Error code 2. Note: this will still call the shutdown function.
}


/**
 * Outputs debug info, according to {@link $debug} or $force param.
 * This gets called typically at the end of the page.
 *
 * @param boolean true to force output regardless of {@link $debug} and content-type
 * @param boolean true to force clean output (without HTML) regardless of {@link $is_cli}
 */
function debug_info( $force = false, $force_clean = false )
{
	global $debug, $debug_done, $Debuglog, $DB, $obhandler_debug, $Timer, $ReqHost, $ReqPath, $is_cli;
	global $cache_imgsize, $cache_File;
	global $Session;
	global $db_config, $tableprefix, $http_response_code;
	/**
	 * @var Hit
	 */
	global $Hit;

	if( !$force )
	{
		if( !empty($debug_done))
		{ // Already displayed!
			return;
		}

		if( empty($debug) )
		{ // No debug output desired:
			return;
		}

		// Do not display, if no content-type header has been sent or it's != "text/html" (debug > 1 skips this)
		if( (int)$debug < 2 )
		{
			$content_type = NULL;
			foreach(headers_list() as $header)
			{
				if( stripos($header, 'content-type:') !== false )
				{ // content type sent
					# "Content-Type:text/html;charset=utf-8" => "text/html"
					$content_type = trim(array_shift(explode(';', array_pop(explode(':', $header, 2)))));
					break;
				}
			}
			if( $content_type != 'text/html' )
			{
				return;
			}
		}
	}
	//Make sure debug output only happens once:
	$debug_done = true;

	// Clean output?
	$clean = $is_cli || $force_clean;
	$printf_format = '| %-45s | %-5s | %-7s | %-5s |';
	$table_headerlen = 73;
	/* This calculates the number of dashes to print e. g. on the top and
	 * bottom of the table and after the header, making the table look
	 * better (looks like the tables of the mysql command line client).
	 * Normally, the value won't change, so it's hardcoded above. If you
	 * change the printf() format above, this might be useful.
	preg_match_all( '#\d+#', $printf_format, $table_headerlen );
	$table_headerlen = array_sum( $table_headerlen[0] ) +
									strlen( preg_replace( '#[^ \|]+#', '',
												$printf_format ) ) - 2;
	*/

	$ReqHostPathQuery = $ReqHost.$ReqPath.( empty( $_SERVER['QUERY_STRING'] ) ? '' : '?'.$_SERVER['QUERY_STRING'] );

	echo "\n\n\n";
	echo ( $clean ? '*** Debug info ***'."\n\n" : '<div class="debug" id="debug_info"><h2>Debug info</h2>' );

	if( !$obhandler_debug )
	{ // don't display changing items when we want to test obhandler

		// ========================== Timer table ================================
		$time_page = $Timer->get_duration( 'total' );
		if( $time_page ) {
			$timer_rows = array();
			foreach( $Timer->get_categories() as $l_cat )
			{
				if( $l_cat == 'sql_query' )
				{
					continue;
				}
				$timer_rows[ $l_cat ] = $Timer->get_duration( $l_cat );
			}
			// Don't sort to see orginal order of creation
			// arsort( $timer_rows );
			// ksort( $timer_rows );

			// Remove "total", it will get output as the last one:
			$total_time = $timer_rows['total'];
			unset($timer_rows['total']);

			$percent_total = $time_page > 0 ? number_format( 100/$time_page * $total_time, 2 ) : '0';

			if( $clean )
			{
				echo '== Timers =='."\n\n";
				echo '+'.str_repeat( '-', $table_headerlen ).'+'."\n";
				printf( $printf_format."\n", 'Category', 'Time', '%', 'Count' );
				echo '+'.str_repeat( '-', $table_headerlen ).'+'."\n";
			}
			else
			{
				echo '<table class="debug_timer"><thead>'
					.'<tr><td colspan="4" class="center">Timers</td></tr>' // dh> TODO: should be TH. Workaround so that tablesorter does not pick it up. Feedback from author requested.
					.'<tr><th>Category</th><th>Time</th><th>%</th><th>Count</th></tr>'
					.'</thead>';

				// Output "total":
				echo "\n<tfoot><tr>"
					.'<td>total</td>'
					.'<td class="right">'.$total_time.'</td>'
					.'<td class="right">'.$percent_total.'%</td>'
					.'<td class="right">'.$Timer->get_count('total').'</td></tr></tfoot>';

				echo '<tbody>';
			}

			$table_rows_collapse = array();
			foreach( $timer_rows as $l_cat => $l_time )
			{
				$percent_l_cat = $time_page > 0 ? number_format( 100/$time_page * $l_time, 2 ) : '0';

				if( $clean )
				{
					$row = sprintf( $printf_format, $l_cat, $l_time, $percent_l_cat.'%', $Timer->get_count( $l_cat ) );
				}
				else
				{
					$row = "\n<tr>"
						.'<td>'.$l_cat.'</td>'
						.'<td class="right">'.$l_time.'</td>'
						.'<td class="right">'.$percent_l_cat.'%</td>'
						.'<td class="right">'.$Timer->get_count( $l_cat ).'</td></tr>';
				}

				// Maybe ignore this row later, but not for clean display.
				if( ! $clean && ( $percent_l_cat < 1  ) )
				{	// Hide everything that tool less tahn 5% of the time
					$table_rows_collapse[] = $row;
				}
				else
				{
					echo $row."\n";
				}
			}
			$count_collapse = count($table_rows_collapse);
			// Collapse ignored rows, allowing to expand them with Javascript:
			if( $count_collapse > 5 )
			{
				echo '<tr><td colspan="4" class="center" id="evo-debuglog-timer-long-header">';
				echo '<a href="" onclick="var e = document.getElementById(\'evo-debuglog-timer-long\'); e.style.display = (e.style.display == \'none\' ? \'\' : \'none\'); return false;">+ '.$count_collapse.' queries &lt; 1%</a> </td></tr>';
				echo '</tbody>';
				echo '<tbody id="evo-debuglog-timer-long" style="display:none;">';
			}
			echo implode( "\n", $table_rows_collapse )."\n";

			if ( $clean )
			{ // "total" (done in tfoot for html above)
				echo sprintf( $printf_format, 'total', $total_time, $percent_total.'%', $Timer->get_count('total') );
				echo '+'.str_repeat( '-', $table_headerlen ).'+'."\n\n";
			}
			else
			{
				echo "\n</tbody></table>";

				// add jquery.tablesorter to the "Debug info" table.
				// It's too late for bundles here, so we include jQuery to be sure (this is debug only).
				global $rsc_url;
				echo '
					<script type="text/javascript" src="'.$rsc_url.'js/jquery.min.js"></script>
					<script type="text/javascript" src="'.$rsc_url.'js/jquery/jquery.tablesorter.min.js"></script>
					<script type="text/javascript">
					(function($){
						var clicked_once;
						$("table.debug_timer th").click( function(event) {
							if( clicked_once ) return; else clicked_once = true;
							$("#evo-debuglog-timer-long tr").appendTo($("table.debug_timer tbody")[0]);
							$("#evo-debuglog-timer-long-header").remove();
							// click for tablesorter:
							$("table.debug_timer").tablesorter();
							jQuery(event.currentTarget).click();
						});
					})(jQuery);
				</script>';
			}
		}

		// ================================== DB Summary ================================
		if( isset($DB) )
		{
			$sql_duration = $Timer->get_duration( 'SQL QUERIES' );
			echo $DB->num_queries.' SQL queries executed in '.( $sql_duration ? $sql_duration : '?' )." seconds\n";
			if( ! $clean )
			{
				echo ' &nbsp; <a href="'.$ReqHostPathQuery.'#evo_debug_queries">scroll down to details</a><p>';
			}
		}

		// ================================ Opcode caching ================================
		echo 'Opcode cache: '.get_active_opcode_cache();
		echo $clean ? "\n" : '<p>';

		// ================================ Memory Usage ================================
		foreach( array( // note: 8MB is default for memory_limit and is reported as 8388608 bytes
			'memory_get_usage' => array( 'display' => 'Memory usage', 'high' => 8000000 ),
			'memory_get_peak_usage' /* PHP 5.2 */ => array( 'display' => 'Memory peak usage', 'high' => 8000000 ) ) as $l_func => $l_var )
		{
			if( function_exists( $l_func ) )
			{
				$_usage = $l_func();

				if( $_usage > $l_var['high'] )
				{
					echo $clean ? '[!!] ' : '<span style="color:red; font-weight:bold">';
				}

				echo $l_var['display'].': '.bytesreadable( $_usage, ! $clean );

				if( ! $clean && $_usage > $l_var['high'] )
				{
					echo '</span>';
				}
				echo $clean ? "\n" : '<br />';
			}
		}

		// Output size of global caches:
		/* Commented out: not accurate (too low) and resource intensive to build.
		// Get sizes:
		$cache_sizes = array();
		foreach( $GLOBALS as $k => $v )
		{
			if( substr($k, -5) != 'Cache' && substr($k, 0, 6) != 'cache_' && $k != 'DB' )
			{
				continue;
			}
			$cache_sizes[$k] = strlen(serialize($v));
		}
		arsort($cache_sizes);

		// Output table:
		echo $clean ? 'Global caches (length, serialized)' : '<table class="debug_timer"><thead><th colspan="2">Global caches (length, serialized)</th></thead><tbody>';
		foreach( $cache_sizes as $k => $v )
		{
			printf( $clean ? "\$%s\t\t\t%s" : '<tr><td>$%s</td><td>%s</td></tr>', $k, bytesreadable($v) );
		}
		echo $clean ? "\n" : '</tbody></table>';
		*/
	}


	echo 'HTTP Response code: '.$http_response_code;
	echo $clean ? "\n" : '<br />';


	// DEBUGLOG(s) FROM PREVIOUS SESSIONS, after REDIRECT(s) (with list of categories at top):
	if( isset($Session) && ($sess_Debuglogs = $Session->get('Debuglogs')) && ! empty($sess_Debuglogs) )
	{
		$count_sess_Debuglogs = count($sess_Debuglogs);
		if( $count_sess_Debuglogs > 1 )
		{ // Links to those Debuglogs:
			if ( $clean )
			{	// kind of useless, but anyway...
				echo "\n".'There are '.$count_sess_Debuglogs.' Debuglogs from redirected pages.'."\n";
			}
			else
			{
				echo '<p>There are '.$count_sess_Debuglogs.' Debuglogs from redirected pages: ';
				for( $i = 1; $i <= $count_sess_Debuglogs; $i++ )
				{
					echo '<a href="'.$ReqHostPathQuery.'#debug_sess_debuglog_'.$i.'">#'.$i.'</a> ';
				}
				echo '</p>';
			}
		}

		foreach( $sess_Debuglogs as $k => $sess_Debuglog )
		{
			$log_categories = array( 'error', 'note', 'all' ); // Categories to output (in that order)

			if( $clean )
			{
				$log_container_head = "\n".'== Debug messages from redirected page (#'.($k+1).') =='."\n"
									 .'See below for the Debuglog from the current request.'."\n";
				echo format_to_output(
					$sess_Debuglog->display( array(
							'container' => array( 'string' => $log_container_head, 'template' => false ),
							'all' => array( 'string' => '= %s ='."\n\n", 'template' => false ) ),
						'', false, $log_categories, '', 'raw', false ),
					'raw' );
			}
			else
			{
				$log_container_head = '<h3 id="debug_sess_debuglog_'.($k+1).'" style="color:#f00;">Debug messages from redirected page (#'.($k+1).')</h3>'
					// link to real Debuglog:
					.'<p><a href="'.$ReqHostPathQuery.'#debug_debuglog">See below for the Debuglog from the current request.</a></p>';
				$log_cats = array_keys($sess_Debuglog->get_messages( $log_categories )); // the real list (with all replaced and only existing ones)
				$log_head_links = array();

				foreach( $log_cats as $l_cat )
				{
					$log_head_links[] .= '<a href="'.$ReqHostPathQuery.'#debug_redir_'.($k+1).'_info_cat_'.str_replace( ' ', '_', $l_cat ).'">'.$l_cat.'</a>';
				}
				$log_container_head .= implode( ' | ', $log_head_links );

				echo format_to_output(
					$sess_Debuglog->display( array(
							'container' => array( 'string' => $log_container_head, 'template' => false ),
							'all' => array( 'string' => '<h4 id="debug_redir_'.($k+1).'_info_cat_%s">%s:</h4>', 'template' => false ) ),
						'', false, $log_categories ),
					'htmlbody' );
			}
		}

		// Delete logs since they have been displayed...
		// EXCEPT if we are redirecting, because in this case we won't see these logs in a browser (only in request debug tools)
		// So in that case we want them to move over to the next page...
		if( $http_response_code < 300 || $http_response_code >= 400 )
		{	// This is NOT a 3xx redirect, assume debuglogs have been seen & delete them:
			$Session->delete( 'Debuglogs' );
		}
	}


	// CURRENT DEBUGLOG (with list of categories at top):
	$log_categories = array( 'error', 'note', 'all' ); // Categories to output (in that order)
	$log_container_head = $clean ? ( "\n".'== Debug messages =='."\n" ) : '<h3 id="debug_debuglog">Debug messages</h3>';
	if( ! empty($sess_Debuglogs) )
	{ // link to first sess_Debuglog:
		if ( $clean )
		{
			$log_container_head .= 'See above for the Debuglog(s) from before the redirect.'."\n";
		}
		else
		{
			$log_container_head .= '<p><a href="'.$ReqHostPathQuery.'#debug_sess_debuglog_1">See above for the Debuglog(s) from before the redirect.</a></p>';
		}
	}

	if ( ! $clean )
	{
		$log_cats = array_keys($Debuglog->get_messages( $log_categories )); // the real list (with all replaced and only existing ones)
		$log_head_links = array();
		foreach( $log_cats as $l_cat )
		{
			$log_head_links[] .= '<a href="'.$ReqHostPathQuery.'#debug_info_cat_'.str_replace( ' ', '_', $l_cat ).'">'.$l_cat.'</a>';
		}
		$log_container_head .= implode( ' | ', $log_head_links );

		echo format_to_output(
			$Debuglog->display( array(
					'container' => array( 'string' => $log_container_head, 'template' => false ),
					'all' => array( 'string' => '<h4 id="debug_info_cat_%s">%s:</h4>', 'template' => false ) ),
				'', false, $log_categories ),
			'htmlbody' );

		echo '<h3 id="evo_debug_queries">DB</h3>';
	}
	else
	{
		echo format_to_output(
			$Debuglog->display( array(
					'container' => array( 'string' => $log_container_head, 'template' => false ),
					'all' => array( 'string' => '= %s ='."\n\n", 'template' => false ) ),
				'', false, $log_categories, '', 'raw', false ),
			'raw' );

		echo "\n".'== DB =='."\n\n";
	}

	if($db_config)
	{
		if ( ! $clean )
		{
			echo '<pre>';
		}

		echo T_('DB Username').': '.$db_config['user']."\n".
			 T_('DB Database').': '.$db_config['name']."\n".
			 T_('DB Host').': '.(isset($db_config['host']) ? $db_config['host'] : 'unset (localhost)')."\n".
			 T_('DB tables prefix').': '.$tableprefix."\n";

		echo $clean ? "\n" : '</pre>';
	}

	if( !isset($DB) )
	{
		echo 'No DB object.'.( $clean ? "\n" : '' );
	}
	else
	{
		$DB->dump_queries( ! $clean );
	}

	if ( ! $clean )
	{
		echo '</div>';
	}
}


/**
 * Prevent email header injection.
 */
function mail_sanitize_header_string( $header_str, $close_brace = false )
{
	// Prevent injection! (remove everything after (and including) \n or \r)
	$header_str = preg_replace( '~(\r|\n).*$~s', '', trim($header_str) );

	if( $close_brace && strpos( $header_str, '<' ) !== false && strpos( $header_str, '>' ) === false )
	{ // We have probably stripped the '>' at the end!
		$header_str .= '>';
	}

	return $header_str;
}

/**
 * Encode to RFC 1342 "Representation of Non-ASCII Text in Internet Message Headers"
 *
 * @param string
 * @param string 'Q' for Quoted printable, 'B' for base64
 */
function mail_encode_header_string( $header_str, $mode = 'Q' )
{
	$r = mb_encode_mimeheader( $header_str, 'utf-8', $mode );
	return $r;
}


/**
 * Sends a mail, wrapping PHP's mail() function.
 *
 * {@link $current_locale} will be used to set the charset.
 *
 * Note: we use a single \n as line ending, though it does not comply to
 * {@link http://www.faqs.org/rfcs/rfc2822 RFC2822}, but seems to be safer,
 * because some mail transfer agents replace \n by \r\n automatically.
 *
 * @todo Unit testing with "nice addresses" This gets broken over and over again.
 *
 * @param string Recipient email address. (Caould be multiple comma-separated addresses.)
 * @param string Recipient name. (Only use if sending to a single address)
 * @param string Subject of the mail
 * @param string The message text
 * @param string From address, being added to headers (we'll prevent injections);
 *               see {@link http://securephp.damonkohler.com/index.php/Email_Injection}.
 *               Defaults to {@link $notify_from} if NULL.
 * @param string From name.
 * @param array Additional headers ( headername => value ). Take care of injection!
 * @return boolean True if mail could be sent (not necessarily delivered!), false if not - (return value of {@link mail()})
 */
function send_mail( $to, $to_name, $subject, $message, $from = NULL, $from_name = NULL, $headers = array() )
{
	global $debug, $app_name, $app_version, $current_locale, $locales, $Debuglog, $notify_from;

	$NL = "\r\n";

	if( !is_array( $headers ) )
	{ // Make sure $headers is an array
		$headers = array( $headers );
	}

	if( empty($from) )
	{
		$from = $notify_from;
	}

	if( ! is_windows() )
	{	// fplanque: Windows XP, Apache 1.3, PHP 4.4, MS SMTP : will not accept "nice" addresses.
		if( !empty( $to_name ) )
		{
			$to = '"'.mail_encode_header_string($to_name).'" <'.$to.'>';
		}
		if( !empty( $from_name ) )
		{
			$from = '"'.mail_encode_header_string($from_name).'" <'.$from.'>';
		}
	}

	$from = mail_sanitize_header_string( $from, true );
	// From has to go into headers
	$headers['From'] = $from;

	// echo 'sending email to: ['.htmlspecialchars($to).'] from ['.htmlspecialchars($from).']';

	$subject = mail_encode_header_string($subject);

	$message = str_replace( array( "\r\n", "\r" ), $NL, $message );

	// Specify charset and content-type of email
	$headers['Content-Type'] = 'text/plain; charset=utf-8';


	// ADDITIONAL HEADERS:
	$headers['X-Mailer'] = $app_name.' '.$app_version.' - PHP/'.phpversion();
	$headers['X-Remote-Addr'] = implode( ',', get_ip_list() );


	// COMPACT HEADERS:
	$headerstring = '';
	reset( $headers );
	while( list( $lKey, $lValue ) = each( $headers ) )
	{ // Add additional headers
		$headerstring .= $lKey.': '.$lValue.$NL;
	}

	// SEND MESSAGE:
	if( $debug > 1 )
	{	// We agree to die for debugging...
		if( ! mail( $to, $subject, $message, $headerstring ) )
		{
			debug_die( 'Sending mail from &laquo;'.htmlspecialchars($from).'&raquo; to &laquo;'.htmlspecialchars($to).'&raquo;, Subject &laquo;'.htmlspecialchars($subject).'&raquo; FAILED.' );
		}
	}
	else
	{	// Soft debugging only....
		if( ! @mail( $to, $subject, $message, $headerstring ) )
		{
			$Debuglog->add( 'Sending mail from &laquo;'.htmlspecialchars($from).'&raquo; to &laquo;'.htmlspecialchars($to).'&raquo;, Subject &laquo;'.htmlspecialchars($subject).'&raquo; FAILED.', 'error' );
			return false;
		}
	}

	$Debuglog->add( 'Sent mail from &laquo;'.htmlspecialchars($from).'&raquo; to &laquo;'.htmlspecialchars($to).'&raquo;, Subject &laquo;'.htmlspecialchars($subject).'&raquo;.' );
	return true;
}



/**
 * If first parameter evaluates to true printf() gets called using the first parameter
 * as args and the second parameter as print-pattern
 *
 * @param mixed variable to test and output if it's true or $disp_none is given
 * @param string printf-pattern to use (%s gets replaced by $var)
 * @param string printf-pattern to use, if $var is numeric and > 1 (%s gets replaced by $var)
 * @param string printf-pattern to use if $var evaluates to false (%s gets replaced by $var)
 */
function disp_cond( $var, $disp_one, $disp_more = NULL, $disp_none = NULL )
{
	if( is_numeric($var) && $var > 1 )
	{
		printf( ( $disp_more === NULL ? $disp_one : $disp_more ), $var );
		return true;
	}
	elseif( $var )
	{
		printf( $disp_one, $var );
		return true;
	}
	else
	{
		if( $disp_none !== NULL )
		{
			printf( $disp_none, $var );
			return false;
		}
	}
}


/**
 * Create IMG tag for an action icon.
 *
 * @param string TITLE text (IMG and A link)
 * @param string icon code for {@link get_icon()}
 * @param string URL where the icon gets linked to (empty to not wrap the icon in a link)
 * @param string word to be displayed after icon (if no icon gets displayed, $title will be used instead!)
 * @param integer 1-5: weight of the icon. The icon will be displayed only if its weight is >= than the user setting threshold.
 *                     Use 5, if it's a required icon - all others could get disabled by the user. (Default: 4)
 * @param integer 1-5: weight of the word. The word will be displayed only if its weight is >= than the user setting threshold.
 *                     (Default: 1)
 * @param array Additional attributes to the A tag. The values must be properly encoded for html output (e.g. quotes).
 *        It may also contain these params:
 *         - 'use_js_popup': if true, the link gets opened as JS popup. You must also pass an "id" attribute for this!
 *         - 'use_js_size': use this to override the default popup size ("500, 400")
 *         - 'class': defaults to 'action_icon', if not set; use "" to not use it
 * @return string The generated action icon link.
 */
function action_icon( $title, $icon, $url, $word = NULL, $icon_weight = NULL, $word_weight = NULL, $link_attribs = array() )
{
	global $UserSettings;

	$link_attribs['href'] = $url;
	$link_attribs['title'] = $title;

	if( is_null($icon_weight) )
	{
		$icon_weight = 4;
	}
	if( is_null($word_weight) )
	{
		$word_weight = 1;
	}

	if( ! isset($link_attribs['class']) )
	{
		$link_attribs['class'] = 'action_icon';
	}

	if( get_icon( $icon, 'rollover' ) )
	{
		if( empty($link_attribs['class']) )
		{
			$link_attribs['class'] = 'rollover';
		}
		else
		{
			$link_attribs['class'] .= ' rollover';
		}
	}

	// "use_js_popup": open link in a JS popup
	// TODO: this needs to be rewritten with jQuery instead
	if( false && ! empty($link_attribs['use_js_popup']) )
	{
		$popup_js = 'var win = new PopupWindow(); win.setUrl( \''.$link_attribs['href'].'\' ); win.setSize(  ); ';

		if( isset($link_attribs['use_js_size']) )
		{
			if( ! empty($link_attribs['use_js_size']) )
			{
				$popup_size = $link_attribs['use_js_size'];
			}
		}
		else
		{
			$popup_size = '500, 400';
		}
		if( isset($popup_size) )
		{
			$popup_js .= 'win.setSize( '.$popup_size.' ); ';
		}
		$popup_js .= 'win.showPopup(\''.$link_attribs['id'].'\'); return false;';

		if( empty( $link_attribs['onclick'] ) )
		{
			$link_attribs['onclick'] = $popup_js;
		}
		else
		{
			$link_attribs['onclick'] .= $popup_js;
		}
		unset($link_attribs['use_js_popup']);
		unset($link_attribs['use_js_size']);
	}

	// NOTE: We do not use format_to_output with get_field_attribs_as_string() here, because it interferes with the Results class (eval() fails on entitied quotes..) (blueyed)
	$r = '<a'.get_field_attribs_as_string( $link_attribs, false ).'>';

	$display_icon = ($icon_weight >= $UserSettings->get('action_icon_threshold'));
	$display_word = ($word_weight >= $UserSettings->get('action_word_threshold'));

	if( $display_icon || ! $display_word )
	{	// We MUST display an action icon in order to make the user happy:
		// OR we default to icon because the user doesn't want the word either!!

		if( $icon_s = get_icon( $icon, 'imgtag', array( 'title'=>$title ), true ) )
		{
			$r .= $icon_s;
		}
		else
		{ // fallback to word
			$display_word = true;
		}
	}

	if( $display_word )
	{	// We MUST display an action word in order to make the user happy:

		if( $display_icon )
		{ // We already have an icon, display a SHORT word:
			if( !empty($word) )
			{	// We have provided a short word:
				$r .= $word;
			}
			else
			{	// We fall back to alt:
				$r .= get_icon( $icon, 'legend' );
			}
		}
		else
		{	// No icon display, let's display a LONG word/text:
			$r .= trim( $title, ' .!' );
		}
	}

	$r .= '</a>';

	return $r;
}


/**
 * Get properties of an icon.
 *
 * Note: to get a file type icon, use {@link File::get_icon()} instead.
 *
 * @uses get_icon_info()
 * @param string icon for what? (key)
 * @param string what to return for that icon ('imgtag', 'alt', 'legend', 'file', 'url', 'size' {@link imgsize()})
 * @param array additional params
 *   - 'class' => class name when getting 'imgtag',
 *   - 'size' => param for 'size',
 *   - 'title' => title attribute for 'imgtag'
 * @param boolean true to include this icon into the legend at the bottom of the page (works for 'imgtag' only)
 * @return mixed False on failure, string on success.
 */
function get_icon( $iconKey, $what = 'imgtag', $params = NULL, $include_in_legend = false )
{
	global $admin_subdir, $Debuglog, $use_strict;
	global $conf_path;
	global $rsc_path, $rsc_url;

	if( ! function_exists('get_icon_info') )
	{
		require_once $conf_path.'_icons.php';
	}

	$icon = get_icon_info($iconKey);
	if( ! $icon || ! isset( $icon['file'] ) )
	{
		$Debuglog->add('No image defined for '.var_export( $iconKey, true ).'!', 'icons');
		return false;
	}

	switch( $what )
	{
		case 'rollover':
			if( isset( $icon['rollover'] ) )
			{	// Image has rollover available
				return $icon['rollover'];
			}
			return false;
			/* BREAK */


		case 'file':
			return $rsc_path.$icon['file'];
			/* BREAK */


		case 'alt':
			if( isset( $icon['alt'] ) )
			{ // alt tag from $map_iconfiles
				return $icon['alt'];
			}
			else
			{ // fallback to $iconKey as alt-tag
				return $iconKey;
			}
			/* BREAK */


		case 'legend':
			if( isset( $icon['legend'] ) )
			{ // legend tag from $map_iconfiles
				return $icon['legend'];
			}
			else
			if( isset( $icon['alt'] ) )
			{ // alt tag from $map_iconfiles
				return $icon['alt'];
			}
			else
			{ // fallback to $iconKey as alt-tag
				return $iconKey;
			}
			/* BREAK */


		case 'class':
			if( isset($icon['class']) )
			{
				return $icon['class'];
			}
			else
			{
				return '';
			}
			/* BREAK */

		case 'url':
			return $rsc_url.$icon['file'];
			/* BREAK */

		case 'size':
			if( !isset( $icon['size'] ) )
			{
				$Debuglog->add( 'No iconsize for ['.$iconKey.']', 'icons' );

				$icon['size'] = imgsize( $rsc_path.$icon['file'] );
			}

			switch( $params['size'] )
			{
				case 'width':
					return $icon['size'][0];

				case 'height':
					return $icon['size'][1];

				case 'widthxheight':
					return $icon['size'][0].'x'.$icon['size'][1];

				case 'width':
					return $icon['size'][0];

				case 'string':
					return 'width="'.$icon['size'][0].'" height="'.$icon['size'][1].'"';

				default:
					return $icon['size'];
			}
			/* BREAK */


		case 'imgtag':
			$r = '<img src="'.$rsc_url.$icon['file'].'" ';

			if( !$use_strict )
			{	// Include non CSS fallbacks - transitional only:
				$r .= 'border="0" align="top" ';
			}

			// Include class (will default to "icon"):
			if( ! isset( $params['class'] ) )
			{
				if( isset($icon['class']) )
				{	// This icon has a class
					$params['class'] = $icon['class'];
				}
				else
				{
					$params['class'] = '';
				}
			}

			// Include size (optional):
			if( isset( $icon['size'] ) )
			{
				$r .= 'width="'.$icon['size'][0].'" height="'.$icon['size'][1].'" ';
			}

			// Include alt (XHTML mandatory):
			if( ! isset( $params['alt'] ) )
			{
				if( isset( $icon['alt'] ) )
				{ // alt-tag from $map_iconfiles
					$params['alt'] = $icon['alt'];
				}
				else
				{ // $iconKey as alt-tag
					$params['alt'] = $iconKey;
				}
			}

			// Add all the attributes:
			$r .= get_field_attribs_as_string( $params, false );

			// Close tag:
			$r .= '/>';


			if( $include_in_legend && ( $IconLegend = & get_IconLegend() ) )
			{ // This icon should be included into the legend:
				$IconLegend->add_icon( $iconKey );
			}

			return $r;
			/* BREAK */

		case 'noimg':
			$blank_icon = get_icon_info('pixel');

			$r = '<img src="'.$rsc_url.$blank_icon['file'].'" ';

			// TODO: dh> add this only for !$use_strict, like above?
			// Include non CSS fallbacks (needed by bozos... and basic skin):
			$r .= 'border="0" align="top" ';

			// Include class (will default to "noicon"):
			if( ! isset( $params['class'] ) )
			{
				if( isset($icon['class']) )
				{	// This icon has a class
					$params['class'] = $icon['class'];
				}
				else
				{
					$params['class'] = 'no_icon';
				}
			}

			// Include size (optional):
			if( isset( $icon['size'] ) )
			{
				$r .= 'width="'.$icon['size'][0].'" height="'.$icon['size'][1].'" ';
			}

			// Include alt (XHTML mandatory):
			if( ! isset( $params['alt'] ) )
			{
				$params['alt'] = '';
			}

			// Add all the attributes:
			$r .= get_field_attribs_as_string( $params, false );

			// Close tag:
			$r .= '/>';

			return $r;
			/* BREAK */
	}
}


/**
 * @param string date (YYYY-MM-DD)
 * @param string time
 */
function form_date( $date, $time = '' )
{
	return substr( $date.'          ', 0, 10 ).' '.$time;
}


/**
 * Get list of client IP addresses from REMOTE_ADDR and HTTP_X_FORWARDED_FOR,
 * in this order. '' is used when no IP could be found.
 *
 * @param boolean True, to get only the first IP (probably REMOTE_ADDR)
 * @return array|string Depends on first param.
 */
function get_ip_list( $firstOnly = false )
{
	$r = array();

	if( !empty( $_SERVER['REMOTE_ADDR'] ) )
	{
		foreach( explode( ',', $_SERVER['REMOTE_ADDR'] ) as $l_ip )
		{
			$l_ip = trim($l_ip);
			if( !empty($l_ip) )
			{
				$r[] = $l_ip;
			}
		}
	}

	if( !empty($_SERVER['HTTP_X_FORWARDED_FOR']) )
	{ // IP(s) behind Proxy - this can be easily forged!
		foreach( explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] ) as $l_ip )
		{
			$l_ip = trim($l_ip);
			if( !empty($l_ip) && $l_ip != 'unknown' )
			{
				$r[] = $l_ip;
			}
		}
	}

	if( !isset( $r[0] ) )
	{ // No IP found.
		$r[] = '';
	}

	return $firstOnly ? $r[0] : $r;
}


/**
 * Get the base domain (without protocol and any subdomain) of an URL.
 *
 * Gets a max of 3 domain parts (x.y.tld)
 *
 * @param string URL
 * @return string the base domain (may become empty, if found invalid)
 */
function get_base_domain( $url )
{
	//echo '<p>'.$url;
	// Chop away the http part and the path:
	$domain = preg_replace( '~^([a-z]+://)?([^:/#]+)(.*)$~i', '\\2', $url );

	if( empty($domain) || preg_match( '~^(\d+\.)+\d+$~', $domain ) )
	{	// Empty or All numeric = IP address, don't try to cut it any further
		return $domain;
	}

	//echo '<br>'.$domain;

	// Get the base domain up to 3 levels (x.y.tld):
	// NOTE: "_" is not really valid, but for Windows it is..
	// NOTE: \w includes "_"

	// convert URL to IDN:
	$domain = idna_encode($domain);

	$domain_pattern = '~ ( \w (\w|-|_)* \. ){0,2}   \w (\w|-|_)* $~ix';
	if( ! preg_match( $domain_pattern, $domain, $match ) )
	{
		return '';
	}
	$base_domain = idna_decode($match[0]);

	// Remove any www*. prefix:
	$base_domain = preg_replace( '~^www.*?\.~i', '', $base_domain );

	//echo '<br>'.$base_domain.'</p>';

	return $base_domain;
}


/**
 * Generate a valid key of size $length.
 *
 * @param integer length of key
 * @param string chars to use in generated key
 * @return string key
 */
function generate_random_key( $length = 32, $keychars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' )
{
	$key = '';
	$rnd_max = strlen($keychars) - 1;

	for( $i = 0; $i < $length; $i++ )
	{
		$key .= $keychars{mt_rand(0, $rnd_max)}; // get a random character out of $keychars
	}

	return $key;
}


/**
 * Generate a random password with no ambiguous chars
 *
 * @param integer length of password
 * @return string password
 */
function generate_random_passwd( $length = NULL )
{
	// fp> NOTE: do not include any characters that would make autogenerated passwords ambiguous
	// 1 (one) vs l (L) vs I (i)
	// O (letter) vs 0 (digit)

	if( empty($length) )
	{
		$length = rand( 8, 14 );
	}

	return generate_random_key( $length, 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789' );
}


function is_create_action( $action )
{
	$action_parts = explode( '_', $action );

	switch( $action_parts[0] )
	{
		case 'new':
		case 'new_switchtab':
		case 'copy':
		case 'create':	// we return in this state after a validation error
			return true;

		case 'edit':
		case 'edit_switchtab':
		case 'update':	// we return in this state after a validation error
		case 'delete':
		// The following one's a bit far fetched, but can happen if we have no sheet display:
		case 'unlink':
		case 'view':
			return false;

		default:
			debug_die( 'Unhandled action in form: '.strip_tags($action_parts[0]) );
	}
}


/**
 * Generate a link that toggles display of an element on clicking.
 *
 * @todo Provide functionality to make those links accessible without JS (using GET parameter)
 * @uses toggle_display_by_id() (JS)
 * @param string ID (html) of the link
 * @param string ID (html) of the target to toggle displaying
 * @return string
 */
function get_link_showhide( $link_id, $target_id, $text_when_displayed, $text_when_hidden, $display_hidden = true )
{
	$html = "<a id='$link_id' href='#' onclick='return toggle_display_by_id(\"$link_id\", \"$target_id\", \""
		.jsspecialchars( $text_when_displayed ).'", "'.jsspecialchars( $text_when_hidden ).'")\'>'
		.( $display_hidden ? $text_when_hidden : $text_when_displayed )
		.'</a>';

	return $html;
}


/**
 * Escape a string to be used in Javascript.
 *
 * @param string
 * @return string
 */
function jsspecialchars($s)
{
	$r = str_replace(
		array(  '\\', '"', "'" ),
		array( '\\\\', '\"', "\'" ),
		$s );
	return htmlspecialchars($r, ENT_QUOTES);
}


/**
 * Compact a date in a number keeping only integer value of the string
 *
 * @param string date
 */
function compact_date( $date )
{
	return preg_replace( '#[^0-9]#', '', $date );
}


/**
 * Decompact a date in a date format ( Y-m-d h:m:s )
 *
 * @param string date
 */
function decompact_date( $date )
{
	$date0 = $date;

	return  substr($date0,0,4).'-'.substr($date0,4,2).'-'.substr($date0,6,2).' '
								.substr($date0,8,2).':'.substr($date0,10,2).':'.substr($date0,12,2);
}

/**
 * Check the format of the phone number param and
 * format it in a french number if it is.
 *
 * @param string phone number
 */
function format_phone( $phone, $hide_country_dialing_code_if_same_as_locale = true )
{
	global $CountryCache;

	$dialing_code = NULL;

	if( substr( $phone, 0, 1 ) == '+' )
	{	// We have a dialing code in the phone, so we extract it:
		$dialing_code = $CountryCache->extract_country_dialing_code( substr( $phone, 1 ) );
	}

	if( !is_null( $dialing_code ) && ( locale_dialing_code() == $dialing_code )
			&& $hide_country_dialing_code_if_same_as_locale )
	{	// The phone dialing code is same as locale and we want to hide it in this case
		if( ( strlen( $phone ) - strlen( $dialing_code ) ) == 10 )
		{	// We can format it like a french phone number ( 0x.xx.xx.xx.xx )
			$phone_formated = format_french_phone( '0'.substr( $phone, strlen( $dialing_code )+1 ) );
		}
		else
		{ // ( 0xxxxxxxxxxxxxx )
			$phone_formated = '0'.substr( $phone, strlen( $dialing_code )+1 );
		}

	}
	elseif( !is_null( $dialing_code ) )
	{	// Phone has a dialing code
		if( ( strlen( $phone ) - strlen( $dialing_code ) ) == 10 )
		{ // We can format it like a french phone number with the dialing code ( +dialing x.xx.xx.xx.xx )
			$phone_formated = '+'.$dialing_code.format_french_phone( ' '.substr( $phone, strlen( $dialing_code )+1 ) );
		}
		else
		{ // ( +dialing  xxxxxxxxxxx )
			$phone_formated = '+'.$dialing_code.' '.substr( $phone, strlen( $dialing_code )+1 );
		}
	}
	else
	{
		if( strlen( $phone ) == 10 )
		{ //  We can format it like a french phone number ( xx.xx.xx.xx.xx )
			$phone_formated = format_french_phone( $phone );
		}
		else
		{	// We don't format phone: TODO generic format phone ( xxxxxxxxxxxxxxxx )
			$phone_formated = $phone;
		}
	}

	return $phone_formated;
}


/**
 * Format a string in a french phone number
 *
 * @param string phone number
 */
function format_french_phone( $phone )
{
	return substr($phone, 0 , 2).'.'.substr($phone, 2, 2).'.'.substr($phone, 4, 2)
					.'.'.substr($phone, 6, 2).'.'.substr($phone, 8, 2);
}


/**
 * Generate a link to a online help resource.
 * testing the concept of online help (aka webhelp).
 * this function should be relocated somewhere better if it is taken onboard by the project
 *
 * @todo replace [?] with icon,
 * @todo write url suffix dynamically based on topic and language
 *
 * QUESTION: launch new window with javascript maybe?
 * @param string Topic
 *        The topic should be in a format like [\w]+(/[\w]+)*, e.g features/online_help.
 * @return string
 */
function get_manual_link( $topic )
{
	global $Settings, $current_locale, $app_shortname, $app_version;

	if( $Settings->get('webhelp_enabled') )
	{
		$manual_url = 'http://manual.b2evolution.net/redirect/'.str_replace(' ','_',strtolower($topic)).'?lang='.$current_locale.'&amp;app='.$app_shortname.'&amp;version='.$app_version;

		$webhelp_link = action_icon( T_('Open relevant page in online manual'), 'manual', $manual_url, T_('Manual'), 5, 1, array( 'target' => '_blank' ) );

		return ' '.$webhelp_link;
	}
	else
	{
		return '';
	}
}


/**
 * Build a string out of $field_attribs, with each attribute
 * prefixed by a space character.
 *
 * @param array Array of field attributes.
 * @param boolean Use format_to_output() for the attributes?
 * @return string
 */
function get_field_attribs_as_string( $field_attribs, $format_to_output = true )
{
	$r = '';
	foreach( $field_attribs as $l_attr => $l_value )
	{
		if( $l_value === NULL )
		{ // don't generate empty attributes (it may be NULL if we pass 'value' => NULL as field_param for example, because isset() does not match it!)
			// sam2kb> what about alt="" how do we handle this?
			// I've removed the "=== ''" check now. Should not do any harm. IIRC NULL is what we want to avoid here.
			continue;
		}

		if( $format_to_output )
		{
			$r .= ' '.$l_attr.'="'.htmlspecialchars($l_value).'"';
		}
		else
		{
			$r .= ' '.$l_attr.'="'.$l_value.'"';
		}
	}

	return $r;
}


/**
 * Is the current page an admin/backoffice page?
 *
 * @return boolean
 */
function is_admin_page()
{
	global $is_admin_page;

	return isset($is_admin_page) && $is_admin_page === true; // check for type also, because of register_globals!
}


/**
 * Implode array( 'x', 'y', 'z' ) to something like 'x, y and z'. Useful for displaying list to the end user.
 *
 * If there's one element in the table, it is returned.
 * If there are at least two elements, the last one is concatenated using $implode_last, while the ones before are imploded using $implode_by.
 *
 * @todo dh> I don't think using entities/HTML as default for $implode_last is sane!
 *           Use "&" instead and make sure that the output for HTML is HTML compliant..
 * @todo Support for locales that have a different kind of enumeration?!
 * @return string
 */
function implode_with_and( $arr, $implode_by = ', ', $implode_last = ' &amp; ' )
{
	switch( count($arr) )
	{
		case 0:
			return '';

		case 1:
			$r = array_shift($arr);
			return $r;

		default:
			$r = implode( $implode_by, array_slice( $arr, 0, -1 ) )
			    .$implode_last.array_pop( $arr );
			return $r;
	}
}


/**
 * Display an array as a list:
 *
 * @param array
 * @param string
 * @param string
 * @param string
 * @param string
 * @param string
 */
function display_list( $items, $list_start = '<ul>', $list_end = '</ul>', $item_separator = '',
												$item_start = '<li>', $item_end = '</li>', $force_hash = NULL, $max_items = NULL, $link_params = array() )
{
	if( !is_null($max_items) && $max_items < 1 )
	{
		return;
	}

	if( !empty( $items ) )
	{
		echo $list_start;
		$count = 0;
		$first = true;

		foreach( $items as $item )
		{	// For each list item:

			$link = resolve_link_params( $item, $force_hash, $link_params );
			if( empty( $link ) )
			{
				continue;
			}

			$count++;
			if( $count>1 )
			{
				echo $item_separator;
			}
			echo $item_start.$link.$item_end;

			if( !is_null($max_items) && $count >= $max_items )
			{
				break;
			}
		}
		echo $list_end;
	}
}


/**
 * Credits stuff.
 */
function display_param_link( $params )
{
	echo resolve_link_params( $params );
}


/**
 * Resolve a link based on params (credits stuff)
 *
 * @param array
 * @param integer
 * @param array
 * @return string
 */
function resolve_link_params( $item, $force_hash = NULL, $params = array() )
{
	global $current_locale;

	// echo 'resolve link ';

	if( is_array( $item ) )
	{
		if( isset( $item[0] ) )
		{	// Older format, which displays the same thing for all locales:
			return generate_link_from_params( $item, $params );
		}
		else
		{	// First get the right locale:
			// echo $current_locale;
			foreach( $item as $l_locale => $loc_item )
			{
				if( $l_locale == substr( $current_locale, 0, strlen($l_locale) ) )
				{	// We found a matching locale:
					//echo "[$l_locale/$current_locale]";
					if( is_array( $loc_item[0] ) )
					{	// Randomize:
						$loc_item = hash_link_params( $loc_item, $force_hash );
					}

					return generate_link_from_params( $loc_item, $params );
				}
			}
			// No match found!
			return '';
		}
	}

	// Super old format:
	return $item;
}


/**
 * Get a link line, based url hash combined with probability percentage in first column
 *
 * @param array of arrays
 * @param display for a specific hash key
 */
function hash_link_params( $link_array, $force_hash = NULL )
{
	global $ReqHost, $ReqPath, $ReqURI;

	static $hash;

	if( !is_null($force_hash) )
	{
		$hash = $force_hash;
	}
	elseif( !isset($hash) )
	{
		$key = $ReqHost.$ReqPath;

		global $Blog;
		if( !empty($Blog) && strpos( $Blog->get_setting('single_links'), 'param_' ) === 0 )
		{	// We are on a blog that doesn't even have clean URLs for posts
			$key .= $ReqURI;
		}

		$hash = 0;
		for( $i=0; $i<strlen($key); $i++ )
		{
			$hash += ord($key[$i]);
		}
		$hash = $hash % 100 + 1;

		// $hash = rand( 1, 100 );
		global $debug, $Debuglog;
		if( $debug )
		{
			$Debuglog->add( 'Hash key: '.$hash, 'request' );
		}
	}
	//	echo "[$hash] ";

	foreach( $link_array as $link_params )
	{
		// echo '<br>'.$hash.'-'.$link_params[ 0 ];
		if( $hash <= $link_params[ 0 ] )
		{	// select this link!
			// pre_dump( $link_params );
			array_shift( $link_params );
			return $link_params;
		}
	}
	// somehow no match, return 1st element:
	$link_params = $link_array[0];
	array_shift( $link_params );
	return $link_params;
}


/**
 * Generate a link from params (credits stuff)
 *
 * @param array
 * @param array
 */
function generate_link_from_params( $link_params, $params = array() )
{
	$url = $link_params[0];
	if( empty( $url ) )
	{
		return '';
	}

	// Make sure we are not missing any param:
	$params = array_merge( array(
			'type'        => 'link',
			'img_url'     => '',
			'img_width'   => '',
			'img_height'  => '',
			'title'       => '',
			'target'      => '_blank',
		), $params );

	$text = $link_params[1];
	if( is_array($text) )
	{
		$text = hash_link_params( $text );
		$text = $text[0];
	}
	if( empty( $text ) )
	{
		return '';
	}

	$r = '<a href="'.$url.'"';

	if( !empty($params['target'] ) )
	{
		$r .= ' target="'.$params['target'].'"';
	}

	if( $params['type'] == 'img' )
	{
		return $r.' title="'.$params['title'].'"><img src="'.$params['img_url'].'" alt="'
						.$text.'" title="'.$params['title'].'" width="'.$params['img_width'].'" height="'.$params['img_height']
						.'" border="0" /></a>';
	}

	return $r.'>'.$text.'</a>';
}


/**
 * Send a result as javascript
 * automatically includes any Messages ( @see Log::display() )
 * no return from function as it terminates processing
 *
 * @author Yabba
 *
 * @todo dh> Move this out into some more specific (not always included) file.
 *
 * @param array $methods javascript funtions to call with array of parameters
 *		format : 'function_name' => array( param1, parm2, param3 )
 * @param boolean $send_as_html Wrap the script into an html page with script tag; default is to send as js file
 * @param string $target prepended to function calls : blank or window.parent
 */
function send_javascript_message( $methods = array(), $send_as_html = false, $target = '' )
{
	// lets spit out any messages
	global $Messages;
	ob_start();
	$Messages->display();
	$output = ob_get_clean();

	// set target
	$target = ( $target ? $target : param( 'js_target', 'string' ) );
	if( $target )
	{	// add trailing [dot]
		$target = trim( $target, '.' ).'.';
	}

	// target should be empty or window.parent.
	if( $target && $target != 'window.parent.' )
	{
		debug_die( 'Unexpected javascript target' );
	}

	if( $output )
	{	// we have some messages
		$output = $target.'DisplayServerMessages( \''.format_to_js( $output ).'\');'."\n";
	}

	if( !empty( $methods ) )
	{	// we have a methods to call
		foreach( $methods as $method => $param_list )
		{	// loop through each requested method
			$params = array();
			if( !is_array( $param_list ) )
			{	// lets make it an array
				$param_list = array( $param_list );
			}
			foreach( $param_list as $param )
			{	// add each parameter to the output
				if( !is_numeric( $param ) )
				{	// this is a string, quote it
					$param = '\''.format_to_js( $param ).'\'';
				}
				$params[] = $param;// add param to the list
			}
			// add method and parameters
			$output .= $target.$method.'('.implode( ',', $params ).');'."\n";
		}
	}

	if( $send_as_html )
	{	// we want to send as a html document
		headers_content_mightcache( 'text/html', 0 );		// Do NOT cache interactive communications.
		echo '<html><head></head><body><script type="text/javascript">'."\n";
		echo $output;
		echo '</script></body></html>';
	}
	else
	{	// we want to send as js
		headers_content_mightcache( 'text/javascript', 0 );		// Do NOT cache interactive communications.
		echo $output;
	}

	exit(0);
}


/**
 * Basic tidy up of strings
 *
 * @author Yabba
 * @author Tblue
 *
 * @param string $unformatted raw data
 * @return string formatted data
 */
function format_to_js( $unformatted )
{
	return str_replace( array(
							'\'',
							'\n',
							'\r',
							'\t',
							"\n",
							"\r",
						),
						array(
							'\\\'',
							'\\\\n',
							'\\\\r',
							'\\\\t',
							'\n',
							'\r',
						), $unformatted );
}

/**
 * Wrapper around htmlspecialchars, with default of 'UTF-8' for $charset
 *
 * @return string
 */
function utf8_htmlspecialchars($string, $quote_style = ENT_QUOTES, $charset = 'UTF-8', $double_encode = true)
{
	return htmlspecialchars($string, $quote_style, $charset, $double_encode);
}


/**
 * @return array key=>name
 */
function get_available_sort_options()
{
	return array(
		'datestart'    => T_('Date issued (Default)'),
		'order'        => T_('Order (as explicitly specified)'),
		//'datedeadline' => T_('Deadline'),
		'title'        => T_('Title'),
		'datecreated'  => T_('Date created'),
		'datemodified' => T_('Date last modified'),
		'urltitle'     => T_('URL "filename"'),
		'priority'     => T_('Priority'),
		'views'        => T_('Views'),
		'RAND'         => T_('Random order!'),
	);
}


/**
 * Get a value from a volatile/lossy cache.
 *
 * @param string key
 * @param boolean success (by reference)
 * @return mixed True in case of success, false in case of failure. NULL, if no backend is available.
 */
function get_from_mem_cache($key, & $success )
{
	global $Timer;

	$Timer->resume('get_from_mem_cache', false);

	if( function_exists('apc_fetch') )
		$r = apc_fetch( $key, $success );
	elseif( function_exists('xcache_get') && ini_get('xcache.var_size') > 0 )
		$r = xcache_get($key);
	elseif( function_exists('eaccelerator_get') )
		$r = eaccelerator_get($key);

	if( ! isset($success) )
	{ // set $success for implementation that do not set it itself (only APC does so)
		$success = isset($r);
	}
	if( ! $success )
	{
		$r = NULL;

		global $Debuglog;
		$Debuglog->add('No caching backend available for reading "'.$key.'".', 'cache');
	}

	$Timer->pause('get_from_mem_cache', false);
	return $r;
}


/**
 * Set a value to a volatile/lossy cache.
 *
 * There's no guarantee that the data is still available, since e.g. old
 * values might get purged.
 *
 * @param string key
 * @param mixed Data. Objects would have to be serialized.
 * @param int Time to live (seconds). Default is 0 and means "forever".
 * @return mixed
 */
function set_to_mem_cache($key, $payload, $ttl = 0)
{
	global $Timer;

	$Timer->resume('set_to_mem_cache', false);

	if( function_exists('apc_store') )
		$r = apc_store( $key, $payload, $ttl );
	elseif( function_exists('xcache_set') && ini_get('xcache.var_size') > 0 )
		$r = xcache_set( $key, $payload, $ttl );
	elseif( function_exists('eaccelerator_put') )
		$r = eaccelerator_put( $key, $payload, $ttl );
	else {
		global $Debuglog;
		$Debuglog->add('No caching backend available for writing "'.$key.'".', 'cache');
		$r = NULL;
	}

	$Timer->pause('set_to_mem_cache', false);

	return $r;
}


/**
 * Remove a given key from the volatile/lossy cache.
 *
 * @param string key
 * @return boolean True on success, false on failure. NULL if no backend available.
 */
function unset_from_mem_cache($key)
{
	if( function_exists('apc_delete') )
		return apc_delete( $key );

	if( function_exists('xcache_unset') )
		return xcache_unset(gen_key_for_cache($key));

	if( function_exists('eaccelerator_rm') )
		return eaccelerator_rm(gen_key_for_cache($key));
}


/**
 * Generate order by clause
 *
 * @return string
 */
function gen_order_clause( $order_by, $order_dir, $dbprefix, $dbIDname_disambiguation )
{
	$orderby = str_replace( ' ', ',', $order_by );
	$orderby_array = explode( ',', $orderby );

	// Format each order param with default column names:
	$orderby_array = preg_replace( '#^(.+)$#', $dbprefix.'$1 '.$order_dir, $orderby_array );

	$order_by = implode( ', ', $orderby_array );

	// Special case for RAND:
	$order_by = str_replace( $dbprefix.'RAND ', 'RAND() ', $order_by );

	// Add an ID parameter to make sure there is no ambiguity in ordering on similar items:
	$order_by = $order_by.', '.$dbIDname_disambiguation.' '.$order_dir;

	return $order_by;
}


/**
 * Get the IconLegend instance.
 *
 * @return IconLegend or false, if the user has not set "display_icon_legend"
 */
function & get_IconLegend()
{
	static $IconLegend;

	if( ! isset($IconLegend) )
	{
		global $UserSettings;
		if( $UserSettings->get('display_icon_legend') )
		{
			/**
			 * Icon Legend
			 */
			load_class( '_core/ui/_iconlegend.class.php', 'IconLegend' );
			$IconLegend = new IconLegend();
		}
		else
		{
			$IconLegend = false;
		}
	}
	return $IconLegend;
}


/**
 * Get name of active opcode cache, or "none".
 * {@internal Anyone using something else, please extend.}}
 * @return string
 */
function get_active_opcode_cache()
{

	if( function_exists('apc_cache_info') && ini_get('apc.enabled') ) # disabled for CLI (see apc.enable_cli), however: just use this setting and do not call the function.
	{
		// fp>blueyed? why did you remove the following 2 lines? your comment above is not clear.
		$apc_info = apc_cache_info( '', true );
		if( $apc_info['num_entries'] )
		{
			return 'APC';
		}
	}

	// xcache: xcache.var_size must be > 0. xcache_set is not necessary (might have been disabled).
	if( ini_get('xcache.size') > 0 )
	{
		return 'xcache';
	}

	return 'none';
}


/**
 * Get comments awaiting moderation number
 *
 * @todo fp>max please put this into dashboard.funcs
 *
 * @param integer blog ID
 * @return integer
 */
function get_comments_awaiting_moderation_number( $blog_ID )
{
	global $DB;

	$BlogCache = & get_BlogCache();
	$Blog = & $BlogCache->get_by_ID( $blog_ID, false, false );

	$sql = 'SELECT COUNT(DISTINCT(comment_ID))
				FROM T_comments
					INNER JOIN T_items__item ON comment_post_ID = post_ID ';

	$sql .= 'INNER JOIN T_postcats ON post_ID = postcat_post_ID
				INNER JOIN T_categories othercats ON postcat_cat_ID = othercats.cat_ID ';

	$sql .= 'WHERE '.$Blog->get_sql_where_aggregate_coll_IDs('othercats.cat_blog_ID');
	$sql .= ' AND comment_type IN (\'comment\',\'trackback\',\'pingback\') ';
	$sql .= ' AND comment_status = \'draft\'';
	$sql .= ' AND '.statuses_where_clause();

	return $DB->get_var( $sql );
}


/**
* Get $ReqPath, $ReqURI
*
* @return array ($ReqPath,$ReqURI);
*/
function get_ReqURI()
{
	global $Debuglog;

	// Investigation for following code by Isaac - http://isaacschlueter.com/
	if( isset($_SERVER['REQUEST_URI']) && !empty($_SERVER['REQUEST_URI']) )
	{ // Warning: on some IIS installs it it set but empty!
		$Debuglog->add( 'vars: vars: Getting ReqURI from REQUEST_URI', 'request' );
		$ReqURI = $_SERVER['REQUEST_URI'];

		// Build requested Path without query string:
		$pos = strpos( $ReqURI, '?' );
		if( false !== $pos )
		{
			$ReqPath = substr( $ReqURI, 0, $pos  );
		}
		else
		{
			$ReqPath = $ReqURI;
		}
	}
	elseif( isset($_SERVER['URL']) )
	{ // ISAPI
		$Debuglog->add( 'vars: Getting ReqPath from URL', 'request' );
		$ReqPath = $_SERVER['URL'];
		$ReqURI = isset($_SERVER['QUERY_STRING']) && !empty( $_SERVER['QUERY_STRING'] ) ? ($ReqPath.'?'.$_SERVER['QUERY_STRING']) : $ReqPath;
	}
	elseif( isset($_SERVER['PATH_INFO']) )
	{ // CGI/FastCGI
		if( isset($_SERVER['SCRIPT_NAME']) )
		{
			$Debuglog->add( 'vars: Getting ReqPath from PATH_INFO and SCRIPT_NAME', 'request' );

			if ($_SERVER['SCRIPT_NAME'] == $_SERVER['PATH_INFO'] )
			{	/* both the same so just use one of them
				 * this happens on a windoze 2003 box
				 * gotta love microdoft
				 */
				$Debuglog->add( 'vars: PATH_INFO and SCRIPT_NAME are the same', 'request' );
				$Debuglog->add( 'vars: Getting ReqPath from PATH_INFO only instead', 'request' );
				$ReqPath = $_SERVER['PATH_INFO'];
			}
			else
			{
				$ReqPath = $_SERVER['SCRIPT_NAME'].$_SERVER['PATH_INFO'];
			}
		}
		else
		{ // does this happen??
			$Debuglog->add( 'vars: Getting ReqPath from PATH_INFO only!', 'request' );

			$ReqPath = $_SERVER['PATH_INFO'];
		}
		$ReqURI = isset($_SERVER['QUERY_STRING']) && !empty( $_SERVER['QUERY_STRING'] ) ? ($ReqPath.'?'.$_SERVER['QUERY_STRING']) : $ReqPath;
	}
	elseif( isset($_SERVER['ORIG_PATH_INFO']) )
	{ // Tomcat 5.5.x with Herbelin PHP servlet and PHP 5.1
		$Debuglog->add( 'vars: Getting ReqPath from ORIG_PATH_INFO', 'request' );
		$ReqPath = $_SERVER['ORIG_PATH_INFO'];
		$ReqURI = isset($_SERVER['QUERY_STRING']) && !empty( $_SERVER['QUERY_STRING'] ) ? ($ReqPath.'?'.$_SERVER['QUERY_STRING']) : $ReqPath;
	}
	elseif( isset($_SERVER['SCRIPT_NAME']) )
	{ // CGI 1.1 spec / Some Odd Win2k Stuff
		$Debuglog->add( 'vars: Getting ReqPath from SCRIPT_NAME', 'request' );
		$ReqPath = $_SERVER['SCRIPT_NAME'];
		$ReqURI = isset($_SERVER['QUERY_STRING']) && !empty( $_SERVER['QUERY_STRING'] ) ? ($ReqPath.'?'.$_SERVER['QUERY_STRING']) : $ReqPath;
	}
	elseif( isset($_SERVER['PHP_SELF']) )
	{ // The Old Stand-By
		$Debuglog->add( 'vars: Getting ReqPath from PHP_SELF', 'request' );
		$ReqPath = $_SERVER['PHP_SELF'];
		$ReqURI = isset($_SERVER['QUERY_STRING']) && !empty( $_SERVER['QUERY_STRING'] ) ? ($ReqPath.'?'.$_SERVER['QUERY_STRING']) : $ReqPath;
	}
	else
	{
		$ReqPath = false;
		$ReqURI = false;
		?>
		<p class="error">
		Warning: $ReqPath could not be set. Probably an odd IIS problem.
		</p>
		<p>
		Go to your <a href="<?php echo $baseurl.$install_subdir ?>phpinfo.php">phpinfo page</a>,
		look for occurences of <code><?php
		// take the baseurlroot out..
		echo preg_replace('#^'.$baseurlroot.'#', '', $baseurl.$install_subdir )
		?>phpinfo.php</code> and copy all lines
		containing this to the <a href="http://forums.b2evolution.net">forum</a>. Also specify what webserver
		you're running on.
		<br />
		(If you have deleted your install folder &ndash; what is recommended after successful setup &ndash;
		you have to upload it again before doing this).
		</p>
		<?php
	}

	return array($ReqPath,$ReqURI);
}


/*
 * $Log$
 * Revision 1.251  2011/05/04 17:44:21  sam2kb
 * More checks before forking a shutdown process
 *
 * Revision 1.250  2011/02/23 21:45:18  fplanque
 * minor / cleanup
 *
 * Revision 1.249  2011/02/15 05:31:53  sam2kb
 * evo_strtolower mbstring wrapper for strtolower function
 *
 * Revision 1.248  2011/01/10 02:24:04  sam2kb
 * Check if POSIX functions loaded
 * Fixes http://forums.b2evolution.net/viewtopic.php?t=21893
 *
 * Revision 1.247  2011/01/02 02:20:25  sam2kb
 * typo: explicitely => explicitly
 *
 * Revision 1.246  2010/11/03 19:44:14  sam2kb
 * Increased modularity - files_Module
 * Todo:
 * - split core functions from _file.funcs.php
 * - check mtimport.ctrl.php and wpimport.ctrl.php
 * - do not create demo Photoblog and posts with images (Blog A)
 *
 * Revision 1.245  2010/10/19 13:58:48  efy-asimo
 * antispam in comment text - fix
 *
 * Revision 1.244  2010/10/12 12:38:22  efy-asimo
 * Comment inline antispam - fix
 *
 * Revision 1.243  2010/09/23 14:21:00  efy-asimo
 * antispam in comment text feature
 *
 * Revision 1.242  2010/07/26 06:52:15  efy-asimo
 * MFB v-4-0
 *
 * Revision 1.241  2010/07/08 05:56:17  efy-asimo
 * Unexpected exception fix on item preview
 *
 * Revision 1.240  2010/06/23 22:00:46  blueyed
 * doc
 *
 * Revision 1.239  2010/06/23 19:39:43  blueyed
 * - evo_substr: return "" for length=0 (mb_substr would return string as-is)
 * - maxstrlen: add $cut_at_whitespace param, with tests
 *
 * Revision 1.238  2010/06/19 02:33:45  blueyed
 * debug_info: fix html for tbody and tfoot. doc/todo.
 *
 * Revision 1.237  2010/06/19 02:12:17  blueyed
 * Add jquery.tablesorter and use it for 'Debug info' table.
 *
 * Revision 1.236  2010/06/19 02:06:57  blueyed
 * debug_info: Use content-type to decide if debug info should get output.
 *
 * Revision 1.235  2010/06/17 20:47:27  blueyed
 * get_active_opcode_cache: do not call apc_cache_info, only use setting/info.
 *
 * Revision 1.234  2010/06/17 19:44:32  blueyed
 * Test for apc.enabled before calling apc_cache_info.
 *
 * Revision 1.233  2010/06/08 22:45:19  sam2kb
 * Fixed "Undefined variable: $r" error in get_from_mem_cache()
 *
 * Revision 1.232  2010/06/01 11:33:19  efy-asimo
 * Split blog_comments advanced permission (published, deprecated, draft)
 * Use this new permissions (Antispam tool,when edit/delete comments)
 *
 * Revision 1.231  2010/05/13 15:12:03  blueyed
 * doc
 *
 * Revision 1.230  2010/05/02 19:04:28  fplanque
 * no message
 *
 * Revision 1.229  2010/05/02 00:35:09  blueyed
 * Revert 'Minor optimization for strmaxlen'. Buggy and not worth it.
 *
 * Revision 1.227  2010/05/02 00:09:51  blueyed
 * Do not log timer resuming/pausing with *_mem_cache functions.
 *
 * Revision 1.226  2010/04/22 20:56:11  blueyed
 *  - Move/Refactor BlockCache::cacheproviderstore/cacheproviderretrieve into get_from_mem_cache/set_to_mem_cache
 *  - Add unset_from_mem_cache
 *
 * Revision 1.225  2010/04/22 20:49:39  blueyed
 * debug_info: handle unset host with db_config output. Make output code more compact.
 *
 * Revision 1.224  2010/04/22 20:28:37  blueyed
 * get_active_opcode_cache: fix xcache detection (checking for xcache.size rather than xcache.var_size). Also xcache_set must not be available for this. doc.
 *
 * Revision 1.223  2010/04/22 19:41:25  blueyed
 * doc/cleanup
 *
 * Revision 1.222  2010/04/22 18:55:20  blueyed
 * An attempt to save views during shutdown.
 *
 * Revision 1.221  2010/03/29 20:31:36  blueyed
 * debug_get_backtrace: crop string args after 255 chars (not 65). Fix escaping of args and handling of resource args.
 *
 * Revision 1.220  2010/03/27 19:40:08  blueyed
 * debug_get_backtrace: strstr on resources does not work for PHP 5.3 anymore. Use the arg as-is (untested).
 *
 * Revision 1.219  2010/03/18 21:16:11  blueyed
 * debug_get_backtrace: properly escape function call/args for html.
 *
 * Revision 1.218  2010/03/18 19:30:20  blueyed
 * doc
 *
 * Revision 1.217  2010/03/02 12:37:10  efy-asimo
 * remove show_comments_awaiting_moderation function from _misc_funcs.php to _dashboard.func.php
 *
 * Revision 1.216  2010/02/28 23:38:38  fplanque
 * minor changes
 *
 * Revision 1.215  2010/02/26 22:15:48  fplanque
 * whitespace/doc/minor
 *
 * Revision 1.213  2010/02/26 08:34:33  efy-asimo
 * dashboard -> ban icon should be javascripted task
 *
 * Revision 1.212  2010/02/13 13:42:26  efy-yury
 * move get_antispam_query()
 *
 * Revision 1.211  2010/02/12 18:22:00  efy-yury
 * add atnispam query obfuscating
 *
 * Revision 1.210  2010/02/09 22:23:28  blueyed
 * rtrim in strmaxlen
 *
 * Revision 1.209  2010/02/09 19:22:46  blueyed
 * pre_dump: flush and return true
 *
 * Revision 1.208  2010/02/08 17:51:28  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.207  2010/01/31 17:39:47  efy-asimo
 * delete url from comments in dashboard and comments form
 *
 * Revision 1.206  2010/01/30 18:55:15  blueyed
 * Fix "Assigning the return value of new by reference is deprecated" (PHP 5.3)
 *
 * Revision 1.205  2010/01/12 15:56:05  fplanque
 * crumbs
 *
 * Revision 1.204  2009/12/24 12:33:21  waltercruz
 * Adding Xcache do get_active_opcode_cache
 *
 * Revision 1.203  2009/12/22 08:45:44  fplanque
 * fix install
 *
 * Revision 1.202  2009/12/20 22:12:16  fplanque
 * doc
 *
 * Revision 1.201  2009/12/12 02:00:59  blueyed
 * doc
 *
 * Revision 1.200  2009/12/11 23:18:23  fplanque
 * doc
 *
 * Revision 1.199  2009/12/10 21:01:02  blueyed
 * get_field_attribs_as_string: just use htmlspecialchars to escape. todo.
 *
 * Revision 1.198  2009/12/06 03:24:11  fplanque
 * minor/doc/fixes
 *
 * Revision 1.197  2009/12/06 01:52:54  blueyed
 * Add 'htmlspecialchars' type to format_to_output, same as formvalue, but less irritating. Useful for strmaxlen, which is being used in more places now.
 *
 * Revision 1.196  2009/12/06 01:48:42  blueyed
 * strmaxlen: do not cut in the middle of an HTML entity, if format is not 'raw'
 *
 * Revision 1.195  2009/12/05 01:22:00  fplanque
 * PageChace 304 handling
 *
 * Revision 1.194  2009/12/04 23:27:49  fplanque
 * cleanup Expires: header handling
 *
 * Revision 1.193  2009/12/03 11:38:37  efy-maxim
 * ajax calls have been improved
 *
 * Revision 1.192  2009/12/02 01:00:07  fplanque
 * header_nocache & header_noexpire
 *
 * Revision 1.191  2009/12/01 13:40:32  efy-maxim
 * rename is_login to user_exists function
 *
 * Revision 1.190  2009/12/01 01:52:08  fplanque
 * Fixed issue with Debuglog in case of redirect -- Thanks @blueyed for help.
 *
 * Revision 1.189  2009/12/01 01:32:59  blueyed
 * whitespace/typo
 *
 * Revision 1.188  2009/11/30 04:31:38  fplanque
 * BlockCache Proof Of Concept
 *
 * Revision 1.187  2009/11/30 01:08:27  fplanque
 * extended system optimization checks
 *
 * Revision 1.186  2009/11/30 00:22:04  fplanque
 * clean up debug info
 * show more timers in view of block caching
 *
 * Revision 1.185  2009/11/22 20:29:38  fplanque
 * minor/doc
 *
 * Revision 1.184  2009/11/22 18:54:51  efy-maxim
 * PHP4 compatibility for is_login
 *
 * Revision 1.183  2009/11/22 18:52:19  efy-maxim
 * change owner; is login
 *
 * Revision 1.182  2009/10/28 09:50:02  efy-maxim
 * Module::check_perm
 *
 * Revision 1.181  2009/10/27 21:57:44  fplanque
 * minor/doc
 *
 * Revision 1.180  2009/10/19 21:53:29  blueyed
 * typo
 *
 * Revision 1.179  2009/10/16 19:57:46  blueyed
 * Add evo_bytes function to count actual bytes in a string.
 *
 * Revision 1.178  2009/10/12 22:55:51  blueyed
 * doc
 *
 * Revision 1.177  2009/10/12 21:29:42  blueyed
 * get_field_attribs_as_string: skip empty fields only if NULL, allow empty strings for ALT handling.
 *
 * Revision 1.176  2009/10/11 09:09:03  efy-maxim
 * Check_is functions have been moved to to params.funcs
 *
 * Revision 1.175  2009/10/08 20:05:51  efy-maxim
 * Modular/Pluggable Permissions
 *
 * Revision 1.174  2009/10/04 23:06:30  fplanque
 * doc
 *
 * Revision 1.173  2009/10/04 12:20:21  efy-maxim
 * 1. validate has been renamed to param_validate
 * 2. check recipients list in load_recipients function in Thread class
 *
 * Revision 1.172  2009/10/01 12:17:12  tblue246
 * doc
 *
 * Revision 1.171  2009/09/30 21:30:35  blueyed
 * validate: check if function is callable (not only if it exists). minor, but still.
 *
 * Revision 1.170  2009/09/30 00:38:12  sam2kb
 * Space is not needed before get_field_attribs_as_string()
 *
 * Revision 1.169  2009/09/29 23:38:26  sam2kb
 * doc
 *
 * Revision 1.168  2009/09/29 02:52:20  fplanque
 * doc
 *
 * Revision 1.167  2009/09/28 23:56:22  blueyed
 * doc
 *
 * Revision 1.166  2009/09/27 14:13:25  tblue246
 * minor
 *
 * Revision 1.165  2009/09/27 13:52:22  tblue246
 * minor
 *
 * Revision 1.164  2009/09/27 12:57:29  blueyed
 * strmaxlen: add format param, which is used on the (possibly) cropped string.
 *
 * Revision 1.163  2009/09/26 15:18:12  efy-maxim
 * temporary solution for file/image types
 *
 * Revision 1.162  2009/09/25 20:34:44  tblue246
 * Fixed parse error
 *
 * Revision 1.161  2009/09/25 20:26:26  fplanque
 * fixes/doc
 *
 * Revision 1.160  2009/09/25 14:50:35  efy-maxim
 * validation function is_url
 *
 * Revision 1.159  2009/09/25 14:45:54  tblue246
 * Improved validate()
 *
 * Revision 1.158  2009/09/25 13:43:35  tblue246
 * validate(): debug_die() if validator function does not exist.
 *
 * Revision 1.157  2009/09/24 21:05:39  fplanque
 * no message
 *
 * Revision 1.156  2009/09/24 19:48:30  efy-maxim
 * validators
 *
 * Revision 1.155  2009/09/24 00:32:28  blueyed
 * Add some timers. skin_display is taking too long - obviously.
 *
 * Revision 1.154  2009/09/21 03:14:35  fplanque
 * modularized a little more
 *
 * Revision 1.153  2009/09/18 16:01:50  fplanque
 * cleanup
 *
 * Revision 1.152  2009/09/18 15:47:11  fplanque
 * doc/cleanup
 *
 * Revision 1.151  2009/09/18 14:22:11  efy-maxim
 * 1. 'reply' permission in group form
 * 2. functionality to store and update contacts
 * 3. fix in misc functions
 *
 * Revision 1.150  2009/09/18 10:38:31  efy-maxim
 * 15x15 icons next to login in messagin module
 *
 * Revision 1.149  2009/09/17 16:18:04  tblue246
 * Fixed PCRE error; minor
 *
 * Revision 1.148  2009/09/17 14:42:13  fplanque
 * optimization
 *
 * Revision 1.147  2009/09/17 14:23:32  fplanque
 * fix
 *
 * Revision 1.146  2009/09/17 14:09:45  fplanque
 * tssss
 *
 * Revision 1.145  2009/09/17 03:59:48  efy-cantor
 * copy the get_*cache into each functions
 *
 * Revision 1.144  2009/09/14 18:37:07  fplanque
 * doc/cleanup/minor
 *
 * Revision 1.143  2009/09/14 11:22:18  efy-arrin
 * Included the ClassName in load_class() call with proper UpperCase
 *
 * Revision 1.142  2009/09/13 21:29:21  blueyed
 * MySQL query cache optimization: remove information about seconds from post_datestart and item_issue_date.
 *
 * Revision 1.141  2009/09/13 15:56:13  fplanque
 * minor
 *
 * Revision 1.140  2009/09/13 02:25:03  fplanque
 * prototype for splitting up get_Cache()
 *
 * Revision 1.139  2009/09/13 02:13:51  fplanque
 * minor
 *
 * Revision 1.138  2009/09/12 10:58:46  efy-arrin
 * Included the ClassName in the loadclass() with proper UpperCase
 *
 * Revision 1.137  2009/09/11 18:34:05  fplanque
 * userfields editing module.
 * needs further cleanup but I think it works.
 *
 * Revision 1.136  2009/09/10 12:13:33  efy-maxim
 * Messaging Module
 *
 * Revision 1.135  2009/09/08 08:03:20  efy-maxim
 * 1. Countries order has been fixed in Edit User form; 2. Currency ID validator has been added to Country class, but it can be empty.
 *
 * Revision 1.134  2009/09/07 14:26:45  efy-maxim
 * Country field has been added to User form (but without updater)
 *
 * Revision 1.133  2009/09/07 12:40:56  efy-maxim
 * Ability to select the default currency when editing a country
 *
 * Revision 1.132  2009/09/05 13:43:07  waltercruz
 * minor
 *
 * Revision 1.131  2009/09/03 10:43:37  efy-maxim
 * Countries tab in Global Settings section
 *
 * Revision 1.130  2009/09/02 06:23:59  efy-maxim
 * Currencies Tab in Global Settings
 *
 * Revision 1.129  2009/08/31 16:14:48  fplanque
 * minor
 *
 * Revision 1.128  2009/08/27 13:13:54  tblue246
 * - Doc/todo
 * - Minor bugfix
 *
 * Revision 1.127  2009/08/23 12:58:49  tblue246
 * minor
 *
 * Revision 1.126  2009/08/10 03:45:42  fplanque
 * fixes
 *
 * Revision 1.125  2009/08/06 16:35:57  fplanque
 * no message
 *
 * Revision 1.124  2009/08/01 18:39:24  blueyed
 * Properly handle empty action icon name: get_icon returns false and action_icon uses the word instead.
 *
 * Revision 1.123  2009/07/29 18:52:02  blueyed
 * action_icon: return early for empty icon name.
 *
 * Revision 1.122  2009/07/28 23:04:50  sam2kb
 * Use CRLF line breaks in emails
 * See http://forums.b2evolution.net/viewtopic.php?t=19275
 *
 * Revision 1.121  2009/07/27 19:45:43  blueyed
 * Minor performance improvements to format_to_output
 *
 * Revision 1.120  2009/07/27 19:40:13  blueyed
 * doc
 *
 * Revision 1.119  2009/07/23 21:28:03  blueyed
 * Add $tail param to strmaxlen, use it in stats_search_keywords and add some tests for it.
 *
 * Revision 1.118  2009/07/20 23:12:56  fplanque
 * more power to autolinks plugin
 *
 * Revision 1.117  2009/07/20 02:15:10  fplanque
 * fun with tags, regexps & the autolink plugin
 *
 * Revision 1.116  2009/07/09 22:57:32  fplanque
 * Fixed init of connection_charset, especially during install.
 *
 * Revision 1.115  2009/07/08 02:38:54  sam2kb
 * Replaced strlen & substr with their mbstring wrappers evo_strlen & evo_substr when needed
 *
 * Revision 1.114  2009/07/08 01:42:44  sam2kb
 * mb__ prefix replaced with evo_ in mbstring wrapper functions
 *
 * Revision 1.113  2009/07/06 22:48:29  sam2kb
 * minor
 *
 * Revision 1.112  2009/07/06 22:30:26  fplanque
 * no message
 *
 * Revision 1.111  2009/07/06 22:06:32  blueyed
 * Fixing tests
 *
 * Revision 1.110  2009/07/06 21:32:52  tblue246
 * format_to_js(): Use str_replace() instead of preg_replace() -- better performance
 *
 * Revision 1.109  2009/07/06 21:08:37  blueyed
 * todo
 *
 * Revision 1.108  2009/07/06 16:28:22  sam2kb
 * Added mbstring wrappers for strlen and substr functions.
 * For now altered strmaxlen() function only, if you don't like the implementation feel free to revert.
 *
 * Revision 1.107  2009/07/05 17:44:53  tblue246
 * Minor (single quotes)
 *
 * Revision 1.106  2009/07/05 16:44:14  sam2kb
 * More strict is_email() allows only ASCII symbols
 *
 * Revision 1.105  2009/06/28 19:21:49  tblue246
 * minor/doc
 *
 * Revision 1.104  2009/06/01 11:57:18  tblue246
 * send_javascript_message(): Send correct Content-Type header (set charset)
 *
 * Revision 1.103  2009/05/31 19:45:14  tblue246
 * format_to_js(): Properly translate escape sequences (fixes http://forums.b2evolution.net/viewtopic.php?p=92133)
 *
 * Revision 1.102  2009/05/30 21:11:58  blueyed
 * make_clickable: more tests, doc
 *
 * Revision 1.101  2009/05/30 15:33:51  tblue246
 * make_clickable_callback(): Removed double quotes from first RegExp because it made URLs in tag attributes clickable. Fixes: http://forums.b2evolution.net/viewtopic.php?p=92073
 *
 * Revision 1.100  2009/05/28 22:45:31  blueyed
 * doc
 *
 * Revision 1.99  2009/05/28 22:26:13  blueyed
 * doc
 *
 * Revision 1.98  2009/05/27 13:46:50  tblue246
 * Universal item list: Added option to sort by view (http://forums.b2evolution.net/viewtopic.php?t=18650 )
 *
 * Revision 1.97  2009/05/17 17:40:24  fplanque
 * minor
 *
 * Revision 1.96  2009/05/10 00:30:33  fplanque
 * doc
 *
 * Revision 1.95  2009/04/12 20:57:06  blueyed
 * debug_die: move sending of status-header to not-in-CLI block.
 *
 * Revision 1.94  2009/04/10 13:38:04  blueyed
 * TODOs about usage of 'border="0" align="top" for image tags.
 *
 * Revision 1.93  2009/03/31 01:01:02  waltercruz
 * minor
 *
 * Revision 1.92  2009/03/31 00:55:53  waltercruz
 * We can have some problem with server headers and javascript caching
 *
 * Revision 1.91  2009/03/23 13:22:34  tblue246
 * Simplify zeroise() function
 *
 * Revision 1.90  2009/03/23 13:00:08  tblue246
 * minor
 *
 * Revision 1.89  2009/03/23 12:59:10  tblue246
 * minor
 *
 * Revision 1.88  2009/03/23 04:09:43  fplanque
 * Best. Evobar. Menu. Ever.
 * menu is now extensible by plugins
 *
 * Revision 1.87  2009/03/22 23:39:33  fplanque
 * new evobar Menu structure
 * Superfish jQuery menu library
 * + removed obsolete JS includes
 *
 * Revision 1.86  2009/03/08 23:57:39  fplanque
 * 2009
 *
 * Revision 1.85  2009/03/04 00:14:48  blueyed
 * Remove TEST-RETURN in debug_info, which has slipped in in r1.83
 *
 * Revision 1.84  2009/03/04 00:10:42  blueyed
 * Make Hit constructor more lazy.
 *  - Move referer_dom_ID generation/fetching to own method
 *  - wrap Debuglog additons with "debug"
 *  - Conditionally call detect_useragent, if required. Move
 *    vars to methods for this
 *  - get_user_agent alone does not require detect_useragent
 * Feel free to revert it (since it changed all the is_foo vars
 * to methods - PHP5 would allow to use __get to handle legacy
 * access to those vars however), but please consider also
 * removing this stuff from HTML classnames, since that is kind
 * of disturbing/unreliable by itself).
 *
 * Revision 1.83  2009/03/03 21:32:48  blueyed
 * TODO/doc about cat_load_postcats_cache
 *
 * Revision 1.82  2009/03/03 00:45:51  fplanque
 * dips_cond() actually makes sense as a generally available function
 *
 * Revision 1.81  2009/02/27 21:33:33  blueyed
 * Move load_funcs from class4.funcs to misc.funcs
 *
 * Revision 1.80  2009/02/27 21:29:31  blueyed
 * Move get_Cache from class4.funcs to misc.funcs.
 *
 * Revision 1.79  2009/02/27 00:01:33  blueyed
 * Fix get_base_domain, after IDNA changes - the joy of editing just before committing.. :/
 *
 * Revision 1.78  2009/02/26 23:33:46  blueyed
 * Update IDNA library to 0.6.2 (includes at least a fix for mbstring.func_overload).
 * Since it is PHP5 only, PHP4 won't benefit from it.
 * Add wrapper idna_encode() and idna_decode() to url.funcs to handle loading
 * of the PHP5 or PHP4 class.
 * Move test.
 *
 * Revision 1.77  2009/02/26 22:33:21  blueyed
 * Fix messup in last commit.
 *
 * Revision 1.76  2009/02/26 22:16:53  blueyed
 * Use load_class for classes (.class.php), and load_funcs for funcs (.funcs.php)
 *
 * Revision 1.75  2009/02/26 01:07:10  blueyed
 * Drop unnecessary check in get_field_attribs_as_string, which would better trigger a notice anyway
 *
 * Revision 1.74  2009/02/26 01:03:56  blueyed
 * Cleanup: remove disp_cond() and expand the code where it has been used only (file browser view)
 *
 * Revision 1.73  2009/02/26 00:35:26  blueyed
 * Cleanup: moving modules_call_method where it gets used (only)
 *
 * Revision 1.72  2009/02/25 22:06:18  blueyed
 * Add id="debug_info" to Debug info div, usable as anchor.
 *
 * Revision 1.71  2009/02/19 04:22:45  blueyed
 * Fix for PHP4, as expected.
 *
 * Revision 1.70  2009/02/19 03:54:44  blueyed
 * Optimize: move instantiation of $IconLegend (and $UserSettings query) out of main.inc.php, into get_IconLegend. TODO: test if it works with PHP4, or if it needs assignment by reference. Will do so on the test server.
 *
 * Revision 1.69  2009/02/10 23:37:41  blueyed
 * Add status param to debug_die() and use it for "Forbidden" in getfile.php. This has quite some potential to get reverted, but then debug_die() should not get used there, maybe?!
 *
 * Revision 1.68  2009/02/10 20:51:36  blueyed
 * s/persits/persists/
 *
 * Revision 1.67  2009/02/09 19:13:00  blueyed
 * Fix strmaxwords for corner cases and add tests.
 *
 * Revision 1.66  2009/02/07 11:09:23  yabs
 * adding word cut function
 *
 * Revision 1.65  2009/01/28 00:54:51  blueyed
 * Remove debug code and display 'total' line in Timer table always.
 *
 * Revision 1.64  2009/01/28 00:51:51  blueyed
 * Do not use jQuery for Timer table toggle, which may not be available always.
 *
 * Revision 1.63  2009/01/25 23:01:48  blueyed
 * debug_info: collapse minor Timer table entries, and do display them completely in clean mode always.
 *
 * Revision 1.62  2009/01/23 17:23:09  fplanque
 * doc/minor
 *
 * Revision 1.61  2009/01/22 00:59:00  blueyed
 * minor
 *
 * Revision 1.60  2009/01/21 00:33:46  blueyed
 * Fix indent
 *
 * Revision 1.59  2009/01/19 23:57:33  blueyed
 * fix indent
 *
 * Revision 1.58  2009/01/18 20:18:28  blueyed
 * debug_info(); Output 'total' row at the end always
 *
 * Revision 1.57  2008/12/28 22:48:12  fplanque
 * increase blog name max length to 255 chars
 *
 * Revision 1.56  2008/12/27 21:09:28  fplanque
 * minor
 *
 * Revision 1.55  2008/12/17 22:36:08  blueyed
 * Trans fix: do not translate unexpected errors
 *
 * Revision 1.54  2008/12/05 00:51:04  blueyed
 * Load template funcs in debug_die(), bad_request_die(), so there is no E_FATAL when header_content_type() has not been defined yet\!
 *
 * Revision 1.53  2008/11/07 23:20:09  tblue246
 * debug_info() now supports plain text output for the CLI.
 *
 * Revision 1.52  2008/10/10 14:13:33  blueyed
 * make_clickable(): make it work for not well-formed HTML and improve performance
 * make_clickable_callback(): do not pass $text param by reference
 *
 * Revision 1.51  2008/10/05 03:43:03  fplanque
 * minor
 *
 * Revision 1.50  2008/10/02 21:52:34  blueyed
 * fix unnecessary global and indent
 *
 * Revision 1.49  2008/09/28 08:06:05  fplanque
 * Refactoring / extended page level caching
 *
 * Revision 1.48  2008/09/24 20:15:31  blueyed
 * Since the commented mbstring way in mail_encode_header_string() has been ''rolled back'', I can provide the method that works, too
 *
 * Revision 1.47  2008/09/24 08:44:12  fplanque
 * Fixed and normalized order params for widgets (Comments not done yet)
 *
 * Revision 1.45  2008/09/15 03:11:36  fplanque
 * target control
 *
 * Revision 1.44  2008/09/13 11:07:41  fplanque
 * speed up display of dashboard on first login of the day
 *
 * Revision 1.43  2008/07/24 00:37:34  blueyed
 * Fix get_base_domain() for numerical domains (IPs). Would get handled as regular domain (and limited to 3 segments therefore).
 *
 * Revision 1.42  2008/07/04 06:23:31  yabs
 * minor bug fix
 *
 * Revision 1.41  2008/07/04 05:56:11  yabs
 * minor bug fix
 *
 * Revision 1.40  2008/07/03 09:51:51  yabs
 * widget UI
 *
 * Revision 1.39  2008/07/01 20:22:23  blueyed
 * Fix get_base_domain for IDN (umlaut) domains.
 *
 * Revision 1.38  2008/06/23 21:55:38  blueyed
 * Add newlines/whitespace before debug_info output; fix indent
 *
 * Revision 1.37  2008/05/10 21:30:38  fplanque
 * better UTF-8 handling
 *
 * Revision 1.36  2008/04/24 01:56:07  fplanque
 * Goal hit summary
 *
 * Revision 1.35  2008/04/17 11:50:21  fplanque
 * I feel stupid :P
 *
 * Revision 1.34  2008/04/16 13:59:47  fplanque
 * oops
 *
 * Revision 1.33  2008/04/16 13:47:55  fplanque
 * better encoding of emails
 *
 * Revision 1.32  2008/04/13 15:15:59  fplanque
 * attempt to fix email headers for non latin charsets
 *
 * Revision 1.31  2008/04/06 19:19:29  fplanque
 * Started moving some intelligence to the Modules.
 * 1) Moved menu structure out of the AdminUI class.
 * It is part of the app structure, not the UI. Up to this point at least.
 * Note: individual Admin skins can still override the whole menu.
 * 2) Moved DB schema to the modules. This will be reused outside
 * of install for integrity checks and backup.
 * 3) cleaned up config files
 *
 * Revision 1.30  2008/04/04 16:02:13  fplanque
 * uncool feature about limiting credits
 *
 * Revision 1.29  2008/03/31 21:13:47  fplanque
 * Reverted übergeekyness
 *
 * Revision 1.28  2008/03/30 23:03:40  blueyed
 * action_icon: doc, provide default for $icon_weight and $word_weight through "null"
 *
 * Revision 1.27  2008/03/30 14:56:50  fplanque
 * DST fix
 *
 * Revision 1.26  2008/03/24 03:10:12  blueyed
 * - shutdown(): update $Session at the very end
 * - debug_info(): Test if $Hit is defined
 *
 * Revision 1.25  2008/03/21 10:25:09  yabs
 * modified autobr to respect code blocks
 *
 * Revision 1.24  2008/03/16 14:19:38  fplanque
 * no message
 *
 * Revision 1.22  2008/03/06 22:41:19  blueyed
 * MFB: doc
 *
 * Revision 1.21  2008/02/19 11:11:17  fplanque
 * no message
 *
 * Revision 1.20  2008/01/21 09:35:23  fplanque
 * (c) 2008
 *
 * Revision 1.19  2008/01/18 15:53:42  fplanque
 * Ninja refactoring
 *
 * Revision 1.18  2008/01/12 02:13:44  fplanque
 * XML-RPC debugging
 *
 * Revision 1.17  2008/01/12 00:53:27  fplanque
 * fix tests
 *
 * Revision 1.16  2008/01/05 02:25:23  fplanque
 * refact
 *
 * Revision 1.15  2007/12/29 18:55:32  fplanque
 * better antispam banning screen
 *
 * Revision 1.14  2007/12/23 19:43:58  fplanque
 * trans fat reduction :p
 *
 * Revision 1.13  2007/11/28 16:38:21  fplanque
 * minor
 *
 * Revision 1.12  2007/11/23 14:54:31  fplanque
 * no message
 *
 * Revision 1.11  2007/11/22 22:53:14  blueyed
 * get_icon_info(): relative to $rsc_url/$rsc_path (instead of $rsc_subdir)
 *
 * Revision 1.10  2007/11/22 13:24:46  fplanque
 * no message
 *
 * Revision 1.9  2007/11/22 12:16:47  blueyed
 * format_to_output(): use ENT_QUOTES for htmlspecialchars (format=formvalue)
 *
 * Revision 1.8  2007/11/08 17:46:45  blueyed
 * doc
 *
 * Revision 1.7  2007/11/03 21:04:25  fplanque
 * skin cleanup
 *
 * Revision 1.6  2007/10/25 18:29:41  blueyed
 * PasteFromBranch: Fixed url_absolute for '//foo/bar'
 *
 * Revision 1.5  2007/09/22 19:23:56  fplanque
 * various fixes & enhancements
 *
 * Revision 1.4  2007/09/12 21:00:30  fplanque
 * UI improvements
 *
 * Revision 1.3  2007/09/08 18:38:08  fplanque
 * MFB
 *
 * Revision 1.2  2007/09/04 14:56:20  fplanque
 * antispam cleanup
 *
 * Revision 1.1  2007/06/25 10:58:52  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.180  2007/06/19 23:15:08  blueyed
 * doc fixes
 *
 * Revision 1.179  2007/06/16 19:54:39  blueyed
 * doc/(-fixes)
 *
 * Revision 1.178  2007/06/05 17:00:02  blueyed
 * MFB v-1-10: Consistent logging of HTTP_REFERER/REQUEST_URI; fixed possible E_NOTICE
 *
 * Revision 1.177  2007/05/23 23:07:53  blueyed
 * Display DB info (user, database, host, tableprefix, charset) in debug_info()
 *
 * Revision 1.176  2007/05/13 20:49:34  fplanque
 * removed hack that did inconsistent action upon the HTML and the Security checkers.
 *
 * Revision 1.175  2007/05/13 18:31:23  blueyed
 * Trim special date param replacements in date_i18n(). Fixes http://forums.b2evolution.net/viewtopic.php?p=55213#55213.
 *
 * Revision 1.174  2007/05/12 10:13:25  yabs
 * secuirty checker uses setting for allowing id/style in comments
 * amended get_icon to respect $use_strict
 *
 * Revision 1.173  2007/04/26 00:11:08  fplanque
 * (c) 2007
 *
 * Revision 1.172  2007/04/25 18:47:42  fplanque
 * MFB 1.10: groovy links
 *
 * Revision 1.171  2007/04/19 20:36:42  blueyed
 * Fixed possible E_NOTICE with REQUEST_URI/cron
 *
 * Revision 1.170  2007/02/16 11:23:02  blueyed
 * No limits for xdebug_var_dump() in pre_dump()
 *
 * Revision 1.169  2007/02/11 02:10:35  blueyed
 * Minor improvements to debug_get_backtrace()
 *
 * Revision 1.168  2007/02/06 13:47:25  blueyed
 * Fixed escaping in get_link_showhide(); added jsspecialchars(); see http://forums.b2evolution.net/viewtopic.php?p=50564
 *
 * Revision 1.167  2007/01/29 09:58:55  fplanque
 * enhanced toolbar - experimental
 *
 * Revision 1.166  2007/01/29 09:24:41  fplanque
 * icon stuff
 *
 * Revision 1.165  2007/01/26 20:43:03  blueyed
 * Fixed exp. app error logging
 *
 * Revision 1.164  2007/01/26 04:52:53  fplanque
 * clean comment popups (skins 2.0)
 *
 * Revision 1.163  2007/01/24 01:32:30  fplanque
 * 'zip & css' is more readable than 'zip and css'
 *
 * Revision 1.162  2007/01/23 22:23:04  fplanque
 * FIXED (!!!) disappearing help window!
 *
 * Revision 1.161  2007/01/23 21:44:43  fplanque
 * handle generic "empty"/noimg icons
 *
 * Revision 1.160  2007/01/23 05:30:21  fplanque
 * "Contact the owner"
 *
 * Revision 1.159  2007/01/20 00:38:19  blueyed
 * Added Debulog entry in header_redirect()
 *
 * Revision 1.158  2007/01/19 03:06:57  fplanque
 * Changed many little thinsg in the login procedure.
 * There may be new bugs, sorry. I tested this for several hours though.
 * More refactoring to be done.
 *
 * Revision 1.157  2007/01/14 05:41:10  blueyed
 * Send correct charset with bad_request_die()
 */
?>
