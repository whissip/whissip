<?php
/**
 * This file implements the goals.
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.com/}
 *
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class( 'sessions/model/_goal.class.php', 'Goal' );
load_funcs('sessions/model/_hitlog.funcs.php');

/**
 * @var User
 */
global $current_User;

global $dispatcher;

$blog = 0;

// Do we have permission to view all stats (aggregated stats) ?
$sessions_Module->check_perm( 'view' );


$tab3 = param( 'tab3', 'string', 'goals', true );
$AdminUI->set_path( 'stats', 'goals', $tab3 );

param_action();

if( param( 'goal_ID', 'integer', '', true) )
{// Load file type:
	$GoalCache = & get_GoalCache();
	if( ($edited_Goal = & $GoalCache->get_by_ID( $goal_ID, false )) === false )
	{	// We could not find the goal to edit:
		unset( $edited_Goal );
		forget_param( 'goal_ID' );
		$Messages->add( sprintf( T_('Requested &laquo;%s&raquo; object does not exist any longer.'), T_('Goal') ), 'error' );
		$action = 'nil';
	}
}

switch( $action )
{

	case 'new':
	case 'copy':
		// Check permission:
		$sessions_Module->check_perm( 'edit' );

		if( ! isset($edited_Goal) )
		{	// We don't have a model to use, start with blank object:
			$edited_Goal = new Goal();
		}
		else
		{	// Duplicate object in order no to mess with the cache:
			$edited_Goal = duplicate( $edited_Goal ); // PHP4/5 abstraction
			$edited_Goal->ID = 0;
		}
		break;

	case 'edit':
		// Edit file type form...:

		// Check permission:
		$sessions_Module->check_perm( 'edit' );

		// Make sure we got an ftyp_ID:
		param( 'goal_ID', 'integer', true );
 		break;

	case 'create': // Record new goal
	case 'create_new': // Record goal and create new
	case 'create_copy': // Record goal and create similar
		// Insert new file type...:
		$edited_Goal = new Goal();

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'goal' );

		// Check permission:
		$sessions_Module->check_perm( 'edit' );

		// load data from request
		if( $edited_Goal->load_from_Request() )
		{	// We could load data from form without errors:

			// Insert in DB:
			$DB->begin();
			$q = $edited_Goal->dbexists();
			if($q)
			{	// We have a duplicate entry:

				param_error( 'goal_key',
					sprintf( T_('This goal already exists. Do you want to <a %s>edit the existing goal</a>?'),
						'href="?ctrl=goals&amp;action=edit&amp;goal_ID='.$q.'"' ) );
			}
			else
			{
				$edited_Goal->dbinsert();
				$Messages->add( T_('New goal created.'), 'success' );
			}
			$DB->commit();

			if( empty($q) )
			{	// What next?
				switch( $action )
				{
					case 'create_copy':
						// Redirect so that a reload doesn't write to the DB twice:
						header_redirect( '?ctrl=goals&action=new&goal_ID='.$edited_Goal->ID, 303 ); // Will EXIT
						// We have EXITed already at this point!!
						break;
					case 'create_new':
						// Redirect so that a reload doesn't write to the DB twice:
						header_redirect( '?ctrl=goals&action=new', 303 ); // Will EXIT
						// We have EXITed already at this point!!
						break;
					case 'create':
						// Redirect so that a reload doesn't write to the DB twice:
						header_redirect( '?ctrl=goals', 303 ); // Will EXIT
						// We have EXITed already at this point!!
						break;
				}
			}
		}
		break;

	case 'update':
		// Edit file type form...:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'goal' );

		// Check permission:
		$sessions_Module->check_perm( 'edit' );

		// Make sure we got an ftyp_ID:
		param( 'goal_ID', 'integer', true );

		// load data from request
		if( $edited_Goal->load_from_Request() )
		{	// We could load data from form without errors:

			// Update in DB:
			$DB->begin();
			$q = $edited_Goal->dbexists();
			if($q)
			{	// We have a duplicate entry:

				param_error( 'goal_key',
					sprintf( T_('This goal already exists. Do you want to <a %s>edit the existing goal</a>?'),
						'href="?ctrl=goals&amp;action=edit&amp;goal_ID='.$q.'"' ) );
			}
			else
			{
				$edited_Goal->dbupdate();
				$Messages->add( T_('Goal updated.'), 'success' );
			}
			$DB->commit();

			if( empty($q) )
			{
				$action = 'list';
				// Redirect so that a reload doesn't write to the DB twice:
				header_redirect( '?ctrl=goals', 303 ); // Will EXIT
				// We have EXITed already at this point!!
			}
		}


		break;

	case 'delete':
		// Delete file type:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'goal' );

		// Check permission:
		$sessions_Module->check_perm( 'edit' );

		// Make sure we got an ftyp_ID:
		param( 'goal_ID', 'integer', true );

		if( param( 'confirm', 'integer', 0 ) )
		{ // confirmed, Delete from DB:
			$msg = sprintf( T_('Goal &laquo;%s&raquo; deleted.'), $edited_Goal->dget('name') );
			$edited_Goal->dbdelete( true );
			unset( $edited_Goal );
			forget_param( 'goal_ID' );
			$Messages->add( $msg, 'success' );
			// Redirect so that a reload doesn't write to the DB twice:
			header_redirect( '?ctrl=goals', 303 ); // Will EXIT
			// We have EXITed already at this point!!
		}
		else
		{	// not confirmed, Check for restrictions:
			if( ! $edited_Goal->check_delete( sprintf( T_('Cannot delete goal &laquo;%s&raquo;'), $edited_Goal->dget('name') ) ) )
			{	// There are restrictions:
				$action = 'view';
			}
		}
		break;

}

$AdminUI->breadcrumbpath_init();
$AdminUI->breadcrumbpath_add( T_('Analytics'), '?ctrl=stats' );
$AdminUI->breadcrumbpath_add( T_('Goal tracking'), '?ctrl=goals' );
switch( $tab3 )
{
	case 'goals':
		$AdminUI->breadcrumbpath_add( T_('Goal definitions'), '?ctrl=goals' );
		break;
	case 'stats':
		$AdminUI->breadcrumbpath_add( T_('Goal hit stats'), '?ctrl=goals&amp;tab3=stats' );
		break;
}

// Display <html><head>...</head> section! (Note: should be done early if actions do not redirect)
$AdminUI->disp_html_head();

// Display title, menu, messages, etc. (Note: messages MUST be displayed AFTER the actions)
$AdminUI->disp_body_top();

$AdminUI->disp_payload_begin();

/**
 * Display payload:
 */
switch( $action )
{
	case 'nil':
		// Do nothing
		break;


	case 'delete':
		// We need to ask for confirmation:
		$edited_Goal->confirm_delete(
				sprintf( T_('Delete goal &laquo;%s&raquo;?'), $edited_Goal->dget('name') ),
				'goal', $action, get_memorized( 'action' ) );
		/* no break */
	case 'new':
	case 'copy':
	case 'create':	// we return in this state after a validation error
	case 'create_new':	// we return in this state after a validation error
	case 'create_copy':	// we return in this state after a validation error
	case 'edit':
	case 'update':	// we return in this state after a validation error
		$AdminUI->disp_view( 'sessions/views/_goal.form.php' );
		break;


	default:
		// No specific request, list all file types:
		switch( $tab3 )
		{
			case 'goals':
				// Cleanup context:
				forget_param( 'goal_ID' );
				// Display goals list:
				$AdminUI->disp_view( 'sessions/views/_stats_goals.view.php' );
				break;

			case 'stats':
				$AdminUI->disp_view( 'sessions/views/_goal_hitsummary.view.php' );
				break;
		}

}

$AdminUI->disp_payload_end();

// Display body bottom, debug info and close </html>:
$AdminUI->disp_global_footer();

/*
 * $Log$
 * Revision 1.23  2010/02/08 17:53:55  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.22  2010/01/30 18:55:33  blueyed
 * Fix "Assigning the return value of new by reference is deprecated" (PHP 5.3)
 *
 * Revision 1.21  2010/01/10 23:24:09  fplanque
 * crumbs...
 *
 * Revision 1.20  2010/01/03 12:03:17  fplanque
 * More crumbs...
 *
 * Revision 1.19  2009/12/12 01:13:08  fplanque
 * A little progress on breadcrumbs on menu structures alltogether...
 *
 * Revision 1.18  2009/12/06 22:55:21  fplanque
 * Started breadcrumbs feature in admin.
 * Work in progress. Help welcome ;)
 * Also move file settings to Files tab and made FM always enabled
 *
 * Revision 1.17  2009/09/25 07:33:14  efy-cantor
 * replace get_cache to get_*cache
 *
 * Revision 1.16  2009/09/19 20:49:51  fplanque
 * Cleaner way of implementing permissions.
 *
 * Revision 1.15  2009/09/14 13:39:00  efy-arrin
 * Included the ClassName in load_class() call with proper UpperCase
 *
 * Revision 1.14  2009/09/03 07:24:58  efy-maxim
 * 1. Show edit screen again if current currency/goal exists in database.
 * 2. Convert currency code to uppercase
 *
 * Revision 1.13  2009/09/02 22:50:49  efy-maxim
 * Clean error message for currency/goal already exists
 *
 * Revision 1.12  2009/08/31 20:35:31  fplanque
 * cleanup
 *
 * Revision 1.11  2009/08/31 14:22:10  tblue246
 * Better fix
 *
 * Revision 1.9  2009/08/30 20:58:10  tblue246
 * Goals ctrl: 1. Do not use localized messages to determine action. 2. Removed redundant "copy" action (always use "new" action with goal_ID).
 *
 * Revision 1.8  2009/08/30 19:54:24  fplanque
 * less translation messgaes for infrequent errors
 *
 * Revision 1.7  2009/08/30 14:13:49  fplanque
 * clean redirects after DB actions
 *
 * Revision 1.6  2009/08/30 00:42:57  fplanque
 * minor
 *
 * Revision 1.5  2009/07/06 23:52:25  sam2kb
 * Hardcoded "admin.php" replaced with $dispatcher
 *
 * Revision 1.4  2009/03/08 23:57:45  fplanque
 * 2009
 *
 * Revision 1.3  2008/05/10 22:59:09  fplanque
 * keyphrase logging
 *
 * Revision 1.2  2008/04/24 01:56:08  fplanque
 * Goal hit summary
 *
 * Revision 1.1  2008/04/17 11:53:18  fplanque
 * Goal editing
 *
 * Revision 1.2  2008/01/21 09:35:28  fplanque
 * (c) 2008
 *
 * Revision 1.1  2007/06/25 10:59:51  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.13  2007/04/26 00:11:13  fplanque
 * (c) 2007
 *
 * Revision 1.12  2006/11/26 01:42:08  fplanque
 * doc
 *
 */
?>