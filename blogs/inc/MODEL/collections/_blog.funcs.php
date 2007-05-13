<?php
/**
 * This file implements Blog handling functions.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2007 by Francois PLANQUE - {@link http://fplanque.net/}
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
 * @author gorgeb: Bertrand GORGE / EPISTEMA
 * @author sakichan: Nobuo SAKIYAMA.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * Update the user permissions for edited blog
 *
 * @param int Blog ID
 */
function blog_update_user_perms( $blog )
{
	global $DB;

	$user_IDs = param( 'user_IDs', '/^[0-9]+(,[0-9]+)*$/', '' );
	// pre_dump( $user_IDs );
	if( !empty( $user_IDs ) )
	{
		// Delete old perms for this blog:
		$DB->query( 'DELETE FROM T_coll_user_perms
									WHERE bloguser_user_ID IN ('.$user_IDs.')
												AND bloguser_blog_ID = '.$blog );
	}

	// Now we need a full user list:
	$inserted_values = array();
	foreach( $DB->get_col( 'SELECT user_ID FROM T_users' ) as $loop_user_ID )
	{ // Check new permissions for each user:
		// echo "getting perms for user : $loop_user_ID <br />";

		$easy_mode = param( 'blog_perm_easy_'.$loop_user_ID, 'string', 'nomember' );

		if( $easy_mode != 'nomember' && $easy_mode != 'custom' )
		{
			$easy_perms = blogperms_from_easy( $easy_mode );

			$inserted_values[] = " ( $blog, $loop_user_ID, ".$easy_perms['bloguser_ismember']
														.', "'.$easy_perms['bloguser_perm_poststatuses']
														.'", '.$easy_perms['bloguser_perm_delpost'].', '.$easy_perms['bloguser_perm_comments']
														.', '.$easy_perms['bloguser_perm_cats'].', '.$easy_perms['bloguser_perm_properties']
														.', '.$easy_perms['bloguser_perm_media_upload'].', '.$easy_perms['bloguser_perm_media_browse']
														.', '.$easy_perms['bloguser_perm_media_change'].' ) ';
		}
		else
		{
			$perm_post = array();

			$ismember = param( 'blog_ismember_'.$loop_user_ID, 'integer', 0 );

			$perm_published = param( 'blog_perm_published_'.$loop_user_ID, 'string', '' );
			if( !empty($perm_published) ) $perm_post[] = 'published';

			$perm_protected = param( 'blog_perm_protected_'.$loop_user_ID, 'string', '' );
			if( !empty($perm_protected) ) $perm_post[] = 'protected';

			$perm_private = param( 'blog_perm_private_'.$loop_user_ID, 'string', '' );
			if( !empty($perm_private) ) $perm_post[] = 'private';

			$perm_draft = param( 'blog_perm_draft_'.$loop_user_ID, 'string', '' );
			if( !empty($perm_draft) ) $perm_post[] = 'draft';

			$perm_deprecated = param( 'blog_perm_deprecated_'.$loop_user_ID, 'string', '' );
			if( !empty($perm_deprecated) ) $perm_post[] = 'deprecated';

			$perm_redirected = param( 'blog_perm_redirected_'.$loop_user_ID, 'string', '' );
			if( !empty($perm_redirected) ) $perm_post[] = 'redirected';

			$perm_delpost = param( 'blog_perm_delpost_'.$loop_user_ID, 'integer', 0 );
			$perm_comments = param( 'blog_perm_comments_'.$loop_user_ID, 'integer', 0 );
			$perm_cats = param( 'blog_perm_cats_'.$loop_user_ID, 'integer', 0 );
			$perm_properties = param( 'blog_perm_properties_'.$loop_user_ID, 'integer', 0 );

			$perm_media_upload = param( 'blog_perm_media_upload_'.$loop_user_ID, 'integer', 0 );
			$perm_media_browse = param( 'blog_perm_media_browse_'.$loop_user_ID, 'integer', 0 );
			$perm_media_change = param( 'blog_perm_media_change_'.$loop_user_ID, 'integer', 0 );

			// Update those permissions in DB:

			if( $ismember || count($perm_post) || $perm_delpost || $perm_comments || $perm_cats || $perm_properties
										|| $perm_media_upload || $perm_media_browse || $perm_media_change )
			{ // There are some permissions for this user:
				$ismember = 1;	// Must have this permission

				// insert new perms:
				$inserted_values[] = " ( $blog, $loop_user_ID, $ismember, '".implode(',',$perm_post)."',
																	$perm_delpost, $perm_comments, $perm_cats, $perm_properties,
																	$perm_media_upload, $perm_media_browse, $perm_media_change )";
			}
		}
	}

	// Proceed with insertions:
	if( count( $inserted_values ) )
	{
		$DB->query( "INSERT INTO T_coll_user_perms( bloguser_blog_ID, bloguser_user_ID, bloguser_ismember,
											bloguser_perm_poststatuses, bloguser_perm_delpost, bloguser_perm_comments,
											bloguser_perm_cats, bloguser_perm_properties,
											bloguser_perm_media_upload, bloguser_perm_media_browse, bloguser_perm_media_change)
									VALUES ".implode( ',', $inserted_values ) );
	}
}


/**
 * Update the group permissions for edited blog
 *
 * @param int Blog ID
 */
function blog_update_group_perms( $blog )
{
	global $DB;
	// Delete old perms for this blog:
	$DB->query( 'DELETE FROM T_coll_group_perms
								WHERE bloggroup_blog_ID = '.$blog );

	// Now we need a full group list:
	$inserted_values = array();
	foreach( $DB->get_col( 'SELECT grp_ID FROM T_groups' ) as $loop_group_ID )
	{ // Check new permissions for each group:
		// echo "getting perms for group : $loop_group_ID <br />";

		$easy_mode = param( 'blog_perm_easy_'.$loop_group_ID, 'string', 'nomember' );

		if( $easy_mode != 'nomember' && $easy_mode != 'custom' )
		{
			$easy_perms = blogperms_from_easy( $easy_mode );

			$inserted_values[] = " ( $blog, $loop_group_ID, ".$easy_perms['bloguser_ismember']
														.', "'.$easy_perms['bloguser_perm_poststatuses']
														.'", '.$easy_perms['bloguser_perm_delpost'].', '.$easy_perms['bloguser_perm_comments']
														.', '.$easy_perms['bloguser_perm_cats'].', '.$easy_perms['bloguser_perm_properties']
														.', '.$easy_perms['bloguser_perm_media_upload'].', '.$easy_perms['bloguser_perm_media_browse']
														.', '.$easy_perms['bloguser_perm_media_change'].' ) ';
		}
		else
		{
			$perm_post = array();

			$ismember = param( 'blog_ismember_'.$loop_group_ID, 'integer', 0 );

			$perm_published = param( 'blog_perm_published_'.$loop_group_ID, 'string', '' );
			if( !empty($perm_published) ) $perm_post[] = 'published';

			$perm_protected = param( 'blog_perm_protected_'.$loop_group_ID, 'string', '' );
			if( !empty($perm_protected) ) $perm_post[] = 'protected';

			$perm_private = param( 'blog_perm_private_'.$loop_group_ID, 'string', '' );
			if( !empty($perm_private) ) $perm_post[] = 'private';

			$perm_draft = param( 'blog_perm_draft_'.$loop_group_ID, 'string', '' );
			if( !empty($perm_draft) ) $perm_post[] = 'draft';

			$perm_deprecated = param( 'blog_perm_deprecated_'.$loop_group_ID, 'string', '' );
			if( !empty($perm_deprecated) ) $perm_post[] = 'deprecated';

			$perm_redirected = param( 'blog_perm_redirected_'.$loop_group_ID, 'string', '' );
			if( !empty($perm_redirected) ) $perm_post[] = 'redirected';

			$perm_delpost = param( 'blog_perm_delpost_'.$loop_group_ID, 'integer', 0 );
			$perm_comments = param( 'blog_perm_comments_'.$loop_group_ID, 'integer', 0 );
			$perm_cats = param( 'blog_perm_cats_'.$loop_group_ID, 'integer', 0 );
			$perm_properties = param( 'blog_perm_properties_'.$loop_group_ID, 'integer', 0 );

			$perm_media_upload = param( 'blog_perm_media_upload_'.$loop_group_ID, 'integer', 0 );
			$perm_media_browse = param( 'blog_perm_media_browse_'.$loop_group_ID, 'integer', 0 );
			$perm_media_change = param( 'blog_perm_media_change_'.$loop_group_ID, 'integer', 0 );

			// Update those permissions in DB:

			if( $ismember || count($perm_post) || $perm_delpost || $perm_comments || $perm_cats || $perm_properties
										|| $perm_media_upload || $perm_media_browse || $perm_media_change )
			{ // There are some permissions for this group:
				$ismember = 1;	// Must have this permission

				// insert new perms:
				$inserted_values[] = " ( $blog, $loop_group_ID, $ismember, '".implode(',',$perm_post)."',
																	$perm_delpost, $perm_comments, $perm_cats, $perm_properties,
																	$perm_media_upload, $perm_media_browse, $perm_media_change )";
			}
		}
	}

	// Proceed with insertions:
	if( count( $inserted_values ) )
	{
		$DB->query( "INSERT INTO T_coll_group_perms( bloggroup_blog_ID, bloggroup_group_ID, bloggroup_ismember,
											bloggroup_perm_poststatuses, bloggroup_perm_delpost, bloggroup_perm_comments,
											bloggroup_perm_cats, bloggroup_perm_properties,
											bloggroup_perm_media_upload, bloggroup_perm_media_browse, bloggroup_perm_media_change)
									VALUES ".implode( ',', $inserted_values ) );
	}
}



/**
 * Translates an given array of permissions to an "easy group".
 *
 * USES OBJECT ROW
 *
 * - nomember
 * - member
 * - editor (member+edit posts+delete+edit comments+all filemanager rights)
 * - administrator (editor+edit cats+edit blog)
 * - custom
 *
 * @param array indexed, as the result row from "SELECT * FROM T_coll_user_perms"
 * @return string one of the five groups (nomember, member, editor, admin, custom)
 */
function blogperms_get_easy2( $perms, $context='user' )
{
	if( !isset($perms->{'blog'.$context.'_ismember'}) )
	{
		return 'nomember';
	}

	if( !empty( $perms->{'blog'.$context.'_perm_poststatuses'} ) )
	{
		$perms_post = count( explode( ',', $perms->{'blog'.$context.'_perm_poststatuses'} ) );
	}
	else
	{
		$perms_post = 0;
	}

	$perms_editor =  $perms_post
									+(int)$perms->{'blog'.$context.'_perm_delpost'}
									+(int)$perms->{'blog'.$context.'_perm_comments'}
									+(int)$perms->{'blog'.$context.'_perm_media_upload'}
									+(int)$perms->{'blog'.$context.'_perm_media_browse'}
									+(int)$perms->{'blog'.$context.'_perm_media_change'};

	$perms_admin =   (int)$perms->{'blog'.$context.'_perm_properties'}
									+(int)$perms->{'blog'.$context.'_perm_cats'};

	if( $perms_editor == 11 )
	{ // has full editor rights
		switch( $perms_admin )
		{
			case 0: return 'editor'; break;
			case 1: return 'custom'; break;
			case 2: return 'admin'; break;
		}
	}
	elseif( $perms_editor == 0 )
	{
		if( $perms_admin )
		{
			return 'custom';
		}
		else
		{
			return 'member';
		}
	}
	else
	{
		return 'custom';
	}
}


/**
 *
 * @param string "easy group": 'admin', 'editor', 'member'
 * @return array indexed, as the result row from "SELECT * FROM T_coll_user_perms"
 */
function blogperms_from_easy( $easy_group )
{
	$r = array(
		'bloguser_ismember' => 0,
		'bloguser_perm_poststatuses' => '',
		'bloguser_perm_delpost' => 0,
		'bloguser_perm_comments' => 0,
		'bloguser_perm_media_upload' => 0,
		'bloguser_perm_media_browse' => 0,
		'bloguser_perm_media_change' => 0,
		'bloguser_perm_properties' => 0,
		'bloguser_perm_cats' => 0
	);

	switch( $easy_group )
	{
		case 'admin':
			$r['bloguser_perm_properties'] = 1;
			$r['bloguser_perm_cats'] = 1;

		case 'editor':
			$r['bloguser_perm_poststatuses'] = 'deprecated,draft,private,protected,published,redirected';
			$r['bloguser_perm_delpost'] = 1;
			$r['bloguser_perm_comments'] = 1;
			$r['bloguser_perm_media_upload'] = 1;
			$r['bloguser_perm_media_browse'] = 1;
			$r['bloguser_perm_media_change'] = 1;

		case 'member':
			$r['bloguser_ismember'] = 1;
			break;

		default:
			return false;
	}
	return $r;
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
		$BlogCache = & get_Cache( 'BlogCache' );

		// Get first suitable blog
		$blog_array = $BlogCache->load_user_blogs( $permname, $permlevel, $current_User->ID, 'ID', 1 );
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

	if( $new_blog_ID == $blog )
	{
		return false;
	}

	$blog = $new_blog_ID;

	if( $new_blog_ID != (int)$UserSettings->get('selected_blog') )
	{
		$UserSettings->set( 'selected_blog', $blog );
		$UserSettings->dbupdate();
	}

	return true;
}


/*
 * $Log$
 * Revision 1.25  2007/05/13 18:49:55  fplanque
 * made autoselect_blog() more robust under PHP4
 *
 * Revision 1.24  2007/05/09 00:58:55  fplanque
 * massive cleanup of old functions
 *
 * Revision 1.23  2007/04/26 00:11:05  fplanque
 * (c) 2007
 *
 * Revision 1.22  2007/03/11 22:48:19  fplanque
 * handling of permission to redirect posts
 *
 * Revision 1.21  2007/03/11 22:30:07  fplanque
 * cleaned up group perms
 *
 * Revision 1.20  2007/03/07 02:38:58  fplanque
 * do some recovery on incorrect $blog
 *
 * Revision 1.19  2006/12/28 18:30:30  fplanque
 * cleanup of obsolete var
 *
 * Revision 1.18  2006/12/18 13:14:34  fplanque
 * bugfix
 *
 * Revision 1.17  2006/12/18 03:20:41  fplanque
 * _header will always try to set $Blog.
 * controllers can use valid_blog_requested() to make sure we have one
 * controllers should call set_working_blog() to change $blog, so that it gets memorized in the user settings
 *
 * Revision 1.16  2006/11/24 18:27:23  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.15  2006/11/13 20:49:52  fplanque
 * doc/cleanup :/
 *
 * Revision 1.14  2006/10/08 03:52:09  blueyed
 * Tell BlogCache that it has loaded all.
 */
?>