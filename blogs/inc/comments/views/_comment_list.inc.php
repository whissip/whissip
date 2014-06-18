<?php
/**
 * This file implements the comment list
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2014 by Francois Planque - {@link http://fplanque.com/}.
 * Parts of this file are copyright (c)2005 by Daniel HAHLER - {@link http://thequod.de/contact}.
 *
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
 *
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * @var Comment
 */
global $Comment;
/**
 * @var Blog
 */
global $Blog;
/**
 * @var CommentList
 */
global $CommentList;

global $current_User;

global $dispatcher;

// If rediret_to was not set, create new redirect
$redirect_to = param( 'redirect_to', 'url', regenerate_url( '', 'filter=restore', '', '&' ) );
$redirect_to = rawurlencode( $redirect_to );
$save_context = param( 'save_context', 'boolean', 'true' );
$show_comments = param( 'show_comments', 'string', 'all' );

$item_id = param( 'item_id', 'integer', 0 );
$currentpage = param( 'currentpage', 'integer', 0 );
$comments_number = param( 'comments_number', 'integer', 0 );
if( ( $item_id != 0 ) && ( $comments_number > 0 ) )
{
	echo_pages( $item_id, $currentpage, $comments_number );
}

while( $Comment = & $CommentList->get_next() )
{ // Loop through comments:
	if( ( $show_comments == 'draft' ) && ( $Comment->get( 'status' ) != 'draft' ) )
	{ // if show only draft comments, and current comment status isn't draft, then continue with the next comment
		continue;
	}
	echo_comment( $Comment->ID, $redirect_to, $save_context );
} //end of the loop, don't delete

if( ( $item_id != 0 ) && ( $comments_number > 0 ) )
{
	echo_pages( $item_id, $currentpage, $comments_number );
}

?>