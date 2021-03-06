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
 *
 * @package evoskins
 * @subpackage basic
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

global $c, $cookie_name, $cookie_email, $cookie_url, $comment_allowed_tags;
global $comments_use_autobr;

// Display filters:
// You can change these and call this template multiple time if you want to separate comments from trackbacks
$disp_comments = 1;					// Display the comments if requested
$disp_comment_form = 1;			// Display the comments form if comments requested
$disp_trackbacks = 1;				// Display the trackbacks if requested
$disp_trackback_url = 1;		// Display the trackbal URL if trackbacks requested
$disp_pingbacks = 1;        // pingbacks (deprecated)



if( empty($c) )
{	// Comments not requested
	$disp_comments = 0;					// DO NOT Display the comments if not requested
	$disp_comment_form = 0;			// DO NOT Display the comments form if not requested
}

if( empty($tb) || (!$Blog->get( 'allowtrackbacks' )) )
{	// Trackback not requested or not allowed
	$disp_trackbacks = 0;				// DO NOT Display the trackbacks if not requested
	$disp_trackback_url = 0;		// DO NOT Display the trackback URL if not requested
}

if( empty($pb) )
{	// Pingback not requested
	$disp_pingbacks = 0;				// DO NOT Display the pingbacks if not requested
}

?>
<a id="feedbacks"></a>
<?php

if( ! ($disp_comments || $disp_comment_form || $disp_trackbacks || $disp_trackback_url || $disp_pingbacks ) )
{	// Nothing more to do....
	return false;
}

$type_list = array();
$disp_title = array();
if(  $disp_comments )
{	// We requested to display comments
	if( $Item->can_see_comments() )
	{ // User can see a comments
		$type_list[] = 'comment';
		$disp_title[] = T_("Comments");
	}
	else
	{ // Use cannot see comments
		$disp_comments = false;
	}
	?>
	<a id="comments"></a>
<?php }
if( $disp_trackbacks ) {
	$type_list[] = 'trackback';
	$disp_title[] = T_("Trackbacks"); ?>
	<a id="trackbacks"></a>
<?php }
if( $disp_pingbacks ) {
	$type_list[] = 'pingback';
	$disp_title[] = T_("Pingbacks"); ?>
	<a id="pingbacks"></a>
<?php } ?>

<?php
if( $disp_trackback_url )
{	// We want to display the trackback URL:
	?>
	<h4><?php echo T_('Trackback address for this post:') ?></h4>
	<?php
	/*
	 * Trigger plugin event, which could display a captcha form, before generating a whitelisted URL:
	 * fp> What's the difference between a "whitelisted" URL and a normal trackback URL ??
	 */
	if( ! $Plugins->trigger_event_first_true( 'DisplayTrackbackAddr', array('Item' => & $Item, 'template' => '<code>%url%</code>') ) )
	{ // No plugin displayed a payload, so we just display the default:
		?>
		<code><?php $Item->trackback_url() ?></code>
		<?php
	}
}
?>

<?php
if( $disp_comments || $disp_trackbacks || $disp_pingbacks  )
{
	if( $disp_comments )
?>

<!-- Title for comments, tbs, pbs... -->
<h4><?php echo implode( ", ", $disp_title) ?>:</h4>

<?php
$CommentList = new CommentList2( $Blog );

// Filter list:
$CommentList->set_filters( array(
		'types' => $type_list,
		'statuses' => array ( 'published' ),
		'post_ID' => $Item->ID,
		'order' => $Blog->get_setting( 'comments_orderdir' ),
	) );

// Get ready for display (runs the query):
$CommentList->display_init();

$CommentList->display_if_empty( array(
		'msg_empty' => sprintf( /* TRANS: NO comments/trackbacks/pingbacks/ FOR THIS POST... */
				T_('No %s for this post yet...'), implode( "/", $disp_title) ),
	 ) );

while( $Comment = & $CommentList->get_next() )
{	// Loop through comments:
	?>
	<!-- ========== START of a COMMENT/TB/PB ========== -->
	<?php $Comment->anchor() ?>
	<h5>
	<?php
		switch( $Comment->get( 'type' ) )
		{
			case 'comment': // Display a comment:
				echo T_('Comment from:') ?>
				<?php $Comment->author() ?>
				<?php $Comment->author_url( '', ' &middot; ', '' ) ?>
				<?php break;

			case 'trackback': // Display a trackback:
				echo T_('Trackback from:') ?>
				<?php $Comment->author( '', '#', '', '#', 'htmlbody', true ) ?>
				<?php break;

			case 'pingback': // Display a pingback:
				echo T_('Pingback from:') ?>
				<?php $Comment->author( '', '#', '', '#', 'htmlbody', true ) ?>
				<?php break;
		}

		$Comment->edit_link( ' &middot; ' ) // Link to backoffice for editing
	?>
	</h5>
	<blockquote>
		<small><?php $Comment->date() ?> @ <?php $Comment->time( 'H:i' ) ?></small>
		<div><?php $Comment->content() ?></div>
	</blockquote>
	<!-- ========== END of a COMMENT/TB/PB ========== -->
	<?php
}

if( $disp_comment_form )
{	// We want to display the comments form:
	if( $Item->can_comment() )
	{ // User can leave a comment
	?>
	<h4><?php echo T_('Leave a comment') ?>:</h4>

	<?php
		$comment_author = isset($_COOKIE[$cookie_name]) ? trim($_COOKIE[$cookie_name]) : '';
		$comment_author_email = isset($_COOKIE[$cookie_email]) ? trim($_COOKIE[$cookie_email]) : '';
		$comment_author_url = isset($_COOKIE[$cookie_url]) ? trim($_COOKIE[$cookie_url]) : '';
	?>

	<!-- form to add a comment -->
	<form action="<?php echo $htsrv_url ?>comment_post.php" method="post">

		<input type="hidden" name="comment_post_ID" value="<?php echo $Item->ID() ?>" />
		<input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars(url_rel_to_same_host(regenerate_url('','','','&'), $htsrv_url)) ?>" />

	<table>
		<?php
		if( is_logged_in() )
		{ // User is logged in:
			?>
			<tr valign="top" bgcolor="#eeeeee">
				<td align="right"><strong><?php echo T_('User') ?>:</strong></td>
				<td align="left">
					<strong><?php $current_User->preferred_name()?></strong>
					<?php user_profile_link( ' [', ']', T_('Edit profile') ) ?>
					</td>
			</tr>
			<?php
		}
		else
		{ // User is not loggued in:
			?>
			<tr valign="top" bgcolor="#eeeeee">
				<td align="right"><label for="author"><strong><?php echo T_('Name') ?>:</strong></label></td>
				<td align="left"><input type="text" name="u" id="author" value="<?php echo $comment_author ?>" size="40" tabindex="1" /></td>
			</tr>

			<tr valign="top" bgcolor="#eeeeee">
				<td align="right"><label for="email"><strong><?php echo T_('Email') ?>:</strong></label></td>
				<td align="left"><input type="text" name="i" id="email" value="<?php echo $comment_author_email ?>" size="40" tabindex="2" /><br />
					<small><?php echo T_('Your email address will <strong>not</strong> be displayed on this site.') ?></small>
				</td>
			</tr>

			<tr valign="top" bgcolor="#eeeeee">
				<td align="right"><label for="url"><strong><?php echo T_('Site/Url') ?>:</strong></label></td>
				<td align="left"><input type="text" name="o" id="url" value="<?php echo $comment_author_url ?>" size="40" tabindex="3" /><br />
					<small><?php echo T_('Your URL will be displayed.') ?></small>
				</td>
			</tr>
			<?php
			}
		?>

		<tr valign="top" bgcolor="#eeeeee">
			<td align="right"><label for="comment"><strong><?php echo T_('Comment text') ?>:</strong></label></td>
			<td align="left" width="450"><textarea cols="50" rows="12" name="p" id="comment" tabindex="4"></textarea><br />
				<small><?php echo T_('Allowed XHTML tags'), ': ', htmlspecialchars(str_replace( '><',', ', $comment_allowed_tags)) ?></small>
			</td>
		</tr>

		<tr valign="top" bgcolor="#eeeeee">
			<td align="right"><strong><?php echo T_('Options') ?>:</strong></td>
			<td align="left">

			<?php if(substr($comments_use_autobr,0,4) == 'opt-') { ?>
				<input type="checkbox" name="comment_autobr" value="1" <?php if($comments_use_autobr == 'opt-out') echo ' checked="checked"' ?> tabindex="6" id="comment_autobr" /> <label for="comment_autobr"><strong><?php echo T_('Auto-BR') ?></strong></label> <small>(<?php echo T_('Line breaks become &lt;br /&gt;') ?>)</small><br />
			<?php }
			if( ! is_logged_in() )
			{ // User is not logged in:
				?>
				<input type="checkbox" name="comment_cookies" value="1" checked="checked" tabindex="7" id="comment_cookies" /> <label for="comment_cookies"><strong><?php echo T_('Remember me') ?></strong></label> <small><?php echo T_('(Set cookies for name, email &amp; url)') ?></small>
				<?php
			} ?>
			</td>
		</tr>

		<tr valign="top" bgcolor="#eeeeee">
			<td colspan="2" align="center">
				<input type="submit" name="submit" value="<?php echo T_('Send comment') ?>" tabindex="8" />
			</td>
		</tr>
	</table>

	</form>
<?php
	}
}
?>

<?php
}
?>
