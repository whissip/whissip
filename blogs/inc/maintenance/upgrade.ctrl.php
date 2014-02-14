<?php
/**
 * Upgrade - This is a LINEAR controller
 *
 * This file is part of b2evolution - {@link http://b2evolution.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2009-2013 by Francois PLANQUE - {@link http://fplanque.net/}
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

/**
 * @vars string paths
 */
global $basepath, $upgrade_path, $install_path;

// Check minimum permission:
$current_User->check_perm( 'perm_maintenance', 'upgrade', true );

// Used in the upgrade process
$script_start_time = $servertimenow;

$tab = param( 'tab', 'string', '', true );

// Set options path:
$AdminUI->set_path( 'options', 'misc', 'upgrade'.$tab );

// Get action parameter from request:
param_action();

// Display message if the upgrade config file doesn't exist
check_upgrade_config( true );

$AdminUI->breadcrumbpath_init( false );  // fp> I'm playing with the idea of keeping the current blog in the path here...
$AdminUI->breadcrumbpath_add( T_('System'), '?ctrl=system' );
$AdminUI->breadcrumbpath_add( T_('Maintenance'), '?ctrl=tools' );
if( $tab == 'svn' )
{
	$AdminUI->breadcrumbpath_add( T_('Upgrade from SVN'), '?ctrl=upgrade&amp;tab='.$tab );
}
else
{
	$AdminUI->breadcrumbpath_add( T_('Auto Upgrade'), '?ctrl=upgrade' );
}


// Display <html><head>...</head> section! (Note: should be done early if actions do not redirect)
$AdminUI->disp_html_head();

// Display title, menu, messages, etc. (Note: messages MUST be displayed AFTER the actions)
$AdminUI->disp_body_top();

$AdminUI->disp_payload_begin();

echo '<h2 class="red">WARNING: EXPERIMENTAL FEATURE!</h2>';

echo '<h3>Use for testing only at this point!</h3>';
evo_flush();

/**
 * Display payload:
 */
switch( $action )
{
	case 'start':
	default:
		// STEP 1: Check for updates.
		if( $tab == '' )
		{
			$block_item_Widget = new Widget( 'block_item' );
			$block_item_Widget->title = T_('Updates from b2evolution.net').get_manual_link( 'auto-upgrade' );
			$block_item_Widget->disp_template_replaced( 'block_start' );

			// Note: hopefully, the update will have been downloaded in the shutdown function of a previous page (including the login screen)
			// However if we have outdated info, we will load updates here.
			load_funcs( 'dashboard/model/_dashboard.funcs.php' );
			// Let's clear any remaining messages that should already have been displayed before...
			$Messages->clear();
			b2evonet_get_updates( true );

			// Display info & error messages
			echo $Messages->display( NULL, NULL, false, 'action_messages' );


			/**
			 * @var AbstractSettings
			 */
			global $global_Cache;

			// Display the current version info for now. We may remove this in the future.
			$version_status_msg = $global_Cache->get( 'version_status_msg' );
			if( !empty($version_status_msg) )
			{	// We have managed to get updates (right now or in the past):
				echo '<p>'.$version_status_msg.'</p>';
				$extra_msg = $global_Cache->get( 'extra_msg' );
				if( !empty($extra_msg) )
				{
					echo '<p>'.$extra_msg.'</p>';
				}
			}

			// Extract available updates:
			$updates = $global_Cache->get( 'updates' );
		}

		// DEBUG:
		// $updates[0]['url'] = 'http://xxx/b2evolution-1.0.0.zip'; // TODO: temporary URL

		$action = 'start';

		break;

	case 'download':
		// STEP 2: DOWNLOAD.

		if( $demo_mode )
		{
			echo('This feature is disabled on the demo server.');
			break;
		}

		$block_item_Widget = new Widget( 'block_item' );
		$block_item_Widget->title = T_('Downloading, unzipping & installing package...');
		$block_item_Widget->disp_template_replaced( 'block_start' );

		$download_url = param( 'upd_url', 'string' );

		$upgrade_name = param( 'upd_name', 'string', '', true );
		$upgrade_file = $upgrade_path.$upgrade_name.'.zip';

		if( $success = prepare_maintenance_dir( $upgrade_path, true ) )
		{
			// Set maximum execution time
			set_max_execution_time( 1800 ); // 30 minutes

			echo '<p>'.sprintf( T_( 'Downloading package to &laquo;<strong>%s</strong>&raquo;...' ), $upgrade_file );
			evo_flush();

			// Downloading
			$file_contents = fetch_remote_page( $download_url, $info, 1800 );

			if( empty($file_contents) )
			{
				$success = false;
				echo '</p><p style="color:red">'.sprintf( T_( 'Unable to download package from &laquo;%s&raquo;' ), $download_url ).'</p>';
			}
			elseif( ! save_to_file( $file_contents, $upgrade_file, 'w' ) )
			{	// Impossible to save file...
				$success = false;
				echo '</p><p style="color:red">'.sprintf( T_( 'Unable to create file: &laquo;%s&raquo;' ), $upgrade_file ).'</p>';

				if( file_exists( $upgrade_file ) )
				{ // Remove file from disk
					if( ! @unlink( $upgrade_file ) )
					{
						echo '<p style="color:red">'.sprintf( T_( 'Unable to remove file: &laquo;%s&raquo;' ), $upgrade_file ).'</p>';
					}
				}
			}
			else
			{ // The package is downloaded successfully
				echo ' OK.</p>';
			}
			evo_flush();
		}

	case 'unzip':
		// STEP 3: UNZIP.

		if( $demo_mode )
		{
			echo('This feature is disabled on the demo server.');
			break;
		}

		if( !isset( $block_item_Widget ) )
		{
			$block_item_Widget = new Widget( 'block_item' );
			$block_item_Widget->title = T_('Unzipping & installing package...');
			$block_item_Widget->disp_template_replaced( 'block_start' );

			$upgrade_name = param( 'upd_name', 'string', '', true );
			$upgrade_file = $upgrade_path.$upgrade_name.'.zip';

			$success = true;
		}

		if( $success )
		{
			// Set maximum execution time
			set_max_execution_time( 1800 ); // 30 minutes

			echo '<p>'.sprintf( T_( 'Unpacking package to &laquo;<strong>%s</strong>&raquo;...' ), $upgrade_path.$upgrade_name );
			evo_flush();

			// Unpack package
			if( $success = unpack_archive( $upgrade_file, $upgrade_path.$upgrade_name, true ) )
			{
				global $debug;

				echo ' OK.</p>';
				evo_flush();

				$new_version_status = check_version( $upgrade_name );
				if( $debug == 0 && !empty( $new_version_status ) )
				{
					echo '<h4 style="color:red">'.$new_version_status.'</h4>';
					evo_flush();
					break;
				}
			}
			else
			{
				echo '</p>';
				// Additional check
				@rmdir_r( $upgrade_path.$upgrade_name );
			}
		}

		if( $success )
		{ // Pause a process before upgrading
			$action = 'backup_and_overwrite';
			$AdminUI->disp_view( 'maintenance/views/_upgrade_continue.form.php' );
			unset( $block_item_Widget );
		}
		break;

	case 'backup_and_overwrite':
		// STEP 4: BACKUP AND OVERWRITE.
	case 'backup_and_overwrite_svn':
		// SVN STEP 2: BACKUP AND OVERWRITE.

		if( $demo_mode )
		{
			echo('This feature is disabled on the demo server.');
			break;
		}

		if( !isset( $block_item_Widget ) )
		{
			$block_item_Widget = new Widget( 'block_item' );
			$block_item_Widget->title = $action == 'backup_and_overwrite_svn'
				? T_('Installing package from SVN...')
				: T_('Installing package...');
			$block_item_Widget->disp_template_replaced( 'block_start' );

			$upgrade_name = param( 'upd_name', 'string', '', true );

			$success = true;
		}

		// Enable maintenance mode
		$success = ( $success && switch_maintenance_mode( true, 'upgrade', T_( 'System upgrade is in progress. Please reload this page in a few minutes.' ) ) );

		if( $success )
		{
			// Set maximum execution time
			set_max_execution_time( 1800 ); // 30 minutes

			// Verify that all destination files can be overwritten
			echo '<h4>'.T_( 'Verifying that all destination files can be overwritten...' ).'</h4>';
			evo_flush();

			$read_only_list = array();

			// Get a folder path where we should get the files
			$upgrade_folder_path = get_upgrade_folder_path( $upgrade_name );

			$success = verify_overwrite( $upgrade_folder_path, no_trailing_slash( $basepath ), 'Verifying', false, $read_only_list );

			if( $success && empty( $read_only_list ) )
			{ // We can backup files and database

				// Load Backup class (PHP4) and backup all of the folders and files
				load_class( 'maintenance/model/_backup.class.php', 'Backup' );
				$Backup = new Backup();
				$Backup->include_all();

				if( !function_exists('gzopen') )
				{
					$Backup->pack_backup_files = false;
				}

				// Start backup
				if( $success = $Backup->start_backup() )
				{	// We can upgrade files and database

					// Copying new folders and files
					echo '<h4>'.T_( 'Copying new folders and files...' ).'</h4>';
					evo_flush();

					$success = verify_overwrite( $upgrade_folder_path, no_trailing_slash( $basepath ), 'Copying', true, $read_only_list );
					if( ( ! $success ) || ( ! empty( $read_only_list ) ) )
					{ // In case if something was changed before the previous verify_overwrite check
						echo '<p style="color:red"><strong>'.T_( 'The files and database backup was created successfully but all folders and files could not be overwritten' );
						if( empty( $read_only_list ) )
						{ // There was some error in the verify_overwrite() function, but the corresponding error message was already displayed.
							echo '.</strong></p>';
						}
						else
						{ // Some file/folder could not be overwritten, display it
							echo ':</strong></p>';
							foreach( $read_only_list as $read_only_file )
							{
								echo $read_only_file.'<br/>';
							}
						}
						echo '<p style="color:red"><strong>'.sprintf( T_('Please restore the backup files from the &laquo;%s&raquo; package. The database was not changed.'), $backup_path ).'</strong></p>';
						evo_flush();
					}
				}
			}
			else
			{
				echo '<p style="color:red">'.T_( '<strong>The following folders and files can\'t be overwritten:</strong>' ).'</p>';
				evo_flush();
				foreach( $read_only_list as $read_only_file )
				{
					echo $read_only_file.'<br/>';
				}
				$success = false;
			}
		}

		if( $success )
		{ // Pause a process before upgrading, and display a link to the normal upgrade action
			$upgrade_url = 'href="'.$baseurl.'install/index.php?action='.(( $action == 'backup_and_overwrite_svn' ) ? 'svn_upgrade' : 'auto_upgrade' ).'&locale='.$current_locale.'"';
			echo '<p><b>'.T_('All new b2evolution files are in place. You will now be redirected to the installer to perform a DB upgrade.').'</b> '.T_('Note: the User Interface will look different.').'</p>';
			echo '<p><b><span style="font-size:110%">'.sprintf( T_('<a %s>Click here to continue &raquo;</a>'), $upgrade_url ).'</span></b></p>';
			unset( $block_item_Widget );
		}
		else
		{ // Disable maintenance mode
			switch_maintenance_mode( false, 'upgrade' );
			echo '<h4 style="color:red">'.T_( 'Upgrade failed!' ).'</h4>';
		}
		break;

	/****** UPGRADE FROM SVN *****/
	case 'upgrade_svn':
		// SVN STEP 1: EXPORT.

		if( $demo_mode )
		{
			echo('This feature is disabled on the demo server.');
			break;
		}

		$block_item_Widget = new Widget( 'block_item' );
		$block_item_Widget->title = T_('Exporting package from SVN...');
		$block_item_Widget->disp_template_replaced( 'block_start' );

		$svn_url = param( 'svn_url', 'string', '' );
		$svn_folder = param( 'svn_folder', 'string', '/' );
		$svn_user = param( 'svn_user', 'string', false );
		$svn_password = param( 'svn_password', 'string', false );
		$svn_revision = param( 'svn_revision', 'integer' );

		$UserSettings->set( 'svn_upgrade_url', $svn_url );
		$UserSettings->set( 'svn_upgrade_folder', $svn_folder );
		$UserSettings->set( 'svn_upgrade_user', $svn_user );
		$UserSettings->set( 'svn_upgrade_revision', $svn_revision );
		$UserSettings->dbupdate();

		$success = param_check_not_empty( 'svn_url', T_('Please enter the URL of repository') );
		$success = $success && param_check_regexp( 'svn_folder', '#/blogs/$#', T_('A correct SVN folder path must ends with "/blogs/"') );

		if( ! $success )
		{
			$action = 'start';
			break;
		}

		$success = prepare_maintenance_dir( $upgrade_path, true );

		if( $success )
		{
			// Set maximum execution time
			set_max_execution_time( 2400 ); // 60 minutes

			load_class('_ext/phpsvnclient/phpsvnclient.php', 'phpsvnclient' );

			$phpsvnclient = new phpsvnclient( $svn_url, $svn_user, $svn_password );

			// Get an error if it was during connecting to svn server
			$svn_error = $phpsvnclient->getError();

			if( ! empty( $svn_error ) || $phpsvnclient->getVersion() < 1 )
			{ // Some errors or Incorrect version
				echo '<p class="red">'.T_( 'Unable to get a repository version, probably URL of repository is incorrect.' ).'</p>';
				evo_flush();
				$action = 'start';
				break; // Stop an upgrade from SVN
			}

			if( $svn_revision > 0 )
			{ // Set revision from request
				if( $phpsvnclient->getVersion() < $svn_revision )
				{ // Incorrect revision number
					echo '<p class="red">'.sprintf( T_( 'Please select a correct revision number. The latest revision is %s.' ), $phpsvnclient->getVersion() ).'</p>';
					evo_flush();
					$action = 'start';
					break; // Stop an upgrade from SVN
				}
				else
				{ // Use only correct revision
					$phpsvnclient->setVersion( $svn_revision );
				}
			}

			$repository_version = $phpsvnclient->getVersion();

			$upgrade_name = 'export_svn_'.$repository_version;
			memorize_param( 'upd_name', 'string', '', $upgrade_name );
			$upgrade_folder = $upgrade_path.$upgrade_name;

			if( file_exists( $upgrade_path.$upgrade_name ) )
			{ // Current version already is downloaded
				echo '<p class="green">'.sprintf( T_('Revision %s has already been downloaded. Using: %s'), $repository_version, $upgrade_path.$upgrade_name );
			}
			else
			{ // Download files
				echo '<p>'.sprintf( T_( 'Downloading package to &laquo;<strong>%s</strong>&raquo;...' ), $upgrade_folder );
				evo_flush();

				// Export all files in temp folder for following coping
				$svn_result = $phpsvnclient->checkOut( $svn_folder, $upgrade_folder, false, true );

				echo '</p>';

				if( $svn_result === false )
				{ // Checkout is failed
					echo '<p style="color:red">'.sprintf( T_( 'Unable to download package from &laquo;%s&raquo;' ), $svn_url ).'</p>';
					evo_flush();
					$action = 'start';
					break;
				}
			}
		}

		if( $success )
		{ // Pause a process before upgrading
			$action = 'backup_and_overwrite_svn';
			$AdminUI->disp_view( 'maintenance/views/_upgrade_continue.form.php' );
			unset( $block_item_Widget );
		}
		break;
}

if( isset( $block_item_Widget ) )
{
	$block_item_Widget->disp_template_replaced( 'block_end' );
}

switch( $tab )
{
	case 'svn':
		$AdminUI->disp_view( 'maintenance/views/_upgrade_svn.form.php' );
		break;

	default:
		$AdminUI->disp_view( 'maintenance/views/_upgrade.form.php' );
		break;
}

$AdminUI->disp_payload_end();

// Display body bottom, debug info and close </html>:
$AdminUI->disp_global_footer();

?>