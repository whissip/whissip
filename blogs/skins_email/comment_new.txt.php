<?php
/**
 * This is sent to ((Users)) and/or ((Moderators)) to notify them that a new comment has been posted.
 *
 * For more info about email skins, see: http://b2evolution.net/man/themes-templates-skins/email-skins/
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2014 by Francois Planque - {@link http://fplanque.com/}
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

// ---------------------------- EMAIL HEADER INCLUDED HERE ----------------------------
emailskin_include( '_email_header.inc.txt.php', $params );
// ------------------------------- END OF EMAIL HEADER --------------------------------

global $htsrv_url, $admin_url;

// Default params:
$params = array_merge( array(
		'notify_full' => false,
		'Comment'     => NULL,
		'Blog'        => NULL,
		'Item'        => NULL,
		'author_ID'   => NULL,
		'author_name' => '',
		'notify_type' => '',
	), $params );


$Comment = $params['Comment'];
$Blog = $params['Blog'];
$Item = $params['Item'];

if( $params['notify_full'] )
{	// Long format notification:
	$notify_message = T_('New comment').': '
		.$Comment->get_permanent_url( '&' )."\n"
		// TODO: fp> We MAY want to force a short URL and avoid it to wrap on a new line in the mail which may prevent people from clicking
		.T_('Blog').': '.$Blog->get('shortname')."\n"
		// Mail bloat: .' ( '.str_replace('&amp;', '&', $Blog->gen_blogurl())." )\n"
		.T_('Post').': '.$Item->get('title')."\n";
		// Mail bloat: .' ( '.str_replace('&amp;', '&', $Item->get_permanent_url())." )\n";
		// TODO: fp> We MAY want to force short URL and avoid it to wrap on a new line in the mail which may prevent people from clicking

	switch( $Comment->type )
	{
		case 'trackback':
			$user_domain = gethostbyaddr($Comment->author_IP);
			$notify_message .= T_('Website').": $Comment->author (IP: $Comment->author_IP, $user_domain)\n";
			$notify_message .= T_('Url').": $Comment->author_url\n";
			break;

		default:
			if( $Comment->get_author_User() )
			{ // Comment from a registered user:
				$notify_message .= T_('Author').': '.$Comment->author_User->get('preferredname').' ('.$Comment->author_User->get('login').")\n";
			}
			else
			{ // Comment from visitor:
				$user_domain = gethostbyaddr($Comment->author_IP);
				$notify_message .= T_('Author').": $Comment->author (IP: $Comment->author_IP, $user_domain)\n";
				$notify_message .= T_('Email').": $Comment->author_email\n";
				$notify_message .= T_('Url').": $Comment->author_url\n";
			}
	}

	if( !empty( $Comment->rating ) )
	{
		$notify_message .= T_('Rating').': '.$Comment->rating.'/5'."\n";
	}

	if( $params['notify_type'] == 'moderator' )
	{
		$notify_message .= T_('Status').': '.$Comment->get( 't_status' )."\n";
	}

	// Content:
	$notify_message .= $Comment->get('content')."\n";

	// Attachments:
	$LinkCache = & get_LinkCache();
	$comment_links = $LinkCache->get_by_comment_ID( $Comment->ID );
	if( !empty( $comment_links ) )
	{
		$notify_message .= "\n".T_('Attachments').":\n";
		foreach( $comment_links as $Link )
		{
			if( $File = $Link->get_File() )
			{
				$notify_message .= ' - '.$File->get_name().': '.$File->get_url()."\n";
			}
		}
		$notify_message .= "\n";
	}
}
else
{	// Shot format notification:
	$notify_message = sprintf( T_( '%s posted a new comment on %s in %s.' ), $params['author_name'], '"'.$Item->get('title').'"', '"'.$Blog->get('shortname').'"' )."\n\n";
	$notify_message .= T_( 'To read the full content of the comment click here:' ).' '
					.$Comment->get_permanent_url( '&' )."\n";
					// TODO: fp> We MAY want to force a short URL and avoid it to wrap on a new line in the mail which may prevent people from clicking
	if( $params['notify_type'] == 'moderator' )
	{
		$notify_message .= "\n"
						.T_('Status').': '.$Comment->get( 't_status' )."\n"
						.T_( 'This is a short form moderation message. To make these emails more useful for quick moderation, ask the administrator to send you long form moderation messages instead.' )
						."\n";
	}
}

$notify_message .= "\n\n";

// add unsubscribe and edit links
$params['unsubscribe_text'] = '';
if( $params['notify_type'] == 'moderator' )
{ // moderation email
	if( ( $Blog->get_setting( 'comment_quick_moderation' ) != 'never' ) && ( !empty( $Comment->secret ) ) )
	{ // quick moderation is permitted, and comment secret was set
		$notify_message .= T_('Quick moderation').': '.'$secret_content_start$'.$htsrv_url.'comment_review.php?cmt_ID='.$Comment->ID.'&secret='.$Comment->secret.'$secret_content_end$'."\n\n";
	}
	$notify_message .= T_('Edit comment').': '.$admin_url.'?ctrl=comments&action=edit&comment_ID='.$Comment->ID."\n\n";
	$params['unsubscribe_text'] = T_( 'You are a moderator in this blog, and you are receiving notifications when a comments may need moderation.' )."\n";
	$params['unsubscribe_text'] .= T_( 'If you don\'t want to receive any more notifications about comment moderation, click here' ).': '
						.$htsrv_url.'quick_unsubscribe.php?type=comment_moderator&user_ID=$user_ID$&key=$unsubscribe_key$';
}
else if( $params['notify_type'] == 'blog_subscription' )
{ // blog subscription
	$params['unsubscribe_text'] = T_( 'You are receiving notifications when anyone comments on any post.' )."\n";
	$params['unsubscribe_text'] .= T_( 'If you don\'t want to receive any more notifications on this blog, click here' ).': '
						.$htsrv_url.'quick_unsubscribe.php?type=coll_comment&coll_ID='.$Blog->ID.'&user_ID=$user_ID$&key=$unsubscribe_key$';
	// subscribers are not allowed to see comment author email
}
else if( $params['notify_type'] == 'item_subscription' )
{ // item subscription
	$params['unsubscribe_text'] = T_( 'You are receiving notifications when anyone comments on this post.' )."\n";
	$params['unsubscribe_text'] .= T_( 'If you don\'t want to receive any more notifications on this post, click here' ).': '
						.$htsrv_url.'quick_unsubscribe.php?type=post&post_ID='.$Item->ID.'&user_ID=$user_ID$&key=$unsubscribe_key$';
	// subscribers are not allowed to see comment author email
}
else if( $params['notify_type'] == 'creator' )
{ // user is the creator of the post
	$params['unsubscribe_text'] = T_( 'This is your post. You are receiving notifications when anyone comments on your posts.' )."\n";
	$params['unsubscribe_text'] .= T_( 'If you don\'t want to receive any more notifications on your posts, click here' ).': '
						.$htsrv_url.'quick_unsubscribe.php?type=creator&user_ID=$user_ID$&key=$unsubscribe_key$';
}

echo $notify_message;

// ---------------------------- EMAIL FOOTER INCLUDED HERE ----------------------------
emailskin_include( '_email_footer.inc.txt.php', $params );
// ------------------------------- END OF EMAIL FOOTER --------------------------------
?>