<?php
/**
 * This file implements the basic Antispam plugin.
 *
 * This file is part of the b2evolution project - {@link http://b2evolution.net/}
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}
 * Parts of this file are copyright (c)2004-2006 by Daniel HAHLER - {@link http://thequod.de/contact}.
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
 * }}
 *
 * @package plugins
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author blueyed: Daniel HAHLER - {@link http://daniel.hahler.de/}
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * Basic Antispam Plugin
 *
 * This plugin doublechecks referers/referrers for Hit logging and trackbacks.
 *
 * @todo Ideas:
 *  - forbid cloned comments (same content) (on the same entry or all entries)
 *  - detect same/similar URLs in a short period (also look at author name: if it differs, it's more likely to be spam)
 */
class basic_antispam_plugin extends Plugin
{
	/**
	 * Variables below MUST be overriden by plugin implementations,
	 * either in the subclass declaration or in the subclass constructor.
	 */
	var $name = 'Basic Antispam';
	var $code = '';
	var $priority = 60;
	var $version = '2.1';
	var $author = 'The b2evo Group';
	var $group = 'antispam';
	var $number_of_installs = 1;


	/**
	 * Init
	 */
	function PluginInit( & $params )
	{
		$this->short_desc = T_('Basic antispam methods');
		$this->long_desc = T_('This plugin provides basic methods to detect & block spam on referers, comments & trackbacks.');
	}


	function GetDefaultSettings()
	{
		return array(
				'allow_anon_comments' => array(
					'type' => 'checkbox',
					'label' => T_('Allow anonymous comments'),
					'note' => T_('Allow non-registered visitors to leave comments.'),
					'defaultvalue' => '1',
				),
				'no_anon_url' => array(
					'type' => 'checkbox',
					'label' => T_('Disable anonymous URLs'),
					'note' => T_('Disable URLs from non-registered visitors.'),
					'defaultvalue' => 0,
				),
				'check_dupes' => array(
					'type' => 'checkbox',
					'label' => T_('Detect feedback duplicates'),
					'note' => T_('Check this to check comments and trackback for duplicate content.'),
					'defaultvalue' => '1',
				),
				'max_number_of_links_feedback' => array(
					'type' => 'integer',
					'label' => T_('Feedback sensitivity to links'),
					'note' => T_('If a comment has more than this number of links in it, it will get 100 percent spam karma. -1 to disable it.'),
					'help' => '#set_max_number_of_links',
					'defaultvalue' => '4',
					'size' => 3,
				),
				'trim_whitespace' => array(
					'type' => 'checkbox',
					'label' => T_('Strip whitespace'),
					'note' => T_('Strip whitespace from the beginning and end of comment content.'),
					'defaultvalue' => 1,
				),
				'remove_repetitions' => array(
					'type' => 'checkbox',
					'label' => T_('Remove repetitive characters'),
					'note'=>T_('Remove repetitive characters in name and content. The string like "Thaaaaaaaaaanks!" becomes "Thaaanks!".'),
					'defaultvalue' => 0,
				),
				'nofollow_for_hours' => array(
					'type' => 'integer',
					'label' => T_('Apply rel="nofollow"'),
					'note'=>T_('hours. For how long should rel="nofollow" be applied to comment links? (0 means never, -1 means always)'),
					'defaultvalue' => '-1', // use "nofollow" infinitely by default so lazy admins won't promote spam
					'size' => 5,
				),
				'check_url_referers' => array(
					'type' => 'checkbox',
					'label' => T_('Check referers for URL'),
					'note' => T_('Check refering pages, if they contain our URL. This may generate a lot of additional traffic!'),
					'defaultvalue' => '0',
				),

			);
	}


	/**
	 * We check if this is an anonymous visitor and do not allow comments, if we're setup
	 * to do so.
	 */
	function ItemCanComment( & $params )
	{
		if( ! is_logged_in() && ! $this->Settings->get('allow_anon_comments') )
		{
			return T_('Comments are not allowed from anonymous visitors.');
		}

		// return NULL
	}


	/**
	 * Handle max_number_of_links_feedback setting.
	 *
	 * Try to detect as many links as possible
	 */
	function GetSpamKarmaForComment( & $params )
	{
		$max_comments = $this->Settings->get('max_number_of_links_feedback');
		if( $max_comments != -1 )
		{ // not deactivated:
			$count = preg_match_all( '~(https?|ftp)://~i', $params['Comment']->content, $matches );

			if( $count > $max_comments )
			{
				return 100;
			}

			if( $count == 0 )
			{
				return 0;
			}

			return (100/$max_comments) * $count;
		}
	}


	/**
	 * Disable/Enable events according to settings.
	 *
	 * "AppendHitLog" gets enabled according to check_url_referers setting.
	 * "BeforeTrackbackInsert" gets disabled, if we do not check for duplicate content.
	 */
	function BeforeEnable()
	{
		if( $this->Settings->get('check_url_referers') )
		{
			$this->enable_event( 'AppendHitLog' );
		}
		else
		{
			$this->disable_event( 'AppendHitLog' );
		}

		if( ! $this->Settings->get('check_dupes') )
		{
			$this->disable_event( 'BeforeTrackbackInsert' );
		}
		else
		{
			$this->enable_event( 'BeforeTrackbackInsert' );
		}

		return true;
	}


	/**
	 * - Check for duplicate trackbacks.
	 */
	function BeforeTrackbackInsert( & $params )
	{
		if( $this->is_duplicate_comment( $params['Comment'] ) )
		{
			$this->msg( T_('The trackback seems to be a duplicate.'), 'error' );
		}
	}


	function CommentFormSent( & $params )
	{
		if( $this->Settings->get('trim_whitespace') )
		{	// Strip whitespace
			$params['comment'] = trim( $params['comment'] );
		}

		if( $this->Settings->get('remove_repetitions') )
		{	// Remove repetitions
			$params['anon_name'] = $this->remove_repetition( $params['anon_name'] );
			$params['comment'] = $this->remove_repetition( $params['comment'] );
		}

		if( $this->Settings->get('no_anon_url') )
		{	// Remove URL
			$params['anon_url'] = '';
		}
	}


	/**
	 * Check for duplicate comments.
	 */
	function BeforeCommentFormInsert( & $params )
	{
		if( $this->is_duplicate_comment( $params['Comment'] ) )
		{
			$this->msg( T_('The comment seems to be a duplicate.'), 'error' );
		}
	}


	/**
	 * If we use "makelink", handle nofollow rel attrib.
	 *
	 * @uses basic_antispam_plugin::apply_nofollow()
	 */
	function FilterCommentAuthor( & $params )
	{
		if( ! isset($params['makelink']) )
		{
			return false;
		}

		if( $this->Settings->get('no_anon_url') && !isset($params['Comment']->author_user_ID) )
		{	// Remove anonymous URL
			$params['data'] = preg_replace( '~<a\s[^>]+>~i', '', $params['data'] );
			
			return;
		}
		
		$this->apply_nofollow( $params['data'], $params['Comment'] );
	}


	/**
	 * Handle nofollow in author URL (if it's made clickable)
	 *
	 * @uses basic_antispam_plugin::FilterCommentAuthor()
	 */
	function FilterCommentAuthorUrl( & $params )
	{
		$this->FilterCommentAuthor( $params );
	}


	/**
	 * Handle nofollow rel attrib in comment content.
	 *
	 * @uses basic_antispam_plugin::FilterCommentAuthor()
	 */
	function FilterCommentContent( & $params )
	{
		$this->apply_nofollow( $params['data'], $params['Comment'] );
	}


	/**
	 * Do we want to apply rel="nofollow" tag?
	 *
	 * @return boolean
	 */
	function apply_nofollow( & $data, $Comment )
	{
		global $localtimenow;

		$hours = $this->Settings->get('nofollow_for_hours'); // 0=never, -1 always, otherwise for x hours

		if( $hours == 0 )
		{ // "never"
			return;
		}

		if( $hours > 0 // -1 is "always"
			&& mysql2timestamp( $Comment->date ) <= ( $localtimenow - $hours*3600 ) )
		{
			return;
		}

		$data = preg_replace_callback( '~(<a\s)([^>]+)>~i', create_function( '$m', '
				if( preg_match( \'~\brel=([\\\'"])(.*?)\1~\', $m[2], $match ) )
				{ // there is already a rel attrib:
					$rel_values = explode( " ", $match[2] );

					if( ! in_array( \'nofollow\', $rel_values ) )
					{
						$rel_values[] = \'nofollow\';
					}

					return $m[1]
						.preg_replace(
							\'~\brel=([\\\'"]).*?\1~\',
							\'rel=$1\'.implode( " ", $rel_values ).\'$1\',
							$m[2] )
						.">";
				}
				else
				{
					return $m[1].$m[2].\' rel="nofollow">\';
				}' ), $data );
	}


	function remove_repetition( $str = '' )
	{
		return @preg_replace( '~(.)\\1{3,}~iu', '$1$1$1', $str );
	}


	/**
	 * Check if the deprecated hit_doublecheck_referer setting is set and then
	 * do not disable the AppendHitLog event. Also removes the old setting.
	 */
	function AfterInstall()
	{
		global $Settings;

		if( $Settings->get('hit_doublecheck_referer') )
		{ // old general settings, "transform it"
			$this->Settings->set( 'check_url_referers', '1' );
			$this->Settings->dbupdate();
		}

		$Settings->delete('hit_doublecheck_referer');
		$Settings->dbupdate();
	}


	/**
	 * Check if our Host+URI is in the referred page, preferrably through
	 * {@link register_shutdown_function()}.
	 *
	 * @return boolean true, if we handle {@link Hit::record_the_hit() recording of the Hit} ourself
	 */
	function AppendHitLog( & $params )
	{
		$Hit = & $params['Hit'];

		if( $Hit->referer_type != 'referer' )
		{
			return false;
		}

		if( function_exists( 'register_shutdown_function' ) )
		{ // register it as a shutdown function, because it will be slow!
			$this->debug_log( 'AppendHitLog: loading referering page.. (through register_shutdown_function())' );

			register_shutdown_function( array( &$this, 'double_check_referer' ), $Hit->referer ); // this will also call Hit::record_the_hit()
		}
		else
		{
			// flush now, so that the meat of the page will get shown before it tries to check back against the refering URL.
			flush();

			$this->debug_log( 'AppendHitLog: loading referering page..' );

			$this->double_check_referer($Hit->referer); // this will also call Hit::record_the_hit()
		}

		return true; // we handle recording
	}


	/**
	 * This function gets called (as a {@link register_shutdown_function() shutdown function}, if possible) and checks
	 * if the referering URL's content includes the current URL - if not it is probably spam!
	 *
	 * On success, this methods records the hit.
	 *
	 * @uses Hit::record_the_hit()
	 */
	function double_check_referer( $referer )
	{
		global $Hit, $ReqURI;

		if( $this->is_referer_linking_us( $referer, $ReqURI ) )
		{
			$Hit->record_the_hit();
		}

		return;
	}


	/**
	 * Check the content of a given URL (referer), if the requested URI (with different hostname variations)
	 * is present.
	 *
	 * @todo Use DB cache to avoid checking the same page again and again! (Plugin DB table)
	 *
	 * @param string
	 * @param string URI to append to matching pattern for hostnames
	 * @return boolean
	 */
	function is_referer_linking_us( $referer, $uri )
	{
		global $misc_inc_path, $lib_subdir, $ReqHost;

		if( empty($referer) )
		{
			return false;
		}

		// Load page content (max. 500kb), using fsockopen:
		$url_parsed = @parse_url($referer);
		if( ! $url_parsed )
		{
			return false;
		}
		if( empty($url_parsed['scheme']) ) {
			$url_parsed = parse_url('http://'.$referer);
		}

		$host = $url_parsed['host'];
		$port = ( empty($url_parsed['port']) ? 80 : $url_parsed['port'] );
		$path = empty($url_parsed['path']) ? '/' : $url_parsed['path'];
		if( ! empty($url_parsed['query']) )
		{
			$path .= '?'.$url_parsed['query'];
		}

		$fp = @fsockopen($host, $port, $errno, $errstr, 30);
		if( ! $fp )
		{ // could not access referring page
			$this->debug_log( 'is_referer_linking_us(): could not access &laquo;'.$referer.'&raquo; (host: '.$host.'): '.$errstr.' (#'.$errno.')' );
			return false;
		}

		// Set timeout for data:
		if( function_exists('stream_set_timeout') )
			stream_set_timeout( $fp, 20 ); // PHP 4.3.0
		else
			socket_set_timeout( $fp, 20 ); // PHP 4

		// Send request:
		$out = "GET $path HTTP/1.0\r\n";
		$out .= "Host: $host:$port\r\n";
		$out .= "Connection: Close\r\n\r\n";
		fwrite($fp, $out);

		// Skip headers:
		$i = 0;
		$source_charset = 'iso-8859-1'; // default
		while( ($s = fgets($fp, 4096)) !== false )
		{
			$i++;
			if( $s == "\r\n" || $i > 100 /* max 100 head lines */ )
			{
				break;
			}
			if( preg_match('~^Content-Type:.*?charset=([\w-]+)~i', $s, $match ) )
			{
				$source_charset = $match[1];
			}
		}

		// Get the refering page's content
		$content_ref_page = '';
		$bytes_read = 0;
		while( ($s = fgets($fp, 4096)) !== false )
		{
			$content_ref_page .= $s;
			$bytes_read += strlen($s);
			if( $bytes_read > 512000 )
			{ // do not pull more than 500kb of data!
				break;
			}
		}
		fclose($fp);

		if( ! strlen($content_ref_page) )
		{
			$this->debug_log( 'is_referer_linking_us(): empty $content_ref_page ('.bytesreadable($bytes_read).' read)' );
			return false;
		}


		$have_idn_name = false;

		// Build the search pattern:
		// We match for basically for 'href="[SERVER][URI]', where [SERVER] is a list of possible hosts (especially IDNA)
		$search_pattern = '~\shref=["\']?https?://(';
		$possible_hosts = array( $_SERVER['HTTP_HOST'] );
		if( $_SERVER['SERVER_NAME'] != $_SERVER['HTTP_HOST'] )
		{
			$possible_hosts[] = $_SERVER['SERVER_NAME'];
		}
		$search_pattern_hosts = array();
		foreach( $possible_hosts as $l_host )
		{
			if( preg_match( '~^([^.]+\.)(.*?)([^.]+\.[^.]+)$~', $l_host, $match ) )
			{ // we have subdomains in this hostname
				if( stristr( $match[1], 'www' ) )
				{ // search also for hostname without 'www.'
					$search_pattern_hosts[] = $match[2].$match[3];
				}
			}
			$search_pattern_hosts[] = $l_host;
		}
		$search_pattern_hosts = array_unique($search_pattern_hosts);
		foreach( $search_pattern_hosts as $l_host )
		{ // add IDN, because this could be linked:
			$l_idn_host = idna_decode( $l_host ); // the decoded puny-code ("xn--..") name (utf8)

			if( $l_idn_host != $l_host )
			{
				$have_idn_name = true;
				$search_pattern_hosts[] = $l_idn_host;
			}
		}

		// add hosts to pattern, preg_quoted
		for( $i = 0, $n = count($search_pattern_hosts); $i < $n; $i++ )
		{
			$search_pattern_hosts[$i] = preg_quote( $search_pattern_hosts[$i], '~' );
		}
		$search_pattern .= implode( '|', $search_pattern_hosts ).')';
		if( empty($uri) )
		{ // host(s) should end with "/", "'", '"', "?" or whitespace
			$search_pattern .= '[/"\'\s?]';
		}
		else
		{
			$search_pattern .= preg_quote($uri, '~');
			// URI should end with "'", '"' or whitespace
			$search_pattern .= '["\'\s]';
		}
		$search_pattern .= '~i';

		if( $have_idn_name )
		{ // Convert charset to UTF-8, because the decoded domain name is UTF-8, too:
			if( can_convert_charsets( 'utf-8', $source_charset ) )
			{
				$content_ref_page = convert_charset( $content_ref_page, 'utf-8', $source_charset );
			}
			else
			{
				$this->debug_log( 'is_referer_linking_us(): warning: cannot convert charset of referring page' );
			}
		}

		if( preg_match( $search_pattern, $content_ref_page ) )
		{
			$this->debug_log( 'is_referer_linking_us(): found current URL in page ('.bytesreadable($bytes_read).' read)' );

			return true;
		}
		else
		{
			if( strpos( $referer, $ReqHost ) === 0 && ! empty($uri) )
			{ // Referer is the same host.. just search for $uri
				if( strpos( $content_ref_page, $uri ) !== false )
				{
					$this->debug_log( 'is_referer_linking_us(): found current URI in page ('.bytesreadable($bytes_read).' read)' );

					return true;
				}
			}
			$this->debug_log( 'is_referer_linking_us(): '.sprintf('did not find &laquo;%s&raquo; in &laquo;%s&raquo; (%s bytes read).', $search_pattern, $referer, bytesreadable($bytes_read) ) );

			return false;
		}
	}


	/**
	 * Simple check for duplicate comment/content from same author
	 *
	 * @param Comment
	 */
	function is_duplicate_comment( $Comment )
	{
		global $DB;

		if( ! $this->Settings->get('check_dupes') )
		{
			return false;
		}

		$sql = '
				SELECT comment_ID
				  FROM T_comments
				 WHERE comment_post_ID = '.$Comment->item_ID;

		if( isset($Comment->author_user_ID) )
		{ // registered user:
			$sql .= ' AND comment_author_ID = '.$Comment->author_user_ID;
		}
		else
		{ // visitor (also trackback):
			$sql_ors = array();
			if( ! empty($Comment->author) )
			{
				$sql_ors[] = 'comment_author = '.$DB->quote($Comment->author);
			}
			if( ! empty($Comment->author_email) )
			{
				$sql_ors[] = 'comment_author_email = '.$DB->quote($Comment->author_email);
			}
			if( ! empty($Comment->author_url) )
			{
				$sql_ors[] = 'comment_author_url = '.$DB->quote($Comment->author_url);
			}

			if( ! empty($sql_ors) )
			{
				$sql .= ' AND ( '.implode( ' OR ', $sql_ors ).' )';
			}
		}

		$sql .= ' AND comment_content = '.$DB->quote($Comment->content).' LIMIT 1';

		return $DB->get_var( $sql, 0, 0, 'Checking for duplicate feedback content.' );
	}


	/**
	 * A little housekeeping.
	 * @return true
	 */
	function PluginVersionChanged( & $params )
	{
		$this->Settings->delete('check_url_trackbacks');
		$this->Settings->dbupdate();
		return true;
	}

}


/*
 * $Log$
 * Revision 1.39  2010/12/10 21:03:29  sam2kb
 * Version bump
 *
 * Revision 1.38  2010/12/10 21:00:39  sam2kb
 * More antispam options
 *
 * Revision 1.37  2010/02/08 17:56:01  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.36  2009/03/08 23:57:49  fplanque
 * 2009
 *
 * Revision 1.35  2009/02/26 23:33:46  blueyed
 * Update IDNA library to 0.6.2 (includes at least a fix for mbstring.func_overload).
 * Since it is PHP5 only, PHP4 won't benefit from it.
 * Add wrapper idna_encode() and idna_decode() to url.funcs to handle loading
 * of the PHP5 or PHP4 class.
 * Move test.
 *
 * Revision 1.34  2009/02/26 22:16:54  blueyed
 * Use load_class for classes (.class.php), and load_funcs for funcs (.funcs.php)
 *
 * Revision 1.33  2008/09/13 10:22:59  fplanque
 * removed superfluous conf variable
 *
 * Revision 1.32  2008/05/03 23:58:41  blueyed
 * basic_antispam_plugin: is_referer_linking_us(): make parse_url silent and return false in case of error
 *
 * Revision 1.31  2008/01/21 09:35:41  fplanque
 * (c) 2008
 *
 * Revision 1.30  2007/06/25 11:02:32  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.29  2007/04/26 00:11:05  fplanque
 * (c) 2007
 *
 * Revision 1.28  2007/04/20 02:53:13  fplanque
 * limited number of installs
 *
 * Revision 1.27  2007/01/30 19:55:04  blueyed
 * Return explictly true in PluginVersionChanged
 *
 * Revision 1.26  2006/12/26 03:19:12  fplanque
 * assigned a few significant plugin groups
 *
 * Revision 1.25  2006/12/21 16:14:25  blueyed
 * Basic Antispam Plugin:
 * - Use fsockopen instead of url fopen to get refering page contents
 * - Removed "check_url_trackbacks" setting: it has been unreliable and is against the trackback specs anyway. This is what pingbacks are for.
 * - Convert charset of the refering page contents, if we have a decoded/utf-8 encoded IDN
 * - Some improvements to matching pattern
 *
 * Revision 1.24  2006/11/24 18:27:27  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.23  2006/07/10 20:19:31  blueyed
 * Fixed PluginInit behaviour. It now gets called on both installed and non-installed Plugins, but with the "is_installed" param appropriately set.
 *
 * Revision 1.22  2006/07/07 21:26:49  blueyed
 * Bumped to 1.9-dev
 *
 * Revision 1.21  2006/07/07 19:28:32  blueyed
 * Trans fix. "%" would need to be escaped.. :/
 *
 * Revision 1.20  2006/06/22 19:47:06  blueyed
 * "Block spam referers" as global option
 *
 * Revision 1.19  2006/06/16 21:30:57  fplanque
 * Started clean numbering of plugin versions (feel free do add dots...)
 *
 * Revision 1.18  2006/06/05 17:45:06  blueyed
 * Disable events at settings time, according to Settings checkboxes.
 *
 * Revision 1.17  2006/06/01 18:36:10  fplanque
 * no message
 *
 * Revision 1.16  2006/05/30 21:25:27  blueyed
 * todo-question
 *
 * Revision 1.15  2006/05/30 20:32:57  blueyed
 * Lazy-instantiate "expensive" properties of Comment and Item.
 *
 * Revision 1.14  2006/05/30 19:39:56  fplanque
 * plugin cleanup
 *
 * Revision 1.13  2006/05/30 00:18:29  blueyed
 * http://dev.b2evolution.net/todo.php?p=87686
 *
 * Revision 1.12  2006/05/29 21:13:19  fplanque
 * no message
 *
 * Revision 1.11  2006/05/29 21:03:07  fplanque
 * Also count links if < tags have been filtered before!
 *
 * Revision 1.10  2006/05/20 01:56:07  blueyed
 * ItemCanComment hook; "disable anonymous feedback" through basic antispam plugin
 *
 * Revision 1.9  2006/05/14 16:30:37  blueyed
 * SQL error fixed with empty visitor comments
 *
 * Revision 1.8  2006/05/12 21:35:24  blueyed
 * Apply karma by number of links in a comment. Note: currently the default is to not allow A tags in comments!
 *
 * Revision 1.7  2006/05/02 22:43:39  blueyed
 * typo
 *
 * Revision 1.6  2006/05/02 15:32:01  blueyed
 * Moved blocking of "spam referers" into basic antispam plugin: does not block backoffice requests in general and can be easily get disabled.
 *
 * Revision 1.5  2006/05/02 04:36:25  blueyed
 * Spam karma changed (-100..100 instead of abs/max); Spam weight for plugins; publish/delete threshold
 *
 * Revision 1.4  2006/05/02 01:27:55  blueyed
 * Moved nofollow handling to basic antispam plugin; added Filter events to Comment class
 *
 * Revision 1.3  2006/05/01 05:20:38  blueyed
 * Check for duplicate content in comments/trackback.
 *
 * Revision 1.2  2006/05/01 04:25:07  blueyed
 * Normalization
 *
 * Revision 1.1  2006/04/29 23:11:23  blueyed
 * Added basic_antispam_plugin; Moved double-check-referers there; added check, if trackback links to us
 *
 */
?>
