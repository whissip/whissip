<?php
/**
 * This file implements the CommentList2 class.
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}.
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
 * @author asimo: Evo Factory - Attila Simo
 * @author blueyed: Daniel HAHLER
 * @author fplanque: Francois PLANQUE
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class( '_core/model/dataobjects/_dataobjectlist2.class.php', 'DataObjectList2' );

/**
 * CommentList Class 2
 *
 * @package evocore
 */
/**
 * @author asimo
 *
 */
class CommentList2 extends DataObjectList2
{
	/**
	 * SQL object for the Query
	 */
	var $CommentQuery;

	/**
	 * Blog object this CommentList refers to
	 */
	var $Blog;

	/**
	 * Constructor
	 *
	 * @param Blog
	 * @param integer|NULL Limit
	 * @param string name of cache to be used
	 * @param string prefix to differentiate page/order params when multiple Results appear one same page
	 * @param string Name to be used when saving the filterset (leave empty to use default for collection)
	 */
	function CommentList2(
		$Blog,
		$limit = 1000,
		$cache_name = 'CommentCache',	// name of cache to be used
		$param_prefix = '',
		$filterset_name = ''			// Name to be used when saving the filterset (leave empty to use default for collection)
		)
	{
		global $Settings;

		// Call parent constructor:
		parent::DataObjectList2( get_Cache($cache_name), $limit, $param_prefix, NULL );

		// The SQL Query object:
		$this->CommentQuery = new CommentQuery(/* $this->Cache->dbtablename, $this->Cache->dbprefix, $this->Cache->dbIDname*/ );

		$this->Blog = & $Blog;

		if( !empty( $filterset_name ) )
		{	// Set the filterset_name with the filterset_name param
			$this->filterset_name = 'CommentList_filters_'.$filterset_name;
		}
		else
		{	// Set a generic filterset_name
			$this->filterset_name = 'CommentList_filters_coll'.$this->Blog->ID;
		}

		$this->page_param = $param_prefix.'paged';

		// Initialize the default filter set:
		$this->set_default_filters( array(
				'filter_preset' => NULL,
				'author' => NULL,
				'author_email' => NULL,
				'author_url' => NULL,
				'url_match' => '=',
				'include_emptyurl' => NULL,
				'author_IP' => NULL,
				'post_ID' => NULL,
				'comment_ID' => NULL,
				'comment_ID_list' => NULL,
				'rating_toshow' => NULL,
				'rating_turn' => 'above',
				'rating_limit' => 1,
				'keywords' => NULL,
				'phrase' => 'AND',
				'exact' => 0,
				'statuses' => NULL,
				'types' => array( 'comment','trackback','pingback' ),
				'orderby' => 'date',
				'order' => $this->Blog->get_setting('orderdir'),
				//'order' => 'DESC',
				'comments' => $this->limit,
				'page' => 1,
				'featured' => NULL,
		) );
	}


	/**
	 * Reset the query -- EXPERIMENTAL
	 *
	 * Useful to requery with a slighlty moidified filterset
	 */
	function reset()
	{
		// The SQL Query object:
		$this->CommentQuery = new CommentQuery( $this->Cache->dbtablename, $this->Cache->dbprefix, $this->Cache->dbIDname );

		parent::reset();
	}


	/**
	 * Set default filter values we always want to use if not individually specified otherwise:
	 *
	 * @param array default filters to be merged with the class defaults
	 * @param array default filters for each preset, to be merged with general default filters if the preset is used
	 */
	function set_default_filters( $default_filters, $preset_filters = array() )
	{
		$this->default_filters = array_merge( $this->default_filters, $default_filters );
		$this->preset_filters = $preset_filters;
	}


	/**
	 * Set/Activate filterset
	 *
	 * This will also set back the GLOBALS !!! needed for regenerate_url().
	 *
	 * @param array
	 * @param boolean
	 */
	function set_filters( $filters, $memorize = true )
	{
		if( !empty( $filters ) )
		{ // Activate the filterset (fallback to default filter when a value is not set):
			$this->filters = array_merge( $this->default_filters, $filters );
		}

		// Activate preset filters if necessary:
		$this->activate_preset_filters();

		// Funky oldstyle params:
		$this->limit = $this->filters['comments']; // for compatibility with parent class
		$this->page = $this->filters['page'];

		// asimo> memorize is always false for now, because is not fully implemented
		if( $memorize )
		{	// set back the GLOBALS !!! needed for regenerate_url() :

			/*
			 * Selected filter preset:
			 */
			memorize_param( $this->param_prefix.'filter_preset', 'string', $this->default_filters['filter_preset'], $this->filters['filter_preset'] );  // List of authors to restrict to

			/*
			 * Restrict to selected authors attribute:
			 */
			memorize_param( $this->param_prefix.'author', 'string', $this->default_filters['author'], $this->filters['author'] );  // List of authors ID to restrict to
			memorize_param( $this->param_prefix.'author_email', 'string', $this->default_filters['author_email'], $this->filters['author_email'] );  // List of authors email to restrict to
			memorize_param( $this->param_prefix.'author_url', 'string', $this->default_filters['author_url'], $this->filters['author_url'] );  // List of authors url to restrict to
			memorize_param( $this->param_prefix.'url_match', 'string', $this->default_filters['url_match'], $this->filters['url_match'] );  // List of authors url to restrict to
			memorize_param( $this->param_prefix.'include_emptyurl', 'string', $this->default_filters['include_emptyurl'], $this->filters['include_emptyurl'] );  // List of authors url to restrict to
			memorize_param( $this->param_prefix.'author_IP', 'string', $this->default_filters['author_IP'], $this->filters['author_IP'] );  // List of authors ip to restrict to

			/*
			 * Restrict to selected rating:
			 */
			memorize_param( $this->param_prefix.'rating_toshow', 'array', $this->default_filters['rating_toshow'], $this->filters['rating_toshow'] );  // Rating to restrict to
			memorize_param( $this->param_prefix.'rating_turn', 'string', $this->default_filters['rating_turn'], $this->filters['rating_turn'] );  // Rating to restrict to
			memorize_param( $this->param_prefix.'rating_limit', 'integer', $this->default_filters['rating_limit'], $this->filters['rating_limit'] );  // Rating to restrict to

			/*
			 * Restrict by keywords
			 */
			memorize_param( $this->param_prefix.'s', 'string', $this->default_filters['keywords'], $this->filters['keywords'] );			 // Search string
			memorize_param( $this->param_prefix.'sentence', 'string', $this->default_filters['phrase'], $this->filters['phrase'] ); // Search for sentence or for words
			memorize_param( $this->param_prefix.'exact', 'integer', $this->default_filters['exact'], $this->filters['exact'] );     // Require exact match of title or contents

			/*
			 * Restrict to selected statuses:
			 */
			memorize_param( $this->param_prefix.'show_statuses', 'array', $this->default_filters['statuses'], $this->filters['statuses'] );  // List of statuses to restrict to

			/*
			 * Restrict to selected comment type:
			 */
			memorize_param( $this->param_prefix.'type', 'string', $this->default_filters['types'], $this->filters['types'] );  // List of comment types to restrict to

			/*
			 * Restrict to the statuses we want to show:
			 */
			// Note: oftentimes, $show_statuses will have been preset to a more restrictive set of values
			//memorize_param( $this->param_prefix.'show_statuses', 'array', $this->default_filters['visibility_array'], $this->filters['visibility_array'] );	// Array of sharings to restrict to

			/*
			 * OLD STYLE orders:
			 */
			memorize_param( $this->param_prefix.'order', 'string', $this->default_filters['order'], $this->filters['order'] );   		// ASC or DESC
			// This order style is OK, because sometimes the commentList is not displayed on a table so we cannot say we want to order by a specific column.
			memorize_param( $this->param_prefix.'orderby', 'string', $this->default_filters['orderby'], $this->filters['orderby'] );  // list of fields to order by (TODO: change that crap)

			/*
			 * Paging limits:
			 */
			memorize_param( $this->param_prefix.'comments', 'integer', $this->default_filters['comments'], $this->filters['comments'] ); 			// # of units to display on the page

			// 'paged'
			memorize_param( $this->page_param, 'integer', 1, $this->filters['page'] );      // List page number in paged display
		}
	}


	/**
	 * Init filter params from request params
	 *
	 * @param boolean do we want to use saved filters ?
	 * @return boolean true if we could apply a filterset based on Request params (either explicit or reloaded)
	 */
	function load_from_Request( $use_filters = true )
	{
		$this->filters = $this->default_filters;

		if( $use_filters )
		{
			// Do we want to restore filters or do we want to create a new filterset
			$filter_action = param( $this->param_prefix.'filter', 'string', 'save' );
			switch( $filter_action )
			{
				case 'restore':
					return $this->restore_filterset();
					/* BREAK */

				case 'reset':
					// We want to reset the memorized filterset:
					global $Session;
					$Session->delete( $this->filterset_name );

					// Memorize global variables:
					$this->set_filters( array(), true );

					// We have applied no filterset:
					return false;
					/* BREAK */
			}

			/**
			 * Filter preset
			 */
			$this->filters['filter_preset'] = param( $this->param_prefix.'filter_preset', 'string', $this->default_filters['filter_preset'], true );

			// Activate preset default filters if necessary:
			$this->activate_preset_filters();
		}

		/*
		 * Restrict to selected author:
		 */
		$this->filters['author'] = param( $this->param_prefix.'author', '/^-?[0-9]+(,[0-9]+)*$/', $this->default_filters['author'], true );      // List of authors to restrict to
		$this->filters['author_email'] = param( $this->param_prefix.'author_email', 'string', $this->default_filters['author_email'], true );
		$this->filters['author_url'] = param( $this->param_prefix.'author_url', 'string', $this->default_filters['author_url'], true );
		$this->filters['url_match'] = param( $this->param_prefix.'url_match', 'string', $this->default_filters['url_match'], true );
		$this->filters['include_emptyurl'] = param( $this->param_prefix.'include_emptyurl', 'string', $this->default_filters['include_emptyurl'], true );
		//$this->filters['author_IP'] = param( $this->param_prefix.'author_IP', 'string', $this->default_filters['author_IP'], true );

		/*
		 * Restrict to selected statuses:
		 */
		$this->filters['statuses'] = param( $this->param_prefix.'show_statuses', 'array', $this->default_filters['statuses'], true );      // List of statuses to restrict to

		/*
		 * Restrict to selected types:
		 */
		$this->filters['types'] = param( $this->param_prefix.'types', 'array', $this->default_filters['types'], true );      // List of types to restrict to

		/*
		 * Restrict by keywords
		 */
		$this->filters['keywords'] = param( $this->param_prefix.'s', 'string', $this->default_filters['keywords'], true );         // Search string
		$this->filters['phrase'] = param( $this->param_prefix.'sentence', 'string', $this->default_filters['phrase'], true ); 		// Search for sentence or for words
		$this->filters['exact'] = param( $this->param_prefix.'exact', 'integer', $this->default_filters['exact'], true );        // Require exact match of title or contents

		/*
		 * Restrict to selected rating:
		 */
		$this->filters['rating_toshow'] = param( $this->param_prefix.'rating_toshow', 'array', $this->default_filters['rating_toshow'], true );      // Rating to restrict to
		$this->filters['rating_turn'] = param( $this->param_prefix.'rating_turn', 'string', $this->default_filters['rating_turn'], true );      // Rating to restrict to
		$this->filters['rating_limit'] = param( $this->param_prefix.'rating_limit', 'integer', $this->default_filters['rating_limit'], true ); 	// Rating to restrict to

		// 'limit'
		$this->filters['comments'] = param( $this->param_prefix.'comments', 'integer', $this->default_filters['comments'], true ); 			// # of units to display on the page
		$this->limit = $this->filters['comments']; // for compatibility with parent class
		$this->filters['limit'] = $this->limit;

		// 'paged'
		$this->filters['page'] = param( $this->page_param, 'integer', 1, true );      // List page number in paged display
		$this->page = $this->filters['page'];

		$this->filters['order'] = param( $this->param_prefix.'order', 'string', $this->default_filters['order'], true );   		// ASC or DESC
		// This order style is OK, because sometimes the commentList is not displayed on a table so we cannot say we want to order by a specific column. It's not a crap.
		$this->filters['orderby'] = param( $this->param_prefix.'orderby', 'string', $this->default_filters['orderby'], true );  // list of fields to order by (TODO: change that crap)

		if( $use_filters && $filter_action == 'save' )
		{
			$this->save_filterset();
		}

		return ! param_errors_detected();
	}


	/**
	 * Activate preset default filters if necessary
	 *
	 */
	function activate_preset_filters()
	{
		$filter_preset = $this->filters['filter_preset'];

		if( empty( $filter_preset ) )
		{ // No filter preset, there are no additional defaults to use:
			return;
		}

		// Override general defaults with the specific defaults for the preset:
		$this->default_filters = array_merge( $this->default_filters, $this->preset_filters[$filter_preset] );

		// Save the name of the preset in order for is_filtered() to work properly:
		$this->default_filters['filter_preset'] = $this->filters['filter_preset'];
	}


	/**
	 * Save current filterset to session.
	 */
	function save_filterset()
	{
		/**
		 * @var Session
		 */
		global $Session, $Debuglog;

		$Debuglog->add( 'Saving filterset <strong>'.$this->filterset_name.'</strong>', 'filters' );

		$Session->set( $this->filterset_name, $this->filters );
	}


	/**
	 * Load previously saved filterset from session.
	 *
	 * @return boolean true if we could restore something
	 */
	function restore_filterset()
	{
	  /**
	   * @var Session
	   */
		global $Session;
	  /**
	   * @var Request
	   */

		global $Debuglog;

		$filters = $Session->get( $this->filterset_name );

		if( empty($filters) )
		{ // set_filters() expects array
			$filters = array();
		}

		$Debuglog->add( 'Restoring filterset <strong>'.$this->filterset_name.'</strong>', 'filters' );

		// Restore filters:
		$this->set_filters( $filters );

		return true;
	}


	/**
	 *
	 *
	 * @todo count?
	 */
	function query_init()
	{
		if( empty( $this->filters ) )
		{	// Filters have not been set before, we'll use the default filterset:
			// If there is a preset filter, we need to activate its specific defaults:
			$this->filters['filter_preset'] = param( $this->param_prefix.'filter_preset', 'string', $this->default_filters['filter_preset'], true );
			$this->activate_preset_filters();

			// Use the default filters:
			$this->set_filters( $this->default_filters );
		}

		// GENERATE THE QUERY:

		/*
		 * Resrict to selected blog
		 */
		// If we dont have specific comment or post ids, we have to restric to blog
		if( ( $this->filters['post_ID'] == NULL || ( ! empty($this->filters['post_ID']) && substr( $this->filters['post_ID'], 0, 1 ) == '-') ) &&
			( $this->filters['comment_ID'] == NULL || ( ! empty($this->filters['comment_ID']) && substr( $this->filters['comment_ID'], 0, 1 ) == '-') ) &&
			( $this->filters['comment_ID_list'] == NULL || ( ! empty($this->filters['comment_ID_list']) && substr( $this->filters['comment_ID_list'], 0, 1 ) == '-') ) )
		{ // restriction for blog
			$this->CommentQuery->blog_restrict( $this->Blog );
		}

		/*
		 * filtering stuff:
		 */
		$this->CommentQuery->where_author( $this->filters['author'] );
		$this->CommentQuery->where_author_email( $this->filters['author_email'] );
		$this->CommentQuery->where_author_url( $this->filters['author_url'], $this->filters['url_match'], $this->filters['include_emptyurl'] );
		$this->CommentQuery->where_author_IP( $this->filters['author_IP'] );
		$this->CommentQuery->where_post_ID( $this->filters['post_ID'] );
		$this->CommentQuery->where_ID( $this->filters['comment_ID'], $this->filters['author'] );
		$this->CommentQuery->where_ID_list( $this->filters['comment_ID_list'] );
		$this->CommentQuery->where_rating( $this->filters['rating_toshow'], $this->filters['rating_turn'], $this->filters['rating_limit'] );
		$this->CommentQuery->where_keywords( $this->filters['keywords'], $this->filters['phrase'], $this->filters['exact'] );
		$this->CommentQuery->where_statuses( $this->filters['statuses'] );
		$this->CommentQuery->where_types( $this->filters['types'] );


		/*
		 * ORDER BY stuff:
		 */
		$order_by = gen_order_clause( $this->filters['orderby'], $this->filters['order'], $this->Cache->dbprefix, $this->Cache->dbIDname );

		$this->CommentQuery->order_by( $order_by );

		/*
		 * GET TOTAL ROW COUNT:
		 */
		$sql_count = '
				SELECT COUNT( DISTINCT '.$this->Cache->dbIDname.') '
					.$this->CommentQuery->get_from()
					.$this->CommentQuery->get_where();

		parent::count_total_rows( $sql_count );

		/*
		 * Page set up:
		 */
		if( $this->page > 1 )
		{ // We have requested a specific page number
			if( $this->limit > 0 )
			{
				$pgstrt = '';
				$pgstrt = (intval($this->page) -1) * $this->limit. ', ';
				$this->CommentQuery->LIMIT( $pgstrt.$this->limit );
			}
		}
		else
		{
			$this->CommentQuery->LIMIT( $this->limit );
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
		// $this->CommentQuery->ORDER_BY_prepend( $this->get_order_field_list() );


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
									.$this->CommentQuery->get_from()
									.$this->CommentQuery->get_where()
									.$this->CommentQuery->get_group_by()
									.$this->CommentQuery->get_order_by()
									.$this->CommentQuery->get_limit();

		// Get list of the IDs we need:
		$ID_list = implode( ',', $DB->get_col( $step1_sql, 0, 'CommentList2::Query() Step 1: Get ID list' ) );

		// *** STEP 2 ***
		$this->sql = 'SELECT *
			              FROM '.$this->Cache->dbtablename;
		if( !empty($ID_list) )
		{
			$this->sql .= ' WHERE '.$this->Cache->dbIDname.' IN ('.$ID_list.') '
										.$this->CommentQuery->get_order_by();
		}
		else
		{
			$this->sql .= ' WHERE 0';
		}

		// ATTENTION: we skip the parent on purpose here!! fp> refactor
		DataObjectList2::query( false, false, false, 'CommentList2::Query() Step 2' );
	}


	/**
	 * Generate a title for the current list, depending on its filtering params
	 *
	 * @return array List of titles to display, which are escaped for HTML display
	 */
	function get_filter_titles( $ignore = array(), $params = array() )
	{
		$title_array = array();

		if( empty ($this->filters) )
		{ // Filters have no been set before, we'll use the default filterset
			$this->set_filters( $this->default_filters );
		}

		if( isset( $this->filters['statuses'] ) && count($this->filters['statuses']) < 3 )
		{
			$title_array['statuses'] = T_('Visibility').': '.implode( ', ', $this->filters['statuses'] );
		}

		if( !empty($this->filters['keywords']) )
		{
			$title_array['keywords'] = T_('Keywords').': '.$this->filters['keywords'];
		}

		return $title_array;
	}


	/**
	 * If the list is sorted by category...
 	 *
 	 * This is basically just a stub for backward compatibility
	 */
	function & get_Comment()
	{
		$Comment = & parent::get_next();

		if( empty($Comment) )
		{
			$r = false;
			return $r;
		}

		//pre_dump( $Comment );

		return $Comment;
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
	
	
	/**
	 * Template tag
	 *
	 * Display page links (when paginated comments are enabled)
	 */
	function page_links( $params = array() )
	{
		global $generating_static;

		$default_params = array(
				'block_start' => '<p class="center">',
				'block_end' => '</p>',
				'block_single' => '',
				'links_format' => '#',
				'page_url' => '', // All generated links will refer to the current page
				'prev_text' => '&lt;&lt;',
				'next_text' => '&gt;&gt;',
				'no_prev_text' => '',
				'no_next_text' => '',
				'list_prev_text' => '...',
				'list_next_text' => '...',
				'list_span' => 11,
				'scroll_list_range' => 5,
			);
		
		if( !empty($generating_static) )
		{	// When generating a static page, act as if we were currently on the blog main page:
			$default_params['page_url'] = $this->Blog->get('url');
		}

		// Use defaults + overrides:
		$params = array_merge( $default_params, $params );

		if( $this->total_pages <= 1 || $this->page > $this->total_pages )
		{	// Single page:
			echo $params['block_single'];
			return;
		}

		if( $params['links_format'] == '#' )
		{
			$params['links_format'] = '$prev$ $first$ $list_prev$ $list$ $list_next$ $last$ $next$';
		}

 		if( $this->Blog->get_setting( 'paged_nofollowto' ) )
		{	// We prefer robots not to follow to pages:
			$this->nofollow_pagenav = true;
		}

		echo $params['block_start'];
		echo $this->replace_vars( $params['links_format'], $params );
		echo $params['block_end'];
	}


	/**
	 * Returns values needed to make sort links for a given column
	 * This is needed because the order is not handled by the result class.
	 * Reason: Sometimes the comment list needs to be ordered without having a display table, and columns. The result class order is based on columns.
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
	 * Checks if currently selected filter contains only comments trash status
	 * 
	 * @return boolean
	 */
	function is_trashfilter()
	{
		if( count( $this->filters['statuses'] ) == 1 )
		{
			return $this->filters['statuses'][0] == 'trash';
		}
		return false;
	}
}

/*
 * $Log$
 * Revision 1.34  2011/02/24 07:42:27  efy-asimo
 * Change trashcan to Recycle bin
 *
 * Revision 1.33  2011/02/23 21:45:18  fplanque
 * minor / cleanup
 *
 * Revision 1.32  2011/02/14 14:13:24  efy-asimo
 * Comments trash status
 *
 * Revision 1.31  2010/12/18 15:01:54  sam2kb
 * Don't display page links if requested page is out of range
 *
 * Revision 1.30  2010/10/19 02:00:53  fplanque
 * MFB
 *
 * Revision 1.29  2010/10/13 14:07:55  efy-asimo
 * Optional paged comments in the front end
 *
 * Revision 1.28  2010/09/02 07:48:32  efy-asimo
 * ItemList and CommentList doc
 *
 * Revision 1.27  2010/07/26 06:52:16  efy-asimo
 * MFB v-4-0
 *
 * Revision 1.26  2010/06/23 09:30:55  efy-asimo
 * Comments display and Antispam ban form modifications
 *
 * Revision 1.25  2010/06/08 01:49:53  sam2kb
 * Paged comments in frontend
 *
 * Revision 1.24  2010/05/24 21:27:58  sam2kb
 * Fixed some translated strings
 *
 * Revision 1.23  2010/05/10 14:26:17  efy-asimo
 * Paged Comments & filtering & add comments listview
 *
 * Revision 1.22  2010/03/28 19:27:47  fplanque
 * minor
 *
 * Revision 1.21  2010/03/27 15:55:48  blueyed
 * cleanup
 *
 * Revision 1.20  2010/03/27 15:51:17  blueyed
 * Minor doc. whitespace.
 *
 * Revision 1.19  2010/03/25 10:45:57  efy-asimo
 * add filter by URL to comments screen
 *
 * Revision 1.18  2010/03/25 07:47:40  efy-asimo
 * Add filter by rating to comments screen
 *
 * Revision 1.17  2010/03/15 17:12:10  efy-asimo
 * Add filters to Comment page
 *
 * Revision 1.16  2010/03/11 10:34:57  efy-asimo
 * Rewrite CommentList to CommentList2 rewrite
 */
?>
