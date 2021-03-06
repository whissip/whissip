<?php
/**
 * This is the main dispatcher for the admin interface.
 *
 * IF YOU ARE READING THIS IN YOUR WEB BROWSER, IT MEANS THAT PHP IS NOT PROPERLY INSTALLED
 * ON YOUR WEB SERVER. IF YOU DON'T KNOW WHAT THIS MEANS, CONTACT YOUR SERVER ADMINISTRATOR
 * OR YOUR HOSTING COMPANY.
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
 * @package main
 *
 * @version $Id$
 */


/**
 * Do the MAIN initializations:
 */
require_once dirname(__FILE__).'/conf/_config.php';


/**
 * @global boolean Is this an admin page? Use {@link is_admin_page()} to query it, because it may change.
 */
$is_admin_page = true;


$login_required = true;
require_once $inc_path.'_main.inc.php';


// Check global permission:
if( ! $current_User->check_perm( 'admin', 'restricted' ) )
{	// No permission to access admin...
	require $adminskins_path.'_access_denied.main.php';
}


/*
 * Asynchronous processing options that may be required on any page
 */
require_once $inc_path.'_async.inc.php';


/*
 * Get the blog from param, defaulting to the last selected one for this user:
 * we need it for quite a few of the menu urls
 */
if( isset($collections_Module) )
{
	$user_selected_blog = (int)$UserSettings->get('selected_blog');
	$BlogCache = & get_BlogCache();
	if( param( 'blog', 'integer', NULL, true ) === NULL      // We got no explicit blog choice (not even '0' for 'no blog'):
		|| ($blog > 0 && ! ($Blog = & $BlogCache->get_by_ID( $blog, false, false )) )) // or we requested a nonexistent blog
	{ // Try the memorized blog from the previous action:
		$blog = $user_selected_blog;
		if( ! ($Blog = & $BlogCache->get_by_ID( $blog, false, false ) ) )
		{	// That one doesn't exist either...
			$blog = 0;
		}
	}
	elseif( $blog != $user_selected_blog )
	{ // We have selected a new & valid blog. Update UserSettings for selected blog:
		set_working_blog( $blog );
	}
}

// bookmarklet, upload (upload actually means sth like: select img for post):
param( 'mode', 'string', '', true );


/*
 * Get the Admin skin
 * TODO: Allow setting through GET param (dropdown in backoffice), respecting a checkbox "Use different setting on each computer" (if cookie_state handling is ready)
 */
$admin_skin = $UserSettings->get( 'admin_skin' );
$admin_skin_path = $adminskins_path.'%s/_adminUI.class.php';

if( ! $admin_skin || ! file_exists( sprintf( $admin_skin_path, $admin_skin ) ) )
{ // there's no skin for the user
	if( !$admin_skin )
	{
		$Debuglog->add( 'The user has no admin skin set.', 'skins' );
	}
	else
	{
		$Debuglog->add( 'The admin skin ['.$admin_skin.'] set by the user does not exist.', 'skins' );
	}

	$admin_skin = $Settings->get( 'admin_skin' );

	if( !$admin_skin || !file_exists( sprintf( $admin_skin_path, $admin_skin ) ) )
	{ // even the default skin does not exist!
		if( !$admin_skin )
		{
			$Debuglog->add( 'There is no default admin skin set!', 'skins' );
		}
		else
		{
			$Debuglog->add( 'The default admin skin ['.$admin_skin.'] does not exist!', array('skin','error') );
		}

		if( file_exists(sprintf( $admin_skin_path, 'chicago' )) )
		{ // 'legacy' does exist
			$admin_skin = 'chicago';

			$Debuglog->add( 'Falling back to legacy admin skin.', 'skins' );
		}
		else
		{ // get the first one available one
			$admin_skin_dirs = get_admin_skins();

			if( $admin_skin_dirs === false )
			{
				$Debuglog->add( 'No admin skin found! Check that the path '.$adminskins_path.' exists.', array('skin','error') );
			}
			elseif( empty($admin_skin_dirs) )
			{ // No admin skin directories found
				$Debuglog->add( 'No admin skin found! Check that there are skins in '.$adminskins_path.'.', array('skin','error') );
			}
			else
			{
				$admin_skin = array_shift($admin_skin_dirs);
				$Debuglog->add( 'Falling back to first available skin.', 'skins' );
			}
		}
	}
}
if( ! $admin_skin )
{
	$Debuglog->display( 'No admin skin available!', '', true, 'skins' );
	die(1);
}

$Debuglog->add( 'Using admin skin &laquo;'.$admin_skin.'&raquo;', 'skins' );

/**
 * Load the AdminUI class for the skin.
 */
require_once $adminskins_path.$admin_skin.'/_adminUI.class.php';
/**
 * This is the Admin UI object which handles the UI for the backoffice.
 *
 * @global AdminUI
 */
$AdminUI = new AdminUI();


/*
 * Pass over to controller...
 */

// Get requested controller and memorize it:
param( 'ctrl', '/^[a-z0-9_]+$/', $default_ctrl, true );


// Redirect old-style URLs (e.g. /admin/plugins.php), if they come here because the webserver maps "/admin/" to "/admin.php"
// NOTE: this is just meant as a transformation from pre-1.8 to 1.8!
if( ! empty( $_SERVER['PATH_INFO'] ) && $_SERVER['PATH_INFO'] != $_SERVER['SCRIPT_NAME'] ) // the "!= SCRIPT_NAME" check seems needed by IIS..
{
	// Try to find the appropriate controller (ctrl) setting
	foreach( $ctrl_mappings as $k => $v )
	{
		if( preg_match( '~'.preg_quote( $_SERVER['PATH_INFO'], '~' ).'$~', $v ) )
		{
			$ctrl = $k;
			break;
		}
	}

	// Sanitize QUERY_STRING
	if( ! empty( $_SERVER['QUERY_STRING'] ) )
	{
		$query_string = explode( '&', $_SERVER['QUERY_STRING'] );
		foreach( $query_string as $k => $v )
		{
			$query_string[$k] = strip_tags($v);
		}
		$query_string = '&'.implode( '&', $query_string );
	}
	else
	{
		$query_string = '';
	}

	header_redirect( url_add_param( $admin_url, 'ctrl='.$ctrl.$query_string, '&' ), true );
	exit(0);
}


// Check matching controller file:
if( !isset($ctrl_mappings[$ctrl]) )
{
	debug_die( 'The requested controller ['.$ctrl.'] does not exist.' );
}

// Call the requested controller:
require $inc_path.$ctrl_mappings[$ctrl];

/*
 * $Log$
 * Revision 1.38  2011/02/15 15:36:59  efy-asimo
 * Change access to admin permission
 *
 * Revision 1.37  2010/02/08 17:50:26  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.36  2010/01/30 18:55:13  blueyed
 * Fix "Assigning the return value of new by reference is deprecated" (PHP 5.3)
 *
 * Revision 1.35  2009/11/30 00:22:04  fplanque
 * clean up debug info
 * show more timers in view of block caching
 *
 * Revision 1.34  2009/09/26 12:00:41  tblue246
 * Minor/coding style
 *
 * Revision 1.33  2009/09/25 07:32:51  efy-cantor
 * replace get_cache to get_*cache
 *
 * Revision 1.32  2009/08/30 00:30:52  fplanque
 * increased modularity
 *
 * Revision 1.31  2009/03/08 23:57:34  fplanque
 * 2009
 *
 * Revision 1.30  2008/04/06 19:19:31  fplanque
 * Started moving some intelligence to the Modules.
 * 1) Moved menu structure out of the AdminUI class.
 * It is part of the app structure, not the UI. Up to this point at least.
 * Note: individual Admin skins can still override the whole menu.
 * 2) Moved DB schema to the modules. This will be reused outside
 * of install for integrity checks and backup.
 * 3) cleaned up config files
 *
 * Revision 1.29  2008/02/19 11:11:14  fplanque
 * no message
 *
 * Revision 1.28  2008/01/23 12:51:21  fplanque
 * posts now have divs with IDs
 *
 * Revision 1.27  2008/01/21 09:35:22  fplanque
 * (c) 2008
 *
 * Revision 1.26  2007/09/03 16:44:28  fplanque
 * chicago admin skin
 *
 * Revision 1.25  2007/07/13 23:47:36  fplanque
 * New start pages!
 *
 * Revision 1.24  2007/06/25 10:58:47  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.23  2007/04/26 00:11:14  fplanque
 * (c) 2007
 *
 * Revision 1.22  2007/03/11 20:40:14  fplanque
 * misuse of globals
 *
 * Revision 1.21  2007/03/07 02:37:15  fplanque
 * OMG I decided that pregenerating the menus was getting to much of a PITA!
 * It's a zillion problems with the permissions.
 * This will be simplified a lot. Enough of these crazy stuff.
 *
 * Revision 1.20  2007/01/24 13:44:56  fplanque
 * cleaned up upload
 *
 * Revision 1.19  2007/01/24 00:48:57  fplanque
 * Refactoring
 *
 * Revision 1.18  2006/11/24 18:27:22  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.17  2006/08/26 20:32:48  fplanque
 * fixed redirects
 *
 * Revision 1.16  2006/08/05 23:37:03  fplanque
 * no message
 *
 * Revision 1.14  2006/07/06 19:56:29  fplanque
 * no message
 *
 * Revision 1.13  2006/06/13 21:49:14  blueyed
 * Merged from 1.8 branch
 *
 * Revision 1.12.2.1  2006/06/12 20:00:29  fplanque
 * one too many massive syncs...
 *
 * Revision 1.12  2006/04/19 22:26:24  blueyed
 * cleanup/polish
 *
 * Revision 1.11  2006/04/19 20:13:48  fplanque
 * do not restrict to :// (does not catch subdomains, not even www.)
 *
 * Revision 1.10  2006/04/14 19:25:31  fplanque
 * evocore merge with work app
 *
 * Revision 1.9  2006/04/11 21:22:25  fplanque
 * partial cleanup
 *
 */
?>
