<?php
/**
 * This file implements functions for logging of hits and extracting stats.
 *
 * NOTE: the refererList() and stats_* functions are not fully functional ATM. I'll transform them into the Hitlog object during the next days. blueyed.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}
 * Parts of this file are copyright (c)2004-2006 by Daniel HAHLER - {@link http://thequod.de/contact}.
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
 * Daniel HAHLER grants Francois PLANQUE the right to license
 * Daniel HAHLER's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * {@internal Origin:
 * This file was inspired by N C Young's Referer Script released in
 * the public domain on 07/19/2002. {@link http://ncyoung.com/entry/57}.
 * See also {@link http://ncyoung.com/demo/referer/}.
 * }}
 *
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author N C Young (nathan@ncyoung.com).
 * @author blueyed: Daniel HAHLER.
 * @author fplanque: Francois PLANQUE.
 * @author vegarg: Vegar BERG GULDAL.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );



/**
 * @todo Transform to make this a stub for {@link $Hitlist}
 *
 * Extract stats
 */
function refererList(
	$howMany = 5,
	$visitURL = '',
	$disp_blog = 0,
	$disp_uri = 0,
	$type = "'referer'",		// was: 'referer' normal refer, 'invalid', 'badchar', 'blacklist', 'rss', 'robot', 'search'
													// new: 'search', 'blacklist', 'referer', 'direct', ('spam' but spam is not logged)
	$groupby = '', 	// dom_name
	$blog_ID = '',
	$get_total_hits = false, // Get total number of hits (needed for percentages)
	$get_user_agent = false ) // Get the user agent
{
	global $DB, $res_stats, $stats_total_hits, $ReqURI;

	if( strpos( $type, "'" ) !== 0 )
	{ // no quote at position 0
		$type = "'".$type."'";
	}

	//if no visitURL, will show links to current page.
	//if url given, will show links to that page.
	//if url="global" will show links to all pages
	if (!$visitURL)
	{
		$visitURL = $ReqURI;
	}

	if( $groupby == '' )
	{ // No grouping:
		$sql = 'SELECT hit_ID, UNIX_TIMESTAMP(hit_datetime) AS hit_datetime, hit_referer, dom_name';
	}
	else
	{ // group by
		if( $groupby == 'baseDomain' )
		{ // compatibility HACK!
			$groupby = 'dom_name';
		}
		$sql = 'SELECT COUNT(*) AS totalHits, hit_referer, dom_name';
	}
	if( $disp_blog )
	{
		$sql .= ', hit_blog_ID';
	}
	if( $disp_uri )
	{
		$sql .= ', hit_uri';
	}
	if( $get_user_agent )
	{
		$sql .= ', agnt_signature';
	}

	$sql_from_where = "
			  FROM T_hitlog LEFT JOIN T_basedomains ON dom_ID = hit_referer_dom_ID
			 WHERE hit_referer_type IN (".$type.")
			   AND hit_agent_type = 'browser'";
	if( !empty($blog_ID) )
	{
		$sql_from_where .= " AND hit_blog_ID = '".$blog_ID."'";
	}
	if ( $visitURL != 'global' )
	{
		$sql_from_where .= " AND hit_uri = '".$DB->escape($visitURL, 0, 250)."'";
	}

	$sql .= $sql_from_where;

	if( $groupby == '' )
	{ // No grouping:
		$sql .= ' ORDER BY hit_ID DESC';
	}
	else
	{ // group by
		$sql .= " GROUP BY ".$groupby." ORDER BY totalHits DESC";
	}
	$sql .= ' LIMIT '.$howMany;

	$res_stats = $DB->get_results( $sql, ARRAY_A );

	if( $get_total_hits )
	{ // we need to get total hits
		$sql = 'SELECT COUNT(*) '.$sql_from_where;
		$stats_total_hits = $DB->get_var( $sql );
	}
	else
	{ // we're not getting total hits
		$stats_total_hits = 1;		// just in case some tries a percentage anyway (avoid div by 0)
	}

}


/*
 * stats_hit_ID(-)
 */
function stats_hit_ID()
{
	global $row_stats;
	echo $row_stats['visitID'];
}

/*
 * stats_hit_remote_addr(-)
 */
function stats_hit_remote_addr()
{
	global $row_stats;
	echo $row_stats['hit_remote_addr'];
}

/*
 * stats_time(-)
 */
function stats_time( $format = '' )
{
	global $row_stats;
	if( $format == '' )
		$format = locale_datefmt().' '.locale_timefmt();
	echo date_i18n( $format, $row_stats['hit_datetime'] );
}


/*
 * stats_total_hit_count(-)
 */
function stats_total_hit_count()
{
	global $stats_total_hits;
	echo $stats_total_hits;
}


/*
 * stats_hit_count(-)
 */
function stats_hit_count( $disp = true )
{
	global $row_stats;
	if( $disp )
		echo $row_stats['totalHits'];
	else
		return $row_stats['totalHits'];
}


/*
 * stats_hit_percent(-)
 */
function stats_hit_percent(
	$decimals = 1,
	$dec_point = '.' )
{
	global $row_stats, $stats_total_hits;
	$percent = $row_stats['totalHits'] * 100 / $stats_total_hits;
	echo number_format( $percent, $decimals, $dec_point, '' ).'&nbsp;%';
}


/*
 * stats_blog_ID(-)
 */
function stats_blog_ID()
{
	global $row_stats;
	echo $row_stats['hit_blog_ID'];
}


/*
 * stats_blog_name(-)
 */
function stats_blog_name()
{
	global $row_stats;

	$BlogCache = & get_BlogCache();
	$Blog = & $BlogCache->get_by_ID($row_stats['hit_blog_ID']);

	$Blog->disp('name');
}


/*
 * stats_referer(-)
 */
function stats_referer( $before='', $after='', $disp_ref = true )
{
	global $row_stats;
	$ref = trim($row_stats['hit_referer']);
	if( strlen($ref) > 0 )
	{
		echo $before;
		if( $disp_ref ) echo utf8_htmlspecialchars($ref);
		echo $after;
	}
}


/*
 * stats_basedomain(-)
 */
function stats_basedomain( $disp = true )
{
	global $row_stats;
	if( $disp )
		echo utf8_htmlspecialchars($row_stats['dom_name']);
	else
		return $row_stats['dom_name'];
}


/**
 * Displays keywords used for search leading to this page
 */
function stats_search_keywords( $keyphrase, $length = 45 )
{
	if( empty( $keyphrase ) )
	{
		return '<span class="note">['.T_('n.a.').']</span>';
	}

	// Save original string
	$keyphrase_orig = $keyphrase;

	$keyphrase = strmaxlen($keyphrase, $length, '...', 'raw');

	
	return '<span title="'.format_to_output( $keyphrase_orig, 'htmlattr' ).'">'.$keyphrase.'</span>';
}



/*
 * $Log$
 * Revision 1.21  2010/02/08 17:53:55  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.20  2009/12/08 22:38:13  fplanque
 * User agent type is now saved directly into the hits table instead of a costly lookup in user agents table
 *
 * Revision 1.19  2009/10/03 20:43:40  tblue246
 * Commit message cleanup...
 *
 * Revision 1.18  2009/10/03 20:07:51  tblue246
 * - Hit::detect_user_agent():
 * 	- Try to use get_browser() to get platform information or detect robots if "normal" detection failed.
 * 	- Use Skin::type to detect RSS readers.
 * - Removed unneeded functions.
 * - translate_user_agent(): Use get_browser() if translation failed.
 *
 * Revision 1.17  2009/09/27 12:57:29  blueyed
 * strmaxlen: add format param, which is used on the (possibly) cropped string.
 *
 * Revision 1.16  2009/09/25 07:33:14  efy-cantor
 * replace get_cache to get_*cache
 *
 * Revision 1.15  2009/07/23 21:28:03  blueyed
 * Add $tail param to strmaxlen, use it in stats_search_keywords and add some tests for it.
 *
 * Revision 1.14  2009/07/09 00:11:18  fplanque
 * minor
 *
 * Revision 1.13  2009/07/08 02:38:55  sam2kb
 * Replaced strlen & substr with their mbstring wrappers evo_strlen & evo_substr when needed
 *
 * Revision 1.12  2009/07/08 01:45:48  sam2kb
 * Added param $length to stats_search_keywords()
 * Changed keywords length for better accessibility on low resolution screens
 *
 * Revision 1.11  2009/07/08 00:52:27  sam2kb
 * stats_search_keywords() returns original (not cropped) string on hover
 *
 * Revision 1.10  2009/03/08 23:57:45  fplanque
 * 2009
 *
 * Revision 1.9  2009/03/03 01:27:45  fplanque
 * I made PHP 4.3.2 the minimum requirement
 *
 * Revision 1.8  2009/02/24 23:02:29  blueyed
 * TODO/NOTE about conditional usage of mb_substr
 *
 * Revision 1.7  2009/02/24 13:21:35  tblue246
 * Minor
 *
 * Revision 1.6  2009/02/24 04:28:34  sam2kb
 * Convert keywords encoding and use 'mb_substr' to crop the string,
 * see http://forums.b2evolution.net/viewtopic.php?t=17431
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
 * Revision 1.2  2008/01/21 09:35:33  fplanque
 * (c) 2008
 *
 * Revision 1.1  2007/06/25 11:00:59  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.12  2007/04/26 00:11:11  fplanque
 * (c) 2007
 *
 * Revision 1.11  2007/02/10 18:00:34  waltercruz
 * Changing double quotes to single quotes
 *
 * Revision 1.10  2006/11/24 18:27:24  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.9  2006/10/10 19:26:24  blueyed
 * Use BlogCache instead "blogparams"
 *
 * Revision 1.8  2006/10/06 21:54:16  blueyed
 * Fixed hit_uri handling, especially in strict mode
 */
?>
