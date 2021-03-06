<?php
/**
 * This file implements misc functions that handle output of the HTML page.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
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
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author blueyed: Daniel HAHLER.
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * Template tag. Output content-type header
 *
 * @param string content-type; override for RSS feeds
 */
function header_content_type( $type = 'text/html', $charset = 'utf-8' )
{
	global $content_type_header;

	$content_type_header = 'Content-type: '.$type;

	$content_type_header .= '; charset='.$charset;

	header( $content_type_header );
}


/**
 * This is a placeholder for future development.
 *
 * @param string content-type; override for RSS feeds
 * @param integer seconds
 */
function headers_content_mightcache( $type = 'text/html', $max_age = '#', $charset = 'utf-8' )
{
	global $is_admin_page;

	header_content_type( $type, $charset );

	if( empty($max_age) || $is_admin_page || is_logged_in() )
	{	// Don't cache if no max_age given + NEVER EVER allow admin pages to cache + NEVER EVER allow logged in data to be cached:
		header_nocache();
		return;
	}

	// If we are on a "normal" page, we may, under some circumstances, tell the browser it can cache the data.
	// This MAY be extremely confusing though, every time a user logs in and gets back to a screen with no evobar!
	// This cannot be enabled by default and requires admin switches.

	// For feeds, it is a little bit less confusing. We might want to have the param enabled by default in that case.

	// WARNING: extra special care needs to be taken before ever caching a blog page that might contain a form or a comment preview
	// having user details cached would be extremely bad.

	// in the meantime...
	header_nocache();
}


/**
 * Sends HTTP header to redirect to the previous location (which
 * can be given as function parameter, GET parameter (redirect_to),
 * is taken from {@link Hit::$referer} or {@link $baseurl}).
 *
 * {@link $Debuglog} and {@link $Messages} get stored in {@link $Session}, so they
 * are available after the redirect.
 *
 * NOTE: This function {@link exit() exits} the php script execution.
 *
 * @todo fp> do NOT allow $redirect_to = NULL. This leads to spaghetti code and unpredictable behavior.
 *
 * @param string Destination URL to redirect to
 * @param boolean|integer is this a permanent redirect? if true, send a 301; otherwise a 303 OR response code 301,302,303
 */
function header_redirect( $redirect_to = NULL, $status = false )
{
	global $Hit, $baseurl, $Blog, $htsrv_url_sensitive;
	global $Session, $Debuglog, $Messages;
	global $http_response_code;

	// TODO: fp> get this out to the caller, make a helper func like get_returnto_url()
	if( empty($redirect_to) )
	{ // see if there's a redirect_to request param given:
		$redirect_to = param( 'redirect_to', 'string', '' );

		if( empty($redirect_to) )
		{
			if( ! empty($Hit->referer) )
			{
				$redirect_to = $Hit->referer;
			}
			elseif( isset($Blog) && is_object($Blog) )
			{
				$redirect_to = $Blog->get('url');
			}
			else
			{
				$redirect_to = $baseurl;
			}
		}
		elseif( $redirect_to[0] == '/' )
		{ // relative URL, prepend current host:
			global $ReqHost;
			$redirect_to = $ReqHost.$redirect_to;
		}
	}
	// <fp

	if( $redirect_to[0] == '/' )
	{
		// TODO: until all calls to header_redirect are cleaned up:
		global $ReqHost;
		$redirect_to = $ReqHost.$redirect_to;
		// debug_die( '$redirect_to must be an absolute URL' );
	}

	if( strpos($redirect_to, $htsrv_url_sensitive) === 0 /* we're going somewhere on $htsrv_url_sensitive */
	 || strpos($redirect_to, $baseurl) === 0   /* we're going somewhere on $baseurl */ )
	{
		// Remove login and pwd parameters from URL, so that they do not trigger the login screen again:
		// Also remove "action" get param to avoid unwanted actions
		// blueyed> Removed the removing of "action" here, as it is used to trigger certain views. Instead, "confirm(ed)?" gets removed now
		// fp> which views please (important to list in order to remove asap)
		// dh> sorry, don't remember
		// TODO: fp> action should actually not be used to trigger views. This should be changed at some point.
		// TODO: fp> confirm should be normalized to confirmed
		$redirect_to = preg_replace( '~(?<=\?|&) (login|pwd|confirm(ed)?) = [^&]+ ~x', '', $redirect_to );
	}

	if( is_integer($status) )
	{
		$http_response_code = $status;
	}
	else
	{
		$http_response_code = $status ? 301 : 303;
	}
 	$Debuglog->add('***** REDIRECT TO '.$redirect_to.' (status '.$http_response_code.') *****', 'request' );

	// Transfer of Debuglog to next page:
	if( $Debuglog->count('all') )
	{ // Save Debuglog into Session, so that it's available after redirect (gets loaded by Session constructor):
		$sess_Debuglogs = $Session->get('Debuglogs');
		if( empty($sess_Debuglogs) )
		{
			$sess_Debuglogs = array();
		}

		$sess_Debuglogs[] = $Debuglog;
		$Session->set( 'Debuglogs', $sess_Debuglogs, 60 /* expire in 60 seconds */ );
	 	// echo 'Passing Debuglog(s) to next page';
	 	// pre_dump( $sess_Debuglogs );
	}

	// Transfer of Messages to next page:
	if( $Messages->count() )
	{ // Set Messages into user's session, so they get restored on the next page (after redirect):
		$Session->set( 'Messages', $Messages );
	 // echo 'Passing Messages to next page';
	}

	if( ! empty($Session) )
	{
		$Session->dbsave(); // If we don't save now, we run the risk that the redirect goes faster than the PHP script shutdown.
	}

	// see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
	switch( $http_response_code )
	{
		case 301:
			// This should be a permanent move redirect!
			header( 'HTTP/1.1 301 Moved Permanently' );
			break;

		case 303:
			// This should be a "follow up" redirect
			// Note: Also see http://de3.php.net/manual/en/function.header.php#50588 and the other comments around
			header( 'HTTP/1.1 303 See Other' );
			break;

		case 302:
		default:
			header( 'HTTP/1.1 302 Found' );
	}

	if( headers_sent() )
	{
		debug_die('Headers have already been sent. Cannot <a href="'.htmlspecialchars($redirect_to).'">redirect</a>.');
	}
	header( 'Location: '.$redirect_to, true, $http_response_code ); // explictly setting the status is required for (fast)cgi
	exit(0);
}



/**
 * Sends HTTP headers to avoid caching of the page.
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
 */
function header_nocache( $timestamp = NULL )
{
	global $servertimenow;
	if( empty($timestamp) )
	{
		$timestamp = $servertimenow;
	}

	header('Expires: '.gmdate('r',$timestamp));
	header('Last-Modified: '.gmdate('r',$timestamp));
	header('Cache-Control: no-cache, must-revalidate');
	header('Pragma: no-cache');
}


/**
 * This is to "force" (strongly suggest) caching.
 *
 * WARNING: use this only for STATIC content that does NOT depend on the current user.
 *
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
 */
function header_noexpire()
{
	global $servertimenow;
	header('Expires: '.gmdate('r', $servertimenow + 31536000)); // 86400*365 (1 year)
}


/**
 * Display a global title matching filter params
 *
 * Outputs the title of the category when you load the page with <code>?cat=</code>
 * Display "Archive Directory" title if it has been requested
 * Display "Latest comments" title if these have been requested
 * Display "Statistics" title if these have been requested
 * Display "User profile" title if it has been requested
 *
 * @todo single month: Respect locales datefmt
 * @todo single post: posts do no get proper checking (wether they are in the requested blog or wether their permissions match user rights,
 * thus the title sometimes gets displayed even when it should not. We need to pre-query the ItemList instead!!
 * @todo make it complete with all possible params!
 *
 * @param array params
 *        - "auto_pilot": "seo_title": Use the SEO title autopilot. (Default: "none")
 */
function request_title( $params = array() )
{
	global $MainList, $preview, $disp;

	$r = array();

	$params = array_merge( array(
			'auto_pilot'          => 'none',
			'title_before'        => '',
			'title_after'         => '',
			'title_none'          => '',
			'title_single_disp'   => true,
			'title_single_before' => '#',
			'title_single_after'  => '#',
			'title_page_disp'     => true,
			'title_page_before'   => '#',
			'title_page_after'    => '#',
			'glue'                => ' - ',
			'format'              => 'htmlbody',
			'arcdir_text'         => T_('Archive Directory'),
			'catdir_text'         => T_('Category Directory'),
			'mediaidx_text'       => T_('Photo Index'),
			'postidx_text'        => T_('Post Index'),
			'search_text'         => T_('Search'),
			'sitemap_text'        => T_('Site Map'),
			'msgform_text'        => T_('Send a Message'),
			'login_text'          => T_('Login'),
			'profile_text'        => T_('User Profile'),
			'avatar_text'         => T_('User Avatar'),
			'pwdchange_text'      => T_('Password'),
			'userprefs_text'      => T_('User preferences'),
			'user_text'           => T_('User'),
			'subs_text'           => T_('Subscriptions'),
			'comments_text'       => T_('Latest Comments'),
			'feedback-popup_text' => T_('Feedback'),
		), $params );

	if( $params['auto_pilot'] == 'seo_title' )
	{	// We want to use the SEO title autopilot. Do overrides:
		global $Blog;
		$params['format'] = 'htmlhead';
		$params['title_after'] = $params['glue'].$Blog->get('name');
		$params['title_single_after'] = '';
		$params['title_page_after'] = '';
		$params['title_none'] = $Blog->dget('name','htmlhead');
	}


	$before = $params['title_before'];
	$after = $params['title_after'];

	switch( $disp )
	{
		case 'arcdir':
			// We are requesting the archive directory:
			$r[] = $params['arcdir_text'];
			break;

		case 'catdir':
			// We are requesting the archive directory:
			$r[] = $params['catdir_text'];
			break;

		case 'mediaidx':
			$r[] = $params['mediaidx_text'];
			break;

		case 'postidx':
			$r[] = $params['postidx_text'];
			break;

		case 'sitemap':
			$r[] = $params['sitemap_text'];
			break;

		case 'search':
			$r[] = $params['search_text'];
			break;

		case 'comments':
			// We are requesting the latest comments:
			global $Item;
			if( isset( $Item ) )
			{
				$r[] = sprintf( $params['comments_text'] . T_(' on %s'), $Item->get('title') );
			}
			else
			{
				$r[] = $params['comments_text'];
			}
			break;

		case 'feedback-popup':
			// We are requesting the comments on a specific post:
			// Should be in first position
			$Item = & $MainList->get_by_idx( 0 );
			$r[] = sprintf( $params['feedback-popup_text'] . T_(' on %s'), $Item->get('title') );
			break;

		case 'profile':
			// We are requesting the user profile:
			$r[] = $params['profile_text'];
			break;

		case 'avatar':
			// We are requesting the user avatar:
			$r[] = $params['avatar_text'];
			break;

		case 'pwdchange':
			// We are requesting the user change password:
			$r[] = $params['pwdchange_text'];
			break;

		case 'userprefs':
			// We are requesting the user preferences:
			$r[] = $params['userprefs_text'];
			break;

		case 'subs':
			// We are requesting the subscriptions screen:
			$r[] = $params['subs_text'];
			break;

		case 'msgform':
			// We are requesting the message form:
			$r[] = $params['msgform_text'];
			break;

		case 'login':
			// We are requesting the login form:
			$r[] = $params['login_text'];
			break;

		case 'single':
		case 'page':
			// We are displaying a single message:
			if( $preview )
			{	// We are requesting a post preview:
				$r[] = T_('PREVIEW');
			}
			elseif( $params['title_'.$disp.'_disp'] && isset( $MainList ) )
			{
				$r = array_merge( $r, $MainList->get_filter_titles( array( 'visibility', 'hide_future' ), $params ) );
			}
			if( $params['title_'.$disp.'_before'] != '#' )
			{
				$before = $params['title_'.$disp.'_before'];
			}
			if( $params['title_'.$disp.'_after'] != '#' )
			{
				$after = $params['title_'.$disp.'_after'];
			}
			break;

		case 'user':
			// We are requesting the message form:
			$r[] = $params['user_text'];
			break;

		default:
			if( isset( $MainList ) )
			{
				$r = array_merge( $r, $MainList->get_filter_titles( array( 'visibility', 'hide_future' ), $params ) );
			}
			break;
	}


	if( ! empty( $r ) )
	{	// We have at leats one title match:
		$r = implode( $params['glue'], $r );
		if( ! empty( $r ) )
		{	// This is in case we asked for an empty title (e-g for search)
			$r = $before.format_to_output( $r, $params['format'] ).$after;
		}
	}
	elseif( !empty( $params['title_none'] ) )
	{
		$r = $params['title_none'];
	}

	if( !empty( $r ) )
	{ // We have something to display:
		echo $r;
	}

}


/**
 * Returns a "<base />" tag and remembers that we've used it ({@link regenerate_url()} needs this).
 *
 * @param string URL to use (this gets used as base URL for all relative links on the HTML page)
 * @return string
 */
function base_tag( $url, $target = NULL )
{
	global $base_tag_set;
	$base_tag_set = $url;
	echo '<base href="'.$url.'"';

	if( !empty($target) )
	{
		echo ' target="'.$target.'"';
	}
	echo " />\n";
}


/**
 * Robots tag
 *
 * Outputs the robots meta tag if necessary
 */
function robots_tag()
{
	global $robots_index, $robots_follow;

	if( is_null($robots_index) && is_null($robots_follow) )
	{
		return;
	}

	$r = '<meta name="robots" content="';

	if( $robots_index === false )
		$r .= 'NOINDEX';
	else
		$r .= 'INDEX';

	$r .= ',';

	if( $robots_follow === false )
		$r .= 'NOFOLLOW';
	else
		$r .= 'FOLLOW';

	$r .= '" />'."\n";

	echo $r;
}


/**
 * Output a link to current blog.
 *
 * We need this function because if no Blog is currently active (some admin pages or site pages)
 * then we'll go to the general home.
 */
function blog_home_link( $before = '', $after = '', $blog_text = 'Blog', $home_text = 'Home' )
{
	global $Blog, $baseurl;

	if( !empty( $Blog ) )
	{
		echo $before.'<a href="'.$Blog->get( 'url' ).'">'.$blog_text.'</a>'.$after;
	}
	elseif( !empty($home_text) )
	{
		echo $before.'<a href="'.$baseurl.'">'.$home_text.'</a>'.$after;
	}
}


/**
 * Require/Include a given JavaScript file.
 *
 * This gets added to the HTML headlines and multiple includes
 * are detected.
 *
 * Accepts aliases (e.g. 'jquery'), absolute filenames, absolute
 * urls, filenames relative to rsc/js or {@link $basepath}.
 *
 * If 'jquery' is used and $debug is set to true, the 'jquery_debug'
 * is automatically swapped in.
 *
 * @param string URL or filename/path (can be absolute, relative to rsc/js or basepath)
 * @param boolean Is the file's path relative to the base path/url?
 *                NOTE: not used in whissip (autodetected).
 */
function require_js( $js_file, $relative_to_base = false )
{
	global $debug;

	$js_aliases = array(
		'#jquery#' => 'jquery.min.js',
		'#jquery_debug#' => 'jquery.js',
		'#jqueryUI#' => 'jquery/jquery.ui.all.min.js',
		'#jqueryUI_debug#' => 'jquery/jquery.ui.all.js',
	);

	// TODO: dh> I think dependencies should get handled where the files are included!
	if( in_array( $js_file, array( '#jqueryUI#', '#jqueryUI_debug#' ) ) )
	{	// Dependency : ensure jQuery is loaded
		require_js( '#jquery#' );
	}
	elseif( $js_file == 'communication.js' )
	{ // jQuery dependency
		require_js( '#jquery#' );
	}

	$attribs = array();
	if( !empty( $js_aliases[$js_file]) )
	{ // It's an alias
		if ( $js_file == '#jquery#' && $debug ) $js_file = '#jquery_debug#';
		$js_file = $js_aliases[$js_file];
	}

	$GLOBALS['ResourceBundles']->add_file('js', $js_file, $attribs);
}


/**
 * Require/Include a given CSS file.
 *
 * This gets added to the HTML headlines and multiple includes
 * are detected.
 *
 * Accepts absolute filename, absolute urls, filenames relative
 * to rsc/js or {@link $basepath}.
 *
 * @param string URL or filename/path (can be absolute, relative to rsc/js or basepath)
 * @param boolean|string Is the file's path relative to the base path/url?
 *                Use true to not add any prefix ("$rsc_url/css/").
 *                NOTE: not used in whissip (autodetected).
 * @param string title.  The title for the link tag
 * @param string media.  ie, 'print'
 */
function require_css( $css_file, $relative_to_base = false, $title = NULL, $media = NULL )
{
	$attribs = array();
	if( isset($title) )
	{
		$attribs['title'] = $title;
	}
	if( isset($media) )
	{
		$attribs['media'] = $media;
	}


	$GLOBALS['ResourceBundles']->add_file('css', $css_file, $attribs);
}


/**
 * Memorize that a specific js helper will be required by the current page.
 * All requested helpers will be included in the page head only once (when headlines is called)
 * All requested helpers will add their required translation strings and any other settings
 *
 * @param string helper, name of the required helper
 */
function require_js_helper( $helper = '' )
{
	static $helpers;

	if( empty( $helpers ) || !in_array( $helper, $helpers ) )
	{ // add the helper
		switch( $helper )
		{
			case 'helper' : // main helper object required
				global $debug;
				require_js( '#jquery#' ); // dependency
				require_js( 'helper.js' );
				add_js_headline('jQuery(document).ready(function()
		{
			b2evoHelper.Init({
				debug:'.( $debug ? 'true' : 'false' ).'
			});
		});');
				break;

			case 'communications' : // communications object required
				require_js_helper('helper'); // dependency

				global $dispatcher;
				require_js( 'communication.js' );
				add_js_headline('jQuery(document).ready(function()
		{
			b2evoCommunications.Init({
				dispatcher:"'.$dispatcher.'"
			});
		});' );
				// add translation strings
				T_('Update cancelled', NULL, array( 'for_helper' => true ) );
				T_('Update paused', NULL, array( 'for_helper' => true ) );
				T_('Changes pending', NULL, array( 'for_helper' => true ) );
				T_('Saving changes', NULL, array( 'for_helper' => true ) );
				break;
		}
		// add to list of loaded helpers
		$helpers[] = $helper;
	}
}

/**
 * Memorize that a specific translation will be required by the current page.
 * All requested translations will be included in the page body only once (when footerlines is called)
 *
 * @param string string, untranslated string
 * @param string translation, translated string
 */
function add_js_translation( $string, $translation )
{
	global $js_translations;
	if( $string != $translation )
	{ // it's translated
		$js_translations[ $string ] = $translation;
	}
}


/**
 * Add a headline, which then gets output in the HTML HEAD section.
 * If you want to include CSS or JavaScript files, please use
 * {@link require_css()} and {@link require_js()} instead.
 * This avoids duplicates and allows caching/concatenating those files
 * later (not implemented yet)
 * @param string
 */
function add_headline($headline)
{
	global $headlines;
	$headlines[] = $headline;
}


/**
 * Add a footer line, which then gets output at the bottom of the HTML BODY.
 * @param string
 */
function add_footerline($footerline)
{
	global $footerlines;
	$footerlines[] = $footerline;
}


/**
 * Add a Javascript headline.
 * This is an extra function, to provide consistent wrapping and allow to bundle it
 * (i.e. create a bundle with all required JS files and these inline code snippets,
 *  in the correct order).
 * It is being added as a footer line actually, to allow for adding them when page
 * output has started already, and to make sure libs like jQuery are available.
 * @param string Javascript
 */
function add_js_headline($headline)
{
	add_footerline('<script type="text/javascript" defer="defer">'.$headline.'</script>');
}


/**
 * Add a CSS headline.
 * This is an extra function, to provide consistent wrapping and allow to bundle it
 * (i.e. create a bundle with all required JS files and these inline code snippets,
 *  in the correct order).
 * @param string CSS
 */
function add_css_headline($headline)
{
	// FIXME: workaround for b2evo, which has no $attribs yet; keep inline code in a separate bundle.
	$attribs['rsc_pack'] = 'inline';
	$GLOBALS['ResourceBundles']->add_inline('css', $headline, $attribs);
}


/**
 * Registers all the javascripts needed by the toolbar menu
 *
 * @todo fp> include basic.css ? -- rename to add_headlines_for* -- potential problem with inclusion order of CSS files!!
 *       dh> would be nice to have the batch of CSS in a separate file. basic.css would get included first always, then e.g. this toolbar.css.
 */
function add_js_for_toolbar()
{
	if( ! is_logged_in() )
	{ // the toolbar (blogs/skins/_toolbar.inc.php) gets only used when logged in.
		return false;
	}

	require_js( '#jquery#' );
	require_js( 'functions.js' );	// for rollovers AddEvent - TODO: change to jQuery
	require_js( 'rollovers.js' );	// TODO: change to jQuery
	// Superfish menus:
	require_js( 'hoverintent.js' );
	require_js( 'superfish.js' );
	add_js_headline( '
		jQuery( function() {
			jQuery("ul.sf-menu").superfish({
	            delay: 500, // mouseout
	            animation: {opacity:"show",height:"show"},
	            speed: "fast"
	        });
		} )');

	return true;
}


/**
 * Outputs the collected HTML HEAD lines.
 * @see add_headline()
 * @return string
 */
function include_headlines()
{
	global $headlines;
	global $ResourceBundles;

	// Output links for resource bundles (CSS only - JS in footer), if there are any:
	if( $ResourceBundles )
	{ // only if the class has been loaded, it is relevant here
		if( $rsc_bundle_lines = $ResourceBundles->get_html_headlines() )
		{
			echo "\n\t<!-- Resource bundles: -->";
			echo "\n\t".implode("\n\t", $rsc_bundle_lines);
			echo "\n";
		}
		$ResourceBundles->forget_resolved_bundles();
	}

	if( $headlines )
	{
		echo "\n\t<!-- headlines: -->\n\t".implode( "\n\t", $headlines );
		echo "\n";
	}
}


/**
 * Outputs the collected translation lines before </body>
 *
 * yabs > Should this be expanded to similar functionality to headlines?
 *
 * @see add_js_translation()
 */
function include_footerlines()
{
	// Output links for resource bundles (CSS only - JS in footer), if there are any:
	global $ResourceBundles;
	if( $ResourceBundles )
	{ // only if the class has been loaded, it is relevant here
		if( $rsc_bundle_lines = $ResourceBundles->get_html_headlines_for_type('js') )
		{
			echo "\n\t<!-- Resource bundles: -->";
			echo "\n\t".implode("\n\t", $rsc_bundle_lines);
			echo "\n";
		}
	}

	global $js_translations;
	if( ! empty( $js_translations ) )
	{
		$r = '';
		foreach( $js_translations as $string => $translation )
		{ // output each translation
			if( $string != $translation )
			{ // this is translated
				$r .= '<div><span class="b2evo_t_string">'.$string.'</span><span class="b2evo_translation">'.$translation.'</span></div>'."\n";
			}
		}
		if( $r )
		{ // we have some translations
			echo '<div id="b2evo_translations" style="display:none;">'."\n";
			echo $r;
			echo '</div>'."\n";
		}
	}

	global $footerlines;
	if( $footerlines ) {
		echo implode("\n", $footerlines);
	}
}


/**
 * Template tag.
 */
function app_version()
{
	global $app_version;
	echo $app_version;
}


/**
 * Displays an empty or a full bullet based on boolean
 *
 * @param boolean true for full bullet, false for empty bullet
 */
function bullet( $bool )
{
	if( $bool )
		return get_icon( 'bullet_full', 'imgtag' );

	return get_icon( 'bullet_empty', 'imgtag' );
}




/**
 * Stub: Links to previous and next post in single post mode
 */
function item_prevnext_links( $params = array() )
{
	global $MainList;

	$params = array_merge( array( 'target_blog' => 'auto' ), $params );

	if( isset($MainList) )
	{
		$MainList->prevnext_item_links( $params );
	}
}


/**
 * Stub
 */
function messages( $params = array() )
{
	global $Messages;

	$Messages->disp( $params['block_start'], $params['block_end'] );
}


/**
 * Stub: Links to list pages:
 */
function mainlist_page_links( $params = array() )
{
	global $MainList;

	if( isset($MainList) )
	{
		$MainList->page_links( $params );
	}
}


/**
 * Stub
 *
 * Sets $Item ion global scope
 *
 * @return Item
 */
function & mainlist_get_item()
{
	global $MainList, $featured_displayed_item_ID;

	if( isset($MainList) )
	{
		$Item = & $MainList->get_item();

		if( $Item && $Item->ID == $featured_displayed_item_ID )
		{	// This post was already displayed as a Featured post, let's skip it and get the next one:
			$Item = & $MainList->get_item();
		}
	}
	else
	{
		$Item = NULL;
	}

	$GLOBALS['Item'] = & $Item;

	return $Item;
}


/**
 * Stub
 *
 * @return boolean true if empty MainList
 */
function display_if_empty( $params = array() )
{
	global $MainList;

	if( isset($MainList) )
	{
		return $MainList->display_if_empty( $params );
	}

	return NULL;
}


/**
 * COMPATIBILITY FUNCTION: Template tag for credits
 * Does nothing in whissip, since "creds" in global_Cache is considered spam,
 * controlled by b2evolution.net/FP.
 *
 * @param array
 */
function credits( $params = array() )
{
	return;
}


/**
 * Display rating as 5 stars
 */
function star_rating( $stars, $class = 'middle' )
{
	if( is_null($stars) )
	{
		return;
	}

	for( $i=1; $i<=5; $i++ )
	{
		if( $i <= $stars )
		{
			echo get_icon( 'star_on', 'imgtag', array( 'class'=>$class ) );
		}
		elseif( $i-.5 <= $stars )
		{
			echo get_icon( 'star_half', 'imgtag', array( 'class'=>$class ) );
		}
		else
		{
			echo get_icon( 'star_off', 'imgtag', array( 'class'=>$class ) );
		}
	}
}


/**
 * Display "Powered by $appname." link.
 */
function powered_by( $params = array() )
{
	global $app_name;

	// Make sure we are not missing any param:
	$params = array_merge( array(
			'block_start' => '<div class="powered_by">',
			'block_end'   => '</div>',
		), $params );

	echo $params['block_start'];
	echo '<a href="http://b2evolution.net/" target="_blank">Powered by '.$app_name.'.</a>';
	echo $params['block_end'];
}



/**
 * DEPRECATED
 */
function bloginfo( $what )
{
	global $Blog;
	$Blog->disp( $what );
}

/**
 * Display allowed tags for comments
 * (Mainly provided for WP compatibility. Not recommended for use)
 *
 * @param string format
 */
function comment_allowed_tags( $format = 'htmlbody' )
{
	global $comment_allowed_tags;

	echo format_to_output( $comment_allowed_tags, $format );
}

/**
 * DEPRECATED
 */
function link_pages()
{
	echo '<!-- link_pages() is DEPRECATED -->';
}


/**
 * Return a formatted percentage (should probably go to _misc.funcs)
 */
function percentage( $hit_count, $hit_total, $decimals = 1, $dec_point = '.' )
{
	return number_format( $hit_count * 100 / $hit_total, $decimals, $dec_point, '' ).'&nbsp;%';
}

function addup_percentage( $hit_count, $hit_total, $decimals = 1, $dec_point = '.' )
{
	static $addup = 0;

	$addup += $hit_count;
	return number_format( $addup * 100 / $hit_total, $decimals, $dec_point, '' ).'&nbsp;%';
}



/*
 * $Log$
 * Revision 1.81  2011/05/09 06:38:18  efy-asimo
 * Simple avatar modification update
 *
 * Revision 1.80  2011/03/24 15:15:05  efy-asimo
 * in-skin login - feature
 *
 * Revision 1.79  2011/03/04 08:20:44  efy-asimo
 * Simple avatar upload in the front office
 *
 * Revision 1.78  2010/12/18 00:23:05  fplanque
 * minor stuff & fixes
 *
 * Revision 1.77  2010/11/25 15:16:34  efy-asimo
 * refactor $Messages
 *
 * Revision 1.76  2010/09/15 13:04:06  efy-asimo
 * Cross post navigatation
 *
 * Revision 1.75  2010/07/26 06:52:15  efy-asimo
 * MFB v-4-0
 *
 * Revision 1.74  2010/04/13 22:23:11  blueyed
 * Check if $Session is existing before calling save on it.
 *
 * Revision 1.73  2010/03/18 21:17:31  blueyed
 * header_redirect: add call to debug_die, if headers have been sent already.
 *
 * Revision 1.72  2010/02/08 17:51:34  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.71  2009/12/22 23:13:38  fplanque
 * Skins v4, step 1:
 * Added new disp modes
 * Hooks for plugin disp modes
 * Enhanced menu widgets (BIG TIME! :)
 *
 * Revision 1.70  2009/12/08 20:21:10  fplanque
 * no message
 *
 * Revision 1.69  2009/12/07 20:02:38  leeturner2701
 * Added support for changing the request_title text for all disp types
 *
 * Revision 1.68  2009/12/05 01:22:00  fplanque
 * PageChace 304 handling
 *
 * Revision 1.67  2009/12/04 23:27:49  fplanque
 * cleanup Expires: header handling
 *
 * Revision 1.66  2009/12/02 03:54:39  fplanque
 * Attempt to let more CSS be loaded sequentially instead of serially (which happens with @import)
 * Also prepares for bundling.
 *
 * Revision 1.65  2009/12/02 01:00:07  fplanque
 * header_nocache & header_noexpire
 *
 * Revision 1.64  2009/12/01 02:09:32  fplanque
 * oops
 *
 * Revision 1.63  2009/12/01 01:52:08  fplanque
 * Fixed issue with Debuglog in case of redirect -- Thanks @blueyed for help.
 *
 * Revision 1.62  2009/11/11 03:24:50  fplanque
 * misc/cleanup
 *
 * Revision 1.61  2009/11/04 13:48:04  efy-maxim
 * new comment_allowed_tags function
 *
 * Revision 1.60  2009/10/13 20:59:49  blueyed
 * Create subdir for jquery plugins. Move jQuery UI in there.
 *
 * Revision 1.59  2009/09/05 22:12:34  fplanque
 * made dummy shorter :)
 *
 * Revision 1.58  2009/09/05 21:04:27  tblue246
 * require_js/require_css(): Add a dummy parameter to JS/CSS URLs to force a cache refresh after application upgrades.
 *
 * Revision 1.57  2009/05/20 13:53:45  fplanque
 * Return to a clean url after posting a comment
 *
 * Revision 1.56  2009/04/26 23:27:58  blueyed
 * doc
 *
 * Revision 1.55  2009/04/26 23:26:35  blueyed
 * add_js_for_toolbar: return if not logged in.
 *
 * Revision 1.54  2009/03/24 23:36:52  fplanque
 * minor
 *
 * Revision 1.53  2009/03/24 22:11:58  fplanque
 * Packaged inclusion of javascript for the toolbar
 *
 * Revision 1.52  2009/03/15 08:36:18  yabs
 * Adding helper functions
 * Adding translation strings for b2evoHelper object
 *
 * Revision 1.51  2009/03/08 23:57:39  fplanque
 * 2009
 *
 * Revision 1.50  2009/03/07 21:35:09  blueyed
 * doc
 *
 * Revision 1.49  2009/01/23 22:10:31  afwas
 * Remove javaScript popup calendar to be replaced with jQuery datepicker.
 *
 * Revision 1.48  2009/01/21 19:17:04  tblue246
 * Fix PHP notice ("Trying to get property of non-object...")
 *
 * Revision 1.47  2009/01/19 21:40:59  fplanque
 * Featured post proof of concept
 *
 * Revision 1.46  2008/12/30 23:00:41  fplanque
 * Major waste of time rolling back broken black magic! :(
 * 1) It was breaking the backoffice as soon as $admin_url was not a direct child of $baseurl.
 * 2) relying on dynamic argument decoding for backward comaptibility is totally unmaintainable and unreliable
 * 3) function names with () in log break searches big time
 * 4) complexity with no purpose (at least as it was)
 *
 * Revision 1.44  2008/11/12 13:59:19  blueyed
 * Fix add_css_headline(): remove unnecessary comment
 *
 * Revision 1.43  2008/11/07 20:07:14  blueyed
 * - Use add_headline() in add_js_headline()
 * - Add add_css_headline()
 *
 * Revision 1.42  2008/10/02 23:33:08  blueyed
 * - require_js(): remove dirty dependency handling for communication.js.
 * - Add add_js_headline() for adding inline JS and use it for admin already.
 *
 * Revision 1.41  2008/09/28 08:06:05  fplanque
 * Refactoring / extended page level caching
 *
 * Revision 1.40  2008/09/28 05:05:06  fplanque
 * minor
 *
 * Revision 1.39  2008/09/27 00:05:35  fplanque
 * doc, minor
 *
 * Revision 1.38  2008/09/15 21:53:09  blueyed
 * Fix lowercase check in require_css() again; broke it in last merge
 *
 * Revision 1.37  2008/07/10 23:21:42  blueyed
 * Merge trivial changes (I hope so) from my bzr branch
 *
 * Revision 1.36  2008/07/10 21:29:23  blueyed
 * base_tag(): remember used URL in , so this can be used/queried later.
 *
 * Revision 1.35  2008/07/10 21:26:52  blueyed
 * Fix deprecated message for link_pages()
 *
 * Revision 1.34  2008/07/03 19:25:10  blueyed
 * Remove var_dump
 *
 * Revision 1.33  2008/07/03 19:15:19  blueyed
 * require_js(): add TODOs about dependency handling; fix 'already included?' check (case insensitivity)
 * require_css(): fix 'already included?' check (case insensitivity)
 *
 * Revision 1.32  2008/07/03 10:35:22  yabs
 * minor fix
 *
 * Revision 1.31  2008/07/03 09:51:52  yabs
 * widget UI
 *
 * Revision 1.30  2008/05/11 01:09:42  fplanque
 * always output charset header + meta
 *
 * Revision 1.29  2008/04/26 22:20:44  fplanque
 * Improved compatibility with older skins.
 *
 * Revision 1.28  2008/04/13 23:38:53  fplanque
 * Basic public user profiles
 *
 * Revision 1.27  2008/04/04 23:56:02  fplanque
 * avoid duplicate content in meta tags
 *
 * Revision 1.26  2008/04/04 16:02:14  fplanque
 * uncool feature about limiting credits
 *
 * Revision 1.25  2008/03/31 00:27:49  fplanque
 * Enhanced comment moderation
 *
 * Revision 1.24  2008/03/30 23:37:22  fplanque
 * TODO
 *
 * Revision 1.22  2008/03/24 03:07:40  blueyed
 * Enable make-redirects-absolute in header_redirect() again
 *
 * Revision 1.21  2008/03/21 19:42:44  fplanque
 * enhanced 404 handling
 *
 * Revision 1.20  2008/03/16 14:19:38  fplanque
 * no message
 *
 * Revision 1.19  2008/03/15 19:07:25  fplanque
 * no message
 *
 * Revision 1.18  2008/02/22 00:39:29  blueyed
 * doc
 *
 * Revision 1.17  2008/02/19 11:11:17  fplanque
 * no message
 *
 * Revision 1.16  2008/02/12 04:59:01  fplanque
 * more custom field handling
 *
 * Revision 1.15  2008/02/10 00:58:57  fplanque
 * no message
 *
 * Revision 1.14  2008/01/21 09:35:23  fplanque
 * (c) 2008
 *
 * Revision 1.13  2008/01/05 02:25:23  fplanque
 * refact
 *
 * Revision 1.12  2007/11/08 17:54:23  blueyed
 * mainlist_get_item(): fixed return by reference (patch by Austriaco)
 *
 * Revision 1.11  2007/11/03 23:54:39  fplanque
 * skin cleanup continued
 *
 * Revision 1.10  2007/11/03 21:04:25  fplanque
 * skin cleanup
 *
 * Revision 1.9  2007/10/01 01:06:31  fplanque
 * Skin/template functions cleanup.
 *
 * Revision 1.8  2007/09/30 04:55:34  fplanque
 * request_title() cleanup
 *
 * Revision 1.7  2007/09/28 09:28:36  fplanque
 * per blog advanced SEO settings
 *
 * Revision 1.6  2007/09/23 18:55:17  fplanque
 * attempting to debloat. The Log class is insane.
 *
 * Revision 1.5  2007/09/13 23:39:50  blueyed
 * trans: use printf
 *
 * Revision 1.4  2007/08/05 17:23:33  waltercruz
 * Feed of the comments on a specific post. Just add the &id=? or &title=? to the URL
 *
 * Revision 1.3  2007/07/01 03:57:20  fplanque
 * toolbar eveywhere
 *
 * Revision 1.2  2007/06/30 22:03:34  fplanque
 * cleanup
 *
 * Revision 1.1  2007/06/25 10:58:53  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.30  2007/06/24 20:19:00  personman2
 * Don't add .js or .css on when they're not there.  Added documentation for require_css
 *
 * Revision 1.29  2007/06/24 19:43:39  personman2
 * changing backoffice over to new js and css handling
 *
 * Revision 1.26  2007/06/24 01:05:31  fplanque
 * skin_include() now does all the template magic for skins 2.0.
 * .disp.php templates still need to be cleaned up.
 *
 * Revision 1.25  2007/06/23 00:12:26  fplanque
 * doc
 *
 * Revision 1.24  2007/06/22 15:44:25  personman2
 * Moved output of require_js() to another callback, as Daniel suggested
 *
 * Revision 1.23  2007/06/22 02:30:12  personman2
 * Added require_js() function to add javascript files.  Can be called from a skin or from a plugin using the SkinBeginHtmlHead hook.
 *
 * Revision 1.22  2007/05/02 20:39:27  fplanque
 * meta robots handling
 *
 * Revision 1.21  2007/04/26 00:11:08  fplanque
 * (c) 2007
 *
 * Revision 1.20  2007/03/25 10:20:02  fplanque
 * cleaned up archive urls
 *
 * Revision 1.19  2007/03/04 21:42:49  fplanque
 * category directory / albums
 *
 * Revision 1.18  2007/03/04 19:47:37  fplanque
 * enhanced toolbar menu
 *
 * Revision 1.17  2007/03/04 05:24:52  fplanque
 * some progress on the toolbar menu
 *
 * Revision 1.16  2007/01/26 04:52:53  fplanque
 * clean comment popups (skins 2.0)
 *
 * Revision 1.15  2007/01/25 13:41:52  fplanque
 * wording
 *
 * Revision 1.14  2006/12/05 00:01:15  fplanque
 * enhanced photoblog skin
 *
 * Revision 1.13  2006/11/24 18:27:27  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 */
?>
