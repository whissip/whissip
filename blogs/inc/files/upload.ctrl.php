<?php
/**
 * This file implements the UI controller for file upload.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2009 by Francois PLANQUE - {@link http://fplanque.net/}
 * (dh please re-add)
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
 * (dh please re-add)
 * }}
 *
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE.
 * (dh please re-add)
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * Filelist
 * fp>> TODO: When the user is viewing details for a file he should (by default) not be presented with the filelist in addition to the file properties
 * In cases like that, we should try to avoid instanciating a Filelist.
 */
load_class('files/model/_filelist.class.php');

/**
 * @var User
 */
global $current_User;

global $dispatcher;

global $blog;

// Check global access permissions:
if( ! $Settings->get( 'fm_enabled' ) )
{
	bad_request_die( 'The filemanager is disabled.' );
}

// Check permission:
$current_User->check_perm( 'files', 'add', true, $blog ? $blog : NULL );


$AdminUI->set_path( 'files', 'upload' );

// Params that may need to be passed through:
param( 'fm_mode', 'string', NULL, true );
param( 'item_ID', 'integer', NULL, true );
param( 'user_ID', 'integer', NULL, true );
param( 'iframe_name', 'string', '', true );

$action = param_action();

// Standard vs Advanced mode
param( 'uploadwithproperties', 'integer', NULL, false );
if( !is_null($uploadwithproperties) )
{
	$UserSettings->set( 'fm_uploadwithproperties', $uploadwithproperties );
	$UserSettings->dbupdate();
}
else
{
	$uploadwithproperties = $UserSettings->get( 'fm_uploadwithproperties' );
}

// INIT params:
if( param( 'root_and_path', 'string', '', false ) /* not memorized (default) */ && strpos( $root_and_path, '::' ) )
{ // root and path together: decode and override (used by "radio-click-dirtree")
	list( $root, $path ) = explode( '::', $root_and_path, 2 );
	// Memorize new root:
	memorize_param( 'root', 'string', NULL );
	memorize_param( 'path', 'string', NULL );
}
else
{
	param( 'root', 'string', NULL, true ); // the root directory from the dropdown box (user_X or blog_X; X is ID - 'user' for current user (default))
	param( 'path', 'string', '', true );  // the path relative to the root dir
	if( param( 'new_root', 'string', '' )
		&& $new_root != $root )
	{ // We have changed root in the select list
		$root = $new_root;
		$path = '';
	}
}

// Get root:
$ads_list_path = false; // false by default, gets set if we have a valid root
/**
 * @var FileRoot
 */
$fm_FileRoot = NULL;

$FileRootCache = & get_Cache( 'FileRootCache' );

$available_Roots = $FileRootCache->get_available_FileRoots();

if( ! empty($root) )
{ // We have requested a root folder by string:
	$fm_FileRoot = & $FileRootCache->get_by_ID($root, true);

	if( ! $fm_FileRoot || ! isset( $available_Roots[$fm_FileRoot->ID] ) )
	{ // Root not found or not in list of available ones
		$Messages->add( T_('You don\'t have access to the requested root directory.'), 'error' );
		$fm_FileRoot = false;
	}
}

if( ! $fm_FileRoot )
{ // No root requested (or the requested is invalid), get the first one available:
	if( $available_Roots
	    && ( $tmp_keys = array_keys( $available_Roots ) )
	    && $first_Root = & $available_Roots[ $tmp_keys[0] ] )
	{ // get the first one
		$fm_FileRoot = & $first_Root;
	}
	else
	{
		$Messages->add( T_('You don\'t have access to any root directory.'), 'error' );
	}
}

if( $fm_FileRoot )
{ // We have access to a file root:
	if( empty($fm_FileRoot->ads_path) )
	{	// Not sure it's possible to get this far, but just in case...
		$Messages->add( sprintf( T_('The root directory &laquo;%s&raquo; does not exist.'), $fm_FileRoot->ads_path ), 'error' );
	}
	else
	{ // Root exists
		// Let's get into requested list dir...
		$non_canonical_list_path = $fm_FileRoot->ads_path.$path;

		// Dereference any /../ just to make sure, and CHECK if directory exists:
		$ads_list_path = get_canonical_path( $non_canonical_list_path );

		if( !is_dir( $ads_list_path ) )
		{ // This should never happen, but just in case the diretory does not exist:
			$Messages->add( sprintf( T_('The directory &laquo;%s&raquo; does not exist.'), $path ), 'error' );
			$path = '';		// fp> added
			$ads_list_path = NULL;
		}
		elseif( ! preg_match( '#^'.preg_quote($fm_FileRoot->ads_path, '#').'#', $ads_list_path ) )
		{ // cwd is OUTSIDE OF root!
			$Messages->add( T_( 'You are not allowed to go outside your root directory!' ), 'error' );
			$path = '';		// fp> added
			$ads_list_path = $fm_FileRoot->ads_path;
		}
		elseif( $ads_list_path != $non_canonical_list_path )
		{	// We have reduced the absolute path, we should also reduce the relative $path (used in urls params)
			$path = get_canonical_path( $path );
		}
	}
}


// If there were errors, display them and exit (especially in case there's no valid FileRoot ($fm_FileRoot)):
// TODO: dh> this prevents users from uploading if _any_ blog media directory is not writable.
//           See http://forums.b2evolution.net/viewtopic.php?p=49001#49001
if( $Messages->count('error') )
{
	// Display <html><head>...</head> section! (Note: should be done early if actions do not redirect)
	$AdminUI->disp_html_head();

	// Display title, menu, messages, etc. (Note: messages MUST be displayed AFTER the actions)
	$AdminUI->disp_body_top();
	$AdminUI->disp_payload_begin();
	$AdminUI->disp_payload_end();

	$AdminUI->disp_global_footer();
	exit(0);
}


$Debuglog->add( 'FM root: '.var_export( $fm_FileRoot, true ), 'files' );
$Debuglog->add( 'FM _ads_list_path: '.var_export( $ads_list_path, true ), 'files' );


if( empty($ads_list_path) )
{ // We have no Root / list path, there was an error. Unset any action.
	$action = '';
}




// Check permissions:
// Tblue> Note: Perm 'files' (level 'add') gets checked above with $assert = true.
if( ! $Settings->get('upload_enabled') )
{ // Upload is globally disabled
	$Messages->add( T_('Upload is disabled.'), 'error' );
}


// If there were errors, display them and exit (especially in case there's no valid FileRoot ($fm_FileRoot)):
if( $Messages->count('error') )
{
	$AdminUI->disp_html_head();
	// Display title, menu, messages, etc. (Note: messages MUST be displayed AFTER the actions)
	$AdminUI->disp_body_top();
	$AdminUI->disp_global_footer();
	exit(0);
}


// Quick mode means "just upload and leave mode when successful"
param( 'upload_quickmode', 'integer', 0 );

/**
 * Remember failed files (and the error messages)
 * @var array
 */
$failedFiles = array();

// Process uploaded files:
if( isset($_FILES) && count( $_FILES ) )
{ // Some files have been uploaded:
	param( 'uploadfile_title', 'array', array() );
	param( 'uploadfile_alt', 'array', array() );
	param( 'uploadfile_desc', 'array', array() );
	param( 'uploadfile_name', 'array', array() );

	foreach( $_FILES['uploadfile']['name'] as $lKey => $lName )
	{
		if( empty( $lName ) )
		{ // No file name
			if( $upload_quickmode
				 || !empty( $uploadfile_title[$lKey] )
				 || !empty( $uploadfile_alt[$lKey] )
				 || !empty( $uploadfile_desc[$lKey] )
				 || !empty( $uploadfile_name[$lKey] ) )
			{ // User specified params but NO file!!!
				// Remember the file as failed when additional info provided.
				$failedFiles[$lKey] = T_( 'Please select a local file to upload.' );
			}
			// Abort upload for this file:
			continue;
		}

		if( $Settings->get( 'upload_maxkb' )
				&& $_FILES['uploadfile']['size'][$lKey] > $Settings->get( 'upload_maxkb' )*1024 )
		{ // bigger than defined by blog
			$failedFiles[$lKey] = sprintf(
					T_('The file is too large: %s but the maximum allowed is %s.'),
					bytesreadable( $_FILES['uploadfile']['size'][$lKey] ),
					bytesreadable($Settings->get( 'upload_maxkb' )*1024) );
			// Abort upload for this file:
			continue;
		}

		if( $_FILES['uploadfile']['error'][$lKey] )
		{ // PHP has detected an error!:
			switch( $_FILES['uploadfile']['error'][$lKey] )
			{
				case UPLOAD_ERR_FORM_SIZE:
					// The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the html form.

					// This can easily be changed, so we do not use it.. file size gets checked for real just above.
					break;

				case UPLOAD_ERR_INI_SIZE: // bigger than allowed in php.ini
					$failedFiles[$lKey] = T_('The file exceeds the upload_max_filesize directive in php.ini.');
					// Abort upload for this file:
					continue;

				case UPLOAD_ERR_PARTIAL:
					$failedFiles[$lKey] = T_('The file was only partially uploaded.');
					// Abort upload for this file:
					continue;

				case UPLOAD_ERR_NO_FILE:
					// Is probably the same as empty($lName) before.
					$failedFiles[$lKey] = T_('No file was uploaded.');
					// Abort upload for this file:
					continue;

				case 6: // numerical value of UPLOAD_ERR_NO_TMP_DIR
				# (min_php: 4.3.10, 5.0.3) case UPLOAD_ERR_NO_TMP_DIR:
					// Missing a temporary folder.
					$failedFiles[$lKey] = T_('Missing a temporary folder (upload_tmp_dir in php.ini).');
					// Abort upload for this file:
					continue;

				default:
					$failedFiles[$lKey] = T_('Unknown error.').' #'.$_FILES['uploadfile']['error'][$lKey];
					// Abort upload for this file:
					continue;
			}
		}

		if( !is_uploaded_file( $_FILES['uploadfile']['tmp_name'][$lKey] ) )
		{ // Ensure that a malicious user hasn't tried to trick the script into working on files upon which it should not be working.
			$failedFiles[$lKey] = T_('The file does not seem to be a valid upload! It may exceed the upload_max_filesize directive in php.ini.');
			// Abort upload for this file:
			continue;
		}

		// Use new name on server if specified:
		$newName = !empty( $uploadfile_name[ $lKey ] ) ? $uploadfile_name[ $lKey ] : $lName;

		if( $error_filename = validate_filename( $newName ) )
		{ // Not a file name or not an allowed extension
			$failedFiles[$lKey] = $error_filename;
			// Abort upload for this file:
			continue;
		}

		// Get File object for requested target location:
		$FileCache = & get_Cache( 'FileCache' );
		$newFile = & $FileCache->get_by_root_and_path( $fm_FileRoot->type, $fm_FileRoot->in_type_ID, trailing_slash($path).$newName, true );

		if( $newFile->exists() )
		{ // The file already exists in the target location!
			// TODO: Rename/Overwriting (save as filename_<numeric_extension> and provide interface to confirm, rename or overwrite)
			$failedFiles[$lKey] = sprintf( T_('The file &laquo;%s&raquo; already exists.'), $newFile->dget('name') );
			// Abort upload for this file:
			continue;
		}

		// Attempt to move the uploaded file to the requested target location:
		if( ! @move_uploaded_file( $_FILES['uploadfile']['tmp_name'][$lKey], $newFile->get_full_path() ) )
		{
			$failedFiles[$lKey] = T_('An unknown error occurred when moving the uploaded file on the server.');
			// Abort upload for this file:
			continue;
		}

		// change to default chmod settings
		if( $newFile->chmod( NULL ) === false )
		{ // add a note, this is no error!
			$Messages->add( sprintf( T_('Could not change permissions of &laquo;%s&raquo; to default chmod setting.'), $newFile->dget('name') ), 'note' );
		}

		// Refreshes file properties (type, size, perms...)
		$newFile->load_properties();

		// Store extra info about the file into File Object:
		if( isset( $uploadfile_title[$lKey] ) )
		{ // If a title text has been passed... (does not happen in quick upload mode)
			$newFile->set( 'title', trim( strip_tags($uploadfile_title[$lKey])) );
		}
		if( isset( $uploadfile_alt[$lKey] ) )
		{ // If an alt text has been passed... (does not happen in quick upload mode)
			$newFile->set( 'alt', trim( strip_tags($uploadfile_alt[$lKey])) );
		}
		if( isset( $uploadfile_desc[$lKey] ) )
		{ // If a desc text has been passed... (does not happen in quick upload mode)
			$newFile->set( 'desc', trim( strip_tags($uploadfile_desc[$lKey])) );
		}

		$success_msg = sprintf( T_('The file &laquo;%s&raquo; has been successfully uploaded.'), $newFile->dget('name') );
		if( $mode == 'upload' )
		{
			// TODO: Add plugin hook to allow generating JS insert code(s)
			$img_tag = format_to_output( $newFile->get_tag(), 'formvalue' );
			$success_msg .=
				'<ul>'
					.'<li>'.T_("Here's the code to display it:").' <input type="text" value="'.$img_tag.'" size="60" /></li>'
					.'<li><a href="#" onclick="if( window.focus && window.opener ){ window.opener.focus(); textarea_wrap_selection( window.opener.document.getElementById(\'itemform_post_content\'), \''.format_to_output( $newFile->get_tag(), 'formvalue' ).'\', \'\', 1, window.opener.document ); } return false;">'.T_('Add the code to your post !').'</a></li>'
				.'</ul>';
		}

		$Messages->add( $success_msg, 'success' );

		// Store File object into DB:
		$newFile->dbsave();

	}

	if( $upload_quickmode && !empty($failedFiles) )
	{	// Transmit file error to next page!
		$Messages->add( $failedFiles[0], 'error' );
		unset($failedFiles);
	}
	if( empty($failedFiles) )
	{ // quick mode or no failed files, Go back to Browsing
		// header_redirect( $dispatcher.'?ctrl=files&root='.$fm_FileRoot->ID.'&path='.rawurlencode($path) );
		header_redirect( regenerate_url( 'ctrl', 'ctrl=files', '', '&' ) );
	}
}


// Update sub-menu:
// Tblue> Note: Perm 'files' (level 'add') gets checked above with $assert = true.
$AdminUI->add_menu_entries(
	'files',
	array(
		'browse' => array(
			'text' => T_('Browse'),
			'href' => regenerate_url( 'ctrl', 'ctrl=files' ) ),
		'upload' => array(
			'text' => T_('Upload'),
			'href' => regenerate_url( 'ctrl', 'ctrl=upload' ) ),
	)
);


// Display <html><head>...</head> section! (Note: should be done early if actions do not redirect)
$AdminUI->disp_html_head();

// Display title, menu, messages, etc. (Note: messages MUST be displayed AFTER the actions)
$AdminUI->disp_body_top();


/*
 * Display payload:
 */
$AdminUI->disp_view( 'files/views/_file_upload.view.php' );


// Display body bottom, debug info and close </html>:
$AdminUI->disp_global_footer();


/*
 * $Log$
 * Revision 1.13  2009/08/29 12:23:56  tblue246
 * - SECURITY:
 * 	- Implemented checking of previously (mostly) ignored blog_media_(browse|upload|change) permissions.
 * 	- files.ctrl.php: Removed redundant calls to User::check_perm().
 * 	- XML-RPC APIs: Added missing permission checks.
 * 	- items.ctrl.php: Check permission to edit item with current status (also checks user levels) for update actions.
 * - XML-RPC client: Re-added check for zlib support (removed by update).
 * - XML-RPC APIs: Corrected method signatures (return type).
 * - Localization:
 * 	- Fixed wrong permission description in blog user/group permissions screen.
 * 	- Removed wrong TRANS comment
 * 	- de-DE: Fixed bad translation strings (double quotes + HTML attribute = mess).
 * - File upload:
 * 	- Suppress warnings generated by move_uploaded_file().
 * 	- File browser: Hide link to upload screen if no upload permission.
 * - Further code optimizations.
 *
 * Revision 1.12  2009/07/06 23:52:24  sam2kb
 * Hardcoded "admin.php" replaced with $dispatcher
 *
 * Revision 1.11  2009/03/08 23:57:42  fplanque
 * 2009
 *
 * Revision 1.10  2008/09/29 08:30:43  fplanque
 * Avatar support
 *
 * Revision 1.9  2008/09/29 03:52:47  fplanque
 * bugfix - iframe name passthrough
 *
 * Revision 1.8  2008/02/20 02:48:25  blueyed
 * Fix default for "path" param, which is "" and not "/". Fixes double slashes in quick-uploaded files.
 *
 * Revision 1.7  2008/02/19 11:11:17  fplanque
 * no message
 *
 * Revision 1.6  2008/02/04 13:57:50  fplanque
 * wording
 *
 * Revision 1.5  2008/01/28 20:17:45  fplanque
 * better display of image file linking while in 'upload' mode
 *
 * Revision 1.4  2008/01/21 09:35:28  fplanque
 * (c) 2008
 *
 * Revision 1.3  2008/01/06 05:16:33  fplanque
 * enhanced upload
 *
 * Revision 1.2  2007/09/26 23:32:39  fplanque
 * upload context saving
 *
 * Revision 1.1  2007/06/25 10:59:53  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.10  2007/04/26 00:11:13  fplanque
 * (c) 2007
 *
 * Revision 1.9  2007/04/20 01:42:32  fplanque
 * removed excess javascript
 *
 * Revision 1.8  2007/02/22 18:36:57  fplanque
 * better error messages
 *
 * Revision 1.7  2007/01/24 13:44:56  fplanque
 * cleaned up upload
 *
 * Revision 1.6  2007/01/24 02:35:42  fplanque
 * refactoring
 *
 * Revision 1.5  2007/01/24 01:40:15  fplanque
 * Upload tab now stays in context
 *
 * Revision 1.4  2007/01/10 21:41:51  blueyed
 * todo: any "error" does not allow uploading and "blog media directory could not be created" is such an error, which may not be relevant
 *
 * Revision 1.3  2006/12/23 22:53:11  fplanque
 * extra security
 *
 * Revision 1.2  2006/12/22 01:09:30  fplanque
 * cleanup
 *
 * Revision 1.1  2006/12/22 00:51:34  fplanque
 * dedicated upload tab - proof of concept
 * (interlinking to be done)
 *
 */
?>
