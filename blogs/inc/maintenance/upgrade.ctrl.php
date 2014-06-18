<?php
/**
 * Upgrade - This is a LINEAR controller
 *
 * This file is part of b2evolution - {@link http://b2evolution.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2009-2014 by Francois PLANQUE - {@link http://fplanque.net/}
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
			autoupgrade_display_steps( 1 );

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
		elseif( $tab == 'svn' )
		{
			svnupgrade_display_steps( 1 );
		}

		// DEBUG:
		// $updates[0]['url'] = 'http://xxx/b2evolution-1.0.0.zip'; // TODO: temporary URL

		$action = 'start';

		break;

	case 'download':
	case 'force_download':
		// STEP 2: DOWNLOAD.

		if( $demo_mode )
		{
			echo('This feature is disabled on the demo server.');
			break;
		}

		$action_success = true;
		$download_success = true;

		autoupgrade_display_steps( 2 );

		$block_item_Widget = new Widget( 'block_item' );
		$block_item_Widget->title = T_('Downloading package...');
		$block_item_Widget->disp_template_replaced( 'block_start' );

		$download_url = param( 'upd_url', 'string', '', true );

		$upgrade_name = pathinfo( $download_url );
		$upgrade_name = $upgrade_name['filename'];
		$upgrade_file = $upgrade_path.$upgrade_name.'.zip';

		if( file_exists( $upgrade_file ) )
		{ // The downloading file already exists
			if( $action == 'force_download' )
			{ // Try to delete previous package if the downloading is forced
				if( ! @unlink( $upgrade_file ) )
				{
					echo '<p class="red">'.sprintf( T_('Unable to delete previous downloaded package %s before forcing the download.'), '<b>'.$upgrade_file.'</b>' ).'</p>';
					$action_success = false;
				}
			}
			else
			{
				echo '<div class="action_messages"><div class="log_error" style="text-align:center;font-weight:bold">'
					.sprintf( T_( 'The package %s is already downloaded.' ), $upgrade_name.'.zip' ).'</div></div>';
				$action_success = false;
			}
			evo_flush();
		}

		if( $action_success && ( $download_success = prepare_maintenance_dir( $upgrade_path, true ) ) )
		{
			// Set maximum execution time
			set_max_execution_time( 1800 ); // 30 minutes

			echo '<p>'.sprintf( T_( 'Downloading package to &laquo;<strong>%s</strong>&raquo;...' ), $upgrade_file );
			evo_flush();

			// Downloading
			$file_contents = fetch_remote_page( $download_url, $info, 1800 );

			if( $info['status'] != 200 || empty( $file_contents ) )
			{ // Impossible to download
				$download_success = false;
				echo '</p><p style="color:red">'.sprintf( T_( 'Unable to download package from &laquo;%s&raquo;' ), $download_url ).'</p>';
			}
			elseif( ! save_to_file( $file_contents, $upgrade_file, 'w' ) )
			{ // Impossible to save file...
				$download_success = false;
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
				echo ' OK '.bytesreadable( filesize( $upgrade_file ), false, false ).'.</p>';
			}
			evo_flush();
		}

		if( $action_success && $download_success )
		{ // Init a button to unzip
			$upgrade_buttons = array( 'unzip' => T_( 'Unzip package' ) );
		}
		elseif( $download_success )
		{ // Init the buttons to select next action
			$upgrade_buttons = array(
					'unzip'          => T_( 'Skip Download' ),
					'force_download' => T_( 'Force New Download' ),
				);
		}
		else
		{ // Init a button to back step
			$upgrade_buttons = array( 'start' => T_( 'Back to select package' ) );
		}

		// Pause a process before next step
		$AdminUI->disp_view( 'maintenance/views/_upgrade_continue.form.php' );
		unset( $block_item_Widget );
		break;

	case 'unzip':
	case 'force_unzip':
		// STEP 3: UNZIP.

		if( $demo_mode )
		{
			echo('This feature is disabled on the demo server.');
			break;
		}

		$action_success = true;
		$unzip_success = true;

		autoupgrade_display_steps( 3 );

		$block_item_Widget = new Widget( 'block_item' );
		$block_item_Widget->title = T_('Unzipping package...');
		$block_item_Widget->disp_template_replaced( 'block_start' );
		evo_flush();

		$download_url = param( 'upd_url', 'string', '', true );

		$upgrade_name = pathinfo( $download_url );
		$upgrade_name = $upgrade_name['filename'];
		$upgrade_dir = $upgrade_path.$upgrade_name;
		$upgrade_file = $upgrade_dir.'.zip';

		if( file_exists( $upgrade_dir ) )
		{ // The downloading file already exists
			if( $action == 'force_unzip' )
			{ // Try to delete previous package if the downloading is forced
				if( ! rmdir_r( $upgrade_dir ) )
				{
					echo '<p class="red">'.sprintf( T_('Unable to delete previous unzipped package %s before forcing the unzip.'), '<b>'.$upgrade_dir.'</b>' ).'</p>';
					$action_success = false;
				}
			}
			else
			{
				echo '<div class="action_messages"><div class="log_error" style="text-align:center;font-weight:bold">'
					.sprintf( T_( 'The package %s is already unzipped.' ), $upgrade_name.'.zip' ).'</div></div>';
				$action_success = false;
			}
			evo_flush();
		}

		if( $action_success )
		{
			// Set maximum execution time
			set_max_execution_time( 1800 ); // 30 minutes

			echo '<p>'.sprintf( T_( 'Unpacking package to &laquo;<strong>%s</strong>&raquo;...' ), $upgrade_dir );
			evo_flush();

			// Unpack package
			if( $unzip_success = unpack_archive( $upgrade_file, $upgrade_dir, true ) )
			{
				echo ' OK.</p>';
				evo_flush();
			}
			else
			{
				echo '</p>';
				// Additional check
				@rmdir_r( $upgrade_dir );
			}
		}

		if( $action_success && $unzip_success )
		{ // Init a button to next step
			$upgrade_buttons = array( 'ready' => T_( 'Continue' ) );
		}
		elseif( $unzip_success )
		{ // Init the buttons to select next action
			$upgrade_buttons = array( 'ready' => T_( 'Skip Unzip' ) );
			if( file_exists( $upgrade_file ) )
			{
				$upgrade_buttons['force_unzip'] = T_( 'Force New Unzip' );
			}
		}
		else
		{ // Init a button to back step
			$upgrade_buttons = array( 'download' => T_( 'Back to download package' ) );
		}

		// Pause a process before next step
		$AdminUI->disp_view( 'maintenance/views/_upgrade_continue.form.php' );
		unset( $block_item_Widget );
		break;

	case 'ready':
		// STEP 4: READY TO UPGRADE.
	case 'ready_svn':
		// SVN STEP 3: READY TO UPGRADE.

		if( $action == 'ready_svn' )
		{ // SVN upgrade
			svnupgrade_display_steps( 3 );

			$upgrade_name = param( 'upd_name', 'string', NULL, true );
		}
		else
		{ // Auto upgrade
			autoupgrade_display_steps( 4 );

			$download_url = param( 'upd_url', 'string', '', true );

			$upgrade_name = pathinfo( $download_url );
			$upgrade_name = $upgrade_name['filename'];
			$upgrade_dir = $upgrade_path.$upgrade_name;
			$upgrade_file = $upgrade_dir.'.zip';
		}

		$block_item_Widget = new Widget( 'block_item' );
		$block_item_Widget->title = T_('Ready to upgrade...');
		$block_item_Widget->disp_template_replaced( 'block_start' );
		evo_flush();

		$new_version_status = check_version( $upgrade_name );
		$action_backup_value = ( $action == 'ready_svn' ) ? 'backup_and_overwrite_svn' : 'backup_and_overwrite';
		if( empty( $new_version_status ) )
		{ // New version
			echo '<p><b>'.T_( 'The new files are ready to be installed.' ).'</b></p>';
			$upgrade_buttons = array( $action_backup_value => T_( 'Backup & Upgrade' ) );
		}
		else
		{ // Old/Same version
			echo '<div class="action_messages"><div class="log_error" style="text-align:center;font-weight:bold">'.$new_version_status.'</div></div>';
			$upgrade_buttons = array( $action_backup_value => T_( 'Force Backup & Upgrade' ) );
		}

		echo '<p>'
			.T_( 'If you continue, the following sequence will be carried out automatically (trying to minimize "maintenance time" for the site):' )
			.'<ul><li>'.T_( 'The site will switch to maintenance mode' ).'</li>'
				.'<li>'.T_( 'A backup will be performed' ).'</li>'
				.'<li>'.T_( 'The upgrade will be applied' ).'</li>'
				.'<li>'.T_( 'The install script of the new version will be called' ).'</li>'
				.'<li>'.sprintf( T_( 'The cleanup rules from %s will be applied' ), '<b>upgrade_policy.conf</b>' ).'</li>'
				.'<li>'.T_( 'The site will switch to normal mode again at the end of the install script.' ).'</li>'
			.'</ul></p>';

		// Pause a process before next step
		$AdminUI->disp_view( 'maintenance/views/_upgrade_continue.form.php' );
		unset( $block_item_Widget );
		break;

	case 'backup_and_overwrite':
		// STEP 5: BACKUP & UPGRADE.
	case 'backup_and_overwrite_svn':
		// SVN STEP 2: BACKUP AND OVERWRITE.

		if( $demo_mode )
		{
			echo('This feature is disabled on the demo server.');
			break;
		}

		if( !isset( $block_item_Widget ) )
		{
			if( $action == 'backup_and_overwrite_svn' )
			{ // SVN upgrade
				svnupgrade_display_steps( 4 );
			}
			else
			{ // Auto upgrade
				autoupgrade_display_steps( 5 );
			}

			$block_item_Widget = new Widget( 'block_item' );
			$block_item_Widget->title = $action == 'backup_and_overwrite_svn'
				? T_('Installing package from SVN...')
				: T_('Installing package...');
			$block_item_Widget->disp_template_replaced( 'block_start' );

			$upgrade_name = param( 'upd_name', 'string', NULL, true );
			if( $upgrade_name === NULL )
			{ // Get an upgrade name from url (Used for auto-upgrade, not svn)
				$download_url = param( 'upd_url', 'string', '', true );
				$upgrade_name = pathinfo( $download_url );
				$upgrade_name = $upgrade_name['filename'];
			}

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
				$Backup->load_from_Request();

				if( ! function_exists( 'gzopen' ) )
				{
					$Backup->pack_backup_files = false;
				}

				// Start backup
				if( $success = $Backup->start_backup() )
				{ // We can upgrade files and database

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
			$block_item_Widget->disp_template_replaced( 'block_end' );
			$Form = new Form();
			$Form->begin_form( 'fform' );
			$Form->begin_fieldset( T_( 'Actions' ) );
			echo '<p><b>'.T_('All new b2evolution files are in place. You will now be redirected to the installer to perform a DB upgrade.').'</b> '.T_('Note: the User Interface will look different.').'</p>';
			$continue_onclick = 'location.href=\''.$baseurl.'install/index.php?action='.( ( $action == 'backup_and_overwrite_svn' ) ? 'svn_upgrade' : 'auto_upgrade' ).'&locale='.$current_locale.'\'';
			$Form->end_form( array( array( 'button', 'continue', T_('Continue to installer'), '', $continue_onclick ) ) );
			unset( $block_item_Widget );
		}
		else
		{ // Disable maintenance mode
			switch_maintenance_mode( false, 'upgrade' );
			echo '<h4 style="color:red">'.T_( 'Upgrade failed!' ).'</h4>';
		}
		break;

	/****** UPGRADE FROM SVN *****/
	case 'export_svn':
	case 'force_export_svn':
		// SVN STEP 2: EXPORT.

		if( $demo_mode )
		{
			echo('This feature is disabled on the demo server.');
			break;
		}

		svnupgrade_display_steps( 2 );

		$block_item_Widget = new Widget( 'block_item' );
		$block_item_Widget->title = T_('Exporting package from SVN...');
		$block_item_Widget->disp_template_replaced( 'block_start' );

		$svn_url = param( 'svn_url', 'string', '', true );
		$svn_folder = param( 'svn_folder', 'string', '/', true );
		$svn_user = param( 'svn_user', 'string', false, true );
		$svn_password = param( 'svn_password', 'string', false, true );
		$svn_revision = param( 'svn_revision', 'integer', 0, true );

		$UserSettings->set( 'svn_upgrade_url', $svn_url );
		$UserSettings->set( 'svn_upgrade_folder', $svn_folder );
		$UserSettings->set( 'svn_upgrade_user', $svn_user );
		$UserSettings->set( 'svn_upgrade_revision', $svn_revision );
		$UserSettings->dbupdate();

		$success = param_check_not_empty( 'svn_url', T_('Please enter the URL of repository') );
		//$success = $success && param_check_regexp( 'svn_folder', '#/blogs/$#', T_('A correct SVN folder path must ends with "/blogs/"') );

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

			if( $action == 'force_export_svn' && file_exists( $upgrade_folder ) )
			{ // The exported folder already exists
				// Try to delete previous package
				if( ! rmdir_r( $upgrade_folder ) )
				{
					echo '<p class="red">'.sprintf( T_('Unable to delete previous exported package %s before forcing the export.'), '<b>'.$upgrade_folder.'</b>' ).'</p>';
				}
				evo_flush();
			}

			if( file_exists( $upgrade_folder ) )
			{ // Current version already is downloaded
				echo '<p class="green">'.sprintf( T_('Revision %s has already been downloaded. Using: %s'), $repository_version, $upgrade_folder );
				$revision_is_exported = true;
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
			if( empty( $revision_is_exported ) )
			{ // Init the buttons to continue
				$upgrade_buttons = array( 'ready_svn' => T_( 'Continue' ) );
			}
			else
			{ // Init the buttons to select next action
				$upgrade_buttons = array(
						'ready_svn'        => T_( 'Skip Export' ),
						'force_export_svn' => T_( 'Force New Export' ),
					);
			}
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