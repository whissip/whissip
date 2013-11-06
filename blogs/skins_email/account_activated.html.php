<?php
/**
 * This is sent to ((Admins)) to notify them that a new ((User)) account has been activated.
 *
 * For more info about email skins, see: http://b2evolution.net/man/themes-templates-skins/email-skins/
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2013 by Francois Planque - {@link http://fplanque.com/}
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

// ---------------------------- EMAIL HEADER INCLUDED HERE ----------------------------
emailskin_include( '_email_header.inc.html.php', $params );
// ------------------------------- END OF EMAIL HEADER --------------------------------

global $Settings, $UserSettings, $admin_url, $htsrv_url;

// Default params:
$params = array_merge( array(
		'User' => NULL,
		'activated_by_admin' => '',// Login of admin which activated current user account
	), $params );


$activated_User = $params['User'];

echo '<p>';
if( empty( $params['activated_by_admin'] ) )
{ // Current user activated own account
	echo T_('New user account activated').':';
}
else
{ // Admin activated current user account
	printf( T_('New user account activated by %s'), $params['activated_by_admin'] ).':';
}
echo '</p>'."\n";

echo '<table class="email_table">'."\n";
echo '<tr><th>'.T_('Login').':</th><td>'.$activated_User->get_colored_login( array( 'mask' => '$avatar$ $login$' ) ).'</td></tr>'."\n";
echo '<tr><th>'.T_('Email').':</th><td>'.$activated_User->email.'</td></tr>'."\n";

if( $activated_User->ctry_ID > 0 )
{ // Country field is defined
	load_class( 'regional/model/_country.class.php', 'Country' );
	echo '<tr><th>'.T_('Country').': </th><td>'.$activated_User->get_country_name().'</td></tr>'."\n";
}

if( $activated_User->firstname != '' )
{ // First name is defined
	echo '<tr><th>'.T_('First name').':</th><td>'.$activated_User->firstname.'</td></tr>'."\n";
}

if( $activated_User->gender == 'M' )
{ // Gender is Male
	echo '<tr><th>'.T_('I am').':</th><td>'.T_('A man').'</td></tr>'."\n";
}
else if( $activated_User->gender == 'F' )
{ // Gender is Female
	echo '<tr><th>'.T_('I am').':</th><td>'.T_('A woman').'</td></tr>'."\n";
}

if( $Settings->get( 'registration_ask_locale' ) && $activated_User->locale != '' )
{ // Locale field is defined
	global $locales;
	echo '<tr><th>'.T_('Locale').':</th><td>'.$locales[$activated_User->locale]['name'].'</td></tr>'."\n";
}

if( !empty( $activated_User->source ) )
{ // Source is defined
	echo '<tr><th>'.T_('Registration Source').':</th><td>'.$activated_User->source.'</td></tr>'."\n";
}

$registration_trigger_url = $UserSettings->get( 'registration_trigger_url', $activated_User->ID );
if( !empty( $registration_trigger_url ) )
{ // Trigger page
	echo '<tr><th>'.T_('Registration Trigger Page').':</th><td>'.get_link_tag( $registration_trigger_url ).'</td></tr>'."\n";
}

$initial_blog_ID = $UserSettings->get( 'initial_blog_ID', $activated_User->ID );
if( !empty( $initial_blog_ID ) )
{ // Hit info
	echo '<tr><th>'.T_('Initial page').':</th><td>'.T_('Blog')." ".$UserSettings->get( 'initial_blog_ID', $activated_User->ID )." - ".$UserSettings->get( 'initial_URI', $activated_User->ID ).'</td></tr>'."\n";
	echo '<tr><th>'.T_('Initial referer').':</th><td>'.get_link_tag( $UserSettings->get( 'initial_referer', $activated_User->ID ) ).'</td></tr>'."\n";
}

echo '</table>'."\n";

// Buttons:
echo '<div class="buttons">'."\n";
echo get_link_tag( $admin_url.'?ctrl=user&user_tab=profile&user_ID='.$activated_User->ID, T_('Edit User'), 'button_yellow' )."\n";
echo get_link_tag( $admin_url.'?ctrl=users&action=show_recent', T_('View recent registrations'), 'button_gray' )."\n";
echo "</div>\n";

// Footer vars:
$params['unsubscribe_text'] = T_( 'If you don\'t want to receive any more notification when an account was activated by email, click here:' )
			.' <a href="'.$htsrv_url.'quick_unsubscribe.php?type=account_activated&user_ID=$user_ID$&key=$unsubscribe_key$">'
			.T_('instant unsubscribe').'</a>.';

// ---------------------------- EMAIL FOOTER INCLUDED HERE ----------------------------
emailskin_include( '_email_footer.inc.html.php', $params );
// ------------------------------- END OF EMAIL FOOTER --------------------------------
?>