<?php
/**
 * This file implements the file editing form.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2008 by Francois PLANQUE - {@link http://fplanque.net/}
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
 * @var File
 */
global $edited_File;

$Form = & new Form( NULL, 'file_edit' );

$Form->global_icon( T_('Cancel editing!'), 'close', regenerate_url('fm_mode') );

$Form->begin_form( 'fform', T_('Editing:').' '.$edited_File->get_rdfs_rel_path() );
	$Form->hidden_ctrl();
	$Form->hidden( 'action', 'update_file' );
	$Form->hiddens_by_key( get_memorized() );

 	$Form->switch_layout( 'none' );
	echo '<div class="center">';

	$Form->textarea_input( 'file_content', $edited_File->content, 25, '', array( 'class'=>'large', 'cols' => '80' ) );

	$Form->buttons( array( array( 'submit', '', T_('Save!'), 'SaveButton' ),
												array( 'reset', '', T_('Reset'), 'ResetButton' ) ) );

	echo '</div>';
 	$Form->switch_layout( NULL );

$Form->end_form();

/*
 * $Log$
 * Revision 1.3  2008/10/11 22:20:48  blueyed
 * Fix edit and properties view in file browser. (edit_File has been renamed to edited_File)
 *
 * Revision 1.2  2008/01/21 09:35:29  fplanque
 * (c) 2008
 *
 * Revision 1.1  2007/06/25 11:00:00  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.4  2007/04/26 00:11:10  fplanque
 * (c) 2007
 *
 * Revision 1.3  2007/01/24 03:45:29  fplanque
 * decrap / removed a lot of bloat...
 *
 * Revision 1.2  2007/01/24 02:35:42  fplanque
 * refactoring
 *
 * Revision 1.1  2006/12/07 20:03:32  fplanque
 * Woohoo! File editing... means all skin editing.
 *
 * Revision 1.5  2006/11/24 18:27:25  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 */
?>