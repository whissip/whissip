<?php
/**
 * This file implements the Group class, which manages user groups.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}
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
 * }}
 *
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class( '_core/model/dataobjects/_dataobject.class.php', 'DataObject' );
load_class( 'users/model/_groupsettings.class.php', 'GroupSettings' );

/**
 * User Group
 *
 * Group of users with specific permissions.
 *
 * @package evocore
 */
class Group extends DataObject
{
	/**
	 * Name of group
	 *
	 * Please use get/set functions to read or write this param
	 *
	 * @var string
	 * @access protected
	 */
	var $name;

	/**
	 * Blog posts statuses permissions
	 */
	var $blog_post_statuses = array();

	var $perm_blogs;
	var $perm_security;
	var $perm_bypass_antispam = false;
	var $perm_xhtmlvalidation = 'always';
	var $perm_xhtmlvalidation_xmlrpc = 'always';
	var $perm_xhtml_css_tweaks = false;
	var $perm_xhtml_iframes = false;
	var $perm_xhtml_javascript = false;
	var $perm_xhtml_objects = false;
	var $perm_stats;
	var $perm_users;

	/**
	 * Pluggable group permissions
	 *
	 * @var Instance of GroupSettings class
	 */
	var $GroupSettings;


	/**
	 * Constructor
	 *
	 * @param object DB row
	 */
	function Group( stdClass $db_row = NULL )
	{
		// Call parent constructor:
		parent::DataObject( 'T_groups', 'grp_', 'grp_ID' );

		$this->delete_restrictions = array(
				array( 'table'=>'T_users', 'fk'=>'user_grp_ID', 'msg'=>T_('%d users in this group') ),
			);

		$this->delete_cascades = array(
			);

		if( $db_row == NULL )
		{
			// echo 'Creating blank group';
			$this->set( 'name', T_('New group') );
			$this->set( 'perm_blogs', 'user' );
			$this->set( 'perm_stats', 'none' );
			$this->set( 'perm_users', 'none' );
		}
		else
		{
			// echo 'Instanciating existing group';
			$this->ID                           = $db_row->grp_ID;
			$this->name                         = $db_row->grp_name;
			$this->perm_blogs                   = $db_row->grp_perm_blogs;
			$this->perm_bypass_antispam         = $db_row->grp_perm_bypass_antispam;
			$this->perm_xhtmlvalidation         = $db_row->grp_perm_xhtmlvalidation;
			$this->perm_xhtmlvalidation_xmlrpc  = $db_row->grp_perm_xhtmlvalidation_xmlrpc;
			$this->perm_xhtml_css_tweaks        = $db_row->grp_perm_xhtml_css_tweaks;
			$this->perm_xhtml_iframes           = $db_row->grp_perm_xhtml_iframes;
			$this->perm_xhtml_javascript        = $db_row->grp_perm_xhtml_javascript;
			$this->perm_xhtml_objects           = $db_row->grp_perm_xhtml_objects;
			$this->perm_stats                   = $db_row->grp_perm_stats;
			$this->perm_users                   = $db_row->grp_perm_users;
		}
	}

	/**
	 * Load data from Request form fields.
	 *
	 * @return boolean true if loaded data seems valid.
	 */
	function load_from_Request()
	{
		// Edited Group Name
		param( 'edited_grp_name', 'string' );
		param_check_not_empty( 'edited_grp_name', T_('You must provide a group name!') );
		$this->set_from_Request('name', 'edited_grp_name', true);

		// Edited Group Permission Blogs
		param( 'edited_grp_perm_blogs', 'string', true );
		$this->set_from_Request( 'perm_blogs', 'edited_grp_perm_blogs', true );

		// Apply Antispam
		$this->set( 'perm_bypass_antispam', param( 'apply_antispam', 'integer', 0 ) ? 0 : 1 );

		// XHTML Validation
		$this->set( 'perm_xhtmlvalidation', param( 'perm_xhtmlvalidation', 'string', true ) );

		// XHTML Validation XMLRPC
		$this->set( 'perm_xhtmlvalidation_xmlrpc', param( 'perm_xhtmlvalidation_xmlrpc', 'string', true ) );

		// CSS Tweaks
		$this->set( 'perm_xhtml_css_tweaks', param( 'prevent_css_tweaks', 'integer', 0 ) ? 0 : 1 );

		// Iframes
		$this->set( 'perm_xhtml_iframes', param( 'prevent_iframes', 'integer', 0 ) ? 0 : 1 );

		// Javascript
		$this->set( 'perm_xhtml_javascript', param( 'prevent_javascript', 'integer', 0 ) ? 0 : 1 );

		// Objects
		$this->set( 'perm_xhtml_objects', param( 'prevent_objects', 'integer', 0 ) ? 0 : 1 );

		// Stats
		$this->set( 'perm_stats', param( 'edited_grp_perm_stats', 'string', true ) );

		// Load pluggable group permissions from request
		$GroupSettings = & $this->get_GroupSettings();
		foreach( $GroupSettings->permission_values as $name => $value )
		{
			// We need to handle checkboxes and radioboxes separately , because when a checkbox isn't checked the checkbox variable is not sent
			if( $name == 'perm_createblog' || $name == 'perm_getblog' || $name == 'perm_templates' )
			{ // These two permissions are represented by checkboxes, all other pluggable group permissions are represented by radiobox.
				$value = param( 'edited_grp_'.$name, 'string', 'denied' );
			}
			else
			if( ( $name == 'perm_admin' ) && ( $this->ID == 1 ) )
			{ // Admin group has always admin perm, it can not be set or changed.
				continue;
			}
			else
			{
				$value = param( 'edited_grp_'.$name, 'string', '' );
			}
			if( $value != '' )
			{ // if radio is not set, then doesn't change the settings
				$GroupSettings->set( $name, $value, $this->ID );
			}
		}

		return !param_errors_detected();
	}


	/**
	 * Set param value
	 *
	 * @param string Parameter name
	 * @param mixed Parameter value
	 * @param boolean true to set to NULL if empty value
	 * @return boolean true, if a value has been set; false if it has not changed
	 */
	function set( $parname, $parvalue, $make_null = false )
	{
		switch( $parname )
		{
			case 'perm_templates':
				return $this->set_param( $parname, 'number', $parvalue, $make_null );

			default:
				return $this->set_param( $parname, 'string', $parvalue, $make_null );
		}
	}


	/**
	 * Get the {@link GroupSettings} of the group.
	 *
	 * @return GroupSettings (by reference)
	 */
	function & get_GroupSettings()
	{
		if( ! isset( $this->GroupSettings ) )
		{
			$this->GroupSettings = new GroupSettings();
			$this->GroupSettings->load( $this->ID );
		}
		return $this->GroupSettings;
	}


	/**
	 * Check a permission for this group.
	 *
	 * @param string Permission name:
	 *                - templates
	 *                - stats
	 *                - spamblacklist
	 *                - options
	 *                - users
	 *                - blogs
	 *                - admin (levels "visible", "hidden")
	 *                - messaging
	 * @param string Requested permission level
	 * @param mixed Permission target (blog ID, array of cat IDs...)
	 * @return boolean True on success (permission is granted), false if permission is not granted
	 */
	function check_perm( $permname, $permlevel = 'any', $perm_target = NULL )
	{
		global $Debuglog;

		$perm = false; // Default is false!

		// echo "<br>Checking group perm $permname:$permlevel against $permvalue";
		if( isset($this->{'perm_'.$permname}) )
		{
			$permvalue = $this->{'perm_'.$permname};
		}
		else
		{ // Object's perm-property not set!
			$Debuglog->add( 'Group permission perm_'.$permname.' not defined!', 'perms' );

			$permvalue = false; // This will result in $perm == false always. We go on for the $Debuglog..
		}

		$pluggable_perms = array( 'admin', 'shared_root', 'spamblacklist', 'slugs', 'templates', 'options', 'files' );
		if( in_array( $permname, $pluggable_perms ) ) 
		{
			$permname = 'perm_'.$permname;
		}
		// echo "<br>Checking group perm $permname:$permlevel against $permvalue";

		// Check group permission:
		switch( $permname )
		{
			case 'blogs':
				switch( $permvalue )
				{ // Depending on current group permission:

					case 'editall':
						// All permissions granted
						$perm = true;
						break;

					case 'viewall':
						// User can only ask for view perm
						if(( $permlevel == 'view' ) || ( $permlevel == 'any' ))
						{ // Permission granted
							$perm = true;
							break;
						}
				}

				if( ! $perm && ( $permlevel == 'create' ) && $this->check_perm( 'perm_createblog', 'allowed' ) )
				{ // User is allowed to create a blog (for himself)
					$perm = true;
				}
				break;

			case 'stats':
			case 'users':
				if( ! $this->check_perm( 'admin', 'normal' ) )
				{
					$perm = false;
					break;
				}
				switch( $permvalue )
				{ // Depending on current group permission:

					case 'edit':
						// All permissions granted
						$perm = true;
						break;

					case 'add':
						// User can ask for add perm...
						if( $permlevel == 'add' )
						{
							$perm = true;
							break;
						}
						// ... or for any lower priority perm... (no break)

					case 'view':
						// User can ask for view perm...
						if( $permlevel == 'view' )
						{
							$perm = true;
							break;
						}
						// ... or for any lower priority perm... (no break)

					case 'user':
						// This is for stats. User perm can grant permissions in the User class
						// Here it will just allow to list
					case 'list':
						// User can only ask for list perm
						if( $permlevel == 'list' )
						{
							$perm = true;
							break;
						}
				}
				break;

			case 'perm_messaging':
			case 'perm_files':
				if( ! $this->check_perm( 'admin', 'restricted' ) )
				{
					$perm = false;
					break;
				}
				// don't break, perm_files and perm_messaging are pluggable permissions

			default:

				// Check pluggable permissions using group permission check function
				$perm = Module::check_perm( $permname, $permlevel, $perm_target, 'group_func', $this );
				if( $perm === NULL )
				{	// Even if group permisson check function doesn't exist we should return false value
					$perm = false;
				}

				break;
		}

		$target_ID = $perm_target;
		if( is_object($perm_target) ) $target_ID = $perm_target->ID;

		$Debuglog->add( "Group perm $permname:$permlevel:$target_ID => ".($perm?'granted':'DENIED'), 'perms' );

		return $perm;
	}


	/**
	 * Check permission for this group on a specified blog
	 *
	 * This is not for direct use, please call {@link User::check_perm()} instead
	 * user is checked for privileges first, group lookup only performed on a false result
	 *
	 * @see User::check_perm()
	 * @param string Permission name, can be one of the following:
	 *                  - blog_ismember
	 *                  - blog_post_statuses
	 *                  - blog_del_post
	 *                  - blog_edit_ts
	 *                  - blog_comments
	 *                  - blog_cats
	 *                  - blog_properties
	 *                  - blog_genstatic
	 * @param string Permission level
	 * @param integer Permission target blog ID
	 * @param Item post that we want to edit
	 * @return boolean 0 if permission denied
	 */
	function check_perm_bloggroups( $permname, $permlevel, $perm_target_blog, $Item = NULL, $User = NULL )
	{
		global $DB;
		// echo "checkin for $permname >= $permlevel on blog $perm_target_blog<br />";

		$BlogCache = & get_BlogCache();
    /**
		 * @var Blog
		 */
		$Blog = & $BlogCache->get_by_ID( $perm_target_blog );
		if( ! $Blog->advanced_perms )
		{	// We do not abide to advanced perms
			return false;
		}

		if( !isset( $this->blog_post_statuses[$perm_target_blog] ) )
		{ // Allowed blog post statuses have not been loaded yet:
			if( $this->ID == 0 )
			{ // User not in DB, nothing to load!:
				return false;	// Permission denied
			}

			// Load now:
			// echo 'loading allowed statuses';
			$query = "SELECT *
								FROM T_coll_group_perms
								WHERE bloggroup_blog_ID = $perm_target_blog
								  AND bloggroup_group_ID = $this->ID";

			$row = $DB->get_row( $query, ARRAY_A );

			if( empty($row) )
			{ // No rights set for this Blog/Group: remember this (in order not to have the same query next time)
				$this->blog_post_statuses[$perm_target_blog] = array(
						'blog_ismember' => '0',
						'blog_post_statuses' => array(),
						'blog_edit' => 'no',
						'blog_del_post' => '0',
						'blog_edit_ts' => '0',
						'blog_comments' => '0',
						'blog_draft_comments' => '0',
						'blog_published_comments' => '0',
						'blog_deprecated_comments' => '0',
						'blog_cats' => '0',
						'blog_properties' => '0',
						'blog_admin' => '0',
						'blog_page' => '0',
						'blog_intro' => '0',
						'blog_podcast' => '0',
						'blog_sidebar' => '0',
						'blog_media_upload' => '0',
						'blog_media_browse' => '0',
						'blog_media_change' => '0',
					);
			}
			else
			{ // OK, rights found:
				$this->blog_post_statuses[$perm_target_blog] = array();

				$this->blog_post_statuses[$perm_target_blog]['blog_ismember'] = $row['bloggroup_ismember'];

				$bloggroup_perm_post = $row['bloggroup_perm_poststatuses'];
				if( empty($bloggroup_perm_post ) )
					$this->blog_post_statuses[$perm_target_blog]['blog_post_statuses'] = array();
				else
					$this->blog_post_statuses[$perm_target_blog]['blog_post_statuses'] = explode( ',', $bloggroup_perm_post );

				$this->blog_post_statuses[$perm_target_blog]['blog_edit'] = $row['bloggroup_perm_edit'];
				$this->blog_post_statuses[$perm_target_blog]['blog_del_post'] = $row['bloggroup_perm_delpost'];
				$this->blog_post_statuses[$perm_target_blog]['blog_edit_ts'] = $row['bloggroup_perm_edit_ts'];
				$this->blog_post_statuses[$perm_target_blog]['blog_comments'] = $row['bloggroup_perm_publ_cmts']
					+ $row['bloggroup_perm_depr_cmts'] + $row['bloggroup_perm_draft_cmts'];
				$this->blog_post_statuses[$perm_target_blog]['blog_draft_comments'] = $row['bloggroup_perm_draft_cmts'];
				$this->blog_post_statuses[$perm_target_blog]['blog_published_comments'] = $row['bloggroup_perm_publ_cmts'];
				$this->blog_post_statuses[$perm_target_blog]['blog_deprecated_comments'] = $row['bloggroup_perm_depr_cmts'];
				$this->blog_post_statuses[$perm_target_blog]['blog_cats'] = $row['bloggroup_perm_cats'];
				$this->blog_post_statuses[$perm_target_blog]['blog_properties'] = $row['bloggroup_perm_properties'];
				$this->blog_post_statuses[$perm_target_blog]['blog_admin'] = $row['bloggroup_perm_admin'];
				$this->blog_post_statuses[$perm_target_blog]['blog_page'] = $row['bloggroup_perm_page'];
				$this->blog_post_statuses[$perm_target_blog]['blog_intro'] = $row['bloggroup_perm_intro'];
				$this->blog_post_statuses[$perm_target_blog]['blog_podcast'] = $row['bloggroup_perm_podcast'];
				$this->blog_post_statuses[$perm_target_blog]['blog_sidebar'] = $row['bloggroup_perm_sidebar'];
				$this->blog_post_statuses[$perm_target_blog]['blog_media_upload'] = $row['bloggroup_perm_media_upload'];
				$this->blog_post_statuses[$perm_target_blog]['blog_media_browse'] = $row['bloggroup_perm_media_browse'];
				$this->blog_post_statuses[$perm_target_blog]['blog_media_change'] = $row['bloggroup_perm_media_change'];
			}
		}

		// Check if permission is granted:
		switch( $permname )
		{
			case 'stats':
				// Wiewing stats is the same perm as being authorized to edit properties: (TODO...)
				if( $permlevel == 'view' )
				{
					return $this->blog_post_statuses[$perm_target_blog]['blog_properties'];
				}
				// No other perm can be granted here (TODO...)
				return false;

			case 'blog_genstatic':
				// generate static pages is not currently a group permission.  if you are here user is denied already anyway
				return (false);

			case 'blog_post_statuses':
				return ( count($this->blog_post_statuses[$perm_target_blog]['blog_post_statuses']) > 0 );

			case 'blog_post!published':
			case 'blog_post!protected':
			case 'blog_post!private':
			case 'blog_post!draft':
			case 'blog_post!deprecated':
			case 'blog_post!redirected':
				// We want a specific permission:
				$subperm = substr( $permname, 10 );
				//$Debuglog->add( "checking : $subperm - ", implode( ',', $this->blog_post_statuses[$perm_target_blog]['blog_post_statuses']  ), 'perms' );
				$perm = in_array( $subperm, $this->blog_post_statuses[$perm_target_blog]['blog_post_statuses'] );

				// TODO: the following probably should be handled by the Item class!
				if( $perm && $permlevel == 'edit' && !empty($Item) )
				{	// Can we edit this specific Item?
					switch( $this->blog_post_statuses[$perm_target_blog]['blog_edit'] )
					{
						case 'own':
							// Own posts only:
							return ($Item->creator_user_ID == $User->ID);

						case 'lt':
							// Own + Lower level posts only:
							if( $Item->creator_user_ID == $User->ID )
							{
								return true;
							}
							$item_creator_User = & $Item->get_creator_User();
							return ( $item_creator_User->level < $User->level );

						case 'le':
							// Own + Lower or equal level posts only:
							if( $Item->creator_user_ID == $User->ID )
							{
								return true;
							}
							$item_creator_User = & $Item->get_creator_User();
							return ( $item_creator_User->level <= $User->level );

						case 'all':
							return true;

						case 'no':
						default:
							return false;
					}
				}

				return $perm;

			case 'files':
				switch( $permlevel )
				{
					case 'add':
						return $this->blog_post_statuses[$perm_target_blog]['blog_media_upload'];
					case 'view':
						return $this->blog_post_statuses[$perm_target_blog]['blog_media_browse'];
					case 'edit':
						return $this->blog_post_statuses[$perm_target_blog]['blog_media_change'];
					default:
						return false;
				}
				break;

			default:
				// echo $permname, '=', $this->blog_post_statuses[$perm_target_blog][$permname], ' ';
				return $this->blog_post_statuses[$perm_target_blog][$permname];
		}
	}


	/**
	 * Get name of the Group
	 *
	 * @return string
	 */
	function get_name()
	{
		return $this->name;
	}


	/**
	 * Insert object into DB based on previously recorded changes.
	 */
	function dbinsert()
	{
		global $DB;

		$DB->begin();

		parent::dbinsert();

		// Create group permissions/settings for the current group
		$GroupSettings = & $this->get_GroupSettings();
		$GroupSettings->dbupdate( $this->ID );

		$DB->commit();
	}


	/**
	 * Update the DB based on previously recorded changes
	 */
	function dbupdate()
	{
		global $DB;

		$DB->begin();

		parent::dbupdate();

		// Update group permissions/settings of the current group
		$GroupSettings = & $this->get_GroupSettings();
		$GroupSettings->dbupdate( $this->ID );

		$DB->commit();
	}


	/**
	 * Delete object from DB.
	 */
	function dbdelete( $Messages = NULL )
	{
		global $DB;

		$DB->begin();

		// Delete group permissions of the current group
		$GroupSettings = & $this->get_GroupSettings();
		$GroupSettings->delete( $this->ID );
		$GroupSettings->dbupdate( $this->ID );

		parent::dbdelete( $Messages );

		$DB->commit();
	}


	/**
	 * Check if this group users have messaging permission and have access to the admin interface
	 *
	 * @return boolean true if group has the necessarry permissions
	 */
	function check_messaging_perm()
	{
		return $this->check_perm( 'perm_messaging', 'write' ) && ( $this->check_perm( 'admin', 'restricted' ) );
	}
}

/*
 * $Log$
 * Revision 1.43  2011/05/12 10:27:34  efy-asimo
 * Add admin normal access master restriction to user and stats permission check
 *
 * Revision 1.42  2011/04/05 12:41:40  efy-asimo
 * file perms check and file delete - fix
 *
 * Revision 1.41  2011/02/15 15:37:00  efy-asimo
 * Change access to admin permission
 *
 * Revision 1.40  2011/02/15 05:25:58  sam2kb
 * Fixed error "Object could not be converted to string"
 *
 * Revision 1.39  2011/02/10 23:07:21  fplanque
 * minor/doc
 *
 * Revision 1.38  2011/01/18 16:23:03  efy-asimo
 * add shared_root perm and refactor file perms - part1
 *
 * Revision 1.37  2011/01/06 14:31:47  efy-asimo
 * advanced blog permissions:
 *  - add blog_edit_ts permission
 *  - make the display more compact
 *
 * Revision 1.36  2010/10/15 13:10:09  efy-asimo
 * Convert group permissions to pluggable permissions - part1
 *
 * Revision 1.35  2010/07/26 06:52:27  efy-asimo
 * MFB v-4-0
 *
 * Revision 1.34  2010/07/14 09:06:14  efy-asimo
 * todo fp>asimo modifications
 *
 * Revision 1.33  2010/06/01 11:33:20  efy-asimo
 * Split blog_comments advanced permission (published, deprecated, draft)
 * Use this new permissions (Antispam tool,when edit/delete comments)
 *
 * Revision 1.32  2010/05/05 09:37:08  efy-asimo
 * add _login.disp.php and change groups&users messaging perm
 *
 * Revision 1.31  2010/04/23 09:39:44  efy-asimo
 * "SEO setting" for help link and Groups slugs permission implementation
 *
 * Revision 1.30  2010/04/08 10:35:23  efy-asimo
 * Allow users to create a new blog for themselves - task
 *
 * Revision 1.29  2010/02/08 17:54:47  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.28  2010/01/30 18:55:35  blueyed
 * Fix "Assigning the return value of new by reference is deprecated" (PHP 5.3)
 *
 * Revision 1.27  2009/10/28 09:50:03  efy-maxim
 * Module::check_perm
 *
 * Revision 1.26  2009/10/08 20:05:52  efy-maxim
 * Modular/Pluggable Permissions
 *
 * Revision 1.25  2009/09/25 14:18:22  tblue246
 * Reverting accidental commits
 *
 * Revision 1.23  2009/09/25 07:33:14  efy-cantor
 * replace get_cache to get_*cache
 *
 * Revision 1.22  2009/09/23 07:17:13  efy-bogdan
 *  load_from_Request added to Group class
 *
 * Revision 1.21  2009/09/23 02:57:06  fplanque
 * tentative fix
 *
 * Revision 1.20  2009/09/20 00:27:08  fplanque
 * cleanup/doc/simplified
 *
 * Revision 1.19  2009/09/19 20:31:39  efy-maxim
 * 'Reply' permission : SQL queries to check permission ; Block/Unblock functionality; Error messages on insert thread/message
 *
 * Revision 1.18  2009/09/18 16:16:50  efy-maxim
 * comments tab in messaging module
 *
 * Revision 1.17  2009/09/14 18:37:07  fplanque
 * doc/cleanup/minor
 *
 * Revision 1.16  2009/09/14 13:46:11  efy-arrin
 * Included the ClassName in load_class() call with proper UpperCase
 *
 * Revision 1.15  2009/09/14 07:31:43  efy-maxim
 * 1. Messaging permissions have been fully implemented
 * 2. Messaging has been added to evo bar menu
 *
 * Revision 1.14  2009/09/13 15:56:13  fplanque
 * minor
 *
 * Revision 1.13  2009/09/13 12:25:34  efy-maxim
 * Messaging permissions have been added to:
 * 1. Upgrader
 * 2. Group class
 * 3. Edit Group form
 *
 * Revision 1.12  2009/08/30 17:27:03  fplanque
 * better NULL param handling all over the app
 *
 * Revision 1.11  2009/08/29 12:23:56  tblue246
 * - SECURITY:
 * 	- Implemented checking of previously (mostly) ignored blog_media_(browse|upload|change) permissions.
 * 	- files.ctrl.php: Removed redundant calls to User::check_perm().
 * 	- XML-RPC APIs: Added missing permission checks.
 * 	- items.ctrl.php: Check permission to edit item with current status (also checks user levels) for update actions.
 * - XML-RPC client: Re-added check for zlib support (removed by update).
 * - XML-RPC APIs: Corrected method signatures (return type).
 * - Localization:
 * 	- Fixed wrong permission description in blog user/group permissions screen.
 * 	- Removed wrong TRANS comment
 * 	- de-DE: Fixed bad translation strings (double quotes + HTML attribute = mess).
 * - File upload:
 * 	- Suppress warnings generated by move_uploaded_file().
 * 	- File browser: Hide link to upload screen if no upload permission.
 * - Further code optimizations.
 *
 * Revision 1.10  2009/08/23 20:08:27  tblue246
 * - Check extra categories when validating post type permissions.
 * - Removed User::check_perm_catusers() + Group::check_perm_catgroups() and modified User::check_perm() to perform the task previously covered by these two methods, fixing a redundant check of blog group permissions and a malfunction introduced by the usage of Group::check_perm_catgroups().
 *
 * Revision 1.9  2009/08/23 13:42:49  tblue246
 * Doc. Please read.
 *
 * Revision 1.8  2009/08/22 20:31:01  tblue246
 * New feature: Post type permissions
 *
 * Revision 1.7  2009/03/08 23:57:46  fplanque
 * 2009
 *
 * Revision 1.6  2008/02/06 23:03:15  fplanque
 * added restriction
 *
 * Revision 1.5  2008/01/21 09:35:36  fplanque
 * (c) 2008
 *
 * Revision 1.4  2008/01/20 18:20:25  fplanque
 * Antispam per group setting
 *
 * Revision 1.3  2008/01/20 15:31:12  fplanque
 * configurable validation/security rules
 *
 * Revision 1.2  2008/01/19 10:57:10  fplanque
 * Splitting XHTML checking by group and interface
 *
 * Revision 1.1  2007/06/25 11:01:45  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.15  2007/06/11 01:55:57  fplanque
 * level based user permissions
 *
 * Revision 1.14  2007/05/31 03:02:23  fplanque
 * Advanced perms now disabled by default (simpler interface).
 * Except when upgrading.
 * Enable advanced perms in blog settings -> features
 *
 * Revision 1.13  2007/05/29 01:17:20  fplanque
 * advanced admin blog settings are now restricted by a special permission
 *
 * Revision 1.12  2007/05/28 01:33:22  fplanque
 * permissions/fixes
 *
 * Revision 1.11  2007/04/26 00:11:11  fplanque
 * (c) 2007
 *
 * Revision 1.10  2007/03/20 09:53:26  fplanque
 * Letting boggers view their own stats.
 * + Letthing admins view the aggregate by default.
 *
 * Revision 1.9  2007/03/07 02:34:29  fplanque
 * Fixed very sneaky bug
 *
 * Revision 1.8  2006/12/07 16:06:23  fplanque
 * prepared new file editing permission
 *
 * Revision 1.7  2006/11/24 18:27:25  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 */
?>
