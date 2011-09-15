<?php
/**
 * Name : Abani Mohan Patra.
 * This is the admin config file
 *
 * This sets how the back-office/admin interface works
 *
 * @package conf
 */
if( !defined('EVO_CONFIG_LOADED') ) die( 'Please, do not access this page directly.' );


/**
 * Reserved post type IDs.
 *
 * These post types are reserved for future use and can't be edited or
 * deleted in the post type editor. It also is not possible to select
 * them when creating a new post.
 * Do not remove any IDs from this array.
 *
 * @todo fp>get rid of this and just delete 4000 &  500 from the database during upgrade
 *
 * @global array $posttypes_reserved_IDs
 */
$posttypes_reserved_IDs = array( 4000, 5000 );

/**
 * Post types that can be restricted on a per-blog basis using the blog
 * user/group permissions.
 *
 * The key of each array element is the part of the corresponding permission
 * name without the "blog_" prefix (e. g. a value of 'page' means the
 * permission 'blog_page').
 * The value of each array element is an array containing the post type
 * IDs whose usage is controlled by the respective permission.
 *
 * @see check_perm_posttype()
 * @see ItemTypeCache::get_option_list_usable_only()
 *
 * @global array $posttypes_perms
 */
$posttypes_perms = array(
	'page' => array(
		1000,
	),
	'intro' => array(
		1500,
		1520,
		1530,
		1570,
		1600,
	),
	'podcast' => array(
		2000,
	),
	'sidebar' => array(
		3000,
	),
);

?>
