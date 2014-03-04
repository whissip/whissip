<?php
/**
 * This file implements misc functions that handle output of the HTML page.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2013 by Francois Planque - {@link http://fplanque.com/}
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
function header_content_type( $type = 'text/html', $charset = '#' )
{
	global $io_charset;
	global $content_type_header;

	$content_type_header = 'Content-type: '.$type;

	if( !empty($charset) )
	{
		if( $charset == '#' )
		{
			$charset = $io_charset;
		}

		$content_type_header .= '; charset='.$charset;
	}

	header( $content_type_header );
}


/**
 * This is a placeholder for future development.
 *
 * @param string content-type; override for RSS feeds
 * @param integer seconds
 * @param string charset
 * @param boolean flush already collected content from the PageCache
 */
function headers_content_mightcache( $type = 'text/html', $max_age = '#', $charset = '#', $flush_pagecache = true )
{
	global $Messages, $is_admin_page;
	global $PageCache, $Debuglog;

	header_content_type( $type, $charset );

	if( empty($max_age) || $is_admin_page || is_logged_in() || $Messages->count() )
	{	// Don't cache if no max_age given
		// + NEVER EVER allow admin pages to cache
		// + NEVER EVER allow logged in data to be cached
		// + NEVER EVER allow transactional Messages to be cached!:
		header_nocache();

		// Check server caching too, but note that this is a different caching process then caching on the client
		// It's important that this is a double security check only and server caching should be prevented before this
		// If something should not be cached on the client, it should never be cached on the server either
		if( !empty( $PageCache ) )
		{ // Abort PageCache collect
			$Debuglog->add( 'Abort server caching in headers_content_mightcache() function. This should have been prevented!' );
			$PageCache->abort_collect( $flush_pagecache );
		}
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
	/**
	 * put your comment there...
	 *
	 * @var Hit
	 */
	global $Hit;
	global $baseurl, $Blog, $htsrv_url_sensitive;
	global $Session, $Debuglog, $Messages;
	global $http_response_code;

	// TODO: fp> get this out to the caller, make a helper func like get_returnto_url()
	if( empty($redirect_to) )
	{ // see if there's a redirect_to request param given:
		$redirect_to = param( 'redirect_to', 'url', '' );

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

	if( ! empty($Session) )
	{	// Session is required here

		// Transfer of Debuglog to next page:
		if( $Debuglog->count('all') )
		{	// Save Debuglog into Session, so that it's available after redirect (gets loaded by Session constructor):
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
		{	// Set Messages into user's session, so they get restored on the next page (after redirect):
			$Session->set( 'Messages', $Messages );
		 // echo 'Passing Messages to next page';
		}

		$Session->dbsave(); // If we don't save now, we run the risk that the redirect goes faster than the PHP script shutdown.
	}

	// see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
	switch( $http_response_code )
	{
		case 301:
			// This should be a permanent move redirect!
			header_http_response( '301 Moved Permanently' );
			break;

		case 303:
			// This should be a "follow up" redirect
			// Note: Also see http://de3.php.net/manual/en/function.header.php#50588 and the other comments around
			header_http_response( '303 See Other' );
			break;

		case 302:
		default:
			header_http_response( '302 Found' );
	}

	// debug_die($redirect_to);
	if( headers_sent($filename, $line) )
	{
		debug_die( sprintf('Headers have already been sent in %s on line %d.', basename($filename), $line)
						.'<br />Cannot <a href="'.htmlspecialchars($redirect_to).'">redirect</a>.' );
	}
	header( 'Location: '.$redirect_to, true, $http_response_code ); // explictly setting the status is required for (fast)cgi
	exit(0);
}



/**
 * Sends HTTP headers to avoid caching of the page at the browser level
 * (at least without revalidating with the server to make sure whether the content has changed or not).
 *
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
 * Generate an etag to identify the version of the current page.
 * We use this primarily to make a difference between the same page that has been generated for anonymous users
 * and a version that has been generated for a specific user.
 *
 * A common problem without this would be that when users log out, the page cache would tell them "304 Not Modified"
 * based on the date of the cache and then the browser would show a locally cached version of the page that includes
 * the evobar.
 *
 * When a specific user logs out, the browser will send back the Etag of the logged in version it got and we will
 * be able to detect that this is not a "304 Not Modified" case -> we will send back the anonymou version of the page.
 */
function gen_current_page_etag()
{
	global $current_User, $Messages;

	if( isset($current_User) )
	{
		$etag = 'user:'.$current_User->ID;
	}
	else
	{
		$etag = 'user:anon';
	}

	if( $Messages->count() )
	{	// This case has never been observed yet, but let's forward protect us against client side cached messages
		$etag .= '-msg:'.md5($Messages->get_string('',''));
	}

	return '"'.$etag.'"';
}


/**
 * This adds teh etag header
 *
 * @param string etag MUST be "quoted"
 */
function header_etag( $etag )
{
	header( 'ETag: '.$etag );
}


/**
 * Get global title matching filter params
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
function get_request_title( $params = array() )
{
	global $MainList, $preview, $disp, $action, $current_User, $Blog, $admin_url;

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
			'msgform_text'        => T_('Sending a message'),
			'messages_text'       => T_('Messages'),
			'contacts_text'       => T_('Contacts'),
			'login_text'          => /* TRANS: trailing space = verb */ T_('Login '),
			'register_text'       => T_('Register'),
			'req_validatemail'    => T_('Account activation'),
			'account_activation'  => T_('Account activation'),
			'lostpassword_text'   => T_('Lost password?'),
			'profile_text'        => T_('User Profile'),
			'avatar_text'         => T_('Profile picture'),
			'pwdchange_text'      => T_('Password change'),
			'userprefs_text'      => T_('User preferences'),
			'user_text'           => T_('User: %s'),
			'users_text'          => T_('Users'),
			'closeaccount_text'   => T_('Close account'),
			'subs_text'           => T_('Notifications'),
			'comments_text'       => T_('Latest Comments'),
			'feedback-popup_text' => T_('Feedback'),
			'edit_text_create'    => T_('New post'),
			'edit_text_update'    => T_('Editing post'),
			'edit_text_copy'      => T_('Duplicating post'),
			'edit_comment_text'   => T_('Editing comment'),
			'front_text'          => '',		// We don't want to display a special title on the front page
			'posts_text'          => '#',
			'useritems_text'      => T_('User posts'),
			'usercomments_text'   => T_('User comments'),
		), $params );

	if( $params['auto_pilot'] == 'seo_title' )
	{	// We want to use the SEO title autopilot. Do overrides:
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

		case 'threads':
		case 'messages':
			// We are requesting the messages form
			$thrd_ID = param( 'thrd_ID', 'integer', 0 );
			if( empty( $thrd_ID ) )
			{
				$r[] = $params['messages_text'];
			}
			else
			{	// We get a thread title by ID
				load_class( 'messaging/model/_thread.class.php', 'Thread' );
				$ThreadCache = & get_ThreadCache();
				if( $Thread = $ThreadCache->get_by_ID( $thrd_ID, false ) )
				{	// Thread exists and we get a title
					if( $params['auto_pilot'] == 'seo_title' )
					{	// Display thread title only for tag <title>
						$r[] = $Thread->title;
					}
				}
				else
				{	// Bad request with not existing thread
					$r[] = strip_tags( $params['messages_text'] );
				}
			}
			break;

		case 'contacts':
			// We are requesting the message form:
			$r[] = $params['contacts_text'];
			break;

		case 'login':
			// We are requesting the login form:
			if( $action == 'req_validatemail' )
			{
				$r[] = $params['req_validatemail'];
			}
			else
			{
				$r[] = $params['login_text'];
			}
			break;

		case 'register':
			// We are requesting the registration form:
			$r[] = $params['register_text'];
			break;

		case 'activateinfo':
			// We are requesting the activate info form:
			$r[] = $params['account_activation'];
			break;

		case 'lostpassword':
			// We are requesting the lost password form:
			$r[] = $params['lostpassword_text'];
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
			// We are requesting the user page:
			$user_ID = param( 'user_ID', 'integer', 0 );
			$UserCache = & get_UserCache();
			$User = & $UserCache->get_by_ID( $user_ID, false, false );
			$user_login = $User ? $User->get( 'login' ) : '';
			$r[] = sprintf( $params['user_text'], $user_login );
			break;

		case 'users':
			$r[] = $params['users_text'];
			break;

		case 'closeaccount':
			$r[] = $params['closeaccount_text'];
			break;

		case 'edit':
			$action = param_action(); // Edit post by switching into 'In skin' mode from Back-office
			$p = param( 'p', 'integer', 0 ); // Edit post from Front-office
			$cp = param( 'cp', 'integer', 0 ); // Copy post from Front-office
			if( $action == 'edit_switchtab' || $p > 0 )
			{	// Edit post
				$title = $params['edit_text_update'];
			}
			else if( $cp > 0 )
			{	// Copy post
				$title = $params['edit_text_copy'];
			}
			else
			{	// Create post
				$title = $params['edit_text_create'];
			}
			if( $params['auto_pilot'] != 'seo_title' )
			{	// Add advanced edit and close icon
				global $edited_Item;
				if( !empty( $edited_Item ) && $edited_Item->ID > 0 )
				{	// Set the cancel editing url as permanent url of the item
					$cancel_url = $edited_Item->get_permanent_url();
				}
				else
				{	// Set the cancel editing url to home page of the blog
					$cancel_url = $Blog->gen_blogurl();
				}

				$title .= '<span class="title_action_icons">';
				if( $current_User->check_perm( 'admin', 'normal' ) )
				{
					global $advanced_edit_link;
					$title .= action_icon( T_('Go to advanced edit screen'), 'edit', $advanced_edit_link['href'], ' '.T_('Advanced editing'), NULL, 3, array( 'onclick' => $advanced_edit_link['onclick'] ) );
				}
				$title .= action_icon( T_('Cancel editing'), 'close', $cancel_url, ' '.T_('Cancel editing'), NULL, 3 );
				$title .= '</span>';
			}
			$r[] = $title;
			break;

		case 'edit_comment':
			global $comment_Item, $edited_Comment;
			$title = $params['edit_comment_text'];
			if( $params['auto_pilot'] != 'seo_title' )
			{	// Add advanced edit and close icon
				$title .= '<span class="title_action_icons">';
				if( $current_User->check_perm( 'admin', 'normal' ) )
				{
					$advanced_edit_url = url_add_param( $admin_url, 'ctrl=comments&amp;action=edit&amp;blog='.$Blog->ID.'&amp;comment_ID='.$edited_Comment->ID );
					$title .= action_icon( T_('Go to advanced edit screen'), 'edit', $advanced_edit_url, ' '.T_('Advanced editing'), NULL, 3, array( 'onclick' => 'return switch_edit_view();' ) );
				}
				if( empty( $comment_Item ) )
				{
					$comment_Item = & $edited_Comment->get_Item();
				}
				if( !empty( $comment_Item ) )
				{
					$title .= action_icon( T_('Cancel editing'), 'close', url_add_tail( $comment_Item->get_permanent_url(), '#c'.$edited_Comment->ID ), ' '.T_('Cancel editing'), NULL, 3 );
				}
				$title .= '</span>';
			}
			$r[] = $title;
			break;

		case 'useritems':
			// We are requesting the user items list:
			$r[] = $params['useritems_text'];
			break;

		case 'usercomments':
			// We are requesting the user comments list:
			$r[] = $params['usercomments_text'];
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
	else
	{	// never return array()
		$r = '';
	}

	return $r;
}


/**
 * Display a global title matching filter params
 *
 * @param array params
 *        - "auto_pilot": "seo_title": Use the SEO title autopilot. (Default: "none")
 */
function request_title( $params = array() )
{
	$r = get_request_title( $params );

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
 * Memorize that a specific javascript file will be required by the current page.
 * All requested files will be included in the page head only once (when headlines is called)
 *
 * Accepts absolute urls, filenames relative to the rsc/js directory and certain aliases, like 'jquery' and 'jquery_debug'
 * If 'jquery' is used and $debug is set to true, the 'jquery_debug' is automatically swapped in.
 * Any javascript added to the page is also added to the $required_js array, which is then checked to prevent adding the same code twice
 *
 * @todo dh>merge with require_css()
 * @param string alias, url or filename (relative to rsc/js) for javascript file
 * @param boolean|string Is the file's path relative to the base path/url?
 */
function require_js( $js_file, $relative_to = 'rsc_url' )
{
	global $rsc_url, $debug, $app_version;
	static $required_js;

	$js_aliases = array(
		'#jquery#' => 'jquery.min.js',
		'#jquery_debug#' => 'jquery.js',
		'#jqueryUI#' => 'jquery/jquery.ui.all.min.js',
		'#jqueryUI_debug#' => 'jquery/jquery.ui.all.js',
	);

	if( in_array( $js_file, array( '#jqueryUI#', '#jqueryUI_debug#' ) ) )
	{	// Dependency : ensure jQuery is loaded
		require_js( '#jquery#', $relative_to );
	}
	elseif( $js_file == 'communication.js' )
	{ // jQuery dependency
		require_js( '#jquery#', $relative_to );
	}

	if( !empty( $js_aliases[$js_file]) )
	{ // We are requsting an alias
		if ( $js_file == '#jquery#' && $debug )
		{
			$js_file = '#jquery_debug#';
		}
		$js_file = $js_aliases[$js_file];

		if( $relative_to === 'relative' || $relative_to === true )
		{	// Aliases cannot be relative, make it relative to $rsc_url
			$relative_to = 'rsc_url';
		}
	}


	if( $relative_to === 'relative' || $relative_to === true )
	{	// Make the file relative to current page <base>:
		$js_url = $js_file;
	}
	elseif( preg_match('~^https?://~', $js_file ) )
	{ // It's an absolute url, keep it as is:
		$js_url = $js_file;
	}
	elseif( $relative_to === 'rsc_url' || $relative_to === false )
	{	// Get the file from $rsc_url:
		$js_url = $rsc_url.'js/'.$js_file;
	}
	elseif( $relative_to === 'blog' )
	{	// Get the file from $rsc_url:
		global $Blog;
		if( !empty( $Blog ) )
		{
			$js_url = $Blog->get_local_rsc_url().'js/'.$js_file;
		}
		else
		{
			$js_url = $rsc_url.'js/'.$js_file;
		}
	}
	else
	{
		debug_die('Unknown $relative to argument in require_js()');
	}


	// Be sure to get a fresh copy of this JS file after application upgrades:
	$js_url = url_add_param( $js_url, 'v='.$app_version );

	// Add to headlines, if not done already:
	if( empty( $required_js ) || ! in_array( strtolower($js_url), $required_js ) )
	{
		$required_js[] = strtolower($js_url);
		add_headline( '<script type="text/javascript" src="'.$js_url.'"></script>' );
	}
}


/**
 * Memorize that a specific css that file will be required by the current page.
 * All requested files will be included in the page head only once (when headlines is called)
 *
 * Accepts absolute urls, filenames relative to the rsc/css directory.
 * Set $relative_to_base to TRUE to prevent this function from adding on the rsc_path
 *
 * @todo dh>merge with require_js()
 * @param string alias, url or filename (relative to rsc/css) for CSS file
 * @param boolean|string Is the file's path relative to the base path/url?
 * @param string title.  The title for the link tag
 * @param string media.  ie, 'print'
 * @param string version number to append at the end of requested url to avoid getting an old version from the cache
 */
function require_css( $css_file, $relative_to = 'rsc_url', $title = NULL, $media = NULL, $version = '#' )
{
	global $rsc_url, $debug, $app_version;
	static $required_css;

	if( $relative_to === 'relative' || $relative_to === true )
	{	// Make the file relative to current page <base>:
		$css_url = $css_file;
	}
	elseif( preg_match('~^https?://~', $css_file ) )
	{ // It's an absolute url, keep it as is:
		$css_url = $css_file;
	}
	elseif( $relative_to === 'rsc_url' || $relative_to === false )
	{	// Get the file from $rsc_url:
		$css_url = $rsc_url.'css/'.$css_file;
	}
	elseif( $relative_to === 'blog' )
	{	// Get the file from $rsc_url:
		global $Blog;
		$css_url = $Blog->get_local_rsc_url().'css/'.$css_file;
	}
	else
	{
		debug_die('Unknown $relative to argument in require_css()');
	}

	if( !empty($version) )
	{	// Be sure to get a fresh copy of this CSS file after application upgrades:
		if( $version == '#' )
		{
			$version = $app_version;
		}
		$css_url = url_add_param( $css_url, 'v='.$version );
	}

	// Add to headlines, if not done already:
	// fp> TODO: check for url without version to avoid duplicate load due to lack of verison in @import statements
	if( empty( $required_css ) || ! in_array( strtolower($css_url), $required_css ) )
	{
		$required_css[] = strtolower($css_url);

		$start_link_tag = '<link rel="stylesheet"';
		if ( !empty( $title ) ) $start_link_tag .= ' title="' . $title . '"';
		if ( !empty( $media ) ) $start_link_tag .= ' media="' . $media . '"';
		$start_link_tag .= ' type="text/css" href="';
		$end_link_tag = '" />';
		add_headline( $start_link_tag . $css_url . $end_link_tag );
	}

}


/**
 * Memorize that a specific js helper will be required by the current page.
 * This allows to require JS + SS + do init.
 *
 * All requested helpers will be included in the page head only once (when headlines is called)
 * Requested helpers should add their required translation strings and any other settings
 *
 * @param string helper, name of the required helper
 */
function require_js_helper( $helper = '', $relative_to = 'rsc_url' )
{
	static $helpers;

	if( empty( $helpers ) || !in_array( $helper, $helpers ) )
	{ // Helper not already added, add the helper:

		switch( $helper )
		{
			case 'helper' :
				// main helper object required
				global $debug;
				require_js( '#jquery#', $relative_to ); // dependency
				require_js( 'helper.js', $relative_to );
				add_js_headline('jQuery(document).ready(function()
				{
					b2evoHelper.Init({
						debug:'.( $debug ? 'true' : 'false' ).'
					});
				});');
				break;

			case 'communications' :
				// communications object required
				require_js_helper('helper', $relative_to ); // dependency

				global $dispatcher;
				require_js( 'communication.js', $relative_to );
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

			case 'colorbox':
				// Colorbox: a lightweight Lightbox alternative -- allows zooming on images and slideshows in groups of images
				// Added by fplanque - (MIT License) - http://colorpowered.com/colorbox/
				require_js( '#jqueryUI#', $relative_to );
				require_js( 'voting.js', $relative_to );
				require_js( 'colorbox/jquery.colorbox-min.js', $relative_to );
				require_css( 'colorbox/colorbox.css', $relative_to );
				if( is_logged_in() )
				{	// If user is logged in - display a voting panel
					$colorbox_params = ',
								displayVoting: true,
								votingUrl: "'.get_secure_htsrv_url().'anon_async.php?action=voting&vote_type=file&'.url_crumb( 'voting' ).'",
								minWidth: 345';
				}
				else
				{	// Set minimum width
					$colorbox_params = ',
								minWidth: 255';
				}
				add_js_headline('jQuery(document).ready(function()
						{
							jQuery("a[rel^=\'lightbox\']").colorbox(
							{
								maxWidth: "95%",
								maxHeight: "90%",
								slideshow: true,
								slideshowAuto: false'.
								$colorbox_params.'
							} );
						} );' );
				// TODO: translation strings
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
 * Add a Javascript headline.
 * This is an extra function, to provide consistent wrapping and allow to bundle it
 * (i.e. create a bundle with all required JS files and these inline code snippets,
 *  in the correct order).
 * @param string Javascript
 */
function add_js_headline($headline)
{
	add_headline("<script type=\"text/javascript\">\n\t/* <![CDATA[ */\n\t\t"
		.$headline."\n\t/* ]]> */\n\t</script>");
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
	add_headline("<style type=\"text/css\">\n\t".$headline."\n\t</style>");
}


/**
 * Registers all the javascripts needed by the toolbar menu
 *
 * @todo fp> include basic.css ? -- rename to add_headlines_for* -- potential problem with inclusion order of CSS files!!
 *       dh> would be nice to have the batch of CSS in a separate file. basic.css would get included first always, then e.g. this toolbar.css.
 */
function add_js_for_toolbar( $relative_to = 'rsc_url' )
{
	if( ! is_logged_in() )
	{ // the toolbar (blogs/skins/_toolbar.inc.php) gets only used when logged in.
		return false;
	}

	require_js( '#jquery#', $relative_to );
	// Superfish menus:
	require_js( 'hoverintent.js', $relative_to );
	require_js( 'superfish.js', $relative_to );
	add_js_headline( '
	jQuery( function() {
	  jQuery("ul.sf-menu").superfish( {
	    delay: 500, // mouseout
	    animation: {opacity:"show",height:"show"},
	    speed: "fast"
	  } );
	} );');

	return true;
}


/**
 * Registers headlines required by AJAX forms, but only if javascript forms are enabled in blog settings.
 */
function init_ajax_forms( $relative_to = 'blog' )
{
	global $Blog;

	if( !empty($Blog) && $Blog->get_setting('ajax_form_enabled') )
	{
		require_js( 'communication.js', $relative_to );
	}
}


/**
 * Registers headlines required by comments forms
 */
function init_ratings_js( $relative_to = 'blog', $force_init = false )
{
	global $Item;

	// fp> Note, the following test is good for $disp == 'single', not for 'posts'
	if( $force_init || ( !empty($Item) && $Item->can_rate() ) )
	{
		require_js( '#jquery#', $relative_to ); // dependency
		require_js( 'jquery/jquery.raty.min.js', $relative_to );
	}
}


/**
 * Registers headlines required to a bubbletip above user login.
 */
function init_bubbletip_js( $relative_to = 'rsc_url' )
{
	if( ! check_setting( 'bubbletip' ) )
	{ // If setting "bubbletip" is OFF for current case
		return;
	}

	require_js( '#jquery#', $relative_to ); // dependency
	require_js( 'jquery/jquery.bubbletip.min.js', $relative_to );
	require_js( 'bubbletip.js', $relative_to );
	require_css( 'jquery/bubbletip/bubbletip.css', $relative_to );
	add_headline('<!--[if IE]>');
	require_css( 'jquery/bubbletip/bubbletip-IE.css', $relative_to );
	add_headline('<![endif]-->');
}


/**
 * Registers headlines required to display a bubbletip to the right of user multi-field.
 */
function init_userfields_js( $relative_to = 'rsc_url' )
{
	require_js( '#jquery#', $relative_to ); // dependency
	require_js( 'jquery/jquery.bubbletip.min.js', $relative_to );
	require_js( 'userfields.js', $relative_to );
	require_css( 'jquery/bubbletip/bubbletip.css', $relative_to );
	add_headline('<!--[if IE]>');
	require_css( 'jquery/bubbletip/bubbletip-IE.css', $relative_to );
	add_headline('<![endif]-->');
}


/**
 * Registers headlines required to display a bubbletip to the right of plugin help icon.
 */
function init_plugins_js( $relative_to = 'rsc_url' )
{
	require_js( '#jquery#', $relative_to ); // dependency
	require_js( 'jquery/jquery.bubbletip.min.js', $relative_to );
	require_js( 'plugins.js', $relative_to );
	require_css( 'jquery/bubbletip/bubbletip.css', $relative_to );
	add_headline('<!--[if IE]>');
	require_css( 'jquery/bubbletip/bubbletip-IE.css', $relative_to );
	add_headline('<![endif]-->');
}


/**
 * Registers headlines for initialization of datepicker inputs
 */
function init_datepicker_js( $relative_to = 'rsc_url' )
{
	require_js( '#jquery#', $relative_to );
	require_js( '#jqueryUI#', $relative_to );

	$datefmt = locale_datefmt();
	$datefmt = str_replace( array( 'd', 'j', 'm', 'Y' ), array( 'dd', 'd', 'mm', 'yy' ), $datefmt );
	require_css( 'jquery/smoothness/jquery-ui.css' );
	add_js_headline( 'jQuery(document).ready( function(){
		var monthNames = ["'.T_('January').'","'.T_('February').'", "'.T_('March').'",
						  "'.T_('April').'", "'.T_('May').'", "'.T_('June').'",
						  "'.T_('July').'", "'.T_('August').'", "'.T_('September').'",
						  "'.T_('October').'", "'.T_('November').'", "'.T_('December').'"];

		var dayNamesMin = ["'.T_('Sun').'", "'.T_('Mon').'", "'.T_('Tue').'",
						  "'.T_('Wed').'", "'.T_('Thu').'", "'.T_('Fri').'", "'.T_('Sat').'"];

		var docHead = document.getElementsByTagName("head")[0];
		for (i=0;i<dayNamesMin.length;i++)
			dayNamesMin[i] = dayNamesMin[i].substr(0, 2)

		jQuery(".form_date_input").datepicker({
			dateFormat: "'.$datefmt.'",
			monthNames: monthNames,
			dayNamesMin: dayNamesMin,
			firstDay: '.locale_startofweek().'
		})
	})' );
}


/**
 * Registers headlines for initialization of scroll wide
 */
function init_scrollwide_js( $relative_to = 'rsc_url' )
{
	require_js( '#jquery#', $relative_to ); // dependency
	require_js( 'jquery/jquery.scrollwide.min.js', $relative_to );
	add_js_headline( 'jQuery( document ).ready( function()
		{
			jQuery( "div.wide_scroll" ).scrollWide( { scroll_time: 100 } );
		} )' );
	// require_css( 'jquery/scrollwide/jquery.scrollwide.css', $relative_to );
}


/**
 * Registers headlines for initialization of jQuery Tokeninput plugin
 */
function init_tokeninput_js( $relative_to = 'rsc_url' )
{
	require_js( '#jquery#', $relative_to ); // dependency
	require_js( 'jquery/jquery.tokeninput.js', $relative_to );
	require_css( 'jquery/jquery.token-input-facebook.css', $relative_to );
}


/**
 * Registers headlines for initialization of functions to work with Results tables
 */
function init_results_js( $relative_to = 'rsc_url' )
{
	require_js( '#jquery#', $relative_to ); // dependency
	require_js( 'results.js', $relative_to );
}


/**
 * Registers headlines for initialization of functions to work with Results tables
 */
function init_voting_comment_js( $relative_to = 'rsc_url' )
{
	global $Blog;

	if( empty( $Blog ) || ! is_logged_in( false ) || ! $Blog->get_setting('allow_rating_comment_helpfulness') )
	{	// If User is not logged OR Users cannot vote
		return false;
	}

	require_js( '#jquery#', $relative_to ); // dependency
	require_js( 'voting.js', $relative_to );
	add_js_headline( '
	jQuery( document ).ready( function()
	{
		var comment_voting_url = "'.get_secure_htsrv_url().'anon_async.php?action=voting&vote_type=comment&'.url_crumb( 'voting' ).'";
		jQuery( "span[id^=vote_helpful_]" ).each( function()
		{
			init_voting_bar( jQuery( this ), comment_voting_url, jQuery( this ).find( "#votingID" ).val(), false );
		} );
	} );
	' );
}


/**
 * Registers headlines for initialization of colorpicker inputs
 */
function init_colorpicker_js( $relative_to = 'rsc_url' )
{
	require_js( '#jquery#', $relative_to );
	require_js( 'jquery/jquery.farbtastic.min.js', $relative_to );
	require_css( 'jquery/farbtastic/farbtastic.css' );
	add_js_headline( 'jQuery(document).ready( function() {
		jQuery( "body" ).append( "<div id=\"colorpicker\"></div>" );
		var farbtastic_colorpicker = jQuery.farbtastic( "#colorpicker" );
		jQuery( ".form_color_input" )
			.each( function () { farbtastic_colorpicker.linkTo( this ); } )
			.blur( function () { jQuery( "#colorpicker" ).hide(); } )
			.focus( function () {
				farbtastic_colorpicker.linkTo( this );
				jQuery( "#colorpicker" ).css( {
					top: jQuery( this ).offset().top - 90,
					left: jQuery( this ).offset().left + jQuery( this ).width() + 15,
				} ).show();
			} );
	} );' );
}


/**
 * Registers headlines required to autocomplete the user logins
 *
 * @param string alias, url or filename (relative to rsc/css, rsc/js) for JS/CSS files
 */
function init_autocomplete_login_js( $relative_to = 'rsc_url' )
{
	require_js( '#jquery#', $relative_to ); // dependency

	// Use hintbox plugin of jQuery

	// Add jQuery hintbox (autocompletion).
	// Form 'username' field requires the following JS and CSS.
	// fp> TODO: think about a way to bundle this with other JS on the page -- maybe always load hintbox in the backoffice
	//     dh> Handle it via http://www.appelsiini.net/projects/lazyload ?
	// dh> TODO: should probably also get ported to use jquery.ui.autocomplete (or its successor)
	require_css( 'jquery/jquery.hintbox.css', $relative_to );
	require_js( 'jquery/jquery.hintbox.min.js', $relative_to );
	add_js_headline( 'jQuery( document ).ready( function()
	{
		jQuery( "input.autocomplete_login" ).hintbox(
		{
			url: "'.get_secure_htsrv_url().'async.php?action=get_login_list",
			matchHint: true,
			autoDimentions: true
		} );
	} );' );
}


/**
 * Outputs the collected HTML HEAD lines.
 * @see add_headline()
 * @return string
 */
function include_headlines()
{
	global $headlines;

	if( $headlines )
	{
		echo "\n\t<!-- headlines: -->\n\t".implode( "\n\t", $headlines );
		echo "\n\n";
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
	global $js_translations;
	if( empty( $js_translations ) )
	{ // nothing to do
		return;
	}
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
 * Stub: Links to previous and next user in single user mode
 */
function user_prevnext_links( $params = array() )
{
	global $UserList;

	if( isset($UserList) )
	{
		$UserList->prevnext_user_links( $params );
	}
}


/**
 * Stub
 */
function messages( $params = array() )
{
	global $Messages;

	if( isset( $params['has_errors'] ) )
	{
		$params['has_errors'] = $Messages->has_errors();
	}
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
	global $MainList, $featured_displayed_item_IDs;

	if( isset($MainList) )
	{
		$Item = & $MainList->get_item();

		if( $Item && in_array( $Item->ID, $featured_displayed_item_IDs ) )
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
	global $MainList, $featured_displayed_item_IDs;

	if( isset( $MainList ) && empty( $featured_displayed_item_IDs ) )
	{
		return $MainList->display_if_empty( $params );
	}

	return NULL;
}


/**
 * Template tag for credits
 *
 * Note: You can limit (and even disable) the number of links being displayed here though the Admin interface:
 * Blog Settings > Advanced > Software credits
 *
 * @param array
 */
function credits( $params = array() )
{
	/**
	 * @var AbstractSettings
	 */
	global $global_Cache;
	global $Blog;

	// Make sure we are not missing any param:
	$params = array_merge( array(
			'list_start'  => ' ',
			'list_end'    => ' ',
			'item_start'  => ' ',
			'item_end'    => ' ',
			'separator'   => ',',
			'after_item'  => '#',
		), $params );


	$cred_links = $global_Cache->get( 'creds' );
	if( empty( $cred_links ) )
	{	// Use basic default:
		$cred_links = unserialize('a:2:{i:0;a:2:{i:0;s:24:"http://b2evolution.net/r";i:1;s:18:"free blog software";}i:1;a:2:{i:0;s:36:"http://b2evolution.net/web-hosting/r";i:1;s:19:"quality web hosting";}}');
	}

	$max_credits = (empty($Blog) ? NULL : $Blog->get_setting( 'max_footer_credits' ));

	display_list( $cred_links, $params['list_start'], $params['list_end'], $params['separator'], $params['item_start'], $params['item_end'], NULL, $max_credits );
}


/**
 * Get rating as 5 stars
 *
 * @param integer Number of stars
 * @param string Class name
 * @return string Template for star rating
 */
function get_star_rating( $stars, $class = 'not-used-any-more' )
{
	if( is_null( $stars ) )
	{
		return;
	}

	$average = ceil( ( $stars ) / 5 * 100 );

	return '<div class="star_rating"><div style="width:'.$average.'%">'.$stars.' stars</div></div>';
}


/**
 * Display rating as 5 stars
 *
 * @param integer Number of stars
 * @param string Class name
 */
function star_rating( $stars, $class = 'not-used-any-more' )
{
	echo get_star_rating( $stars, $class );
}


/**
 * Display "powered by b2evolution" logo
 */
function powered_by( $params = array() )
{
	/**
	 * @var AbstractSettings
	 */
	global $global_Cache;

	global $rsc_uri;

	// Make sure we are not missing any param:
	$params = array_merge( array(
			'block_start' => '<div class="powered_by">',
			'block_end'   => '</div>',
			'img_url'     => '$rsc$img/powered-by-b2evolution-120t.gif',
			'img_width'   => '',
			'img_height'  => '',
		), $params );

	echo $params['block_start'];

	$img_url = str_replace( '$rsc$', $rsc_uri, $params['img_url'] );

	$evo_links = $global_Cache->get( 'evo_links' );
	if( empty( $evo_links ) )
	{	// Use basic default:
		$evo_links = unserialize('a:1:{s:0:"";a:1:{i:0;a:3:{i:0;i:100;i:1;s:23:"http://b2evolution.net/";i:2;a:2:{i:0;a:2:{i:0;i:55;i:1;s:36:"powered by b2evolution blog software";}i:1;a:2:{i:0;i:100;i:1;s:29:"powered by free blog software";}}}}}');
	}

	echo resolve_link_params( $evo_links, NULL, array(
			'type'        => 'img',
			'img_url'     => $img_url,
			'img_width'   => $params['img_width'],
			'img_height'  => $params['img_height'],
			'title'       => 'b2evolution: next generation blog software',
		) );

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
	$percentage = $hit_total > 0 ? $hit_count * 100 / $hit_total : 0;
	return number_format( $percentage, $decimals, $dec_point, '' ).'&nbsp;%';
}

function addup_percentage( $hit_count, $hit_total, $decimals = 1, $dec_point = '.' )
{
	static $addup = 0;

	$addup += $hit_count;
	return number_format( $addup * 100 / $hit_total, $decimals, $dec_point, '' ).'&nbsp;%';
}


/**
 * Check if the array given as the first param contains recursion
 *
 * @param array what to check
 * @param array contains object which were already seen
 * @return boolean true if contains recursion false otherwise
 */
function is_recursive( /*array*/ & $array, /*array*/ & $alreadySeen = array() )
{
    static $uniqueObject;
    if( !$uniqueObject )
    {
        $uniqueObject = new stdClass;
    }

    // Set main array as already seen
    $alreadySeen[] = & $array;

    foreach( $array as & $item )
    { // for each item in array
        if( !is_array( $item ) )
        { // if not array, we don't have to check it
            continue;
        }

        // put the unique object into the end of the array
        $item[] = $uniqueObject;
        $recursionDetected = false;
        foreach( $alreadySeen as $candidate )
        {
            if( end( $candidate ) === $uniqueObject )
            { // In the end of an already scanned array is the same unique Obect, this means that recursion was detected
                $recursionDetected = true;
                break;
            }
        }

        array_pop( $item );

        if( $recursionDetected || is_recursive( $item, $alreadySeen ) )
        { // Check until recursion detected or there are not more arrays
            return true;
        }
    }

    return false;
}


/**
 * Display a form (like comment or contact form) through an ajax call
 *
 * @param array params
 */
function display_ajax_form( $params )
{
	global $rsc_uri, $samedomain_htsrv_url, $ajax_form_number;

	if( is_recursive( $params ) )
	{ // The params array contains recursion, don't try to encode, display error message instead
		// We don't use translation because this situation should not really happen ( Probably it happesn with some wrong skin )
		echo '<p style="color:red;font-weight:bold">'.T_( 'This section can\'t be displayed because wrong params were created by the skin.' ).'</p>';
		return;
	}

	if( empty( $ajax_form_number ) )
	{	// Set number for ajax form to use unique ID for each new form
		$ajax_form_number = 0;
	}
	$ajax_form_number++;

	echo '<div id="ajax_form_number_'.$ajax_form_number.'" class="section_requires_javascript">';

	// Needs json_encode function to create json type params
	$json_params = evo_json_encode( $params );
	$ajax_loader = "<p class='ajax-loader'><img src='".$rsc_uri."img/ajax-loader2.gif' /><br />".T_( 'Form is loading...' )."</p>";
	?>
	<script type="text/javascript">
		// display loader gif until the ajax call returns
		document.write( <?php echo '"'.$ajax_loader.'"'; ?> );

		var ajax_form_offset_<?php echo $ajax_form_number; ?> = jQuery('#ajax_form_number_<?php echo $ajax_form_number; ?>').offset().top;
		var request_sent_<?php echo $ajax_form_number; ?> = false;

		function get_form_<?php echo $ajax_form_number; ?>()
		{
			jQuery.ajax({
				url: '<?php echo $samedomain_htsrv_url; ?>anon_async.php',
				type: 'POST',
				data: <?php echo $json_params; ?>,
				success: function(result)
					{
						jQuery('#ajax_form_number_<?php echo $ajax_form_number; ?>').html( ajax_debug_clear( result ) );
					}
			});
		}

		function check_and_show_<?php echo $ajax_form_number; ?>()
		{
			var window_scrollTop = jQuery(window).scrollTop();
			var window_height = jQuery(window).height();
			// check if the ajax form is visible, or if it will be visible soon ( 20 pixel )
			if( window_scrollTop >= ajax_form_offset_<?php echo $ajax_form_number; ?> - window_height - 20 )
			{
				if( !request_sent_<?php echo $ajax_form_number; ?> )
				{
					request_sent_<?php echo $ajax_form_number; ?> = true;
					// get the form
					get_form_<?php echo $ajax_form_number; ?>();
				}
			}
		}

		jQuery(window).scroll(function() {
			check_and_show_<?php echo $ajax_form_number; ?>();
		});

		jQuery(document).ready( function() {
			check_and_show_<?php echo $ajax_form_number; ?>();
		});

		jQuery(window).resize( function() {
			check_and_show_<?php echo $ajax_form_number; ?>();
		});
	</script>
	<noscript>
		<?php echo '<p>'.T_( 'This section can only be displayed by javascript enabled browsers.' ).'</p>'; ?>
	</noscript>
	<?php
	echo '</div>';
}


/**
 * Display login form
 *
 * @param array params
 */
function display_login_form( $params )
{
	global $Settings, $Plugins, $Session, $Blog, $blog, $dummy_fields;
	global $secure_htsrv_url, $admin_url, $baseurl, $ReqHost;

	$params = array_merge( array(
			'form_action' => '',
			'form_name' => 'login_form' ,
			'form_layout' => '',
			'form_class' => 'bComment',
			'source' => 'inskin login form',
			'inskin' => true,
			'login_required' => true,
			'validate_required' => NULL,
			'redirect_to' => '',
			'login' => '',
			'action' => '',
			'reqID' => '',
			'sessID' => '',
			'transmit_hashed_password' => false,
		), $params );

	$inskin = $params[ 'inskin' ];
	$login = $params[ 'login' ];
	$redirect_to = $params[ 'redirect_to' ];
	$links = array();

	if( empty( $params[ 'login_required' ] )
		&& $params[ 'action' ] != 'req_validatemail'
		&& strpos($redirect_to, $admin_url) !== 0
		&& strpos($ReqHost.$redirect_to, $admin_url ) !== 0 )
	{ // No login required, allow to pass through
		// TODO: dh> validate redirect_to param?!
		// check if redirect_to url requires logged in user
		if( require_login( $redirect_to, true ) )
		{ // logged in user require for redirect_to url
			if( !empty( $blog ) )
			{ // blog is set
				if( empty( $Blog ) )
				{
					$BlogCache = & get_BlogCache();
					$Blog = $BlogCache->get_by_ID( $blog, false );
				}
				// set abort url to Blog url
				$abort_url = $Blog->gen_blogurl();
			}
			else
			{ // set abort login url to base url
				$abort_url = $baseurl;
			}
		}
		else
		{ // logged in user isn't required for redirect_to url, set abort url to redirect_to
			$abort_url = $redirect_to;
		}
		$links[] = '<a href="'.htmlspecialchars( url_rel_to_same_host( $abort_url, $ReqHost ) ).'">'
		./* Gets displayed as link to the location on the login form if no login is required */ T_('Abort login!').'</a>';
	}

	if( ( !$inskin ) && is_logged_in() )
	{ // if we arrive here, but are logged in, provide an option to logout (e.g. during the email validation procedure)
		$links[] = get_user_logout_link();
	}

	if( count($links) )
	{
		echo '<div style="float:right; margin: 0 1em">'.implode( $links, ' &middot; ' ).'</div>
		<div class="clear"></div>';
	}

	$Form = new Form( $params[ 'form_action' ] , $params[ 'form_name' ], 'post', $params[ 'form_layout' ] );

	$Form->begin_form( $params[ 'form_class' ] );

	$Form->add_crumb( 'loginform' );
	$source = param( 'source', 'string', $params[ 'source' ].' login form' );
	$Form->hidden( 'source', $source );
	$Form->hidden( 'redirect_to', $redirect_to );
	if( $inskin )
	{ // inskin login form
		$Form->hidden( 'inskin', true );
		$separator = '<br />';
	}
	else
	{ // standard login form
		$Form->hidden( 'validate_required', $params[ 'validate_required' ] );
		if( isset( $params[ 'action' ],  $params[ 'reqID' ], $params[ 'sessID' ] ) &&  $params[ 'action' ] == 'validatemail' )
		{ // the user clicked the link from the "validate your account" email, but has not been logged in; pass on the relevant data:
			$Form->hidden( 'action', 'validatemail' );
			$Form->hidden( 'reqID', $params[ 'reqID' ] );
			$Form->hidden( 'sessID', $params[ 'sessID' ] );
		}
		$separator = '';
	}

	// check if should transmit hashed password
	if( $params[ 'transmit_hashed_password' ] )
	{ // used by JS-password encryption/hashing:
		$pwd_salt = $Session->get('core.pwd_salt');
		if( empty($pwd_salt) )
		{ // Do not regenerate if already set because we want to reuse the previous salt on login screen reloads
			// fp> Question: the comment implies that the salt is reset even on failed login attemps. Why that? I would only have reset it on successful login. Do experts recommend it this way?
			// but if you kill the session you get a new salt anyway, so it's no big deal.
			// At that point, why not reset the salt at every reload? (it may be good to keep it, but I think the reason should be documented here)
			$pwd_salt = generate_random_key(64);
			$Session->set( 'core.pwd_salt', $pwd_salt, 86400 /* expire in 1 day */ );
			$Session->dbsave(); // save now, in case there's an error later, and not saving it would prevent the user from logging in.
		}
		$Form->hidden( 'pwd_salt', $pwd_salt );
		$Form->hidden( 'pwd_hashed', '' ); // gets filled by JS
	}

	$Form->begin_field();
	$Form->text_input( $dummy_fields[ 'login' ], $params[ 'login' ], 18, T_('Login'), $separator.T_('Enter your username (or email address).'),
					array( 'maxlength' => 255, 'class' => 'input_text', 'required'=>true ) );
	$Form->end_field();

	if( $inskin )
	{
		$lost_password_url = regenerate_url( 'disp', 'disp=lostpassword' );
	}
	else
	{
		$lost_password_url = $secure_htsrv_url.'login.php?action=lostpassword&amp;redirect_to='.rawurlencode( url_rel_to_same_host( $redirect_to, $secure_htsrv_url) );
	}
	if( !empty($login) )
	{
		$lost_password_url .= '&amp;'.$dummy_fields[ 'login' ].'='.rawurlencode($login);
	}
	$pwd_note = $pwd_note = '<a href="'.$lost_password_url.'">'.T_('Lost password ?').'</a>';

	$Form->begin_field();
	$Form->password_input( $dummy_fields[ 'pwd' ], '', 18, T_('Password'), array( 'note'=>$pwd_note, 'maxlength' => 70, 'class' => 'input_text', 'required'=>true ) );
	$Form->end_field();

	// Allow a plugin to add fields/payload
	$Plugins->trigger_event( 'DisplayLoginFormFieldset', array( 'Form' => & $Form ) );

	// Submit button(s):
	$submit_buttons = array( array( 'name'=>'login_action[login]', 'value'=>T_('Log in!'), 'class'=>'search', 'style'=>'font-size: 120%' ) );
	if( ( !$inskin ) && ( strpos( $redirect_to, $admin_url ) !== 0 )
		&& ( strpos( $ReqHost.$redirect_to, $admin_url ) !== 0 )// if $redirect_to is relative
		&& ( ! is_admin_page() ) )
	{ // provide button to log straight into backoffice, if we would not go there anyway
		$submit_buttons[] = array( 'name'=>'login_action[redirect_to_backoffice]', 'value'=>T_('Log into backoffice!'), 'class'=>'search' );
	}

	$Form->buttons_input( $submit_buttons );

	if( $inskin )
	{
		$before_register_link = '<strong>';
		$after_register_link = '</strong>';
		$register_link_style = 'text-align:right; margin: 1em 0 1ex';
	}
	else
	{
		echo '<div class="center notes" style="margin: 1em 0">'.T_('You will have to accept cookies in order to log in.').'</div>';

		// Passthrough REQUEST data (when login is required after having POSTed something)
		// (Exclusion of 'login_action', 'login', and 'action' has been removed. This should get handled via detection in Form (included_input_field_names),
		//  and "action" is protected via crumbs)
		$Form->hiddens_by_key( remove_magic_quotes($_REQUEST) );

		$before_register_link = '';
		$after_register_link = '';
		$register_link_style = 'text-align:right';
	}

	echo '<div class="login_actions" style="'.$register_link_style.'">';
	echo get_user_register_link( $before_register_link, $after_register_link, T_('No account yet? Register here').' &raquo;', '#', true /*disp_when_logged_in*/, $redirect_to, $source );
	echo '</div>';

	$Form->end_form();

	echo '<script type="text/javascript">';
	// Autoselect login text input or pwd input, if there\'s a login already:
	echo 'var login = document.getElementById("'.$dummy_fields[ 'login' ].'");
		if( login.value.length > 0 )
		{	// Focus on the password field:
			document.getElementById("'.$dummy_fields[ 'pwd' ].'").focus();
		}
		else
		{	// Focus on the login field:
			login.focus();
		}';

	if( $params[ 'transmit_hashed_password' ] )
	{ // Hash the password onsubmit and clear the original pwd field
		// TODO: dh> it would be nice to disable the clicked/used submit button. That's how it has been when the submit was attached to the submit button(s)
		echo 'addEvent( document.getElementById("login_form"), "submit", function(){'.
				/* this.value = '.TS_('Please wait...').' */
				'var form = document.getElementById("login_form");'.

				// Calculate hashed password and set it in the form:
				'if( form.pwd_hashed && form.'.$dummy_fields[ 'pwd' ].' && form.pwd_salt && typeof hex_sha1 != "undefined" && typeof hex_md5 != "undefined" )
				{'.
					// We first hash to md5, because that's how the passwords are stored in the database
					// We then hash with the salt using SHA1 (fp> can't we do that with md5 again, in order to load 1 less Javascript library?)
					// NOTE: MD5 is kind of "weak" and therefor we also use SHA1
					'form.pwd_hashed.value = hex_sha1( hex_md5(form.'.$dummy_fields[ 'pwd' ].'.value) + form.pwd_salt.value );
					form.'.$dummy_fields[ 'pwd' ].'.value = "padding_padding_padding_padding_padding_padding_hashed_'.$Session->ID.'";'. /* to detect cookie problems */
					// (paddings to make it look like encryption on screen. When the string changes to just one more or one less *, it looks like the browser is changing the password on the fly)
				'}
				return true;
			}, false );';
	}
	echo '</script>';
}


/**
 * Display lost password form
 *
 * @param array login form hidden params
 */
function display_lostpassword_form( $login, $hidden_params )
{
	global $secure_htsrv_url, $dummy_fields;
	$Form = new Form( $secure_htsrv_url.'login.php', '', 'post', 'fieldset' );

	$Form->begin_form( 'fform' );

	// Display hidden fields
	$Form->add_crumb( 'lostpassform' );
	$Form->hidden( 'action', 'retrievepassword' );
	foreach( $hidden_params as $key => $value )
	{
		$Form->hidden( $key, $value );
	}

	$Form->begin_fieldset();

	echo '<ol>';
	echo '<li>'.T_('Please enter your login (or email address) below.').'</li>';
	echo '<li>'.T_('An email will be sent to your registered email address immediately.').'</li>';
	echo '<li>'.T_('As soon as you receive the email, click on the link therein to change your password.').'</li>';
	echo '<li>'.T_('Your browser will open a page where you can chose a new password.').'</li>';
	echo '</ol>';
	echo '<p class="red"><strong>'.T_('Important: for security reasons, you must do steps 1 and 4 on the same computer and same web browser. Do not close your browser in between.').'</strong></p>';

	$Form->text( $dummy_fields[ 'login' ], $login, 30, T_('Login'), '', 255, 'input_text' );

	$Form->buttons_input( array(array( /* TRANS: Text for submit button to request an activation link by email */ 'value' => T_('Send me an email now!'), 'class' => 'ActionButton' )) );

	$Form->end_fieldset();;

	$Form->end_form();
}


/**
 * Display user activate info form content
 *
 * @param Object activateinfo Form
 */
function display_activateinfo( $params )
{
	global $current_User, $Settings, $UserSettings, $Plugins;
	global $secure_htsrv_url, $rsc_path, $rsc_url, $dummy_fields;

	if( !is_logged_in() )
	{ // if this happens, it means the code is not correct somewhere before this
		debug_die( "You must log in to see this page." );
	}

	// init force request new email address param
	$force_request = param( 'force_request', 'boolean', false );

	// get last activation email timestamp from User Settings
	$last_activation_email_date = $UserSettings->get( 'last_activation_email', $current_User->ID );

	if( $force_request || empty( $last_activation_email_date ) )
	{ // notification email was not sent yet, or user needs another one ( forced request )
		$params = array_merge( array(
				'form_action' => $secure_htsrv_url.'login.php',
				'form_name' => 'form_validatemail',
				'form_class' => 'fform',
				'form_layout' => 'fieldset',
				'inskin' => false,
			), $params );
		$Form = new Form( $params[ 'form_action' ], $params[ 'form_name' ], 'post', $params[ 'form_layout' ] );

		$Form->begin_form( $params[ 'form_class' ] );

		$Form->add_crumb( 'validateform' );
		$Form->hidden( 'action', 'req_validatemail');
		$Form->hidden( 'redirect_to', $params[ 'redirect_to' ] );
		if( $params[ 'inskin' ] )
		{
			$Form->hidden( 'inskin', $params[ 'inskin' ] );
			$Form->hidden( 'blog', $params[ 'blog' ] );
		}
		$Form->hidden( 'req_validatemail_submit', 1 ); // to know if the form has been submitted

		$Form->begin_fieldset();

		echo '<ol>';
		echo '<li>'.T_('Please confirm your email address below:').'</li>';
		echo '</ol>';

		// set email text input content only if this is not a forced request. This way the user may have bigger chance to write a correct email address.
		$user_email = ( $force_request ? '' : $current_User->email );
		// fp> note: 45 is the max length for evopress skin.
		$Form->text_input( $dummy_fields[ 'email' ], $user_email, 45, T_('Your email'), '', array( 'maxlength'=>255, 'class'=>'input_text', 'required'=>true ) );
		$Form->end_fieldset();

		// Submit button:
		$submit_button = array( array( 'name'=>'submit', 'value'=>T_('Send me a new activation email now!'), 'class'=>'submit' ) );

		$Form->buttons_input($submit_button);

		if( !$params[ 'inskin' ] )
		{
			$Plugins->trigger_event( 'DisplayValidateAccountFormFieldset', array( 'Form' => & $Form ) );
		}

		$Form->end_form();

		return;
	}

	// get notification email from general Settings
	$notification_email = $Settings->get( 'notification_sender_email' );
	// convert date to timestamp
	$last_activation_email_ts = mysql2timestamp( $last_activation_email_date );
	// get difference between local time and server time
	$time_difference = $Settings->get('time_difference');
	// get last activation email local date and time
	$last_email_date = date( locale_datefmt(), $last_activation_email_ts + $time_difference );
	$last_email_time = date( locale_shorttimefmt(), $last_activation_email_ts + $time_difference );
	$user_email = $current_User->email;

	echo '<ol start="1" class="expanded">';
	$instruction =  sprintf( T_('Open your email account for %s and find a message we sent you on %s at %s with the following title:'), $user_email, $last_email_date, $last_email_time );
	echo '<li>'.$instruction.'<br /><b>'.sprintf( T_('Activate your account: %s'), $current_User->login ).'</b>';
	$request_validation_url = 'href="'.regenerate_url( '', 'force_request=1&validate_required=true&redirect_to='.$params[ 'redirect_to' ] ).'"';
	echo '<p>'.sprintf( T_('NOTE: If you don\'t find it, check your "Junk", "Spam" or "Unsolicited email" folders. If you really can\'t find it, <a %s>request a new activation email</a>.'), $request_validation_url ).'</p></li>';
	echo '<li>'.sprintf( T_('Add us (%s) to your contacts to make sure you receive future email notifications, especially when someone sends you a private message.'), '<b><span class="nowrap">'.$notification_email.'</span></b>').'</li>';
	echo '<li><b class="red">'.T_('Click on the activation link in the email.').'</b>';
	echo '<p>'.T_('If this does not work, please copy/paste that link into the address bar of your browser.').'</p>';
	echo '<p>'.sprintf( T_('If you need assistance, please send an email to %s'), '<b><a href="mailto:"'.$notification_email.'"><span class="nowrap">'.$notification_email.'</span></a></b>' ).'</p></li>';
	echo '</ol>';

	if( (strpos( $user_email, '@hotmail.' ) || strpos( $user_email, '@live.' ) || strpos( $user_email, '@msn.' ))
		&& file_exists( $rsc_path.'img/login_help/hotmail-validation.png' ) )
	{	// The user is on hotmail and we have a help screen to show him: (needs to be localized and include correct site name)
		echo '<div class="center" style="margin: 2em auto"><img src="'.$rsc_url.'img/login_help/hotmail-validation.png" /></div>';
	}
}


/*
 * Display javascript password strength indicator bar
 *
 * @param array Params
 */
function display_password_indicator( $params = array() )
{
	global $Blog, $rsc_url, $disp, $dummy_fields;

	$params = array_merge( array(
			'pass1-id'    => $dummy_fields[ 'pass1' ],
			'pass2-id'    => $dummy_fields[ 'pass2' ],
			'login-id'    => $dummy_fields[ 'login' ],
			'email-id'    => $dummy_fields[ 'email' ],
			'field-width' => 140,
			'disp-status' => 1,
			'disp-time'   => 0,
			'blacklist'   => "'b2evo','b2evolution'", // Identify the password as "weak" if it includes any of these words
		), $params );

	$extra_bar_width = 2;
	$container_left_margin = 0;
	if( !empty($disp) )
	{	// In skin password form
		$extra_bar_width = 0;
		$container_left_margin = '3px';
	}

	echo "<script type='text/javascript'>
	// Load password strength estimation library
	(function(){var a;a=function(){var a,b;b=document.createElement('script');b.src='".$rsc_url."js/zxcvbn.js';b.type='text/javascript';b.async=!0;a=document.getElementsByTagName('script')[0];return a.parentNode.insertBefore(b,a)};null!=window.attachEvent?window.attachEvent('onload',a):window.addEventListener('load',a,!1)}).call(this);

	// Call 'passcheck' function when document is loaded
	if( document.addEventListener ) { document.addEventListener('DOMContentLoaded', passcheck, false); } else { window.attachEvent('onload', passcheckpasscheck); }

	function passcheck()
	{
		var pass1input = document.getElementById('".$params['pass1-id']."');
		if( pass1input == null ) {
			return; // password field not found
		}

		var pass2input = document.getElementById('".$params['pass2-id']."');
		if( pass2input != null ) {
			pass2input.style.width = '".($params['field-width'] - 2)."px'; // Set fixed length
		}

		// Prepair password field
		pass1input.style.width = '".($params['field-width'] - 2)."px'; // Set fixed length
		pass1input.setAttribute('onkeyup','return passinfo(this);'); // Add onkeyup attribute
		pass1input.parentNode.innerHTML += \"<div id='p-container'><div id='p-result'></div><div id='p-status'></div><div id='p-time'></div></div>\";

		var pstyle = document.createElement('style');
		pstyle.innerHTML += '#p-container { position: relative; margin: 4px 0 0 ".$container_left_margin."; width:".($params['field-width']+$extra_bar_width)."px; height:5px; border: 1px solid #CCC; font-size: 84%; line-height:normal; color: #999 }';
		pstyle.innerHTML += '#p-result { height:5px }';
		pstyle.innerHTML += '#p-status { position:absolute; width: 100px; top:-7px; left:".($params['field-width']+8)."px }';
		pstyle.innerHTML += '#p-time { position:absolute; width: 400px }';
		document.body.appendChild(pstyle);
	}

	function passinfo(el)
	{
		var presult = document.getElementById('p-result');
		var pstatus = document.getElementById('p-status');
		var ptime = document.getElementById('p-time');

		var vlogin = '';
		var login = document.getElementById('".$params['login-id']."');
		if( login != null && login.value != '' ) { vlogin = login.value; }

		var vemail = '';
		var email = document.getElementById('".$params['email-id']."');
		if( email != null && email.value != '' ) { vemail = email.value; }

		// Check the password
		var passcheck = zxcvbn(el.value, [vlogin, vemail, ".$params['blacklist']."]);

		var bar_color = 'red';
		var bar_status = '".format_to_output( T_('Very weak'), 'htmlattr' )."';

		if( el.value.length == 0 ) {
			presult.style.display = 'none';
			pstatus.style.display = 'none';
			ptime.style.display = 'none';
		} else {
			presult.style.display = 'block';
			pstatus.style.display = 'block';
			ptime.style.display = 'block';
		}

		switch(passcheck.score) {
			case 1:
				bar_color = '#F88158';
				bar_status = '".format_to_output( T_('Weak'), 'htmlattr' )."';
				break;
			case 2:
				bar_color = '#FBB917';
				bar_status = '".format_to_output( T_('So-so'), 'htmlattr' )."';
				break;
			case 3:
				bar_color = '#8BB381';
				bar_status = '".format_to_output( T_('Good'), 'htmlattr' )."';
				break;
			case 4:
				bar_color = '#59E817';
				bar_status = '".format_to_output( T_('Great!'), 'htmlattr' )."';
				break;
		}

		presult.style.width = (passcheck.score * 20 + 20)+'%';
		presult.style.background = bar_color;

		if( ".$params['disp-status']." ) {
			pstatus.innerHTML = bar_status;
		}
		if( ".$params['disp-time']." ) {
			document.getElementById('p-time').innerHTML = '".TS_('Estimated crack time').": ' + passcheck.crack_time_display;
		}
	}
</script>";
}


/*
 * Display javascript login validator
 *
 * @param array Params
 */
function display_login_validator( $params = array() )
{
	global $rsc_url, $dummy_fields;

	$params = array_merge( array(
			'login-id'    => $dummy_fields[ 'login' ],
		), $params );

	echo '<script type="text/javascript">
	var login_icon_load = \'<img src="'.$rsc_url.'img/ajax-loader.gif" alt="'.TS_('Loading...').'" title="'.TS_('Loading...').'" style="margin:2px 0 0 5px" align="top" />\';
	var login_icon_available = \''.get_icon( 'allowback', 'imgtag', array( 'title' => TS_('This username is available.') ) ).'\';
	var login_icon_exists = \''.get_icon( 'xross', 'imgtag', array( 'title' => TS_('This username is already in use. Please choose another one.') ) ).'\';

	var login_text_empty = \''.TS_('Choose an username.').'\';
	var login_text_available = \''.TS_('This username is available.').'\';
	var login_text_exists = \''.TS_('This username is already in use. Please choose another one.').'\';

	jQuery( "#register_form #'.$params[ 'login-id' ].'" ).change( function()
	{	// Validate if username is available
		var note_Obj = jQuery( this ).next().next();
		if( jQuery( this ).val() == "" )
		{	// Login is empty
			jQuery( "#login_status" ).html( "" );
			note_Obj.html( login_text_empty ).attr( "class", "notes" );
		}
		else
		{	// Validate login
			jQuery( "#login_status" ).html( login_icon_load );
			jQuery.ajax( {
				type: "POST",
				url: "'.get_samedomain_htsrv_url().'anon_async.php",
				data: "action=validate_login&login=" + jQuery( this ).val(),
				success: function( result )
				{
					result = ajax_debug_clear( result );
					if( result == "exists" )
					{	// Login already exists
						jQuery( "#login_status" ).html( login_icon_exists );
						note_Obj.html( login_text_exists ).attr( "class", "notes red" );
					}
					else
					{	// Login is available
						jQuery( "#login_status" ).html( login_icon_available );
						note_Obj.html( login_text_available ).attr( "class", "notes green" );
					}
				}
			} );
		}
	} );
</script>';
}

?>