<?php
/**
 * This is the template that displays the linkblog
 *
 * This file is not meant to be called directly.
 * It is meant to be called by an include in the main.page.php template.
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2006 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @package evoskins
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

if( !isset( $linkblog ) )
{	// No link blog explicitely specified, we use default:
	$linkblog = $Blog->get('links_blog_ID');
}

if( ! $linkblog )
{	// No linkblog blog requested for this blog
	return;
}

// Load the linkblog blog:
$link_Blog = & $BlogCache->get_by_ID( $linkblog, false );

if( empty($link_Blog) )
{
	echo $linkblog_main_start.T_('The requested LinkBlog doesn\'t exist any more!').$linkblog_main_end;
	return;
}


# maximum number of linkblog entries to display:
if(!isset($linkblog_limit)) $linkblog_limit = 20;
# global linkblog delimiters:
if(!isset($linkblog_main_start)) $linkblog_main_start = '<div class="bSideItem"><h3>'.
																													T_('Linkblog').'</h3>';
if(!isset($linkblog_main_end)) $linkblog_main_end = '</div>';
# Category delimiters:
if(!isset($linkblog_catname_before)) $linkblog_catname_before = '<h4>';
if(!isset($linkblog_catname_after)) $linkblog_catname_after = '</h4><ul>';
if(!isset($linkblog_catlist_end)) $linkblog_catlist_end = '</ul>';
# Item delimiters:
if(!isset($linkblog_item_before)) $linkblog_item_before = '<li>';
if(!isset($linkblog_item_after)) $linkblog_item_after = '</li>';


$LinkblogList = & new ItemList2( $link_Blog, $timestamp_min, $timestamp_max, $linkblog_limit );

// Compile cat array stuff:
$linkblog_cat_array = array();
$linkblog_cat_modifier = '';
compile_cat_array( $linkblog_cat, $linkblog_catsel, /* by ref */ $linkblog_cat_array, /* by ref */  $linkblog_cat_modifier, $linkblog );

$LinkblogList->set_filters( array(
		'cat_array' => $linkblog_cat_array,
		'cat_modifier' => $linkblog_cat_modifier,
		'order' => 'ASC',
		'orderby' => 'main_cat_ID title',
		'unit' => 'posts',
	) );

// Run the query:
$LinkblogList->query();


// Open the global list
echo $linkblog_main_start;


while( $Item = & $LinkblogList->get_category_group() )
{
	// Open new cat:
	echo $linkblog_catname_before;
	$Item->main_category();
	echo $linkblog_catname_after;

	while( $Item = & $LinkblogList->get_item() )
	{
		echo $linkblog_item_before;

		$Item->title( '', ' ' );

		$Item->content_teaser( array(
				'before'      => '',
				'after'       => ' ',
				'disppage'    => 1,
				'stripteaser' => false,
			) );

		$Item->more_link( '', ' ', T_('more').' &raquo;' );

		$Item->permanent_link( '#icon#' );

		echo $linkblog_item_after;
	}

	// Close cat
	echo $linkblog_catlist_end;
}
// Close the global list
echo $linkblog_main_end;


/*
 * $Log$
 * Revision 1.20  2007/03/18 01:39:55  fplanque
 * renamed _main.php to main.page.php to comply with 2.0 naming scheme.
 * (more to come)
 *
 * Revision 1.19  2007/03/06 12:18:09  fplanque
 * got rid of dirty Item::content()
 * Advantage: the more link is now independant. it can be put werever people want it
 *
 * Revision 1.18  2006/12/18 00:41:07  fplanque
 * handle non existing blogs a little better
 *
 * Revision 1.17  2006/09/10 23:40:47  fplanque
 * minor
 *
 * Revision 1.16  2006/09/10 21:18:25  blueyed
 * call-time pass-by-reference has been deprecated
 *
 * Revision 1.15  2006/09/06 18:34:04  fplanque
 * Finally killed the old stinkin' ItemList(1) class which is deprecated by ItemList2
 *
 * Revision 1.14  2006/07/06 19:56:29  fplanque
 * no message
 *
 * Revision 1.13  2006/04/11 21:22:26  fplanque
 * partial cleanup
 *
 */
?>