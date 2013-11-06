<?php
/**
 * This file implements Blog handling functions.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2013 by Francois Planque - {@link http://fplanque.com/}
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
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * Update the advanced user/group permissions for edited blog
 *
 * @param int Blog ID
 * @param string 'user' or 'group'
 */
function blog_update_perms( $blog, $context = 'user' )
{
	global $DB;

 	/**
	 * @var User
	 */
	global $current_User;

	if( $context == 'user' )
	{
		$table = 'T_coll_user_perms';
		$prefix = 'bloguser_';
		$ID_field = 'bloguser_user_ID';
	}
	else
	{
		$table = 'T_coll_group_perms';
		$prefix = 'bloggroup_';
		$ID_field = 'bloggroup_group_ID';
	}

	// Get affected user/group IDs:
	$IDs = param( $context.'_IDs', '/^[0-9]+(,[0-9]+)*$/', '' );
	$ID_array = explode( ',', $IDs );
	// pre_dump( $ID_array );

	// Can the current user touch advanced admin permissions?
	if( ! $current_User->check_perm( 'blog_admin', 'edit', false, $blog ) )
	{	// We have no permission to touch advanced admins!
		// echo 'restrict';

		// Get the users/groups which are adavnced admins
		$admins_ID_array = $DB->get_col( "SELECT {$ID_field}
																				FROM $table
																			 WHERE {$ID_field} IN (".implode(',',$ID_array).")
																							AND {$prefix}blog_ID = $blog
																							AND {$prefix}perm_admin <> 0" );

		// Take the admins out of the list:
		$ID_array = array_diff( $ID_array, $admins_ID_array );
		// pre_dump( $ID_array );
	}
	// else echo 'adv admin';

	if( empty( $ID_array ) )
	{
		return;
	}

	// Delete old perms for this blog:
	$DB->query( "DELETE FROM $table
								WHERE {$ID_field} IN (".implode(',',$ID_array).")
											AND {$prefix}blog_ID = ".$blog );

	$inserted_values = array();
	foreach( $ID_array as $loop_ID )
	{ // Check new permissions for each user:
		// echo "<br/>getting perms for $ID_field : $loop_ID <br />";

		// Use checkboxes
		$perm_post = array();

		$ismember = param( 'blog_ismember_'.$loop_ID, 'integer', 0 );

		$perm_published = param( 'blog_perm_published_'.$loop_ID, 'string', '' );
		if( !empty($perm_published) ) $perm_post[] = 'published';

		$perm_community = param( 'blog_perm_community_'.$loop_ID, 'string', '' );
		if( !empty($perm_community) ) $perm_post[] = 'community';

		$perm_protected = param( 'blog_perm_protected_'.$loop_ID, 'string', '' );
		if( !empty($perm_protected) ) $perm_post[] = 'protected';

		$perm_private = param( 'blog_perm_private_'.$loop_ID, 'string', '' );
		if( !empty($perm_private) ) $perm_post[] = 'private';

		$perm_review = param( 'blog_perm_review_'.$loop_ID, 'string', '' );
		if( !empty($perm_review) ) $perm_post[] = 'review';

		$perm_draft = param( 'blog_perm_draft_'.$loop_ID, 'string', '' );
		if( !empty($perm_draft) ) $perm_post[] = 'draft';

		$perm_deprecated = param( 'blog_perm_deprecated_'.$loop_ID, 'string', '' );
		if( !empty($perm_deprecated) ) $perm_post[] = 'deprecated';

		$perm_redirected = param( 'blog_perm_redirected_'.$loop_ID, 'string', '' );
		if( !empty($perm_redirected) ) $perm_post[] = 'redirected';

		$perm_page    = param( 'blog_perm_page_'.$loop_ID, 'integer', 0 );
		$perm_intro   = param( 'blog_perm_intro_'.$loop_ID, 'integer', 0 );
		$perm_podcast = param( 'blog_perm_podcast_'.$loop_ID, 'integer', 0 );
		$perm_sidebar = param( 'blog_perm_sidebar_'.$loop_ID, 'integer', 0 );

		$perm_edit = param( 'blog_perm_edit_'.$loop_ID, 'string', 'no' );

		$perm_delpost = param( 'blog_perm_delpost_'.$loop_ID, 'integer', 0 );
		$perm_edit_ts = param( 'blog_perm_edit_ts_'.$loop_ID, 'integer', 0 );

		$perm_delcmts = param( 'blog_perm_delcmts_'.$loop_ID, 'integer', 0 );
		$perm_recycle_owncmts = param( 'blog_perm_recycle_owncmts_'.$loop_ID, 'integer', 0 );
		$perm_vote_spam_comments = param( 'blog_perm_vote_spam_cmts_'.$loop_ID, 'integer', 0 );
		$perm_cmtstatuses = 0;
		$perm_cmtstatuses += param( 'blog_perm_published_cmt_'.$loop_ID, 'integer', 0 ) ? get_status_permvalue( 'published' ) : 0;
		$perm_cmtstatuses += param( 'blog_perm_community_cmt_'.$loop_ID, 'integer', 0 ) ? get_status_permvalue( 'community' ) : 0;
		$perm_cmtstatuses += param( 'blog_perm_protected_cmt_'.$loop_ID, 'integer', 0 ) ? get_status_permvalue( 'protected' ) : 0;
		$perm_cmtstatuses += param( 'blog_perm_private_cmt_'.$loop_ID, 'integer', 0 ) ? get_status_permvalue( 'private' ) : 0;
		$perm_cmtstatuses += param( 'blog_perm_review_cmt_'.$loop_ID, 'integer', 0 ) ? get_status_permvalue( 'review' ) : 0;
		$perm_cmtstatuses += param( 'blog_perm_draft_cmt_'.$loop_ID, 'integer', 0 ) ? get_status_permvalue( 'draft' ) : 0;
		$perm_cmtstatuses += param( 'blog_perm_deprecated_cmt_'.$loop_ID, 'integer', 0 ) ? get_status_permvalue( 'deprecated' ) : 0;
		$perm_edit_cmt = param( 'blog_perm_edit_cmt_'.$loop_ID, 'string', 'no' );

		$perm_cats = param( 'blog_perm_cats_'.$loop_ID, 'integer', 0 );
		$perm_properties = param( 'blog_perm_properties_'.$loop_ID, 'integer', 0 );

		if( $current_User->check_perm( 'blog_admin', 'edit', false, $blog ) )
		{	// We have permission to give advanced admins perm!
			$perm_admin = param( 'blog_perm_admin_'.$loop_ID, 'integer', 0 );
		}
		else
		{
			$perm_admin = 0;
		}

		$perm_media_upload = param( 'blog_perm_media_upload_'.$loop_ID, 'integer', 0 );
		$perm_media_browse = param( 'blog_perm_media_browse_'.$loop_ID, 'integer', 0 );
		$perm_media_change = param( 'blog_perm_media_change_'.$loop_ID, 'integer', 0 );

		// Update those permissions in DB:

		if( $ismember || count($perm_post) || $perm_delpost || $perm_edit_ts || $perm_delcmts || $perm_recycle_owncmts || $perm_vote_spam_comments || $perm_cmtstatuses ||
			$perm_cats || $perm_properties || $perm_admin || $perm_media_upload || $perm_media_browse || $perm_media_change )
		{ // There are some permissions for this user:
			$ismember = 1;	// Must have this permission

			// insert new perms:
			$inserted_values[] = " ( $blog, $loop_ID, $ismember, ".$DB->quote(implode(',',$perm_post)).",
																".$DB->quote($perm_edit).",
																$perm_delpost, $perm_edit_ts, $perm_delcmts, $perm_recycle_owncmts, $perm_vote_spam_comments, $perm_cmtstatuses,
																".$DB->quote( $perm_edit_cmt ).",
																$perm_cats, $perm_properties, $perm_admin, $perm_media_upload,
																$perm_media_browse, $perm_media_change, $perm_page,	$perm_intro, $perm_podcast,
																$perm_sidebar )";
		}
	}

	// Proceed with insertions:
	if( count( $inserted_values ) )
	{
		$DB->query( "INSERT INTO $table( {$prefix}blog_ID, {$ID_field}, {$prefix}ismember,
											{$prefix}perm_poststatuses, {$prefix}perm_edit, {$prefix}perm_delpost, {$prefix}perm_edit_ts,
											{$prefix}perm_delcmts, {$prefix}perm_recycle_owncmts, {$prefix}perm_vote_spam_cmts, {$prefix}perm_cmtstatuses, {$prefix}perm_edit_cmt,
											{$prefix}perm_cats, {$prefix}perm_properties, {$prefix}perm_admin,
											{$prefix}perm_media_upload, {$prefix}perm_media_browse, {$prefix}perm_media_change,
											{$prefix}perm_page, {$prefix}perm_intro, {$prefix}perm_podcast, {$prefix}perm_sidebar )
									VALUES ".implode( ',', $inserted_values ) );
	}
}


/**
 * Check permissions on a given blog (by ID) and autoselect an appropriate blog
 * if necessary.
 *
 * For use in admin
 *
 * NOTE: we no longer try to set $Blog inside of the function because later global use cannot be safely guaranteed in PHP4.
 *
 * @param string Permission name that must be given to the {@link $current_User} object.
 * @param string Permission level that must be given to the {@link $current_User} object.
 * @return integer new selected blog
 */
function autoselect_blog( $permname, $permlevel = 'any' )
{
	global $blog;

  /**
	 * @var User
	 */
	global $current_User;

	$autoselected_blog = $blog;

	if( $autoselected_blog )
	{ // a blog is already selected
		if( !$current_User->check_perm( $permname, $permlevel, false, $autoselected_blog ) )
		{ // invalid blog
		 	// echo 'current blog was invalid';
			$autoselected_blog = 0;
		}
	}

	if( !$autoselected_blog )
	{ // No blog is selected so far (or selection was invalid)...
		// Let's try to find another one:

    /**
		 * @var BlogCache
		 */
		$BlogCache = & get_BlogCache();

		// Get first suitable blog
		$blog_array = $BlogCache->load_user_blogs( $permname, $permlevel, $current_User->ID, 'ID', 'ASC', 1 );
		if( !empty($blog_array) )
		{
			$autoselected_blog = $blog_array[0];
		}
	}

	return $autoselected_blog;
}


/**
 * Check that we have received a valid blog param
 *
 * For use in admin
 */
function valid_blog_requested()
{
	global $Blog, $Messages;
	if( empty( $Blog ) )
	{	// The requested blog does not exist
		$Messages->add( T_('The requested blog does not exist (any more?)'), 'error' );
		return false;
	}
	return true;
}


/**
 * Set working blog to a new value and memorize it in user settings if needed.
 *
 * For use in admin
 *
 * @return boolean $blog changed?
 */
function set_working_blog( $new_blog_ID )
{
	global $blog, $UserSettings;

	if( $new_blog_ID != (int)$UserSettings->get('selected_blog') )
	{	// Save the new default blog.
		// fp> Test case 1: dashboard without a blog param should go to last selected blog
		// fp> Test case 2: uploading to the default blog may actually upload into another root (sev)
		$UserSettings->set( 'selected_blog', $blog );
		$UserSettings->dbupdate();
	}

	if( $new_blog_ID == $blog )
	{
		return false;
	}

	$blog = $new_blog_ID;

	return true;
}


/**
 * @param string
 * @return array|string
 */
function get_collection_kinds( $kind = NULL )
{
	global $Plugins;

	$kinds = array(
		'std' => array(
				'name' => T_('Standard blog'),
				'desc' => T_('A standard blog with the most common features.'),
			),
		'photo' => array(
				'name' => T_('Photoblog'),
				'desc' => T_('A blog optimized to publishing photos.'),
			),
		'group' => array(
				'name' => T_('Group blog'),
				'desc' => T_('A blog optimized for team/collaborative editing. Posts can be assigned to different reviewers before being published. Look for the workflow properties at the bottom of the post editing form.'),
			),
		'forum' => array(
				'name' => T_('Forum'),
				'desc' => T_('A collection optimized to be used as a forum. (This should be used with a forums skin)'),
			),
		'manual' => array(
				'name' => T_('Manual'),
				'desc' => T_('A collection optimized to be used as an online manual, book or guide. (This should be used with a manual skin)'),
			),
		);

	// Define blog kinds, their names and description.
	$plugin_kinds = $Plugins->trigger_collect( 'GetCollectionKinds', array('kinds' => & $kinds) );

	foreach( $plugin_kinds as $l_kinds )
	{
		$kinds = array_merge( $l_kinds, $kinds );
	}

	if( is_null($kind) )
	{	// Return kinds array
		return $kinds;
	}

	if( array_key_exists( $kind, $kinds ) && !empty($kinds[$kind]['name']) )
	{
		return $kinds[$kind]['name'];
	}
	else
	{	// Use default collection kind
		return $kinds['std']['name'];
	}
}


/**
 * Enable/Disable the given cache
 *
 * @param string cache key name, 'general_cache_enabled', blogs 'cache_enabled'
 * @param boolean status to set
 * @param integer the id of the blog, if we want to set a blog's cache. Let it NULL to set general caching.
 * @param boolean true to save db changes, false if db update will be called outside from this function
 */
function set_cache_enabled( $cache_key, $new_status, $coll_ID = NULL, $save_setting = true )
{
	load_class( '_core/model/_pagecache.class.php', 'PageCache' );
	global $Settings;

	if( empty( $coll_ID ) )
	{ // general cache
		$Blog = NULL;
		$old_cache_status = $Settings->get( $cache_key );
		$cache_name = T_( 'General' );
	}
	else
	{ // blog page cache
		$BlogCache = & get_BlogCache();
		$Blog = $BlogCache->get_by_ID( $coll_ID );
		$old_cache_status = $Blog->get_setting( $cache_key );
		$cache_name = T_( 'Page' );
	}

	$PageCache = new PageCache( $Blog );
	if( $old_cache_status == false && $new_status == true )
	{ // Caching has been turned ON:
		if( $PageCache->cache_create( false ) )
		{ // corresponding cache folder was created
			$result = array( 'success', sprintf( T_( '%s caching has been enabled.' ), $cache_name ) );
		}
		else
		{ // error creating cache folder
			$result = array( 'error', sprintf( T_( '%s caching could not be enabled. Check /cache/ folder file permissions.' ), $cache_name ) );
			$new_status = false;
		}
	}
	elseif( $old_cache_status == true && $new_status == false )
	{ // Caching has been turned OFF:
		$PageCache->cache_delete();
		$result = array( 'note',  sprintf( T_( '%s caching has been disabled. Cache contents have been purged.' ), $cache_name ) );
	}
	else
	{ // nothing was changed
		// check if ajax_form_enabled has correct state after b2evo upgrade
		if( ( $Blog != NULL ) && ( $new_status ) && ( !$Blog->get_setting( 'ajax_form_enabled' ) ) )
		{ // if page cache is enabled, ajax form must be enabled to
			$Blog->set_setting( 'ajax_form_enabled', true );
			$Blog->dbupdate();
		}
		return NULL;
	}

	// set db changes
	if( $Blog == NULL )
	{
		$Settings->set( 'general_cache_enabled', $new_status );
		if( $save_setting )
		{ // save
			$Settings->dbupdate();
		}
	}
	else
	{
		$Blog->set_setting( $cache_key, $new_status );
		if( ( $cache_key == 'cache_enabled' ) && $new_status )
		{ // if page cache is enabled, ajax form must be enabled to
			$Blog->set_setting( 'ajax_form_enabled', true );
		}
		if( $save_setting )
		{ // save
			$Blog->dbupdate();
		}
	}
	return $result;
}


/**
 * Initialize global $blog variable to the requested blog
 *
 * @return boolean true if $blog was initialized successful, false otherwise
 */
function init_requested_blog()
{
	global $blog, $ReqHost, $ReqPath;
	global $Settings;
	global $Debuglog;

	if( !empty( $blog ) )
	{ // blog was already initialized
		return true;
	}

	// Check if a specific blog has been requested in the URL:
	$blog = param( 'blog', 'integer', '', true );

	if( !empty($blog) )
	{ // a specific blog has been requested in the URL
		return true;
	}

	// No blog requested by URL param, let's try to match something in the URL
	$Debuglog->add( 'No blog param received, checking extra path...', 'detectblog' );
	$BlogCache = & get_BlogCache();
	if( preg_match( '#^(.+?)index.php/([^/]+)#', $ReqHost.$ReqPath, $matches ) )
	{ // We have an URL blog name:
		$Debuglog->add( 'Found a potential URL blog name: '.$matches[2], 'detectblog' );
		if( (($Blog = & $BlogCache->get_by_urlname( $matches[2], false )) !== false) )
		{ // We found a matching blog:
			$blog = $Blog->ID;
			return true;
		}
	}

	// No blog identified by URL name, let's try to match the absolute URL
	if( preg_match( '#^(.+?)index.php#', $ReqHost.$ReqPath, $matches ) )
	{ // Remove what's not part of the absolute URL
		$ReqAbsUrl = $matches[1];
	}
	else
	{
		$ReqAbsUrl = $ReqHost.$ReqPath;
	}
	$Debuglog->add( 'Looking up absolute url : '.$ReqAbsUrl, 'detectblog' );
	if( (($Blog = & $BlogCache->get_by_url( $ReqAbsUrl, false )) !== false) )
	{ // We found a matching blog:
		$blog = $Blog->ID;
		$Debuglog->add( 'Found matching blog: '.$blog, 'detectblog' );
		return true;
	}

	// Still no blog requested, use default
	$blog = $Settings->get('default_blog_ID');
	if( (($Blog = & $BlogCache->get_by_ID( $blog, false, false )) !== false) )
	{ // We found a matching blog:
		$Debuglog->add( 'Using default blog '.$blog, 'detectblog' );
		return true;
	}

	$blog = NULL;
	return false;
}


/**
 * Initialize blog enabled widgets. It will call every enabled widget request_required_files() function.
 *
 * @param integer blog ID
 */
function init_blog_widgets( $blog_id )
{
	/**
	 * @var EnabledWidgetCache
	 */
	$EnabledWidgetCache = & get_EnabledWidgetCache();
	$container_Widget_array = & $EnabledWidgetCache->get_by_coll_ID( $blog_id );

	if( !empty($container_Widget_array) )
	{
		foreach( $container_Widget_array as $container=>$Widget_array )
		{
			foreach( $Widget_array as $ComponentWidget )
			{	// Let the Widget initialize itself:
				$ComponentWidget->request_required_files();
			}
		}
	}
}


/**
 * Check if user is activated and status allow to display the requested form ($disp).
 * Do nothing if status is activated or can't be activated or display is not allowed, add error message to activate the account otherwise.
 * asimo>TODO: We may find a better name and a better place for this function ( maybe user.funcs.php )
 *
 * @param string the requested view name
 */
function check_allow_disp( $disp )
{
	global $Blog, $Messages, $Settings, $current_User, $secure_htsrv_url;

	if( !check_user_status( 'can_be_validated' ) )
	{ // we don't have the case when user is logged in and the account is not active
		return;
	}

	$messages_content = $Messages->get_string( '', '', '', 'raw' );
	if( ( strstr( $messages_content, 'disp=activateinfo' ) !== false ) || ( strstr( $messages_content, 'action=req_validatemail' ) !== false ) )
	{ // If there is already a message to display activateinfo link, then don't add this message again
		return;
	}

	switch( $disp )
	{
		case 'activateinfo':
			// don't display activate account error notification in activate info page
			return;
			break; // already exited before this
		case 'contacts':
			if( !$current_User->check_status( 'can_view_contacts' ) )
			{ // contacts view display is not allowed
				return;
			}
			break;
		case 'edit':
			if( !$current_User->check_status( 'can_edit_post' ) )
			{ // edit post is not allowed
				return;
			}
			break;
		case 'messages':
			if( !$current_User->check_status( 'can_view_messages' ) )
			{ // messages view display is not allowed
				return;
			}
			break;
		case 'msgform':
			if( !$current_User->check_status( 'can_view_msgform' ) )
			{ // msgform display is not allowed
				return;
			}
			break;
		case 'threads':
			if( !$current_User->check_status( 'can_view_threads' ) )
			{ // threads view display is not allowed
				return;
			}
			break;
		case 'user':
			$user_ID = param( 'user_ID', 'integer', '', true );
			if( !$current_User->check_status( 'can_view_user', $user_ID ) )
			{ // user profile display is not allowed
				return;
			}
			break;
		case 'users':
			if( !$current_User->check_status( 'can_view_users' ) )
			{ // not active user can't see users list
				return;
			}
			break;
		default:
			break;
	}

	// User is allowed to see the requested view, but show an account activation error message
	if( $Blog->get_setting( 'in_skin_login' ) )
	{
		$activateinfo_link = 'href="'.url_add_param( $Blog->gen_blogurl(), 'disp=activateinfo' ).'"';
	}
	else
	{
		$activateinfo_link = 'href="'.$secure_htsrv_url.'login.php?action=req_validatemail'.'"';
	}
	$Messages->add( sprintf( T_( 'IMPORTANT: your account is not active yet! Activate your account now by clicking on the activation link in the email we sent you. <a %s>More info &raquo;</a>' ), $activateinfo_link ) );
}


/**
 * Get the highest public status and action button lable of a new post or comment in the given blog what the current User may create.
 *
 * @param string 'post' or 'comment'
 * @param integer blog ID
 * @param boolean set false to get only the status without the action button label
 * @return mixed string status if with_label is false, array( status, label ) if with_label is true
 */
function get_highest_publish_status( $type, $blog, $with_label = true )
{
	global $current_User;

	if( ( $type != 'post' ) && ( $type != 'comment' ) )
	{ // $type is invalid
		debug_die( 'Invalid type parameter!' );
	}

	$BlogCache = & get_BlogCache();
	$requested_Blog = $BlogCache->get_by_ID( $blog );
	$default_status = ( $type == 'post' ) ? $requested_Blog->get_setting( 'default_post_status' ) : $requested_Blog->get_setting( 'new_feedback_status' );
	$status = array( $default_status, '' );

	if( empty( $current_User ) || ( ( !$requested_Blog->get( 'advanced_perms' ) ) && ( !$current_User->check_perm_blog_global( $blog, 'editall' ) ) ) )
	{ // creator User is not set or collection advanced perms are not enabled and user has no global perms on the given blog, set status to the default status
		return ( $with_label ? $status : $default_status );
	}

	$status_order = get_visibility_statuses( 'ordered-array' );
	$highest_index = count( $status_order ) - 1;
	$result = false;
	for( $index = $highest_index; $index > 0; $index-- )
	{
		$curr_status = $status_order[$index][0];
		if( $current_User->check_perm( 'blog_'.$type.'!'.$curr_status, 'create', false, $blog ) )
		{ // the highest publish status has be found
			$result = array( $curr_status, $status_order[$index][1] );
			break;
		}
	}

	if( !$result )
	{ // there are no available public status
		if( $current_User->check_perm( 'blog_'.$type.'!private', 'create', false, $blog ) )
		{ // check private status 
			$result = array( 'private', T_('Make private!') );
		}
		else
		{ // Only draft is allowed
			$result = array( 'draft', '' );
		}
	}

	if( $with_label )
	{
		return $result;
	}

	// Return only the highest available visibility status without label
	return $result[0];
}


/**
 * Retrieves all tags from published posts
 *
 * @param integer the id of the blog or array of blog ids. Set NULL to use current blog
 * @param integer maximum number of returned tags
 * @param string a comma separated list of tags to ignore/exclude
 * @param bool true to skip tags from pages, intro posts and sidebar stuff
 * @return array of tags
 */
function get_tags( $blog_ids, $limit = 0, $filter_list = NULL, $skip_intro_posts = false )
{
	global $DB, $localtimenow, $posttypes_specialtypes;

	$BlogCache = & get_BlogCache();

	if( is_null($blog_ids) )
	{
		global $blog;
		$blog_ids = $blog;
	}

	if( is_array($blog_ids) )
	{	// Get quoted ID list
		$blog_ids = $DB->quote($blog_ids);
		$where_cats = 'cat_blog_ID IN ('.$blog_ids.')';
	}
	else
	{
		$Blog = & $BlogCache->get_by_ID($blog_ids);

		// Get list of relevant blogs
		$where_cats = trim($Blog->get_sql_where_aggregate_coll_IDs('cat_blog_ID'));
	}

	// fp> verrry dirty and params; TODO: clean up
	// dh> oddly, this appears to not get cached by the query cache. Have experimented a bit, but not found the reason.
	//     It worked locally somehow, but not live.
	//     This takes up to ~50% (but more likely 15%) off the total SQL time. With the query being cached, it would be far better.

	// build query, only joining categories, if not using all.
	$sql = 'SELECT LOWER(tag_name) AS tag_name, post_datestart, COUNT(DISTINCT itag_itm_ID) AS tag_count, tag_ID, cat_blog_ID
			FROM T_items__tag
			INNER JOIN T_items__itemtag ON itag_tag_ID = tag_ID';

	if( $where_cats != '1' )
	{	// we have to join the cats
		$sql .= '
		 INNER JOIN T_postcats ON itag_itm_ID = postcat_post_ID
		 INNER JOIN T_categories ON postcat_cat_ID = cat_ID';
	}

	$sql .= "
		 INNER JOIN T_items__item ON itag_itm_ID = post_ID
		 WHERE $where_cats
		   AND post_status = 'published' AND post_datestart < '".remove_seconds($localtimenow)."'";

	if( $skip_intro_posts )
	{
		$sql .= ' AND post_ptyp_ID NOT IN ('.implode(',',$posttypes_specialtypes).')';
	}

	if( !empty($filter_list) )
	{	// Filter tags
		$filter_list = explode( ',', $filter_list ) ;

		$filter_tags = array();
		foreach( $filter_list as $l_tag )
		{
			$filter_tags[] = '"'.$DB->escape(trim($l_tag)).'"';
		}

		$sql .= ' AND tag_name NOT IN ('.implode(', ', $filter_tags).')';
	}

	$sql .= ' GROUP BY tag_name ORDER BY tag_count DESC';

	if( !empty($limit) )
	{
		$sql .= ' LIMIT '.$limit;
	}

	return $DB->get_results( $sql, OBJECT, 'Get tags' );
}


/**
 * Get a list of those statuses which can be displayed in the front office
 * 
 * @return array
 */
function get_inskin_statuses()
{
	return array( 'published', 'community', 'protected', 'private', 'review', 'draft' );
}


/**
 * Get available post statuses
 *
 * @param string Statuses format, defaults to translated statuses
 * @param array Statuses to exclude. Unused 'trash' status excluded by default
 * @return array of statuses
 */
function get_visibility_statuses( $format = '', $exclude = array('trash') )
{
	switch( $format )
	{
		case 'notes-array':
		case 'notes-string':
		case 'radio-options':
			// Array notes for visibility_select()
			$r = array(
					'published'  => array( T_('Public'),     '('.T_('Everyone').')' ),
					'community'  => array( T_('Community'),  '('.T_('Logged in users only').')' ),
					'protected'  => array( T_('Members'),    '('.T_('Blog members only').')' ),
					'review'     => array( T_('Review'),     '('.T_('Moderators only (+You)').')' ),
					'private'    => array( T_('Private'),    '('.T_('You only').')' ),
					'draft'      => array( T_('Draft'),      '('.T_('You only (+backoffice users)').')' ),
					'deprecated' => array( T_('Deprecated'), '('.T_('Not published!').')' ),
					'redirected' => array( T_('Redirected'), '(301)' ),
					'trash'      => array( T_('Recycled'),   '' )
				);

			if( $format == 'notes-string' )
			{	// String notes
				$r = array_map( create_function('$v', 'return implode(" ", $v);'), $r );
			}
			break;

		case 'moderation-titles':
			$change_status = T_('Change status to').': ';
			$visible_by = ' ('.T_('Visible by').': ';
			$r = array(
					'published'  => $change_status.T_('Public').$visible_by.T_('Everyone').')',
					'community'  => $change_status.T_('Community').$visible_by.T_('Logged in users only').')',
					'protected'  => $change_status.T_('Members').$visible_by.T_('Blog members only').')',
					'review'     => $change_status.T_('Review').$visible_by.T_('Moderators only (+You)').')',
					'private'    => $change_status.T_('Private').$visible_by.T_('You only').')',
					'draft'      => $change_status.T_('Draft').$visible_by.T_('You only (+backoffice users)').')',
					'deprecated' => $change_status.T_('Deprecated').' ('.T_('Not published!').')',
					'redirected' => '',
					'trash'      => ''
				);
			break;

		case 'legend-titles':
			$r = array(
					'published' => T_('Visible by anyone'),
					'community' => T_('Visible by logged-in users only'),
					'protected' => T_('Visible by members only'),
					'review'    => T_('Waiting for moderator review'),
					'private'   => T_('Visible by you only'),
					'draft'     => T_('Unfinished post'),
				);
			break;

		case 'ordered-array': // indexed array, ordered from the lowest to the highest public level
			$r = array(
				0 => array( 'deprecated', '', T_('Deprecate!'), 'grey' ),
				1 => array( 'review', T_('Open to moderators!'), T_('Restrict to moderators!'), 'magenta' ),
				2 => array( 'protected', T_('Open to members!'), T_('Restrict to members!'), 'orange' ),
				3 => array( 'community', T_('Open to community!'), T_('Restrict to community!'), 'blue' ),
				4 => array( 'published', T_('Make public!'), '', 'green' )
			);
			return $r;

		case 'ordered-index': // gives each status index in the statuses ordered array
			$r = array(
				'redirected' => 0,
				'trash'      => 0,
				'private'    => 0,
				'draft'      => 0,
				'deprecated' => 0,
				'review'     => 1,
				'protected'  => 2,
				'community'  => 3,
				'published'  => 4,
			);
			break;

		case 'dashboard':
			$r = array( 'community', 'protected', 'review', 'draft' );
			return $r;

		case 'raw':
		default:
			$r = array (
					'published'  => NT_('Public'),
					'community'  => NT_('Community'),
					'protected'  => NT_('Members'),
					'private'    => NT_('Private'),
					'review'     => NT_('Review'),
					'draft'      => NT_('Draft'),
					'deprecated' => NT_('Deprecated'),
					'redirected' => NT_('Redirected'),
					'trash'      => NT_('Recycled'),
				);

			if( $format != 'keys' && $format != 'raw' )
			{	// Translate statuses (default format)
				$r = array_map( 'T_', $r );
			}
	}

	if( !empty($exclude) )
	{
		// PHP 5.1 array_diff_key( $r, $exclude );
		foreach( $exclude as $ex )
		{
			if( isset($r[$ex]) )
			{
				unset($r[$ex]);
			}
		}
	}

	if( $format == 'keys' )
	{ // Return status keys for 'visibility_array'
		$r = array_keys( $r );
	}

	if( $format == 'radio-options' )
	{ // Return options for radio buttons
		$radio_options = array();
		foreach( $r as $status => $labels )
		{
			$radio_options[] = array( $status, $labels[0].' <span class="notes">'.$labels[1].'</span>' );
		}
		return $radio_options;
	}

	return $r;
}


/**
 * Compare two visibility status in the point of public level
 *
 * @param string first_status
 * @param string second_status
 * @return integer
 *   0 if the two statuses have the same public level
 *   1 if the first status has higher public level
 *   -1 if it first status has lower public level
 */
function compare_visibility_status( $first_status, $second_status )
{
	$status_index = get_visibility_statuses( 'ordered-index', array() );
	if( !isset( $status_index[$first_status] ) || !isset( $status_index[$second_status] ) )
	{ // At least one of the given statuses doesn't exist
		debug_die( 'Invalid status given to compare!' );
	}

	$first_status_index = $status_index[$first_status];
	$second_status_index = $status_index[$second_status];
	if( $first_status_index == $second_status_index )
	{ // The two status public level is equal, but note this doesn't mean that the two status must be same!
		return 0;
	}

	return ( $first_status_index > $second_status_index ) ? 1 : -1;
}


/**
 * Get restricted visibility statuses for the current User in the given blog in back office
 * 
 * @param integer blog ID
 * @param string permission prefix: 'blog_post!' or 'blog_comment!'
 * @param string permlevel: 'view'/'edit' depending on where we would like to use it
 * @return array of restricted statuses
 */
function get_restricted_statuses( $blog_ID, $prefix, $permlevel = 'view' )
{
	global $current_User;

	$result = array();

	// This statuses are allowed to view/edit only for those users who may create post/comment with these statuses
	$restricted = array( 'review', 'draft', 'deprecated', 'private' );
	foreach( $restricted as $status )
	{
		if( !$current_User->check_perm( $prefix.$status, 'create', false, $blog_ID ) )
		{ // not allowed
			$result[] = $status;
		}
	}

	// 'redirected' status is allowed to view/edit only in case of posts, and only if user has permission
	if( ( $prefix == 'blog_post!' ) && !$current_User->check_perm( $prefix.'redirected', 'create', false, $blog_ID ) )
	{ // not allowed
		$result[] = 'redirected';
	}

	// 'trash' status is allowed only in case of comments, and only if user has global editall permission
	if( ( $prefix == 'blog_comment!' ) && !$current_User->check_perm( 'blogs', 'editall', false ) )
	{ // not allowed
		$result[] = 'trash';
	}

	// The other statuses are always allowed to view in backoffice
	if( $permlevel != 'view' )
	{ // in case of other then 'view' action we must check the permissions
		$restricted = array( 'published', 'community', 'protected' );
		foreach( $restricted as $status )
		{
			if( !$current_User->check_perm( $prefix.$status, 'create', false, $blog_ID ) )
			{ // not allowed
				$result[] = $status;
			}
		}
	}

	return $result;
}


/**
 * Display blogs results table
 *
 * @param array Params
 */
function blogs_results_block( $params = array() )
{
	// Make sure we are not missing any param:
	$params = array_merge( array(
			'edited_User'          => NULL,
			'results_param_prefix' => 'actv_blog_',
			'results_title'        => T_('Blogs owned by the user'),
			'results_no_text'      => T_('User does not own any blogs'),
		), $params );

	if( !is_logged_in() )
	{	// Only logged in users can access to this function
		return;
	}

	global $current_User;
	if( !$current_User->check_perm( 'users', 'edit' ) || !$current_User->check_perm( 'blogs', 'view' ) )
	{	// Check minimum permission:
		return;
	}

	$edited_User = $params['edited_User'];
	if( !$edited_User )
	{	// No defined User, probably the function is calling from AJAX request
		$user_ID = param( 'user_ID', 'integer', 0 );
		if( empty( $user_ID ) )
		{	// Bad request, Exit here
			return;
		}
		$UserCache = & get_UserCache();
		if( ( $edited_User = & $UserCache->get_by_ID( $user_ID, false ) ) === false )
		{	// Bad request, Exit here
			return;
		}
	}

	global $DB;

	param( 'user_tab', 'string', '', true );
	param( 'user_ID', 'integer', 0, true );

	$SQL = new SQL();
	$SQL->SELECT( '*' );
	$SQL->FROM( 'T_blogs' );
	$SQL->WHERE( 'blog_owner_user_ID = '.$DB->quote( $edited_User->ID ) );

	// Create result set:
	$blogs_Results = new Results( $SQL->get(), $params['results_param_prefix'] );
	$blogs_Results->Cache = & get_BlogCache();
	$blogs_Results->title = $params['results_title'];
	$blogs_Results->no_results_text = $params['results_no_text'];

	// Get a count of the blogs which current user can delete
	$deleted_blogs_count = count( $edited_User->get_deleted_blogs() );
	if( $blogs_Results->total_rows > 0 && $deleted_blogs_count > 0 )
	{	// Display action icon to delete all records if at least one record exists & user can delete at least one blog
		$blogs_Results->global_icon( sprintf( T_('Delete all blogs owned by %s'), $edited_User->login ), 'delete', '?ctrl=user&amp;user_tab=activity&amp;action=delete_all_blogs&amp;user_ID='.$edited_User->ID.'&amp;'.url_crumb('user'), ' '.T_('Delete all'), 3, 4 );
	}

	// Initialize Results object
	blogs_results( $blogs_Results, array(
			'display_owner' => false,
		) );

	if( is_ajax_content() )
	{	// init results param by template name
		if( !isset( $params[ 'skin_type' ] ) || ! isset( $params[ 'skin_name' ] ) )
		{
			debug_die( 'Invalid ajax results request!' );
		}
		$blogs_Results->init_params_by_skin( $params[ 'skin_type' ], $params[ 'skin_name' ] );
	}

	$display_params = array(
		'before' => '<div class="results" style="margin-top:25px" id="owned_blogs_result">'
	);
	$blogs_Results->display( $display_params );

	if( !is_ajax_content() )
	{	// Create this hidden div to get a function name for AJAX request
		echo '<div id="'.$params['results_param_prefix'].'ajax_callback" style="display:none">'.__FUNCTION__.'</div>';
	}
}


/**
 * Initialize Results object for blogs list
 *
 * @param object Results
 * @param array Params
 */
function blogs_results( & $blogs_Results, $params = array() )
{
	// Make sure we are not missing any param:
	$params = array_merge( array(
			'display_id' => true,
			'display_name' => true,
			'display_fullname' => true,
			'display_owner' => true,
			'display_url' => true,
			'display_locale' => true,
			'display_actions' => true,
		), $params );

	if( $params['display_id'] )
	{	// Display ID column
		$blogs_Results->cols[] = array(
				'th' => T_('ID'),
				'order' => 'blog_ID',
				'th_class' => 'shrinkwrap',
				'td_class' => 'shrinkwrap',
				'td' => '$blog_ID$',
			);
	}

	if( $params['display_name'] )
	{	// Display Name column
		$blogs_Results->cols[] = array(
				'th' => T_('Name'),
				'order' => 'blog_shortname',
				'td' => '<strong>%disp_coll_name( #blog_shortname#, #blog_ID# )%</strong>',
			);
	}

	if( $params['display_name'] )
	{	// Display Full Name column
		$blogs_Results->cols[] = array(
				'th' => T_('Full Name'),
				'order' => 'blog_name',
				'td' => '%strmaxlen( #blog_name#, 40, NULL, "raw" )%',
			);
	}

	if( $params['display_owner'] )
	{	// Display Owner column
		$blogs_Results->cols[] = array(
				'th' => T_('Owner'),
				'order' => 'user_login',
				'td' => '%get_user_identity_link( #user_login# )%',
			);
	}

	if( $params['display_url'] )
	{	// Display Blog URL column
		$blogs_Results->cols[] = array(
				'th' => T_('Blog URL'),
				'td' => '<a href="@get(\'url\')@">@get(\'url\')@</a>',
			);
	}

	if( $params['display_locale'] )
	{	// Display Locale column
		$blogs_Results->cols[] = array(
				'th' => T_('Locale'),
				'order' => 'blog_locale',
				'th_class' => 'shrinkwrap',
				'td_class' => 'shrinkwrap',
				'td' => '%locale_flag( #blog_locale# )%',
			);
	}

	if( $params['display_actions'] )
	{	// Display Actions column
		$blogs_Results->cols[] = array(
				'th' => T_('Actions'),
				'th_class' => 'shrinkwrap',
				'td_class' => 'shrinkwrap',
				'td' => '%disp_actions( #blog_ID# )%',
			);
	}
}


/**
 * Helper functions to display Blogs results.
 * New ( not display helper ) functions must be created above blogs_results function
 */

/**
 * Get a blogs name with link to edit
 *
 * @param string Blog name
 * @param integer Blog ID
 * @return string Link
 */
function disp_coll_name( $coll_name, $coll_ID )
{
	global $current_User, $ctrl;
	if( $ctrl == 'dashboard' )
	{	// Dashboard
		$edit_url = regenerate_url( 'ctrl', 'ctrl=dashboard&amp;blog='.$coll_ID );
		$r = '<a href="'.$edit_url.'">';
		$r .= $coll_name;
		$r .= '</a>';
	}
	elseif( $current_User->check_perm( 'blog_properties', 'edit', false, $coll_ID ) )
	{	// Blog setting & can edit
		$edit_url = regenerate_url( 'ctrl', 'ctrl=coll_settings&amp;blog='.$coll_ID );
		$r = '<a href="'.$edit_url.'" title="'.T_('Edit properties...').'">';
		$r .= $coll_name;
		$r .= '</a>';
	}
	else
	{
		$r = $coll_name;
	}
	return $r;
}


/**
 * Get available actions for current blog
 *
 * @param integer Blog ID
 * @return string Action links
 */
function disp_actions( $curr_blog_ID )
{
	global $current_User, $admin_url;
	$r = '';

	if( $current_User->check_perm( 'blog_properties', 'edit', false, $curr_blog_ID ) )
	{
		$r .= action_icon( T_('Edit properties...'), 'properties', $admin_url.'?ctrl=coll_settings&amp;blog='.$curr_blog_ID );
	}

	if( $current_User->check_perm( 'blog_cats', '', false, $curr_blog_ID ) )
	{
		$r .= action_icon( T_('Edit categories...'), 'edit', $admin_url.'?ctrl=chapters&amp;blog='.$curr_blog_ID );
	}

	if( $current_User->check_perm( 'blog_properties', 'edit', false, $curr_blog_ID ) )
	{
		$r .= action_icon( T_('Delete this blog...'), 'delete', $admin_url.'?ctrl=collections&amp;action=delete&amp;blog='.$curr_blog_ID.'&amp;'.url_crumb('collection').'&amp;redirect_to='.rawurlencode( regenerate_url( '', '', '', '&' ) ) );
	}

	if( empty($r) )
	{ // for IE
		$r = '&nbsp;';
	}

	return $r;
}

/**
 * End of helper functions block to display Blogs results.
 * New ( not display helper ) functions must be created above blogs_results function
 */

?>