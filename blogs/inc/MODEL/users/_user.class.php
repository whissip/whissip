<?php
/**
 * This file implements the User class.
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
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * Includes:
 */
require_once dirname(__FILE__).'/../dataobjects/_dataobject.class.php';

/**
 * User Class
 *
 * @package evocore
 */
class User extends DataObject
{
	var $login;
	var $pass;
	var $firstname;
	var $lastname;
	var $nickname;
	var $idmode;
	var $locale;
	var $email;
	var $url;
	var $icq;
	var $aim;
	var $msn;
	var $yim;
	var $ip;
	var $domain;
	var $browser;
	var $datecreated;
	var $level;

	/**
	 * Does the user accept emails through a message form?
	 * @var boolean
	 */
	var $allow_msgform;
	var $notify;
	var $showonline;

	/**
	 * Has the user been validated (by email)?
	 * @var boolean
	 */
	var $validated;

	/**
	 * Number of posts by this user. Use get_num_posts() to access this (lazy filled).
	 * @var integer
	 * @access protected
	 */
	var $_num_posts;

	/**
	 * The ID of the (primary, currently only) group of the user.
	 * @var integer
	 */
	var $group_ID;

	/**
	 * Reference to group
	 * @see User::get_Group()
	 * @var Group
	 * @access protected
	 */
	var $Group;

	/**
	 * Blog posts statuses permissions
	 */
	var $blog_post_statuses = array();

	/**
	 * Cache for perms.
	 * @access protected
	 * @var array
	 */
	var $cache_perms = array();


	/**
	 * Constructor
	 *
	 * @param object DB row
	 */
	function User( $db_row = NULL )
	{
		global $default_locale, $Settings, $localtimenow;

		// Call parent constructor:
		parent::DataObject( 'T_users', 'user_', 'user_ID' );

		// blueyed> TODO: this will never get translated for the current User if he has another locale/lang set than default, because it gets adjusted AFTER instantiating him/her..
		//       Use a callback (get_delete_restrictions/get_delete_cascades) instead? Should be also better for performance!
		// fp> These settings should probably be merged with the global database description used by the installer/upgrader. However I'm not sure about how compelx plugins would be able to integrate then...
		$this->delete_restrictions = array(
				array( 'table'=>'T_blogs', 'fk'=>'blog_owner_user_ID', 'msg'=>T_('%d blogs owned by this user') ),
				array( 'table'=>'T_posts', 'fk'=>'post_lastedit_user_ID', 'msg'=>T_('%d posts last edited by this user') ),
				array( 'table'=>'T_posts', 'fk'=>'post_assigned_user_ID', 'msg'=>T_('%d posts assigned to this user') ),
				array( 'table'=>'T_links', 'fk'=>'link_creator_user_ID', 'msg'=>T_('%d links created by this user') ),
				array( 'table'=>'T_links', 'fk'=>'link_lastedit_user_ID', 'msg'=>T_('%d links last edited by this user') ),
			);

		$this->delete_cascades = array(
				array( 'table'=>'T_usersettings', 'fk'=>'uset_user_ID', 'msg'=>T_('%d user settings on collections') ),
				array( 'table'=>'T_sessions', 'fk'=>'sess_user_ID', 'msg'=>T_('%d sessions opened by this user') ),
				array( 'table'=>'T_coll_user_perms', 'fk'=>'bloguser_user_ID', 'msg'=>T_('%d user permissions on blogs') ),
				array( 'table'=>'T_subscriptions', 'fk'=>'sub_user_ID', 'msg'=>T_('%d subscriptions') ),
				array( 'table'=>'T_posts', 'fk'=>'post_creator_user_ID', 'msg'=>T_('%d posts created by this user') ),
			);

		if( $db_row == NULL )
		{ // Setting those object properties, which are not "NULL" in DB (MySQL strict mode):

			// echo 'Creating blank user';
			$this->set( 'login', 'login' );
			$this->set( 'pass', md5('pass') );
			$this->set( 'locale',
				isset( $Settings )
					? $Settings->get('default_locale') // TODO: (settings) use "new users template setting"
					: $default_locale );
			$this->set( 'email', '' );	// fp> TODO: this is an invalid value. Saving the object without a valid email should fail! (actually: it should be fixed by providing a valid email)
			$this->set( 'level', isset( $Settings ) ? $Settings->get('newusers_level') : 0 );
			if( isset($localtimenow) )
			{
				$this->set_datecreated( $localtimenow );
			}
			else
			{ // We don't know local time here!
				$this->set_datecreated( time() );
			}

			if( isset($Settings) )
			{ // Group for this user:
				$this->group_ID = $Settings->get('newusers_grp_ID');
			}

 			$this->set( 'allow_msgform', 1 );
 			$this->set( 'notify', 1 );
 			$this->set( 'showonline', 1 );
		}
		else
		{
			// echo 'Instanciating existing user';
			$this->ID = $db_row->user_ID;
			$this->login = $db_row->user_login;
			$this->pass = $db_row->user_pass;
			$this->firstname = $db_row->user_firstname;
			$this->lastname = $db_row->user_lastname;
			$this->nickname = $db_row->user_nickname;
			$this->idmode = $db_row->user_idmode;
			$this->locale = $db_row->user_locale;
			$this->email = $db_row->user_email;
			$this->url = $db_row->user_url;
			$this->icq = $db_row->user_icq;
			$this->aim = $db_row->user_aim;
			$this->msn = $db_row->user_msn;
			$this->yim = $db_row->user_yim;
			$this->ip = $db_row->user_ip;
			$this->domain = $db_row->user_domain;
			$this->browser = $db_row->user_browser;
			$this->datecreated = $db_row->dateYMDhour;
			$this->level = $db_row->user_level;
			$this->allow_msgform = $db_row->user_allow_msgform;
			$this->validated = $db_row->user_validated;
			$this->notify = $db_row->user_notify;
			$this->showonline = $db_row->user_showonline;

			// Group for this user:
			$this->group_ID = $db_row->user_grp_ID;
		}
	}


	/**
	 * Get a param
	 *
	 * @param string the parameter
	 */
	function get( $parname )
	{
		switch( $parname )
		{
			case 'fullname':
				return $this->firstname.' '.$this->lastname;

			case 'preferredname':
				return $this->get_preferred_name();

			case 'num_posts':
				return $this->get_num_posts();

			default:
			// All other params:
				return parent::get( $parname );
		}
	}


	/**
	 * Get preferred name of the user, according to {@link User::idmode}.
	 *
	 * @return string
	 */
	function get_preferred_name()
	{
		switch( $this->idmode )
		{
			case 'namefl':
				return parent::get('firstname').' '.parent::get('lastname');

			case 'namelf':
				return parent::get('lastname').' '.parent::get('firstname');

			default:
				return parent::get($this->idmode);
		}
	}


	/**
	 * Get the number of posts for the user.
	 *
	 * @return integer
	 */
	function get_num_posts()
	{
		global $DB;

		if( is_null( $this->_num_posts ) )
		{
			$this->_num_posts = $DB->get_var( 'SELECT count(*)
																				FROM T_posts
																				WHERE post_creator_user_ID = '.$this->ID );
		}

		return $this->_num_posts;
	}


	/**
	 * Get the path to the media directory. If it does not exist, it will be created.
	 *
	 * If we're {@link is_admin_page() on an admin page}, it adds status messages.
	 * @todo These status messages should rather go to a "syslog" and not be displayed to a normal user
	 *
	 * @param boolean Create the directory, if it does not exist yet?
	 * @return mixed the path as string on success, false if the dir could not be created
	 */
	function get_media_dir( $create = true )
	{
		global $basepath, $media_subdir, $Messages, $Settings, $Debuglog;

		if( ! $Settings->get( 'fm_enable_roots_user' ) )
		{	// User directories are disabled:
			$Debuglog->add( 'Attempt to access user media dir, but this feature is disabled', 'files' );
			return false;
		}

		$userdir = get_canonical_path( $basepath.$media_subdir.'users/'.$this->login.'/' );

		if( $create && ! is_dir( $userdir ) )
		{
			if( ! is_writable( dirname($userdir) ) )
			{ // add error
				if( is_admin_page() )
				{
					$Messages->add( sprintf( T_("The user's media directory &laquo;%s&raquo; could not be created, because the parent directory is not writable or does not exist."), rel_path_to_base($userdir) ), 'error' );
				}
				return false;
			}
			elseif( !@mkdir( $userdir ) )
			{ // add error
				if( is_admin_page() )
				{
					$Messages->add( sprintf( T_("The user's media directory &laquo;%s&raquo; could not be created."), rel_path_to_base($userdir) ), 'error' );
				}
				return false;
			}
			else
			{ // chmod and add note:
				$chmod = $Settings->get('fm_default_chmod_dir');
				if( !empty($chmod) )
				{
					@chmod( $userdir, octdec($chmod) );
				}
				if( is_admin_page() )
				{
					$Messages->add( sprintf( T_("The user's directory &laquo;%s&raquo; has been created with permissions %s."), rel_path_to_base($userdir), substr( sprintf('%o', fileperms($userdir)), -3 ) ), 'success' );
				}
			}
		}
		return $userdir;
	}


	/**
	 * Get the URL to the media folder
	 *
	 * @return string the URL
	 */
	function get_media_url()
	{
		global $baseurl, $media_subdir, $Settings, $Debuglog;

		if( ! $Settings->get( 'fm_enable_roots_user' ) )
		{	// User directories are disabled:
			$Debuglog->add( 'Attempt to access user media URL, but this feature is disabled', 'files' );
			return false;
		}

		return $baseurl.$media_subdir.'users/'.$this->login.'/';
	}


	/**
	 * Set param value
	 *
	 * @param string parameter name
	 * @param mixed parameter value
	 * @return boolean true, if a value has been set; false if it has not changed
	 */
	function set( $parname, $parvalue )
	{
		switch( $parname )
		{
			case 'icq':
				return parent::set_param( $parname, 'number', $parvalue, true );

			case 'level':
			case 'notify':
			case 'showonline':
				return parent::set_param( $parname, 'number', $parvalue );

			case 'validated':
				return parent::set_param( $parname, 'number', $parvalue ? 1 : 0 );	// convert boolean

			default:
				return parent::set_param( $parname, 'string', $parvalue );
		}
	}


	/**
	 * Set date created.
	 *
	 * @param integer seconds since Unix Epoch.
	 */
	function set_datecreated( $datecreated, $isYMDhour = false )
	{
		if( !$isYMDhour )
		{
			$datecreated = date('Y-m-d H:i:s', $datecreated );
		}
		// Set value:
		$this->datecreated = $datecreated;
		// Remmeber change for later db update:
		$this->dbchange( 'dateYMDhour', 'string', 'datecreated' );
	}


	/**
	 * Set email address of the user.
	 *
	 * If the email address has changed and we're configured to invalidate the user in this case,
	 * the user's account gets invalidated here.
	 *
	 * @param string email address to set for the User
	 * @return boolean true, if set; false if not changed
	 */
	function set_email( $email )
	{
		global $Settings;

		$r = parent::set_param( 'email', 'string', $email );

		// Change "validated" status to false (if email has changed and Settings are available, which they are not during install):
		if( $r && isset($Settings) && $Settings->get('newusers_revalidate_emailchg') )
		{ // In-validate account, because (changed) email has not been verified yet:
			parent::set_param( 'validated', 'number', 0 );
		}

		return $r;
	}


	/**
	 * Set new Group.
	 *
	 * @param Group the Group object to put the user into
	 * @return boolean true if set, false if not changed
	 */
	function set_Group( & $Group )
	{
		if( $Group !== $this->Group )
		{
			$this->Group = & $Group;

			$this->dbchange( 'user_grp_ID', 'number', 'Group->get(\'ID\')' );

			return true;
		}

		return false;
	}

	/**
	 * @deprecated by {@link User::set_Group()} since 1.9
	 */
	function setGroup( & $Group )
	{
		global $Debuglog;
		$Debuglog->add( 'Call to deprecated method User::setGroup(), use set_Group() instead.', 'deprecated' );
		return $this->set_Group( $Group );
	}


	/**
	 * Get the {@link Group} of the user.
	 *
	 * @return Group (by reference)
	 */
	function & get_Group()
	{
		if( ! isset($this->Group) )
		{
			$GroupCache = & get_Cache( 'GroupCache' );
			$this->Group = & $GroupCache->get_by_ID($this->group_ID);
		}
		return $this->Group;
	}


	/**
	 * Check permission for this user
	 *
	 * @param string Permission name, can be one of:
	 *                - 'upload'
	 *                - 'edit_timestamp'
	 *                - 'cats_post_statuses', see {@link User::check_perm_catsusers()}
	 *                - either group permission names, see {@link Group::check_perm()}
	 *                - either blogusers permission names, see {@link User::check_perm_blogusers()}
	 * @param string Permission level
	 * @param boolean Execution will halt if this is !0 and permission is denied
	 * @param mixed Permission target (blog ID, array of cat IDs...)
	 * @return boolean 0 if permission denied
	 */
	function check_perm( $permname, $permlevel = 'any', $assert = false, $perm_target = NULL )
	{
		global $Debuglog;

		if( is_object($perm_target) && isset($perm_target->ID) )
		{
			$perm_target_ID = $perm_target->ID;
		}
		elseif( !is_array($perm_target) )
		{
			$perm_target_ID = $perm_target;
		}

		if( isset($perm_target_ID)	// if it makes sense to check the cache
			&& isset($this->cache_perms[$permname][$permlevel][$perm_target_ID]) )
		{ // Permission in available in Cache:
			$Debuglog->add( "Got perm [$permname][$permlevel][$perm_target_ID] from cache", 'perms' );
			return $this->cache_perms[$permname][$permlevel][$perm_target_ID];
		}

		$perm = false;

		switch( $permname )
		{ // What permission do we want to check?
			case 'cats_post_statuses':
				// Category permissions...
				$perm = $this->check_perm_catsusers( $permname, $permlevel, $perm_target );
				if ( $perm == false )
				{ // Check groups category permissions...
					$this->get_Group();
					$perm = $this->Group->check_perm_catsgroups( $permname, $permlevel, $perm_target );
				}
				break;

			case 'blog_properties':
			case 'blog_ismember':
			case 'blog_post_statuses':
			case 'blog_del_post':
			case 'blog_comments':
			case 'blog_cats':
			case 'blog_genstatic':
				// Blog permission to edit its properties...
				$this->get_Group();

				// Group may grant VIEW acces, FULL access:
				if( $this->Group->check_perm( 'blogs', $permlevel ) )
				{ // If group grants a global permission:
					$perm = true;
					break;
				}

				if( $perm_target > 0 )
				{ // Check user perm for this blog:
					$perm = $this->check_perm_blogusers( $permname, $permlevel, $perm_target );
					if ( $perm == false )
					{ // Check groups for permissions to this specific blog:
						$perm = $this->Group->check_perm_bloggroups( $permname, $permlevel, $perm_target );
					}
				}
				break;

			case 'edit_timestamp':
				// Global permission to edit timestamps...
				// fp> TODO: merge below
				$perm = ($this->level >= 5);
				break;

			case 'item':
				// This is a high level perm...
				if( !is_a( $perm_target, 'Item' ) )
				{
					debug_die( 'No item provided to permission item:'.$permlevel );
				}

				switch( $permlevel )
				{
					case 'edit':
         		$post_status = $perm_target->get( 'status' );
         		$blog = $perm_target->blog_ID;
         		// Call lower level:
						$perm = $this->check_perm( 'blog_post_statuses', $post_status, false, $blog );
						break;

					default:
						debug_die( 'Unhandled permission item:'.$permlevel );
				}
				break;


			default:
				// Other global permissions (see if the group can handle them), includes:
				// files
				// Forward request to group:
				$this->get_Group();
				$perm = $this->Group->check_perm( $permname, $permlevel, $perm_target );
		}

		// echo "<br>Checking user perm $permname:$permlevel:$perm_target";
		$Debuglog->add( "User perm $permname:$permlevel:"
			.( is_object($perm_target) ? get_class($perm_target).'('.$perm_target_ID.')' : $perm_target ) // prevent catchable E_FATAL with PHP 5.2 (because there's no __tostring for e.g. Item)
			.' => '.($perm?'granted':'DENIED'), 'perms' );

		if( ! $perm && $assert )
		{ // We can't let this go on!
			global $app_name;
			debug_die( sprintf( /* %s is the application name, usually "b2evolution" */ T_('Group/user permission denied by %s!'), $app_name )." ($permname:$permlevel:$perm_target)" );
		}

		if( isset($perm_target_ID) )
		{
			// echo "cache_perms[$permname][$permlevel][$perm_target] = $perm;";
			$this->cache_perms[$permname][$permlevel][$perm_target_ID] = $perm;
		}

		return $perm;
	}


	/**
	 * Check permission for this user on a set of specified categories
	 *
	 * This is not for direct use, please call {@link User::check_perm()} instead
	 *
	 * @see User::check_perm()
	 * @param string Permission name, can be one of the following:
	 *                  - cat_post_statuses
	 *                  - more to come later...
	 * @param string Permission level
	 * @param array Array of target cat IDs
	 * @return boolean 0 if permission denied
	 */
	function check_perm_catsusers( $permname, $permlevel, & $perm_target_cats )
	{
		// Check if permission is granted:
		switch( $permname )
		{
			case 'cats_post_statuses':
				// We'll actually pass this on to blog permissions
				// First we need to create an array of blogs, not cats
				$perm_target_blogs = array();
				foreach( $perm_target_cats as $loop_cat_ID )
				{
					$loop_cat_blog_ID = get_catblog( $loop_cat_ID );
					// echo "cat $loop_cat_ID -> blog $loop_cat_blog_ID <br />";
					if( ! in_array( $loop_cat_blog_ID, $perm_target_blogs ) )
					{ // not already in list: add it:
						$perm_target_blogs[] = $loop_cat_blog_ID;
					}
				}
				// Now we'll check permissions for each blog:
				foreach( $perm_target_blogs as $loop_blog_ID )
				{
					if( ! $this->check_perm( 'blog_post_statuses', $permlevel, false, $loop_blog_ID ) )
					{ // If at least one blog is denied:
						return false;	// permission denied
					}
				}
				return true;	// Permission granted
		}

		return false; 	// permission denied
	}


	/**
	 * Check permission for this user on a specified blog
	 *
	 * This is not for direct use, please call {@link User::check_perm()} instead
	 *
	 * @see User::check_perm()
	 * @param string Permission name, can be one of the following:
	 *                  - blog_ismember
	 *                  - blog_post_statuses
	 *                  - blog_del_post
	 *                  - blog_comments
	 *                  - blog_cats
	 *                  - blog_properties
	 *                  - blog_genstatic
	 * @param string Permission level
	 * @param integer Permission target blog ID
	 * @return boolean 0 if permission denied
	 */
	function check_perm_blogusers( $permname, $permlevel, $perm_target_blog )
	{
		global $DB;
		// echo "checkin for $permname >= $permlevel on blog $perm_target_blog<br />";

		if( !isset( $this->blog_post_statuses[$perm_target_blog] ) )
		{ // Allowed blog post statuses have not been loaded yet:
			if( $this->ID == 0 )
			{ // User not in DB, nothing to load!:
				return false;	// Permission denied
			}

			// Load now:
			// echo 'loading allowed statuses';
			$query = "SELECT *
								  FROM T_coll_user_perms
								 WHERE bloguser_blog_ID = $perm_target_blog
								       AND bloguser_user_ID = $this->ID";
			// echo $query, '<br />';
			$row = $DB->get_row( $query, ARRAY_A );

			if( empty($row) )
			{ // No rights set for this Blog/User: remember this (in order not to have the same query next time)
				$this->blog_post_statuses[$perm_target_blog] = array(
						'blog_ismember' => '0',
						'blog_post_statuses' => array(),
						'blog_del_post' => '0',
						'blog_comments' => '0',
						'blog_cats' => '0',
						'blog_properties' => '0',
					);
			}
			else
			{ // OK, rights found:
				$this->blog_post_statuses[$perm_target_blog] = array();

				$this->blog_post_statuses[$perm_target_blog]['blog_ismember'] = $row['bloguser_ismember'];

				$bloguser_perm_post = $row['bloguser_perm_poststatuses'];
				if( empty($bloguser_perm_post ) )
					$this->blog_post_statuses[$perm_target_blog]['blog_post_statuses'] = array();
				else
					$this->blog_post_statuses[$perm_target_blog]['blog_post_statuses'] = explode( ',', $bloguser_perm_post );

				$this->blog_post_statuses[$perm_target_blog]['blog_del_post'] = $row['bloguser_perm_delpost'];
				$this->blog_post_statuses[$perm_target_blog]['blog_comments'] = $row['bloguser_perm_comments'];
				$this->blog_post_statuses[$perm_target_blog]['blog_cats'] = $row['bloguser_perm_cats'];
				$this->blog_post_statuses[$perm_target_blog]['blog_properties'] = $row['bloguser_perm_properties'];
			}
		}

		// Check if permission is granted:
		switch( $permname )
		{
			case 'blog_genstatic':
				return ($this->level >= 2);

			case 'blog_post_statuses':
				if( $permlevel == 'any' )
				{ // Any permission will do:
					// echo count($this->blog_post_statuses);
					return ( count($this->blog_post_statuses[$perm_target_blog]['blog_post_statuses']) > 0 );
				}

				// We want a specific permission:
				// echo 'checking :', implode( ',', $this->blog_post_statuses  ), '<br />';
				return in_array( $permlevel, $this->blog_post_statuses[$perm_target_blog]['blog_post_statuses'] );

			default:
				// echo $permname, '=', $this->blog_post_statuses[$perm_target_blog][$permname], ' ';
				return $this->blog_post_statuses[$perm_target_blog][$permname];
		}
	}


	/**
	 * Insert object into DB based on previously recorded changes
	 *
	 * Triggers the plugin event AfterUserInsert.
	 *
	 * @return boolean true on success
	 */
	function dbinsert()
	{
		global $Plugins;

		if( $result = parent::dbinsert() )
		{ // We could insert the user object..

			// Notify plugins:
			// A user could be created also in another DB (to synchronize it with b2evo)
			$Plugins->trigger_event( 'AfterUserInsert', $params = array( 'User' => & $this ) );
		}

		return $result;
	}


	/**
	 * Update the DB based on previously recorded changes.
	 *
	 * Triggers the plugin event AfterUserUpdate.
	 *
	 * @return boolean true on success
	 */
	function dbupdate()
	{
		global $DB, $Plugins;

		if( $result = parent::dbupdate() )
		{ // We could update the user object..

			// Notify plugins:
			// Example: A authentication plugin could synchronize/update the password of the user.
			$Plugins->trigger_event( 'AfterUserUpdate', $params = array( 'User' => & $this ) );
		}

		return $result;
	}


	/**
	 * Delete user and dependencies from database
	 *
	 * Includes WAY TOO MANY requests because we try to be compatible with MySQL 3.23, bleh!
	 *
	 * @param Log Log object where output gets added (by reference).
	 */
	function dbdelete( & $Log )
	{
		global $DB, $Plugins;

		if( $this->ID == 0 ) debug_die( 'Non persistant object cannot be deleted!' );

		$DB->begin();

		// Transform registered user comments to unregistered:
		$ret = $DB->query( 'UPDATE T_comments
												SET comment_author_ID = NULL,
														comment_author = '.$DB->quote( $this->get('preferredname') ).',
														comment_author_email = '.$DB->quote( $this->get('email') ).',
														comment_author_url = '.$DB->quote( $this->get('url') ).'
												WHERE comment_author_ID = '.$this->ID );
		if( is_a( $Log, 'log' ) )
		{
			$Log->add( 'Transforming user\'s comments to unregistered comments... '.sprintf( '(%d rows)', $ret ), 'note' );
		}

		// Get list of posts that are going to be deleted (3.23)
		$post_list = implode( ',', $DB->get_col( '
				SELECT post_ID
				  FROM T_posts
				 WHERE post_creator_user_ID = '.$this->ID ) );

		if( !empty( $post_list ) )
		{
			// Delete comments
			$ret = $DB->query( "DELETE FROM T_comments
													WHERE comment_post_ID IN ($post_list)" );
			if( is_a( $Log, 'log' ) )
			{
				$Log->add( sprintf( 'Deleted %d comments on user\'s posts.', $ret ), 'note' );
			}

			// Delete post extracats
			$ret = $DB->query( "DELETE FROM T_postcats
													WHERE postcat_post_ID IN ($post_list)" );
			if( is_a( $Log, 'log' ) )
			{
				$Log->add( sprintf( 'Deleted %d extracats of user\'s posts\'.', $ret ) ); // TODO: geeky wording.
			}

			// Posts will we auto-deleted by parent method
		}
		else
		{ // no posts
			if( is_a( $Log, 'log' ) )
			{
				$Log->add( 'No posts to delete.', 'note' );
			}
		}

		// remember ID, because parent method resets it to 0
		$old_ID = $this->ID;

		// Delete main object:
		if( ! parent::dbdelete() )
		{
			$DB->rollback();

			$Log->add( 'User has not been deleted.', 'error' );
			return false;
		}

		$DB->commit();

		if( is_a( $Log, 'log' ) )
		{
			$Log->add( 'Deleted User.', 'note' );
		}

		// Notify plugins:
		$this->ID = $old_ID;
		$Plugins->trigger_event( 'AfterUserDelete', $params = array( 'User' => & $this ) );
		$this->ID = 0;

		return true;
	}


	function callback_optionsForIdMode( $value )
	{
		$field_options = '';
		$idmode = $this->get( 'idmode' );

		foreach( array( 'nickname' => array( T_('Nickname') ),
										'login' => array( T_('Login') ),
										'firstname' => array( T_('First name') ),
										'lastname' => array( T_('Last name') ),
										'namefl' => array( T_('First name').' '.T_('Last name'),
																				implode( ' ', array( $this->get('firstname'), $this->get('lastname') ) ) ),
										'namelf' => array( T_('Last name').' '.T_('First name'),
																				implode( ' ', array( $this->get('lastname'), $this->get('firstname') ) ) ),
										)
							as $lIdMode => $lInfo )
		{
			$disp = isset( $lInfo[1] ) ? $lInfo[1] : $this->get($lIdMode);

			$field_options .= '<option value="'.$lIdMode.'"';
			if( $value == $lIdMode )
			{
				$field_options .= ' selected="selected"';
			}
			$field_options .= '>'.( !empty( $disp ) ? $disp.' ' : ' - ' )
												.'&laquo;'.$lInfo[0].'&raquo;'
												.'</option>';
		}

		return $field_options;
	}


	/**
	 * Send an email to the user with a link to validate/confirm his email address.
	 *
	 * If the email could get sent, it saves the used "request_id" into the user's Session.
	 *
	 * @param string URL, where to redirect the user after he clicked the validation link (gets saved in Session).
	 * @return boolean True, if the email could get sent; false if not
	 */
	function send_validate_email( $redirect_to_after = NULL )
	{
		global $app_name, $htsrv_url_sensitive, $Session;

		$request_id = generate_random_key(22);

		$message = T_('You need to validate your email address by clicking on the following link.')
			."\n\n"
			.T_('Login:')." $this->login\n"
			.sprintf( /* TRANS: %s gets replaced by $app_name (normally "b2evolution") */ T_('Link to validate your %s account:'), $app_name )
			."\n"
			.$htsrv_url_sensitive.'login.php?action=validatemail'
				.'&reqID='.$request_id
				.'&sessID='.$Session->ID  // used to detect cookie problems
			."\n\n"
			.T_('Please note:')
			.' '.T_('For security reasons the link is only valid for your current session (by means of your session cookie).');

		$r = send_mail( $this->email, sprintf( T_('Validate your email address for "%s"'), $this->login ), $message );

		if( $r )
		{ // save request_id into Session
			$request_ids = $Session->get( 'core.validatemail.request_ids' );
			if( ! is_array($request_ids) )
			{
				$request_ids = array();
			}
			$request_ids[] = $request_id;
			$Session->set( 'core.validatemail.request_ids', $request_ids, 86400 * 2 ); // expires in two days (or when clicked)
			if( isset($redirect_to_after) )
			{
				$Session->set( 'core.validatemail.redirect_to', $redirect_to_after  );
			}
			$Session->dbsave(); // save immediately
		}

		return $r;
	}


	// Template functions {{{

	/**
	 * Template function: display user's level
	 */
	function level()
	{
		$this->disp( 'level', 'raw' );
	}


	/**
	 * Template function: display user's login
	 *
	 * @param string Output format, see {@link format_to_output()}
	 */
	function login( $format = 'htmlbody' )
	{
		$this->disp( 'login', $format );
	}


	/**
	 * Template helper function: Get a link to a message form for this user.
	 *
	 * @param string url of the message form
	 * @param string to display before link
	 * @param string to display after link
	 * @param string link text
	 * @param string link title
	 * @param string class name
	 */
	function get_msgform_link( $form_url = NULL, $before = ' ', $after = ' ', $text = '#', $title = '#', $class = '' )
	{
		if( empty($this->email) )
		{ // We have no email for this User :(
			return false;
		}
		if( empty($this->allow_msgform) )
		{
			return false;
		}

		if( is_null($form_url) )
		{
			global $Blog;
			$form_url = isset($Blog) ? $Blog->get('msgformurl') : '';
		}

		$form_url = url_add_param( $form_url, 'recipient_id='.$this->ID.'&amp;redirect_to='.rawurlencode(url_rel_to_same_host(regenerate_url('','','','&'), $form_url)) );

		if( $title == '#' ) $title = T_('Send email to user');
		if( $text == '#' ) $text = get_icon( 'email', 'imgtag', array( 'class' => 'middle', 'title' => $title ) );

		$r = '';
		$r .= $before;
		$r .= '<a href="'.$form_url.'" title="'.$title.'"';
		if( !empty( $class ) )
		{
			$r .= ' class="'.$class.'"';
		}
		$r .= '>'.$text.'</a>';
		$r .= $after;

		return $r;
	}


	/**
	 * Template function: display a link to a message form for this user
	 *
	 * @param string url of the message form
	 * @param string to display before link
	 * @param string to display after link
	 * @param string link text
	 * @param string link title
	 * @param string class name
	 */
	function msgform_link( $form_url = NULL, $before = ' ', $after = ' ', $text = '#', $title = '#', $class = '' )
	{
		echo $this->get_msgform_link( $form_url, $before, $after, $text, $title, $class );
	}


	/**
	 * Template function: display user's preferred name
	 *
	 * @param string Output format, see {@link format_to_output()}
	 */
	function preferred_name( $format = 'htmlbody' )
	{
		echo format_to_output( $this->get_preferred_name(), $format );
	}


	/**
	 * Template function: display user's URL
	 *
	 * @param string string to display before the date (if changed)
	 * @param string string to display after the date (if changed)
	 * @param string Output format, see {@link format_to_output()}
	 */
	function url( $before = '', $after = '', $format = 'htmlbody' )
	{
		if( !empty( $this->url ) )
		{
			echo $before;
			$this->disp( 'url', $format );
			echo $after;
		}
	}


	/**
	 * Template function: display number of user's posts
	 */
	function num_posts( $format = 'htmlbody' )
	{
		echo format_to_output( $this->get_num_posts(), $format );
	}


	/**
	 * Template function: display first name of the user
	 */
	function first_name( $format = 'htmlbody' )
	{
		$this->disp( 'firstname', $format );
	}


	/**
	 * Template function: display last name of the user
	 */
	function last_name( $format = 'htmlbody' )
	{
		$this->disp( 'lastname', $format );
	}


	/**
	 * Template function: display nickname of the user
	 */
	function nick_name( $format = 'htmlbody' )
	{
		$this->disp( 'nickname', $format );
	}


	/**
	 * Template function: display email of the user
	 */
	function email( $format = 'htmlbody' )
	{
		$this->disp( 'email', $format );
	}


	/**
	 * Template function: display ICQ of the user
	 */
	function icq( $format = 'htmlbody' )
	{
		$this->disp( 'icq', $format );
	}


	/**
	 * Template function: display AIM of the user.
	 *
	 * NOTE: Replaces spaces with '+' ?!?
	 */
	function aim( $format = 'htmlbody' )
	{
		echo format_to_output( str_replace(' ', '+', $this->get('aim') ), $format );
	}


	/**
	 * Template function: display Yahoo IM of the user
	 */
	function yim( $format = 'htmlbody' )
	{
		$this->disp( 'yim', $format );
	}


	/**
	 * Template function: display MSN of the user
	 */
	function msn( $format = 'htmlbody' )
	{
		$this->disp( 'msn', $format );
	}

	// }}}

}

/*
 * $Log$
 * Revision 1.65  2007/03/07 02:34:29  fplanque
 * Fixed very sneaky bug
 *
 * Revision 1.64  2007/03/02 00:44:43  fplanque
 * various small fixes
 *
 * Revision 1.63  2007/01/23 21:45:25  fplanque
 * "enforce" foreign keys
 *
 * Revision 1.62  2007/01/23 05:00:25  fplanque
 * better user defaults
 *
 * Revision 1.61  2007/01/14 22:08:48  fplanque
 * Broadened global group blog view/edit provileges.
 * I hoipe I didn't screw up here :/
 *
 * Revision 1.60  2006/12/22 00:50:33  fplanque
 * improved path cleaning
 *
 * Revision 1.59  2006/12/13 19:16:31  blueyed
 * Fixed E_FATAL with PHP 5.2
 *
 * Revision 1.58  2006/12/12 19:39:07  fplanque
 * enhanced file links / permissions
 *
 * Revision 1.57  2006/12/07 23:13:11  fplanque
 * @var needs to have only one argument: the variable type
 * Otherwise, I can't code!
 *
 * Revision 1.56  2006/12/06 22:30:07  fplanque
 * Fixed this use case:
 * Users cannot register themselves.
 * Admin creates users that are validated by default. (they don't have to validate)
 * Admin can invalidate a user. (his email, address actually)
 *
 * Revision 1.55  2006/12/03 00:22:16  fplanque
 * doc
 *
 * Revision 1.54  2006/11/28 01:10:28  blueyed
 * doc/discussion
 *
 * Revision 1.53  2006/11/28 00:33:01  blueyed
 * Removed DB::compString() (never used) and DB::get_list() (just a macro and better to have in the 4 used places directly; Cleanup/normalization; no extended regexp, when not needed!
 *
 * Revision 1.52  2006/11/27 21:10:23  fplanque
 * doc
 *
 * Revision 1.51  2006/11/26 02:30:39  fplanque
 * doc / todo
 *
 * Revision 1.50  2006/11/24 18:27:25  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.49  2006/11/02 20:34:40  blueyed
 * MFB (the changed member order is by design, according to db_schema.inc.php)
 *
 * Revision 1.48  2006/10/23 22:19:02  blueyed
 * Fixed/unified encoding of redirect_to param. Use just rawurlencode() and no funky &amp; replacements
 *
 * Revision 1.47  2006/10/22 21:38:00  blueyed
 * getGroup() was never in 1.8, so no need to keep it for BC
 *
 * Revision 1.46  2006/10/22 21:28:41  blueyed
 * Fixes and cleanup for empty User instantiation.
 *
 * Revision 1.45  2006/10/18 00:03:51  blueyed
 * Some forgotten url_rel_to_same_host() additions
 *
 * Revision 1.44  2006/09/30 16:55:58  blueyed
 * $create param for media dir handling, which allows to just get the dir, without creating it.
 *
 * Revision 1.43  2006/09/26 11:15:26  blueyed
 * Minor fix for deprecated stub
 *
 * Revision 1.42  2006/09/25 22:18:31  blueyed
 * Removed debug code, sorry.
 *
 * Revision 1.41  2006/09/25 22:16:56  blueyed
 * Re-added User::getGroup() and User::setGroup() as stubs for BC
 *
 * Revision 1.40  2006/09/11 22:06:08  blueyed
 * Cleaned up option_list callback handling
 *
 * Revision 1.39  2006/08/19 08:50:26  fplanque
 * moved out some more stuff from main
 *
 * Revision 1.38  2006/08/19 07:56:31  fplanque
 * Moved a lot of stuff out of the automatic instanciation in _main.inc
 *
 * Revision 1.37  2006/08/18 23:26:22  blueyed
 * Caching of perms through member rather than static var.
 *
 * Revision 1.36  2006/08/18 21:19:30  fplanque
 * minor
 *
 * Revision 1.35  2006/08/17 22:57:16  blueyed
 * Caching of user perms.
 *
 * Revision 1.34  2006/08/07 08:40:22  blueyed
 * doc/examples
 *
 * Revision 1.33  2006/07/23 22:07:18  fplanque
 * cleanup
 *
 * Revision 1.32  2006/07/23 21:50:28  blueyed
 * doc
 *
 * Revision 1.31  2006/07/23 20:18:31  fplanque
 * cleanup
 *
 * Revision 1.30  2006/07/17 01:33:13  blueyed
 * Fixed account validation by email for users who registered themselves
 *
 * Revision 1.29  2006/07/17 01:19:25  blueyed
 * Added events: AfterUserInsert, AfterUserUpdate, AfterUserDelete
 *
 * Revision 1.28  2006/07/08 17:04:18  fplanque
 * minor
 *
 * Revision 1.27  2006/07/07 18:47:58  blueyed
 * Don't use entities in email subject
 *
 * Revision 1.26  2006/07/07 18:15:48  fplanque
 * fixes
 *
 * Revision 1.25  2006/07/04 23:38:11  blueyed
 * Validate email: admin user (#1) has an extra button to validate him/herself through the form; store multiple req_validatemail keys in the user's session.
 *
 * Revision 1.24  2006/07/01 17:05:30  blueyed
 * Made check_perm() more clear.
 *
 * Revision 1.23  2006/06/25 23:34:15  blueyed
 * wording pt2
 *
 * Revision 1.22  2006/06/25 23:23:38  blueyed
 * wording
 *
 * Revision 1.21  2006/06/22 22:30:04  blueyed
 * htsrv url for password related scripts (login, register and profile update)
 *
 * Revision 1.20  2006/06/22 21:58:34  fplanque
 * enhanced comment moderation
 *
 * Revision 1.19  2006/06/19 20:59:37  fplanque
 * noone should die anonymously...
 *
 * Revision 1.18  2006/06/18 01:14:03  blueyed
 * lazy instantiate user's group; normalisation
 *
 * Revision 1.17  2006/06/14 17:26:13  fplanque
 * minor
 *
 * Revision 1.16  2006/06/13 21:49:15  blueyed
 * Merged from 1.8 branch
 *
 * Revision 1.15.2.1  2006/06/12 20:00:38  fplanque
 * one too many massive syncs...
 *
 * Revision 1.15  2006/04/24 18:28:54  blueyed
 * Fix for install
 *
 * Revision 1.14  2006/04/24 18:12:54  blueyed
 * Added Setting to invalidate a user account on email address change.
 *
 * Revision 1.13  2006/04/24 15:43:36  fplanque
 * no message
 *
 * Revision 1.12  2006/04/22 02:36:38  blueyed
 * Validate users on registration through email link (+cleanup around it)
 *
 * Revision 1.11  2006/04/19 22:39:08  blueyed
 * Only add status messages about media_dir creation if on an admin page.
 *
 * Revision 1.10  2006/04/19 20:13:50  fplanque
 * do not restrict to :// (does not catch subdomains, not even www.)
 *
 * Revision 1.9  2006/04/19 19:52:27  blueyed
 * url-encode redirect_to param
 *
 * Revision 1.8  2006/04/13 00:29:32  blueyed
 * cleanup
 *
 * Revision 1.7  2006/04/12 15:16:54  fplanque
 * partial cleanup
 *
 * Revision 1.6  2006/03/25 00:29:35  blueyed
 * todo
 *
 * Revision 1.5  2006/03/19 17:54:26  blueyed
 * Opt-out for email through message form.
 *
 * Revision 1.4  2006/03/18 19:17:54  blueyed
 * Removed remaining use of $img_url
 *
 * Revision 1.3  2006/03/16 19:26:04  fplanque
 * Fixed & simplified media dirs out of web root.
 *
 * Revision 1.2  2006/03/12 23:09:00  fplanque
 * doc cleanup
 *
 * Revision 1.1  2006/02/23 21:11:58  fplanque
 * File reorganization to MVC (Model View Controller) architecture.
 * See index.hml files in folders.
 * (Sorry for all the remaining bugs induced by the reorg... :/)
 *
 * Revision 1.63  2006/02/09 22:05:43  blueyed
 * doc fixes
 *
 * Revision 1.62  2006/01/27 14:06:11  fplanque
 * no message
 *
 * Revision 1.61  2006/01/15 16:36:58  blueyed
 * check_perm_blogusers(): optimize and fix for blog_genstatic.
 *
 * Revision 1.60  2006/01/10 21:09:30  fplanque
 * I think the ICQ NULL is better enforced in User::set()
 *
 * Revision 1.59  2006/01/10 19:59:55  blueyed
 * type-fix for icq member
 *
 * Revision 1.58  2006/01/09 19:11:14  blueyed
 * User/Blog media dir creation messages more verbose/secure.
 *
 * Revision 1.57  2005/12/14 17:14:42  blueyed
 * chmod() created media directory
 *
 * Revision 1.56  2005/12/12 19:44:09  fplanque
 * Use cached objects by reference instead of copying them!!
 *
 * Revision 1.55  2005/12/12 19:21:23  fplanque
 * big merge; lots of small mods; hope I didn't make to many mistakes :]
 *
 * Revision 1.54  2005/12/08 22:44:02  blueyed
 * Use rel_path_to_base() to hide absolute paths in Messages
 *
 * Revision 1.53  2005/11/28 17:42:09  blueyed
 * Fixed msgform_link(): call $this->get_msgform_link()
 *
 * Revision 1.52  2005/11/24 15:12:44  blueyed
 * Suppress notices/warnings on mkdir() for the user's directory.
 *
 * Revision 1.51  2005/11/18 01:36:36  blueyed
 * Display permissions of created media dirs right.
 *
 * Revision 1.50  2005/11/07 02:05:49  blueyed
 * Added get_msgform_link() and made msgform_link() use it
 *
 * Revision 1.49  2005/11/04 22:18:03  fplanque
 * no message
 *
 * Revision 1.48  2005/11/04 21:42:22  blueyed
 * Use setter methods to set parameter values! dataobject::set_param() won't pass the parameter to dbchange() if it is already set to the same member value.
 *
 * Revision 1.47  2005/11/04 16:24:36  blueyed
 * Use $localtimenow instead of $servertimenow.
 *
 * Revision 1.46  2005/11/04 13:50:57  blueyed
 * Dataobject::set_param() / set(): return true if a value has been set and false if it did not change. It will not get considered for dbchange() then, too.
 *
 * Revision 1.45  2005/10/03 18:10:07  fplanque
 * renamed post_ID field
 *
 * Revision 1.44  2005/10/03 17:26:44  fplanque
 * synched upgrade with fresh DB;
 * renamed user_ID field
 *
 * Revision 1.43  2005/09/29 15:07:30  fplanque
 * spelling
 *
 * Revision 1.42  2005/09/06 17:13:55  fplanque
 * stop processing early if referer spam has been detected
 *
 * Revision 1.41  2005/08/22 19:19:26  fplanque
 * minor fix
 *
 * Revision 1.40  2005/08/21 16:20:13  halton
 * Added group based blogging permissions (new tab under blog). Required schema change
 *
 * Revision 1.39  2005/08/11 19:41:11  fplanque
 * no message
 *
 * Revision 1.38  2005/08/10 21:14:34  blueyed
 * Enhanced $demo_mode (user editing); layout fixes; some function names normalized
 *
 * Revision 1.37  2005/07/12 00:29:58  blueyed
 * Moved call to $Debuglog up, so that it may be logged however later before die()ing.
 *
 * Revision 1.36  2005/06/20 17:40:23  fplanque
 * minor
 *
 * Revision 1.35  2005/06/10 23:21:12  fplanque
 * minor bugfixes
 *
 * Revision 1.34  2005/06/06 17:59:39  fplanque
 * user dialog enhancements
 *
 * Revision 1.33  2005/06/03 15:12:33  fplanque
 * error/info message cleanup
 *
 * Revision 1.32  2005/05/25 17:13:33  fplanque
 * implemented email notifications on new comments/trackbacks
 *
 * Revision 1.31  2005/05/12 18:39:24  fplanque
 * storing multi homed/relative pathnames for file meta data
 *
 * Revision 1.30  2005/05/11 13:21:38  fplanque
 * allow disabling of mediua dir for specific blogs
 *
 * Revision 1.29  2005/05/10 18:40:08  fplanque
 * normalizing
 *
 * Revision 1.28  2005/05/09 16:09:42  fplanque
 * implemented file manager permissions through Groups
 *
 * Revision 1.27  2005/05/06 20:04:48  fplanque
 * added contribs
 * fixed filemanager settings
 *
 * Revision 1.26  2005/05/04 18:16:55  fplanque
 * Normalizing
 *
 * Revision 1.25  2005/04/28 20:44:20  fplanque
 * normalizing, doc
 *
 * Revision 1.22  2005/03/22 19:17:27  fplanque
 * cleaned up some nonsense...
 *
 * Revision 1.21  2005/03/15 19:19:48  fplanque
 * minor, moved/centralized some includes
 *
 * Revision 1.20  2005/03/13 19:52:14  blueyed
 * use $servertimenow for creation of new blank user
 *
 * Revision 1.19  2005/03/07 00:17:16  blueyed
 * use $Settings instead of $default_locale
 *
 * Revision 1.18  2005/03/02 18:30:56  fplanque
 * tedious merging... :/
 *
 * Revision 1.17  2005/03/01 23:26:34  blueyed
 * upload perms..
 *
 * Revision 1.16  2005/02/28 09:06:34  blueyed
 * removed constants for DB config (allows to override it from _config_TEST.php), introduced EVO_CONFIG_LOADED
 *
 * Revision 1.15  2005/02/27 20:24:48  blueyed
 * minor
 *
 * Revision 1.14  2005/02/23 21:43:30  blueyed
 * fix instantiating new User without existing $GroupCache (install)
 *
 * Revision 1.13  2005/02/23 04:26:18  blueyed
 * moved global $start_of_week into $locales properties
 *
 * Revision 1.12  2005/02/22 02:53:33  blueyed
 * getPreferedName()
 *
 * Revision 1.11  2005/02/20 22:41:13  blueyed
 * use setters in constructor (dbchange()), fixed getNumPosts(), enhanced set_datecreated(), fixed set_Group()
 *
 * Revision 1.10  2005/02/19 18:54:52  blueyed
 * doc
 *
 * Revision 1.9  2005/02/15 22:05:10  blueyed
 * Started moving obsolete functions to _obsolete092.php..
 *
 * Revision 1.8  2005/01/20 20:37:59  fplanque
 * bugfix
 *
 * Revision 1.7  2005/01/13 19:53:51  fplanque
 * Refactoring... mostly by Fabrice... not fully checked :/
 *
 * Revision 1.6  2004/12/29 04:30:58  blueyed
 * removed safefilename()
 *
 * Revision 1.5  2004/12/10 19:45:55  fplanque
 * refactoring
 *
 * Revision 1.4  2004/11/15 18:57:05  fplanque
 * cosmetics
 *
 * Revision 1.3  2004/11/09 00:25:12  blueyed
 * minor translation changes (+MySQL spelling :/)
 *
 * Revision 1.2  2004/10/14 18:31:25  blueyed
 * granting copyright
 *
 * Revision 1.1  2004/10/13 22:46:32  fplanque
 * renamed [b2]evocore/*
 *
 * Revision 1.58  2004/10/12 17:22:29  fplanque
 * Edited code documentation.
 *
 */
?>