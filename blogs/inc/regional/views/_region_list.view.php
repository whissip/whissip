<?php
/**
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2009-2014 by Francois PLANQUE - {@link http://fplanque.net/}
 * Parts of this file are copyright (c)2009 by The Evo Factory - {@link http://www.evofactory.com/}.
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
 * The Evo Factory grants Francois PLANQUE the right to license
 * The Evo Factory's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author efy-maxim: Evo Factory / Maxim.
 * @author fplanque: Francois Planque.
 *
 * @version $Id$
 */

if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

global $dispatcher;

// Get params from request
$s = param( 's', 'string', '', true );
$c = param( 'c', 'integer', 0, true );

// Create query
$SQL = new SQL();
$SQL->SELECT( 'rgn_ID, rgn_code, rgn_name, rgn_enabled, rgn_preferred, ctry_ID, ctry_name' );
$SQL->FROM( 'T_regional__region' );
$SQL->FROM_add( 'LEFT JOIN T_regional__country ON rgn_ctry_ID=ctry_ID' );
$SQL->ORDER_BY( '*, ctry_name, rgn_name' );

$sql_where = array();
if( !empty($s) )
{	// We want to filter on search keyword:
	// Note: we use CONCAT_WS (Concat With Separator) because CONCAT returns NULL if any arg is NULL
	$sql_where[] = 'CONCAT_WS( " ", rgn_code, rgn_name ) LIKE "%'.$DB->escape($s).'%"';
}
if( $c > 0 )
{	// We want to filter on search country:
	$sql_where[] = 'rgn_ctry_ID = "'.$DB->escape($c).'"';
}

if( count( $sql_where ) > 0 )
{	// Some filters are applied
	$SQL->WHERE( implode( ' AND ', $sql_where ) );
}

// Create result set:
$Results = new Results( $SQL->get(), 'rgn_', '----A' );

$Results->title = T_('Regions/States').get_manual_link('countries_list');

/*
 * STATUS TD:
 */
function rgn_td_enabled( $rgn_enabled, $rgn_ID )
{

	global $dispatcher;

	$r = '';

	if( $rgn_enabled == true )
	{
		$r .= action_icon( T_('Disable the region!'), 'bullet_full',
										regenerate_url( 'action', 'action=disable_region&amp;rgn_ID='.$rgn_ID.'&amp;'.url_crumb('region') ) );
	}
	else
	{
		$r .= action_icon( T_('Enable the region!'), 'bullet_empty',
										regenerate_url( 'action', 'action=enable_region&amp;rgn_ID='.$rgn_ID.'&amp;'.url_crumb('region') ) );
	}
	return $r;

}

function rgn_td_preferred( $rgn_preferred, $rgn_ID )
{

	global $dispatcher;

	$r = '';

	if( $rgn_preferred == true )
	{
		$r .= action_icon( T_('Remove from preferred regions'), 'bullet_full',
										regenerate_url( 'action', 'action=disable_region_pref&amp;rgn_ID='.$rgn_ID.'&amp;'.url_crumb('region') ) );
	}
	else
	{
		$r .= action_icon( T_('Add to preferred regions'), 'bullet_empty',
										regenerate_url( 'action', 'action=enable_region_pref&amp;rgn_ID='.$rgn_ID.'&amp;'.url_crumb('region') ) );
	}
	return $r;

}



$Results->cols[] = array(
		'th' => /* TRANS: shortcut for enabled */ T_('En'),
		'th_title' => T_('Enabled'),
		'order' => 'rgn_enabled',
		'td' => '%rgn_td_enabled( #rgn_enabled#, #rgn_ID# )%',
		'th_class' => 'shrinkwrap',
		'td_class' => 'shrinkwrap'
	);

$Results->cols[] = array(
		'th' => /* TRANS: shortcut for preferred */ T_('Pref'),
		'th_title' => T_('Preferred'),
		'order' => 'rgn_preferred',
		'default_dir' => 'D',
		'td' => '%rgn_td_preferred( #rgn_preferred# , #rgn_ID# )%',
		'th_class' => 'shrinkwrap',
		'td_class' => 'shrinkwrap'
	);


/**
 * Callback to add filters on top of the result set
 *
 * @param Form
 */
function filter_regions( & $Form )
{
	load_class( 'regional/model/_country.class.php', 'Country' );
	$CountryCache = & get_CountryCache( T_('All') );
	$Form->select_country( 'c', get_param('c'), $CountryCache, T_('Country'), array( 'allow_none' => true ) );

	$Form->text( 's', get_param('s'), 30, T_('Search'), '', 255 );
}

$Results->filter_area = array(
	'callback' => 'filter_regions',
	'presets' => array(
		'all' => array( T_('All'), '?ctrl=regions' ),
		)
	);


if( $current_User->check_perm( 'options', 'edit', false ) )
{ // We have permission to modify:
	$Results->cols[] = array(
							'th' => T_('Country'),
							'order' => 'ctry_name',
										'td' => '<a href="?ctrl=countries&amp;ctry_ID=$ctry_ID$&amp;action=edit" title="'.T_('Edit this country...')
											.'"><strong>$ctry_name$</strong></a>',
						);
}
else
{	// View only:
	$Results->cols[] = array(
							'th' => T_('Country'),
							'order' => 'ctry_name',
							'td' => '$ctry_name$',
						);

}


$Results->cols[] = array(
						'th' => T_('Code'),
						'td_class' => 'center',
						'order' => 'rgn_code',
						'td' => '<strong>$rgn_code$</strong>',
						'th_class' => 'shrinkwrap',
						'td_class' => 'shrinkwrap'
					);


if( $current_User->check_perm( 'options', 'edit', false ) )
{ // We have permission to modify:
	$Results->cols[] = array(
							'th' => T_('Name'),
							'order' => 'rgn_name',
							'td' => '<a href="?ctrl=regions&amp;rgn_ID=$rgn_ID$&amp;action=edit" title="'.T_('Edit this region...').'"><strong>$rgn_name$</strong></a>',
						);
}
else
{	// View only:
	$Results->cols[] = array(
							'th' => T_('Name'),
							'order' => 'rgn_name',
							'td' => '$rgn_name$',
						);

}

/*
 * ACTIONS TD:
 */
function rgn_td_actions($rgn_enabled, $rgn_ID )
{
	global $dispatcher;

	$r = '';

	if( $rgn_enabled == true )
	{
		$r .= action_icon( T_('Disable the region!'), 'deactivate', 
										regenerate_url( 'action', 'action=disable_region&amp;rgn_ID='.$rgn_ID.'&amp;'.url_crumb('region') ) );
	}
	else
	{
		$r .= action_icon( T_('Enable the region!'), 'activate',
										regenerate_url( 'action', 'action=enable_region&amp;rgn_ID='.$rgn_ID.'&amp;'.url_crumb('region') ) );
	}
	$r .= action_icon( T_('Edit this region...'), 'edit',
										regenerate_url( 'action', 'rgn_ID='.$rgn_ID.'&amp;action=edit' ) );
	$r .= action_icon( T_('Duplicate this region...'), 'copy',
										regenerate_url( 'action', 'rgn_ID='.$rgn_ID.'&amp;action=new' ) );
	$r .= action_icon( T_('Delete this region!'), 'delete',
										regenerate_url( 'action', 'rgn_ID='.$rgn_ID.'&amp;action=delete&amp;'.url_crumb('region') ) );

	return $r;
}
if( $current_User->check_perm( 'options', 'edit', false ) )
{
	$Results->cols[] = array(
			'th' => T_('Actions'),
			'td' => '%rgn_td_actions( #rgn_enabled#, #rgn_ID# )%',
			'td_class' => 'shrinkwrap',
		);

	$Results->global_icon( T_('Create a new region...'), 'new',
				regenerate_url( 'action', 'action=new'), T_('New region').' &raquo;', 3, 4  );
}

$Results->display();

?>