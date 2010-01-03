<?php
/**
 * This file implements the UI controller for skins management.
 *
 * b2evolution - {@link http://b2evolution.net/}
 *
 * @copyright (c)2003-2009 by Francois PLANQUE - {@link http://fplanque.net/}
 * Parts of this file are copyright (c)2004-2006 by Daniel HAHLER - {@link http://thequod.de/contact}.
 *
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 *
 * {@internal Open Source relicensing agreement:
 * }}
 *
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_funcs( 'skins/_skin.funcs.php' );

// Check permission to display:
$current_User->check_perm( 'options', 'view', true );

param( 'action', 'string', 'list' );

param( 'redirect_to', 'string', '?ctrl=skins' );

if( param( 'skin_ID', 'integer', '', true) )
{// Load file type:
	$SkinCache = & get_SkinCache();
	if( ($edited_Skin = & $SkinCache->get_by_ID( $skin_ID, false )) === false )
	{	// We could not find the skin to edit:
		unset( $edited_Skin );
		forget_param( 'skin_ID' );
		$Messages->head = T_('Cannot edit skin!');
		$Messages->add( T_('Requested skin is not installed any longer.'), 'error' );
		$action = 'nil';
	}
}


/**
 * Perform action:
 */
switch( $action )
{
	case 'create':
		param( 'skin_folder', 'string', true );
		// Check validity of requested skin name:
		if( preg_match( '~([^-A-Za-z0-9._]|\.\.)~', $skin_folder ) )
		{
			debug_die( 'The requested skin name is invalid.' );
		}

		// Check permission to edit:
		$current_User->check_perm( 'options', 'edit', true );

		// CREATE NEW SKIN:
		$edited_Skin = & skin_install( $skin_folder );

		$Messages->add( T_('Skin has been installed.'), 'success' );

		// We want to highlight the edited object on next list display:
 		$Session->set( 'fadeout_array', array( 'skin_ID' => array($edited_Skin->ID) ) );

		// PREVENT RELOAD & Switch to list mode:
		header_redirect( $redirect_to );
		break;


	case 'update':
		// Update skin properties:

		// Check permission:
		$current_User->check_perm( 'options', 'edit', true );

		// Make sure we got an skin_ID:
		param( 'skin_ID', 'integer', true );

		// load data from request
		if( $edited_Skin->load_from_Request() )
		{	// We could load data from form without errors:
			// Update in DB:
			$edited_Skin->dbupdate();
			$Messages->add( T_('Skin properties updated.'), 'success' );

			// We want to highlight the edited object on next list display:
 			$Session->set( 'fadeout_array', array( 'skin_ID' => array($edited_Skin->ID) ) );

			$action = 'list';
		}
		break;


	case 'reload':
		// Reload containers:

 		// Check permission:
		$current_User->check_perm( 'options', 'edit', true );

		// Make sure we got an skin_ID:
		param( 'skin_ID', 'integer', true );

		// Look for containers in skin file:
		$edited_Skin->discover_containers();

		// Save to DB:
		$edited_Skin->db_save_containers();

		// We want to highlight the edited object on next list display:
 		$Session->set( 'fadeout_array', array( 'skin_ID' => array($edited_Skin->ID) ) );

		$action = 'list';
		break;


	case 'delete':
		// Uninstall a skin:

		// Check permission:
		$current_User->check_perm( 'options', 'edit', true );

		// Make sure we got an skin_ID:
		param( 'skin_ID', 'integer', true );

		if( param( 'confirm', 'integer', 0 ) )
		{ // confirmed, Delete from DB:
			$msg = sprintf( T_('Skin &laquo;%s&raquo; uninstalled.'), $edited_Skin->dget('name') );
			$edited_Skin->dbdelete( true );
			unset( $edited_Skin );
			forget_param( 'skin_ID' );
			$Messages->add( $msg, 'success' );

			// PREVENT RELOAD & Switch to list mode:
			header_redirect( '?ctrl=skins' );
		}
		else
		{	// not confirmed, Check for restrictions:
			if( ! $edited_Skin->check_delete( sprintf( T_('Cannot uninstall skin &laquo;%s&raquo;'), $edited_Skin->dget('name') ) ) )
			{	// There are restrictions:
				$action = 'edit';
			}
		}

		break;
}


$AdminUI->set_path( 'options', 'skins' );


$AdminUI->breadcrumbpath_init();
$AdminUI->breadcrumbpath_add( T_('Global settings'), '?ctrl=settings',
		T_('Global settings are shared between all blogs; see Blog settings for more granular settings.') );
$AdminUI->breadcrumbpath_add( T_('Skin configuration'), '?ctrl=skins' );


// Display <html><head>...</head> section! (Note: should be done early if actions do not redirect)
$AdminUI->disp_html_head();

// Display title, menu, messages, etc. (Note: messages MUST be displayed AFTER the actions)
$AdminUI->disp_body_top();

// Begin payload block:
$AdminUI->disp_payload_begin();

/**
 * Display Payload:
 */
switch( $action )
{
	case 'new':
		// Display VIEW:
		$AdminUI->disp_view( 'skins/views/_skin_list_available.view.php' );
		break;

	case 'delete':
		// We need to ask for confirmation:
		$edited_Skin->confirm_delete(
				sprintf( T_('Uninstall skin &laquo;%s&raquo;?'),  $edited_Skin->dget( 'name' ) ),
				'skin', $action, get_memorized( 'action' ) );
	case 'edit':
	case 'update':	// we return in this state after a validation error
		// Display VIEW:
		$AdminUI->disp_view( 'skins/views/_skin.form.php' );
		break;

	case 'list':
		// Display VIEW:
		$AdminUI->disp_view( 'skins/views/_skin_list.view.php' );
		break;
}

// End payload block:
$AdminUI->disp_payload_end();

// Display body bottom, debug info and close </html>:
$AdminUI->disp_global_footer();

/*
 * $Log$
 * Revision 1.9  2010/01/03 12:03:18  fplanque
 * More crumbs...
 *
 * Revision 1.8  2009/12/06 22:55:22  fplanque
 * Started breadcrumbs feature in admin.
 * Work in progress. Help welcome ;)
 * Also move file settings to Files tab and made FM always enabled
 *
 * Revision 1.7  2009/09/26 12:00:43  tblue246
 * Minor/coding style
 *
 * Revision 1.6  2009/09/25 07:33:14  efy-cantor
 * replace get_cache to get_*cache
 *
 * Revision 1.5  2009/05/23 20:20:18  fplanque
 * Skins can now have a _skin.class.php file to override default Skin behaviour. Currently only the default name but can/will be extended.
 *
 * Revision 1.4  2009/03/08 23:57:45  fplanque
 * 2009
 *
 * Revision 1.3  2008/01/21 09:35:34  fplanque
 * (c) 2008
 *
 * Revision 1.2  2007/09/29 03:42:13  fplanque
 * skin install UI improvements
 *
 * Revision 1.1  2007/06/25 11:01:31  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.10  2007/06/24 18:28:56  fplanque
 * refactored skin install
 *
 * Revision 1.9  2007/04/26 00:11:15  fplanque
 * (c) 2007
 *
 * Revision 1.8  2007/01/23 21:45:26  fplanque
 * "enforce" foreign keys
 *
 * Revision 1.7  2007/01/09 00:55:16  blueyed
 * fixed typo(s)
 *
 * Revision 1.6  2007/01/08 02:11:56  fplanque
 * Blogs now make use of installed skins
 * next step: make use of widgets inside of skins
 *
 * Revision 1.5  2007/01/07 23:38:21  fplanque
 * discovery of skin containers
 *
 * Revision 1.4  2007/01/07 19:40:18  fplanque
 * discover skin containers
 *
 * Revision 1.3  2007/01/07 05:32:12  fplanque
 * added some more DB skin handling (install+uninstall+edit properties ok)
 * still useless though :P
 * next step: discover containers in installed skins
 *
 * Revision 1.2  2006/12/29 01:10:06  fplanque
 * basic skin registering
 *
 * Revision 1.1  2006/12/05 05:41:42  fplanque
 * created playground for skin management
 *
 */
?>