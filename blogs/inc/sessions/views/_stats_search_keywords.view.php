<?php
/**
 * This file implements the UI view for the referering searches stats.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2009 by Francois PLANQUE - {@link http://fplanque.net/}
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

load_class( '/sessions/model/_goal.class.php' );

global $blog, $admin_url, $rsc_url, $goal_ID, $localtimenow;
global $datestartinput, $datestart, $datestopinput, $datestop;

if( param_date( 'datestartinput', T_('Invalid date'), false,  NULL ) !== NULL )
{	// We have a user provided localized date:
	memorize_param( 'datestart', 'string', NULL, trim(form_date($datestartinput)) );
}
else
{	// We may have an automated param transmission date:
	param( 'datestart', 'string', '', true );
}
if( param_date( 'datestopinput', T_('Invalid date'), false, NULL ) !== NULL )
{	// We have a user provided localized date:
	memorize_param( 'datestop', 'string', NULL, trim(form_date($datestopinput)) );
}
else
{	// We may have an automated param transmission date:
	param( 'datestop', 'string', '', true );
}
//pre_dump( $datestart, $datestop );

if( $current_User->check_perm( 'stats', 'view' ) )
{	// Permission to view stats for ALL blogs:
	param( 'goal_ID', 'integer', 0, true );
	$goal_name = param( 'goal_name', 'string', NULL, true );
}
else
{
	$goal_ID = 0;
	$goal_name = NULL;
}

$split_engines = param( 'split_engines', 'integer', 0, true );

if( param_errors_detected() )
{
	$sql = 'SELECT 0 AS count';
	$sql_count = 0;
}
else
{
	$SQL = & new SQL();
	if( empty( $goal_ID ) && empty($goal_name)  )
	{	// We're not restricting to one or more Goals, get ALL possible keyphrases:
		$SQL->FROM( 'T_track__keyphrase INNER JOIN T_hitlog ON keyp_ID = hit_keyphrase_keyp_ID' );
		// Date param applies to serach hit
		if( !empty($datestart) )
		{
			$SQL->WHERE_and( 'T_hitlog.hit_datetime >= '.$DB->quote($datestart.' 00:00:00') );
		}
		if( !empty($datestop) )
		{
			$SQL->WHERE_and( 'T_hitlog.hit_datetime <= '.$DB->quote($datestop.' 23:59:59') );
		}
	}
	else
	{	// We ARE restricting to a Goal, start off with IPs and Sessions IDs that hit that goal
		// then find marching hits
		// then keywords
		// fp> Note: so far we only join on remote IP because MySQL can only use a single index. Solution: probably UNION 2 results
		// INNER JOIN T_hitlog ON (goalhit_hit.hit_sess_ID = T_hitlog.hit_sess_ID OR goalhit_hit.hit_remote_addr = T_hitlog.hit_remote_addr )
		$SQL->FROM( 'T_track__goalhit INNER JOIN T_hitlog AS goalhit_hit ON ghit_hit_ID = goalhit_hit.hit_ID
								INNER JOIN T_hitlog ON goalhit_hit.hit_remote_addr = T_hitlog.hit_remote_addr
								INNER JOIN T_track__keyphrase ON T_hitlog.hit_keyphrase_keyp_ID = keyp_ID' );
		if( !empty( $goal_ID ) )
		{
			$SQL->WHERE( 'ghit_goal_ID = '.$goal_ID );
		}
		else
		{
			$SQL->FROM_add( 'INNER JOIN T_track__goal ON goal_ID = ghit_goal_ID' );
			$SQL->WHERE_and( 'goal_name LIKE '.$DB->quote($goal_name.'%') );
		}

		// Date param applies to goal hit
		if( !empty($datestart) )
		{
			$SQL->WHERE_and( 'goalhit_hit.hit_datetime >= '.$DB->quote($datestart.' 00:00:00') );
		}
		if( !empty($datestop) )
		{
			$SQL->WHERE_and( 'goalhit_hit.hit_datetime <= '.$DB->quote($datestop.' 23:59:59') );
		}
	}
	$SQL->FROM_add( 'INNER JOIN T_useragents ON T_hitlog.hit_agnt_ID = agnt_ID' );
	$SQL->WHERE_and( ' T_hitlog.hit_referer_type = "search"
						 				AND agnt_type = "browser"' );
	if( $split_engines )
	{
		$SQL->GROUP_BY( 'keyp_ID, T_hitlog.hit_referer_dom_ID' );
	}
	else
	{
		$SQL->GROUP_BY( 'keyp_ID' );
	}

	if( ! empty($blog) )
	{
		$SQL->WHERE_and( 'T_hitlog.hit_blog_ID = '.$blog );
	}

	// COUNT:
	$SQL->SELECT( 'keyp_ID' );
	if( empty( $goal_ID ) && empty($goal_name) )
	{	// We're not restricting to a Goal
		$SQL->SELECT_add( ', COUNT(DISTINCT hit_remote_addr) as count' );
	}
	else
	{ // We ARE retsrticting to a Goal
		$SQL->SELECT_add( ', COUNT(DISTINCT goalhit_hit.hit_ID, T_hitlog.hit_remote_addr) as count' );
	}
	$vars = $DB->get_row( 'SELECT COUNT(keyp_ID) AS count, SUM(count) AS total
													FROM ('.$SQL->get().') AS dummy', OBJECT, 0, 'Count rows + total for stats' );
	$sql_count = (int)$vars->count;
	$total = (int)$vars->total;

	// DATA:
	$SQL->SELECT_add( ', keyp_phrase' );

	if( $split_engines )
	{
		$SQL->SELECT_add( ', dom_name, T_hitlog.hit_referer ' );
		$SQL->FROM_add( 'LEFT JOIN T_basedomains ON dom_ID = T_hitlog.hit_referer_dom_ID' );
		$SQL->ORDER_BY( '*, keyp_phrase, dom_name' );
	}
	else
	{
		$SQL->ORDER_BY( '*, keyp_phrase' );
	}
	$sql = $SQL->get();
}

// Create result set:
$Results = & new Results( $sql, '', $split_engines ? '--D' : '-D' , NULL, $sql_count );

$Results->title = T_('Keyphrases');

/**
 * Callback to add filters on top of the result set
 *
 * @param Form
 */
function filter_keyphrases( & $Form )
{
	global $current_User, $datestart, $datestop;

	$Form->date_input( 'datestartinput', $datestart, T_('From') );
	$Form->date_input( 'datestopinput', $datestop, T_('to') );

	if( $current_User->check_perm( 'stats', 'view' ) )
	{	// Permission to view stats for ALL blogs:
		global $goal_ID;
		$GoalCache = & get_Cache( 'GoalCache' );
		$GoalCache->load_all();
		$Form->select_object( 'goal_ID', $goal_ID, $GoalCache, T_('Goal'), '', true );
	}

	$Form->text_input( 'goal_name', get_param('goal_name'), 20, T_('Goal names starting with'), '', array( 'maxlength'=>50 ) );

 	$Form->checkbox_basic_input( 'split_engines', get_param('split_engines'), T_('Split engines') );
}
$today = date( 'Y-m-d', $localtimenow );
$Results->filter_area = array(
	'callback' => 'filter_keyphrases',
	'url_ignore' => 'goal_ID,datestartinput,datestart,datestopinput,datestop,goal_name,split_engines',
	'presets' => array(
		'all' => array( T_('All'), '?ctrl=stats&amp;tab=refsearches&amp;tab3=keywords&amp;blog='.$blog ),
		'today' => array( T_('Today'), '?ctrl=stats&amp;tab=refsearches&amp;tab3=keywords&amp;blog='.$blog
																	.'&amp;datestart='.$today.'&amp;datestop='.$today ),
		)
	);

if( $split_engines )
{	// Search engine:
	$Results->cols[] = array(
			'th' => T_('Search engine'),
			'order' => 'dom_name',
			'td_class' => 'nowrap',
			'td' => '<a href="$hit_referer$">$dom_name$</a>',
			'total' => T_('TOTAL'),
		);
}

// Keywords:
$Results->cols[] = array(
		'th' => T_('Search keywords'),
		'order' => 'keyp_phrase',
		'td' => '%stats_search_keywords( #keyp_phrase# )%',
		'total' => $sql_count.' '.T_('keyphrases'),
	);

// Count:
if( empty( $goal_ID ) )
{	// We're not restricting to a Goal
	$Results->cols[] = array(
			'th' => T_('Unique IP hits'),
			'order' => 'count',
			'default_dir' => 'D',
			'td_class' => 'right',
			'td' => '$count$',
			'total_class' => 'right',
			'total' => $total,
		);
}
else
{ // We ARE retsrticting to a Goal
	$Results->cols[] = array(
			'th' => T_('Goal hits'),
			'order' => 'count',
			'default_dir' => 'D',
			'td_class' => 'right',
			'td' => '$count$',
			'total_class' => 'right',
			'total' => $total,
		);

}

$Results->cols[] = array(
		'th' => '%',
		'order' => 'count',
		'default_dir' => 'D',
		'td_class' => 'right',
		'td' => '%percentage( #count#, '.$total.' )%',
		'total_class' => 'right',
		'total' => '100.0 %',
	);

$Results->cols[] = array(
		'th' => T_('Cumulative'),
		'td_class' => 'right',
		'td' => '%addup_percentage( #count#, '.$total.' )%',
	);

// Display results:
$Results->display();

/*
 * $Log$
 * Revision 1.2  2009/03/08 23:57:45  fplanque
 * 2009
 *
 * Revision 1.1  2008/05/26 19:30:39  fplanque
 * enhanced analytics
 *
 * Revision 1.5  2008/05/10 22:59:10  fplanque
 * keyphrase logging
 *
 * Revision 1.4  2008/02/19 11:11:18  fplanque
 * no message
 *
 * Revision 1.3  2008/02/14 02:19:52  fplanque
 * cleaned up stats
 *
 * Revision 1.2  2008/01/21 09:35:34  fplanque
 * (c) 2008
 *
 * Revision 1.1  2007/06/25 11:01:05  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.7  2007/04/26 00:11:13  fplanque
 * (c) 2007
 *
 * Revision 1.6  2007/03/20 09:53:26  fplanque
 * Letting boggers view their own stats.
 * + Letthing admins view the aggregate by default.
 *
 * Revision 1.5  2006/11/26 01:42:10  fplanque
 * doc
 */
?>