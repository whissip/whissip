<?php
/**
 * This file implements the File type form.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}
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

/**
 * @var FileType
 */
global $edited_Filetype;

global $force_upload_forbiddenext;
global $rsc_path;

// Determine if we are creating or updating...
global $action;
$creating = is_create_action( $action );


$Form = new Form( NULL, 'ftyp_checkchanges', 'post', 'compact' );

$Form->global_icon( T_('Delete this filetype!'), 'delete', regenerate_url( 'action', 'action=delete' ) );
$Form->global_icon( T_('Cancel editing!'), 'close', regenerate_url( 'action' ) );

$Form->begin_form( 'fform', $creating ?  T_('New file type') : T_('File type') );

	$Form->add_crumb( 'filetype' );
	$Form->hidden_ctrl();
	$Form->hidden( 'action', $creating ? 'create' : 'update' );

	if( ! $creating ) $Form->hidden( 'ftyp_ID', $edited_Filetype->ID );

	$Form->text_input( 'ftyp_extensions', $edited_Filetype->extensions, 40, T_('Extensions'), '', array( 'maxlength'=>80, 'required'=>true, 'note'=>sprintf('E.g. &laquo;%s&raquo;'.', '.T_('separated by whitespace'), 'html') ) );

	$Form->text_input( 'ftyp_name', $edited_Filetype->name, 40, T_('File type name'), sprintf('E.g. &laquo;%s&raquo;', 'HTML file'), array( 'maxlength'=> 80, 'required'=>true ) );

	$Form->text_input( 'ftyp_mimetype', $edited_Filetype->mimetype, 40, T_('Mime type'), sprintf('E.g. &laquo;%s&raquo;', 'text/html'), array( 'maxlength'=> 80, 'required'=>true ) );

	$Form->text( 'ftyp_icon', $edited_Filetype->icon, 20, T_('Icon'), sprintf( /* TRANS: %s is a filesystem path */T_('File name of the icon, must be in %s.'), rel_path_to_base($rsc_path.'icons/fileicons/') ), 40 );

	$Form->radio( 'ftyp_viewtype',
								$edited_Filetype->viewtype,
								 array(
												array( 'browser', T_( 'Open with browser (popup)' ), T_( 'Let the browser handle the file in a popup.' ) ),
												array( 'text', T_( 'Open with text viewer (popup)' ), T_( 'Use the online text viewer (recommended for .txt)' ) ),
												array( 'image', T_( 'Open with image viewer (popup)' ), T_( 'Use the online image viewer (recommended for .gif .png .jpg)' ) ),
												array( 'external', T_( 'Open with external app (no popup)' ), T_( 'Let the browser handle the file in a popup. Note: if you do not want Word to open inside of IE, you must uncheck "browse in same window" in Windows\' file types.' ) ),
												array( 'download', T_( 'Download to disk (no popup)' ), T_( 'Tell the browser to save the file to disk instead of displaying it.' ) )
											),
									T_( 'View type' ),
									true // separate lines
							 );

	// Check if the extension is in the array of the not allowed upload extensions from _advanced.php
	$not_allowed = false;
	$extensions = explode ( ' ', $edited_Filetype->extensions );
	foreach($extensions as $extension)
	{
		if( in_array( $extension, $force_upload_forbiddenext ) )
		{
			$not_allowed = true;
			continue;
		}
	}

	$Form->radio( 'ftyp_allowed',  $edited_Filetype->allowed,
					array(
							array( 'any', T_( 'Allow anyone (including anonymous users) to upload/rename files of this type' ) ),
							array( 'registered', T_( 'Allow only registered users to upload/rename files of this type' ) ),
							array( 'admin', T_( 'Allow only admins to upload/rename files of this type' ) )
						),
					T_( 'Allow upload' ), true );

if( $creating )
{
	$Form->end_form( array( array( 'submit', 'submit', T_('Record'), 'SaveButton' ),
													array( 'submit', 'submit', T_('Record, then Create New'), 'SaveButton' ),
													array( 'submit', 'submit', T_('Record, then Create Similar'), 'SaveButton' ),
													array( 'reset', '', T_('Reset'), 'ResetButton' ) ) );
}
else
{
	$Form->end_form( array( array( 'submit', 'submit', T_('Update'), 'SaveButton' ),
													array( 'reset', '', T_('Reset'), 'ResetButton' ) ) );
}


/*
 * $Log$
 * Revision 1.8  2011/03/10 14:54:18  efy-asimo
 * Allow file types modification & add m4v file type
 *
 * Revision 1.7  2010/02/08 17:53:02  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.6  2010/01/30 18:55:27  blueyed
 * Fix "Assigning the return value of new by reference is deprecated" (PHP 5.3)
 *
 * Revision 1.5  2010/01/03 13:45:36  fplanque
 * set some crumbs (needs checking)
 *
 * Revision 1.4  2009/03/08 23:57:43  fplanque
 * 2009
 *
 * Revision 1.3  2008/01/21 09:35:30  fplanque
 * (c) 2008
 *
 * Revision 1.2  2007/10/08 08:31:59  fplanque
 * nicer forms
 *
 * Revision 1.1  2007/06/25 11:00:08  fplanque
 * MODULES (refactored MVC)
 *
 */
?>