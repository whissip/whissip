<?php
/**
 * This file implements the UI view for the file settings.
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
 * @author fsaya: Fabrice SAYA-GASNIER / PROGIDISTRI
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * @var GeneralSettings
 */
global $Settings;

global $upload_maxmaxkb;

/**
 * Javascript to init hidden/shown state of something (like a DIV) based on a checkbox
 *
 * EXPERIMENTAL
 * Will be moved to another file, I'm leaving it here for a short period, in order to provide context
 *
 * PROBLEM: jQuery is not necessarily loaded at the moment we use this :(
 *
 * @param string DOM class
 * @param string DOM id
 */
function JS_showhide_class_on_checkbox( $class, $checkbox_id )
{
	return '<script type="text/javascript">
    if( document.getElementById("'.$checkbox_id.'").checked )
		{
 			jQuery(".'.$class.'").show();
		}
		else
		{
 			jQuery(".'.$class.'").hide();
		}
	</script>';
}

/**
 * Javascript to init hidden/shown state of something (like a DIV) based on a checkbox
 *
 * EXPERIMENTAL
 * Will be moved to another file, I'm leaving it here for a short period, in order to provide context
 *
 * @param array|string DOM id
 * @param string DOM id
 */
function JS_showhide_ids_on_checkbox( $div_ids, $checkbox_id )
{
	if( !is_array($div_ids) )
	{
		$div_ids = array( $div_ids );
	}
	$r = '<script type="text/javascript">
		var display = document.getElementById("'.$checkbox_id.'").checked ? "" : "none";'."\n";
	foreach( $div_ids as $div_id )
	{
		$r .= 'document.getElementById("'.$div_id.'").style.display = display;'."\n";
	}
	$r .= '</script>';
	return $r;
}

/**
 * Javascript to init hidden/shown state of a fastform field based on a checkbox
 *
 * EXPERIMENTAL
 * Will be moved to another file, I'm leaving it here for a short period, in order to provide context
 *
 * @param string form field id as used when creating it with the Form class
 * @param string DOM id
 */
function JS_showhide_ffield_on_checkbox( $field_id, $checkbox_id )
{
	return '<script type="text/javascript">
		document.getElementById("ffield_'.$field_id.'").style.display = (document.getElementById("'.$checkbox_id.'").checked ? "" : "none")
	</script>';
}

/**
 * Javascript hide/show all DOM elements with a particular class based on current checkbox
 *
 * EXPERIMENTAL
 * Will be moved to another file, I'm leaving it here for a short period, in order to provide context
 *
 * @param string DOM class name
 */
function JS_showhide_class_on_this( $class )
{
	return 'if( this.checked )
		{
 			jQuery(".'.$class.'").show();
		}
		else
		{
 			jQuery(".'.$class.'").hide();
		}';
}

/**
 * Javascript hide/show something (like a DIV) based on current checkbox
 *
 * EXPERIMENTAL
 * Will be moved to another file, I'm leaving it here for a short period, in order to provide context
 *
 * @param array|string DOM ids
 */
function JS_showhide_ids_on_this( $div_ids )
{
	if( !is_array($div_ids) )
	{
		$div_ids = array( $div_ids );
	}
	$r = 'var display = this.checked ? "" : "none";'."\n";
	foreach( $div_ids as $div_id )
	{
		$r .= 'document.getElementById("'.$div_id.'").style.display = display;'."\n";
	}
	return $r;
}

/**
 * Javascript hide/show a fastform field based on current checkbox
 *
 * EXPERIMENTAL
 * Will be moved to another file, I'm leaving it here for a short period, in order to provide context
 *
 * @param string DOM id
 */
function JS_showhide_ffield_on_this( $field_id )
{
	return 'document.getElementById("ffield_'.$field_id.'").style.display = (this.checked ? "" : "none")';
}


$Form = new Form( NULL, 'files_checkchanges' );

$Form->begin_form( 'fform', T_('File manager settings') );

$Form->add_crumb( 'file' );
$Form->hidden( 'ctrl', 'fileset' );
$Form->hidden( 'action', 'update' );

$Form->begin_fieldset( T_('Accessible file roots').get_manual_link('accessible_file_roots'), array( 'id' => 'ffset_fileroots', 'class' => 'additional_file_settings' ) );
	$Form->checkbox( 'fm_enable_roots_blog', $Settings->get('fm_enable_roots_blog'), T_('Enable blog directories'), T_('Check to enable root directories for blogs.' ) );
	$Form->checkbox( 'fm_enable_roots_user', $Settings->get('fm_enable_roots_user'), T_('Enable user directories'), T_('Check to enable root directories for users.' ) );
	$Form->checkbox( 'fm_enable_roots_shared', $Settings->get('fm_enable_roots_shared'), T_('Enable shared directory'), T_('Check to enable shared root directory.' ) );
	$Form->checkbox( 'fm_enable_roots_skins', $Settings->get('fm_enable_roots_skins'), T_('Enable skins directory'), T_('Check to enable root directory for skins.' ) );	// fp> note: meaning may change to 1 dir per (installed) skin
$Form->end_fieldset();

$Form->begin_fieldset( T_('File creation options'), array( 'id' => 'ffset_filecreate', 'class' => 'additional_file_settings' ) );
	$Form->checkbox( 'fm_enable_create_dir', $Settings->get('fm_enable_create_dir'), T_('Enable creation of folders'), T_('Check to enable creation of directories.' ) );
	$Form->checkbox( 'fm_enable_create_file', $Settings->get('fm_enable_create_file'), T_('Enable creation of files'), T_('Check to enable creation of files.' ) );
	$Form->checkbox_input( 'upload_enabled', $Settings->get('upload_enabled'), T_('Enable upload of files'), array(
		'note' => T_('Check to allow uploading files in general.' ), 'onclick' => JS_showhide_ffield_on_this('upload_maxkb') ) );
	$Form->text_input( 'upload_maxkb', $Settings->get('upload_maxkb'), 6, T_('Maximum upload filesize'), sprintf( /* TRANS: first %s is php.ini limit, second is setting/var name, third is file name, 4th is limit in b2evo conf */ T_('KB. This cannot be higher than your PHP/Webserver setting (PHP: %s) and the limit of %s (in %s), which is currently %s!'), ini_get('upload_max_filesize').'/'.ini_get('post_max_size').' (upload_max_filesize/post_max_size)', '$upload_maxmaxkb', '/conf/_advanced.php', $upload_maxmaxkb.' '.T_('KB') ), array( 'maxlength'=>7, 'required'=>true ) );
	// Javascript to init hidden/shown state:
	echo JS_showhide_ffield_on_checkbox( 'upload_maxkb', 'upload_enabled' );
$Form->end_fieldset();

$Form->begin_fieldset( T_('Advanced options'), array( 'id' => 'ffset_fileadvanced', 'class' => 'additional_file_settings' ) );

	$Form->text_input( 'fm_default_chmod_dir', $Settings->get('fm_default_chmod_dir'), 4, T_('Default folder permissions'), T_('Default CHMOD (UNIX permissions) for new directories created by the file manager.' ) );

	// fp> Does the following also applu to *uploaded* files? (It should)
 	$Form->text_input( 'fm_default_chmod_file', $Settings->get('fm_default_chmod_file'), 4, T_('Default file permissions'), T_('Default CHMOD (UNIX permissions) for new files created by the file manager.' ) );

	if( empty( $force_regexp_filename ) || empty( $force_regexp_dirname ) )
	{ // At least one of these strings can be configured in the UI:

		// Do not display regexp for filename if the force_regexp_filename var is set
		if( empty($force_regexp_filename) )
		{
			$Form->text( 'regexp_filename',
											$Settings->get('regexp_filename'),
											40,
											T_('Valid filename'),
											T_('Regular expression'),
											255 );
		}
		// Do not display regexp for dirname if the force_regexp_dirname var is set
		if( empty( $force_regexp_dirname ) )
		{
			$Form->text( 'regexp_dirname',
											$Settings->get('regexp_dirname'),
											40,
											T_('Valid dirname'),
											T_('Regular expression'),
											255 );
		}
	}

	$Form->radio_input( 'evocache_foldername', $Settings->get( 'evocache_foldername' ), array(
						array( 'value' => '.evocache', 'label' => T_('Use .evocache folders (system hidden folders)') ),
						array( 'value' => '_evocache', 'label' => T_('Use _evocache folders (compatible with all webservers)') ) ), T_('Cache folder names'), array( 'lines' => 2 ) );

$Form->end_fieldset();

if( $current_User->check_perm( 'options', 'edit', false ) )
{ // We have permission to modify:
	$Form->buttons( array(
			array( 'submit', 'submit', T_('Update'), 'SaveButton' ),
			array( 'reset', '', T_('Reset'), 'ResetButton' ),
			array( 'submit', 'submit[restore_defaults]', T_('Restore defaults'), 'ResetButton' ),
		) );
}

$Form->end_form();

if( $current_User->check_perm( 'options', 'edit', false ) )
{	// TODO: better perm check
	echo '<p class="note">'.T_('See also:').' ';
	echo T_('Blog Settings').' &gt; '.T_('Advanced').' &gt; '.T_('Media directory location');
}


/*
 * $Log$
 * Revision 1.15  2010/03/28 17:08:08  fplanque
 * minor
 *
 * Revision 1.14  2010/03/12 10:52:56  efy-asimo
 * Set EvoCache  folder names - task
 *
 * Revision 1.13  2010/03/01 07:52:34  efy-asimo
 * Set manual links to lowercase
 *
 * Revision 1.12  2010/02/14 14:18:39  efy-asimo
 * insert manual links
 *
 * Revision 1.11  2010/02/08 17:53:02  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.10  2010/01/03 13:45:36  fplanque
 * set some crumbs (needs checking)
 *
 * Revision 1.9  2009/12/08 23:42:03  fplanque
 * minor
 *
 * Revision 1.8  2009/12/06 22:55:21  fplanque
 * Started breadcrumbs feature in admin.
 * Work in progress. Help welcome ;)
 * Also move file settings to Files tab and made FM always enabled
 *
 * Revision 1.7  2009/08/06 15:02:48  fplanque
 * minor
 *
 * Revision 1.6  2009/07/29 21:06:17  blueyed
 * Make upload_maxkb settings note more verbose about the involved PHP settings
 *
 * Revision 1.5  2009/03/08 23:57:43  fplanque
 * 2009
 *
 * Revision 1.4  2008/09/23 06:18:38  fplanque
 * File manager now supports a shared directory (/media/shared/global/)
 *
 * Revision 1.3  2008/02/13 11:33:53  blueyed
 * Explicitly call jQuery(), not the shortcut ($())
 *
 * Revision 1.2  2008/01/21 09:35:30  fplanque
 * (c) 2008
 *
 * Revision 1.1  2007/06/25 11:00:05  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.15  2007/04/26 00:11:10  fplanque
 * (c) 2007
 *
 * Revision 1.14  2006/12/10 01:53:39  blueyed
 * Mention value of $upload_maxmaxkb
 *
 * Revision 1.13  2006/12/10 01:47:11  blueyed
 * Note about $upload_maxmaxkb limit
 *
 * Revision 1.12  2006/12/09 01:55:36  fplanque
 * feel free to fill in some missing notes
 * hint: "login" does not need a note! :P
 *
 * Revision 1.11  2006/12/07 15:23:42  fplanque
 * filemanager enhanced, refactored, extended to skins directory
 *
 * Revision 1.10  2006/12/06 21:22:49  fplanque
 * the jQuery tentative and more
 *
 * Revision 1.9  2006/12/06 18:06:18  fplanque
 * an experiment with JS hiding/showing form parts
 *
 * Revision 1.8  2006/11/28 01:40:13  fplanque
 * wording
 *
 * Revision 1.7  2006/11/26 01:42:09  fplanque
 * doc
 */
?>
