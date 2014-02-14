<?php
/**
 * This file implements the UI controller for browsing the email tools.
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2013 by Francois Planque - {@link http://fplanque.com/}.
 *
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
 *
 * {@internal Open Source relicensing agreement:
 * Vegar BERG GULDAL grants Francois PLANQUE the right to license
 * Vegar BERG GULDAL's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * @var User
 */
global $current_User;

global $DB;

// Check permission:
$current_User->check_perm( 'emails', 'view', true );

load_funcs('tools/model/_email.funcs.php');

param_action();

$tab = param( 'tab', 'string', 'blocked', true );

param( 'action', 'string' );

if( $tab == 'blocked' )
{	// Email addresses
	load_class( 'tools/model/_emailblocked.class.php', 'EmailBlocked' );
	if( param( 'emblk_ID', 'integer', '', true) )
	{	// Load Email Blocked object
		$EmailBlockedCache = & get_EmailBlockedCache();
		if( ( $edited_EmailBlocked = & $EmailBlockedCache->get_by_ID( $emblk_ID, false ) ) === false )
		{	// We could not find the goal to edit:
			unset( $edited_EmailBlocked );
			forget_param( 'emblk_ID' );
			$Messages->add( sprintf( T_('Requested &laquo;%s&raquo; object does not exist any longer.'), T_('Email Blocked') ), 'error' );
		}
	}
}

switch( $action )
{
	case 'settings': // Update the email settings
		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'emailsettings' );

		// Check permission:
		$current_User->check_perm( 'emails', 'edit', true );

		/** Email notifications **/

		// Sender email address
		$sender_email = param( 'notification_sender_email', 'string', '' );
		param_check_email( 'notification_sender_email', true );
		$Settings->set( 'notification_sender_email',  $sender_email );

		// Return path
		$return_path = param( 'notification_return_path', 'string', '' );
		param_check_email( 'notification_return_path', true );
		$Settings->set( 'notification_return_path', $return_path );

		// Sender name
		$sender_name = param( 'notification_sender_name', 'string', '' );
		param_check_not_empty( 'notification_sender_name' );
		$Settings->set( 'notification_sender_name',  $sender_name );

		// Site short name
		$short_name = param( 'notification_short_name', 'string', '' );
		param_check_not_empty( 'notification_short_name' );
		$Settings->set( 'notification_short_name',  $short_name );

		// Site long name
		$Settings->set( 'notification_long_name',  param( 'notification_long_name', 'string', '' ) );

		// Site logo url
		$Settings->set( 'notification_logo',  param( 'notification_logo', 'string', '' ) );

		/** Settings to decode the returned emails **/
		param( 'repath_enabled', 'boolean', 0 );
		$Settings->set( 'repath_enabled', $repath_enabled );

		param( 'repath_method', 'string', true );
		$Settings->set( 'repath_method', strtolower( $repath_method ) );

		param( 'repath_server_host', 'string', true );
		$Settings->set( 'repath_server_host', evo_strtolower( $repath_server_host ) );

		param( 'repath_server_port', 'integer', true );
		$Settings->set( 'repath_server_port', $repath_server_port );

		param( 'repath_encrypt', 'string', true );
		$Settings->set( 'repath_encrypt', $repath_encrypt );

		param( 'repath_novalidatecert', 'boolean', 0 );
		$Settings->set( 'repath_novalidatecert', $repath_novalidatecert );

		param( 'repath_username', 'string', true );
		$Settings->set( 'repath_username', $repath_username );

		param( 'repath_password', 'string', true );
		$Settings->set( 'repath_password', $repath_password );

		param( 'repath_delete_emails', 'boolean', 0 );
		$Settings->set( 'repath_delete_emails', $repath_delete_emails );

		param( 'repath_subject', 'text', true );
		$Settings->set( 'repath_subject', $repath_subject );

		param( 'repath_body_terminator', 'text', true );
		$Settings->set( 'repath_body_terminator', $repath_body_terminator );

		param( 'repath_errtype', 'text', true );
		if( strlen( $repath_errtype ) > 5000 )
		{	// Crop the value by max available size
			$Messages->add( T_('Maximum length of the field "Error message decoding configuration" is 5000 symbols, the big value will be cropped.'), 'note' );
			$repath_errtype = substr( $repath_errtype, 0, 5000 );
		}
		$Settings->set( 'repath_errtype', $repath_errtype );

		if( ! $Messages->has_errors() )
		{
			$Settings->dbupdate();
			$Messages->add( T_('Settings updated.'), 'success' );
			// Redirect so that a reload doesn't write to the DB twice:
			header_redirect( '?ctrl=email&tab=settings', 303 ); // Will EXIT
			// We have EXITed already at this point!!
		}
		break;

	case 'test_1':
	case 'test_2':
	case 'test_3':
		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'emailsettings' );

		// Check permission:
		$current_User->check_perm( 'emails', 'edit', true );

		load_funcs( 'cron/model/_decode_returned_emails.funcs.php');
		load_class( '_ext/mime_parser/rfc822_addresses.php', 'rfc822_addresses_class' );
		load_class( '_ext/mime_parser/mime_parser.php', 'mime_parser_class' );

		if( isset($GLOBALS['files_Module']) )
		{
			load_funcs( 'files/model/_file.funcs.php');
		}

		global $dre_messages;

		switch( $action )
		{
			case 'test_1':
				if( $mbox = dre_connect() )
				{	// Close opened connection
					imap_close( $mbox );
				}
				break;

			case 'test_2':
				if( $mbox = dre_connect() )
				{
					// Read messages from server
					dre_msg('Reading messages from server');
					$imap_obj = imap_check( $mbox );
					dre_msg('Found '.$imap_obj->Nmsgs.' messages');

					if( $imap_obj->Nmsgs > 0 )
					{	// We will read only 1 message from server in test mode
						dre_process_messages( $mbox, 1 );
					}
					else
					{
						dre_msg( T_('There are no messages in the mailbox') );
					}
					imap_close( $mbox );
				}
				break;

				case 'test_3':
					param( 'test_error_message', 'raw', '' );
					if( !empty( $test_error_message ) )
					{	// Simulate a message processing
						dre_simulate_message( $test_error_message );
						$repath_test_output = implode( "<br />\n", $dre_messages );
					}
					break;
		}

		$Messages->clear(); // Clear all messages

		if( !empty( $dre_messages ) )
		{	// We will display the output in a scrollable fieldset
			$repath_test_output = implode( "<br />\n", $dre_messages );
		}
		break;

	case 'blocked_new':
		// Init Email Blocked to show on the form
		$edited_EmailBlocked = new EmailBlocked();
		break;

	case 'blocked_save':
		// Update Email Blocked...

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'email_blocked' );

		$action = 'blocked_edit';
		if( !isset( $edited_EmailBlocked ) )
		{	// Create a new address
			$edited_EmailBlocked = new EmailBlocked();
			$action = 'blocked_new';
		}

		// load data from request
		if( $edited_EmailBlocked->load_from_Request() )
		{	// We could load data from form without errors:
			// Save Email Blocked in DB:
			$edited_EmailBlocked->dbsave();
			$Messages->add( T_('The email address was updated.'), 'success' );

			// Redirect so that a reload doesn't write to the DB twice:
			header_redirect( '?ctrl=email&tab=blocked', 303 ); // Will EXIT
			// We have EXITed already at this point!!
		}
		break;

	case 'blocked_delete':
		// Delete Email Blocked...

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'email_blocked' );

		// Make sure we got an iprange_ID:
		param( 'emblk_ID', 'integer', true );

		if( $edited_EmailBlocked->dbdelete() )
		{
			$Messages->add( T_('The email address was deleted.'), 'success' );

			// Redirect so that a reload doesn't write to the DB twice:
			header_redirect( '?ctrl=email&tab=blocked', 303 ); // Will EXIT
			// We have EXITed already at this point!!
		}
		break;
}

$AdminUI->breadcrumbpath_init( false );
$AdminUI->breadcrumbpath_add( T_('Emails'), '?ctrl=email' );

switch( $tab )
{
	case 'sent':
		$AdminUI->breadcrumbpath_add( T_('Sent'), '?ctrl=email&amp;tab='.$tab );

		$emlog_ID = param( 'emlog_ID', 'integer', 0 );
		if( empty( $emlog_ID ) )
		{ // Init datepicker css on list page
			require_css( 'ui.datepicker.css' );
		}
		else
		{ // Require the styles for email content
			require_css( $emailskins_url.'_email_style.css' );
		}
		break;

	case 'blocked':
		$AdminUI->breadcrumbpath_add( T_('Addresses'), '?ctrl=email&amp;tab='.$tab );
		if( !isset( $edited_EmailBlocked ) )
		{	// List page
			// Init datepicker css
			require_css( 'ui.datepicker.css' );
			// Init js to edit status field
			require_js( 'jquery/jquery.jeditable.js', 'rsc_url' );
		}
		break;

	case 'return':
		$AdminUI->breadcrumbpath_add( T_('Returned'), '?ctrl=email&amp;tab='.$tab );
		if( empty( $emret_ID ) )
		{	// Init datepicker css on list page
			require_css( 'ui.datepicker.css' );
		}
		break;

	case 'settings':
		$AdminUI->breadcrumbpath_add( T_('Settings'), '?ctrl=email&amp;tab='.$tab );
		break;
}

$AdminUI->set_path( 'email', $tab );

// Display <html><head>...</head> section! (Note: should be done early if actions do not redirect)
$AdminUI->disp_html_head();

// Display title, menu, messages, etc. (Note: messages MUST be displayed AFTER the actions)
$AdminUI->disp_body_top();

// Begin payload block:
$AdminUI->disp_payload_begin();

evo_flush();

switch( $tab )
{
	case 'sent':
		if( $emlog_ID > 0 )
		{	// Display a details of selected email log
			$MailLog = $DB->get_row( '
				SELECT *
				  FROM T_email__log
				 WHERE emlog_ID = '.$DB->quote( $emlog_ID ) );
			if( $MailLog )
			{	// The mail log exists with selected ID
				$AdminUI->disp_view( 'tools/views/_email_sent_details.view.php' );
				break;
			}
		}
		// Display a list of email logs:
		$AdminUI->disp_view( 'tools/views/_email_sent.view.php' );
		break;

	case 'blocked':
		if( isset( $edited_EmailBlocked ) )
		{	// Display form to create/edit an email address
			$AdminUI->disp_view( 'tools/views/_email_blocked.form.php' );
			break;
		}
		// Display a list of email logs:
		$AdminUI->disp_view( 'tools/views/_email_blocked.view.php' );
		break;

	case 'return':
		load_funcs('cron/model/_decode_returned_emails.funcs.php');
		$emret_ID = param( 'emret_ID', 'integer', 0 );
		if( $emret_ID > 0 )
		{	// Display a details of selected email
			$MailReturn = $DB->get_row( '
				SELECT *
				  FROM T_email__returns
				 WHERE emret_ID = '.$DB->quote( $emret_ID ) );
			if( $MailReturn )
			{	// The returned email exists with selected ID
				$AdminUI->disp_view( 'tools/views/_email_return_details.view.php' );
				break;
			}
		}
		// Display a list of email logs:
		$AdminUI->disp_view( 'tools/views/_email_return.view.php' );
		break;

	case 'settings':
		$AdminUI->disp_view( 'tools/views/_email_settings.form.php' );
		break;

}

// End payload block:
$AdminUI->disp_payload_end();

// Display body bottom, debug info and close </html>:
$AdminUI->disp_global_footer();

?>