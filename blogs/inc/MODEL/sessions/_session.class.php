<?php
/**
 * This file implements the Session class and holds the
 * {@link session_unserialize_callback()} function used by it.
 *
 * A session can be bound to a user and provides functions to store data in its
 * context.
 * All Hitlogs are also bound to a Session.
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
 *
 * Matt FOLLETT grants Francois PLANQUE the right to license
 * Matt FOLLETT's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author blueyed: Daniel HAHLER.
 * @author fplanque: Francois PLANQUE.
 * @author jeffbearer: Jeff BEARER - {@link http://www.jeffbearer.com/}.
 * @author mfollett:  Matt FOLLETT - {@link http://www.mfollett.com/}.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * A session tracks a given user (not necessarily logged in) while he's navigating the site.
 * A sessions also stores data for the length of the session.
 *
 * Sessions are tracked with a cookie containing the session ID.
 * The cookie also contains a random key to prevent sessions hacking.
 *
 * @package evocore
 */
class Session
{
	/**
	 * The ID of the session.
	 * @var integer
	 */
	var $ID;

	/**
	 * The session key (to be used in URLs).
	 * @var string
	 */
	var $key;

	/**
	 * The user ID for the user of the session (NULL for anonymous (not logged in) user).
	 *
	 * @var integer
	 */
	var $user_ID;

	/**
	 * Is the session validated?
	 * This means that it was created from a received cookie.
	 * @var boolean
	 */
	var $is_validated = false;

	/**
	 * Data stored for the session.
	 *
	 * This holds an array( expire, value ) for each data item key.
	 *
	 * @access protected
	 * @var array
	 */
	var $_data;

	var $_session_needs_save = false;


	/**
	 * Constructor
	 */
	function Session()
	{
		global $DB, $Debuglog, $current_User, $localtimenow, $Messages, $Settings;
		global $Hit;
		global $cookie_session, $cookie_expires, $cookie_path, $cookie_domain;

		$Debuglog->add( 'cookie_domain='.$cookie_domain, 'session' );
		$Debuglog->add( 'cookie_path='.$cookie_path, 'session' );

		$session_cookie = param_cookie( $cookie_session, 'string', '' );
		if( empty( $session_cookie ) )
		{
			$Debuglog->add( 'No session cookie received.', 'session' );
		}
		else
		{ // session ID sent by cookie
			if( ! preg_match( '~^(\d+)_(\w+)$~', $session_cookie, $match ) )
			{
				$Debuglog->add( 'Invalid session cookie format!', 'session' );
			}
			else
			{	// We have a valid session cookie:
				$session_id_by_cookie = $match[1];
				$session_key_by_cookie = $match[2];

				$Debuglog->add( 'Session ID received from cookie: '.$session_id_by_cookie, 'session' );

				$row = $DB->get_row( '
					SELECT sess_ID, sess_key, sess_data, sess_user_ID
					  FROM T_sessions
					 WHERE sess_ID  = '.$DB->quote($session_id_by_cookie).'
					   AND sess_key = '.$DB->quote($session_key_by_cookie).'
					   AND UNIX_TIMESTAMP(sess_lastseen) > '.($localtimenow - $Settings->get('timeout_sessions')) );
				if( empty( $row ) )
				{
					$Debuglog->add( 'Session ID/key combination is invalid!', 'session' );
				}
				else
				{ // ID + key are valid: load data
					$Debuglog->add( 'Session ID is valid.', 'session' );
					$this->ID = $row->sess_ID;
					$this->key = $row->sess_key;
					$this->user_ID = $row->sess_user_ID;
					$this->is_validated = true;

					$Debuglog->add( 'Session user_ID: '.var_export($this->user_ID, true), 'session' );

					if( empty( $row->sess_data ) )
					{
						$Debuglog->add( 'No session data available.', 'session' );
						$this->_data = array();
					}
					else
					{ // Some session data has been previsouly stored:

						// Unserialize session data (using an own callback that should provide class definitions):
						$old_callback = ini_set( 'unserialize_callback_func', 'session_unserialize_callback' );
						if( $old_callback === false )
						{ // this can fail, if "ini_set" has been disabled for security reasons.. :/
							// TODO: dh> add this to "System check page"?
							debug_die('ini_set() is disabled! b2evo cannot adjust "unserialize_callback_func" for Session restoring!');
						}
						// TODO: dh> This can fail, if there are special chars in sess_data:
						//       It will be encoded in $evo_charset _after_ "SET NAMES", but
						//       get retrieved here, _before_ any "SET NAMES" (if $db_config['connection_charset'] is not set (default))!
						$this->_data = @unserialize($row->sess_data);
						ini_set( 'unserialize_callback_func', $old_callback );

						if( $this->_data === false )
						{
							$Debuglog->add( 'Session data corrupted!<br />
								connection_charset: '.var_export($DB->connection_charset, true).'<br />
								Serialized data was: --['.var_export($row->sess_data, true).']--', array('session','error') );
							$this->_data = array();
						}
						else
						{
							$Debuglog->add( 'Session data loaded.', 'session' );

							// Load a Messages object from session data, if available:
							if( ($sess_Messages = $this->get('Messages')) && is_a( $sess_Messages, 'log' ) )
							{
								$Messages->add_messages( $sess_Messages->messages );
								$Debuglog->add( 'Added Messages from session data.', 'session' );
								$this->delete( 'Messages' );
							}
						}
					}
				}
			}
		}


		if( $this->ID )
		{ // there was a valid session before; update data (lastseen)
			$this->_session_needs_save = true;
		}
		else
		{ // create a new session
			$this->key = generate_random_key(32);

			// We need to INSERT now because we need an ID now! (for the cookie)
			$DB->query( "
				INSERT INTO T_sessions( sess_key, sess_lastseen, sess_ipaddress )
				VALUES (
					'".$this->key."',
					'".date( 'Y-m-d H:i:s', $localtimenow )."',
					'".$Hit->IP."'
				)" );

			$this->ID = $DB->insert_id;

			// Set a cookie valid for ~ 10 years:
			setcookie( $cookie_session, $this->ID.'_'.$this->key, time()+315360000, $cookie_path, $cookie_domain );

			$Debuglog->add( 'ID (generated): '.$this->ID, 'session' );
			$Debuglog->add( 'Cookie sent.', 'session' );
		}

		register_shutdown_function( array( & $this, 'dbsave' ) );
	}


	/**
	 * Attach a User object to the session.
	 *
	 * @param User The user to attach
	 */
	function set_User( $User )
	{
		return $this->set_user_ID( $User->ID );
	}


	/**
	 * Attach a user ID to the session.
	 *
	 * NOTE: ID gets saved to DB on shutdown. This may be a "problem" when querying T_sessions for sess_user_ID.
	 *
	 * @param integer The ID of the user to attach
	 */
	function set_user_ID( $user_ID )
	{
		if( $user_ID != $this->user_ID )
		{
			$this->user_ID = $user_ID;
			$this->_session_needs_save = true;
		}
	}


	/**
	 * Logout the user, by invalidating the session key and unsetting {@link $user_ID}.
	 *
	 * We want to keep the user in the session log, but we're unsetting {@link $user_ID}, which refers
	 * to the current session.
	 *
	 * Because the session key is invalid/broken, on the next request a new session will be started.
	 *
	 * NOTE: we MIGHT want to link subsequent sessions together if we want to keep track...
	 */
	function logout()
	{
		global $Debuglog, $cookie_session, $cookie_path, $cookie_domain;

		// Invalidate the session key (no one will be able to use this session again)
		$this->key = NULL;
		$this->_data = array(); // We don't need to keep old data
		$this->_session_needs_save = true;
		$this->dbsave();

		$this->user_ID = NULL; // Unset user_ID after invalidating/saving the session above, to keep the user info attached to the old session.

		// clean up the session cookie:
		setcookie( $cookie_session, '', 200000000, $cookie_path, $cookie_domain );
	}


	/**
	 * Check if session has a user attached.
	 *
	 * @return boolean
	 */
	function has_User()
	{
		return !empty( $this->user_ID );
	}


	/**
	 * Get the attached User.
	 *
	 * @return false|User
	 */
	function & get_User()
	{
		if( !empty($this->user_ID) )
		{
			$UserCache = & get_Cache( 'UserCache' );
			return $UserCache->get_by_ID( $this->user_ID );
		}

		$r = false;
		return $r;
	}


	/**
	 * Get a data value for the session. This checks for the data to be expired and unsets it then.
	 *
	 * @param string Name of the data's key.
	 * @param mixed Default value to use if key is not set or has expired. (since 1.10.0)
	 * @return mixed The value, if set; otherwise $default
	 */
	function get( $param, $default = NULL )
	{
		global $Debuglog, $localtimenow;

		if( isset( $this->_data[$param] ) )
		{
			if( array_key_exists(1, $this->_data[$param]) // can be NULL!
			  && ( is_null( $this->_data[$param][0] ) || $this->_data[$param][0] > $localtimenow ) ) // check for expired data
			{
				return $this->_data[$param][1];
			}
			else
			{ // expired or old format (without 'value' key)
				unset( $this->_data[$param] );
				$this->_session_needs_save = true;
				$Debuglog->add( 'Session data['.$param.'] expired.', 'session' );
			}
		}

		return $default;
	}


	/**
	 * Set a data value for the session.
	 *
	 * @param string Name of the data's key.
	 * @param mixed The value
	 * @param integer Time in seconds for data to expire (0 to disable).
	 */
	function set( $param, $value, $expire = 0 )
	{
		global $Debuglog, $localtimenow;

		if( ! isset($this->_data[$param])
		 || ! is_array($this->_data[$param]) // deprecated: check to transform 1.6 session data to 1.7
		 || $this->_data[$param][1] != $value
		 || $expire != 0 )
		{	// There is something to update:
			$this->_data[$param] = array( ( $expire ? ($localtimenow + $expire) : NULL ), $value );

			if( $param == 'Messages' )
			{ // also set boolean to not call CachePageContent plugin event on next request:
				$this->set( 'core.no_CachePageContent', 1 );
			}

			$Debuglog->add( 'Session data['.$param.'] updated. Expire in: '.( $expire ? $expire.'s' : '-' ).'.', 'session' );

			$this->_session_needs_save = true;
		}
	}


	/**
	 * Delete a value from the session data.
	 *
	 * @param string Name of the data's key.
	 */
	function delete( $param )
	{
		global $Debuglog;

		if( isset($this->_data[$param]) )
		{
			unset( $this->_data[$param] );

			$Debuglog->add( 'Session data['.$param.'] deleted!', 'session' );

			$this->_session_needs_save = true;
		}
	}


	/**
	 * Updates session data in database.
	 *
	 * Note: The key actually only needs to be updated on a logout.
	 */
	function dbsave()
	{
		global $DB, $Debuglog, $Hit, $localtimenow;

		if( ! $this->_session_needs_save )
		{	// There have been no changes since the last save.
			return false;
		}

		$sess_data = empty($this->_data) ? NULL : serialize($this->_data);
		$DB->query( '
			UPDATE T_sessions SET
				sess_data = '.$DB->quote( $sess_data ).',
				sess_ipaddress = "'.$Hit->IP.'",
				sess_key = '.$DB->quote( $this->key ).',
				sess_lastseen = "'.date( 'Y-m-d H:i:s', $localtimenow ).'",
				sess_user_ID = '.$DB->null( $this->user_ID ).'
			WHERE sess_ID = '.$this->ID, 'Session::dbsave()' );

		$Debuglog->add( 'Session data saved!', 'session' );

		$this->_session_needs_save = false;
	}


	/**
	 * Reload session data.
	 *
	 * This is needed if the running process waits for a child process to write data
	 * into the Session, e.g. the captcha plugin in test mode waiting for the Debuglog
	 * output from the process that created the image (included through an IMG tag).
	 */
	function reload_data()
	{
		global $Debuglog, $DB;

		if( empty($this->ID) )
		{
			return false;
		}

		$sess_data = $DB->get_var( '
			SELECT sess_data FROM T_sessions
			 WHERE sess_ID = '.$this->ID );

		$sess_data = @unserialize( $sess_data );
		if( $sess_data === false )
		{
			$this->_data = array();
		}
		else
		{
			$this->_data = $sess_data;
		}

		$Debuglog->add( 'Reloaded session data.' );
	}
}


/**
 * This gets used as a {@link unserialize()} callback function, which is
 * responsible to load the requested class.
 *
 * @todo Once we require PHP5, we should think about using this as __autoload function.
 *
 * Currently, this just gets used by the {@link Session} class and includes the
 * {@link Comment} class and its dependencies.
 *
 * @return boolean True, if the required class could be loaded; false, if not
 */
function session_unserialize_callback( $classname )
{
	global $model_path, $object_def;

	switch( strtolower($classname) )
	{
		case 'blog':
			require_once $model_path.'collections/_blog.class.php';
			return true;

		case 'collectionsettings':
			require_once $model_path.'collections/_collsettings.class.php';
			return true;

		case 'comment':
			require_once $model_path.'comments/_comment.class.php';
			return true;

		case 'item':
			require_once $model_path.'items/_item.class.php';
			return true;

		case 'group':
			require_once $model_path.'users/_group.class.php';
			return true;

		case 'user':
			require_once $model_path.'users/_user.class.php';
			return true;
	}

	return false;
}


/*
 * $Log$
 * Revision 1.34  2007/02/14 14:38:04  waltercruz
 * Changing double quotes to single quotes
 *
 * Revision 1.33  2007/01/27 15:18:23  blueyed
 * doc
 *
 * Revision 1.32  2007/01/27 01:02:49  blueyed
 * debug_die() if ini_set() fails on Session data restore
 *
 * Revision 1.31  2007/01/16 00:08:44  blueyed
 * Implemented $default param for Session::get()
 *
 * Revision 1.30  2006/12/28 15:43:31  fplanque
 * minor
 *
 * Revision 1.29  2006/12/17 23:44:35  fplanque
 * minor cleanup
 *
 * Revision 1.28  2006/12/07 23:13:11  fplanque
 * @var needs to have only one argument: the variable type
 * Otherwise, I can't code!
 *
 * Revision 1.27  2006/11/24 18:27:24  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.26  2006/11/14 21:13:58  blueyed
 * I've spent > 2 hours debugging this charset nightmare and all I've got are those lousy TODOs..
 *
 * Revision 1.25  2006/09/10 00:00:57  blueyed
 * "Solved" Session related todos.
 *
 * Revision 1.24  2006/09/09 23:43:52  blueyed
 * Added param_cookie() and used it for session cookie
 *
 * Revision 1.23  2006/08/20 13:47:25  fplanque
 * extracted param funcs from misc
 *
 * Revision 1.22  2006/08/19 07:56:31  fplanque
 * Moved a lot of stuff out of the automatic instanciation in _main.inc
 *
 * Revision 1.21  2006/08/07 23:49:52  blueyed
 * Display Debuglog object stored in session (after redirect) separately.
 *
 * Revision 1.20  2006/08/07 22:37:54  fplanque
 * no message
 *
 * Revision 1.19  2006/08/07 22:29:33  fplanque
 * minor / doc
 *
 * Revision 1.16  2006/08/07 16:49:35  fplanque
 * doc
 *
 * Revision 1.15  2006/08/01 20:02:38  blueyed
 * Fixed bug with sess_lastseen WHERE-clause.
 *
 * Revision 1.14  2006/07/31 15:39:06  blueyed
 * Save Debuglog into Session before redirect and load it from there, if available.
 *
 * Revision 1.13  2006/06/20 21:16:02  blueyed
 * doc/debug
 *
 * Revision 1.12  2006/05/29 19:54:45  fplanque
 * no message
 *
 * Revision 1.11  2006/05/12 21:53:37  blueyed
 * Fixes, cleanup, translation for plugins
 *
 * Revision 1.10  2006/05/04 10:18:41  blueyed
 * Added Session property to skip page content caching event.
 *
 * Revision 1.9  2006/05/04 01:06:05  blueyed
 * debuglog
 *
 * Revision 1.8  2006/05/02 22:25:27  blueyed
 * Comment preview for frontoffice.
 *
 * Revision 1.7  2006/04/21 17:05:08  blueyed
 * cleanup
 *
 * Revision 1.6  2006/04/19 20:13:50  fplanque
 * do not restrict to :// (does not catch subdomains, not even www.)
 *
 * Revision 1.5  2006/03/20 18:49:44  fplanque
 * no message
 *
 * Revision 1.4  2006/03/19 19:53:53  blueyed
 * minor
 *
 * Revision 1.3  2006/03/12 23:08:59  fplanque
 * doc cleanup
 *
 * Revision 1.2  2006/03/02 20:05:29  blueyed
 * Fixed/polished stats (linking T_useragents to T_hitlog, not T_sessions again). I've done this the other way around before, but it wasn't my idea.. :p
 *
 * Revision 1.1  2006/02/23 21:11:58  fplanque
 * File reorganization to MVC (Model View Controller) architecture.
 * See index.hml files in folders.
 * (Sorry for all the remaining bugs induced by the reorg... :/)
 *
 * Revision 1.45  2006/01/29 15:07:01  blueyed
 * Added reload_data()
 *
 * Revision 1.43  2006/01/22 19:38:45  blueyed
 * Added expiration support through set() for session data.
 *
 * Revision 1.42  2006/01/20 17:08:13  blueyed
 * Save sess_data as NULL (unserialized) if NULL.
 *
 * Revision 1.41  2006/01/20 16:40:56  blueyed
 * Cleanup
 *
 * Revision 1.40  2006/01/14 14:23:07  blueyed
 * "Out of range" fix in dbsave()
 *
 * Revision 1.39  2006/01/12 21:55:13  blueyed
 * Fix
 *
 * Revision 1.38  2006/01/11 18:23:04  blueyed
 * Also update sess_user_ID in DB on shutdown with set_User() and set_user_ID().
 *
 * Revision 1.36  2006/01/11 01:06:37  blueyed
 * Save session data once at shutdown into DB
 *
 * Revision 1.35  2005/12/21 20:38:18  fplanque
 * Session refactoring/doc
 *
 * Revision 1.34  2005/12/12 19:21:23  fplanque
 * big merge; lots of small mods; hope I didn't make to many mistakes :]
 *
 * Revision 1.33  2005/11/17 19:35:26  fplanque
 * no message
 *
 */
?>