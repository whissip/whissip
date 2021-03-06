<?php
/**
 * This is the template that displays the feedback for a post
 * (comments, trackback, pingback...)
 *
 * You may want to call this file multiple time in a row with different $c $tb $pb params.
 * This allow to seprate different kinds of feedbacks instead of displaying them mixed together
 *
 * This file is not meant to be called directly.
 * It is meant to be called by an include in the main.page.php template.
 * To display a feedback, you should call a stub AND pass the right parameters
 * For example: /blogs/index.php?p=1&more=1&c=1&tb=1&pb=1
 * Note: don't code this URL by hand, use the template functions to generate it!
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @package evoskins
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

?>
<!-- ===================== START OF FEEDBACK ===================== -->
<?php

// Default params:
$params = array_merge( array(
		'disp_comments'        => true,
		'disp_comment_form'    => true,
		'disp_trackbacks'      => true,
		'disp_trackback_url'   => true,
		'disp_pingbacks'       => true,
		'before_section_title' => '<h3>',
		'after_section_title'  => '</h3>',
		'comment_list_start'   => "\n\n",
		'comment_list_end'     => "\n\n",
		'comment_start'        => '<div class="bComment">',
		'comment_end'          => '</div>',
		'preview_start'        => '<div class="bComment" id="comment_preview">',
		'preview_end'          => '</div>',
		'comment_template'     => '_item_comment.inc.php',	// The template used for displaying individual comments (including preview)
		'link_to'		           => 'userurl>userpage',		    // 'userpage' or 'userurl' or 'userurl>userpage' or 'userpage>userurl'
		'form_title_start'     => '<h3>',
		'form_title_end'       => '</h3>',
	), $params );


global $c, $tb, $pb, $redir;


if( ! $Item->can_see_comments() )
{	// Comments are disabled for this post
	return;
}

if( empty($c) )
{	// Comments not requested
	$params['disp_comments'] = false;					// DO NOT Display the comments if not requested
	$params['disp_comment_form'] = false;			// DO NOT Display the comments form if not requested
}

if( empty($tb) || !$Blog->get( 'allowtrackbacks' ) )
{	// Trackback not requested or not allowed
	$params['disp_trackbacks'] = false;				// DO NOT Display the trackbacks if not requested
	$params['disp_trackback_url'] = false;		// DO NOT Display the trackback URL if not requested
}

if( empty($pb) )
{	// Pingback not requested
	$params['disp_pingbacks'] = false;				// DO NOT Display the pingbacks if not requested
}

if( ! ($params['disp_comments'] || $params['disp_comment_form'] || $params['disp_trackbacks'] || $params['disp_trackback_url'] || $params['disp_pingbacks'] ) )
{	// Nothing more to do....
	return false;
}

echo '<a id="feedbacks"></a>';

$type_list = array();
$disp_title = array();

if( $params['disp_comments'] )
{	// We requested to display comments
	if( $Item->can_see_comments() )
	{ // User can see a comments
		$type_list[] = 'comment';
		if( $title = $Item->get_feedback_title( 'comments' ) )
		{
			$disp_title[] = $title;
		}
	}
	else
	{ // Use cannot see comments
		$params['disp_comments'] = false;
	}
	echo '<a id="comments"></a>';
}

if( $params['disp_trackbacks'] )
{
	$type_list[] = 'trackback';
	if( $title = $Item->get_feedback_title( 'trackbacks' ) )
	{
		$disp_title[] = $title;
	}
	echo '<a id="trackbacks"></a>';
}

if( $params['disp_pingbacks'] )
{
	$type_list[] = 'pingback';
	if( $title = $Item->get_feedback_title( 'pingbacks' ) )
	{
		$disp_title[] = $title;
	}
	echo '<a id="pingbacks"></a>';
}

if( $params['disp_trackback_url'] )
{ // We want to display the trackback URL:

	echo $params['before_section_title'];
	echo T_('Trackback address for this post');
	echo $params['after_section_title'];

	/*
	 * Trigger plugin event, which could display a captcha form, before generating a whitelisted URL:
	 */
	if( ! $Plugins->trigger_event_first_true( 'DisplayTrackbackAddr', array('Item' => & $Item, 'template' => '<code>%url%</code>') ) )
	{ // No plugin displayed a payload, so we just display the default:
		echo '<p class="trackback_url"><a href="'.$Item->get_trackback_url().'">'.T_('Trackback URL (right click and copy shortcut/link location)').'</a></p>';
	}
}


if( $params['disp_comments'] || $params['disp_trackbacks'] || $params['disp_pingbacks']  )
{
	if( empty($disp_title) )
	{	// No title yet
		if( $title = $Item->get_feedback_title( 'feedbacks', '', T_('Feedback awaiting moderation'), T_('Feedback awaiting moderation'), 'draft' ) )
		{ // We have some feedback awaiting moderation: we'll want to show that in the title
			$disp_title[] = $title;
		}
	}

	if( empty($disp_title) )
	{	// Still no title
		$disp_title[] = T_('No feedback yet');
	}

	echo $params['before_section_title'];
	echo implode( ', ', $disp_title);
	echo $params['after_section_title'];

	$CommentList = new CommentList2( $Blog, $Blog->get_setting('comments_per_page'), 'CommentCache', 'c_' );

	// Filter list:
	$CommentList->set_default_filters( array(
			'types' => $type_list,
			'statuses' => array ( 'published' ),
			'post_ID' => $Item->ID,
			'order' => $Blog->get_setting( 'comments_orderdir' ),
		) );

	$CommentList->load_from_Request();

	// Get ready for display (runs the query):
	$CommentList->display_init();

	// Set redir=no in order to open comment pages
	memorize_param( 'redir', 'string', '', 'no' );

	if( $Blog->get_setting( 'paged_comments' ) )
	{ // Prev/Next page navigation
		$CommentList->page_links( array(
				'page_url' => url_add_tail( $Item->get_permanent_url(), '#comments' ),
			) );
	}


	echo $params['comment_list_start'];
	/**
	 * @var Comment
	 */
	while( $Comment = & $CommentList->get_next() )
	{	// Loop through comments:

		// ------------------ COMMENT INCLUDED HERE ------------------
		skin_include( $params['comment_template'], array(
				'Comment'         => & $Comment,
			  'comment_start'   => $params['comment_start'],
			  'comment_end'     => $params['comment_end'],
				'link_to'		      => $params['link_to'],		// 'userpage' or 'userurl' or 'userurl>userpage' or 'userpage>userurl'
			) );
		// Note: You can customize the default item feedback by copying the generic
		// /skins/_item_comment.inc.php file into the current skin folder.
		// ---------------------- END OF COMMENT ---------------------

	}	// End of comment list loop.
	echo $params['comment_list_end'];


	if( $Blog->get_setting( 'paged_comments' ) )
	{ // Prev/Next page navigation
		$CommentList->page_links( array(
				'page_url' => url_add_tail( $Item->get_permanent_url(), '#comments' ),
			) );
	}

	// Restore "redir" param
	forget_param('redir');

	// _______________________________________________________________

	// Display count of comments to be moderated:
	$Item->feedback_moderation( 'feedbacks', '<div class="moderation_msg"><p>', '</p></div>', '',
			T_('This post has 1 feedback awaiting moderation... %s'),
			T_('This post has %d feedbacks awaiting moderation... %s') );

	// _______________________________________________________________

	if( $Blog->get_setting( 'feed_content' ) != 'none' )
	{ // fp: TODO; move this tesqt into feedback_feed_link()
		// Display link for comments feed:
		$Item->feedback_feed_link( '_rss2', '<div class="feedback_feed_msg"><p>', '</p></div>' );

	}

	// _______________________________________________________________

}

// ----------- Register for item's comment notification -----------
if( is_logged_in() && $Item->can_comment( NULL ) )
{
	global $DB, $htsrv_url;
	$not_subscribed = true;
	$creator_User = $Item->get_creator_User();

	if( $Blog->get_setting( 'allow_subscriptions' ) )
	{
		$sql = 'SELECT count( sub_user_ID ) FROM T_subscriptions
					WHERE sub_user_ID = '.$current_User->ID.' AND sub_coll_ID = '.$Blog->ID.' AND sub_comments <> 0';
		if( $DB->get_var( $sql ) > 0 )
		{
			echo '<p>'.T_( 'You are receiving notifications when anyone comments on any post.' );
			echo ' <a href="'.$Blog->gen_baseurl().'?disp=subs">'.T_( 'Click here to manage your subscriptions.' ).'</a></p>';
			$not_subscribed = false;
		}
	}

	if( $not_subscribed && ( $creator_User->ID == $current_User->ID ) && ( $current_User->get( 'notify' ) != 0 ) )
	{
		echo '<p>'.T_( 'This is your post. You are receiving notifications when anyone comments on your posts.' );
		echo ' <a href="'.$Blog->gen_baseurl().'?disp=subs">'.T_( 'Click here to manage your subscriptions.' ).'</a></p>';
		$not_subscribed = false;
	}
	if( $not_subscribed && $Blog->get_setting( 'allow_item_subscriptions' ) )
	{
		if( get_user_isubscription( $current_User->ID, $Item->ID ) )
		{
			echo '<p>'.T_( 'You will be notified by email when someone comments here.' );
			echo ' <a href="'.$htsrv_url.'isubs_update.php?p='.$Item->ID.'&amp;notify=0&amp;'.url_crumb( 'itemsubs' ).'">'.T_( 'Click here to unsubscribe.' ).'</a></p>';
		}
		else
		{
			echo '<p><a href="'.$htsrv_url.'isubs_update.php?p='.$Item->ID.'&amp;notify=1&amp;'.url_crumb( 'itemsubs' ).'">'.T_( 'Notify me by email when someone comments here.' ).'</a></p>';
		}
	}
}

// ------------------ COMMENT FORM INCLUDED HERE ------------------
skin_include( '_item_comment_form.inc.php', $params );
// Note: You can customize the default item feedback by copying the generic
// /skins/_item_comment_form.inc.php file into the current skin folder.
// ---------------------- END OF COMMENT FORM ---------------------


/*
 * $Log$
 * Revision 1.30  2011/05/19 17:47:07  efy-asimo
 * register for updates on a specific blog post
 *
 * Revision 1.29  2010/12/19 06:17:21  sam2kb
 * Fixed paged comments
 *
 * Revision 1.28  2010/11/07 18:50:45  fplanque
 * Added Comment::author2() with skins v2 style params.
 *
 * Revision 1.27  2010/10/13 14:07:56  efy-asimo
 * Optional paged comments in the front end
 *
 * Revision 1.26  2010/10/11 12:03:29  efy-asimo
 * Comments number on front office - fix
 *
 * Revision 1.25  2010/06/08 01:49:53  sam2kb
 * Paged comments in frontend
 *
 * Revision 1.24  2010/03/11 10:35:15  efy-asimo
 * Rewrite CommentList to CommentList2 task
 *
 * Revision 1.23  2010/02/08 17:56:12  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.22  2010/01/30 18:55:37  blueyed
 * Fix "Assigning the return value of new by reference is deprecated" (PHP 5.3)
 *
 * Revision 1.21  2009/09/28 23:58:16  blueyed
 * whitespace
 *
 * Revision 1.20  2009/08/27 12:24:27  tblue246
 * Added blog setting to display comments in ascending/descending order
 *
 * Revision 1.19  2009/07/02 18:08:53  fplanque
 * minor
 *
 * Revision 1.18  2009/07/02 09:37:15  leeturner2701
 * All default skins now fully support feeds being turned off
 *
 * Revision 1.17  2009/03/08 23:57:56  fplanque
 * 2009
 *
 * Revision 1.16  2008/06/30 20:51:03  blueyed
 * Fix indent
 *
 * Revision 1.15  2008/02/05 01:52:37  fplanque
 * enhanced comment form
 *
 * Revision 1.14  2008/01/21 09:35:42  fplanque
 * (c) 2008
 *
 * Revision 1.13  2007/12/22 16:41:05  fplanque
 * Modular feedback template.
 *
 * Revision 1.12  2007/12/18 23:51:33  fplanque
 * nofollow handling in comment urls
 *
 * Revision 1.11  2007/11/22 17:53:39  fplanque
 * filemanager display cleanup, especially in IE (not perfect)
 *
 * Revision 1.10  2007/11/03 21:04:28  fplanque
 * skin cleanup
 *
 * Revision 1.9  2007/11/02 01:55:57  fplanque
 * comment ratings
 *
 * Revision 1.8  2007/11/01 19:52:46  fplanque
 * better comment forms
 *
 * Revision 1.7  2007/09/28 02:18:10  fplanque
 * minor
 *
 * Revision 1.6  2007/09/26 21:54:00  fplanque
 * minor
 *
 * Revision 1.5  2007/09/16 22:07:06  fplanque
 * cleaned up feedback form
 *
 * Revision 1.4  2007/09/08 19:31:28  fplanque
 * cleanup of XML feeds for comments on individual posts.
 *
 * Revision 1.3  2007/06/24 22:26:34  fplanque
 * improved feedback template
 *
 * Revision 1.2  2007/06/24 01:05:31  fplanque
 * skin_include() now does all the template magic for skins 2.0.
 * .disp.php templates still need to be cleaned up.
 *
 * Revision 1.1  2007/06/23 22:09:30  fplanque
 * feedback and item content templates.
 * Interim check-in before massive changes ahead.
 *
 * Revision 1.91  2007/04/26 00:11:04  fplanque
 * (c) 2007
 *
 * Revision 1.90  2007/04/03 19:22:22  blueyed
 * Fixed WhiteSpace
 *
 * Revision 1.89  2007/03/18 01:39:55  fplanque
 * renamed _main.php to main.page.php to comply with 2.0 naming scheme.
 * (more to come)
 *
 * Revision 1.88  2007/01/26 04:49:17  fplanque
 * cleanup
 *
 * Revision 1.87  2007/01/18 22:28:53  fplanque
 * no unnecessary complexity
 *
 * Revision 1.86  2007/01/16 22:53:38  blueyed
 * TODOs
 *
 * Revision 1.85  2006/12/28 23:20:40  fplanque
 * added plugin event for displaying comment form toolbars
 * used by smilies plugin
 *
 * Revision 1.84  2006/12/17 23:42:39  fplanque
 * Removed special behavior of blog #1. Any blog can now aggregate any other combination of blogs.
 * Look into Advanced Settings for the aggregating blog.
 * There may be side effects and new bugs created by this. Please report them :]
 *
 * Revision 1.83  2006/11/20 22:15:30  blueyed
 * whitespace
 *
 * Revision 1.82  2006/10/23 22:19:03  blueyed
 * Fixed/unified encoding of redirect_to param. Use just rawurlencode() and no funky &amp; replacements
 *
 * Revision 1.81  2006/10/15 21:30:46  blueyed
 * Use url_rel_to_same_host() for redirect_to params.
 */
?>
