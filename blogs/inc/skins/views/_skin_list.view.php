<?php
/**
 * This file implements the UI view for the installed skins.
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2009 by Francois PLANQUE - {@link http://fplanque.net/}.
 *
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
 *
 * @package admin
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

// Create result set:
$SQL = & new SQL();
$SQL->SELECT( 'T_skins__skin.*, COUNT(blog_ID) AS nb_blogs' );
$SQL->FROM( 'T_skins__skin LEFT JOIN T_blogs ON skin_ID = blog_skin_ID' );
$SQL->GROUP_BY( 'skin_ID' );

$CountSQL = & new SQL();
$CountSQL->SELECT( 'COUNT( * )' );
$CountSQL->FROM( 'T_skins__skin' );

$Results = & new Results( $SQL->get(), '', '', NULL, $CountSQL->get() );

$Results->Cache = & get_SkinCache();

$Results->title = T_('Installed skins');

if( $current_User->check_perm( 'options', 'edit', false ) )
{ // We have permission to modify:
	$Results->cols[] = array(
							'th' => T_('Name'),
							'order' => 'skin_name',
							'td' => '<strong><a href="'.regenerate_url( '', 'skin_ID=$skin_ID$&amp;action=edit' ).'" title="'.TS_('Edit skin properties...').'">$skin_name$</a></strong>',
						);
}
else
{ // We have NO permission to modify:
	$Results->cols[] = array(
							'th' => T_('Name'),
							'order' => 'skin_name',
							'td' => '<strong>$skin_name$</strong>',
						);
}

$Results->cols[] = array(
						'th' => T_('Skin type'),
						'order' => 'skin_type',
						'td_class' => 'center',
						'td' => '$skin_type$',
					);

$Results->cols[] = array(
						'th' => T_('Blogs'),
						'order' => 'nb_blogs',
						'th_class' => 'shrinkwrap',
						'td_class' => 'center',
						'td' => '�conditional( (#nb_blogs# > 0), #nb_blogs#, \'&nbsp;\' )�',
					);

$Results->cols[] = array(
						'th' => T_('Skin Folder'),
						'order' => 'skin_folder',
						'td' => '$skin_folder$',
					);

if( $current_User->check_perm( 'options', 'edit', false ) )
{ // We have permission to modify:
	$Results->cols[] = array(
							'th' => T_('Actions'),
							'th_class' => 'shrinkwrap',
							'td_class' => 'shrinkwrap',
							'td' => action_icon( TS_('Edit skin properties...'), 'properties',
	                        '%regenerate_url( \'\', \'skin_ID=$skin_ID$&amp;action=edit\')%' )
	                    .action_icon( TS_('Reload containers!'), 'reload',
	                        '%regenerate_url( \'\', \'skin_ID=$skin_ID$&amp;action=reload\')%' )
											.'�conditional( #nb_blogs# < 1, \''
											.action_icon( TS_('Uninstall this skin!'), 'delete',
	                        '%regenerate_url( \'\', \'skin_ID=$skin_ID$&amp;action=delete\')%' ).'\', \''
	                        .get_icon( 'delete', 'noimg' ).'\' )�',
						);

  $Results->global_icon( T_('Install new skin...'), 'new', regenerate_url( 'action,blog', 'action=new'), T_('Install new'), 3, 4  );
}


// $fadeout_array = array( 'skin_ID' => array(6) );
$fadeout_array = NULL;

$Results->display( NULL, 'session' );


/*
 * $Log$
 * Revision 1.7  2009/09/26 12:00:43  tblue246
 * Minor/coding style
 *
 * Revision 1.6  2009/09/25 13:09:36  efy-vyacheslav
 * Using the SQL class to prepare queries
 *
 * Revision 1.5  2009/09/25 07:33:14  efy-cantor
 * replace get_cache to get_*cache
 *
 * Revision 1.4  2009/03/08 23:57:46  fplanque
 * 2009
 *
 * Revision 1.3  2008/11/26 16:00:21  tblue246
 * Add correct SQL query for counting result rows, enables paging. Fixes http://forums.b2evolution.net/viewtopic.php?t=17280 .
 *
 */
?>
