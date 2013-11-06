<?php
/**
 * This file implements the ItemQuery class.
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
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class( '_core/model/db/_sql.class.php', 'SQL' );

/**
 * ItemQuery: help constructing queries on Items
 * @package evocore
 */
class ItemQuery extends SQL
{
	var $p;
	var $pl;
	var $title;
	var $blog;
	var $cat;
	var $catsel;
	var $show_statuses;
	var $tags;
	var $author;
	var $assignees;
	var $statuses;
	var $types;
	var $dstart;
	var $dstop;
	var $timestamp_min;
	var $timestamp_max;
	var $keywords;
	var $phrase;
	var $exact;
	var $featured;


	/**
	 * Constructor.
	 *
	 * @param string Name of table in database
	 * @param string Prefix of fields in the table
	 * @param string Name of the ID field (including prefix)
	 */
	function ItemQuery( $dbtablename, $dbprefix = '', $dbIDname )
	{
		$this->dbtablename = $dbtablename;
		$this->dbprefix = $dbprefix;
		$this->dbIDname = $dbIDname;

		$this->FROM( $this->dbtablename );
	}


	/**
	 * Restrict to a specific post
	 */
	function where_ID( $p = '', $title = '' )
	{
		$r = false;

		$this->p = $p;
		$this->title = $title;

		// if a post number is specified, load that post
		if( !empty($p) )
		{
			if( substr( $this->p, 0, 1 ) == '-' )
			{	// Starts with MINUS sign:
				$eq_p = ' <> ';
				$this->p = substr( $this->p, 1 );
			}
			else
			{
				$eq_p = ' = ';
			}

			$this->WHERE_and( $this->dbIDname.$eq_p.intval($this->p) );
			$r = true;
		}

		// if a post urltitle is specified, load that post
		if( !empty( $title ) )
		{
			if( substr( $this->title, 0, 1 ) == '-' )
			{	// Starts with MINUS sign:
				$eq_title = ' <> ';
				$this->title = substr( $this->title, 1 );
			}
			else
			{
				$eq_title = ' = ';
			}

			global $DB;
			$this->WHERE_and( $this->dbprefix.'urltitle'.$eq_title.$DB->quote($this->title) );
			$r = true;
		}

		return $r;
	}


	/**
	 * Restrict to a specific list of posts
	 */
	function where_ID_list( $pl = '' )
	{
		$r = false;

		$this->pl = $pl;

		if( empty( $pl ) ) return $r; // nothing to do

		if( substr( $this->pl, 0, 1 ) == '-' )
		{	// List starts with MINUS sign:
			$eq = 'NOT IN';
			$this->pl = substr( $this->pl, 1 );
		}
		else
		{
			$eq = 'IN';
		}

		$p_ID_array = array();
		$p_id_list = explode( ',', $this->pl );
		foreach( $p_id_list as $p_id )
		{
			$p_ID_array[] = intval( $p_id );// make sure they're all numbers
		}

		$this->pl = implode( ',', $p_ID_array );

		$this->WHERE_and( $this->dbIDname.' '.$eq.'( '.$this->pl.' )' );
		$r = true;

		return $r;
	}


	/**
	 * Restrict to specific collection/chapters (blog/categories)
	 *
	 * @param integer
	 * @param string List of cats to restrict to
	 * @param array Array of cats to restrict to
	 */
	function where_chapter( $blog, $cat = '', $catsel = array() )
	{
		global $cat_array; // this is required for the cat_req() callback in compile_cat_array()

		$blog = intval($blog);	// Extra security

		// Save for future use (permission checks..)
		$this->blog = $blog;

		$this->FROM_add( 'INNER JOIN T_postcats ON '.$this->dbIDname.' = postcat_post_ID
											INNER JOIN T_categories ON postcat_cat_ID = cat_ID' );

		$BlogCache = & get_BlogCache();
		$current_Blog = $BlogCache->get_by_ID( $blog );

		$this->WHERE_and( $current_Blog->get_sql_where_aggregate_coll_IDs('cat_blog_ID') );


		$cat_array = NULL;
		$cat_modifier = NULL;

		// Compile the real category list to use:
		// TODO: allow to pass the compiled vars directly to this class
		compile_cat_array( $cat, $catsel, /* by ref */ $cat_array, /* by ref */ $cat_modifier, /* TODO $blog == 1 ? 0 : */ $blog );

		if( ! empty($cat_array) )
		{	// We want to restict to some cats:
			if( $cat_modifier == '-' )
			{
				$eq = 'NOT IN';
			}
			else
			{
				$eq = 'IN';
			}
			$whichcat = 'postcat_cat_ID '. $eq.' ('.implode(',', $cat_array). ') ';

			// echo $whichcat;
			$this->WHERE_and( $whichcat );

			if( $cat_modifier == '*' )
			{ // We want the categories combined! (i-e posts must be in ALL requested cats)
				$this->GROUP_BY( $this->dbIDname.' HAVING COUNT(postcat_cat_ID) = '.count($cat_array) );
			}
		}
	}


	/**
	 * Restrict to specific collection/chapters (blog/categories)
	 *
	 * @param Blog
	 * @param array
	 * @param string
	 * @param string 'wide' to search in extra cats too
	 *               'main' for main cat only
	 *               'extra' for extra cats only
	 */
	function where_chapter2( & $Blog, $cat_array, $cat_modifier, $cat_focus = 'wide' )
	{
		// Save for future use (permission checks..)
		$this->blog = $Blog->ID;
		$this->Blog = $Blog;
		$this->cat_array = $cat_array;
		$this->cat_modifier = $cat_modifier;

		if( $cat_focus == 'wide' || $cat_focus == 'extra' )
		{
			$sql_join_categories = ( $cat_focus == 'extra' ) ? ' AND post_main_cat_ID != cat_ID' : '';
			$this->FROM_add( 'INNER JOIN T_postcats ON '.$this->dbIDname.' = postcat_post_ID
												INNER JOIN T_categories ON postcat_cat_ID = cat_ID'.$sql_join_categories );
			// fp> we try to restrict as close as possible to the posts but I don't know if it matters
			$cat_ID_field = 'postcat_cat_ID';
		}
		else
		{
			$this->FROM_add( 'INNER JOIN T_categories ON post_main_cat_ID = cat_ID' );
			$cat_ID_field = 'post_main_cat_ID';
		}

		if( $cat_focus == 'main' )
		{ // We are requesting a narrow search
			$this->WHERE_and( 'cat_blog_ID = '.$Blog->ID );
		}
		else
		{
			$this->WHERE_and( $Blog->get_sql_where_aggregate_coll_IDs('cat_blog_ID') );
		}


		if( ! empty($cat_array) )
		{	// We want to restict to some cats:
			if( $cat_modifier == '-' )
			{
				$eq = 'NOT IN';
			}
			else
			{
				$eq = 'IN';
			}
			$whichcat = $cat_ID_field.' '.$eq.' ('.implode(',', $cat_array). ') ';

			// echo $whichcat;
			$this->WHERE_and( $whichcat );

			if( $cat_modifier == '*' )
			{ // We want the categories combined! (i-e posts must be in ALL requested cats)
				$this->GROUP_BY( $this->dbIDname.' HAVING COUNT('.$cat_ID_field.') = '.count($cat_array) );
			}
		}
	}


	/**
	 * Restrict to the visibility/sharing statuses we want to show
	 *
	 * @param array Restrict to these statuses
	 */
	function where_visibility( $show_statuses )
	{
		$this->show_statuses = $show_statuses;

		if( !isset( $this->blog ) )
		{
			debug_die( 'Status restriction requires to work with a specific blog first.' );
		}

		$this->WHERE_and( statuses_where_clause( $show_statuses, $this->dbprefix, $this->blog ) );
	}


	/**
	 * Restrict to the featured/non featured posts if requested
	 *
	 * @param boolean|NULL Restrict to featured
	 */
	function where_featured( $featured = NULL )
	{
		$this->featured = $featured;

		if( is_null( $this->featured ) )
		{ // no restriction
			return;
		}
		elseif( !empty( $this->featured ) )
		{ // restrict to featured
			$this->WHERE_and( $this->dbprefix.'featured <> 0' );
		}
		else
		{ // restrict to NON featured
			$this->WHERE_and( $this->dbprefix.'featured = 0' );
		}
	}


	/**
	 * Restrict to specific tags
	 *
	 * @param string List of tags to restrict to
	 */
	function where_tags( $tags )
	{
		global $DB;

		$this->tags = $tags;

		if( empty( $tags ) )
		{
			return;
		}

		$tags = explode( ',', $tags );

		$this->FROM_add( 'INNER JOIN T_items__itemtag ON post_ID = itag_itm_ID
											INNER JOIN T_items__tag ON (itag_tag_ID = tag_ID AND tag_name IN ('.$DB->quote($tags).') )' );
	}


	/**
	 * Restrict to specific authors
	 *
	 * @param string List of authors to restrict to (must have been previously validated)
	 */
	function where_author( $author )
	{
		$this->author = $author;

		if( empty( $author ) )
		{
			return;
		}

		if( substr( $author, 0, 1 ) == '-' )
		{	// List starts with MINUS sign:
			$eq = 'NOT IN';
			$author_list = substr( $author, 1 );
		}
		else
		{
			$eq = 'IN';
			$author_list = $author;
		}

		$this->WHERE_and( $this->dbprefix.'creator_user_ID '.$eq.' ('.$author_list.')' );
	}


	/**
	 * Restrict to specific assignees
	 *
	 * @param string List of assignees to restrict to (must have been previously validated)
	 */
	function where_assignees( $assignees )
	{
		$this->assignees = $assignees;

		if( empty( $assignees ) )
		{
			return;
		}

		if( $assignees == '-' )
		{	// List is ONLY a MINUS sign (we want only those not assigned)
			$this->WHERE_and( $this->dbprefix.'assigned_user_ID IS NULL' );
		}
		elseif( substr( $assignees, 0, 1 ) == '-' )
		{	// List starts with MINUS sign:
			$this->WHERE_and( '( '.$this->dbprefix.'assigned_user_ID IS NULL
			                  OR '.$this->dbprefix.'assigned_user_ID NOT IN ('.substr( $assignees, 1 ).') )' );
		}
		else
		{
			$this->WHERE_and( $this->dbprefix.'assigned_user_ID IN ('.$assignees.')' );
		}
	}


	/**
	 * Restrict to specific assignee or author
	 *
	 * @param integer assignee or author to restrict to (must have been previously validated)
	 */
	function where_author_assignee( $author_assignee )
	{
		$this->author_assignee = $author_assignee;

		if( empty( $author_assignee ) )
		{
			return;
		}

		$this->WHERE_and( '( '.$this->dbprefix.'creator_user_ID = '. $author_assignee.' OR '.
											$this->dbprefix.'assigned_user_ID = '.$author_assignee.' )' );
	}


	/**
	 * Restrict to specific locale
	 *
	 * @param string locale to restrict to ('all' if you don't want to restrict)
	 */
	function where_locale( $locale )
	{
		global $DB;

		if( $locale == 'all' )
		{
			return;
		}

		$this->WHERE_and( $this->dbprefix.'locale LIKE '.$DB->quote($locale.'%') );
	}


	/**
	 * Restrict to specific (exetnded) statuses
	 *
	 * @param string List of assignees to restrict to (must have been previously validated)
	 */
	function where_statuses( $statuses )
	{
		$this->statuses = $statuses;

		if( empty( $statuses ) )
		{
			return;
		}

		if( $statuses == '-' )
		{	// List is ONLY a MINUS sign (we want only those not assigned)
			$this->WHERE_and( $this->dbprefix.'pst_ID IS NULL' );
		}
		elseif( substr( $statuses, 0, 1 ) == '-' )
		{	// List starts with MINUS sign:
			$this->WHERE_and( '( '.$this->dbprefix.'pst_ID IS NULL
			                  OR '.$this->dbprefix.'pst_ID NOT IN ('.substr( $statuses, 1 ).') )' );
		}
		else
		{
			$this->WHERE_and( $this->dbprefix.'pst_ID IN ('.$statuses.')' );
		}
	}


	/**
	 * Restrict to specific item types
	 *
	 * @param string List of types to restrict to (must have been previously validated)
	 */
	function where_types( $types )
	{
		$this->types = $types;

		if( empty( $types ) )
		{
			return;
		}

		if( $types == '-' )
		{	// List is ONLY a MINUS sign (we want only those not assigned)
			$this->WHERE_and( $this->dbprefix.'ptyp_ID IS NULL' );
		}
		elseif( substr( $types, 0, 1 ) == '-' )
		{	// List starts with MINUS sign:
			$this->WHERE_and( '( '.$this->dbprefix.'ptyp_ID IS NULL
			                  OR '.$this->dbprefix.'ptyp_ID NOT IN ('.substr( $types, 1 ).') )' );
		}
		else
		{
			$this->WHERE_and( $this->dbprefix.'ptyp_ID IN ('.$types.')' );
		}
	}


	/**
	 * Restricts the datestart param to a specific date range.
	 *
	 * Start date gets restricted to minutes only (to make the query more
	 * cachable).
	 *
	 * Priorities:
	 *  -dstart and/or dstop
	 *  -week + m
	 *  -m
	 * @todo  -dstart + x days
	 * @see ItemList2::get_advertised_start_date()
	 *
	 * @param string YYYYMMDDHHMMSS (everything after YYYY is optional) or ''
	 * @param integer week number or ''
	 * @param string YYYYMMDDHHMMSS to start at, '' for first available
	 * @param string YYYYMMDDHHMMSS to stop at
	 * @param mixed Do not show posts before this timestamp, can be 'now'
	 * @param mixed Do not show posts after this timestamp, can be 'now'
	 */
	function where_datestart( $m = '', $w = '', $dstart = '', $dstop = '', $timestamp_min = '', $timestamp_max = 'now' )
	{
		global $time_difference;

		$this->m = $m;
		$this->w = $w;
		$this->dstart = $dstart;
		$this->dstop = $dstop;
		$this->timestamp_min = $timestamp_min;
		$this->timestamp_max = $timestamp_max;


		$start_is_set = false;
		$stop_is_set = false;


		// if a start date is specified in the querystring, crop anything before
		if( !empty($dstart) )
		{
			// Add trailing 0s: YYYYMMDDHHMMSS
			$dstart0 = $dstart.'00000000000000';  // TODO: this is NOT correct, should be 0101 for month

			// Start date in MySQL format: seconds get omitted (rounded to lower to minute for caching purposes)
			$dstart_mysql = substr($dstart0,0,4).'-'.substr($dstart0,4,2).'-'.substr($dstart0,6,2).' '
											.substr($dstart0,8,2).':'.substr($dstart0,10,2);

			$this->WHERE_and( $this->dbprefix.'datestart >= \''.$dstart_mysql.'\'
													OR ( '.$this->dbprefix.'datedeadline IS NULL AND '.$this->dbprefix.'datestart >= \''.$dstart_mysql.'\' )' );

			$start_is_set = true;
		}


		// if a stop date is specified in the querystring, crop anything before
		if( !empty($dstop) )
		{
			switch( strlen( $dstop ) )
			{
				case '4':
					// We have only year, add one to year
					$dstop_mysql = ($dstop+1).'-01-01 00:00:00';
					break;

				case '6':
					// We have year month, add one to month
					$dstop_mysql = date("Y-m-d H:i:s ", mktime(0, 0, 0, substr($dstop,4,2)+1, 01, substr($dstop,0,4)));
					break;

				case '8':
					// We have year mounth day, add one to day
					$dstop_mysql = date("Y-m-d H:i:s ", mktime(0, 0, 0, substr($dstop,4,2), (substr($dstop,6,2) + 1 ), substr($dstop,0,4)));
					break;

				case '10':
					// We have year mounth day hour, add one to hour
					$dstop_mysql = date("Y-m-d H:i:s ", mktime( ( substr($dstop,8,2) + 1 ), 0, 0, substr($dstop,4,2), substr($dstop,6,2), substr($dstop,0,4)));
					break;

				case '12':
					// We have year mounth day hour minute, add one to minute
					$dstop_mysql = date("Y-m-d H:i:s ", mktime( substr($dstop,8,2), ( substr($dstop,8,2) + 1 ), 0, substr($dstop,4,2), substr($dstop,6,2), substr($dstop,0,4)));
					break;

				default:
					// add one to second
					// Stop date in MySQL format: seconds get omitted (rounded to lower to minute for caching purposes)
					$dstop_mysql = substr($dstop,0,4).'-'.substr($dstop,4,2).'-'.substr($dstop,6,2).' '
											.substr($dstop,8,2).':'.substr($dstop,10,2);
			}

			$this->WHERE_and( $this->dbprefix.'datestart < \''.$dstop_mysql.'\'' ); // NOT <= comparator because we compare to the superior stop date

			$stop_is_set = true;
		}


		if( !$start_is_set || !$stop_is_set )
		{

			if( !is_null($w)  // Note: week # can be 0
					&& strlen($m) == 4 )
			{ // If a week number is specified (with a year)

				// Note: we use PHP to calculate week boundaries in order to handle weeks
				// that overlap 2 years properly, even when start on week is monday (which MYSQL won't handle properly)
				$start_date_for_week = get_start_date_for_week( $m, $w, locale_startofweek() );

				$this->WHERE_and( $this->dbprefix."datestart >= '".date('Y-m-d',$start_date_for_week)."'" );
				$this->WHERE_and( $this->dbprefix."datestart < '".date('Y-m-d',$start_date_for_week+604800 )."'" ); // + 7 days

				$start_is_set = true;
				$stop_is_set = true;
			}
			elseif( !empty($m) )
			{	// We want to restrict on an interval:
				$this->WHERE_and( 'EXTRACT(YEAR FROM '.$this->dbprefix.'datestart)='.intval(substr($m,0,4)) );
				if( strlen($m) > 5 )
					$this->WHERE_and( 'EXTRACT(MONTH FROM '.$this->dbprefix.'datestart)='.intval(substr($m,4,2)) );
				if( strlen($m) > 7 )
					$this->WHERE_and( 'EXTRACT(DAY FROM '.$this->dbprefix.'datestart)='.intval(substr($m,6,2)) );
				if( strlen($m) > 9 )
					$this->WHERE_and( 'EXTRACT(HOUR FROM '.$this->dbprefix.'datestart)='.intval(substr($m,8,2)) );
				if( strlen($m) > 11 )
					$this->WHERE_and( 'EXTRACT(MINUTE FROM '.$this->dbprefix.'datestart)='.intval(substr($m,10,2)) );
				if( strlen($m) > 13 )
					$this->WHERE_and( 'EXTRACT(SECOND FROM '.$this->dbprefix.'datestart)='.intval(substr($m,12,2)) );

				$start_is_set = true;
				$stop_is_set = true;
			}

		}


		// TODO: start + x days
		// TODO: stop - x days


		// SILENT limits!

		// Timestamp limits:
		if( $timestamp_min == 'now' )
		{
			// echo 'hide past';
			$timestamp_min = time();
		}
		if( !empty($timestamp_min) )
		{ // Hide posts before
			// echo 'hide before '.$timestamp_min;
			$date_min = remove_seconds( $timestamp_min + $time_difference );
			$this->WHERE_and( $this->dbprefix.'datestart >= \''. $date_min.'\'' );
		}

		if( $timestamp_max == 'now' )
		{
			// echo 'hide future';
			$timestamp_max = time();
		}
		if( !empty($timestamp_max) )
		{ // Hide posts after
			// echo 'after';
			$date_max = remove_seconds( $timestamp_max + $time_difference );
			$this->WHERE_and( $this->dbprefix.'datestart <= \''. $date_max.'\'' );
		}

	}


	/**
	 * Restricts creation date to a specific date range.
	 *
 	 * @param mixed Do not show posts CREATED after this timestamp
	 */
	function where_datecreated( $timestamp_created_max = 'now' )
	{
		global $time_difference;

		if( !empty($timestamp_created_max) )
		{ // Hide posts after
			// echo 'after';
			$date_max = date('Y-m-d H:i:s', $timestamp_created_max + $time_difference );
			$this->WHERE_and( $this->dbprefix.'datecreated <= \''. $date_max.'\'' );
		}

	}


	/**
	 * Restrict with keywords
	 *
	 * @param string Keyword search string
	 * @param mixed Search for entire phrase or for individual words
	 * @param mixed Require exact match of title or contents
	 */
	function where_keywords( $keywords, $phrase, $exact )
	{
		global $DB;

		$this->keywords = $keywords;
		$this->phrase = $phrase;
		$this->exact = $exact;

		if( empty($keywords) )
		{
			return;
		}

		$search = '';

		if( $exact )
		{	// We want exact match of title or contents
			$n = '';
		}
		else
		{ // The words/sentence are/is to be included in in the title or the contents
			$n = '%';
		}

		if( ($phrase == '1') or ($phrase == 'sentence') )
		{ // Sentence search
			$keywords = $DB->escape(trim($keywords));
			$search .= '('.$this->dbprefix.'title LIKE \''. $n. $keywords. $n. '\') OR ('.$this->dbprefix.'content LIKE \''. $n. $keywords. $n.'\')';
		}
		else
		{ // Word search
			if( strtoupper( $phrase ) == 'OR' )
				$swords = 'OR';
			else
				$swords = 'AND';

			// puts spaces instead of commas
			$keywords = preg_replace('/, +/', '', $keywords);
			$keywords = str_replace(',', ' ', $keywords);
			$keywords = str_replace('"', ' ', $keywords);
			$keywords = trim($keywords);
			$keyword_array = explode(' ',$keywords);
			$join = '';
			for ( $i = 0; $i < count($keyword_array); $i++)
			{
				$search .= ' '. $join. ' ( ('.$this->dbprefix.'title LIKE \''. $n. $DB->escape($keyword_array[$i]). $n. '\')
																OR ('.$this->dbprefix.'content LIKE \''. $n. $DB->escape($keyword_array[$i]). $n.'\') ) ';
				$join = $swords;
			}
		}

		//echo $search;
		$this->WHERE_and( $search );
	}


}


/*
 * $Log$
 * Revision 1.29  2013/11/06 08:04:15  efy-asimo
 * Update to version 5.0.1-alpha-5
 *
 */
?>