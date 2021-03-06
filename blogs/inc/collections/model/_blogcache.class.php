<?php
/**
 * This file implements the BlogCache class.
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
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author blueyed: Daniel HAHLER.
 * @author fplanque: Francois PLANQUE
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class( '_core/model/dataobjects/_dataobjectcache.class.php', 'DataObjectCache' );

/**
 * Blog Cache Class
 *
 * @package evocore
 */
class BlogCache extends DataObjectCache
{
	/**
	 * Cache by absolute siteurl
	 * @var array
	 */
	var $cache_siteurl_abs = array();

	/**
	 * Cache by urlname
	 * @var array
	 */
	var $cache_urlname = array();

	/**
	 * Constructor
	 */
	function BlogCache()
	{
		parent::DataObjectCache( 'Blog', false, 'T_blogs', 'blog_', 'blog_ID', NULL, '',
			/* TRANS: "None" select option */ T_('No blog'), 0 );
	}


	/**
	 * Add object to cache, handling our own indices.
	 *
	 * @param Blog
	 * @return boolean True on add, false if already existing.
	 */
	function add( & $Blog )
	{
		if( ! empty($Blog->siteurl) && preg_match( '~^https?://~', $Blog->siteurl ) )
		{ // absolute siteurl
			$this->cache_siteurl_abs[ $Blog->siteurl ] = & $Blog;
		}

		$this->cache_urlname[ $Blog->urlname ] = & $Blog;

		return parent::add( $Blog );
	}


	/**
	 * Get an object from cache by its url ("siteurl")
	 *
	 * Load the cache if necessary
	 *
	 * This gets used in /index_multi.php to detect blogs according to the requested HostWithPath
	 *
	 * @todo fp> de-factorize. cleanup. make efficient. split access types.
	 *
	 * @param string URL of blog to load (should be the whole requested URL/path, e.g. "http://mr.example.com/permalink")
	 * @param boolean false if you want to return false on error
	 * @return Blog A Blog object on success, false on failure (may also halt!)
	 */
	function & get_by_url( $req_url, $halt_on_error = true )
	{
		global $DB, $Debuglog, $baseurl, $basedomain;

		foreach( array_keys($this->cache_siteurl_abs) as $siteurl_abs )
		{
			if( strpos( $req_url, $siteurl ) === 0 )
			{ // found in cache
				return $this->cache_siteurl_abs[$siteurl_abs];
			}
		}

		// Load just the requested object:
		$Debuglog->add( "Loading <strong>$this->objtype($req_url)</strong> into cache", 'dataobjects' );

		$req_url_wo_proto = substr( $req_url, strpos( $req_url, '://' ) ); // req_url without protocol, so it matches http and https below

		$sql = 'SELECT *
			  FROM T_blogs
			 WHERE ( blog_access_type = "absolute"
			         AND ( '.$DB->quote('http'.$req_url_wo_proto).' LIKE CONCAT( blog_siteurl, "%" )
		                 OR '.$DB->quote('https'.$req_url_wo_proto).' LIKE CONCAT( blog_siteurl, "%" ) ) )
			    OR ( blog_access_type = "subdom"
			         AND '.$DB->quote($req_url_wo_proto).' LIKE CONCAT( "://", blog_urlname, ".'.$basedomain.'/%" ) )';

		// Match stubs like "http://base/url/STUB?param=1" on $baseurl
		/*
		if( preg_match( "#^$baseurl([^/?]+)#", $req_url, $match ) )
		{
			$sql .= "\n OR ( blog_access_type = 'stub' AND blog_stub = ".$DB->quote($match[1])." )";
		}
		*/

		$row = $DB->get_row( $sql, OBJECT, 0, 'Blog::get_by_url()' );

		if( empty( $row ) )
		{ // Requested object does not exist
			if( $halt_on_error ) debug_die( "Requested $this->objtype does not exist!" );

			$r = false;
			return $r; // we return by reference!
		}

		$Blog = new Blog( $row );
		$this->add( $Blog );

		return $Blog;
	}


	/**
	 * Get a blog from cache by its URL name.
	 *
	 * Load the object into cache, if necessary.
	 *
	 * @param string URL name of object to load
	 * @param boolean false if you want to return false on error
	 * @return Blog|false A Blog object on success, false on failure (may also halt!)
	 */
	function & get_by_urlname( $req_urlname, $halt_on_error = true )
	{
		global $DB, $Debuglog;

		if( isset($this->cache_urlname[$req_urlname]) )
		{
			return $this->cache_urlname[$req_urlname];
		}

		// Load just the requested object:
		$Debuglog->add( "Loading <strong>$this->objtype($req_urlname)</strong> into cache", 'dataobjects' );
		$sql = "
				SELECT *
				  FROM $this->dbtablename
				 WHERE blog_urlname = ".$DB->quote($req_urlname);
		$row = $DB->get_row( $sql );

		if( empty( $row ) )
		{ // Requested object does not exist
			if( $halt_on_error ) debug_die( "Requested $this->objtype does not exist!" );
			$r = false;
			return $r;
		}

		$Blog = new Blog( $row );
		$this->add( $Blog );

		return $Blog;
	}


	/**
	 * Load a list of public blogs into the cache
	 *
	 * @param string
	 * @return array of IDs
	 */
	function load_public( $order_by = 'ID' )
	{
		global $DB, $Debuglog;

		$Debuglog->add( "Loading <strong>$this->objtype(public)</strong> into cache", 'dataobjects' );

		$sql = "SELECT *
		          FROM {$this->dbtablename}
		         WHERE blog_in_bloglist <> 0
		         ORDER BY {$this->dbprefix}{$order_by}";

		foreach( $DB->get_results( $sql, OBJECT, 'Load public blog list' ) as $row )
		{
			// Instantiate a custom object
			$this->instantiate( $row );
		}

		return $DB->get_col( NULL, 0 );
	}


	/**
	 * Load a list of blogs owner by specific ID into the cache
	 *
	 * @param integer
	 * @param string
	 * @return array of IDs
	 */
	function load_owner_blogs( $owner_ID, $order_by = 'ID' )
	{
		global $DB, $Debuglog;

		$Debuglog->add( "Loading <strong>$this->objtype(owner={$owner_ID})</strong> into cache", 'dataobjects' );

		$sql = "SELECT *
		          FROM {$this->dbtablename}
		         WHERE blog_owner_user_ID = {$owner_ID}
		         ORDER BY {$this->dbprefix}{$order_by}";

		foreach( $DB->get_results( $sql, OBJECT, 'Load owner blog list' ) as $row )
		{
			// Instantiate a custom object
			$this->instantiate( $row );
		}

		return $DB->get_col( NULL, 0 );
	}


	/**
	 * Load blogs a user has permissions for.
	 *
	 * @param string permission: 'member' (default), 'browse' (files)
	 * @param string
	 * @param integer user ID
	 * @return array The blog IDs
	 */
	function load_user_blogs( $permname = 'blog_ismember', $permlevel = 'view', $user_ID = NULL, $order_by = 'ID', $limit = NULL )
	{
		global $DB, $Debuglog;

		$Debuglog->add( "Loading <strong>$this->objtype(permission: $permname)</strong> into cache", 'dataobjects' );

		if( is_null($user_ID) )
		{
			global $current_User;
			$user_ID = $current_User->ID;
			$for_User = $current_User;
		}
		else
		{
			$UserCache = & get_UserCache();
			$for_User = & $UserCache->get_by_ID( $user_ID );
		}
		$for_User->get_Group();// ensure Group is set

		$Group = $for_User->Group;
		// First check if we have a global access perm:
 		if( $Group->check_perm( 'blogs', $permlevel ) )
		{ // If group grants a global permission:
			$this->load_all();
			return $this->get_ID_array();
		}

		// Note: We only JOIN in the advanced perms if any given blog has them enabled,
		// otherwise they are ignored!
		$sql = "SELECT DISTINCT T_blogs.*
		          FROM T_blogs LEFT JOIN T_coll_user_perms ON (blog_advanced_perms <> 0
		          																				AND blog_ID = bloguser_blog_ID
		          																				AND bloguser_user_ID = {$user_ID} )
		          		 LEFT JOIN T_coll_group_perms ON (blog_advanced_perms <> 0
		          																	AND blog_ID = bloggroup_blog_ID
		          																	AND bloggroup_group_ID = {$Group->ID} )
		         WHERE ";

		if( $permname != 'blog_admin' )
		{	// Only the admin perm is not convered by being the owner of the blog:
			$sql .= "blog_owner_user_ID = {$user_ID} ";
		}

		switch( $permname )
		{
			case 'blog_ismember':
				$sql .= "OR bloguser_ismember <> 0
								 OR bloggroup_ismember <> 0";
				break;

			case 'blog_post_statuses':
				$sql .= "OR bloguser_perm_poststatuses <> ''
							   OR bloggroup_perm_poststatuses <> ''";
				break;

			case 'blog_comments':
				// user needs to have permission for at least one kind of comments (published, draft, deprecated)
				$sql .= "OR bloguser_perm_draft_cmts <> 0
						OR bloguser_perm_publ_cmts <> 0
						OR bloguser_perm_depr_cmts <> 0
						OR bloggroup_perm_draft_cmts <> 0
						OR bloggroup_perm_publ_cmts <> 0
						OR bloggroup_perm_depr_cmts <> 0";
				break;

			case 'stats':
				$permname = 'blog_properties';	// TEMP
			case 'blog_cats':
			case 'blog_properties':
			case 'blog_admin':
			case 'blog_media_browse':
				$short_permname = substr( $permname, 5 );
				$sql .= "OR bloguser_perm_{$short_permname} <> 0
								 OR bloggroup_perm_{$short_permname} <> 0";
				break;

			default:
				debug_die( 'BlogCache::load_user_blogs() : Unsupported perm ['.$permname.']!' );
		}

		$sql .= " ORDER BY {$this->dbprefix}{$order_by}";

		if( $limit )
		{
			$sql .= " LIMIT {$limit}";
		}

		foreach( $DB->get_results( $sql, OBJECT, 'Load user blog list' ) as $row )
		{
			// Instantiate a custom object
			$this->instantiate( $row );
		}

		return $DB->get_col( NULL, 0 );
	}


	/**
	 * Returns form option list with cache contents
	 *
	 * Loads the whole cache!
	 *
	 * @param integer selected ID
	 * @param boolean provide a choice for "none" with ID 0
	 */
	function get_option_list( $default = 0, $allow_none = false, $method = 'get_name' )
	{
		// We force a full load!
		$this->load_all();

		return parent::get_option_list( $default, $allow_none, $method );
	}
}


/*
 * $Log$
 * Revision 1.14  2010/07/26 06:52:15  efy-asimo
 * MFB v-4-0
 *
 * Revision 1.13  2010/06/01 11:33:19  efy-asimo
 * Split blog_comments advanced permission (published, deprecated, draft)
 * Use this new permissions (Antispam tool,when edit/delete comments)
 *
 * Revision 1.12  2010/02/08 17:52:09  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.11  2009/09/26 12:00:42  tblue246
 * Minor/coding style
 *
 * Revision 1.10  2009/09/25 07:32:52  efy-cantor
 * replace get_cache to get_*cache
 *
 * Revision 1.9  2009/09/14 12:43:05  efy-arrin
 * Included the ClassName in load_class() call with proper UpperCase
 *
 * Revision 1.8  2009/09/05 18:17:40  tblue246
 * DataObjectCache/BlogCache::get_option_list(): Back again... Allow custom value for "None" option and use 0 for BlogCache.
 *
 * Revision 1.6  2009/09/03 15:51:52  tblue246
 * Doc, "refix", use "0" instead of an empty string for the "No blog" option.
 *
 * Revision 1.5  2009/03/08 23:57:42  fplanque
 * 2009
 *
 * Revision 1.4  2008/03/04 22:27:43  blueyed
 * MFB: Fix SQL injection through requested URL (commented out?!); fix indent
 *
 * Revision 1.3  2008/01/21 09:35:26  fplanque
 * (c) 2008
 *
 * Revision 1.2  2007/12/20 12:01:56  yabs
 * bug fix
 *
 * Revision 1.1  2007/06/25 10:59:32  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.25  2007/05/31 03:02:23  fplanque
 * Advanced perms now disabled by default (simpler interface).
 * Except when upgrading.
 * Enable advanced perms in blog settings -> features
 *
 * Revision 1.24  2007/05/30 01:18:56  fplanque
 * blog owner gets all permissions except advanced/admin settings
 *
 * Revision 1.23  2007/05/29 01:17:20  fplanque
 * advanced admin blog settings are now restricted by a special permission
 *
 * Revision 1.22  2007/05/09 01:58:57  fplanque
 * Widget to display other blogs from same owner
 *
 * Revision 1.21  2007/05/09 01:00:24  fplanque
 * optimized querying for blog lists
 *
 * Revision 1.20  2007/04/26 00:11:05  fplanque
 * (c) 2007
 *
 * Revision 1.19  2007/03/25 15:07:38  fplanque
 * multiblog fixes
 *
 * Revision 1.18  2006/12/17 23:44:35  fplanque
 * minor cleanup
 *
 * Revision 1.17  2006/12/07 23:13:10  fplanque
 * @var needs to have only one argument: the variable type
 * Otherwise, I can't code!
 *
 * Revision 1.16  2006/12/06 18:04:23  fplanque
 * doc
 *
 * Revision 1.15  2006/12/05 01:35:27  blueyed
 * Hooray for less complexity and the 8th param for DataObjectCache()
 *
 * Revision 1.14  2006/12/05 00:34:39  blueyed
 * Implemented custom "None" option text in DataObjectCache; Added for $ItemStatusCache, $GroupCache, UserCache and BlogCache; Added custom text for Item::priority_options()
 *
 * Revision 1.13  2006/11/24 18:27:23  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 */
?>
