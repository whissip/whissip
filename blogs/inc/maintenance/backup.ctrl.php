<?php
/**
 * Backup - This is a LINEAR controller
 *
 * This file is part of b2evolution - {@link http://b2evolution.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2009 by Francois PLANQUE - {@link http://fplanque.net/}
 * Parts of this file are copyright (c)2009 by The Evo Factory - {@link http://www.evofactory.com/}.
 *
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 *
 * {@internal Open Source relicensing agreement:
 * The Evo Factory grants Francois PLANQUE the right to license
 * The Evo Factory's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package maintenance
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author efy-maxim: Evo Factory / Maxim.
 * @author fplanque: Francois Planque.
 *
 * @version $Id$
 */

if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * @var instance of User class
 */
global $current_User;

// Check minimum permission:
$current_User->check_perm( 'perm_maintenance', 'backup', true );

// Load Backup class (PHP4):
load_class( 'maintenance/model/_backup.class.php', 'Backup' );

// Set options path:
$AdminUI->set_path( 'tools', 'backup' );

// Get action parameter from request:
param_action( 'start' );

// Create instance of Backup class
$current_Backup = new Backup();

// Load backup settings from request
if( $action == 'backup' && !$current_Backup->load_from_Request() )
{
	$action = 'new';
}


$AdminUI->breadcrumbpath_init( false );  // fp> I'm playing with the idea of keeping the current blog in the path here...
$AdminUI->breadcrumbpath_add( T_('Tools'), '?ctrl=crontab' );
$AdminUI->breadcrumbpath_add( T_('Backup'), '?ctrl=backup' );


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
	case 'start':
		// Display backup settings form
		$AdminUI->disp_view( 'maintenance/views/_backup.form.php' );
		break;

	case 'backup':
		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'backup' );
		
		$Form = new Form( NULL, 'backup_progress', 'post' );

		// Interactive / flush() backup should start here
		$Form->begin_form( 'fform', T_('System backup is in progress...') );

		flush();

		$success = true;
		if( $maintenance_mode = param( 'bk_maintenance_mode', 'boolean' ) )
		{	// Enable maintenance mode
			$success = switch_maintenance_mode( true, T_( 'System backup is in progress. Please reload this page in a few minutes.' ) );
			
			// Make sure we exit the maintenance mode if PHP dies
			register_shutdown_function( 'switch_maintenance_mode', false );
		}

		if( $success )
		{	// We can start backup
			set_max_execution_time( 1800 ); // 30 minutes
			$current_Backup->start_backup();
		}

		if( $maintenance_mode )
		{	// Disable maintenance mode
			switch_maintenance_mode( false );
		}

		$Form->end_form();
		break;
}

$AdminUI->disp_payload_end();

// Display body bottom, debug info and close </html>:
$AdminUI->disp_global_footer();


/*
 * $Log$
 * Revision 1.8  2010/10/27 23:55:40  sam2kb
 * Register shutdown function to exit the maintenance mode if PHP dies
 *
 * Revision 1.7  2010/01/30 18:55:32  blueyed
 * Fix "Assigning the return value of new by reference is deprecated" (PHP 5.3)
 *
 * Revision 1.6  2010/01/16 14:27:04  efy-yury
 * crumbs, fadeouts, redirect, action_icon
 *
 * Revision 1.5  2009/12/06 22:55:18  fplanque
 * Started breadcrumbs feature in admin.
 * Work in progress. Help welcome ;)
 * Also move file settings to Files tab and made FM always enabled
 *
 * Revision 1.4  2009/11/18 21:54:25  efy-maxim
 * compatibility fix for PHP4
 *
 * Revision 1.3  2009/10/21 14:27:38  efy-maxim
 * upgrade
 *
 * Revision 1.2  2009/10/20 14:38:54  efy-maxim
 * maintenance modulde: downloading - unpacking - verifying destination files - backing up - copying new files - upgrade database using regular script (Warning: it is very unstable version! Please, don't use maintenance modulde, because it can affect your data )
 *
 * Revision 1.1  2009/10/18 20:15:51  efy-maxim
 * 1. backup, upgrade have been moved to maintenance module
 * 2. maintenance module permissions
 *
 */
?>