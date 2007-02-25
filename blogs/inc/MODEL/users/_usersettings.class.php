<?php
/**
 * This file implements the UserSettings class which handles user_ID/name/value triplets.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2006 by Francois PLANQUE - {@link http://fplanque.net/}
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
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE
 * @author blueyed: Daniel HAHLER
 *
 * @version $Id$
 *
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * Includes
 */
require_once dirname(__FILE__).'/../settings/_abstractsettings.class.php';

/**
 * Class to handle the settings for users
 *
 * @package evocore
 */
class UserSettings extends AbstractSettings
{
	/**
	 * The default settings to use, when a setting is not given
	 * in the database.
	 *
	 * @todo Allow overriding from /conf/_config_TEST.php?
	 * @access protected
	 * @var array
	 */
	var $_defaults = array(
		'action_icon_threshold' => 3,
		'action_word_threshold' => 3,
		'display_icon_legend' => 0,
		'control_form_abortions' => 1,
		'focus_on_first_input' => 0,			// TODO: fix sideeffect when pressing F5
		'pref_browse_tab' => 'full',
		'pref_edit_tab' => 'simple',

		'fm_imglistpreview' => 1,
		'fm_showdate'       => 'compact',
		'fm_allowfiltering' => 'simple',

		'blogperms_layout' => 'default', // selected view in blog (user/group) perms

		'login_multiple_sessions' => 0, 	// allow multiple concurrent sessions?
	);


	/**
	 * Constructor
	 */
	function UserSettings()
	{ // constructor
		parent::AbstractSettings( 'T_usersettings', array( 'uset_user_ID', 'uset_name' ), 'uset_value', 1 );
	}


	/**
	 * Get a setting from the DB user settings table
	 *
	 * @param string name of setting
	 * @param integer User ID (by default $current_User->ID will be used)
	 */
	function get( $setting, $user_ID = NULL )
	{
		if( ! isset($user_ID) )
		{
			global $current_User;

			if( ! isset($current_User) )
			{ // no current/logged in user:
				return $this->get_default($setting);
			}

			$user_ID = $current_User->ID;
		}

		return parent::get( $user_ID, $setting );
	}


	/**
	 * Temporarily sets a user setting ({@link dbupdate()} writes it to DB)
	 *
	 * @param string name of setting
	 * @param mixed new value
	 * @param integer User ID (by default $current_User->ID will be used)
	 */
	function set( $setting, $value, $user_ID = NULL )
	{
		if( ! isset($user_ID) )
		{
			global $current_User;

			if( ! isset($current_User) )
			{ // no current/logged in user:
				return false;
			}

			$user_ID = $current_User->ID;
		}

		return parent::set( $user_ID, $setting, $value );
	}


	/**
	 * Mark a setting for deletion ({@link dbupdate()} writes it to DB).
	 *
	 * @param string name of setting
	 * @param integer User ID (by default $current_User->ID will be used)
	 */
	function delete( $setting, $user_ID = NULL )
	{
		if( ! isset($user_ID) )
		{
			global $current_User;

			if( ! isset($current_User) )
			{ // no current/logged in user:
				return false;
			}

			$user_ID = $current_User->ID;
		}

		return parent::delete( $user_ID, $setting );
	}


	/**
	 * Get a param from Request and save it to UserSettings, or default to previously saved user setting.
	 *
	 * If the user setting was not set before (and there's no default given that gets returned), $default gets used.
	 *
	 * @todo Move this to _abstractsettings.class.php - the other Settings object can also make use of it!
	 *
	 * @param string Request param name
	 * @param string User setting name. Make sure this is unique!
	 * @param string Force value type to one of:
	 * - integer
	 * - float
	 * - string (strips (HTML-)Tags, trims whitespace)
	 * - array
	 * - object
	 * - null
	 * - html (does nothing)
	 * - '' (does nothing)
	 * - '/^...$/' check regexp pattern match (string)
	 * - boolean (will force type to boolean, but you can't use 'true' as a default since it has special meaning. There is no real reason to pass booleans on a URL though. Passing 0 and 1 as integers seems to be best practice).
	 * Value type will be forced only if resulting value (probably from default then) is !== NULL
	 * @param mixed Default value or TRUE if user input required
	 * @param boolean Do we need to memorize this to regenerate the URL for this page?
	 * @param boolean Override if variable already set
	 * @return NULL|mixed NULL, if neither a param was given nor {@link $UserSettings} knows about it.
	 */
	function param_Request( $param_name, $uset_name, $type = '', $default = '', $memorize = false, $override = false ) // we do not force setting it..
	{
		$value = param( $param_name, $type, NULL, $memorize, $override, false ); // we pass NULL here, to see if it got set at all

		if( $value !== false )
		{ // we got a value
			$this->set( $uset_name, $value );
			$this->dbupdate();
		}
		else
		{ // get the value from user settings
			$value = $this->get($uset_name);

			if( is_null($value) )
			{ // it's not saved yet and there's not default defined ($_defaults)
				$value = $default;
			}
		}

		set_param( $param_name, $value );
		return get_param($param_name);
	}
}


/*
 * $Log$
 * Revision 1.29  2007/02/25 01:39:06  fplanque
 * wording
 *
 * Revision 1.28  2007/02/21 22:21:30  blueyed
 * "Multiple sessions" user setting
 *
 * Revision 1.27  2007/01/25 03:17:00  fplanque
 * visual cleanup for average users
 * geeky stuff preserved as options
 *
 * Revision 1.26  2007/01/24 01:57:07  fplanque
 * minor
 *
 * Revision 1.25  2007/01/23 05:00:25  fplanque
 * better user defaults
 *
 * Revision 1.24  2006/12/18 03:20:22  fplanque
 * _header will always try to set $Blog.
 * autoselect_blog() will do so also.
 * controllers can use valid_blog_requested() to make sure we have one
 * controllers should call set_working_blog() to change $blog, so that it gets memorized in the user settings
 *
 * Revision 1.23  2006/12/18 01:43:26  fplanque
 * minor bugfix
 *
 * Revision 1.22  2006/11/24 18:27:25  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.21  2006/11/12 17:37:41  fplanque
 * code for "simple" is "default"
 *
 * Revision 1.20  2006/11/04 17:43:14  blueyed
 * Blog perm layout views: fixed non-JS links (ctrl param) and store selected one in UserSettings (TODO for switching by JS)
 *
 * Revision 1.19  2006/08/24 00:42:20  fplanque
 * minor
 *
 * Revision 1.18  2006/08/20 22:25:21  fplanque
 * param_() refactoring part 2
 *
 * Revision 1.17  2006/08/20 20:12:33  fplanque
 * param_() refactoring part 1
 *
 * Revision 1.16  2006/07/28 18:27:10  blueyed
 * Basic image preview for image files in the file list
 *
 * Revision 1.15  2006/07/23 22:29:16  blueyed
 * Fix for if $current_User is not set and no user_ID is given: we cannot set/delete then and get returns the default value
 *
 * Revision 1.14  2006/07/17 01:53:12  blueyed
 * added param to UserSettings::param_Request
 *
 * Revision 1.13  2006/07/16 23:07:19  fplanque
 * no message
 *
 * Revision 1.12  2006/07/13 00:40:29  blueyed
 * Fixed uset-name for pref. edit tab.
 *
 * Revision 1.11  2006/04/20 17:21:46  blueyed
 * doc
 *
 * Revision 1.10  2006/04/20 16:31:30  fplanque
 * comment moderation (finished for 1.8)
 *
 * Revision 1.9  2006/04/19 20:13:50  fplanque
 * do not restrict to :// (does not catch subdomains, not even www.)
 *
 * Revision 1.8  2006/04/17 23:58:29  blueyed
 * todo
 *
 * Revision 1.7  2006/04/14 19:20:19  fplanque
 * icon cleanup
 *
 * Revision 1.6  2006/03/15 00:24:59  blueyed
 * fixed UserSettings::param_Request()
 *
 * Revision 1.4  2006/03/12 23:09:00  fplanque
 * doc cleanup
 *
 * Revision 1.3  2006/03/12 20:51:53  blueyed
 * Moved Request::param_UserSettings() to UserSettings::param_Request()
 *
 * Revision 1.2  2006/02/27 16:43:09  blueyed
 * Normalized
 *
 * Revision 1.1  2006/02/23 21:11:58  fplanque
 * File reorganization to MVC (Model View Controller) architecture.
 * See index.hml files in folders.
 * (Sorry for all the remaining bugs induced by the reorg... :/)
 *
 * Revision 1.11  2005/12/19 17:39:56  fplanque
 * Remember prefered browing tab for each user.
 *
 * Revision 1.10  2005/12/12 19:21:23  fplanque
 * big merge; lots of small mods; hope I didn't make to many mistakes :]
 *
 * Revision 1.9  2005/11/16 22:40:48  blueyed
 * doc
 *
 * Revision 1.8  2005/10/28 02:37:37  blueyed
 * Normalized AbstractSettings API
 *
 * Revision 1.7  2005/09/06 17:13:55  fplanque
 * stop processing early if referer spam has been detected
 *
 * Revision 1.6  2005/03/15 19:19:49  fplanque
 * minor, moved/centralized some includes
 *
 * Revision 1.5  2005/02/28 09:06:34  blueyed
 * removed constants for DB config (allows to override it from _config_TEST.php), introduced EVO_CONFIG_LOADED
 *
 * Revision 1.4  2005/02/22 02:30:20  blueyed
 * overloaded delete()
 *
 * Revision 1.3  2005/01/06 05:20:14  blueyed
 * refactored (constructor), getDefaults()
 *
 * Revision 1.2  2004/11/08 02:23:44  blueyed
 * allow caching by column keys (e.g. user ID)
 *
 * Revision 1.1  2004/10/13 22:46:32  fplanque
 * renamed [b2]evocore/*
 *
 * Revision 1.7  2004/10/12 17:22:29  fplanque
 * Edited code documentation.
 *
 */
?>