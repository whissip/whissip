<?php
/**
 * This file display the additional tools
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2013 by Francois Planque - {@link http://fplanque.com/}.
 * Parts of this file are copyright (c)2005 by Daniel HAHLER - {@link http://thequod.de/contact}.
 *
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
 *
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author blueyed: Daniel HAHLER
 * @author efy-asimo: Attila Simo.
 *
 * @version $Id$
 */

if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

global $Plugins, $template_action;

$block_item_Widget = new Widget( 'block_item' );

if( !empty( $template_action ) )
{ // Execute action inside template to display a process in real rime
	$block_item_Widget->title = T_('Log');
	$block_item_Widget->disp_template_replaced( 'block_start' );

	// Turn off the output buffering to do the correct work of the function flush()
	@ini_set( 'output_buffering', 'off' );
	flush();

	switch( $template_action )
	{
		case 'optimize_tables':
			// Optimize MyISAM & InnoDB tables
			dbm_optimize_tables();
			break;

		case 'check_tables':
			// Check ALL database tables
			dbm_check_tables();
			break;

		case 'analyze_tables':
			// Analize ALL database tables
			dbm_analyze_tables();
			break;
	}
	$block_item_Widget->disp_template_raw( 'block_end' );
}


if( $current_User->check_perm( 'users', 'edit' ) )
{ // Setting to lock system
	global $Settings;

	$Form = new Form( NULL, 'settings_checkchanges' );
	$Form->begin_form( 'fform' );

	$Form->add_crumb( 'globalsettings' );
	$Form->hidden( 'ctrl', 'gensettings' );
	$Form->hidden( 'action', 'update_tools' );

	$Form->begin_fieldset( T_('Locking down b2evolution for maintenance, upgrade or server switching...') );

		$Form->checkbox_input( 'system_lock', $Settings->get('system_lock'), T_('Lock system'), array(
				'note' => T_('check this to prevent login (except for admins) and sending comments/messages. This prevents the DB from receiving updates (other than logging)').'<br />'.
				          T_('Note: for a more complete lock down, rename the file /conf/_maintenance.html to /conf/maintenance.html (complete lock) or /conf/imaintenance.html (gives access to /install)') ) );

	if( $current_User->check_perm( 'options', 'edit' ) )
	{
		$Form->buttons( array( array( 'submit', 'submit', T_('Save changes!'), 'SaveButton' ) ) );
	}

	$Form->end_fieldset();

	$Form->end_form();
}

// TODO: dh> this should really be a separate permission.. ("tools", "exec") or similar!
if( $current_User->check_perm('options', 'edit') )
{ // default admin actions:
	global $Settings;

	$block_item_Widget->title = T_('Cache management');
	// dh> TODO: add link to delete all caches at once?
	$block_item_Widget->disp_template_replaced( 'block_start' );
	echo '<ul>';
	echo '<li><a href="'.regenerate_url('action', 'action=del_itemprecache&amp;'.url_crumb('tools')).'">'.T_('Clear pre-renderered item cache (DB)').'</a></li>';
	echo '<li><a href="'.regenerate_url('action', 'action=del_commentprecache&amp;'.url_crumb('tools')).'">'.T_('Clear pre-renderered comment cache (DB)').'</a></li>';
	echo '<li><a href="'.regenerate_url('action', 'action=del_pagecache&amp;'.url_crumb('tools')).'">'.T_('Clear full page cache (/cache directory)').'</a></li>';
	echo '<li><a href="'.regenerate_url('action', 'action=del_filecache&amp;'.url_crumb('tools')).'">'.T_('Clear thumbnail caches (?evocache directories)').'</a></li>';
	echo '<li><a href="'.regenerate_url('action', 'action=repair_cache&amp;'.url_crumb('tools')).'">'.T_('Repair cache').'</a></li>';
	echo '</ul>';
	$block_item_Widget->disp_template_raw( 'block_end' );

	$block_item_Widget->title = T_('Database management');
	$block_item_Widget->disp_template_replaced( 'block_start' );
	echo '<ul>';
	echo '<li><a href="'.regenerate_url('action', 'action=check_tables&amp;'.url_crumb('tools')).'">'.T_('CHECK database tables').'</a></li>';
	echo '<li><a href="'.regenerate_url('action', 'action=optimize_tables&amp;'.url_crumb('tools')).'">'.T_('OPTIMIZE database tables').'</a></li>';
	echo '<li><a href="'.regenerate_url('action', 'action=analyze_tables&amp;'.url_crumb('tools')).'">'.T_('ANALYZE database tables').'</a></li>';
	// echo '<li><a href="'.regenerate_url('action', 'action=backup_db').'">'.T_('Backup database').'</a></li>';
	echo '</ul>';
	$block_item_Widget->disp_template_raw( 'block_end' );

	$block_item_Widget->title = T_('Database Maintenance Tools');
	$block_item_Widget->disp_template_replaced( 'block_start' );
	echo '<ul>';
	echo '<li><a href="'.regenerate_url('action', 'action=del_obsolete_tags&amp;'.url_crumb('tools')).'">'.T_('Remove obsolete (unused) tag entries').'</a></li>';
	echo '<li><a href="'.regenerate_url('action', 'action=find_broken_posts&amp;'.url_crumb('tools')).'">'.T_('Find all broken posts that have no matching category').'</a></li>';
	echo '<li><a href="'.regenerate_url('action', 'action=find_broken_slugs&amp;'.url_crumb('tools')).'">'.T_('Find all broken slugs that have no matching target post').'</a></li>';
// fp>attila : is this a DB maintenance tool or a FILE maintenance tool?
// attila>fp : It is both. It will delete the orphan files from the disk, and also from the DB.
	echo '<li><a href="'.regenerate_url('action', 'action=delete_orphan_comment_uploads&amp;'.url_crumb('tools')).'">'.T_('Find and delete orphan comment uploads').'</a></li>';
	echo '<li><a href="'.regenerate_url('action', 'action=prune_hits_sessions&amp;'.url_crumb('tools')).'">'.T_('Prune old hits & sessions (includes OPTIMIZE)').'</a></li>';
	echo '</ul>';
	$block_item_Widget->disp_template_raw( 'block_end' );

	$block_item_Widget->title = T_('Recreate item slugs');
	$block_item_Widget->disp_template_replaced( 'block_start' );
	echo '&raquo; <a href="'.regenerate_url('action', 'action=recreate_itemslugs&amp;'.url_crumb('tools')).'">'.T_('Recreate all item slugs (change title-[0-9] canonical slugs to a slug generated from current title). Old slugs will still work, but redirect to the new one.').'</a>';
	$block_item_Widget->disp_template_raw( 'block_end' );
}


// Event AdminToolPayload for each Plugin:
$tool_plugins = $Plugins->get_list_by_event( 'AdminToolPayload' );
foreach( $tool_plugins as $loop_Plugin )
{
	$block_item_Widget->title = format_to_output($loop_Plugin->name);
	$block_item_Widget->disp_template_replaced( 'block_start' );
	$Plugins->call_method_if_active( $loop_Plugin->ID, 'AdminToolPayload', $params = array() );
	$block_item_Widget->disp_template_raw( 'block_end' );
}


/*
 * $Log$
 * Revision 1.15  2013/11/06 08:04:55  efy-asimo
 * Update to version 5.0.1-alpha-5
 *
 */
?>