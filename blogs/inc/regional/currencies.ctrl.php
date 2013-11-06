<?php
/**
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2009-2013 by Francois PLANQUE - {@link http://fplanque.net/}
 * Parts of this file are copyright (c)2009 by The Evo Factory - {@link http://www.evofactory.com/}.
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
 * The Evo Factory grants Francois PLANQUE the right to license
 * The Evo Factory's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author efy-maxim: Evo Factory / Maxim.
 * @author fplanque: Francois Planque.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

// Load Currency class (PHP4):
load_class( 'regional/model/_currency.class.php', 'Currency' );

/**
 * @var User
 */
global $current_User;

// Check minimum permission:
$current_User->check_perm( 'options', 'view', true );

// Memorize this as the last "tab" used in the Global Settings:
$UserSettings->set( 'pref_glob_settings_tab', $ctrl );
$UserSettings->set( 'pref_glob_regional_tab', $ctrl );
$UserSettings->dbupdate();

// Set options path:
$AdminUI->set_path( 'options', 'regional', 'currencies' );

// Get action parameter from request:
param_action();

if( param( 'curr_ID', 'integer', '', true) )
{// Load currency from cache:
	$CurrencyCache = & get_CurrencyCache();
	if( ($edited_Currency = & $CurrencyCache->get_by_ID( $curr_ID, false )) === false )
	{	unset( $edited_Currency );
		forget_param( 'curr_ID' );
		$Messages->add( sprintf( T_('Requested &laquo;%s&raquo; object does not exist any longer.'), T_('Currency') ), 'error' );
		$action = 'nil';
	}
}

switch( $action )
{
	case 'disable_currency':
	case 'enable_currency':
		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'currency' );

		// Disable a currency only if it is enabled, and user has edit access.
		$current_User->check_perm( 'options', 'edit', true );

		// Make sure the currency information was loaded. If not, just exit with error.
		if( empty($edited_Currency) )
		{
			$Messages->add( sprintf( 'The currency with ID %d could not be instantiated.', $curr_ID ), 'error' );
			break;
		}

		if ( $action == 'disable_currency' )
		{	// Disable this currency by setting flag to false.
			$edited_Currency->set( 'enabled', 0 );
			$Messages->add( sprintf( T_('Disabled currency (%s, #%d).'), $edited_Currency->name, $edited_Currency->ID ), 'success' );
		}
		elseif ( $action == 'enable_currency' )
		{	// Enable currency by setting flag to true.
			$edited_Currency->set( 'enabled', 1 );
			$Messages->add( sprintf( T_('Enabled currency (%s, #%d).'), $edited_Currency->name, $edited_Currency->ID ), 'success' );
		}

		// Update db with new flag value.
		$edited_Currency->dbupdate();

		param( 'results_curr_page', 'integer', '', true );
		param( 'results_curr_order', 'string', '', true );

		// Redirect so that a reload doesn't write to the DB twice:
		header_redirect( regenerate_url ( '', '', '', '&' ), 303 ); // Will EXIT
		// We have EXITed already at this point!!
		break;

	case 'new':
		// Check permission:
		$current_User->check_perm( 'options', 'edit', true );

		if( ! isset($edited_Currency) )
		{	// We don't have a model to use, start with blank object:
			$edited_Currency = new Currency();
		}
		else
		{	// Duplicate object in order no to mess with the cache:
			$edited_Currency = duplicate( $edited_Currency ); // PHP4/5 abstraction
			$edited_Currency->ID = 0;
		}
		break;

	case 'edit':
		// Check permission:
		$current_User->check_perm( 'options', 'edit', true );

		// Make sure we got an curr_ID:
		param( 'curr_ID', 'integer', true );
 		break;

	case 'create': // Record new currency
	case 'create_new': // Record currency and create new
	case 'create_copy': // Record currency and create similar
		// Insert new currency:
		$edited_Currency = new Currency();

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'currency' );

		// Check permission:
		$current_User->check_perm( 'options', 'edit', true );

		// Load data from request
		if( $edited_Currency->load_from_Request() )
		{	// We could load data from form without errors:

			// Insert in DB:
			$DB->begin();
			$q = $edited_Currency->dbexists();
			if($q)
			{	// We have a duplicate entry:

				param_error( 'curr_code',
					sprintf( T_('This currency already exists. Do you want to <a %s>edit the existing currency</a>?'),
						'href="?ctrl=currencies&amp;action=edit&amp;curr_ID='.$q.'"' ) );
			}
			else
			{
				$edited_Currency->dbinsert();
				$Messages->add( T_('New currency created.'), 'success' );
			}
			$DB->commit();

			if( empty($q) )
			{	// What next?

				switch( $action )
				{
					case 'create_copy':
						// Redirect so that a reload doesn't write to the DB twice:
						header_redirect( '?ctrl=currencies&action=new&curr_ID='.$edited_Currency->ID, 303 ); // Will EXIT
						// We have EXITed already at this point!!
						break;
					case 'create_new':
						// Redirect so that a reload doesn't write to the DB twice:
						header_redirect( '?ctrl=currencies&action=new', 303 ); // Will EXIT
						// We have EXITed already at this point!!
						break;
					case 'create':
						// Redirect so that a reload doesn't write to the DB twice:
						header_redirect( '?ctrl=currencies', 303 ); // Will EXIT
						// We have EXITed already at this point!!
						break;
				}
			}
		}
		break;

	case 'update':
		// Edit currency form:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'currency' );

		// Check permission:
		$current_User->check_perm( 'options', 'edit', true );

		// Make sure we got an curr_ID:
		param( 'curr_ID', 'integer', true );

		// load data from request
		if( $edited_Currency->load_from_Request() )
		{	// We could load data from form without errors:

			// Update in DB:
			$DB->begin();
			$q = $edited_Currency->dbexists();
			if($q)
			{ 	// We have a duplicate entry:
				param_error( 'curr_code',
					sprintf( T_('This currency already exists. Do you want to <a %s>edit the existing currency</a>?'),
						'href="?ctrl=currencies&amp;action=edit&amp;curr_ID='.$q.'"' ) );
			}
			else
			{
				$edited_Currency->dbupdate();
				$Messages->add( T_('Currency updated.'), 'success' );
			}
			$DB->commit();

			if( empty($q) )
			{	// If no error, Redirect so that a reload doesn't write to the DB twice:
				header_redirect( '?ctrl=currencies', 303 ); // Will EXIT
				// We have EXITed already at this point!!
			}
		}
		break;

	case 'delete':
		// Delete currency:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'currency' );

		// Check permission:
		$current_User->check_perm( 'options', 'edit', true );

		// Make sure we got an curr_ID:
		param( 'curr_ID', 'integer', true );

		if( param( 'confirm', 'integer', 0 ) )
		{ // confirmed, Delete from DB:
			$msg = sprintf( T_('Currency &laquo;%s&raquo; deleted.'), $edited_Currency->dget('name') );
			$edited_Currency->dbdelete( true );
			unset( $edited_Currency );
			forget_param( 'curr_ID' );
			$Messages->add( $msg, 'success' );
			// Redirect so that a reload doesn't write to the DB twice:
			header_redirect( '?ctrl=currencies', 303 ); // Will EXIT
			// We have EXITed already at this point!!
		}
		else
		{	// not confirmed, Check for restrictions:
			if( ! $edited_Currency->check_delete( sprintf( T_('Cannot delete currency &laquo;%s&raquo;'), $edited_Currency->dget('name') ), array(), true ) )
			{	// There are restrictions:
				$action = 'view';
			}
		}
		break;

}


$AdminUI->breadcrumbpath_init( false );
$AdminUI->breadcrumbpath_add( T_('System'), '?ctrl=system',
		T_('Global settings are shared between all blogs; see Blog settings for more granular settings.') );
$AdminUI->breadcrumbpath_add( T_('Regional settings'), '?ctrl=locales' );
$AdminUI->breadcrumbpath_add( T_('Currencies'), '?ctrl=currencies' );


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
		$edited_Currency->confirm_delete(
				sprintf( T_('Delete currency &laquo;%s&raquo;?'), $edited_Currency->dget('name') ),
				'currency', $action, get_memorized( 'action' ) );
	case 'new':
	case 'create':
	case 'create_new':
	case 'create_copy':
	case 'edit':
	case 'update':
		$AdminUI->disp_view( 'regional/views/_currency.form.php' );
		break;

	default:
		// No specific request, list all currencies:
		// Cleanup context:
		forget_param( 'curr_ID' );
		// Display currency list:
		$AdminUI->disp_view( 'regional/views/_currency_list.view.php' );
		break;
}

$AdminUI->disp_payload_end();

// Display body bottom, debug info and close </html>:
$AdminUI->disp_global_footer();

/*
 * $Log$
 * Revision 1.23  2013/11/06 08:04:36  efy-asimo
 * Update to version 5.0.1-alpha-5
 *
 */
?>