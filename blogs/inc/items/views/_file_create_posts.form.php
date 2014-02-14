<?php
/**
 * This file implements the UI for make posts from images in file upload.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2013 by Francois Planque - {@link http://fplanque.com/}
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
 * EVO FACTORY grants Francois PLANQUE the right to license
 * EVO FACTORY contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 *
 * @version $Id$
 */

if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * @var Settings
 */

load_class( 'items/model/_item.class.php', 'Item' );
load_class( 'files/model/_filelist.class.php', 'FileList' );

global $post_extracats , $fm_FileRoot , $edited_Item;
$edited_Item= new Item();

$Form = new Form( NULL, 'pre_post_publish' );

$Form->begin_form( 'fform', T_('Posts preview') );
$Form->hidden_ctrl();

$images_list = param( 'fm_selected', 'array/string' );
foreach( $images_list as $key => $item )
{
	$Form->hidden( 'fm_selected['.$key.']', $item );
}
// fp> TODO: cleanup all this crap:
$Form->hidden( 'confirmed', get_param( 'confirmed' ) );
$Form->hidden( 'md5_filelist', get_param( 'md5_filelist' ) );
$Form->hidden( 'md5_cwd', get_param( 'md5_cwd' ) );
$Form->hidden( 'locale', get_param( 'locale' ) );
$Form->hidden( 'blog', get_param( 'blog' ) );
$Form->hidden( 'mode', get_param( 'mode' ) );
$Form->hidden( 'root', get_param( 'root' ) );
$Form->hidden( 'path', get_param( 'path' ) );
$Form->hidden( 'fm_mode', get_param( 'fm_mode' ) );
$Form->hidden( 'linkctrl', get_param( 'linkctrl' ) );
$Form->hidden( 'linkdata', get_param( 'linkdata' ) );
$Form->hidden( 'iframe_name', get_param( 'iframe_name' ) );
$Form->hidden( 'fm_filter', get_param( 'fm_filter' ) );
$Form->hidden( 'fm_filter_regex', get_param( 'fm_filter_regex' ) );
$Form->hidden( 'iframe_name', get_param( 'iframe_name' ) );
$Form->hidden( 'fm_flatmode', get_param( 'fm_flatmode' ) );
$Form->hidden( 'fm_order', get_param( 'fm_order' ) );
$Form->hidden( 'fm_orderasc', get_param( 'fm_orderasc' ) );
$Form->hidden( 'crumb_file', get_param( 'crumb_file' ) );

$post_extracats = array();
$post_counter = 0;


/**
 * Get the categories list
 *
 * @param integer Parent category ID
 * @param integer Level
 * @return array Categories
 */
function fcpf_categories_select( $parent_category_ID = -1, $level = 0 )
{
	global $blog, $DB;
	$result_Array = array();

	$SQL = new SQL();
	$SQL->SELECT( 'cat_ID, cat_name' );
	$SQL->FROM( 'T_categories' );
	$SQL->WHERE( 'cat_blog_ID = '.$DB->quote( $blog ) );
	if( $parent_category_ID == -1 )
	{
		$SQL->WHERE_and( 'cat_parent_ID IS NULL' );
	}
	else
	{
		$SQL->WHERE( 'cat_parent_ID = '.$DB->quote( $parent_category_ID ) );
	}
	$SQL->ORDER_BY( 'cat_name' );
	$categories = $DB->get_results( $SQL->get() );

	if( ! empty( $categories ) )
	{
		foreach( $categories as $category )
		{
			$result_Array[] = array(
					'value' => $category->cat_ID,
					'label' => str_repeat( '&nbsp;&nbsp;&nbsp;', $level ).$category->cat_name
				);

			$child_Categories_opts = fcpf_categories_select( $category->cat_ID, $level + 1 );
			if( $child_Categories_opts != '' )
			{
				foreach( $child_Categories_opts as $cat )
				{
					$result_Array[] = $cat;
				}
			}
		}
	}
	return $result_Array;
}

$FileCache = & get_FileCache();

// Get the categories
$categories = fcpf_categories_select();

foreach( $images_list as $item )
{
	$File = & $FileCache->get_by_root_and_path( $fm_FileRoot->type,  $fm_FileRoot->in_type_ID, urldecode( $item ), true );
	$title = $File->get( 'title' );
	if( empty( $title ) )
	{
		$title = basename( urldecode( $File->get( 'name' ) ) );
	}
	$Form->begin_fieldset( T_('Post #').( $post_counter + 1 ).get_manual_link( 'creating-posts-from-files' ) );
	$Form->text_input( 'post_title['.$post_counter.']', $title, 40, T_('Post title') );

	if( $post_counter != 0 )
	{ // The posts after first
		if( $post_counter == 1 )
		{ // Add new option to select a category from previous post
			$categories = array_merge(
				array(
					array(
						'value' => 'same',
						'label' => T_('Same as above').'<br />',
					)
				), $categories );
		}
		// Use the same category for all others after first
		$selected_category_ID = 'same';
	}
	else
	{ // First post, Use a default category as selected on load form
		global $Blog;
		$selected_category_ID = isset( $Blog ) ? $Blog->get_default_cat_ID() : 1;
	}

	$Form->radio_input( 'category['.$post_counter.']', $selected_category_ID, $categories, T_('Category'), array( 'suffix' => '<br />' ) );
	$Form->info( T_('Post content'), '<img src="'.$fm_FileRoot->ads_url.urldecode( $item ).'" width="200" />' );

	$Form->end_fieldset();

	$post_counter++;
}
$edited_Item = NULL;

$Form->end_form( array( array( 'submit', 'actionArray[make_posts_from_files]', T_('Make posts'), 'ActionButton') ) );

?>