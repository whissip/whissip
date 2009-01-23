<?php
/**
 * This file implements the CommentList class.
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2008 by Francois PLANQUE - {@link http://fplanque.net/}.
 * Parts of this file are copyright (c)2004-2005 by Daniel HAHLER - {@link http://thequod.de/contact}.
 *
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
 *
 * {@internal Open Source relicensing agreement:
 * Daniel HAHLER grants Francois PLANQUE the right to license
 * Daniel HAHLER's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author blueyed: Daniel HAHLER.
 * @author fplanque: Francois PLANQUE
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class('_core/model/dataobjects/_dataobjectlist.class.php');

/**
 * CommentList Class
 *
 * @package evocore
 */
class CommentList extends DataObjectList
{
	/**
	 * Constructor
	 *
	 * @param Blog can pass NULL if $p is passed
	 * @param string
	 * @param array
	 * @param
	 * @param string Order ("ASC"/"DESC")
	 * @param string List of fields to order by (separated by " ")
	 * @param integer Limit
	 */
	function CommentList(
		$Blog,
		$comment_types = "'comment'",
		$show_statuses = array( 'published' ),	// Restrict to these statuses
		$p = '',															// Restrict to specific post
		$author = '',													// Not used yet
		$order = 'DESC',											// ASC or DESC
		$orderby = '',												// list of fields to order by
		$limit = '' 													// # of comments to display on the page
		)
	{
		global $DB;
		global $cache_categories;
		global $pagenow;		// Bleh !

		// Call parent constructor:
		parent::DataObjectList( 'T_comments', 'comment_', 'comment_ID', 'Item', NULL, $limit );

		$this->sql = 'SELECT DISTINCT T_comments.*
									FROM T_comments INNER JOIN T_items__item ON comment_post_ID = post_ID ';

		if( !empty( $p ) )
		{	// Restrict to comments on selected post
			$this->sql .= 'WHERE comment_post_ID = '.$p;
		}
		else
		{
			$this->sql .= 'INNER JOIN T_postcats ON post_ID = postcat_post_ID
										INNER JOIN T_categories othercats ON postcat_cat_ID = othercats.cat_ID ';

			$this->sql .= 'WHERE '.$Blog->get_sql_where_aggregate_coll_IDs('othercats.cat_blog_ID');
		}

		$this->sql .= ' AND comment_type IN ('.$comment_types.') ';

		/*
		 * ----------------------------------------------------
		 *  Restrict to the statuses we want to show:
		 * ----------------------------------------------------
		 */
		if( ! empty( $show_statuses ) )
		{
			$this->sql .= ' AND comment_status IN (\''.implode( "', '", $show_statuses ).'\')';
		}

		// This one restricts to post statuses, but it doesn't work completely right:
		// TODO: handle status dependencies with post
		$this->sql .= ' AND '.statuses_where_clause();


		// order by stuff
		if( (!empty($order)) && !in_array( strtoupper($order), array( 'ASC', 'DESC', 'RAND' ) ) )
		{
			$order='DESC';
		}

		if(empty($orderby))
		{
			$orderby = 'comment_date '.$order.', comment_ID '.$order;
		}
		else
		{
			$orderby_array = explode(' ',$orderby);
			$orderby = $orderby_array[0].' '.$order;
			if (count($orderby_array)>1)
			{
				for($i = 1; $i < (count($orderby_array)); $i++)
				{
					$orderby .= ', comment_'.$orderby_array[$i].' '.$order;
				}
			}
		}

		if( $order == 'RAND' ) $orderby = 'RAND()';

		$this->sql .= "ORDER BY $orderby";
		if( !empty( $this->limit ) )
		{
			$this->sql .= ' LIMIT '.$this->limit;
		}

		// echo $this->sql;

		$this->rows = $DB->get_results( $this->sql, ARRAY_A );

		// Prebuild and cache objects:
		if( $this->result_num_rows = $DB->num_rows )
		{	// fplanque>> why this test??

			$i = 0;
			foreach( $this->rows as $row )
			{
				// Prebuild object:
				$this->Obj[$i] = new Comment( $row ); // COPY (function)

				// To avoid potential future waste, cache this object:
				// $this->DataObjectCache->add( $this->Obj[$i] );

				$i++;
			}
		}
	}


	/**
	 * Template function: display message if list is empty
	 *
	 * @return boolean true if empty
	 */
	function display_if_empty( $params = array() )
	{
		// Make sure we are not missing any param:
		$params = array_merge( array(
				'msg_empty'   => T_('No comment yet...'),
			), $params );

		return parent::display_if_empty( $params );
	}

}

/*
 * $Log$
 * Revision 1.8  2009/01/23 00:05:24  blueyed
 * Add Blog::get_sql_where_aggregate_coll_IDs, which adds support for '*' in list of aggregated blogs.
 *
 * Revision 1.7  2008/09/24 08:46:45  fplanque
 * Fixed random order
 *
 * Revision 1.6  2008/01/21 09:35:27  fplanque
 * (c) 2008
 *
 * Revision 1.5  2008/01/10 19:56:58  fplanque
 * moved to v-3-0
 *
 * Revision 1.4  2008/01/09 00:25:51  blueyed
 * Vastly improve performance in CommentList for large number of comments:
 * - add index comment_date_ID; and force it in the SQL (falling back to comment_date)
 *
 * Revision 1.3  2007/12/24 10:36:07  yabs
 * adding random order
 *
 * Revision 1.2  2007/11/03 21:04:26  fplanque
 * skin cleanup
 *
 * Revision 1.1  2007/06/25 10:59:42  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.9  2007/06/20 23:00:14  blueyed
 * doc fixes
 *
 * Revision 1.8  2007/05/14 02:43:04  fplanque
 * Started renaming tables. There probably won't be a better time than 2.0.
 *
 * Revision 1.7  2007/04/26 00:11:08  fplanque
 * (c) 2007
 *
 * Revision 1.6  2006/12/17 23:42:38  fplanque
 * Removed special behavior of blog #1. Any blog can now aggregate any other combination of blogs.
 * Look into Advanced Settings for the aggregating blog.
 * There may be side effects and new bugs created by this. Please report them :]
 *
 * Revision 1.5  2006/07/04 17:32:29  fplanque
 * no message
 *
 * Revision 1.4  2006/04/20 16:31:30  fplanque
 * comment moderation (finished for 1.8)
 *
 * Revision 1.3  2006/04/18 19:29:51  fplanque
 * basic comment status implementation
 *
 * Revision 1.2  2006/03/12 23:08:58  fplanque
 * doc cleanup
 *
 * Revision 1.1  2006/02/23 21:11:57  fplanque
 * File reorganization to MVC (Model View Controller) architecture.
 * See index.hml files in folders.
 * (Sorry for all the remaining bugs induced by the reorg... :/)
 *
 * Revision 1.14  2005/12/19 19:30:14  fplanque
 * minor
 *
 * Revision 1.13  2005/12/12 19:21:21  fplanque
 * big merge; lots of small mods; hope I didn't make to many mistakes :]
 *
 * Revision 1.12  2005/11/21 20:37:39  fplanque
 * Finished RSS skins; turned old call files into stubs.
 *
 * Revision 1.11  2005/11/18 22:05:41  fplanque
 * no message
 *
 * Revision 1.10  2005/10/03 18:10:07  fplanque
 * renamed post_ID field
 *
 * Revision 1.9  2005/09/06 17:13:54  fplanque
 * stop processing early if referer spam has been detected
 *
 * Revision 1.8  2005/08/25 16:06:45  fplanque
 * Isolated compilation of categories to use in an ItemList.
 * This was one of the oldest bugs on the list! :>
 *
 * Revision 1.7  2005/04/07 17:55:50  fplanque
 * minor changes
 *
 * Revision 1.6  2005/03/14 20:22:19  fplanque
 * refactoring, some cacheing optimization
 *
 * Revision 1.5  2005/03/09 20:29:39  fplanque
 * added 'unit' param to allow choice between displaying x days or x posts
 * deprecated 'paged' mode (ultimately, everything should be pageable)
 *
 * Revision 1.4  2005/03/06 16:30:40  blueyed
 * deprecated global table names.
 *
 * Revision 1.3  2005/02/28 09:06:32  blueyed
 * removed constants for DB config (allows to override it from _config_TEST.php), introduced EVO_CONFIG_LOADED
 *
 * Revision 1.2  2004/10/14 18:31:25  blueyed
 * granting copyright
 *
 * Revision 1.1  2004/10/13 22:46:32  fplanque
 * renamed [b2]evocore/*
 *
 * Revision 1.20  2004/10/11 19:13:14  fplanque
 * Edited code documentation.
 *
 */
?>