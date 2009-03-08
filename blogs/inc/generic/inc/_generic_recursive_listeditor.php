<?php
/**
 * This file implements the generic recursive list editor
 *
 * NOTE: It uses <code>$AdminUI->get_path(1).'.php'</code> to link back to the ID of the entry.
 *       If that causes problems later, we'd probably need to set a global like $listeditor_url.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2009 by Francois PLANQUE - {@link http://fplanque.net/}
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
 * PROGIDISTRI S.A.S. grants Francois PLANQUE the right to license
 * PROGIDISTRI S.A.S.'s contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE.
 * @author mbruneau: Marc BRUNEAU / PROGIDISTRI
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

param( 'action', 'string', 'list' );

if( param( $GenericCategoryCache->dbIDname, 'integer', NULL, true, false, false ) )
{
	if( ($edited_GenericCategory = & $GenericCategoryCache->get_by_ID( ${$GenericCategoryCache->dbIDname}, false, true, $subset_ID )) === false )
	{	// We could not find the element to edit:
		unset( $edited_GenericCategory );
		$Messages->head = T_('Cannot edit element!');
		$Messages->add( T_('Requested element does not exist any longer.'), 'error' );
		$action = 'nil';
	}
}

if ( !is_null( param( $GenericCategoryCache->dbprefix.'parent_ID', 'integer', NULL ) ) )
{
	if( ( $edited_parent_GenericElement = & $GenericCategoryCache->get_by_ID( ${$GenericCategoryCache->dbprefix.'parent_ID'}, false, true, $subset_ID ) ) === false )
	{ // Parent generic category doesn't exist any longer.
		unset( $GenericCategoryCache->dbIDname );
		$Messages->head = T_('Cannot edit element!');
		$Messages->add( T_('Requested element does not exist any longer.'), 'error' );
		$action = 'nil';
	}
}

// Init fadeout result array of IDs:
$result_fadeout = array();

/**
 * Check locked elements
 */
if( !empty( $locked_IDs )
		&& in_array( $action, array( 'edit', 'update', 'delete' ) )
		&& in_array( $$GenericCategoryCache->dbIDname, $locked_IDs ) )
{
	$Messages->add( T_('This element is locked and cannot be edited!') );
	$action = 'list';
}


/**
 * Perform action:
 */
switch( $action )
{
	case 'new':
		// New action

		if( ! $permission_to_edit )
		{
			debug_die( 'No permission to edit' );
		}

		$edited_GenericCategory = & $GenericCategoryCache->new_obj( NULL, $subset_ID );

		if( isset( $edited_parent_GenericElement ) )
		{
			$edited_GenericCategory->parent_ID = $edited_parent_GenericElement->ID;
			$edited_GenericCategory->parent_name = $edited_parent_GenericElement->name;
		}
		else
		{
			$edited_GenericCategory->parent_name = T_('Root');
		}

		break;


	case 'edit':
		// Edit element form...:
		// Make sure we got an ID:
		param( $GenericCategoryCache->dbIDname, 'integer', true );

		if( ! $permission_to_edit )
		{
			debug_die( 'No permission to edit' );
		}

		// Get the page number we come from:
		$previous_page = param( 'results'.$GenericCategoryCache->dbprefix.'page', 'integer', 1, true );

		break;


	case 'create':
		// Insert new element...:

		if( ! $permission_to_edit )
		{
			debug_die( 'No permission to edit' );
		}

		$edited_GenericCategory = & $GenericCategoryCache->new_obj( NULL, $subset_ID );

		// load data from request
		if( $edited_GenericCategory->load_from_Request() )
		{	// We could load data from form without errors:
			// Insert in DB:
			if( $edited_GenericCategory->dbinsert() !== false )
			{
				$Messages->add( T_('New element created.'), 'success' ); // TODO CHANGES THIS
				// Add the ID of the new element to the result fadeout
				$result_fadeout[$edited_GenericCategory->dbIDname][] = $edited_GenericCategory->ID;
				$action = 'list';
			}
		}
		break;


	case 'update':
		// Make sure we got an ID:
		param( $GenericCategoryCache->dbIDname, 'integer', true );

		if( ! $permission_to_edit )
		{
			debug_die( 'No permission to edit' );
		}

		// LOAD FORM DATA:
		if( $edited_GenericCategory->load_from_Request() )
		{	// We could load data from form without errors:
			// Update in DB:
			if( $edited_GenericCategory->dbupdate() !== false )
			{
				$Messages->add( T_('Element updated.'), 'success' ); //ToDO change htis
				// Add the ID of the updated element to the result fadeout
				$result_fadeout[$edited_GenericCategory->dbIDname][] = $edited_GenericCategory->ID;
			}
			$action = 'list';
		}
		else
		{
			// Get the page number we come from:
			$previous_page = param( 'results'.$GenericCategoryCache->dbprefix.'page', 'integer', 1, true );
		}
		break;


	case 'delete':
		// Delete entry:
		param( $GenericCategoryCache->dbIDname, 'integer', true );

		if( ! $permission_to_edit )
		{
			debug_die( 'No permission to edit' );
		}

		// Set restrictions for element
		$edited_GenericCategory->delete_restrictions = $delete_restrictions;

		if( param( 'confirm', 'integer', 0 ) )
		{ // confirmed, Delete from DB:
			$msg = sprintf( T_('Element &laquo;%s&raquo; deleted.'), $edited_GenericCategory->dget( 'name' ) );
			$GenericCategoryCache->dbdelete_by_ID( $edited_GenericCategory->ID );
			unset($edited_GenericCategory);
			forget_param( $GenericCategoryCache->dbIDname );
			$Messages->add( $msg, 'success' );
			$action = 'list';
		}
		else
		{	// not confirmed, Check for restrictions:
			// Get the page number we come from:
			$previous_page = param( 'results_'.$GenericCategoryCache->dbprefix.'page', 'integer', 1, true );
			if( ! $edited_GenericCategory->check_delete( sprintf( T_('Cannot delete element &laquo;%s&raquo;'), $edited_GenericCategory->dget( 'name' ) ) ) )
			{	// There are restrictions:
				$action = 'edit';
			}
		}
		break;

}


// Display <html><head>...</head> section! (Note: should be done early if actions do not redirect)
$AdminUI->disp_html_head();

// Display title, menu, messages, etc. (Note: messages MUST be displayed AFTER the actions)
$AdminUI->disp_body_top();


/**
 * Display payload:
 */
switch( $action )
{
	case 'nil':
		// Do nothing
		break;

	case 'new':
	case 'copy':
	case 'create':
	case 'edit':
	case 'update':
	case 'delete':
		// Begin payload block:
		$AdminUI->disp_payload_begin();

		if( $action == 'delete' )
		{	// We need to ask for confirmation:
			$edited_GenericCategory->confirm_delete(
					sprintf( T_('Delete element &laquo;%s&raquo;?'),  $edited_GenericCategory->dget( 'name' ) ),
					$action, get_memorized( 'action' ) );
		}

		if( $form_below_list )
		{
			// Display list VIEW before form view:
			if( !empty( $list_view_path ) )
			{
				$AdminUI->disp_view( $list_view_path );
			}
			else
			{
				$AdminUI->disp_view( 'generic/_generic_recursive_list.inc.php' );
			}
		}

		// Display category edit form:
		if( !empty( $edit_view_path ) )
		{
			$AdminUI->disp_view( $edit_view_path );
		}
		else
		{
			$AdminUI->disp_view( 'generic/_generic_category.form.php' );
		}

		// End payload block:
		$AdminUI->disp_payload_end();
		break;

	case 'list':
	default:
		// Begin payload block:
		$AdminUI->disp_payload_begin();

		// Display list VIEW:
		if( !empty( $list_view_path ) )
		{
			$AdminUI->disp_view( $list_view_path );
		}
		else
		{
			$AdminUI->disp_view( 'generic/_generic_recursive_list.inc.php' );
		}

		// End payload block:
		$AdminUI->disp_payload_end();
		break;
}


// Fadeout javascript
echo '<script type="text/javascript" src="'.$rsc_url.'js/fadeout.js"></script>';
echo '<script type="text/javascript">addEvent( window, "load", Fat.fade_all, false);</script>';


// End payload block:
$AdminUI->disp_payload_end();

// Display body bottom, debug info and close </html>:
$AdminUI->disp_global_footer();

/*
 * $Log$
 * Revision 1.3  2009/03/08 23:57:43  fplanque
 * 2009
 *
 * Revision 1.2  2008/01/21 09:35:30  fplanque
 * (c) 2008
 *
 * Revision 1.1  2007/06/25 11:00:13  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.13  2007/04/26 00:11:11  fplanque
 * (c) 2007
 *
 * Revision 1.12  2007/01/07 05:27:41  fplanque
 * extended fadeout, but still not fixed everywhere
 *
 * Revision 1.11  2006/12/09 17:59:33  fplanque
 * started "moving chapters accross blogs" feature
 *
 * Revision 1.10  2006/11/24 18:27:23  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.9  2006/11/22 15:39:11  yabs
 * Removed duplicate  footer call
 */
?>