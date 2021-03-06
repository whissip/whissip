<?php
/**
 * This file implements the ItemList class 2.
 *
 * This is the object handling item/post/article lists.
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
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class( '/items/model/_itemlistlight.class.php', 'ItemListLight' );


/**
 * Item List Class 2
 *
 * This SECOND implementation will deprecate the first one when finished.
 *
 * @package evocore
 */
class ItemList2 extends ItemListLight
{
	/**
	 * @var array
	 */
	var $prevnext_Item = array();

	/**
	 * Constructor
	 *
	 * @todo  add param for saved session filter set
	 *
	 * @param Blog
	 * @param mixed Default filter set: Do not show posts before this timestamp, can be 'now'
	 * @param mixed Default filter set: Do not show posts after this timestamp, can be 'now'
	 * @param integer|NULL Limit
	 * @param string name of cache to be used
	 * @param string prefix to differentiate page/order params when multiple Results appear one same page
	 * @param string Name to be used when saving the filterset (leave empty to use default for collection)
	 * @param array restrictions for itemlist (position, contact, firm, ...) key: restriction name, value: ID of the restriction
	 */
	function ItemList2(
			& $Blog,
			$timestamp_min = NULL,       // Do not show posts before this timestamp
			$timestamp_max = NULL,   		 // Do not show posts after this timestamp
			$limit = 20,
			$cache_name = 'ItemCache',	 // name of cache to be used
			$param_prefix = '',
			$filterset_name = ''				// Name to be used when saving the filterset (leave empty to use default for collection)
		)
	{
		global $Settings;

		// Call parent constructor:
		parent::ItemListLight( $Blog, $timestamp_min, $timestamp_max, $limit, $cache_name, $param_prefix, $filterset_name );
	}


	/**
	 * We want to preview a single post, we are going to fake a lot of things...
	 */
	function preview_from_request()
	{
		global $current_User;

		if( empty($current_User) )
		{ // dh> only logged in user's can preview. Alternatively we need those checks where $current_User gets used below.
			return;
		}

		global $DB, $localtimenow, $Messages, $BlogCache;
		global $Plugins;

		$preview_userid = param( 'preview_userid', 'integer', true );
		$post_status = param( 'post_status', 'string', true );
		$post_locale = param( 'post_locale', 'string', $current_User->locale );
		$content = param( 'content', 'html', true );
		$post_title = param( 'post_title', 'html', true );
		$post_titletag = param( 'titletag', 'string', true );
		$post_metadesc = param( 'metadesc', 'string', true );
		$post_metakeywords = param( 'metakeywords', 'string', true );
		$post_excerpt = param( 'post_excerpt', 'string', true );
		$post_url = param( 'post_url', 'string', '' );
		check_categories_nosave( $post_category, $post_extracats );
		$post_views = param( 'post_views', 'integer', 0 );
		$renderers = param( 'renderers', 'array', array('default') );
		if( ! is_array($renderers) )
		{ // dh> workaround for param() bug. See rev 1.93 of /inc/_misc/_misc.funcs.php
			$renderers = array('default');
		}
		if( $post_category == 0 )
		{
			$post_category = $this->Blog->get_default_cat_ID();
		}
		$comment_Blog = & $BlogCache->get_by_ID( get_catblog( $post_category ) );
		if( ( $comment_Blog->get_setting( 'allow_comments' ) != 'never' ) && ( $comment_Blog->get_setting( 'disable_comments_bypost' ) ) )
		{ // param is required
			$post_comment_status = param( 'post_comment_status', 'string', true );
		}
		else
		{
			$post_comment_status = $comment_Blog->get_setting( 'allow_comments' );
		}


		// Get issue date, using the user's locale (because it's entered like this in the form):
		locale_temp_switch( $current_User->locale );

		param_date( 'item_issue_date', T_('Please enter a valid issue date.'), false );
		// TODO: dh> get_param() is always true here, also on invalid dates:
		if( strlen(get_param('item_issue_date')) )
		{ // only set it, if a date was given:
			param_time( 'item_issue_time' );
			$item_issue_date = form_date( get_param( 'item_issue_date' ), get_param( 'item_issue_time' ) ); // TODO: cleanup...
		}
		else
		{
			$item_issue_date = date( 'Y-m-d H:i:s', $localtimenow );
		}
		locale_restore_previous();


		if( !($item_typ_ID = param( 'item_typ_ID', 'integer', NULL )) )
			$item_typ_ID = NULL;
		if( !($item_st_ID = param( 'item_st_ID', 'integer', NULL )) )
			$item_st_ID = NULL;
		if( !($item_assigned_user_ID = param( 'item_assigned_user_ID', 'integer', NULL )) )
			$item_assigned_user_ID = NULL;
		if( !($item_deadline = param( 'item_deadline', 'string', NULL )) )
			$item_deadline = NULL;
		$item_priority = param( 'item_priority', 'integer', NULL ); // QUESTION: can this be also empty/NULL?

		// Do some optional filtering on the content
		// Typically stuff that will help the content to validate
		// Useful for code display.
		// Will probably be used for validation also.
		$Plugins_admin = & get_Plugins_admin();
		$Plugins_admin->filter_contents( $post_title /* by ref */, $content /* by ref */, $renderers );

		$post_title = format_to_post( $post_title );
		$content = format_to_post( $content );

		$post_ID = param('post_ID', 'integer', 0);

		$this->sql = "SELECT
			$post_ID AS post_ID,
			$preview_userid AS post_creator_user_ID,
			$preview_userid AS post_lastedit_user_ID,
			'$item_issue_date' AS post_datestart,
			'$item_issue_date' AS post_dateset,
			'$item_issue_date' AS post_datecreated,
			'$item_issue_date' AS post_datemodified,
			0 AS post_dateset,
			'".$DB->escape($post_status)."' AS post_status,
			'".$DB->escape($post_locale)."' AS post_locale,
			'".$DB->escape($content)."' AS post_content,
			'".$DB->escape($post_title)."' AS post_title,
			'".$DB->escape($post_titletag)."' AS post_titletag,
			'".$DB->escape($post_metadesc)."' AS post_metadesc,
			'".$DB->escape($post_metakeywords)."' AS post_metakeywords,
			'".$DB->escape($post_excerpt)."' AS post_excerpt,
			NULL AS post_excerpt_autogenerated,
			NULL AS post_urltitle,
			NULL AS post_canonical_slug_ID,
			NULL AS post_tiny_slug_ID,
			'".$DB->escape($post_url)."' AS post_url,
			$post_category AS post_main_cat_ID,
			$post_views AS post_views,
			'' AS post_flags,
			'noreq' AS post_notifications_status,
			NULL AS post_notifications_ctsk_ID,
			".bpost_count_words( $content )." AS post_wordcount,
			".$DB->quote($post_comment_status)." AS post_comment_status,
			'".$DB->escape( implode( '.', $renderers ) )."' AS post_renderers,
			".$DB->quote($item_assigned_user_ID)." AS post_assigned_user_ID,
			".$DB->quote($item_typ_ID)." AS post_ptyp_ID,
			".$DB->quote($item_st_ID)." AS post_pst_ID,
			".$DB->quote($item_deadline)." AS post_datedeadline,
			".$DB->quote($item_priority)." AS post_priority,
			NULL AS post_editor_code,";

		// CUSTOM FIELDS double
		for( $i = 1 ; $i <= 5; $i++ )
		{	// For each custom double field:
			$this->sql .= $DB->quote(param( 'item_double'.$i, 'double', NULL )).' AS post_double'.$i.",\n";
		}

		// CUSTOM FIELDS varchar
		for( $i = 1 ; $i <= 3; $i++ )
		{	// For each custom varchar field:
			$this->sql .= $DB->quote(param( 'item_varchar'.$i, 'string', '' )).' AS post_varchar'.$i.",\n";
		}

		$this->sql .= $DB->quote(param( 'item_order', 'double', NULL )).' AS post_order'.",\n"
								.$DB->quote(param( 'item_featured', 'integer', NULL )).' AS post_featured'."\n";
		$this->total_rows = 1;
		$this->total_pages = 1;
		$this->page = 1;

		// ATTENTION: we skip the parent on purpose here!! fp> refactor
		DataObjectList2::query( false, false, false, 'PREVIEW QUERY' );

		$Item = & $this->Cache->instantiate( $this->rows[0] );

		// Trigger plugin event, allowing to manipulate or validate the item before it gets previewed
		$Plugins->trigger_event( 'AppendItemPreviewTransact', array( 'Item' => & $Item ) );

		if( $Messages->has_errors() )
		{
			$errcontent = $Messages->display( T_('Invalid post, please correct these errors:'), '', false );
			$Item->content = $errcontent."\n<hr />\n".$content;
		}

		// little funky fix for IEwin, rawk on that code
		global $Hit;
		if( ($Hit->is_winIE()) && (!isset($IEWin_bookmarklet_fix)) )
		{ // QUESTION: Is this still needed? What about $IEWin_bookmarklet_fix? (blueyed)
			$Item->content = preg_replace('/\%u([0-9A-F]{4,4})/e', "'&#'.base_convert('\\1',16,10). ';'", $Item->content);
		}
	}


	/**
	 * Run Query: GET DATA ROWS *** HEAVY ***
	 */
	function query()
	{
		global $DB;

		if( !is_null( $this->rows ) )
		{ // Query has already executed:
			return;
		}

		// INIT THE QUERY:
		$this->query_init();

		// Results style orders:
		// $this->ItemQuery->ORDER_BY_prepend( $this->get_order_field_list() );


		// We are going to proceed in two steps (we simulate a subquery)
		// 1) we get the IDs we need
		// 2) we get all the other fields matching these IDs
		// This is more efficient than manipulating all fields at once.

		// *** STEP 1 ***
		// walter> Accordding to the standart, to DISTINCT queries, all columns used
		// in ORDER BY must appear in the query. This make que query work with PostgreSQL and
		// other databases.
		// fp> That can dramatically fatten the returned data. You must handle this in the postgres class (check that order fields are in select)
		$step1_sql = 'SELECT DISTINCT '.$this->Cache->dbIDname // .', '.implode( ', ', $order_cols_to_select )
									.$this->ItemQuery->get_from()
									.$this->ItemQuery->get_where()
									.$this->ItemQuery->get_group_by()
									.$this->ItemQuery->get_order_by()
									.$this->ItemQuery->get_limit();

		// echo $DB->format_query( $step1_sql );

		// Get list of the IDs we need:
		$ID_list = implode( ',', $DB->get_col( $step1_sql, 0, 'ItemList2::Query() Step 1: Get ID list' ) );

		// *** STEP 2 ***
		$this->sql = 'SELECT *
			              FROM '.$this->Cache->dbtablename;
		if( !empty($ID_list) )
		{
			$this->sql .= ' WHERE '.$this->Cache->dbIDname.' IN ('.$ID_list.') '
										.$this->ItemQuery->get_order_by();
		}
		else
		{
			$this->sql .= ' WHERE 0';
		}

		//echo $DB->format_query( $this->sql );

		// ATTENTION: we skip the parent on purpose here!! fp> refactor
		DataObjectList2::query( false, false, false, 'ItemList2::Query() Step 2' );
	}


	/**
	 * If the list is sorted by category...
 	 *
 	 * This is basically just a stub for backward compatibility
	 */
	function & get_item()
	{
		if( $this->group_by_cat == 1 )
		{	// This is the first call to get_item() after get_category_group()
			$this->group_by_cat = 2;
			// Return the object we already got in get_category_group():
			return $this->current_Obj;
		}

		$Item = & parent::get_next();

		if( !empty($Item) && $this->group_by_cat == 2 && $Item->main_cat_ID != $this->main_cat_ID )
		{	// We have just hit a new category!
			$this->group_by_cat == 0; // For info only.
			$r = false;
			return $r;
		}

		//pre_dump( $Item );

		return $Item;
	}


	/**
	 * Get all tags used in current ItemList
	 *
	 * @todo caching in case of multiple calls
	 *
	 * @return array
	 */
	function get_all_tags()
	{
		$all_tags = array();

		for( $i=0; $i<$this->result_num_rows; $i++ )
		{
			/**
			 * @var Item
			 */
			$l_Item = & $this->get_by_idx( $i );
			$l_tags = $l_Item->get_tags();
			$all_tags = array_merge( $all_tags, $l_tags );
		}

		// Keep each tag only once:
		$all_tags = array_unique( $all_tags );

		return $all_tags;
	}



	/**
	 * Returns values needed to make sort links for a given column
	 * Needed because the order is not handled by the result class.
	 * Reason: Sometimes the item list needs to be ordered without having a display table, and columns. The result class order is based on columns.
	 *
	 * Returns an array containing the following values:
	 *  - current_order : 'ASC', 'DESC' or ''
	 *  - order_asc : url needed to order in ascending order
	 *  - order_desc
	 *  - order_toggle : url needed to toggle sort order
	 *
	 * @param integer column to sort
	 * @return array
	 */
	function get_col_sort_values( $col_idx )
	{
		$col_order_fields = $this->cols[$col_idx]['order'];

		// pre_dump( $col_order_fields, $this->filters['orderby'], $this->filters['order'] );

		// Current order:
		if( $this->filters['orderby'] == $col_order_fields )
		{
			$col_sort_values['current_order'] = $this->filters['order'];
		}
		else
		{
			$col_sort_values['current_order'] = '';
		}


		// Generate sort values to use for sorting on the current column:
		$col_sort_values['order_asc'] = regenerate_url( array($this->param_prefix.'order',$this->param_prefix.'orderby'),
																			$this->param_prefix.'order=ASC&amp;'.$this->param_prefix.'orderby='.$col_order_fields );
		$col_sort_values['order_desc'] = regenerate_url(  array($this->param_prefix.'order',$this->param_prefix.'orderby'),
																			$this->param_prefix.'order=DESC&amp;'.$this->param_prefix.'orderby='.$col_order_fields );

		if( !$col_sort_values['current_order'] && isset( $this->cols[$col_idx]['default_dir'] ) )
		{	// There is no current order on this column and a default order direction is set for it
			// So set a default order direction for it

			if( $this->cols[$col_idx]['default_dir'] == 'A' )
			{	// The default order direction is A, so set its toogle  order to the order_asc
				$col_sort_values['order_toggle'] = $col_sort_values['order_asc'];
			}
			else
			{ // The default order direction is A, so set its toogle order to the order_desc
				$col_sort_values['order_toggle'] = $col_sort_values['order_desc'];
			}
		}
		elseif( $col_sort_values['current_order'] == 'ASC' )
		{	// There is an ASC current order on this column, so set its toogle order to the order_desc
			$col_sort_values['order_toggle'] = $col_sort_values['order_desc'];
		}
		else
		{ // There is a DESC or NO current order on this column,  so set its toogle order to the order_asc
			$col_sort_values['order_toggle'] = $col_sort_values['order_asc'];
		}

		// pre_dump( $col_sort_values );

		return $col_sort_values;
	}



	/**
	 * Link to previous and next link in collection
	 */
	function prevnext_item_links( $params )
	{
		$params = array_merge( array(
									'template' => '$prev$$next$',
									'prev_start' => '',
									'prev_text' => '&laquo; $title$',
									'prev_end' => '',
									'prev_no_item' => '',
									'next_start' => '',
									'next_text' => '$title$ &raquo;',
									'next_end' => '',
									'next_no_item' => '',
									'target_blog' => '',
								), $params );

		$prev = $this->prev_item_link( $params['prev_start'], $params['prev_end'], $params[ 'prev_text' ], $params[ 'prev_no_item' ], false, $params[ 'target_blog'] );
		$next = $this->next_item_link( $params['next_start'], $params['next_end'], $params[ 'next_text' ], $params[ 'next_no_item' ], false, $params[ 'target_blog'] );

		$output = str_replace( '$prev$', $prev, $params['template'] );
		$output = str_replace( '$next$', $next, $output );

		if( !empty( $output ) )
		{	// we have some output, lets wrap it
			echo( $params['block_start'] );
			echo $output;
			echo( $params['block_end'] );
		}
	}


	/**
	 * Skip to previous
	 */
	function prev_item_link( $before = '', $after = '', $text = '&laquo; $title$', $no_item = '', $display = true, $target_blog = '' )
	{
		/**
		 * @var Item
		 */
		$prev_Item = & $this->get_prevnext_Item( 'prev' );

		if( !is_null($prev_Item) )
		{
			$output = $before;
			$output .= $prev_Item->get_permanent_link( $text, '#', '', $target_blog );
			$output .= $after;
		}
		else
		{
			$output = $no_item;
		}
		if( $display ) echo $output;
		return $output;
	}


	/**
	 * Skip to next
	 */
	function next_item_link(  $before = '', $after = '', $text = '$title$ &raquo;', $no_item = '', $display = true, $target_blog = '' )
	{
		/**
		 * @var Item
		 */
		$next_Item = & $this->get_prevnext_Item( 'next' );

		if( !is_null($next_Item) )
		{
			$output = $before;
			$output .= $next_Item->get_permanent_link( $text, '#', '', $target_blog );
			$output .= $after;
		}
		else
		{
			$output = $no_item;
		}
		if( $display ) echo $output;
		return $output;
	}


	/**
	 * Skip to previous/next Item
	 *
	 * If several items share the same spot (like same issue datetime) then they'll get all skipped at once.
	 *
	 * @param string prev | next  (relative to the current sort order)
	 */
	function & get_prevnext_Item( $direction = 'next' )
	{
		global $DB, $ItemCache;

		if( ! $this->single_post )
		{	// We are not on a single post:
			$r = NULL;
			return $r;
		}

		/**
		 * @var Item
		 */
		$current_Item = $this->get_by_idx(0);

		if( is_null($current_Item) )
		{	// This happens if we are on a single post that we do not actually have permission to view,
			// or if there's no item in the list!
			$r = NULL;
			return $r;
		}

		if( in_array( $current_Item->ptyp_ID, array( 1000, 1500, 1520, 1530, 1570, 1600 ) ) ) // page & intros
		{	// We are not on a REGULAR post:
			$r = NULL;
			return $r;
		}

		if( !empty( $this->prevnext_Item[$direction] ) )
		{
			return $this->prevnext_Item[$direction];
		}

		$next_Query = new ItemQuery( $this->Cache->dbtablename, $this->Cache->dbprefix, $this->Cache->dbIDname );

		// GENERATE THE QUERY:

		/*
		 * filtering stuff:
		 */
		$next_Query->where_chapter2( $this->Blog, $this->filters['cat_array'], $this->filters['cat_modifier'],
																 $this->filters['cat_focus'] );
		$next_Query->where_author( $this->filters['authors'] );
		$next_Query->where_assignees( $this->filters['assignees'] );
		$next_Query->where_author_assignee( $this->filters['author_assignee'] );
		$next_Query->where_locale( $this->filters['lc'] );
		$next_Query->where_statuses( $this->filters['statuses'] );
		$next_Query->where_types( $this->filters['types'] );
		$next_Query->where_keywords( $this->filters['keywords'], $this->filters['phrase'], $this->filters['exact'] );
		// $next_Query->where_ID( $this->filters['post_ID'], $this->filters['post_title'] );
		$next_Query->where_datestart( $this->filters['ymdhms'], $this->filters['week'],
		                                   $this->filters['ymdhms_min'], $this->filters['ymdhms_max'],
		                                   $this->filters['ts_min'], $this->filters['ts_max'] );
		$next_Query->where_visibility( $this->filters['visibility_array'] );

		/*
		 * ORDER BY stuff:
		 */
		if( ($direction == 'next' && $this->filters['order'] == 'DESC')
			|| ($direction == 'prev' && $this->filters['order'] == 'ASC') )
		{
			$order = 'DESC';
			$operator = ' < ';
		}
		else
		{
			$order = 'ASC';
			$operator = ' > ';
		}

		$orderby = str_replace( ' ', ',', $this->filters['orderby'] );
		$orderby_array = explode( ',', $orderby );

		// Format each order param with default column names:
		$orderbyorder_array = preg_replace( '#^(.+)$#', $this->Cache->dbprefix.'$1 '.$order, $orderby_array );

		// Add an ID parameter to make sure there is no ambiguity in ordering on similar items:
		$orderbyorder_array[] = $this->Cache->dbIDname.' '.$order;

		$order_by = implode( ', ', $orderbyorder_array );


		$next_Query->order_by( $order_by );


		// LIMIT to 1 single result
		$next_Query->LIMIT( '1' );

		// fp> TODO: I think some additional limits need to come back here (for timespans)


		/*
		 * Position right after the current element depending on current sorting params
		 *
		 * If there are several items on the same issuedatetime for example, we'll then differentiate on post ID
		 * WARNING: you cannot combine criterias with AND here; you need stuf like a>a0 OR (a=a0 AND b>b0)
		 */
		switch( $orderby_array[0] )
		{
			case 'datestart':
				// special var name:
				$next_Query->WHERE_and( $this->Cache->dbprefix.$orderby_array[0]
																.$operator
																.$DB->quote($current_Item->issue_date)
																.' OR ( '
                                  .$this->Cache->dbprefix.$orderby_array[0]
																	.' = '
																	.$DB->quote($current_Item->issue_date)
																	.' AND '
																	.$this->Cache->dbIDname
																	.$operator
																	.$current_Item->ID
																.')'
														 );
				break;

			case 'title':
			case 'ptyp_ID':
			case 'datecreated':
			case 'datemodified':
			case 'urltitle':
			case 'priority':
				$next_Query->WHERE_and( $this->Cache->dbprefix.$orderby_array[0]
																.$operator
																.$DB->quote($current_Item->{$orderby_array[0]})
																.' OR ( '
                                  .$this->Cache->dbprefix.$orderby_array[0]
																	.' = '
																	.$DB->quote($current_Item->{$orderby_array[0]})
																	.' AND '
																	.$this->Cache->dbIDname
																	.$operator
																	.$current_Item->ID
																.')'
															);
				break;

			case 'order':
				// We have to integrate a rounding error margin
				$comp_order_value = $current_Item->order;
				$and_clause = '';

				if( is_null($comp_order_value) )
				{	// current Item has NULL order
					if( $operator == ' < ' )
					{	// This is needed when browsing through a descending ordered list and we reach the limit where orders are not set/NULL (ex: b2evo screenshots)
						$and_clause .= $this->Cache->dbprefix.$orderby_array[0].' IS NULL AND ';
					}
					else
					{ // This is needed when browsing through a descending ordered list and we want to browse back into the posts that have numbers (pb appears if first NULL posts is the highest ID)
						$and_clause .= $this->Cache->dbprefix.$orderby_array[0].' IS NOT NULL OR ';
					}
					$and_clause .= $this->Cache->dbIDname
						.$operator
						.$current_Item->ID;
				}
				else
				{
					if( $operator == ' < ' )
					{	// This is needed when browsing through a descending ordered list and we reach the limit where orders are not set/NULL (ex: b2evo screenshots)
						$and_clause .= $this->Cache->dbprefix.$orderby_array[0].' IS NULL OR ';
					}
					$and_clause .= $this->Cache->dbprefix.$orderby_array[0]
													.$operator
													.( $operator == ' < ' ? $comp_order_value-0.000000001 : $comp_order_value+0.000000001 )
													.' OR ( '
	                          .$this->Cache->dbprefix.$orderby_array[0]
														.( $operator == ' < ' ? ' <= '.($comp_order_value+0.000000001) : ' >= '.($comp_order_value-0.000000001) )
														.' AND '
														.$this->Cache->dbIDname
														.$operator
														.$current_Item->ID
													.')';
				}

				$next_Query->WHERE_and( $and_clause );
				break;

			default:
				echo 'WARNING: unhandled sorting: '.htmlspecialchars( $orderby_array[0] );
		}

		// GET DATA ROWS:


		// We are going to proceed in two steps (we simulate a subquery)
		// 1) we get the IDs we need
		// 2) we get all the other fields matching these IDs
		// This is more efficient than manipulating all fields at once.

		// Step 1:
		$step1_sql = 'SELECT DISTINCT '.$this->Cache->dbIDname
									.$next_Query->get_from()
									.$next_Query->get_where()
									.$next_Query->get_group_by()
									.$next_Query->get_order_by()
									.$next_Query->get_limit();

		//echo $DB->format_query( $step1_sql );

		// Get list of the IDs we need:
		$next_ID = $DB->get_var( $step1_sql, 0, 0, 'Get ID of next item' );

		//pre_dump( $next_ID );

		// Step 2: get the item (may be NULL):
		$this->prevnext_Item[$direction] = & $ItemCache->get_by_ID( $next_ID, true, false );

		return $this->prevnext_Item[$direction];

	}
}

/*
 * $Log$
 * Revision 1.39  2011/03/02 09:45:59  efy-asimo
 * Update collection features allow_comments, disable_comments_bypost, allow_attachments, allow_rating
 *
 * Revision 1.38  2011/02/10 23:07:21  fplanque
 * minor/doc
 *
 * Revision 1.37  2010/11/25 15:16:35  efy-asimo
 * refactor $Messages
 *
 * Revision 1.36  2010/09/15 13:04:06  efy-asimo
 * Cross post navigatation
 *
 * Revision 1.35  2010/09/02 07:48:33  efy-asimo
 * ItemList and CommentList doc
 *
 * Revision 1.34  2010/07/08 05:29:54  efy-asimo
 * post_dateset notice on preview item page - fix
 *
 * Revision 1.33  2010/07/07 08:50:35  efy-asimo
 * Fix item preview, when no main category is set
 *
 * Revision 1.32  2010/04/17 11:49:56  efy-asimo
 * Notice: Undefined properties: stdClass::$post_canonical_slug_ID stdClass::$post_tiny_slug_ID - bugfix
 *
 * Revision 1.31  2010/03/09 11:30:21  efy-asimo
 * create categories on the fly -  fix
 *
 * Revision 1.30  2010/02/21 01:25:47  sam2kb
 * item_varchar fields rolled back to 'string'
 *
 * Revision 1.29  2010/02/10 22:16:26  sam2kb
 * Allow HTML in item_varchar fields
 *
 * Revision 1.28  2010/02/08 17:53:15  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.27  2010/01/30 18:55:30  blueyed
 * Fix "Assigning the return value of new by reference is deprecated" (PHP 5.3)
 *
 * Revision 1.26  2009/10/05 23:22:15  blueyed
 * Fix notice about post_excerpt_autogenerated in preview mode.
 *
 * Revision 1.25  2009/09/25 07:32:52  efy-cantor
 * replace get_cache to get_*cache
 *
 * Revision 1.24  2009/09/14 13:17:28  efy-arrin
 * Included the ClassName in load_class() call with proper UpperCase
 *
 * Revision 1.23  2009/07/31 21:30:29  blueyed
 * Use existing post_ID in item preview. This makes attachments appear when previewing a new and existing post.
 *
 * Revision 1.22  2009/07/11 18:45:31  tblue246
 * Fix PHP notice
 *
 * Revision 1.21  2009/07/02 23:29:40  fplanque
 * even nastier!
 *
 * Revision 1.20  2009/07/02 23:10:41  fplanque
 * nasty bug fix
 *
 * Revision 1.19  2009/06/20 17:19:33  leeturner2701
 * meta desc and meta keywords per blog post
 *
 * Revision 1.18  2009/03/20 22:44:04  fplanque
 * Related Items -- Proof of Concept
 *
 * Revision 1.17  2009/03/08 23:57:44  fplanque
 * 2009
 *
 * Revision 1.16  2009/03/04 00:10:42  blueyed
 * Make Hit constructor more lazy.
 *  - Move referer_dom_ID generation/fetching to own method
 *  - wrap Debuglog additons with "debug"
 *  - Conditionally call detect_useragent, if required. Move
 *    vars to methods for this
 *  - get_user_agent alone does not require detect_useragent
 * Feel free to revert it (since it changed all the is_foo vars
 * to methods - PHP5 would allow to use __get to handle legacy
 * access to those vars however), but please consider also
 * removing this stuff from HTML classnames, since that is kind
 * of disturbing/unreliable by itself).
 *
 * Revision 1.15  2009/02/19 05:00:20  blueyed
 * Fix (some) indenting.
 *
 * Revision 1.14  2009/01/21 22:26:26  fplanque
 * Added tabs to post browsing admin screen All/Posts/Pages/Intros/Podcasts/Comments
 *
 * Revision 1.13  2009/01/21 18:23:26  fplanque
 * Featured posts and Intro posts
 *
 * Revision 1.12  2008/05/26 19:22:02  fplanque
 * fixes
 *
 * Revision 1.11  2008/04/12 21:40:16  afwas
 * Minor: Added ptyp_ID as post order query option in single post display (get_prevnext_Item())
 *
 * Revision 1.10  2008/03/30 14:17:10  fplanque
 * fix
 *
 * Revision 1.9  2008/02/19 11:11:17  fplanque
 * no message
 *
 * Revision 1.8  2008/02/09 17:36:15  fplanque
 * better handling of order, including approximative comparisons
 *
 * Revision 1.7  2008/02/08 22:24:46  fplanque
 * bugfixes
 *
 * Revision 1.6  2008/01/21 09:35:31  fplanque
 * (c) 2008
 *
 * Revision 1.5  2007/11/25 19:47:15  fplanque
 * cleaned up photo/media index a little bit
 *
 * Revision 1.4  2007/11/04 17:55:13  fplanque
 * More cleanup
 *
 * Revision 1.3  2007/09/09 12:51:58  fplanque
 * cleanup
 *
 * Revision 1.2  2007/09/09 09:15:59  yabs
 * validation
 *
 * Revision 1.1  2007/06/25 11:00:26  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.65  2007/05/28 15:18:30  fplanque
 * cleanup
 *
 * Revision 1.64  2007/05/13 22:02:07  fplanque
 * removed bloated $object_def
 *
 * Revision 1.63  2007/04/26 00:11:12  fplanque
 * (c) 2007
 *
 * Revision 1.62  2007/04/05 22:57:33  fplanque
 * Added hook: UnfilterItemContents
 *
 * Revision 1.61  2007/03/31 22:46:47  fplanque
 * FilterItemContent event
 *
 * Revision 1.60  2007/03/26 18:51:58  fplanque
 * fix
 *
 * Revision 1.59  2007/03/26 14:21:30  fplanque
 * better defaults for pages implementation
 *
 * Revision 1.58  2007/03/26 12:59:18  fplanque
 * basic pages support
 *
 * Revision 1.57  2007/03/19 23:59:18  fplanque
 * fixed preview
 *
 * Revision 1.56  2007/03/19 21:57:36  fplanque
 * ItemLists: $cat_focus and $unit extensions
 *
 * Revision 1.55  2007/03/18 03:43:19  fplanque
 * EXPERIMENTAL
 * Splitting Item/ItemLight and ItemList/ItemListLight
 * Goal: Handle Items with less footprint than with their full content
 * (will be even worse with multiple languages/revisions per Item)
 *
 * Revision 1.53  2007/03/18 01:39:54  fplanque
 * renamed _main.php to main.page.php to comply with 2.0 naming scheme.
 * (more to come)
 *
 * Revision 1.52  2007/03/12 14:02:41  waltercruz
 * Adding the columns in order by to the query to satisfy the SQL Standarts
 *
 * Revision 1.51  2007/03/03 03:37:56  fplanque
 * extended prev/next item links
 *
 * Revision 1.50  2007/03/03 01:14:12  fplanque
 * new methods for navigating through posts in single item display mode
 *
 * Revision 1.49  2007/01/26 04:49:17  fplanque
 * cleanup
 *
 * Revision 1.48  2007/01/23 09:25:40  fplanque
 * Configurable sort order.
 *
 * Revision 1.47  2007/01/20 23:05:11  blueyed
 * todos
 *
 * Revision 1.46  2007/01/19 21:48:09  blueyed
 * Fixed possible notice in preview_from_request()
 *
 * Revision 1.45  2006/12/17 23:42:38  fplanque
 * Removed special behavior of blog #1. Any blog can now aggregate any other combination of blogs.
 * Look into Advanced Settings for the aggregating blog.
 * There may be side effects and new bugs created by this. Please report them :]
 *
 * Revision 1.44  2006/12/05 00:01:15  fplanque
 * enhanced photoblog skin
 *
 * Revision 1.43  2006/12/04 18:16:50  fplanque
 * Each blog can now have its own "number of page/days to display" settings
 *
 * Revision 1.42  2006/11/28 00:33:01  blueyed
 * Removed DB::compString() (never used) and DB::get_list() (just a macro and better to have in the 4 used places directly; Cleanup/normalization; no extended regexp, when not needed!
 *
 * Revision 1.41  2006/11/24 18:27:24  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.40  2006/11/17 00:19:22  blueyed
 * Switch to user locale for validating item_issue_date, because it uses T_()
 *
 * Revision 1.39  2006/11/17 00:09:15  blueyed
 * TODO: error/E_NOTICE with invalid issue date
 *
 * Revision 1.38  2006/11/12 02:13:19  blueyed
 * doc, whitespace
 *
 * Revision 1.37  2006/11/11 17:33:50  blueyed
 * doc
 *
 * Revision 1.36  2006/11/04 19:38:53  blueyed
 * Fixes for hook move
 *
 * Revision 1.35  2006/11/02 16:00:42  blueyed
 * Moved AppendItemPreviewTransact hook, so it can throw error messages
 *
 * Revision 1.34  2006/10/31 00:33:26  blueyed
 * Fixed item_issue_date for preview
 *
 * Revision 1.33  2006/10/10 17:09:39  blueyed
 * doc
 *
 * Revision 1.32  2006/10/08 22:35:01  blueyed
 * TODO: limit===NULL handling
 *
 * Revision 1.31  2006/10/05 01:17:36  blueyed
 * Removed unnecessary/doubled call to Item::update_renderers_from_Plugins()
 *
 * Revision 1.30  2006/10/05 01:06:36  blueyed
 * Removed dirty "hack"; added ItemApplyAsRenderer hook instead.
 */
?>
