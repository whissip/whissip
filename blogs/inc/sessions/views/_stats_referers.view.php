<?php
/**
 * This file implements the UI view for the referer stats.
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


global $blog, $admin_url, $rsc_url, $AdminUI;

?>
<h2><?php echo T_('Refered browser hits') ?></h2>
<p class="notes"><?php echo T_('These are browser hits from external web pages refering to this blog') ?>.</p>
<?php
// Create result set:
$SQL = new SQL();
$SQL->SELECT( 'SQL_NO_CACHE hit_ID, hit_datetime, hit_referer, dom_name, hit_blog_ID, hit_uri, hit_remote_addr, blog_shortname' );
$SQL->FROM( 'T_hitlog INNER JOIN T_basedomains ON dom_ID = hit_referer_dom_ID'
	. ' INNER JOIN T_sessions ON hit_sess_ID = sess_ID'
	. ' LEFT JOIN T_blogs ON hit_blog_ID = blog_ID' );
$SQL->WHERE( 'hit_referer_type = "referer" AND hit_agent_type = "browser"' );
if( ! empty( $blog ) )
	$SQL->WHERE_and( 'hit_blog_ID = ' . $blog );
$Results = new Results( $SQL->get(), 'lstref_', 'D' );

$Results->title = T_('Refered browser hits');

// datetime:
$Results->cols[0] = array(
		'th' => T_('Date Time'),
		'order' => 'hit_ID', // This field is index, much faster than actually sorting on the datetime!
		'td_class' => 'timestamp',
		'td' => '%mysql2localedatetime_spans( \'$hit_datetime$\' )%',
	);

// Referer:
$Results->cols[1] = array(
		'th' => T_('Referer'),
		'order' => 'dom_name',
	);
if( $current_User->check_perm( 'stats', 'edit' ) )
{
	$Results->cols[1]['td'] = '<a href="%regenerate_url( \'action\', \'action=delete&amp;hit_ID=$hit_ID$\')%" title="'
			.T_('Delete this hit!').'">'.get_icon( 'delete' ).'</a> '

			.'<a href="%regenerate_url( \'action\', \'action=changetype&amp;new_hit_type=search&amp;hit_ID=$hit_ID$\')%" title="'
			.T_('Log as a search instead')
			.'"><img src="'.$rsc_url.'icons/magnifier.png" width="14" height="13" class="middle" alt="'
			./* TRANS: Abbrev. for "move to searches" (stats) */ T_('-&gt;S')
			.'" title="'.T_('Log as a search instead').'" /></a> '

			.'<a href="$hit_referer$" target="_blank">$dom_name$</a>';
}
else
{
	$Results->cols[1]['td'] = '<a href="$hit_referer$">$dom_name$</a>';
}

// Antispam:
if( $current_User->check_perm( 'spamblacklist', 'edit' ) )
{
	/**
	 * @uses get_ban_domain()
	 * @param string URL
	 * @return string Link to ban the URL
	 */
	function referer_ban_link( $uri )
	{
		return '<a href="?ctrl=antispam&amp;action=ban&amp;keyword='.rawurlencode( get_ban_domain( $uri ) ).'&amp;'.url_crumb('antispam')
				.'" title="'.T_('Ban this domain!').'">'.get_icon('ban').'</a>';
	}
	$Results->cols[] = array(
			'th' => /* TRANS: Abbrev. for Spam */ T_('S'),
			'td_class' => 'center',
			'td' => '%referer_ban_link( #hit_referer# )%', // we use hit_referer, because unlike dom_name it includes more subdomains, especially "www."
		);
}

// Target Blog:
if( empty($blog) )
{
	$Results->cols[] = array(
			'th' => T_('Target Blog'),
			'order' => 'hit_blog_ID',
			'td' => '$blog_shortname$',
		);
}

// Requested URI (linked to blog's baseurlroot+URI):
$Results->cols[] = array(
		'th' => T_('Requested URI'),
		'order' => 'hit_uri',
		'td' => '%stats_format_req_URI( #hit_blog_ID#, #hit_uri# )%',
	);

// Remote address (IP):
$Results->cols[] = array(
		'th' => T_('Remote IP'),
		'order' => 'hit_remote_addr',
		'td' => '% $GLOBALS[\'Plugins\']->get_trigger_event( \'FilterIpAddress\', $tmp_params = array(\'format\'=>\'htmlbody\', \'data\'=>\'$hit_remote_addr$\') ) %',
	);


// Display results:
$Results->display();

?>
<h3><?php echo T_('Top referers') ?>:</h3>

<?php
// TODO: re-use $Results from above
global $res_stats, $row_stats;
refererList( 30, 'global', 0, 0, "'referer'", 'dom_name', $blog, true );
if( count( $res_stats ) )
{
	$chart [ 'chart_data' ][ 0 ][ 0 ] = "";
	$chart [ 'chart_data' ][ 1 ][ 0 ] = 'Top referers'; // Needs UTF-8

	$count = 0;
	foreach( $res_stats as $row_stats )
	{
		if( $count < 9 )
		{
			$count++;
			$chart [ 'chart_data' ][ 0 ][ $count ] = stats_basedomain( false );
		}
		else
		{
			$chart [ 'chart_data' ][ 0 ][ $count ] = 'Others'; // Needs UTF-8
		}
		if( isset($chart [ 'chart_data' ][ 1 ][ $count ]) )
		{
			$chart['chart_data'][1][$count]	+= stats_hit_count( false );
		}
		else
		{
			$chart [ 'chart_data' ][ 1 ][ $count ] = stats_hit_count( false );
		}
	} // End stat loop

	// Include common chart properties:
	require dirname(__FILE__).'/inc/_pie_chart.inc.php';

	//pre_dump( $chart );
	echo '<div class="center">';
	load_funcs('_ext/_swfcharts.php');
	DrawChart( $chart );
	echo '</div>';

?>
<table class="grouped" cellspacing="0">
	<tr>
		<th class="firstcol"><?php echo T_('Referer') ?></th>
		<th><?php echo T_('Spam') ?></th>
		<th><?php echo T_('Hits') ?></th>
		<th class="lastcol"><?php echo /* xgettext:no-php-format */ T_('% of total') ?></th>
	</tr>
	<?php
	$count = 0;
	foreach( $res_stats as $row_stats )
	{
		?>
		<tr class="<?php echo( $count%2 ? 'odd' : 'even') ?>">
			<td class="firstcol"><a href="<?php stats_referer() ?>" target="_blank"><?php stats_basedomain() ?></a></td>
			<?php
			if( $current_User->check_perm( 'spamblacklist', 'edit' ) )
			{ // user can ban:
				echo '<td class="center">'.action_icon( T_('Ban this domain!'), 'ban', regenerate_url( 'ctrl,action,keyword', 'ctrl=antispam&amp;action=ban&amp;keyword='.rawurlencode( get_ban_domain($row_stats['hit_referer']) ).'&amp;'.url_crumb('antispam') ) ).'</td>'; // we use hit_referer, because unlike dom_name it includes subdomains (especially 'www.')
			}
			?>
			<td class="right"<?php
				if( $count < 8 )
				{
					echo ' style="background-color: #'.$chart['series_color'][$count].'"';
				}
			?>><?php stats_hit_count() ?></td>
			<td class="right"><?php stats_hit_percent() ?></td>
		</tr>
		<?php
		$count++;
	}
	?>
	<tr class="total">
		<td><?php echo T_('Total referers') ?></td>
		<td>&nbsp;</td>
		<td class="right"><?php stats_total_hit_count() ?></td>
		<td>&nbsp;</td>
	</tr>
</table>
<?php }


/*
 * $Log$
 * Revision 1.16  2010/10/23 08:27:17  sam2kb
 * Added missing antispam crumb
 *
 * Revision 1.15  2010/02/08 17:53:55  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.14  2010/01/30 18:55:34  blueyed
 * Fix "Assigning the return value of new by reference is deprecated" (PHP 5.3)
 *
 * Revision 1.13  2009/12/08 22:38:13  fplanque
 * User agent type is now saved directly into the hits table instead of a costly lookup in user agents table
 *
 * Revision 1.12  2009/09/25 13:09:36  efy-vyacheslav
 * Using the SQL class to prepare queries
 *
 * Revision 1.11  2009/09/13 21:26:50  blueyed
 * SQL_NO_CACHE for SELECT queries using T_hitlog
 *
 * Revision 1.10  2009/07/06 06:51:03  sam2kb
 * Added target="_blank" on referer URLs
 *
 * Revision 1.9  2009/04/11 23:45:47  tblue246
 * Fix translation problems
 *
 * Revision 1.8  2009/03/08 23:57:45  fplanque
 * 2009
 *
 * Revision 1.7  2009/02/27 22:57:26  blueyed
 * Use load_funcs for swfcharts, and especially only include it when needed (in the stats controllers only, not main.inc)
 *
 * Revision 1.6  2008/02/19 11:11:18  fplanque
 * no message
 *
 * Revision 1.5  2008/02/14 05:45:37  fplanque
 * cleaned up stats
 *
 * Revision 1.4  2008/02/14 02:19:52  fplanque
 * cleaned up stats
 *
 * Revision 1.3  2008/01/21 18:16:33  personman2
 * Different chart bg colors for each admin skin
 *
 * Revision 1.2  2008/01/21 09:35:33  fplanque
 * (c) 2008
 *
 * Revision 1.1  2007/06/25 11:01:04  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.5  2007/04/26 00:11:13  fplanque
 * (c) 2007
 *
 * Revision 1.4  2007/03/20 09:53:26  fplanque
 * Letting boggers view their own stats.
 * + Letthing admins view the aggregate by default.
 *
 * Revision 1.3  2006/11/24 18:27:26  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 */
?>