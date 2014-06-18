<?php
/**
 * This file implements a class derived of the generic Skin class in order to provide custom code for
 * the skin in this folder.
 *
 * This file is part of the b2evolution project - {@link http://b2evolution.net/}
 *
 * @package skins
 * @subpackage pureforums
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * Specific code for this skin.
 *
 * ATTENTION: if you make a new skin you have to change the class name below accordingly
 */
class pureforums_Skin extends Skin
{
  /**
	 * Get default name for the skin.
	 * Note: the admin can customize it.
	 */
	function get_default_name()
	{
		return 'Pure Forums';
	}


  /**
	 * Get default type for the skin.
	 */
	function get_default_type()
	{
		return 'normal';
	}


	/**
	 * Get definitions for editable params
	 *
	 * @see Plugin::GetDefaultSettings()
	 * @param local params like 'for_editing' => true
	 */
	function get_param_definitions( $params )
	{
		$r = array_merge( array(
				'avatar_style' => array(
					'label' => T_('Style of profile pictures'),
					'note' => '',
					'defaultvalue' => 'round',
					'type' => 'radio',
					'options' => array(
						array( 'round', T_('Round the corners of profile pictures') ),
						array( 'square', T_('Original pictures with square corners') ) ),
					'field_lines' => true,
				),
				'display_post_date' => array(
					'label' => T_('Post date'),
					'note' => T_('Display the date of each post'),
					'defaultvalue' => 1,
					'type' => 'checkbox',
				),
				'colorbox' => array(
					'label' => T_('Colorbox Image Zoom'),
					'note' => T_('Check to enable javascript zooming on images (using the colorbox script)'),
					'defaultvalue' => 1,
					'type' => 'checkbox',
				),
				'gender_colored' => array(
					'label' => T_('Display gender'),
					'note' => T_('Use colored usernames to differentiate men & women.'),
					'defaultvalue' => 0,
					'type' => 'checkbox',
				),
				'bubbletip' => array(
					'label' => T_('Username bubble tips'),
					'note' => T_('Check to enable bubble tips on usernames'),
					'defaultvalue' => 0,
					'type' => 'checkbox',
				),
				'autocomplete_usernames' => array(
					'label' => T_('Autocomplete usernames'),
					'note' => T_('Check to enable autocomplete usernames after entered sign "@" in the comment form'),
					'defaultvalue' => 1,
					'type' => 'checkbox',
				),
				'banner_public' => array(
					'label' => T_('"Public" banner'),
					'note' => T_('Display banner for "Public" posts (posts & comments)'),
					'defaultvalue' => 1,
					'type' => 'checkbox',
				),
			), parent::get_param_definitions( $params ) );

		return $r;
	}


	/**
	 * Get current skin post navigation setting. Always use this navigation setting where this skin is applied.
	 */
	function get_post_navigation()
	{
		return 'same_category';
	}


	/**
	 * Get ready for displaying the skin.
	 *
	 * This may register some CSS or JS...
	 */
	function display_init()
	{
		// call parent:
		parent::display_init();

		// Add CSS:
		require_css( 'basic_styles.css', 'blog' ); // the REAL basic styles
		require_css( 'basic.css', 'blog' ); // Basic styles
		require_css( 'blog_base.css', 'blog' ); // Default styles for the blog navigation
		require_css( 'item_base.css', 'blog' ); // Default styles for the post CONTENT

		// Make sure standard CSS is called ahead of custom CSS generated below:
		//require_css( 'style-old.css', true );
		require_css( 'style.css', true );

		// Colorbox (a lightweight Lightbox alternative) allows to zoom on images and do slideshows with groups of images:
		if($this->get_setting("colorbox"))
		{
			require_js_helper( 'colorbox', 'blog' );
		}

		// Functions to switch between the width sizes
		require_js( '#jquery#', 'blog' );
		require_js( 'widthswitcher.js', 'blog' );
	}


	/**
	 * Display breadcrumbs
	 *
	 * @param integer Chapter ID
	 * @param array Params
	 */
	function display_breadcrumbs( $chapter_ID, $params = array() )
	{
		if( $chapter_ID <= 0 )
		{ // No selected chapter, or an exlcude chapter filter is set
			return;
		}

		$params = array_merge( array(
				'before'    => '<div class="breadcrumbs">',
				'after'     => '</div>',
				'separator' => '',
			), $params );

		global $Blog;

		$ChapterCache = & get_ChapterCache();

		$breadcrumbs = array();
		do
		{	// Get all parent chapters
			$Chapter = & $ChapterCache->get_by_ID( $chapter_ID );

			$breadcrumbs[] = '<a href="'.$Chapter->get_permanent_url().'">'.$Chapter->dget( 'name' ).'</a>';

			$chapter_ID = $Chapter->get( 'parent_ID' );
		}
		while( !empty( $chapter_ID ) );

		$breadcrumbs[] = '<a href="'.$Blog->get( 'blogurl' ).'">'.$Blog->get( 'name' ).'</a>';
		$breadcrumbs = array_reverse( $breadcrumbs );

		// Display
		echo $params['before'];
		echo implode( $params['separator'], $breadcrumbs );
		echo $params['after'];
	}


	/**
	 * Display button to create a new post
	 *
	 * @param integer Chapter ID
	 * @param object Item
	 */
	function display_post_button( $chapter_ID, $Item = NULL )
	{
		echo $this->get_post_button( $chapter_ID, $Item );
	}


	/**
	 * Get HTML code of button to create a new post
	 *
	 * @param integer Chapter ID
	 * @param object Item
	 * @return string
	 */
	function get_post_button( $chapter_ID, $Item = NULL )
	{
		global $Blog;

		$post_button = '';

		$chapter_is_locked = false;

		$write_new_post_url = $Blog->get_write_item_url( $chapter_ID );
		if( $write_new_post_url != '' )
		{ // Display button to write a new post
			$post_button = '<a href="'.$write_new_post_url.'"><span class="ficon newTopic" title="'.T_('Post new topic').'"></span></a>';
		}
		else
		{ // If a creating of new post is unavailable
			$ChapterCache = & get_ChapterCache();
			$current_Chapter = $ChapterCache->get_by_ID( $chapter_ID, false, false );

			if( $current_Chapter && $current_Chapter->lock )
			{ // Display icon to inform that this forum is locked
				$post_button = '<span class="ficon locked" title="'.T_('This forum is locked: you cannot post, reply to, or edit topics.').'"></span>';
				$chapter_is_locked = true;
			}
		}

		if( !empty( $Item ) )
		{
			if( $Item->comment_status == 'closed' || $Item->comment_status == 'disabled' || $Item->is_locked() )
			{ // Display icon to inform that this topic is locked for comments
				if( !$chapter_is_locked )
				{ // Display this button only when chapter is not locked, to avoid a duplicate button
					$post_button .= ' <span class="ficon locked" title="'.T_('This topic is locked: you cannot edit posts or make replies.').'"></span>';
				}
			}
			else
			{ // Display button to post a reply
				$post_button .= ' <a href="'.$Item->get_feedback_url().'#form_p'.$Item->ID.'"><span class="ficon postReply" title="'.T_('Reply to topic').'"></span></a>';
			}
		}

		if( !empty( $post_button ) )
		{ // Display button
			return '<div class="post_button">'.$post_button.'</div>';
		}
	}

	/**
	 * Get chapters
	 *
	 * @param integer Chapter parent ID
	 */
	function get_chapters( $parent_ID = 0 )
	{
		global $Blog, $skin_chapters_cache;

		if( isset( $skin_chapters_cache ) )
		{	// Get chapters from cache
			return $skin_chapters_cache;
		}

		$skin_chapters_cache = array();
		if( $parent_ID > 0 )
		{	// Get children of selected chapter
			global $DB, $Settings;

			$skin_chapters_cache = array();

			$SQL = new SQL();
			$SQL->SELECT( 'cat_ID' );
			$SQL->FROM( 'T_categories' );
			$SQL->WHERE( 'cat_parent_ID = '.$DB->quote( $parent_ID ) );
			if( $Settings->get( 'chapter_ordering' ) == 'manual' )
			{	// Manual order
				$SQL->ORDER_BY( 'cat_meta, cat_order' );
			}
			else
			{	// Alphabetic order
				$SQL->ORDER_BY( 'cat_meta, cat_name' );
			}

			$ChapterCache = & get_ChapterCache();

			$categories = $DB->get_results( $SQL->get() );
			foreach( $categories as $c => $category )
			{
				$skin_chapters_cache[$c] = $ChapterCache->get_by_ID( $category->cat_ID );
				// Get children
				$SQL->WHERE( 'cat_parent_ID = '.$DB->quote( $category->cat_ID ) );
				$children = $DB->get_results( $SQL->get() );
				foreach( $children as $child_Chapter )
				{
					$skin_chapters_cache[$c]->children[$child_Chapter->cat_ID] = $ChapterCache->get_by_ID( $child_Chapter->cat_ID );
				}
			}
		}
		else
		{	// Get the all chapters for current blog
			$ChapterCache = & get_ChapterCache();
			$ChapterCache->load_subset( $Blog->ID );

			if( isset( $ChapterCache->subset_cache[ $Blog->ID ] ) )
			{
				$skin_chapters_cache = $ChapterCache->subset_cache[ $Blog->ID ];

				foreach( $skin_chapters_cache as $c => $Chapter )
				{ // Init children
					foreach( $skin_chapters_cache as $child_Chapter )
					{ // Again go through all chapters to find a children for current chapter
						if( $Chapter->ID == $child_Chapter->get( 'parent_ID' ) )
						{ // Add to array of children
							$skin_chapters_cache[$c]->children[$child_Chapter->ID] = $child_Chapter;
						}
					}
				}

				foreach( $skin_chapters_cache as $c => $Chapter )
				{ // Unset the child chapters
					if( $Chapter->get( 'parent_ID' ) )
					{
						unset( $skin_chapters_cache[$c] );
					}
				}
			}
		}

		return $skin_chapters_cache;
	}


	/**
	 * Determine to display status banner or to don't display
	 *
	 * @param string Status of Item or Comment
	 * @return boolean TRUE if we can display status banner for given status
	 */
	function enabled_status_banner( $status )
	{
		if( $status != 'published' )
		{	// Display status banner everytime when status is not 'published'
			return true;
		}

		if( is_logged_in() && $this->get_setting( 'banner_public' ) )
		{	// Also display status banner if status is 'published'
			//   AND current user is logged in
			//   AND this feature is enabled in skin settings
			return true;
		}

		// Don't display status banner
		return false;
	}


	/**
	 * Those templates are used for example by the messaging screens.
	 */
	function get_template( $name )
	{
		switch( $name )
		{
			case 'Results':
				// Results list:
				return array(
					'page_url' => '', // All generated links will refer to the current page
					'before' => '<div class="results">',
					'content_start' => '<div id="$prefix$ajax_content">',
					'header_start' => '',
						'header_text' => '<div class="center"><strong>'.T_('Pages').'</strong>: <ul class="pagination">'
								.'$prev$$first$$list_prev$$list$$list_next$$last$$next$'
							.'</ul></div>',
						'header_text_single' => '',
					'header_end' => '',
					'head_title' => '<div class="title">$title$<span class="floatright">$global_icons$</span></div>'."\n",
					'filters_start' => '<div class="filters">',
					'filters_end' => '</div>',
					'list_start' => '<div class="table_scroll">'."\n"
					               .'<table class="forums_table highlight" cellspacing="0" cellpadding="0">'."\n",
						'head_start' => "<thead>\n",
							'line_start_head' => '<tr>',
							'colhead_start' => '<th $class_attrib$>',
							'colhead_start_first' => '<th class="$class$">',
							'colhead_start_last' => '<th class="$class$">',
							'colhead_end' => "</th>\n",
							'sort_asc_off' => get_icon( 'sort_asc_off' ),
							'sort_asc_on' => get_icon( 'sort_asc_on' ),
							'sort_desc_off' => get_icon( 'sort_desc_off' ),
							'sort_desc_on' => get_icon( 'sort_desc_on' ),
							'basic_sort_off' => '',
							'basic_sort_asc' => get_icon( 'ascending' ),
							'basic_sort_desc' => get_icon( 'descending' ),
						'head_end' => "</thead>\n\n",
						'tfoot_start' => "<tfoot>\n",
						'tfoot_end' => "</tfoot>\n\n",
						'body_start' => "<tbody>\n",
							'line_start' => '<tr class="even">'."\n",
							'line_start_odd' => '<tr class="odd">'."\n",
							'line_start_last' => '<tr class="even lastline">'."\n",
							'line_start_odd_last' => '<tr class="odd lastline">'."\n",
								'col_start' => '<td $class_attrib$>',
								'col_start_first' => '<td class="firstcol $class$">',
								'col_start_last' => '<td class="lastcol $class$">',
								'col_end' => "</td>\n",
							'line_end' => "</tr>\n\n",
							'grp_line_start' => '<tr class="group">'."\n",
							'grp_line_start_odd' => '<tr class="odd">'."\n",
							'grp_line_start_last' => '<tr class="lastline">'."\n",
							'grp_line_start_odd_last' => '<tr class="odd lastline">'."\n",
										'grp_col_start' => '<td $class_attrib$ $colspan_attrib$>',
										'grp_col_start_first' => '<td class="firstcol $class$" $colspan_attrib$>',
										'grp_col_start_last' => '<td class="lastcol $class$" $colspan_attrib$>',
								'grp_col_end' => "</td>\n",
							'grp_line_end' => "</tr>\n\n",
						'body_end' => "</tbody>\n\n",
						'total_line_start' => '<tr class="total">'."\n",
							'total_col_start' => '<td $class_attrib$>',
							'total_col_start_first' => '<td class="firstcol $class$">',
							'total_col_start_last' => '<td class="lastcol $class$">',
							'total_col_end' => "</td>\n",
						'total_line_end' => "</tr>\n\n",
					'list_end' => "</table></div>\n\n",
					'footer_start' => '',
					'footer_text' => '<div class="center"><strong>'.T_('Pages').'</strong>: <ul class="pagination">'
							.'$prev$$first$$list_prev$$list$$list_next$$last$$next$'
						.'</ul></div><div class="center">$page_size$</div>'
					                  /* T_('Page $scroll_list$ out of $total_pages$   $prev$ | $next$<br />'. */
					                  /* '<strong>$total_pages$ Pages</strong> : $prev$ $list$ $next$' */
					                  /* .' <br />$first$  $list_prev$  $list$  $list_next$  $last$ :: $prev$ | $next$') */,
					'footer_text_single' => '<div class="center">$page_size$</div>',
					'footer_text_no_limit' => '', // Text if theres no LIMIT and therefor only one page anyway
						'page_current_template' => '<span><b>$page_num$</b></span>',
						'page_item_before' => '<li>',
						'page_item_after' => '</li>',
						'prev_text' => T_('Previous'),
						'next_text' => T_('Next'),
						'no_prev_text' => '',
						'no_next_text' => '',
						'list_prev_text' => T_('...'),
						'list_next_text' => T_('...'),
						'list_span' => 11,
						'scroll_list_range' => 5,
					'footer_end' => "\n\n",
					'no_results_start' => '<div class="table_scroll"><table class="forums_table highlight" cellspacing="0" cellpadding="0"><tr><td>'."\n",
					'no_results_end'   => '$no_results$</td></tr></table></div>'."\n\n",
					'content_end' => '</div>',
					'after' => '</div>',
					'sort_type' => 'basic'
				);
				break;

			default:
				// Delegate to parent class:
				return parent::get_template( $name );
		}
	}
}

?>