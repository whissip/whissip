<?php
/**
 * This file implements Template tags for use withing skins.
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}.
 * Parts of this file are copyright (c)2004-2005 by Daniel HAHLER - {@link http://thequod.de/contact}.
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
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author blueyed: Daniel HAHLER.
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


// DEBUG: (Turn switch on or off to log debug info for specified category)
$GLOBALS['debug_skins'] = false;


/**
 * Template tag. Initializes internal states for the most common skin displays.
 *
 * For more specific skins, this function should not be called and
 * equivalent code should be customized within the skin.
 *
 * @param string What are we going to display. Most of the time the global $disp should be passed.
 */
function skin_init( $disp )
{
	/**
	 * @var Blog
	 */
	global $Blog;

	/**
	 * @var Item
	 */
	global $Item;

	/**
	 * @var Skin
	 */
	global $Skin;

	global $robots_index;
	global $seo_page_type;

	global $redir, $ReqURL, $ReqURI, $m, $w, $preview;

	global $Chapter;
	global $Debuglog;

	/**
	 * @var ItemList2
	 */
	global $MainList;

	/**
	 * This will give more detail when $disp == 'posts'; otherwise it will have the same content as $disp
	 * @var string
	 */
	global $disp_detail;

	global $Timer;

	$Timer->resume( 'skin_init' );

	if( empty($disp_detail) )
	{
		$disp_detail = $disp;
	}

	$Debuglog->add('skin_init: '.$disp, 'skins' );

	// This is the main template; it may be used to display very different things.
	// Do inits depending on current $disp:
	switch( $disp )
	{
		case 'posts':
		case 'single':
		case 'page':
		case 'feedback-popup':
		case 'search':
			// We need to load posts for this display:

			// Note: even if we request the same post as $Item above, the following will do more restrictions (dates, etc.)
			// Init the MainList object:
			init_MainList( $Blog->get_setting('posts_per_page') );
			break;
	}

	// SEO stuff:
	$seo_page_type = NULL;
	switch( $disp )
	{
		// CONTENT PAGES:
		case 'single':
		case 'page':
			if( $disp == 'single' )
			{
				$seo_page_type = 'Single post page';
			}
			else
			{
				$seo_page_type = '"Page" page';
			}

			// Check if the post has 'redirected' status:
			if( ! $preview && $Item->status == 'redirected' && $redir == 'yes' )
			{	// $redir=no here allows to force a 'single post' URL for commenting
				// Redirect to the URL specified in the post:
				$Debuglog->add( 'Redirecting to post URL ['.$Item->url.'].' );
				header_redirect( $Item->url, true );
			}

			// Check if we want to redirect to a canonical URL for the post
			// Please document encountered problems.
			if( ! $preview
					&& (( $Blog->get_setting( 'canonical_item_urls' ) && $redir == 'yes' )
								|| $Blog->get_setting( 'relcanonical_item_urls' ) ) )
			{	// We want to redirect to the Item's canonical URL:

				$canonical_url = $Item->get_permanent_url( '', '', '&' );
				if( preg_match( '|[&?](page=\d+)|', $ReqURI, $page_param ) )
				{	// A certain post page has been requested, keep only this param and discard all others:
					$canonical_url = url_add_param( $canonical_url, $page_param[1], '&' );
				}

				if( ! is_same_url( $ReqURL, $canonical_url) )
				{	// The requested URL does not look like the canonical URL for this post...
					if( $Blog->get_setting( 'canonical_item_urls' ) && $redir == 'yes' && ( ! $Item->check_cross_post_nav( 'auto', $Blog->ID ) ) )
					{	// REDIRECT TO THE CANONICAL URL:
						$Debuglog->add( 'Redirecting to canonical URL ['.$canonical_url.'].' );
						header_redirect( $canonical_url, true );
					}
					else
					{	// Use rel="canoncial":
						add_headline( '<link rel="canonical" href="'.$canonical_url.'" />' );
					}
					// EXITED.
				}
			}

			if( ! $MainList->result_num_rows )
			{	// There is nothing to display for this page, don't index it!
				$robots_index = false;
			}
			break;

		case 'posts':
			// Get list of active filters:
			$active_filters = $MainList->get_active_filters();

			if( !empty($active_filters) )
			{	// The current page is being filtered...

				if( array_diff( $active_filters, array( 'page' ) ) == array() )
				{ // This is just a follow "paged" page
					$disp_detail = 'posts-next';
					$seo_page_type = 'Next page';
					if( $Blog->get_setting( 'paged_noindex' ) )
					{	// We prefer robots not to index category pages:
						$robots_index = false;
					}
				}
				elseif( array_diff( $active_filters, array( 'cat_array', 'cat_modifier', 'cat_focus', 'posts', 'page' ) ) == array() )
				{ // This is a category page
					$disp_detail = 'posts-cat';
					$seo_page_type = 'Category page';
					if( $Blog->get_setting( 'chapter_noindex' ) )
					{	// We prefer robots not to index category pages:
						$robots_index = false;
					}

					global $cat, $catsel;
					if( empty( $catsel ) && preg_match( '�[0-9]+�', $cat ) )
					{	// We are on a single cat page:
						// NOTE: we must have selected EXACTLY ONE CATEGORY through the cat parameter
						// BUT: - this can resolved to including children
						//      - selecting exactly one cat through catsel[] is NOT OK since not equivalent (will exclude children)
						// echo 'SINGLE CAT PAGE';
						if( ( $Blog->get_setting( 'canonical_cat_urls' ) && $redir == 'yes' )
							|| $Blog->get_setting( 'relcanonical_cat_urls' ) )
						{ // Check if the URL was canonical:
							if( !isset( $Chapter ) )
							{
								$ChapterCache = & get_ChapterCache();
								/**
								 * @var Chapter
								 */
								$Chapter = & $ChapterCache->get_by_ID( $MainList->filters['cat_array'][0], false );
							}
							if( $Chapter )
							{
								$canonical_url = $Chapter->get_permanent_url( NULL, NULL, $MainList->get_active_filter('page'), NULL, '&' );
								if( ! is_same_url($ReqURL, $canonical_url) )
								{	// fp> TODO: we're going to lose the additional params, it would be better to keep them...
									// fp> what additional params actually?
									if( $Blog->get_setting( 'canonical_cat_urls' ) && $redir == 'yes' )
									{	// REDIRECT TO THE CANONICAL URL:
										header_redirect( $canonical_url, true );
									}
									else
									{	// Use rel="canoncial":
										add_headline( '<link rel="canonical" href="'.$canonical_url.'" />' );
									}
								}
							}
						}
					}
				}
				elseif( array_diff( $active_filters, array( 'tags', 'posts', 'page' ) ) == array() )
				{ // This is a tag page
					$disp_detail = 'posts-tag';
					$seo_page_type = 'Tag page';
					if( $Blog->get_setting( 'tag_noindex' ) )
					{	// We prefer robots not to index tag pages:
						$robots_index = false;
					}

					if( ( $Blog->get_setting( 'canonical_tag_urls' ) && $redir == 'yes' )
							|| $Blog->get_setting( 'relcanonical_tag_urls' ) )
					{ // Check if the URL was canonical:
						$canonical_url = $Blog->gen_tag_url( $MainList->get_active_filter('tags'), $MainList->get_active_filter('page'), '&' );
						if( ! is_same_url($ReqURL, $canonical_url) )
						{
							if( $Blog->get_setting( 'canonical_tag_urls' ) && $redir == 'yes' )
							{	// REDIRECT TO THE CANONICAL URL:
								header_redirect( $canonical_url, true );
							}
							else
							{	// Use rel="canoncial":
								add_headline( '<link rel="canonical" href="'.$canonical_url.'" />' );
							}
						}
					}
				}
				elseif( array_diff( $active_filters, array( 'ymdhms', 'week', 'posts', 'page' ) ) == array() ) // fp> added 'posts' 2009-05-19; can't remember why it's not in there
				{ // This is an archive page
					// echo 'archive page';
					$disp_detail = 'posts-date';
					$seo_page_type = 'Date archive page';

					if( ($Blog->get_setting( 'canonical_archive_urls' ) && $redir == 'yes' )
							|| $Blog->get_setting( 'relcanonical_archive_urls' ) )
					{ // Check if the URL was canonical:
						$canonical_url =  $Blog->gen_archive_url( substr( $m, 0, 4 ), substr( $m, 4, 2 ), substr( $m, 6, 2 ), $w, '&', $MainList->get_active_filter('page') );
						if( ! is_same_url($ReqURL, $canonical_url) )
						{
							if( $Blog->get_setting( 'canonical_archive_urls' ) && $redir == 'yes' )
							{	// REDIRECT TO THE CANONICAL URL:
								header_redirect( $canonical_url, true );
							}
							else
							{	// Use rel="canoncial":
								add_headline( '<link rel="canonical" href="'.$canonical_url.'" />' );
							}
						}
					}

					if( $Blog->get_setting( 'archive_noindex' ) )
					{	// We prefer robots not to index archive pages:
						$robots_index = false;
					}
				}
				else
				{	// Other filtered pages:
					// pre_dump( $active_filters );
					$disp_detail = 'posts-filtered';
					$seo_page_type = 'Other filtered page';
					if( $Blog->get_setting( 'filtered_noindex' ) )
					{	// We prefer robots not to index other filtered pages:
						$robots_index = false;
					}
				}
			}
			else
			{	// This is the default blog page
				$disp_detail = 'posts-default';
				$seo_page_type = 'Default page';
				if( ($Blog->get_setting( 'canonical_homepage' ) && $redir == 'yes' )
						|| $Blog->get_setting( 'relcanonical_homepage' ) )
				{ // Check if the URL was canonical:
					$canonical_url = $Blog->gen_blogurl();
					if( ! is_same_url($ReqURL, $canonical_url) )
					{
						if( $Blog->get_setting( 'canonical_homepage' ) && $redir == 'yes' )
						{	// REDIRECT TO THE CANONICAL URL:
							header_redirect( $canonical_url, true );
						}
						else
						{	// Use rel="canoncial":
							add_headline( '<link rel="canonical" href="'.$canonical_url.'" />' );
						}
					}
				}

				if( $Blog->get_setting( 'default_noindex' ) )
				{	// We prefer robots not to index archive pages:
					$robots_index = false;
				}
			}

			break;

		case 'search':
			$seo_page_type = 'Search page';
			if( $Blog->get_setting( 'filtered_noindex' ) )
			{	// We prefer robots not to index these pages:
				$robots_index = false;
			}
			break;

		// SPECIAL FEATURE PAGES:
		case 'feedback-popup':
			$seo_page_type = 'Comment popup';
			if( $Blog->get_setting( $disp.'_noindex' ) )
			{	// We prefer robots not to index these pages:
				$robots_index = false;
			}
			break;

		case 'arcdir':
			$seo_page_type = 'Date archive directory';
			if( $Blog->get_setting( $disp.'_noindex' ) )
			{	// We prefer robots not to index these pages:
				$robots_index = false;
			}
			break;

		case 'catdir':
			$seo_page_type = 'Category directory';
			if( $Blog->get_setting( $disp.'_noindex' ) )
			{	// We prefer robots not to index these pages:
				$robots_index = false;
			}
			break;

		case 'msgform':
			$seo_page_type = 'Contact form';
			if( $Blog->get_setting( $disp.'_noindex' ) )
			{	// We prefer robots not to index these pages:
				$robots_index = false;
			}
			break;

		case 'login':
			$seo_page_type = 'Login form';
			if( $Blog->get_setting( $disp.'_noindex' ) )
			{	// We prefer robots not to index these pages:
				$robots_index = false;
			}
			break;

		case 'profile':
		case 'avatar':
		case 'pwdchange':
		case 'userprefs':
		case 'subs':
			$seo_page_type = 'Special feature page';
			if( $Blog->get_setting( 'special_noindex' ) )
			{	// We prefer robots not to index these pages:
				$robots_index = false;
			}
			break;

		case '404':
			// We have a 404 unresolved content error
			// How do we want do deal with it?
			skin_404_header();
			// This MAY or MAY not have exited -- will exit on 30x redirect, otherwise will return here.
			// Just in case some dumb robot needs extra directives on this:
			$robots_index = false;
			break;
	}

	// dummy var for backward compatibility with versions < 2.4.1 -- prevents "Undefined variable"
	global $credit_links;
	$credit_links = array();

	$Timer->pause( 'skin_init' );

	// Initialize displaying....
	$Timer->start( 'Skin:display_init' );
	$Skin->display_init();
	$Timer->pause( 'Skin:display_init' );

	// Send default headers:
	// See comments inside of this function:
	headers_content_mightcache( 'text/html' );		// In most situations, you do NOT want to cache dynamic content!
}


/**
 * Tells if we are on the default blog page
 *
 * @return boolean
 */
function is_default_page()
{
	global $disp_detail;
	return ($disp_detail == 'posts-default' );
}


/**
 * Template tag. Include a sub-template at the current position
 *
 */
function skin_include( $template_name, $params = array() )
{
	global $skins_path, $ads_current_skin_path, $disp;

	// Globals that may be needed by the template:
	global $Blog, $MainList, $Item;
	global $Plugins, $Skin;
	global $current_User, $Hit, $Session, $Settings;
	global $skin_url, $htsrv_url, $htsrv_url_sensitive;
	global $credit_links, $skin_links, $francois_links, $fplanque_links, $skinfaktory_links;

	global $Timer;

	$timer_name = 'skin_include('.$template_name.')';
	$Timer->resume( $timer_name );

	if( $template_name == '$disp$' )
	{ // This is a special case.
		// We are going to include a template based on $disp:

		// Default display handlers:
		$disp_handlers = array(
				'disp_404'            => '_404_not_found.disp.php',
				'disp_arcdir'         => '_arcdir.disp.php',
				'disp_catdir'         => '_catdir.disp.php',
				'disp_comments'       => '_comments.disp.php',
				'disp_feedback-popup' => '_feedback_popup.disp.php',
				'disp_help'           => '_help.disp.php',
				'disp_login'          => '_login.disp.php',
				'disp_mediaidx'       => '_mediaidx.disp.php',
				'disp_msgform'        => '_msgform.disp.php',
				'disp_page'           => '_page.disp.php',
				'disp_postidx'        => '_postidx.disp.php',
				'disp_posts'          => '_posts.disp.php',
				'disp_profile'        => '_profile.disp.php',
				'disp_avatar'         => '_profile.disp.php',
				'disp_pwdchange'      => '_profile.disp.php',
				'disp_userprefs'      => '_profile.disp.php',
				'disp_search'         => '_search.disp.php',
				'disp_single'         => '_single.disp.php',
				'disp_sitemap'        => '_sitemap.disp.php',
				'disp_subs'           => '_subs.disp.php',
				'disp_user'           => '_user.disp.php',
			);

		// Add plugin disp handlers:
		if( $disp_Plugins = $Plugins->get_list_by_event( 'GetHandledDispModes' ) )
		{
			foreach( $disp_Plugins as $disp_Plugin )
			{ // Go through whole list of plugins providing disps
				if( $plugin_modes = $Plugins->call_method( $disp_Plugin->ID, 'GetHandledDispModes', $disp_handlers ) )
				{ // plugin handles some custom disp modes
					foreach( $plugin_modes as $plugin_mode )
					{
						$disp_handlers[$plugin_mode] = '#'.$disp_Plugin->ID;
					}
				}
			}
		}

		// Allow skin overrides as well as additional disp modes (This can be used in the famou shopping cart scenario...)
		$disp_handlers = array_merge( $disp_handlers, $params );

		if( !isset( $disp_handlers['disp_'.$disp] ) )
		{
			printf( '<div class="skin_error">Unhandled disp type [%s]</div>',  htmlspecialchars( $disp ) );
			$Timer->pause( $timer_name );
			return;
		}

		$template_name = $disp_handlers['disp_'.$disp];

		if( empty( $template_name ) )
		{	// The caller asked not to display this handler
			$Timer->pause( $timer_name );
			return;
		}

	}

	$disp_handled = false;

	if( $template_name[0] == '#' )
	{	// This disp mode is handled by a plugin:
		$plug_ID = substr( $template_name, 1 );
		$disp_params = array( 'disp' => $disp );
		$Plugins->call_method( $plug_ID, 'HandleDispMode', $disp_params );
		$disp_handled = true;
	}

	elseif( file_exists( $ads_current_skin_path.$template_name ) )
	{	// The skin has a customized handler, use that one instead:
		global $Debuglog;
		$file = $ads_current_skin_path.$template_name;
		$Debuglog->add('skin_include ('.($Item ? 'Item #'.$Item->ID : '-').'): '.rel_path_to_base($file), 'skins');
		require $file;
		$disp_handled = true;
	}

	elseif( file_exists( $skins_path.$template_name ) )
	{	// Use the default template:
		global $Debuglog;
		$file = $skins_path.$template_name;
		$Debuglog->add('skin_include ('.($Item ? 'Item #'.$Item->ID : '-').'): '.rel_path_to_base($file), 'skins');
		require $file;
		$disp_handled = true;
	}

	if( ! $disp_handled )
	{ // nothing handled the disp mode
		printf( '<div class="skin_error">Sub template [%s] not found.</div>', $template_name );
		if( !empty($current_User) && $current_User->level == 10 )
		{
			printf( '<div class="skin_error">User level 10 help info: [%s]</div>', $ads_current_skin_path.$template_name );
		}
	}

	$Timer->pause( $timer_name );
}


/**
 * Template tag. Output HTML base tag to current skin.
 *
 * This is needed for relative css and img includes.
 */
function skin_base_tag()
{
	global $skins_url, $skin, $Blog, $disp;

	if( ! empty( $skin ) )
	{
		$base_href = $skins_url.$skin.'/';
	}
	else
	{ // No skin used:
		if( ! empty( $Blog ) )
		{
			$base_href = $Blog->gen_baseurl();
		}
		else
		{
			global $baseurl;
			$base_href = $baseurl;
		}
	}

	$target = NULL;
	if( !empty($disp) && strpos( $disp, '-popup' ) )
	{	// We are (normally) displaying in a popup window, we need most links to open a new window!
		$target = '_blank';
	}

	base_tag( $base_href, $target );
}


/**
 * Template tag
 *
 * Note for future mods: we do NOT want to repeat identical content on multiple pages.
 */
function skin_description_tag()
{
	global $Blog, $disp, $disp_detail, $MainList, $Chapter;

	$r = '';

	if( is_default_page() )
	{
		if( !empty($Blog) )
		{	// Description for the blog:
			$r = $Blog->get('shortdesc');
		}
	}
	elseif( $disp_detail == 'posts-cat' )
	{
		if( $Blog->get_setting( 'categories_meta_description') )
		{
			$r = $Chapter->get( 'description' );
		}
	}
	elseif( in_array( $disp, array( 'single', 'page' ) ) )
	{	// custom desc for the current single post:
		$Item = & $MainList->get_by_idx( 0 );
		if( is_null( $Item ) )
		{	// This is not an object (happens on an invalid request):
			return;
		}

		$r = $Item->get_metadesc();

		if( empty( $r )&& $Blog->get_setting( 'excerpts_meta_description' ) )
		{	// Fall back to excerpt for the current single post:
			// Replace line breaks with single space
			$r = preg_replace( '|[\r\n]+|', ' ', $Item->get('excerpt') );
		}
	}

	if( !empty($r) )
	{
		echo '<meta name="description" content="'.format_to_output( $r, 'htmlattr' )."\" />\n";
	}
}


/**
 * Template tag
 *
 * Note for future mods: we do NOT want to repeat identical content on multiple pages.
 */
function skin_keywords_tag()
{
	global $Blog, $disp, $MainList;

	$r = '';

	if( is_default_page() )
	{
		if( !empty($Blog) )
		{
			$r = $Blog->get('keywords');
		}
	}
	elseif( in_array( $disp, array( 'single', 'page' ) ) )
	{	// custom keywords for the current single post:
		$Item = & $MainList->get_by_idx( 0 );
		if( is_null( $Item ) )
		{	// This is not an object (happens on an invalid request):
			return;
		}

		$r = $Item->get_metakeywords();


		if( empty( $r ) && $Blog->get_setting( 'tags_meta_keywords' ) )
		{	// Fall back to tags for the current single post:
			$r = implode( ', ', $Item->get_tags() );
		}

	}

	if( !empty($r) )
	{
		echo '<meta name="keywords" content="'.format_to_output( $r, 'htmlattr' )."\" />\n";
	}
}


/**
 * Sends the desired HTTP response header in case of a "404".
 */
function skin_404_header()
{
	global $Blog;

	// We have a 404 unresolved content error
	// How do we want do deal with it?
	switch( $resp_code = $Blog->get_setting( '404_response' ) )
	{
		case '404':
			header('HTTP/1.0 404 Not Found');
			break;

		case '410':
			header('HTTP/1.0 410 Gone');
			break;

		case '301':
		case '302':
		case '303':
			// Redirect to home page:
			header_redirect( $Blog->get('url'), intval($resp_code) );
			// THIS WILL EXIT!
			break;

		default:
			// Will result in a 200 OK
	}
}


/**
 * Template tag. Output content-type header
 * For backward compatibility
 *
 * @see skin_content_meta()
 *
 * @param string content-type; override for RSS feeds
 */
function skin_content_header( $type = 'text/html' )
{
	header_content_type( $type );
}


/**
 * Template tag. Output content-type http_equiv meta tag
 *
 * @see skin_content_header()
 *
 * @param string content-type; override for RSS feeds
 */
function skin_content_meta( $type = 'text/html' )
{
	echo '<meta http-equiv="Content-Type" content="'.$type.'; charset=utf-8" />'."\n";
}


/**
 * Template tag. Display a Widget.
 *
 * This load the widget class, instantiates it, and displays it.
 *
 * @param array
 */
function skin_widget( $params )
{
	global $inc_path;

	if( empty( $params['widget'] ) )
	{
		echo 'No widget code provided!';
		return false;
	}

	$widget_code = $params['widget'];
	unset( $params['widget'] );

	if( ! file_exists( $inc_path.'widgets/widgets/_'.$widget_code.'.widget.php' ) )
	{	// For some reason, that widget doesn't seem to exist... (any more?)
		echo "Invalid widget code provided [$widget_code]!";
		return false;
	}
	require_once $inc_path.'widgets/widgets/_'.$widget_code.'.widget.php';

	$widget_classname = $widget_code.'_Widget';

	/**
	 * @var ComponentWidget
	 */
	$Widget = new $widget_classname();	// COPY !!

	return $Widget->display( $params );
}


/**
 * Display a container
 *
 * @param string
 * @param array
 */
function skin_container( $sco_name, $params = array() )
{
	global $Skin;

	$Skin->container( $sco_name, $params );
}


/**
 * Install a skin
 *
 * @todo do not install if skin doesn't exist. Important for upgrade. Need to NOT fail if ZERO skins installed though :/
 *
 * @param string Skin folder
 * @return Skin
 */
function & skin_install( $skin_folder )
{
	$SkinCache = & get_SkinCache();
	$Skin = & $SkinCache->new_obj( NULL, $skin_folder );

	$Skin->install();

	return $Skin;
}


/**
 * Checks if a skin is provided by a plugin.
 *
 * Used by front-end.
 *
 * @uses Plugin::GetProvidedSkins()
 * @return false|integer False in case no plugin provides the skin or ID of the first plugin that provides it.
 */
function skin_provided_by_plugin( $name )
{
	static $plugin_skins;
	if( ! isset($plugin_skins) || ! isset($plugin_skins[$name]) )
	{
		global $Plugins;

		$plugin_r = $Plugins->trigger_event_first_return('GetProvidedSkins', NULL, array('in_array'=>$name));
		if( $plugin_r )
		{
			$plugin_skins[$name] = $plugin_r['plugin_ID'];
		}
		else
		{
			$plugin_skins[$name] = false;
		}
	}

	return $plugin_skins[$name];
}


/**
 * Checks if a skin exists. This can either be a regular skin directory
 * or can be in the list {@link Plugin::GetProvidedSkins()}.
 *
 * Used by front-end.
 *
 * @param skin name (directory name)
 * @return boolean true is exists, false if not
 */
function skin_exists( $name, $filename = 'index.main.php' )
{
	global $skins_path;

	if( skin_file_exists( $name, $filename ) )
	{
		return true;
	}

	// Check list provided by plugins:
	if( skin_provided_by_plugin($name) )
	{
		return true;
	}

	return false;
}


/**
 * Checks if a specific file exists for a skin.
 *
 * @param skin name (directory name)
 * @param file name
 * @return boolean true is exists, false if not
 */
function skin_file_exists( $name, $filename = 'index.main.php' )
{
	global $skins_path;

	if( is_readable( $skins_path.$name.'/'.$filename ) )
	{
		return true;
	}

	return false;
}


/**
 * Check if a skin is installed.
 *
 * This can either be a regular skin or a skin provided by a plugin.
 *
 * @param Skin name (directory name)
 * @return boolean True if the skin is installed, false otherwise.
 */
function skin_installed( $name )
{
	$SkinCache = & get_SkinCache();

	if( skin_provided_by_plugin( $name ) || $SkinCache->get_by_folder( $name, false ) )
	{
		return true;
	}

	return false;
}


/*
 * $Log$
 * Revision 1.120  2012/11/21 19:31:53  efy-asimo
 * Fix XSS vulnerability
 *
 * Revision 1.89  2011/05/09 06:38:18  efy-asimo
 * Simple avatar modification update
 *
 * Revision 1.88  2011/03/24 15:15:05  efy-asimo
 * in-skin login - feature
 *
 * Revision 1.87  2011/03/04 08:20:45  efy-asimo
 * Simple avatar upload in the front office
 *
 * Revision 1.86  2010/10/18 12:02:26  efy-asimo
 * tiny url links - fix
 *
 * Revision 1.85  2010/09/15 13:04:06  efy-asimo
 * Cross post navigatation
 *
 * Revision 1.84  2010/04/08 21:02:43  waltercruz
 * Tags as meta-description fallback
 *
 * Revision 1.83  2010/02/08 17:53:55  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.82  2010/01/01 20:37:43  fplanque
 * help disp
 *
 * Revision 1.81  2009/12/22 23:13:38  fplanque
 * Skins v4, step 1:
 * Added new disp modes
 * Hooks for plugin disp modes
 * Enhanced menu widgets (BIG TIME! :)
 *
 * Revision 1.80  2009/12/22 08:53:34  fplanque
 * global $ReqURL
 *
 * Revision 1.79  2009/12/04 23:27:50  fplanque
 * cleanup Expires: header handling
 *
 * Revision 1.78  2009/11/30 00:22:05  fplanque
 * clean up debug info
 * show more timers in view of block caching
 *
 * Revision 1.77  2009/11/23 00:09:06  sam2kb
 * Replace line breaks with single space in meta description
 *
 * Revision 1.76  2009/09/26 12:00:43  tblue246
 * Minor/coding style
 *
 * Revision 1.75  2009/09/25 22:04:39  tblue246
 * Bugfix
 *
 * Revision 1.74  2009/09/25 07:33:14  efy-cantor
 * replace get_cache to get_*cache
 *
 * Revision 1.73  2009/09/12 20:51:58  tblue246
 * phpdoc fixes
 *
 * Revision 1.72  2009/08/03 12:02:40  tblue246
 * Keep only page param when redirecting to canonical post URL
 *
 * Revision 1.71  2009/07/19 14:34:43  fplanque
 * doc
 *
 * Revision 1.70  2009/07/17 11:44:27  tblue246
 * Crop params before comparing requested post URL and canonical post URL. Fixes http://forums.b2evolution.net//viewtopic.php?t=19200
 *
 * Revision 1.69  2009/07/14 10:55:03  tblue246
 * Bugfix: Remember requested page number when redirecting to a canonical chapter URL
 *
 * Revision 1.68  2009/07/12 19:12:51  fplanque
 * doc
 *
 * Revision 1.67  2009/07/08 14:19:59  yabs
 * doc
 *
 * Revision 1.66  2009/07/06 22:55:11  fplanque
 * minor
 *
 * Revision 1.65  2009/07/06 13:00:07  yabs
 * doc
 *
 * Revision 1.64  2009/07/04 22:48:04  tblue246
 * Fix fatal PHP error
 *
 * Revision 1.63  2009/07/04 01:52:51  fplanque
 * doc
 *
 * Revision 1.62  2009/07/02 22:17:05  yabs
 * <answer>sorry for the delay</answer>
 *
 * Revision 1.61  2009/07/02 21:33:45  fplanque
 * doc / waiting for answer
 *
 * Revision 1.60  2009/07/02 00:46:46  fplanque
 * doc.
 *
 * Revision 1.59  2009/06/29 09:33:34  yabs
 * changed plugin disp handling
 *
 * Revision 1.58  2009/06/28 23:55:32  fplanque
 * Item specific description has priority.
 * If none provided, fall back to excerpt.
 * Never include duplicate general description.
 * Also added TODO for keywords to have a fallback to tags.
 *
 * Revision 1.57  2009/06/20 17:19:35  leeturner2701
 * meta desc and meta keywords per blog post
 *
 * Revision 1.56  2009/06/14 06:50:29  yabs
 * code improvement for plugin custom disp modes
 *
 * Revision 1.55  2009/06/07 14:24:17  yabs
 * enabling plugin disp types
 *
 * Revision 1.54  2009/05/30 15:35:31  tblue246
 * - Fixed wrong $seo_page_type contents
 * - Fixed PHP notice when previewing a post
 *
 * Revision 1.53  2009/05/28 22:47:10  blueyed
 * skin_include: add info about the used file to Debuglog
 *
 * Revision 1.52  2009/05/27 14:46:33  waltercruz
 * Using categories description as meta-description for categories pages
 *
 * Revision 1.51  2009/05/25 19:39:50  fplanque
 * bugfix
 *
 * Revision 1.50  2009/05/24 21:14:38  fplanque
 * _skin.class.php can now provide skin specific settings.
 * Demo: the custom skin has configurable header colors.
 * The settings can be changed through Blog Settings > Skin Settings.
 * Anyone is welcome to extend those settings for any skin you like.
 *
 * Revision 1.49  2009/05/23 20:20:18  fplanque
 * Skins can now have a _skin.class.php file to override default Skin behaviour. Currently only the default name but can/will be extended.
 *
 * Revision 1.48  2009/05/20 18:27:09  fplanque
 * canonical support for date archives
 *
 * Revision 1.47  2009/05/20 12:58:17  fplanque
 * Homepage: option to 301 redirect to canonical homepage.
 * Option to support rel="canonical" instead of or when 301 redirect cannot be used.
 *
 * Revision 1.46  2009/05/19 14:34:32  fplanque
 * Category, tag, archive and serahc page snow only display post excerpts by default. (Requires a 3.x skin; otherwise the skin will display full posts as before). This can be controlled with the ''content_mode'' param in the skin tags.
 *
 * Revision 1.45  2009/03/23 12:19:20  tblue246
 * (temp)skin param: Allow plugin-provided skins
 *
 * Revision 1.44  2009/03/22 17:19:37  fplanque
 * better intro posts handling
 *
 * Revision 1.43  2009/03/22 16:12:03  fplanque
 * minor
 *
 * Revision 1.42  2009/03/21 00:38:15  waltercruz
 * Addind SEO setting for excerpts as meta description
 *
 * Revision 1.41  2009/03/20 03:41:02  fplanque
 * todo
 *
 * Revision 1.40  2009/03/16 16:00:27  waltercruz
 * Auto meta description
 *
 * Revision 1.39  2009/03/08 23:57:45  fplanque
 * 2009
 *
 * Revision 1.38  2009/03/06 16:40:26  blueyed
 * Fix path check/inclusion of widget classes.
 *
 * Revision 1.37  2009/03/05 23:38:53  blueyed
 * Merge autoload branch (lp:~blueyed/b2evolution/autoload) into CVS HEAD.
 *
 * Revision 1.36  2008/12/20 22:36:33  blueyed
 * Add is_same_url() to compare URLs without taking case of urlencoded parts into account. This is required to prevent infinite redirects in the handling of canonical URLs.
 *
 * Revision 1.35  2008/11/07 23:12:47  tblue246
 * minor
 *
 * Revision 1.34  2008/10/12 18:07:17  blueyed
 * s/canoncical_url/canonical_url/g
 *
 * Revision 1.33  2008/09/28 08:06:08  fplanque
 * Refactoring / extended page level caching
 *
 * Revision 1.32  2008/09/27 07:54:34  fplanque
 * minor
 *
 * Revision 1.31  2008/05/11 01:09:42  fplanque
 * always output charset header + meta
 *
 * Revision 1.30  2008/04/26 22:20:45  fplanque
 * Improved compatibility with older skins.
 *
 * Revision 1.29  2008/04/13 23:38:53  fplanque
 * Basic public user profiles
 *
 * Revision 1.28  2008/04/04 23:56:02  fplanque
 * avoid duplicate content in meta tags
 *
 * Revision 1.27  2008/03/21 19:42:44  fplanque
 * enhanced 404 handling
 *
 * Revision 1.26  2008/03/21 17:41:56  fplanque
 * custom 404 pages
 *
 * Revision 1.25  2008/02/25 19:49:04  blueyed
 * Fix E_FATAL for invalid category ID and "canonical_cat_urls"; fix indenting
 *
 * Revision 1.24  2008/01/21 09:35:34  fplanque
 * (c) 2008
 *
 * Revision 1.23  2008/01/07 02:53:27  fplanque
 * cleaner tag urls
 *
 * Revision 1.22  2007/12/22 00:19:27  blueyed
 * - add debuglog to skin_init
 * - fix indent
 *
 * Revision 1.21  2007/12/20 22:59:34  fplanque
 * TagCloud widget prototype
 *
 * Revision 1.20  2007/12/16 21:53:15  blueyed
 * skin_base_tag: globalize baseurl, if used
 *
 * Revision 1.19  2007/11/29 19:29:22  fplanque
 * normalized skin filenames
 *
 * Revision 1.18  2007/11/25 19:47:15  fplanque
 * cleaned up photo/media index a little bit
 *
 * Revision 1.17  2007/11/25 18:20:38  fplanque
 * additional SEO settings
 *
 * Revision 1.16  2007/11/25 14:28:18  fplanque
 * additional SEO settings
 *
 * Revision 1.15  2007/11/24 21:41:12  fplanque
 * additional SEO settings
 *
 * Revision 1.14  2007/11/02 02:41:25  fplanque
 * refactored blog settings / UI
 *
 * Revision 1.13  2007/10/12 05:26:59  fplanque
 * global $DB has been added to _subscriptions already and its use should not be encouraged. Therefore I don't want it available by default. _subs.disp.php should be cleaned up at some point.
 *
 * Revision 1.11  2007/10/09 02:10:50  fplanque
 * URL fixes
 *
 * Revision 1.10  2007/10/06 21:31:40  fplanque
 * Category redirector fix
 *
 * Revision 1.9  2007/10/01 13:37:28  fplanque
 * fix
 *
 * Revision 1.8  2007/10/01 08:03:57  yabs
 * minor fix
 *
 * Revision 1.7  2007/10/01 01:06:31  fplanque
 * Skin/template functions cleanup.
 *
 * Revision 1.6  2007/09/28 09:28:36  fplanque
 * per blog advanced SEO settings
 *
 * Revision 1.5  2007/09/11 23:10:39  fplanque
 * translation updates
 *
 * Revision 1.4  2007/09/11 21:07:09  fplanque
 * minor fixes
 */
?>
