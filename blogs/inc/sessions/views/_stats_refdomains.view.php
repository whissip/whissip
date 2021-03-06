<?php
/**
 * This file implements the UI view for the User Agents stats.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}
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
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * View funcs
 */
require_once dirname(__FILE__).'/_stats_view.funcs.php';


global $blog, $admin_url, $rsc_url;

global $dtyp_normal, $dtyp_searcheng, $dtyp_aggregator, $dtyp_unknown;

// For the referring domains list:
param( 'dtyp_normal', 'integer', 0, true );
param( 'dtyp_searcheng', 'integer', 0, true );
param( 'dtyp_aggregator', 'integer', 0, true );
param( 'dtyp_unknown', 'integer', 0, true );

if( !$dtyp_normal && !$dtyp_searcheng && !$dtyp_aggregator && !$dtyp_unknown )
{	// Set default status filters:
	$dtyp_normal = 1;
	$dtyp_searcheng = 1;
	$dtyp_aggregator = 1;
	$dtyp_unknown = 1;
}


echo '<h2>'.T_('Referring domains').'</h2>';

$SQL = new SQL();

$selected_agnt_types = array();
if( $dtyp_normal ) $selected_agnt_types[] = "'normal'";
if( $dtyp_searcheng ) $selected_agnt_types[] = "'searcheng'";
if( $dtyp_aggregator ) $selected_agnt_types[] = "'aggregator'";
if( $dtyp_unknown ) $selected_agnt_types[] = "'unknown'";
$SQL->WHERE( 'dom_type IN ( ' . implode( ', ', $selected_agnt_types ) . ' )' );

// Exclude hits of type "self" and "admin":
// TODO: fp>implement filter checkboxes, not a hardwired filter
//$where_clause .= ' AND hit_referer_type NOT IN ( "self", "admin" )';

if( !empty($blog) )
{
	$SQL->WHERE_and( 'hit_blog_ID = ' . $blog );
}

$SQL->SELECT( 'SQL_NO_CACHE COUNT(*) AS hit_count' );
$SQL->FROM( 'T_basedomains INNER JOIN T_hitlog ON dom_ID = hit_referer_dom_ID' );

$total_hit_count = $DB->get_var( $SQL->get(), 0, 0, 'Get total hit count - referred hits only' );


// Create result set:
$SQL->SELECT( 'SQL_NO_CACHE dom_name, dom_status, dom_type, COUNT( * ) AS hit_count' );
$SQL->GROUP_BY( 'dom_ID' );

$CountSQL = new SQL();
$CountSQL->SELECT( 'SQL_NO_CACHE COUNT( DISTINCT dom_ID )' );
$CountSQL->FROM( $SQL->get_from( '' ) );
$CountSQL->WHERE( $SQL->get_where( '' ) );

$Results = new Results( $SQL->get(), 'refdom_', '---D', 20, $CountSQL->get() );

/**
 * Callback to add filters on top of the result set
 *
 * @param Form
 */
function filter_basedomains( & $Form )
{
	global $blog, $dtyp_normal, $dtyp_searcheng, $dtyp_aggregator, $dtyp_unknown;

	$Form->checkbox( 'dtyp_normal', $dtyp_normal, T_('Regular sites') );
	$Form->checkbox( 'dtyp_searcheng', $dtyp_searcheng, T_('Search engines') );
	$Form->checkbox( 'dtyp_aggregator', $dtyp_aggregator, T_('Feed aggregators') );
	$Form->checkbox( 'dtyp_unknown', $dtyp_unknown, T_('Unknown') );
}
$Results->filter_area = array(
	'callback' => 'filter_basedomains',
	'url_ignore' => 'results_refdom_page,dtyp_normal,dtyp_searcheng,dtyp_aggregator,dtyp_unknown',	// ignore page param and checkboxes
	'presets' => array(
			'browser' => array( T_('Regular'), '?ctrl=stats&amp;tab=domains&amp;dtyp_normal=1&amp;blog='.$blog ),
			'robot' => array( T_('Search engines'), '?ctrl=stats&amp;tab=domains&amp;dtyp_searcheng=1&amp;blog='.$blog ),
			'rss' => array( T_('Aggregators'), '?ctrl=stats&amp;tab=domains&amp;dtyp_aggregator=1&amp;blog='.$blog ),
			'unknown' => array( T_('Unknown'), '?ctrl=stats&amp;tab=domains&amp;dtyp_unknown=1&amp;blog='.$blog ),
			'all' => array( T_('All'), '?ctrl=stats&amp;tab=domains&amp;dtyp_normal=1&amp;dtyp_searcheng=1&amp;dtyp_aggregator=1&amp;dtyp_unknown=1&amp;blog='.$blog ),
		)
	);


$Results->title = T_('Referring domains');

$Results->cols[] = array(
						'th' => T_('Domain name'),
						'order' => 'dom_name',
						'td' => '�dom_name�',
						'total' => '<strong>'.T_('Global total').'</strong>',
					);

$Results->cols[] = array(
						'th' => T_('Type'),
						'order' => 'dom_type',
						'td' => '$dom_type$',
						'total' => '',
					);

$Results->cols[] = array(
						'th' => T_('Status'),
						'order' => 'dom_status',
						'td' => '$dom_status$',
						'total' => '',
					);

$Results->cols[] = array(
						'th' => T_('Hit count'),
						'order' => 'hit_count',
						'td_class' => 'right',
						'total_class' => 'right',
						'td' => '$hit_count$',
						'total' => $total_hit_count,
					);

$Results->cols[] = array(
						'th' => T_('Hit %'),
						'order' => 'hit_count',
						'td_class' => 'right',
						'total_class' => 'right',
						'td' => '%percentage( #hit_count#, '.$total_hit_count.' )%',
						'total' => '%percentage( 100, 100 )%',
					);

// Display results:
$Results->display();

/*
 * $Log$
 * Revision 1.7  2010/02/08 17:53:55  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.6  2010/01/30 18:55:34  blueyed
 * Fix "Assigning the return value of new by reference is deprecated" (PHP 5.3)
 *
 * Revision 1.5  2009/09/25 13:09:36  efy-vyacheslav
 * Using the SQL class to prepare queries
 *
 * Revision 1.4  2009/09/13 21:26:50  blueyed
 * SQL_NO_CACHE for SELECT queries using T_hitlog
 *
 * Revision 1.3  2009/03/08 23:57:45  fplanque
 * 2009
 *
 * Revision 1.2  2008/01/21 09:35:33  fplanque
 * (c) 2008
 *
 * Revision 1.1  2007/06/25 11:01:03  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.8  2007/04/26 00:11:13  fplanque
 * (c) 2007
 *
 * Revision 1.7  2007/03/20 09:53:26  fplanque
 * Letting boggers view their own stats.
 * + Letthing admins view the aggregate by default.
 *
 * Revision 1.6  2006/11/26 01:42:10  fplanque
 * doc
 */
?>
