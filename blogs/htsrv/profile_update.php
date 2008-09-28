<?php
/**
 * This file updates the current user's profile!
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2008 by Francois PLANQUE - {@link http://fplanque.net/}
 * Parts of this file are copyright (c)2004-2006 by Daniel HAHLER - {@link http://thequod.de/contact}.
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
 * Daniel HAHLER grants Francois PLANQUE the right to license
 * Daniel HAHLER's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package htsrv
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE
 * @author blueyed: Daniel HAHLER
 *
 *
 * @todo integrate it into the skins to avoid ugly die() on error and confusing redirect on success.
 *
 * @version $Id$
 */

/**
 * Initialize everything:
 */
require_once dirname(__FILE__).'/../conf/_config.php';

require_once $inc_path.'_main.inc.php';

// Getting GET or POST parameters:
param( 'checkuser_id', 'integer', '' );
param( 'newuser_firstname', 'string', '' );
param( 'newuser_lastname', 'string', '' );
param( 'newuser_nickname', 'string', '' );
param( 'newuser_idmode', 'string', '' );
param( 'newuser_locale', 'string', $default_locale );
param( 'newuser_icq', 'string', '' );
param( 'newuser_aim', 'string', '' );
param( 'newuser_msn', 'string', '' );
param( 'newuser_yim', 'string', '' );
param( 'newuser_url', 'string', '' );
param( 'newuser_email', 'string', '' );
param( 'newuser_allow_msgform', 'integer', 0 ); // checkbox
param( 'newuser_notify', 'integer', 0 );        // checkbox
param( 'newuser_showonline', 'integer', 0 );    // checkbox
param( 'pass1', 'string', '' );
param( 'pass2', 'string', '' );

/**
 * Basic security checks:
 */
if( ! is_logged_in() )
{ // must be logged in!
	bad_request_die( T_('You are not logged in.') );
}

if( $checkuser_id != $current_User->ID )
{ // Can only edit your own profile
	bad_request_die( 'You are not logged in under the same account you are trying to modify.' );
}

if( $demo_mode && ($current_User->ID == 1 || $current_User->login == 'demouser') )
{
	bad_request_die( 'Demo mode: you can\'t edit the admin/demouser profile!<br />[<a href="javascript:history.go(-1)">'
		. T_('Back to profile') . '</a>]' );
}

/**
 * Additional checks:
 */
profile_check_params( array(
	'nickname' => $newuser_nickname,
	'icq' => $newuser_icq,
	'email' => $newuser_email,
	'url' => $newuser_url,
	'pass1' => $pass1,
	'pass2' => $pass2,
	'pass_required' => false ), $current_User );


if( $Messages->count('error') )
{
	header_content_type( 'text/html' );
	// TODO: dh> these error should get displayed with the profile form itself, or at least there should be a "real HTML page" here (without JS-backlink)
	$Messages->display( T_('Cannot update profile. Please correct the following errors:'),
		'[<a href="javascript:history.go(-1)">' . T_('Back to profile') . '</a>]' );
	exit(0);
}


// Do the update:

$updatepassword = '';
if( !empty($pass1) )
{
	$newuser_pass = md5($pass1);
	$current_User->set( 'pass', $newuser_pass );
}

$current_User->set( 'firstname', $newuser_firstname );
$current_User->set( 'lastname', $newuser_lastname );
$current_User->set( 'nickname', $newuser_nickname );
$current_User->set( 'icq', $newuser_icq );
$current_User->set_email( $newuser_email );
$current_User->set( 'url', $newuser_url );
$current_User->set( 'aim', $newuser_aim );
$current_User->set( 'msn', $newuser_msn );
$current_User->set( 'yim', $newuser_yim );
$current_User->set( 'idmode', $newuser_idmode );
$current_User->set( 'locale', $newuser_locale );
$current_User->set( 'allow_msgform', $newuser_allow_msgform );
$current_User->set( 'notify', $newuser_notify );
$current_User->set( 'showonline', $newuser_showonline );


// Set Messages into user's session, so they get restored on the next page (after redirect):
if( $current_User->dbupdate() )
{
	$Messages->add( T_('Your profile has been updated.'), 'success' );
}
else
{
	$Messages->add( T_('Your profile has not been changed.'), 'note' );
}


header_nocache();
// redirect Will save $Messages into Session:
header_redirect();

/*
 * $Log$
 * Revision 1.54  2008/09/28 08:06:03  fplanque
 * Refactoring / extended page level caching
 *
 * Revision 1.53  2008/02/19 11:11:16  fplanque
 * no message
 *
 * Revision 1.52  2008/01/21 09:35:23  fplanque
 * (c) 2008
 *
 * Revision 1.51  2007/11/24 17:34:14  blueyed
 * Add User->ID check for demo_mode where only login==demouser was checked (profile/subs update)
 *
 * Revision 1.50  2007/04/26 00:11:14  fplanque
 * (c) 2007
 *
 * Revision 1.49  2007/01/27 19:52:51  blueyed
 * Fixed charset when displaying errors
 *
 * Revision 1.48  2006/11/26 02:30:38  fplanque
 * doc / todo
 *
 * Revision 1.47  2006/11/24 18:27:22  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.46  2006/11/24 18:06:02  blueyed
 * Handle saving of $Messages centrally in header_redirect()
 *
 * Revision 1.45  2006/06/19 20:59:37  fplanque
 * noone should die anonymously...
 *
 * Revision 1.44  2006/04/22 02:36:38  blueyed
 * Validate users on registration through email link (+cleanup around it)
 *
 * Revision 1.43  2006/04/20 12:15:32  fplanque
 * no message
 *
 * Revision 1.42  2006/04/19 23:50:39  blueyed
 * Normalized Messages handling (error displaying and transport in Session)
 *
 * Revision 1.41  2006/04/19 20:13:48  fplanque
 * do not restrict to :// (does not catch subdomains, not even www.)
 *
 * Revision 1.40  2006/04/11 21:22:25  fplanque
 * partial cleanup
 *
 */
?>