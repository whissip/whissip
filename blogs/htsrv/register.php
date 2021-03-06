<?php
/**
 * Register a new user.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}
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
 * @author blueyed: Daniel HAHLER
 * @author fplanque: Francois PLANQUE
 *
 * @version $Id$
 */

/**
 * Includes:
 */
require_once dirname(__FILE__).'/../conf/_config.php';

require_once $inc_path.'_main.inc.php';

// Login is not required on the register page:
$login_required = false;

// Check if country is required
$registration_require_country = (bool)$Settings->get('registration_require_country');
// Check if gender is required
$registration_require_gender = $Settings->get('registration_require_gender');

param( 'action',  'string', '' );
param( 'login',   'string', '' );
param( 'email',   'string', '' );
param( 'country', 'integer', '' );
param( 'gender',  'string', NULL );
param( 'source', 'string', '' );
param( 'redirect_to', 'string', '' ); // do not default to $admin_url; "empty" gets handled better in the end (uses $blogurl, if no admin perms).


if( ! $Settings->get('newusers_canregister') )
{
	$action = 'disabled';
}

switch( $action )
{
	case 'register':
		/*
		 * Do the registration:
		 */
		param( 'pass1', 'string', '' );
		param( 'pass2', 'string', '' );

		// Call plugin event to allow catching input in general and validating own things from DisplayRegisterFormFieldset event
		$Plugins->trigger_event( 'RegisterFormSent', array(
				'login'   => & $login,
				'email'   => & $email,
				'country' => & $country,
				'gender'  => & $gender,
				'locale'  => & $locale,
				'pass1'   => & $pass1,
				'pass2'   => & $pass2,
			) );

		if( $Messages->has_errors() )
		{ // a Plugin has added an error
			break;
		}

		// Set params:
		$paramsList = array(
			'login'   => $login,
			'pass1'   => $pass1,
			'pass2'   => $pass2,
			'email'   => $email,
			'pass_required' => true );

		if( $registration_require_country )
		{
			$paramsList['country'] = $country;
		}

		if( $registration_require_gender == 'required' )
		{
			$paramsList['gender'] = $gender;
		}

		// Check profile params:
		profile_check_params( $paramsList );

		// We want all logins to be lowercase to guarantee uniqueness regardless of the database case handling for UNIQUE indexes:
		$login = evo_strtolower( $login );

		$UserCache = & get_UserCache();
		if( $UserCache->get_by_login( $login ) )
		{ // The login is already registered
			param_error( 'login', sprintf( T_('The login &laquo;%s&raquo; is already registered, please choose another one.'), $login ) );
		}

		if( $Messages->has_errors() )
		{
			break;
		}

		$DB->begin();

		$new_User = new User();
		$new_User->set( 'login', $login );
		$new_User->set( 'pass', md5($pass1) ); // encrypted
		$new_User->set( 'nickname', $login );
		$new_User->set( 'ctry_ID', $country );
		$new_User->set( 'gender', $gender );
		$new_User->set( 'source', $source );
		$new_User->set_email( $email );
		$new_User->set( 'ip', $Hit->IP );
		$new_User->set( 'domain', $Hit->get_remote_host( true ) );
		$new_User->set( 'browser', substr( $Hit->get_user_agent(), 0 , 200 ) );
		$new_User->set_datecreated( $localtimenow );
		$new_User->set( 'locale', $locale );
		$newusers_grp_ID = $Settings->get('newusers_grp_ID');
		// echo $newusers_grp_ID;
		$GroupCache = & get_GroupCache();
		$new_user_Group = & $GroupCache->get_by_ID( $newusers_grp_ID );
		// echo $new_user_Group->disp('name');
		$new_User->set_Group( $new_user_Group );

 		// Determine if the user must validate before using the system:
		$new_User->set( 'validated', ! $Settings->get('newusers_mustvalidate') );

		$new_User->dbinsert();

		$new_user_ID = $new_User->ID; // we need this to "rollback" user creation if there's no DB transaction support

		// TODO: Optionally auto create a blog (handle this together with the LDAP plugin)

		// TODO: Optionally auto assign rights

		// Actions to be appended to the user registration transaction:
		if( $Plugins->trigger_event_first_false( 'AppendUserRegistrTransact', array( 'User' => & $new_User ) ) )
		{
			// TODO: notify the plugins that have been called before about canceling of the event?!
			$DB->rollback();

			// Delete, in case there's no transaction support:
			$new_User->dbdelete( $Debuglog );

			$Messages->add( T_('No user account has been created!'), 'error' );
			break; // break out to _reg_form.php
		}

		// User created:
		$DB->commit();

		$UserCache->add( $new_User );

		// Send email to admin (using his locale):
		/**
		 * @var User
		 */
		$AdminUser = & $UserCache->get_by_ID( 1 );
		locale_temp_switch( $AdminUser->get( 'locale' ) );

		$message  = T_('New user registration on your blog').":\n"
							."\n"
							.T_('Login:')." $login\n"
							.T_('Email').": $email\n"
							."\n"
							.T_('Edit user').': '.$admin_url.'?ctrl=user&user_tab=profile&user_ID='.$new_User->ID."\n";

		send_mail( $AdminUser->get( 'email' ), NULL, T_('New user registration on your blog'), $message, $notify_from ); // ok, if this may fail..

		locale_restore_previous();

		$Plugins->trigger_event( 'AfterUserRegistration', array( 'User' => & $new_User ) );


		if( $Settings->get('newusers_mustvalidate') )
		{ // We want that the user validates his email address:
			if( $new_User->send_validate_email($redirect_to) )
			{
				$Messages->add( T_('An email has been sent to your email address. Please click on the link therein to validate your account.'), 'success' );
			}
			else
			{
				$Messages->add( T_('Sorry, the email with the link to validate and activate your password could not be sent.')
					.'<br />'.T_('Possible reason: the PHP mail() function may have been disabled on the server.'), 'error' );
				// fp> TODO: allow to enter a different email address (just in case it's that kind of problem)
			}
		}

		// Autologin the user. This is more comfortable for the user and avoids
		// extra confusion when account validation is required.
		$Session->set_User( $new_User );

		// Display confirmation screen:
		require $adminskins_path.'login/_reg_complete.main.php';

		exit(0);
		break;


	case 'disabled':
		/*
		 * Registration disabled:
		 */
		require $adminskins_path.'login/_reg_disabled.main.php';

		exit(0);
}


/*
 * Default: registration form:
 */
// Display reg form:
require $adminskins_path.'login/_reg_form.main.php';


/*
 * $Log$
 * Revision 1.109  2011/05/11 07:11:51  efy-asimo
 * User settings update
 *
 * Revision 1.108  2011/02/17 14:56:38  efy-asimo
 * Add user source param
 *
 * Revision 1.107  2011/02/15 05:31:53  sam2kb
 * evo_strtolower mbstring wrapper for strtolower function
 *
 * Revision 1.106  2010/11/25 15:16:34  efy-asimo
 * refactor $Messages
 *
 * Revision 1.105  2010/11/24 16:05:52  efy-asimo
 * User country and gender options modifications
 *
 * Revision 1.104  2010/11/24 14:55:30  efy-asimo
 * Add user gender
 *
 * Revision 1.103  2010/02/08 17:51:14  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.102  2010/01/30 18:55:15  blueyed
 * Fix "Assigning the return value of new by reference is deprecated" (PHP 5.3)
 *
 * Revision 1.101  2009/11/21 13:31:57  efy-maxim
 * 1. users controller has been refactored to users and user controllers
 * 2. avatar tab
 * 3. jQuery to show/hide custom duration
 *
 * Revision 1.100  2009/09/26 12:00:42  tblue246
 * Minor/coding style
 *
 * Revision 1.99  2009/09/25 07:32:51  efy-cantor
 * replace get_cache to get_*cache
 *
 * Revision 1.98  2009/09/22 16:37:59  efy-bogdan
 * Require country checkbox added
 *
 * Revision 1.97  2009/09/16 06:55:13  efy-bogdan
 * Require country checkbox added
 *
 * Revision 1.96  2009/05/28 20:57:23  blueyed
 * Rolling back additional activation of locale in htsrv (register, login). http://forums.b2evolution.net/viewtopic.php?p=92006#92006
 *
 * Revision 1.95  2009/03/21 23:00:21  fplanque
 * minor
 *
 * Revision 1.94  2009/03/21 01:00:41  waltercruz
 * Fixing http://forums.b2evolution.net//viewtopic.php?p=89122
 *
 * Revision 1.93  2009/03/08 23:57:37  fplanque
 * 2009
 *
 * Revision 1.92  2009/03/04 00:10:42  blueyed
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
 * Revision 1.91  2008/04/13 15:15:59  fplanque
 * attempt to fix email headers for non latin charsets
 *
 * Revision 1.90  2008/02/19 11:11:16  fplanque
 * no message
 *
 * Revision 1.89  2008/01/21 09:35:23  fplanque
 * (c) 2008
 *
 * Revision 1.88  2008/01/19 16:08:21  fplanque
 * minor
 *
 * Revision 1.87  2008/01/19 14:17:00  yabs
 * bugfix : http://forums.b2evolution.net/viewtopic.php?t=13848
 *
 * Revision 1.86  2007/06/25 10:58:50  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.85  2007/06/19 23:10:25  blueyed
 * Better redirect_to handling/fallback
 *
 * Revision 1.84  2007/04/26 00:11:14  fplanque
 * (c) 2007
 *
 * Revision 1.83  2007/02/18 20:05:47  blueyed
 * Use param_error() for "login already exists" error message
 *
 * Revision 1.82  2007/02/13 21:03:40  blueyed
 * Improved login/register/validation process:
 * - "Your account has been validated already." if an account had already been validated
 * - "We have already sent you %d email(s) with a validation link." note
 * - Autologin the user after he has registered (he just typed his credentials!)
 *
 * Revision 1.81  2007/01/28 23:58:46  blueyed
 * - Added hook CommentFormSent
 * - Re-ordered comment_post.php to: init, validate, process
 * - RegisterFormSent hook can now filter the form values in a clean way
 *
 * Revision 1.80  2007/01/27 19:57:12  blueyed
 * Use param_error() in profile_check_params()
 *
 * Revision 1.79  2007/01/25 22:03:37  blueyed
 * Move hardcoded "$login_required = false" after include of _main.inc.php, so that it cannot get overridden in main init. There is no use case for this.
 *
 * Revision 1.78  2007/01/16 00:44:42  fplanque
 * don't use $admin_email in  the app
 *
 * Revision 1.77  2006/12/06 22:30:07  fplanque
 * Fixed this use case:
 * Users cannot register themselves.
 * Admin creates users that are validated by default. (they don't have to validate)
 * Admin can invalidate a user. (his email, address actually)
 *
 * Revision 1.76  2006/11/24 18:27:22  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.75  2006/11/19 16:17:37  blueyed
 * Login cannot be required on the register page
 *
 * Revision 1.74  2006/09/10 18:14:24  blueyed
 * Do report error, if sending email fails in message_send.php (msgform and opt-out)
 *
 * Revision 1.73  2006/08/19 08:50:25  fplanque
 * moved out some more stuff from main
 *
 * Revision 1.72  2006/08/19 07:56:29  fplanque
 * Moved a lot of stuff out of the automatic instanciation in _main.inc
 *
 * Revision 1.71  2006/07/17 01:33:13  blueyed
 * Fixed account validation by email for users who registered themselves
 *
 * Revision 1.70  2006/06/18 01:14:03  blueyed
 * lazy instantiate user's group; normalisation
 *
 * Revision 1.69  2006/05/19 18:15:04  blueyed
 * Merged from v-1-8 branch
 *
 * Revision 1.68  2006/05/01 04:21:50  blueyed
 * todo
 *
 * Revision 1.67  2006/04/24 21:01:07  blueyed
 * just delete
 *
 * Revision 1.66  2006/04/24 20:52:30  fplanque
 * no message
 *
 * Revision 1.65  2006/04/24 17:52:24  blueyed
 * Manually delete user if no transaction-support
 *
 * Revision 1.64  2006/04/24 15:43:35  fplanque
 * no message
 *
 * Revision 1.63  2006/04/22 02:36:38  blueyed
 * Validate users on registration through email link (+cleanup around it)
 *
 * Revision 1.62  2006/04/21 17:05:08  blueyed
 * cleanup
 *
 * Revision 1.61  2006/04/20 22:24:07  blueyed
 * plugin hooks cleanup
 *
 * Revision 1.60  2006/04/19 20:13:48  fplanque
 * do not restrict to :// (does not catch subdomains, not even www.)
 *
 * Revision 1.59  2006/04/11 21:22:25  fplanque
 * partial cleanup
 *
 */
?>
