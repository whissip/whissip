<?php
/**
 * This file implements the UI controller for additional tools.
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @package admin
 * @author blueyed: Daniel HAHLER
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


load_funcs('plugins/_plugin.funcs.php');


param( 'tab', 'string', '', true );

$tab_Plugin = NULL;
$tab_plugin_ID = false;

if( ! empty($tab) )
{	// We have requested a tab which is handled by a plugin:
	if( preg_match( '~^plug_ID_(\d+)$~', $tab, $match ) )
	{ // Instanciate the invoked plugin:
		$tab_plugin_ID = $match[1];
		$tab_Plugin = & $Plugins->get_by_ID( $match[1] );
		if( ! $tab_Plugin )
		{ // Plugin does not exist
			$Messages->add( sprintf( T_( 'The plugin with ID %d could not get instantiated.' ), $tab_plugin_ID ), 'error' );
			$tab_plugin_ID = false;
			$tab_Plugin = false;
			$tab = '';
		}
		else
		{
			$Plugins->call_method_if_active( $tab_plugin_ID, 'AdminTabAction', $params = array() );
		}
	}
	else
	{
		$tab = '';
		$Messages->add( 'Invalid sub-menu!' ); // Should need no translation, prevented by GUI
	}
}

// Highlight the requested tab (if valid):
$AdminUI->set_path( 'tools', $tab );


if( empty($tab) )
{	// "Main tab" actions:
	if( param( 'action', 'string', '' ) )
	{
		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'tools' );
	
		// fp> TODO: have an option to only PRUNE files older than for example 30 days
		$current_User->check_perm('options', 'edit', true);
	}

	switch( $action )
	{
		case 'del_itemprecache':
			$DB->query('DELETE FROM T_items__prerendering WHERE 1=1');

			$Messages->add( sprintf( T_('Removed %d cached entries.'), $DB->rows_affected ), 'success' );
			break;

		case 'del_pagecache':
			// Delete the page cache /blogs/cache
			global $cache_path;

			// Clear general cache directory
			if( cleardir_r( $cache_path.'general' ) )
			{
				$Messages->add( sprintf( T_('General cache deleted: %s'), $cache_path.'general' ), 'note' );
			}
			else
			{
				$Messages->add( sprintf( T_('Could not delete general cache: %s'), $cache_path.'general' ), 'error' );
			}

			$SQL = 'SELECT blog_ID FROM T_blogs
					INNER JOIN T_coll_settings ON ( blog_ID = cset_coll_ID
								AND cset_name = "cache_enabled"
								AND cset_value = "1" )
					WHERE 1=1';

			if( $blog_array = $DB->get_col( $SQL ) )
			{
				foreach( $blog_array as $l_blog )
				{	// Clear blog cache
					if( cleardir_r( $cache_path.'c'.$l_blog ) )
					{
						$Messages->add( sprintf( T_('Blog %d cache deleted: %s'), $l_blog, $cache_path.'c'.$l_blog ), 'note' );
					}
					else
					{
						$Messages->add( sprintf( T_('Could not delete blog %d cache: %s'), $l_blog, $cache_path.'c'.$l_blog ), 'error' );
					}
				}
			}

			$Messages->add( T_('Page cache deleted.'), 'success' );
			break;

		case 'del_filecache':
			// delete the thumbnail cahces .evocache
			// TODO> handle custom media directories dh> ??
			// Delete any ?evocache folders:
			$deleted_dirs = delete_cachefolders($Messages);
			$Messages->add( sprintf( T_('Deleted %d directories.'), $deleted_dirs ), 'success' );
			break;

		case 'optimize_tables':
			// Optimize MyISAM tables
			global $tableprefix;

			$db_optimized = false;
			$tables = $DB->get_results( 'SHOW TABLE STATUS FROM `'.$DB->dbname.'` LIKE \''.$tableprefix.'%\'');

			foreach( $tables as $table )
			{
				// Before MySQL 4.1.2, the "Engine" field was labeled as "Type".
				if( ( ( isset( $table->Engine ) && $table->Engine == 'MyISAM' )
					  || ( isset( $table->Type ) && $table->Type == 'MyISAM' ) )
					&& $table->Data_free )
				{	// Optimization needed
					if( !$DB->query( 'OPTIMIZE TABLE '.$table->Name ) )
					{
						$Messages->add( sprintf( T_('Database table %s could not be optimized.'), '<b>'.$table->Name.'</b>' ), 'note' );
					}
					else
					{
						$db_optimized = true;
						$Messages->add( sprintf( T_('Database table %s optimized.'), '<b>'.$table->Name.'</b>' ), 'success' );
					}
				}
			}

			if( !$db_optimized )
			{
				$Messages->add( T_('Database tables are already optimized.'), 'success' );
			}
			break;

		case 'del_broken_posts':
			// Check that this action request is not a CSRF hacked request:
			$Session->assert_received_crumb( 'tools' );

			$current_User->check_perm('options', 'edit', true);

			// load item class
			load_class( 'items/model/_item.class.php', 'Item' );

			// select broken items
			$sql = 'SELECT * FROM T_items__item
						WHERE post_canonical_slug_ID NOT IN (
							SELECT slug_ID FROM T_slug )';
			$broken_items = $DB->get_results( $sql, OBJECT, 'Find broken posts' );
			$num_deleted = 0;
			foreach( $broken_items as $row )
			{ // delete broken items
				$broken_Item = new Item( $row );
				if( $broken_Item->dbdelete() )
				{
					$num_deleted++;
				}
			}

			$Messages->add( sprintf( T_('Deleted %d posts.'), $num_deleted ), 'success' );
			break;

		case 'del_broken_slugs':
			// Check that this action request is not a CSRF hacked request:
			$Session->assert_received_crumb( 'tools' );

			$current_User->check_perm('options', 'edit', true);

			// delete broken slugs
			$r = $DB->query( 'DELETE FROM T_slug
								WHERE slug_type = "item" and slug_itm_ID NOT IN (
									SELECT post_ID FROM T_items__item )' );

			if( $r !== false )
			{
				$Messages->add( sprintf( T_('Deleted %d slugs.'), $r ), 'success' );
			}
			break;

		case 'create_sample_comments':
			// Check that this action request is not a CSRF hacked request:
			$Session->assert_received_crumb( 'tools' );

			$current_User->check_perm('options', 'edit', true);

			$blog_ID = param( 'blog_ID', 'string', 0 );
			$num_comments = param( 'num_comments', 'string', 0 );
			$num_posts = param( 'num_posts', 'string', 0 );

			if ( ! ( param_check_number( 'blog_ID', T_('Blog ID must be a number'), true ) &&
				param_check_number( 'num_comments', T_('Comments per post must be a number'), true ) &&
				param_check_number( 'num_posts', T_('"How many posts" field must be a number'), true ) ) )
			{ // param errors
				$action = 'show_create_comments';
				break;
			}

			// check blog_ID
			$BlogCache = & get_BlogCache();
			if( $BlogCache->get_by_ID( $blog_ID, false, false ) == NULL )
			{
				$Messages->add( T_( 'Blog ID must be a valid Blog ID!' ), 'error' );
				$action = 'show_create_comments';
				break;
			}

			// find the $num_posts latest posts in blog
			$sql = 'SELECT post_ID 
						FROM T_items__item INNER JOIN T_categories ON post_main_cat_ID = cat_ID
					 WHERE cat_blog_ID = '.$blog_ID.' AND post_status = '.$DB->quote( 'published' ).'
					 ORDER BY post_datecreated DESC
					 LIMIT '.$num_posts;
			$items_result = $DB->get_results( $sql, ARRAY_A, 'Find the x latest posts in blog' );

			$count = 1;
			$fix_content = 'This is an auto generated comment for testing the moderation features.
							http://www.test.com/test_comment_';
			// go through on selected items
			foreach( $items_result as $row )
			{
				$item_ID = $row['post_ID'];
				// create $num_comments comments for each item
				for( $i = 0; $i < $num_comments; $i++ )
				{
					$author = 'Test '.$count;
					$email = 'test_'.$count.'@test.com';
					$url = 'http://www.test.com/test_comment_'.$count;

					$content = $fix_content.$count;
					for( $j = 0; $j < 50; $j++ )
					{ // create 50 random word
						$length = rand(1, 15);
						$word = generate_random_key( $length, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' );
						$content = $content.' '.$word;
					}

					// create and save a new comment
					$Comment = new Comment();
					$Comment->set( 'post_ID', $item_ID );
					$Comment->set( 'status', 'draft' );
					$Comment->set( 'author', $author );
					$Comment->set( 'author_email', $email );
					$Comment->set( 'author_url', $url );
					$Comment->set( 'content', $content );
					$Comment->set( 'date', date( 'Y-m-d H:i:s', $localtimenow ) );
					$Comment->set( 'author_IP', $Hit->IP );
					$Comment->dbsave();
					$count++;
				}
			}

			$Messages->add( sprintf( T_('Created %d comments.'), $count - 1 ), 'success' );
			break;


		case 'del_rscbundlecache':
			// TODO: dh> this should really be a separate permission.. ("tools", "exec") or similar!
			$current_User->check_perm('options', 'edit', true);

			$ResourceBundles->cleanup_cache_dir();

			$Messages->add( sprintf( T_('Removed cached resource bundles.') ), 'success' );
			break;


		case 'create_tagsfromcats':
			$count_tags = 0;

			$cats = $DB->get_results('
				SELECT cat_ID, cat_name
				  FROM T_categories');
			foreach( $cats as $cat )
			{
				// Is there a tag for this category name already?
				$tag_ID = $DB->get_var('
					SELECT tag_ID
					  FROM T_items__tag
					 WHERE tag_name = '.$DB->quote($cat->cat_name));
				// Create tag, if new:
				if( ! $tag_ID )
				{
					$DB->query('
						INSERT INTO T_items__tag ( tag_name )
						VALUES ('.$DB->quote($cat->cat_name).')');
					$tag_ID = $DB->insert_id;
					$count_tags++;
				}

				// Get posts in this cat:
				$post_IDs = $DB->get_col('
					SELECT postcat_post_ID
					  FROM T_postcats
					 WHERE postcat_cat_ID = '.$cat->cat_ID );

				if( $post_IDs )
				{
					// Link posts to tag:
					$query = "
						REPLACE INTO T_items__itemtag ( itag_itm_ID, itag_tag_ID )
						VALUES \n";
					foreach( $post_IDs as $post_ID )
					{
						$query .= "($post_ID, $tag_ID),\n";
					}
					$query = substr($query, 0, -2);

					$DB->query( $query );
				}
			}

			$Messages->add( sprintf('Created %d tags from %d categories.', $count_tags, count($cats)), 'success' );
			break;


		case 'recreate_itemslugs':
			$ItemCache = get_ItemCache();
			$SlugCache = get_SlugCache();
			$ItemCache->load_all();
			$SlugCache->load_all();
			$items = $ItemCache->get_ID_array();
			$count_slugs = 0;
			@set_time_limit(0);
			foreach( $items as $item_ID )
			{
				$Item = $ItemCache->get_by_ID($item_ID);

				$item_title = $Item->get( 'title' );
				if( ! strlen($item_title) ) {
					continue;
				}
				$prev_urltitle = $Item->get( 'urltitle' );

				$DB->begin();
				if( $Item->update_slug('') || $prev_urltitle != $Item->get('urltitle') /* might happen when urltitle is different from associated slug (e.g. manual table update) */ )
				{
					$result = $Item->dbupdate(/* do not autotrack modification */ false, /* update slug */ false, /* do not update excerpt */ false); 
					if( ( $result ) && ( $prev_urltitle != $Item->get( 'urltitle' ) ) )
					{ // update was successful, and item urltitle was changed
						$count_slugs++;
						$DB->commit();
						continue;
					}
				}
				$DB->rollback();
			}
			$Messages->add( sprintf('Created %d new URL slugs for %d total posts.', $count_slugs, count($items)), 'success' );
			break;

		case 'del_obsolete_tags':
			$DB->query('
				DELETE T_items__tag FROM T_items__tag
				  LEFT JOIN T_items__itemtag ON tag_ID = itag_tag_ID
				 WHERE itag_itm_ID IS NULL');
			$Messages->add( sprintf(T_('Removed %d obsolete tag entries.'), $DB->rows_affected), 'success' );
			break;

		case 'view_phpinfo':
			// Display PHP info and exit
			headers_content_mightcache('text/html');
			phpinfo();
			exit();
			break;
	}
}

$AdminUI->breadcrumbpath_init( false );  // fp> I'm playing with the idea of keeping the current blog in the path here...
$AdminUI->breadcrumbpath_add( T_('Tools'), '?ctrl=crontab' );
$AdminUI->breadcrumbpath_add( T_('Miscellaneous'), '?ctrl=tools' );


// Display <html><head>...</head> section! (Note: should be done early if actions do not redirect)
$AdminUI->disp_html_head();

// Display title, menu, messages, etc. (Note: messages MUST be displayed AFTER the actions)
$AdminUI->disp_body_top();

// Begin payload block:
$AdminUI->disp_payload_begin();


if( empty($tab) )
{
	switch( $action )
	{
		case 'find_broken_posts':
			$AdminUI->disp_view( 'tools/views/_broken_posts.view.php' );
			break;

		case 'find_broken_slugs':
			$AdminUI->disp_view( 'tools/views/_broken_slugs.view.php' );
			break;

		case 'show_create_comments':
			$AdminUI->disp_view( 'tools/views/_create_comments.form.php' );
			break;

		default:
			$AdminUI->disp_view( 'tools/views/_misc_tools.view.php' );
			break;
	}
}
elseif( $tab_Plugin )
{ // Plugin tab

	// Icons:
	?>

	<div class="right_icons">

	<?php
	echo $tab_Plugin->get_edit_settings_link()
		.' '.$tab_Plugin->get_help_link('$help_url')
		.' '.$tab_Plugin->get_help_link('$readme');
	?>

	</div>

	<?php
	$Plugins->call_method_if_active( $tab_plugin_ID, 'AdminTabPayload', $params = array() );
}


// End payload block:
$AdminUI->disp_payload_end();

// Display body bottom, debug info and close </html>:
$AdminUI->disp_global_footer();

/*
 * $Log$
 * Revision 1.35  2010/11/12 15:13:31  efy-asimo
 * MFB:
 * Tool 1: "Find all broken posts that have no matching category"
 * Tool 2: "Find all broken slugs that have no matching target post"
 * Tool 3: "Create sample comments for testing moderation"
 *
 * Revision 1.34  2010/11/04 03:16:10  sam2kb
 * Display PHP info in a pop-up window
 *
 * Revision 1.33  2010/07/28 07:58:53  efy-asimo
 * Add where condition to recreate slugs tool query
 *
 * Revision 1.32  2010/07/26 07:24:27  efy-asimo
 * Tools recreate item slugs (change description + fix notice)
 *
 * Revision 1.31  2010/07/26 06:52:27  efy-asimo
 * MFB v-4-0
 *
 * Revision 1.30  2010/06/15 21:33:24  blueyed
 * Fix patch failure.
 *
 * Revision 1.29  2010/06/15 21:20:37  blueyed
 * Add tools action to remove obsolete/unused tags.
 *
 * Revision 1.28  2010/05/24 21:27:58  sam2kb
 * Fixed some translated strings
 *
 * Revision 1.27  2010/05/02 00:15:07  blueyed
 * cleanup
 *
 * Revision 1.26  2010/05/02 00:14:07  blueyed
 * Add recreate_itemslugs tool to re-generate slugs for all items.
 *
 * Revision 1.25  2010/03/27 19:57:30  blueyed
 * Add delete_cachefolders function and use it in the Tools Misc actions and with the watermark plugin. The latter will also remove caches when it gets enabled or disabled.
 *
 * Revision 1.24  2010/03/12 10:52:56  efy-asimo
 * Set EvoCache  folder names - task
 *
 * Revision 1.23  2010/02/08 17:54:47  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.22  2010/01/14 21:30:31  blueyed
 * Make deleting .evocache folders far less verbose.
 *
 * Revision 1.21  2010/01/03 18:07:37  fplanque
 * crumbs
 *
 * Revision 1.20  2009/12/06 22:55:20  fplanque
 * Started breadcrumbs feature in admin.
 * Work in progress. Help welcome ;)
 * Also move file settings to Files tab and made FM always enabled
 *
 * Revision 1.19  2009/11/25 19:53:58  blueyed
 * Fix 'Optimize database tables' SQL: quote DB name.
 *
 * Revision 1.18  2009/11/25 00:54:26  blueyed
 * todo
 *
 * Revision 1.17  2009/11/12 03:54:17  fplanque
 * wording/doc/cleanup
 *
 * Revision 1.16  2009/10/16 18:41:47  tblue246
 * Cleanup/doc
 *
 * Revision 1.15  2009/10/02 14:17:34  tblue246
 * minor/doc
 *
 * Revision 1.13  2009/10/02 13:28:03  sam2kb
 * Backup b2evo database from Tools > Misc
 *
 * Revision 1.12  2009/10/01 16:19:14  sam2kb
 * minor
 *
 * Revision 1.11  2009/10/01 14:58:44  sam2kb
 * Delete page and thumbnails cache
 *
 * Revision 1.10  2009/10/01 13:06:03  tblue246
 * Fix for backward compatibility with MySQL versions lower than 4.1.2.
 *
 * Revision 1.9  2009/10/01 12:57:18  tblue246
 * Tools -> Optimize DB: Drop substr() check for table prefix and modify the SQL query to only return appropriate tables instead.
 *
 * Revision 1.8  2009/09/30 19:48:38  tblue246
 * Tools -> Optimize tables: Do not use preg_match() to check table prefix but a simple substr().
 *
 * Revision 1.7  2009/09/30 18:00:19  sam2kb
 * Optimize b2evo tables from Tools > Misc
 *
 * Revision 1.6  2009/03/08 23:57:46  fplanque
 * 2009
 *
 * Revision 1.5  2008/07/11 23:10:01  blueyed
 * s/insctructions/instructions/g
 *
 * Revision 1.4  2008/01/21 09:35:35  fplanque
 * (c) 2008
 *
 * Revision 1.3  2007/10/09 01:18:12  fplanque
 * Hari's WordPress importer
 *
 * Revision 1.2  2007/09/04 14:57:07  fplanque
 * interface cleanup
 *
 * Revision 1.1  2007/06/25 11:01:42  fplanque
 * MODULES (refactored MVC)
 *
 */
?>
