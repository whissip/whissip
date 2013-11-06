<?php
/**
 * This file implements the UI for file display settings.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2013 by Francois Planque - {@link http://fplanque.com/}
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
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * @var UserSettings
 */
global $UserSettings;

$Form = new Form( NULL, 'file_displaysettings_checkchanges' );

$Form->global_icon( T_('Close settings!'), 'close', regenerate_url() );

$Form->begin_form( 'fform', T_('Display settings') );

	$Form->add_crumb( 'file' );
	$Form->hidden_ctrl();
	$Form->hiddens_by_key( get_memorized() );

	$Form->begin_fieldset( T_('Images') );
		$Form->checkbox( 'option_imglistpreview', $UserSettings->get('fm_imglistpreview'), T_('Thumbnails'), T_('Check to display thumbnails instead of icons for image files') );
		$Form->checkbox( 'option_getimagesizes', $UserSettings->get('fm_getimagesizes'), T_('Dimensions'), T_('Check to display the pixel dimensions of image files') );
	$Form->end_fieldset();

	$Form->begin_fieldset( T_('Columns') );
		$Form->checkbox( 'option_showtypes', $UserSettings->get('fm_showtypes'), T_('File type'), T_('Based on file extension') );
		$Form->radio_input( 'option_showdate', $UserSettings->get('fm_showdate'), array(
				array( 'value'=>'no', 'label'=>T_('No') ),
				array( 'value'=>'compact', 'label'=>T_('Compact format') ),
				array( 'value'=>'long', 'label'=>T_('Long format') ) ), T_('Last change') );
		$Form->checkbox( 'option_showfsperms', $UserSettings->get('fm_showfsperms'), T_('File permissions'), T_('Unix file permissions') );
		$Form->checkbox( 'option_permlikelsl', $UserSettings->get('fm_permlikelsl'), '', T_('Check to display file permissions like "rwxr-xr-x" rather than short form') );
		$Form->checkbox( 'option_showfsowner', $UserSettings->get('fm_showfsowner'), T_('File Owner'), T_('Unix file owner') );
		$Form->checkbox( 'option_showfsgroup', $UserSettings->get('fm_showfsgroup'), T_('File Group'), T_('Unix file group') );
	$Form->end_fieldset();

	$Form->begin_fieldset( T_('Options') );
		$Form->checkbox( 'option_showhidden', $UserSettings->get('fm_showhidden'), T_('Hidden files'), T_('Check this to show system hidden files. System hidden files start with a dot (.)') );
		$Form->checkbox( 'option_showevocache', $UserSettings->get('fm_showevocache'), '', T_('Check this to show _evocache folders (not recommended)') );
		$Form->checkbox( 'option_dirsattop', !$UserSettings->get('fm_dirsnotattop'), T_('Folders first'), T_('Check to always display folders before files') );
		$Form->checkbox( 'option_recursivedirsize', $UserSettings->get('fm_recursivedirsize'), T_('Folder sizes'), T_('Check to compute recursive size of folders') );
		$Form->radio_input( 'option_allowfiltering', $UserSettings->get('fm_allowfiltering'), array(
				array( 'value'=>'no', 'label'=>T_('Don\'t show') ),
				array( 'value'=>'simple', 'label'=>T_('Simple') ),
				array( 'value'=>'regexp', 'label'=>T_('With regular expressions') ) ), T_('Filter box') );
	$Form->end_fieldset();

if( $current_User->check_perm( 'options', 'edit', false ) )
{	// TODO: better perm check
	echo '<p class="note">'.T_('See also:').' ';

  /**
	 * @var FileRoot
	 */
	global $fm_FileRoot;
	if( $fm_FileRoot->type == 'collection' )
	{
		echo T_('Blog Settings').' &gt; '.T_('Advanced').' &gt; <a href="?ctrl=coll_settings&tab=advanced&blog='.$fm_FileRoot->in_type_ID.'">'
					.T_('Media directory location').'</a>';
	}
}

$Form->end_form( array( array( 'submit', 'actionArray[update_settings]', T_('Update !'), 'ActionButton'),
												array( 'reset', '', T_('Reset'), 'ResetButton' ) ) );

/*
 * $Log$
 * Revision 1.11  2013/11/06 08:04:15  efy-asimo
 * Update to version 5.0.1-alpha-5
 *
 */
?>