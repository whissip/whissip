<?php
/**
 * URL manipulation functions
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}.
 * Parts of this file are copyright (c)2006 by Daniel HAHLER - {@link http://daniel.hahler.de/}.
 *
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
 *
 * {@internal Open Source relicensing agreement:
 * Daniel HAHLER grants Francois PLANQUE the right to license
 * Daniel HAHLER's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package evocore
 *
 * @author blueyed: Daniel HAHLER
 * @author Danny Ferguson
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * Check the validity of a given URL
 *
 * Checks allowed URI schemes and URL ban list.
 * URL can be empty.
 *
 * Note: We have a problem when trying to "antispam" a keyword which is already blacklisted
 * If that keyword appears in the URL... then the next page has a bad referer! :/
 *
 * {@internal This function gets tested in misc.funcs.simpletest.php.}}
 *
 * @param string Url to validate
 * @param string Context ("posting", "commenting")
 * @param boolean also do an antispam check on the url
 * @return mixed false (which means OK) or error message
 */
function validate_url( $url, $context = 'posting', $antispam_check = true )
{
	global $Debuglog, $debug;

	if( empty($url) )
	{ // Empty URL, no problem
		return false;
	}

	$verbose = $debug || $context != 'commenting';

	$allowed_uri_schemes = get_allowed_uri_schemes( $context );

	// Validate URL structure
	if( $url[0] == '$' )
	{	// This is a 'special replace code' URL (used in footers)
 		if( ! preg_match( '¤\$([a-z_]+)\$¤', $url ) )
		{
			return T_('Invalid URL $code$ format');
		}
	}
	elseif( preg_match( '~^\w+:~', $url ) )
	{ // there's a scheme and therefor an absolute URL:
		if( substr($url, 0, 7) == 'mailto:' )
		{ // mailto:link
			if( ! in_array( 'mailto', $allowed_uri_schemes ) )
			{ // Scheme not allowed
				$scheme = 'mailto:';
				$Debuglog->add( 'URI scheme &laquo;'.$scheme.'&raquo; not allowed!', 'error' );
				return $verbose
					? sprintf( T_('URI scheme "%s" not allowed.'), htmlspecialchars($scheme) )
					: T_('URI scheme not allowed.');
			}

			preg_match( '~^(mailto):(.*?)(\?.*)?$~', $url, $match );
			if( ! $match )
			{
				return $verbose
					? sprintf( T_('Invalid email link: %s.'), htmlspecialchars($url) )
					: T_('Invalid email link.');
			}
      elseif( ! is_email($match[2]) )
			{
				return $verbose
					? sprintf( T_('Supplied email address (%s) is invalid.'), htmlspecialchars($match[2]) )
					: T_('Invalid email address.');
			}
		}
		elseif( substr($url, 0, 6) == 'clsid:' )
		{ // clsid:link
			if( ! in_array( 'clsid', $allowed_uri_schemes ) )
			{ // Scheme not allowed
				$scheme = 'clsid:';
				$Debuglog->add( 'URI scheme &laquo;'.$scheme.'&raquo; not allowed!', 'error' );
				return $verbose
					? sprintf( T_('URI scheme "%s" not allowed.'), htmlspecialchars($scheme) )
					: T_('URI scheme not allowed.');
			}

			if( ! preg_match( '¤^(clsid):([a-fA-F0-9\-]+)$¤', $url, $match) )
			{
				return T_('Invalid class ID format');
			}
		}
		elseif( substr($url, 0, 11) == 'javascript:' )
		{ // javascript:
			// Basically there could be anything here
			if( ! in_array( 'javascript', $allowed_uri_schemes ) )
			{ // Scheme not allowed
				$scheme = 'javascript:';
				$Debuglog->add( 'URI scheme &laquo;'.$scheme.'&raquo; not allowed!', 'error' );
				return $verbose
					? sprintf( T_('URI scheme "%s" not allowed.'), htmlspecialchars($scheme) )
					: T_('URI scheme not allowed.');
			}

			preg_match( '¤^(javascript):¤', $url, $match );
		}
		else
		{
			// convert URL to IDN:
			$url = idna_encode($url);

			if( ! preg_match('~^           # start
				([a-z][a-z0-9+.\-]*)             # scheme
				://                              # authorize absolute URLs only ( // not present in clsid: -- problem? ; mailto: handled above)
				(\w+(:\w+)?@)?                   # username or username and password (optional)
				( localhost |
						[a-z0-9]([a-z0-9\-])*            # Don t allow anything too funky like entities
						\.                               # require at least 1 dot
						[a-z0-9]([a-z0-9.\-])+           # Don t allow anything too funky like entities
				)
				(:[0-9]+)?                       # optional port specification
				.*                               # allow anything in the path (including spaces - used in FileManager - but no newlines).
				$~ix', $url, $match) )
			{ // Cannot validate URL structure
				$Debuglog->add( 'URL &laquo;'.$url.'&raquo; does not match url pattern!', 'error' );
				return $verbose
					? sprintf( T_('Invalid URL format (%s).'), htmlspecialchars($url) )
					: T_('Invalid URL format.');
			}

			$scheme = strtolower($match[1]);
			if( ! in_array( $scheme, $allowed_uri_schemes ) )
			{ // Scheme not allowed
				$Debuglog->add( 'URI scheme &laquo;'.$scheme.'&raquo; not allowed!', 'error' );
				return $verbose
					? sprintf( T_('URI scheme "%s" not allowed.'), htmlspecialchars($scheme) )
					: T_('URI scheme not allowed.');
			}
		}
	}
	else
	{ // URL is relative..
		if( $context == 'commenting' )
		{	// We do not allow relative URLs in comments
			return $verbose ? sprintf( T_('URL "%s" must be absolute.'), htmlspecialchars($url) ) : T_('URL must be absolute.');
		}

		$char = substr($url, 0, 1);
		if( $char != '/' && $char != '#' )
		{ // must start with a slash or hash (for HTML anchors to the same page)
			return $verbose
				? sprintf( T_('URL "%s" must be a full path starting with "/" or an anchor starting with "#".'), htmlspecialchars($url) )
				: T_('URL must be a full path starting with "/" or an anchor starting with "#".');
		}
	}


	if( $antispam_check )
	{	// Search for blocked keywords:
		if( $block = antispam_check($url) )
		{
			return $verbose
				? sprintf( T_('URL "%s" not allowed: blacklisted word "%s".'), htmlspecialchars($url), $block )
				: T_('URL not allowed');
		}
	}

	return false; // OK
}


/**
 * Get allowed URI schemes for a given context.
 * @param string Context ("posting", "commenting")
 * @return array
 */
function get_allowed_uri_schemes( $context = 'posting' )
{
  /**
	 * @var User
	 */
	global $current_User;

	$schemes = array(
			'http',
			'https',
			'ftp',
			'gopher',
			'nntp',
			'news',
			'mailto',
			'irc',
			'aim',
			'icq'
		);

	if( $context == 'commenting' )
	{
		return $schemes;
	}

	if( !empty( $current_User ) )
	{	// Add additional permissions the current User may have:

		$Group = & $current_User->get_Group();

		if( $Group->perm_xhtml_javascript )
		{
			$schemes[] = 'javascript';
		}

		if( $Group->perm_xhtml_objects )
		{
			$schemes[] = 'clsid';
		}

	}

	return $schemes;
}


/**
 * Get the last HTTP status code received by the HTTP/HTTPS wrapper of PHP.
 *
 * @param array The $http_response_header array (by reference).
 * @return integer|boolean False if no HTTP status header could be found,
 *                         the HTTP status code otherwise.
 */
function _http_wrapper_last_status( & $headers )
{
	for( $i = count( $headers ) - 1; $i >= 0; --$i )
	{
		if( preg_match( '|^HTTP/\d+\.\d+ (\d+)|', $headers[$i], $matches ) )
		{
			return $matches[1];
		}
	}

	return false;
}


/**
 * Fetch remote page
 *
 * Attempt to retrieve a remote page using a HTTP GET request, first with
 * cURL, then fsockopen, then fopen.
 *
 * cURL gets skipped, if $max_size_kb is requested, since there appears to be no
 * method to control this.
 * {@internal (CURLOPT_READFUNCTION maybe? But it has not been called for me.. seems
 *            to affect sending, not fetching?!)}}
 *
 * @todo dh> Should we try remaining methods, if the previous one(s) failed?
 * @todo Tblue> Also allow HTTP POST.
 *
 * @param string URL
 * @param array Info (by reference)
 *        'error': holds error message, if any
 *        'status': HTTP status (e.g. 200 or 404)
 *        'used_method': Used method ("curl", "fopen", "fsockopen" or null if no method
 *                       is available)
 * @param integer Timeout (default: 15 seconds)
 * @param integer Maximum size in kB
 * @return string|false The remote page as a string; false in case of error
 */
function fetch_remote_page( $url, & $info, $timeout = NULL, $max_size_kb = NULL )
{
	$info = array(
		'error' => '',
		'status' => NULL,
		'mimetype' => NULL,
		'used_method' => NULL,
	);

	if( ! isset($timeout) )
		$timeout = 15;

	if( extension_loaded('curl') && ! $max_size_kb ) // dh> I could not find an option to support "maximum size" for curl (to abort during download => memory limit).
	{	// CURL:
		$info['used_method'] = 'curl';

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
		curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
		@curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true ); // made silent due to possible errors with safe_mode/open_basedir(?)
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 3 );
		$r = curl_exec( $ch );

		$info['mimetype'] = curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );
		$info['status'] = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$info['error'] = curl_error( $ch );
		if( ( $errno = curl_errno( $ch ) ) )
		{
			$info['error'] .= ' (#'.$errno.')';
		}
		curl_close( $ch );

		return $r;
	}

	if( function_exists( 'fsockopen' ) ) // may have been disabled
	{	// FSOCKOPEN:
		$info['used_method'] = 'fsockopen';

		if ( ( $url_parsed = @parse_url( $url ) ) === false
			 || ! isset( $url_parsed['host'] ) )
		{
			$info['error'] = 'Could not parse URL';
			return false;
		}

		$host = $url_parsed['host'];
		$port = empty( $url_parsed['port'] ) ? 80 : $url_parsed['port'];
		$path = empty( $url_parsed['path'] ) ? '/' : $url_parsed['path'];
		if( ! empty( $url_parsed['query'] ) )
		{
			$path .= '?'.$url_parsed['query'];
		}

		$out = 'GET '.$path.' HTTP/1.1'."\r\n";
		$out .= 'Host: '.$host;
		if( ! empty( $url_parsed['port'] ) )
		{	// we don't want to add :80 if not specified. remote end may not resolve it. (e-g b2evo multiblog does not)
			$out .= ':'.$port;
		}
		$out .= "\r\n".'Connection: Close'."\r\n\r\n";

		$fp = @fsockopen( $host, $port, $errno, $errstr, $timeout );
		if( ! $fp )
		{
			$info['error'] = $errstr.' (#'.$errno.')';
			return false;
		}

		// Send request:
		fwrite( $fp, $out );

		// Set timeout for data:
		if( function_exists( 'stream_set_timeout' ) )
		{
			stream_set_timeout( $fp, $timeout ); // PHP 4.3.0
		}
		else
		{
			socket_set_timeout( $fp, $timeout ); // PHP 4
		}

		// Read response:
		$r = '';
		// First line:
		$s = fgets( $fp );
		if( ! preg_match( '~^HTTP/\d+\.\d+ (\d+)~', $s, $match ) )
		{
			$info['error'] = 'Invalid response.';
			fclose( $fp );
			return false;
		}

		while( ! feof( $fp ) )
		{
			$r .= fgets( $fp );
			if( $max_size_kb && evo_bytes($r) >= $max_size_kb*1024 )
			{
				$info['error'] = sprintf('Maximum size of %d kB reached.', $max_size_kb);
				return false;
			}
		}
		fclose($fp);

		if ( ( $pos = strpos( $r, "\r\n\r\n" ) ) === false )
		{
			$info['error'] = 'Could not locate end of headers';
			return false;
		}

		// Remember headers to extract info at the end
		$headers = explode("\r\n", substr($r, 0, $pos));

		$info['status'] = $match[1];
		$r = substr( $r, $pos + 4 );
	}
	elseif( ini_get( 'allow_url_fopen' ) )
	{	// URL FOPEN:
		$info['used_method'] = 'fopen';

		$fp = @fopen( $url, 'r' );
		if( ! $fp )
		{
			if( isset( $http_response_header )
			    && ( $code = _http_wrapper_last_status( $http_response_header ) ) !== false )
			{	// fopen() returned false because it got a bad HTTP code:
				$info['error'] = 'Invalid response';
				$info['status'] = $code;
				return '';
			}

			$info['error'] = 'fopen() failed';
			return false;
		}
		// Check just to be sure:
		else if ( ! isset( $http_response_header )
		          || ( $code = _http_wrapper_last_status( $http_response_header ) ) === false )
		{
			$info['error'] = 'Invalid response';
			return false;
		}
		else
		{
			// Used to get info at the end
			$headers = $http_response_header;

			// Retrieve contents
			$r = '';
			while( ! feof( $fp ) )
			{
				$r .= fgets( $fp );
				if( $max_size_kb && evo_bytes($r) >= $max_size_kb*1024 )
				{
					$info['error'] = sprintf('Maximum size of %d kB reached.', $max_size_kb);
					return false;
				}
			}

			$info['status'] = $code;
		}
		fclose( $fp );
	}

	// Extract mimetype info from the headers (for fsockopen/fopen)
	if( isset($r) )
	{
		foreach($headers as $header)
		{
			$header = strtolower($header);
			if( substr($header, 0, 13) == 'content-type:' )
			{
				$info['mimetype'] = trim(substr($header, 13));
				break; // only looking for mimetype
			}
		}

		return $r;
	}

	// All failed:
	$info['error'] = 'No method available to access URL!';
	return false;
}


/**
 * Get $url with the same protocol (http/https) as $other_url.
 *
 * @param string URL
 * @param string other URL (defaults to {@link $ReqHost})
 * @return string
 */
function url_same_protocol( $url, $other_url = NULL )
{
	if( is_null($other_url) )
	{
		global $ReqHost;

		$other_url = $ReqHost;
	}

	// change protocol of $url to same of admin ('https' <=> 'http')
	if( substr( $url, 0, 7 ) == 'http://' )
	{
		if( substr( $other_url, 0, 8 ) == 'https://' )
		{
			$url = 'https://'.substr( $url, 7 );
		}
	}
	elseif( substr( $url, 0, 8 ) == 'https://' )
	{
		if( substr( $other_url, 0, 7 ) == 'http://' )
		{
			$url = 'http://'.substr( $url, 8 );
		}
	}

	return $url;
}


/**
 * Add param(s) at the end of an URL, using either "?" or "&amp;" depending on existing url
 *
 * @param string existing url
 * @param string|array Params to add (string as-is) or array, which gets urlencoded.
 * @param string delimiter to use for more params
 */
function url_add_param( $url, $param, $glue = '&amp;' )
{
	if( empty($param) )
	{
		return $url;
	}

	if( ($anchor_pos = strpos($url, '#')) !== false )
	{ // There's an "#anchor" in the URL
		$anchor = substr($url, $anchor_pos);
		$url = substr($url, 0, $anchor_pos);
	}
	else
	{ // URL without "#anchor"
		$anchor = '';
	}

	// Handle array use case
	if( is_array($param) )
	{ // list of key => value pairs
		$param_list = array();
		foreach( $param as $k => $v )
		{
			$param_list[] = get_param_urlencoded($k, $v, $glue);
		}
		$param = implode($glue, $param_list);
	}

	if( strpos($url, '?') !== false )
	{ // There are already params in the URL
		$r = $url;
		if( substr($url, -1) != '?' )
		{ // the "?" is not the last char
			$r .= $glue;
		}
		return $r.$param.$anchor;
	}

	// These are the first params
	return $url.'?'.$param.$anchor;
}


/**
 * Add a tail (starting with "/") at the end of an URL before any params (starting with "?")
 *
 * @param string existing url
 * @param string tail to add
 */
function url_add_tail( $url, $tail )
{
	$parts = explode( '?', $url );
	if( substr($parts[0], -1) == '/' )
	{
		$parts[0] = substr($parts[0], 0, -1);
	}
	if( isset($parts[1]) )
	{
		return $parts[0].$tail.'?'.$parts[1];
	}

	return $parts[0].$tail;
}


/**
 * Create a crumb param to be passed in action urls...
 *
 * @access public
 * @param string crumb_name
 */
function url_crumb( $crumb_name )
{
	return 'crumb_'.$crumb_name.'='.get_crumb($crumb_name);
}


/**
 * Get crumb via {@link $Session}.
 * @access public
 * @param string crumb_name
 * @return string
 */
function get_crumb($crumb_name)
{
	global $Session;
	return $Session->create_crumb($crumb_name);
}


/**
 * Try to make $url relative to $target_url, if scheme, host, user and pass matches.
 *
 * This is useful for redirect_to params, to keep them short and avoid mod_security
 * rejecting the request as "Not Acceptable" (whole URL as param).
 *
 * @param string URL to handle
 * @param string URL where we want to make $url relative to
 * @return string
 */
function url_rel_to_same_host( $url, $target_url )
{
	// Prepend fake scheme to URLs starting with "//" (relative to current protocol), since
	// parse_url fails to handle them correctly otherwise (recognizes them as path-only)
	$mangled_url = substr($url, 0, 2) == '//' ? 'noprotocolscheme:'.$url : $url;

	if( substr($target_url, 0, 2) == '//' )
		$target_url = 'noprotocolscheme:'.$target_url;


	$parsed_url = @parse_url( $mangled_url );
	if( ! $parsed_url )
	{ // invalid url
		return $url;
	}
	if( empty($parsed_url['scheme']) || empty($parsed_url['host']) )
	{ // no protocol or host information
		return $url;
	}

	$target_url = @parse_url( $target_url );
	if( ! $target_url )
	{ // invalid url
		return $url;
	}
	if( ! empty($target_url['scheme']) && $target_url['scheme'] != $parsed_url['scheme']
		&& $parsed_url['scheme'] != 'noprotocolscheme' )
	{ // scheme/protocol is different
		return $url;
	}
	if( ! empty($target_url['host']) )
	{
		if( empty($target_url['scheme']) || $target_url['host'] != $parsed_url['host'] )
		{ // target has no scheme (but a host) or hosts differ
			return $url;
		}

		if( @$target_url['port'] != @$parsed_url['port'] )
			return $url;
		if( @$target_url['user'] != @$parsed_url['user'] )
			return $url;
		if( @$target_url['pass'] != @$parsed_url['pass'] )
			return $url;
	}

	// We can make the URL relative:
	$r = '';
	if( isset($parsed_url['path']) && strlen($parsed_url['path']) )
		$r .= $parsed_url['path'];

	if( isset($parsed_url['query']) && strlen($parsed_url['query']) )
		$r .= '?'.$parsed_url['query'];

	if( isset($parsed_url['fragment']) && strlen($parsed_url['fragment']) )
		$r .= '#'.$parsed_url['fragment'];

	return $r;
}


/**
 * Make an $url absolute according to $host, if it is not absolute yet.
 *
 * @param string URL
 * @param string Host (including protocol, e.g. 'http://example.com'); defaults to {@link $ReqHost}
 * @return string
 */
function url_absolute( $url, $host = NULL )
{
	if( is_absolute_url($url) )
	{ // URL is already absolute
		return $url;
	}

	if( empty($host) )
	{
		global $ReqHost;
		$host = $ReqHost;
	}
	return $host.$url;
}


/**
 * Make links in $s absolute.
 *
 * It searches for "src" and "href" HTML tag attributes and makes the absolute.
 *
 * @uses url_absolute()
 * @param string content
 * @param string Hostname including scheme, e.g. http://example.com; defaults to $ReqHost
 * @return string
 */
function make_rel_links_abs( $s, $host = NULL )
{
	if( empty($host) )
	{
		global $ReqHost;
		$host = $ReqHost;
	}

	$s = preg_replace_callback( '~(<[^>]+?)\b((?:src|href)\s*=\s*)(["\'])?([^\\3]+?)(\\3)~i', create_function( '$m', '
		return $m[1].$m[2].$m[3].url_absolute($m[4], "'.$host.'").$m[5];' ), $s );
	return $s;
}


/**
 * Display an URL, constrained to a max length
 *
 * @param string
 * @param integer
 */
function disp_url( $url, $max_length = NULL )
{
	if( !empty($max_length) && evo_strlen($url) > $max_length )
	{
		$disp_url = htmlspecialchars(substr( $url, 0, $max_length-1 )).'…';
	}
	else
	{
		$disp_url = htmlspecialchars($url);
	}
	echo '<a href="'.$url.'">'.$disp_url.'</a>';
}


/**
 * Is a given URL absolute?
 * Note: "//foo/bar" is absolute - leaving the protocol out.
 *
 * @param string URL
 * @return boolean
 */
function is_absolute_url($url)
{
    return (bool)preg_match('~^(?:\w+:)?//~', $url);
}


/**
 * Compare two given URLs, if they are the same.
 * This converts all urlencoded chars (e.g. "%AA") to lowercase.
 * It appears that some webservers use lowercase for the chars (Apache),
 * while others use uppercase (lighttpd).
 * @return boolean
 */
function is_same_url( $a, $b )
{
	$a = preg_replace_callback('~%[0-9A-F]{2}~', create_function('$m', 'return strtolower($m[0]);'), $a);
	$b = preg_replace_callback('~%[0-9A-F]{2}~', create_function('$m', 'return strtolower($m[0]);'), $b);
	return $a == $b;
}


/**
 * IDNA-Encode URL to Punycode.
 * @param string URL
 * @return string Encoded URL (ASCII)
 */
function idna_encode( $url )
{
	load_class('_ext/idna/_idna_convert.class.php', 'idna_convert');
	$IDNA = new idna_convert();
	return $IDNA->encode( $url );
}


/**
 * Decode IDNA puny-code ("xn--..") to UTF-8 name.
 *
 * @param string
 * @return string The decoded puny-code ("xn--..") (UTF8!)
 */
function idna_decode( $url )
{
	load_class('_ext/idna/_idna_convert.class.php', 'idna_convert');
	$IDNA = new idna_convert();
	return $IDNA->decode($url);
}


/* {{{ Revision log:
 * $Log$
 * Revision 1.53  2011/02/15 06:13:49  sam2kb
 * strlen replaced with evo_strlen to support utf-8 logins and domain names
 *
 * Revision 1.52  2010/07/26 06:52:15  efy-asimo
 * MFB v-4-0
 *
 * Revision 1.51  2010/05/15 21:12:04  blueyed
 * Fix url_rel_to_same_host for both '0' as path, query or fragment, and the fragment itself ('#' instead of '?').
 *
 * Revision 1.50  2010/04/08 18:28:02  blueyed
 * crumb refactoring: add get_crumb
 *
 * Revision 1.49  2010/02/08 17:51:38  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.48  2010/01/09 21:04:06  blueyed
 * doc
 *
 * Revision 1.47  2010/01/09 17:46:05  fplanque
 * minor
 *
 * Revision 1.46  2010/01/09 01:11:01  sam2kb
 * Prevent PHP warning caused by CURLOPT_FOLLOWLOCATION and safe_mode/open_basedir
 * Added missing return to CURL method
 *
 * Revision 1.45  2010/01/02 21:13:32  fplanque
 * demo of Crumbs in action urls (GET not POST).
 * Normalized code a little (not perfect).
 *
 * Revision 1.44  2009/10/27 21:57:45  fplanque
 * minor/doc
 *
 * Revision 1.43  2009/10/16 19:58:46  blueyed
 * fetch_remote_page:
 *  - add max_size_kb param (TODO: implement this also for curl, currently it gets skipped).
 *  - add some config for curl: CURLOPT_FOLLOWLOCATION=1, CURLOPT_MAXREDIRS=3
 *  - add "mimetype" info to $info
 *
 * Revision 1.42  2009/09/28 22:57:51  blueyed
 * Fix url_rel_to_same_host for protocol relative URLs (//example.org/)
 *
 * Revision 1.41  2009/09/27 13:12:53  blueyed
 * Add get_param_urlencoded: use it in regenerate_url and add functionality to pass array of params to url_add_param
 *
 * Revision 1.40  2009/09/15 12:09:52  tblue246
 * More load_class() fixes
 *
 * Revision 1.39  2009/09/14 18:37:07  fplanque
 * doc/cleanup/minor
 *
 * Revision 1.38  2009/09/14 11:41:23  efy-arrin
 * Included the ClassName in load_class() call with proper UpperCase
 *
 * Revision 1.37  2009/08/24 13:07:10  tblue246
 * Fixed bugs in fetch_remote_page()
 *
 * Revision 1.36  2009/06/04 21:20:23  blueyed
 * Fix url_add_param if last char is question mark. Add and move tests.
 *
 * Revision 1.35  2009/05/26 17:18:36  tblue246
 * - Twitter plugin:
 * 	- removed unnecessary BeforeEnable() method.
 * 	- Todo: Do not depend on cURL
 * 	- Minor code improvements
 * - fetch_remote_page(): Todo about supporting HTTP POST requests
 *
 * Revision 1.34  2009/05/26 16:16:50  fplanque
 * minor
 *
 * Revision 1.33  2009/05/19 19:19:59  blueyed
 * doc. yes. ;)
 *
 * Revision 1.32  2009/05/05 19:00:30  tblue246
 * More error checking...
 *
 * Revision 1.31  2009/05/04 11:28:32  tblue246
 * error checking
 *
 * Revision 1.30  2009/05/04 11:12:51  tblue246
 * minor
 *
 * Revision 1.29  2009/05/02 20:36:55  tblue246
 * fetch_remote_page(): Allow the use of URLs without a scheme (assume "http://").
 *
 * Revision 1.28  2009/05/02 19:14:42  tblue246
 * Improved fetch_remote_page(). e. g. if fopen() returns false, check if this was caused by an HTTP response with the status code 404.
 *
 * Revision 1.27  2009/03/08 23:57:40  fplanque
 * 2009
 *
 * Revision 1.26  2009/02/26 23:33:46  blueyed
 * Update IDNA library to 0.6.2 (includes at least a fix for mbstring.func_overload).
 * Since it is PHP5 only, PHP4 won't benefit from it.
 * Add wrapper idna_encode() and idna_decode() to url.funcs to handle loading
 * of the PHP5 or PHP4 class.
 * Move test.
 *
 * Revision 1.25  2009/02/26 22:16:54  blueyed
 * Use load_class for classes (.class.php), and load_funcs for funcs (.funcs.php)
 *
 * Revision 1.24  2009/01/23 17:23:09  fplanque
 * doc/minor
 *
 * Revision 1.23  2009/01/21 21:22:07  blueyed
 * Add is_absolute_url
 *
 * Revision 1.22  2009/01/21 21:17:31  blueyed
 * validate_url: allow spaces in URL path. They are valid and allowed
 * in the file manager. So this allows linking to uploaded pictures
 * (which contain spaces in the file name).
 * Previously, newlines have been valid in URLs! (due to use of "[^ ]")
 * Add test.
 *
 * Revision 1.21  2009/01/21 21:06:18  blueyed
 * url_rel_to_same_host:
 *  - Tests have been moved to url.funcs.simpletest.php.
 *  - Add currently failing tests for handling of URLs without protocol,
 *    which parse_url parses as path-only.
 *  - Add note to phpdoc.
 *
 * Revision 1.20  2008/12/20 22:36:33  blueyed
 * Add is_same_url() to compare URLs without taking case of urlencoded parts into account. This is required to prevent infinite redirects in the handling of canonical URLs.
 *
 * Revision 1.19  2008/05/10 21:30:39  fplanque
 * better UTF-8 handling
 *
 * Revision 1.18  2008/05/06 23:23:46  fplanque
 * timeout handling
 *
 * Revision 1.17  2008/05/04 15:49:33  blueyed
 * doc
 *
 * Revision 1.16  2008/04/14 16:01:36  fplanque
 * url validation fix
 *
 * Revision 1.15  2008/03/09 03:31:58  blueyed
 * validate_url: convert URL to IDN, before checking it (LP: #195702)
 *
 * Revision 1.14  2008/03/09 02:34:26  blueyed
 * minor: fix typo in comment
 *
 * Revision 1.13  2008/02/19 11:11:17  fplanque
 * no message
 *
 * Revision 1.11  2008/02/11 23:43:07  fplanque
 * support for localhost
 *
 * Revision 1.10  2008/02/09 16:19:31  fplanque
 * fixed commenting bugs
 *
 * Revision 1.9  2008/01/21 09:35:23  fplanque
 * (c) 2008
 *
 * Revision 1.8  2008/01/20 15:31:12  fplanque
 * configurable validation/security rules
 *
 * Revision 1.7  2008/01/19 18:24:24  fplanque
 * antispam checking refactored
 *
 * Revision 1.6  2008/01/19 15:45:28  fplanque
 * refactoring
 *
 * Revision 1.5  2008/01/18 15:53:42  fplanque
 * Ninja refactoring
 *
 * Revision 1.4  2008/01/05 02:25:23  fplanque
 * refact
 *
 * Revision 1.3  2007/11/27 22:31:57  fplanque
 * debugged blog moderation
 *
 * Revision 1.2  2007/07/03 22:16:15  blueyed
 * Solved todo: changed order to "cURL, fsockopen, fopen" for fetch_remote_page
 *
 * Revision 1.1  2007/06/25 10:58:54  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.10  2007/04/26 00:11:08  fplanque
 * (c) 2007
 *
 * Revision 1.9  2007/03/12 22:39:42  blueyed
 * Fixed just introduced E_PARSE
 *
 * Revision 1.8  2007/03/12 22:12:58  blueyed
 * fetch_remote_page(): handle case when fsockopen is disabled
 *
 * Revision 1.7  2007/03/08 22:55:21  blueyed
 * fetch_remote_page: Added "used_method" to $info and errno to "curl" method.
 *
 * Revision 1.6  2006/12/22 00:25:15  blueyed
 * Do not send URL fragment in GET
 *
 * Revision 1.5  2006/12/21 21:50:52  fplanque
 * doc
 *
 * Revision 1.4  2006/12/19 21:35:26  blueyed
 * fetch_remote_page(): if url_fopen fails, use fsockopen-fallback (for errstr and status). Plus minor fixes.
 *
 * Revision 1.3  2006/12/01 17:31:38  blueyed
 * Fixed url_fopen method for fetch_remote_page
 *
 * Revision 1.2  2006/11/29 20:48:46  blueyed
 * Moved url_rel_to_same_host() from _misc.funcs.php to _url.funcs.php
 *
 * Revision 1.1  2006/11/25 23:00:39  blueyed
 * Added file for URL handling. Includes fetch_remote_page()
 *
 * }}}
 */
?>
