<?php
/**
 * This file implements upgrading of DB tables
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2013 by Francois Planque - {@link http://fplanque.com/}
 *
 * {@internal Open Source relicensing agreement:
 * Daniel HAHLER grants Francois PLANQUE the right to license
 * Daniel HAHLER's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package install
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_funcs('_core/_param.funcs.php');


/**
 * Create a DB version checkpoint
 *
 * This is useful when the next operation might timeout or fail!
 * The checkpoint will allow to restart the script and continue where it stopped
 *
 * @param string version of DB at checkpoint
 */
function set_upgrade_checkpoint( $version )
{
	global $DB, $script_start_time, $locale;

	echo "Creating DB schema version checkpoint at $version... ";

	if( $version < 8060 )
	{
		$query = 'UPDATE T_settings SET db_version = '.$version;
	}
	else
	{
		$query = "UPDATE T_settings
								SET set_value = '$version'
								WHERE set_name = 'db_version'";
	}
	$DB->query( $query );


	$elapsed_time = time() - $script_start_time;

	echo "OK. (Elapsed upgrade time: $elapsed_time seconds)<br />\n";
	flush();

	$max_exe_time = ini_get( 'max_execution_time' );
	if( $max_exe_time && ( $elapsed_time > ( $max_exe_time - 20 ) ) )
	{ // Max exe time not disabled and we're recahing the end
		echo 'We are reaching the time limit for this script. Please click <a href="index.php?locale='.$locale.'&amp;action=evoupgrade">continue</a>...';
		// Dirty temporary solution:
		exit(0);
	}
}


/**
 * @return boolean Does a given index key name exist in DB?
 */
function db_index_exists( $table, $index_name )
{
	global $DB;

	$index_name = strtolower($index_name);

	$DB->query('SHOW INDEX FROM '.$table);
	while( $row = $DB->get_row() )
	{
		if( strtolower($row->Key_name) == $index_name )
		{
			return true;
		}
	}

	return false;
}


/**
 * @param string Table name
 * @param array Column names
 * @return boolean Does a list of given column names exist in DB?
 */
function db_cols_exist( $table, $col_names )
{
	global $DB;

	foreach( $col_names as $k => $v )
		$col_names[$k] = strtolower($v);

	foreach( $DB->get_results('SHOW COLUMNS FROM '.$table) as $row )
		if( ($key = array_search(strtolower($row->Field), $col_names)) !== false )
			unset( $col_names[$key] );

	return count($col_names) == 0;
}

/**
 * Drops a column, if it exists.
 */
function db_drop_col( $table, $col_name )
{
	global $DB;

	if( ! db_col_exists($table, $col_name) )
		return false;

	$DB->query( 'ALTER TABLE '.$table.' DROP COLUMN '.$col_name );
}

/**
 * Add a column, if it does not already exist.
 * If it exists already, a "ALTER TABLE" statement will get executed instead.
 *
 * @return boolean True if the column has been added, False if not.
 */
function db_add_col( $table, $col_name, $col_desc )
{
	global $DB;

	if( db_col_exists($table, $col_name) )
	{ // Column exists already, make sure it's the same.
		$DB->query( 'ALTER TABLE '.$table.' MODIFY COLUMN '.$col_name.' '.$col_desc );
		return false;
	}

	$DB->query( 'ALTER TABLE '.$table.' ADD COLUMN '.$col_name.' '.$col_desc );
}


/**
 * Add an INDEX. If another index with the same name already exists, it will
 * get dropped before.
 */
function db_add_index( $table, $name, $def, $type = 'INDEX' )
{
	global $DB;
	if( db_index_exists($table, $name) )
	{
		$DB->query( 'ALTER TABLE '.$table.' DROP INDEX '.$name );
	}
	$DB->query( 'ALTER TABLE '.$table.' ADD '.$type.' '.$name.' ('.$def.')' );
}


/**
 * Check if a key item value already exists on database
 */
function db_key_exists( $table, $field_name, $field_value )
{
	global $DB;
	return $DB->get_var( '
		SELECT COUNT('.$field_name.')
		FROM '.$table.'
		WHERE '.$field_name.' = '.$field_value );
}

/**
 * Add a Foreign Key constraint.
 * If another foreign key exists between these two fields then the old FK will be deleted and a new will be created.
 * If the table engine is not InnoDB, then it will be changed automatically.
 * If the FK table contains data which would prevent foreign key creation then these records will be deleted.
 *
 * @param string foreign key table
 * @param string foreing key column name
 * @param string foreign key refrence table name
 * @param string reference column name in the reference table
 * @param string foreign key definition ( e.g. "ON DELETE CASCADE" or "ON UPDATE RESTRICT" )
 */
function db_add_foreign_key( $table, $field_name, $reference_table, $reference_field_name, $definition )
{
	global $DB;
	$table = preg_replace( $DB->dbaliases, $DB->dbreplaces, $table );
	$reference_table = preg_replace( $DB->dbaliases, $DB->dbreplaces, $reference_table );
	$foreign_key_fields = array( array(
			'fk_fields' => $field_name,
			'reference_table' => $reference_table,
			'reference_columns' => $reference_field_name,
			'fk_definition' => $definition,
			'create' => true, // This FK should be created if not exists
		) );
	db_delta_foreign_keys( $foreign_key_fields, $table, true, 'add' );
}

/**
 * Drop a Foreign Key constraint.
 * If this foreign key not exists the function won't give error.
 *
 * @param string foreign key table
 * @param string foreing key column name
 * @param string foreign key refrence table name
 * @param string reference column name in the reference table
 */
function db_drop_foreign_key( $table, $field_name, $reference_table, $reference_field_name )
{
	global $DB;
	$table = preg_replace( $DB->dbaliases, $DB->dbreplaces, $table );
	$reference_table = preg_replace( $DB->dbaliases, $DB->dbreplaces, $reference_table );
	$foreign_key_fields = array( array(
			'fk_fields' => $field_name,
			'reference_table' => $reference_table,
			'reference_columns' => $reference_field_name,
			'fk_definition' => '',
			'create' => false, // This FK shouldn't be created if not exists
		) );
	db_delta_foreign_keys( $foreign_key_fields, $table, true, 'drop' );
}

/**
 * Converts languages in a given table into according locales
 *
 * @param string name of the table
 * @param string name of the column where lang is stored
 * @param string name of the table's ID column
 */
function convert_lang_to_locale( $table, $columnlang, $columnID )
{
	global $DB, $locales, $default_locale;

	if( !preg_match('/[a-z]{2}-[A-Z]{2}(-.{1,14})?/', $default_locale) )
	{ // we want a valid locale
		$default_locale = 'en-EU';
	}

	echo 'Converting langs to locales for '. $table. '...<br />';

	// query given languages in $table
	$query = "SELECT $columnID, $columnlang FROM $table";
	$languagestoconvert = array();
	foreach( $DB->get_results( $query, ARRAY_A ) as $row )
	{
		// remember the ID for that locale
		$languagestoconvert[ $row[ $columnlang ] ][] = $row[ $columnID ];
	}

	foreach( $languagestoconvert as $lkey => $lIDs)
	{ // converting the languages we've found
		$converted = false;
		echo '&nbsp; Converting lang \''. $lkey. '\' '; // (with IDs: '. implode( ', ', $lIDs ). ').. ';

		if( preg_match('/[a-z]{2}-[A-Z]{2}(-.{1,14})?/', $lkey) )
		{ // Already valid
			echo 'nothing to update, already valid!<br />';
			continue;
		}

		if( (strlen($lkey) == 2) && ( substr( $default_locale, 0, 2 ) != $lkey ) )
		{ // we have an old two letter lang code to convert
			// and it doesn't match the default locale
			foreach( $locales as $newlkey => $v )
			{  // loop given locales
				if( substr($newlkey, 0, 2) == strtolower($lkey) ) # TODO: check if valid/suitable
				{  // if language matches, update
					$converted = $DB->query( "
						UPDATE $table
						   SET $columnlang = '$newlkey'
						 WHERE $columnlang = '$lkey'" );
					echo 'to locale \''. $newlkey. '\'<br />';
					break;
				}
			}
		}

		if( !$converted )
		{ // we have nothing converted yet, setting default:
			$DB->query( "UPDATE $table
											SET $columnlang = '$default_locale'
										WHERE $columnlang = '$lkey'" );
			echo 'forced to default locale \''. $default_locale. '\'<br />';
		}
	}
	echo "\n";
}  // convert_lang_to_locale(-)


/**
 * upgrade_b2evo_tables(-)
 */
function upgrade_b2evo_tables()
{
	global $db_config, $tableprefix;
	global $baseurl, $old_db_version, $new_db_version;
	global $Group_Admins, $Group_Privileged, $Group_Bloggers, $Group_Users;
	global $locales, $locale;
	global $DB;
	global $admin_url;
	global $Settings, $Plugins;

	// used for defaults, when upgrading to 1.6
	global $use_fileupload, $fileupload_allowedtypes, $fileupload_maxk, $doubleCheckReferers;

	// new DB-delta functionality
	global $schema_queries, $inc_path;

	// used to check script time before starting to create db delta
	global $script_start_time;

	// Load DB schema from modules
	load_db_schema();

	load_funcs('_core/model/db/_upgrade.funcs.php');


	echo '<p>'.T_('Checking DB schema version...').' ';
	$old_db_version = get_db_version();

	if( empty($old_db_version) )
	{
		echo '<p><strong>OOPS! b2evolution doesn\'t seem to be installed yet.</strong></p>';
		return;
	}

	echo $old_db_version, ' : ';

	if( $old_db_version < 8000 ) debug_die( T_('This version is too old!') );
	if( $old_db_version > $new_db_version ) debug_die( T_('This version is too recent! We cannot downgrade to the version you are trying to install...') );
	echo "OK.<br />\n";


	// Try to obtain some serious time to do some serious processing (5 minutes)
	set_max_execution_time(300);



	if( $old_db_version < 8010 )
	{
		echo 'Upgrading users table... ';
		$query = "ALTER TABLE T_users
							MODIFY COLUMN user_pass CHAR(32) NOT NULL";
		$DB->query( $query );
		echo "OK.<br />\n";

		echo 'Upgrading blogs table... ';
		$query = "ALTER TABLE T_blogs
							MODIFY COLUMN blog_lang VARCHAR(20) NOT NULL DEFAULT 'en_US',
							MODIFY COLUMN blog_longdesc TEXT NULL DEFAULT NULL";
		$DB->query( $query );
		echo "OK.<br />\n";

		echo 'Upgrading categories table... ';
		$query = "ALTER TABLE T_categories
							ADD COLUMN cat_description VARCHAR(250) NULL DEFAULT NULL,
							ADD COLUMN cat_longdesc TEXT NULL DEFAULT NULL,
							ADD COLUMN cat_icon VARCHAR(30) NULL DEFAULT NULL";
		$DB->query( $query );
		echo "OK.<br />\n";

		echo 'Upgrading posts table... ';
		$query = "ALTER TABLE {$tableprefix}posts
							MODIFY COLUMN post_lang VARCHAR(20) NOT NULL DEFAULT 'en_US',
							ADD COLUMN post_urltitle VARCHAR(50) NULL DEFAULT NULL AFTER post_title,
							ADD COLUMN post_url VARCHAR(250) NULL DEFAULT NULL AFTER post_urltitle,
							ADD COLUMN post_comments ENUM('disabled', 'open', 'closed') NOT NULL DEFAULT 'open' AFTER post_wordcount";
		$DB->query( $query );
		echo "OK.<br />\n";

		echo 'Generating wordcounts... ';
		load_funcs('items/model/_item.funcs.php');
		$query = "SELECT ID, post_content FROM {$tableprefix}posts WHERE post_wordcount IS NULL";
		$i = 0;
		foreach( $DB->get_results( $query, ARRAY_A ) as $row )
		{
			$query_update_wordcount = "UPDATE {$tableprefix}posts
																SET post_wordcount = " . bpost_count_words($row['post_content']) . "
																WHERE ID = " . $row['ID'];
			$DB->query($query_update_wordcount);
			$i++;
		}
		echo "OK. ($i rows updated)<br />\n";

		set_upgrade_checkpoint( '8010' );
	}


	if( $old_db_version < 8020 )
	{
		echo 'Encoding passwords... ';
		$query = "UPDATE T_users
							SET user_pass = MD5(user_pass)";
		$DB->query( $query );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '8020' );
	}


	if( $old_db_version < 8030 )
	{
		echo 'Deleting unecessary logs... ';
		$query = "DELETE FROM T_hitlog
							WHERE hit_ignore = 'badchar'";
		$DB->query( $query );
		echo "OK.<br />\n";

		echo 'Updating blog urls... ';
		$query = "SELECT blog_ID, blog_siteurl FROM T_blogs";
		$i = 0;
		foreach( $DB->get_results( $query, ARRAY_A ) as $row )
		{
			$blog_ID = $row['blog_ID'];
			$blog_siteurl = $row['blog_siteurl'];
			// echo $blog_ID.':'.$blog_siteurl;
			if( strpos( $blog_siteurl.'/', $baseurl ) !== 0 )
			{ // If not found at position 0
				echo ' <strong>WARNING: please check blog #', $blog_ID, ' manually.</strong><br /> ';
				continue;
			}
			// crop off the baseurl:
			$blog_siteurl = evo_substr( $blog_siteurl.'/', evo_strlen($baseurl) );
			// echo ' -> ', $blog_siteurl,'<br />';

			$query_update_blog = "UPDATE T_blogs SET blog_siteurl = '$blog_siteurl' WHERE blog_ID = $blog_ID";
			// echo $query_update_blog, '<br />';
			$DB->query( $query_update_blog );
			$i++;
		}
		echo "OK. ($i rows updated)<br />\n";

		set_upgrade_checkpoint( '8030' );
	}


	if( $old_db_version < 8040 )
	{ // upgrade to 0.8.7
		echo 'Creating table for Antispam Blackist... ';
		$query = "CREATE TABLE T_antispam (
			aspm_ID bigint(11) NOT NULL auto_increment,
			aspm_string varchar(80) NOT NULL,
			aspm_source enum( 'local','reported','central' ) NOT NULL default 'reported',
			PRIMARY KEY aspm_ID (aspm_ID),
			UNIQUE aspm_string (aspm_string)
		)";
		$DB->query( $query );
		echo "OK.<br />\n";

		echo 'Creating default blacklist entries... ';
		// This string contains antispam information that is obfuscated because some hosting
		// companies prevent uploading PHP files containing "spam" strings.
		// pre_dump(get_antispam_query());
		$query = get_antispam_query();
		$DB->query( $query );
		echo "OK.<br />\n";

		echo 'Upgrading Settings table... ';
		$query = "ALTER TABLE T_settings
							ADD COLUMN last_antispam_update datetime NOT NULL default '2000-01-01 00:00:00'";
		$DB->query( $query );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '8040' );
	}


	if( $old_db_version < 8050 )
	{ // upgrade to 0.8.9
		echo 'Upgrading blogs table... ';
		$query = "ALTER TABLE T_blogs
							ADD COLUMN blog_allowtrackbacks tinyint(1) NOT NULL default 1,
							ADD COLUMN blog_allowpingbacks tinyint(1) NOT NULL default 0,
							ADD COLUMN blog_pingb2evonet tinyint(1) NOT NULL default 0,
							ADD COLUMN blog_pingtechnorati tinyint(1) NOT NULL default 0,
							ADD COLUMN blog_pingweblogs tinyint(1) NOT NULL default 0,
							ADD COLUMN blog_pingblodotgs tinyint(1) NOT NULL default 0,
							ADD COLUMN blog_disp_bloglist tinyint NOT NULL DEFAULT 1";
		$DB->query( $query );
		echo "OK.<br />\n";

		// Create User Groups
		global $Group_Admins, $Group_Privileged, $Group_Bloggers, $Group_Users;
		echo 'Creating table for Groups... ';
		$query = "CREATE TABLE T_groups (
			grp_ID int(11) NOT NULL auto_increment,
			grp_name varchar(50) NOT NULL default '',
			grp_perm_admin enum('none','hidden','visible') NOT NULL default 'visible',
			grp_perm_blogs enum('user','viewall','editall') NOT NULL default 'user',
			grp_perm_stats enum('none','view','edit') NOT NULL default 'none',
			grp_perm_spamblacklist enum('none','view','edit') NOT NULL default 'none',
			grp_perm_options enum('none','view','edit') NOT NULL default 'none',
			grp_perm_users enum('none','view','edit') NOT NULL default 'none',
			grp_perm_templates TINYINT NOT NULL DEFAULT 0,
			grp_perm_files enum('none','view','add','edit') NOT NULL default 'none',
			PRIMARY KEY grp_ID (grp_ID)
		)";
		$DB->query( $query );
		echo "OK.<br />\n";

		// This table needs to be created here for proper group insertion
		task_begin( 'Creating table for Group Settings... ' );
		$DB->query( "CREATE TABLE T_groups__groupsettings (
			gset_grp_ID INT(11) UNSIGNED NOT NULL,
			gset_name VARCHAR(30) NOT NULL,
			gset_value VARCHAR(255) NULL,
			PRIMARY KEY (gset_grp_ID, gset_name)
		) ENGINE = innodb" );
		task_end();

		echo 'Creating default groups... ';
		$Group_Admins = new Group(); // COPY !
		$Group_Admins->set( 'name', 'Administrators' );
		$Group_Admins->set( 'perm_admin', 'visible' );
		$Group_Admins->set( 'perm_blogs', 'editall' );
		$Group_Admins->set( 'perm_stats', 'edit' );
		$Group_Admins->set( 'perm_spamblacklist', 'edit' );
		$Group_Admins->set( 'perm_files', 'all' );
		$Group_Admins->set( 'perm_options', 'edit' );
		$Group_Admins->set( 'perm_templates', 1 );
		$Group_Admins->set( 'perm_users', 'edit' );
		$Group_Admins->dbinsert();

		$Group_Privileged = new Group(); // COPY !
		$Group_Privileged->set( 'name', 'Privileged Bloggers' );
		$Group_Privileged->set( 'perm_admin', 'visible' );
		$Group_Privileged->set( 'perm_blogs', 'viewall' );
		$Group_Privileged->set( 'perm_stats', 'view' );
		$Group_Privileged->set( 'perm_spamblacklist', 'edit' );
		$Group_Privileged->set( 'perm_files', 'add' );
		$Group_Privileged->set( 'perm_options', 'view' );
		$Group_Privileged->set( 'perm_templates', 0 );
		$Group_Privileged->set( 'perm_users', 'view' );
		$Group_Privileged->dbinsert();

		$Group_Bloggers = new Group(); // COPY !
		$Group_Bloggers->set( 'name', 'Bloggers' );
		$Group_Bloggers->set( 'perm_admin', 'visible' );
		$Group_Bloggers->set( 'perm_blogs', 'user' );
		$Group_Bloggers->set( 'perm_stats', 'none' );
		$Group_Bloggers->set( 'perm_spamblacklist', 'view' );
		$Group_Bloggers->set( 'perm_files', 'view' );
		$Group_Bloggers->set( 'perm_options', 'none' );
		$Group_Bloggers->set( 'perm_templates', 0 );
		$Group_Bloggers->set( 'perm_users', 'none' );
		$Group_Bloggers->dbinsert();

		$Group_Users = new Group(); // COPY !
		$Group_Users->set( 'name', 'Basic Users' );
		$Group_Users->set( 'perm_admin', 'none' );
		$Group_Users->set( 'perm_blogs', 'user' );
		$Group_Users->set( 'perm_stats', 'none' );
		$Group_Users->set( 'perm_spamblacklist', 'none' );
		$Group_Users->set( 'perm_files', 'none' );
		$Group_Users->set( 'perm_options', 'none' );
		$Group_Users->set( 'perm_templates', 0 );
		$Group_Users->set( 'perm_users', 'none' );
		$Group_Users->dbinsert();
		echo "OK.<br />\n";


		echo 'Creating table for Blog-User permissions... ';
		$query = "CREATE TABLE T_coll_user_perms (
			bloguser_blog_ID int(11) unsigned NOT NULL default 0,
			bloguser_user_ID int(11) unsigned NOT NULL default 0,
			bloguser_ismember tinyint NOT NULL default 0,
			bloguser_perm_poststatuses set('published','deprecated','protected','private','draft') NOT NULL default '',
			bloguser_perm_delpost tinyint NOT NULL default 0,
			bloguser_perm_comments tinyint NOT NULL default 0,
			bloguser_perm_cats tinyint NOT NULL default 0,
			bloguser_perm_properties tinyint NOT NULL default 0,
			bloguser_perm_media_upload tinyint NOT NULL default 0,
			bloguser_perm_media_browse tinyint NOT NULL default 0,
			bloguser_perm_media_change tinyint NOT NULL default 0,
			PRIMARY KEY bloguser_pk (bloguser_blog_ID,bloguser_user_ID)
		)";
		$DB->query( $query );
		echo "OK.<br />\n";
		$tablegroups_isuptodate = true;
		$tableblogusers_isuptodate = true;

		echo 'Creating user blog permissions... ';
		// Admin: full rights for all blogs (look 'ma, doing a natural join! :>)
		$query = "INSERT INTO T_coll_user_perms( bloguser_blog_ID, bloguser_user_ID, bloguser_ismember,
								bloguser_perm_poststatuses, bloguser_perm_delpost, bloguser_perm_comments,
								bloguser_perm_cats, bloguser_perm_properties)
							SELECT blog_ID, ID, 1, 'published,deprecated,protected,private,draft', 1, 1, 1, 1
							FROM T_users, T_blogs
							WHERE user_level = 10";
		$DB->query( $query );

		// Normal users: basic rights for all blogs (can't stop doing joins :P)
		$query = "INSERT INTO T_coll_user_perms( bloguser_blog_ID, bloguser_user_ID, bloguser_ismember,
								bloguser_perm_poststatuses, bloguser_perm_delpost, bloguser_perm_comments,
								bloguser_perm_cats, bloguser_perm_properties)
							SELECT blog_ID, ID, 1, 'published,protected,private,draft', 0, 1, 0, 0
							FROM T_users, T_blogs
							WHERE user_level > 0 AND user_level < 10";
		$DB->query( $query );
		echo "OK.<br />\n";

		echo 'Upgrading users table... ';
		$query = "ALTER TABLE T_users
							ADD COLUMN user_notify tinyint(1) NOT NULL default 1,
							ADD COLUMN user_grp_ID int(4) NOT NULL default 1,
							MODIFY COLUMN user_idmode varchar(20) NOT NULL DEFAULT 'login',
							ADD KEY user_grp_ID (user_grp_ID)";
		$DB->query( $query );
		echo "OK.<br />\n";

		echo 'Assigning user groups... ';

		// Default is 1, so admins are already set.

		// Basic Users:
		$query = "UPDATE T_users
							SET user_grp_ID = $Group_Users->ID
							WHERE user_level = 0";
		$DB->query( $query );

		// Bloggers:
		$query = "UPDATE T_users
							SET user_grp_ID = $Group_Bloggers->ID
							WHERE user_level > 0 AND user_level < 10";
		$DB->query( $query );

		echo "OK.<br />\n";

		echo 'Upgrading settings table... ';
		$query = "ALTER TABLE T_settings
							DROP COLUMN time_format,
							DROP COLUMN date_format,
							ADD COLUMN pref_newusers_grp_ID int unsigned DEFAULT 4 NOT NULL,
							ADD COLUMN pref_newusers_level tinyint unsigned DEFAULT 1 NOT NULL,
							ADD COLUMN pref_newusers_canregister tinyint unsigned DEFAULT 0 NOT NULL";
		$DB->query( $query );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '8050' );
	}


	if( $old_db_version < 8060 )
	{ // upgrade to 0.9
		// Important check:
		$stub_list = $DB->get_col( "
			SELECT blog_stub
			  FROM T_blogs
			 GROUP BY blog_stub
			HAVING COUNT(*) > 1" );
		if( !empty($stub_list) )
		{
			echo '<div class="error"><p class="error">';
			printf( T_("It appears that the following blog stub names are used more than once: ['%s']" ), implode( "','", $stub_list ) );
			echo '</p><p>';
			printf( T_("I can't upgrade until you make them unique. DB field: [%s]" ), $db_config['aliases']['T_blogs'].'.blog_stub' );
			echo '</p></div>';
			return false;
		}

		// Create locales
		echo 'Creating table for Locales... ';
		$query = "CREATE TABLE T_locales (
				loc_locale varchar(20) NOT NULL default '',
				loc_charset varchar(15) NOT NULL default 'iso-8859-1',
				loc_datefmt varchar(10) NOT NULL default 'y-m-d',
				loc_timefmt varchar(10) NOT NULL default 'H:i:s',
				loc_name varchar(40) NOT NULL default '',
				loc_messages varchar(20) NOT NULL default '',
				loc_priority tinyint(4) UNSIGNED NOT NULL default '0',
				loc_enabled tinyint(4) NOT NULL default '1',
				PRIMARY KEY loc_locale( loc_locale )
			) COMMENT='saves available locales'";
		$DB->query( $query );
		echo "OK.<br />\n";

		echo 'Upgrading posts table... ';
		$query = "UPDATE {$tableprefix}posts
							SET post_urltitle = NULL";
		$DB->query( $query );

		$query = "ALTER TABLE {$tableprefix}posts
							CHANGE COLUMN post_date post_issue_date datetime NOT NULL default '1000-01-01 00:00:00',
							ADD COLUMN post_mod_date datetime NOT NULL default '1000-01-01 00:00:00'
										AFTER post_issue_date,
							CHANGE COLUMN post_lang post_locale varchar(20) NOT NULL default 'en-EU',
							DROP COLUMN post_url,
							CHANGE COLUMN post_trackbacks post_url varchar(250) NULL default NULL,
							MODIFY COLUMN post_flags SET( 'pingsdone', 'imported' ),
							ADD COLUMN post_renderers VARCHAR(179) NOT NULL default 'default',
							DROP INDEX post_date,
							ADD INDEX post_issue_date( post_issue_date ),
							ADD UNIQUE post_urltitle( post_urltitle )";
		$DB->query( $query );

		$query = "UPDATE {$tableprefix}posts
							SET post_mod_date = post_issue_date";
		$DB->query( $query );
		echo "OK.<br />\n";

		// convert given languages to locales
		convert_lang_to_locale( "{$tableprefix}posts", 'post_locale', 'ID' );

		echo 'Upgrading blogs table... ';
		$query = "ALTER TABLE T_blogs
							CHANGE blog_lang blog_locale varchar(20) NOT NULL default 'en-EU',
							CHANGE blog_roll blog_notes TEXT NULL,
							MODIFY COLUMN blog_default_skin VARCHAR(30) NOT NULL DEFAULT 'custom',
							DROP COLUMN blog_filename,
							ADD COLUMN blog_access_type VARCHAR(10) NOT NULL DEFAULT 'index.php' AFTER blog_locale,
							ADD COLUMN blog_force_skin tinyint(1) NOT NULL default 0 AFTER blog_default_skin,
							ADD COLUMN blog_in_bloglist tinyint(1) NOT NULL DEFAULT 1 AFTER blog_disp_bloglist,
							ADD COLUMN blog_links_blog_ID INT(4) NOT NULL DEFAULT 0,
							ADD UNIQUE KEY blog_stub (blog_stub)";
		$DB->query( $query );

		$query = "UPDATE T_blogs
							SET blog_access_type = 'stub',
									blog_default_skin = 'custom'";
		$DB->query( $query );

		echo "OK.<br />\n";

		// convert given languages to locales
		convert_lang_to_locale( 'T_blogs', 'blog_locale', 'blog_ID' );


		echo 'Converting settings table... ';

		// get old settings
		$query = 'SELECT * FROM T_settings';
		$row = $DB->get_row( $query, ARRAY_A );

		#echo 'oldrow:<br />'; pre_dump($row);
		$transform = array(
			'posts_per_page' => array(5),      // note: moved to blogsettings in 2.0
			'what_to_show' => array('posts'),  // note: moved to blogsettings in 2.0
			'archive_mode' => array('monthly'),// note: moved to blogsettings in 2.0
			'time_difference' => array(0),
			'AutoBR' => array(0),
			'last_antispam_update' => array('2000-01-01 00:00:00', 'antispam_last_update'),
			'pref_newusers_grp_ID' => array($Group_Users->ID, 'newusers_grp_ID'),
			'pref_newusers_level'  => array(1, 'newusers_level'),
			'pref_newusers_canregister' => array(0, 'newusers_canregister'),
		);

		$_trans = array();
		foreach( $transform as $oldkey => $newarr )
		{
			$newname = ( isset($newarr[1]) ? $newarr[1] : $oldkey );
			if( !isset( $row[$oldkey] ) )
			{
				echo '&nbsp;&middot;Setting '.$oldkey.' not found, using defaults.<br />';
				$_trans[ $newname ] = $newarr[0];
			}
			else
			{
				$_trans[ $newname ] = $row[$oldkey];
			}
		}

		// drop old table
		$DB->query( 'DROP TABLE IF EXISTS T_settings' );

		// create new table
		$DB->query(
			'CREATE TABLE T_settings (
				set_name VARCHAR( 30 ) NOT NULL ,
				set_value VARCHAR( 255 ) NULL ,
				PRIMARY KEY ( set_name )
			)');

		// insert defaults and use transformed settings
		create_default_settings( $_trans );

		if( !isset( $tableblogusers_isuptodate ) )
		{
			echo 'Upgrading Blog-User permissions table... ';
			$query = "ALTER TABLE T_coll_user_perms
								ADD COLUMN bloguser_ismember tinyint NOT NULL default 0 AFTER bloguser_user_ID";
			$DB->query( $query );

			// Any row that is created holds at least one permission,
			// minimum permsission is to be a member, so we add that one too, to all existing rows.
			$DB->query( "UPDATE T_coll_user_perms
											SET bloguser_ismember = 1" );
			echo "OK.<br />\n";
		}

		echo 'Upgrading Comments table... ';
		$query = "ALTER TABLE T_comments
							ADD COLUMN comment_author_ID int unsigned NULL default NULL AFTER comment_status,
							MODIFY COLUMN comment_author varchar(100) NULL,
							MODIFY COLUMN comment_author_email varchar(100) NULL,
							MODIFY COLUMN comment_author_url varchar(100) NULL,
							MODIFY COLUMN comment_author_IP varchar(23) NOT NULL default ''";
		$DB->query( $query );
		echo "OK.<br />\n";

		echo 'Upgrading Users table... ';
		$query = "ALTER TABLE T_users ADD user_locale VARCHAR( 20 ) DEFAULT 'en-EU' NOT NULL AFTER user_yim";
		$DB->query( $query );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '8060' );
	}


	if( $old_db_version < 8062 )
	{ // upgrade to 0.9.0.4
		echo "Checking for extra quote escaping in posts... ";
		$query = "SELECT ID, post_title, post_content
								FROM {$tableprefix}posts
							 WHERE post_title LIKE '%\\\\\\\\\'%'
									OR post_title LIKE '%\\\\\\\\\"%'
									OR post_content LIKE '%\\\\\\\\\'%'
									OR post_content LIKE '%\\\\\\\\\"%' ";
		/* FP: the above looks overkill, but MySQL is really full of surprises...
						tested on 4.0.14-nt */
		// echo $query;
		$rows = $DB->get_results( $query, ARRAY_A );
		if( $DB->num_rows )
		{
			echo 'Updating '.$DB->num_rows.' posts... ';
			foreach( $rows as $row )
			{
				// echo '<br />'.$row['post_title'];
				$query = "UPDATE {$tableprefix}posts
									SET post_title = ".$DB->quote( stripslashes( $row['post_title'] ) ).",
											post_content = ".$DB->quote( stripslashes( $row['post_content'] ) )."
									WHERE ID = ".$row['ID'];
				// echo '<br />'.$query;
				$DB->query( $query );
			}
		}
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '8062' );
	}


	if( $old_db_version < 8064 )
	{ // upgrade to 0.9.0.6
		cleanup_comment_quotes();

		set_upgrade_checkpoint( '8064' );
	}


	if( $old_db_version < 8066 )
	{	// upgrade to 0.9.1
		echo 'Adding catpost index... ';
		$DB->query( 'ALTER TABLE T_postcats ADD UNIQUE catpost ( postcat_cat_ID, postcat_post_ID )' );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '8066' );
	}


	if( $old_db_version < 8800 )
	{ // ---------------------------------- upgrade to 1.6 "phoenix ALPHA"

		echo 'Dropping old Hitlog table... ';
		$DB->query( 'DROP TABLE IF EXISTS T_hitlog' );
		echo "OK.<br />\n";

		// New tables:
			echo 'Creating table for active sessions... ';
			$DB->query( "CREATE TABLE T_sessions (
											sess_ID        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
											sess_key       CHAR(32) NULL,
											sess_lastseen  DATETIME NOT NULL,
											sess_ipaddress VARCHAR(15) NOT NULL DEFAULT '',
											sess_user_ID   INT(10) DEFAULT NULL,
											sess_agnt_ID   INT UNSIGNED NULL,
											sess_data      TEXT DEFAULT NULL,
											PRIMARY KEY( sess_ID )
										)" );
			echo "OK.<br />\n";


			echo 'Creating user settings table... ';
			$DB->query( "CREATE TABLE {$tableprefix}usersettings (
											uset_user_ID INT(11) UNSIGNED NOT NULL,
											uset_name    VARCHAR( 30 ) NOT NULL,
											uset_value   VARCHAR( 255 ) NULL,
											PRIMARY KEY ( uset_user_ID, uset_name )
										)");
			echo "OK.<br />\n";


			echo 'Creating plugins table... ';
			$DB->query( "CREATE TABLE T_plugins (
											plug_ID        INT(11) UNSIGNED NOT NULL auto_increment,
											plug_priority  INT(11) NOT NULL default 50,
											plug_classname VARCHAR(40) NOT NULL default '',
											PRIMARY KEY ( plug_ID )
										)");
			echo "OK.<br />\n";


			echo 'Creating table for Post Statuses... ';
			$query="CREATE TABLE {$tableprefix}poststatuses (
											pst_ID   int(11) unsigned not null AUTO_INCREMENT,
											pst_name varchar(30)      not null,
											primary key ( pst_ID )
										)";
			$DB->query( $query );
			echo "OK.<br />\n";


			echo 'Creating table for Post Types... ';
			$query="CREATE TABLE {$tableprefix}posttypes (
											ptyp_ID   int(11) unsigned not null AUTO_INCREMENT,
											ptyp_name varchar(30)      not null,
											primary key (ptyp_ID)
										)";
			$DB->query( $query );
			echo "OK.<br />\n";


			echo 'Creating table for File Meta Data... ';
			$DB->query( "CREATE TABLE T_files (
										 file_ID        int(11) unsigned  not null AUTO_INCREMENT,
										 file_root_type enum('absolute','user','group','collection') not null default 'absolute',
										 file_root_ID   int(11) unsigned  not null default 0,
										 file_path      varchar(255)      not null default '',
										 file_title     varchar(255),
										 file_alt       varchar(255),
										 file_desc      text,
										 primary key (file_ID),
										 unique file (file_root_type, file_root_ID, file_path)
									)" );
			echo "OK.<br />\n";


			echo 'Creating table for base domains... ';
			$DB->query( "CREATE TABLE T_basedomains (
										dom_ID     INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
										dom_name   VARCHAR(250) NOT NULL DEFAULT '',
										dom_status ENUM('unknown','whitelist','blacklist') NOT NULL DEFAULT 'unknown',
										dom_type   ENUM('unknown','normal','searcheng','aggregator') NOT NULL DEFAULT 'unknown',
										PRIMARY KEY (dom_ID),
										UNIQUE dom_name (dom_name)
									)" );	// fp> the unique key was only named in version 1.9. Crap. Put the name back here to save as many souls as possible. bulk has not upgraded from 0.9 yet :/
			echo "OK.<br />\n";

		set_upgrade_checkpoint( '8820' );
	}


	if( $old_db_version < 8840 )
	{

			echo 'Creating table for user agents... ';
			$DB->query( "CREATE TABLE {$tableprefix}useragents (
										agnt_ID        INT UNSIGNED NOT NULL AUTO_INCREMENT,
										agnt_signature VARCHAR(250) NOT NULL,
										agnt_type      ENUM('rss','robot','browser','unknown') DEFAULT 'unknown' NOT NULL,
										PRIMARY KEY (agnt_ID) )" );
			echo "OK.<br />\n";


			echo 'Creating table for Hit-Logs... ';
			$query = "CREATE TABLE T_hitlog (
									hit_ID             INT(11) NOT NULL AUTO_INCREMENT,
									hit_sess_ID        INT UNSIGNED,
									hit_datetime       DATETIME NOT NULL,
									hit_uri            VARCHAR(250) DEFAULT NULL,
									hit_referer_type   ENUM('search','blacklist','referer','direct','spam') NOT NULL,
									hit_referer        VARCHAR(250) DEFAULT NULL,
									hit_referer_dom_ID INT UNSIGNED DEFAULT NULL,
									hit_blog_ID        int(11) UNSIGNED NULL DEFAULT NULL,
									hit_remote_addr    VARCHAR(40) DEFAULT NULL,
									PRIMARY KEY (hit_ID),
									INDEX hit_datetime ( hit_datetime ),
									INDEX hit_blog_ID (hit_blog_ID)
								)";
			$DB->query( $query );
			echo "OK.<br />\n";


			echo 'Creating table for subscriptions... ';
			$DB->query( "CREATE TABLE T_subscriptions (
										 sub_coll_ID     int(11) unsigned    not null,
										 sub_user_ID     int(11) unsigned    not null,
										 sub_items       tinyint(1)          not null,
										 sub_comments    tinyint(1)          not null,
										 primary key (sub_coll_ID, sub_user_ID)
										)" );
			echo "OK.<br />\n";


			echo 'Creating table for blog-group permissions... ';
			$DB->query( "CREATE TABLE T_coll_group_perms (
											bloggroup_blog_ID int(11) unsigned NOT NULL default 0,
											bloggroup_group_ID int(11) unsigned NOT NULL default 0,
											bloggroup_ismember tinyint NOT NULL default 0,
											bloggroup_perm_poststatuses set('published','deprecated','protected','private','draft') NOT NULL default '',
											bloggroup_perm_delpost tinyint NOT NULL default 0,
											bloggroup_perm_comments tinyint NOT NULL default 0,
											bloggroup_perm_cats tinyint NOT NULL default 0,
											bloggroup_perm_properties tinyint NOT NULL default 0,
											bloggroup_perm_media_upload tinyint NOT NULL default 0,
											bloggroup_perm_media_browse tinyint NOT NULL default 0,
											bloggroup_perm_media_change tinyint NOT NULL default 0,
											PRIMARY KEY bloggroup_pk (bloggroup_blog_ID,bloggroup_group_ID) )" );
			echo "OK.<br />\n";


		echo 'Upgrading blogs table... ';
		$query = "ALTER TABLE T_blogs
							MODIFY COLUMN blog_ID int(11) unsigned NOT NULL auto_increment,
							MODIFY COLUMN blog_links_blog_ID INT(11) NULL DEFAULT NULL,
							CHANGE COLUMN blog_stub blog_urlname VARCHAR(255) NOT NULL DEFAULT 'urlname',
							ADD COLUMN blog_allowcomments VARCHAR(20) NOT NULL default 'post_by_post' AFTER blog_keywords,
							ADD COLUMN blog_allowblogcss TINYINT(1) NOT NULL default 1 AFTER blog_allowpingbacks,
							ADD COLUMN blog_allowusercss TINYINT(1) NOT NULL default 1 AFTER blog_allowblogcss,
							ADD COLUMN blog_stub VARCHAR(255) NOT NULL DEFAULT 'stub' AFTER blog_staticfilename,
							ADD COLUMN blog_commentsexpire INT(4) NOT NULL DEFAULT 0 AFTER blog_links_blog_ID,
							ADD COLUMN blog_media_location ENUM( 'default', 'subdir', 'custom', 'none' ) DEFAULT 'default' NOT NULL AFTER blog_commentsexpire,
							ADD COLUMN blog_media_subdir VARCHAR( 255 ) NOT NULL AFTER blog_media_location,
							ADD COLUMN blog_media_fullpath VARCHAR( 255 ) NOT NULL AFTER blog_media_subdir,
							ADD COLUMN blog_media_url VARCHAR(255) NOT NULL AFTER blog_media_fullpath,
							DROP INDEX blog_stub,
							ADD UNIQUE blog_urlname ( blog_urlname )";
		$DB->query( $query );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '8840' );
	}


	// sam2kb>fp: We need to make sure there are no values like "blog_a.php" in blog_urlname,
	//			after this upgrade blog URLs look like $baseurl.'blog_a.php' which might be OK in 0.x version,
	//			but this config will not work in b2evo 4. Blog URLs will be broken!
	if( $old_db_version < 8850 )
	{
		echo 'Updating relative URLs... ';
		// We need to move the slashes to the end:
		$query = "UPDATE T_blogs
								 SET blog_siteurl = CONCAT( SUBSTRING(blog_siteurl,2) , '/' )
							 WHERE blog_siteurl LIKE '/%'";
		$DB->query( $query );
		echo "OK.<br />\n";

		echo 'Copying urlnames to stub names... ';
		$query = 'UPDATE T_blogs
							SET blog_stub = blog_urlname';
		$DB->query( $query );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '8850' );
	}


	if( $old_db_version < 8855 )
	{
		echo 'Upgrading posts table... ';
		$query = "ALTER TABLE {$tableprefix}posts
							DROP COLUMN post_karma,
							DROP COLUMN post_autobr,
							DROP INDEX post_author,
							DROP INDEX post_issue_date,
							DROP INDEX post_category,
							CHANGE COLUMN ID post_ID int(11) unsigned NOT NULL auto_increment,
							CHANGE COLUMN post_author	post_creator_user_ID int(11) unsigned NOT NULL,
							CHANGE COLUMN post_issue_date	post_datestart datetime NOT NULL,
							CHANGE COLUMN post_mod_date	post_datemodified datetime NOT NULL,
							CHANGE COLUMN post_category post_main_cat_ID int(11) unsigned NOT NULL,
							ADD post_parent_ID				int(11) unsigned NULL AFTER post_ID,
							ADD post_lastedit_user_ID	int(11) unsigned NULL AFTER post_creator_user_ID,
							ADD post_assigned_user_ID	int(11) unsigned NULL AFTER post_lastedit_user_ID,
							ADD post_datedeadline 		datetime NULL AFTER post_datestart,
							ADD post_datecreated			datetime NULL AFTER post_datedeadline,
							ADD post_pst_ID						int(11) unsigned NULL AFTER post_status,
							ADD post_ptyp_ID					int(11) unsigned NULL AFTER post_pst_ID,
							ADD post_views						int(11) unsigned NOT NULL DEFAULT 0 AFTER post_flags,
							ADD post_commentsexpire		datetime DEFAULT NULL AFTER post_comments,
							ADD post_priority					int(11) unsigned null,
							ADD INDEX post_creator_user_ID( post_creator_user_ID ),
							ADD INDEX post_parent_ID( post_parent_ID ),
							ADD INDEX post_assigned_user_ID( post_assigned_user_ID ),
							ADD INDEX post_datestart( post_datestart ),
							ADD INDEX post_main_cat_ID( post_main_cat_ID ),
							ADD INDEX post_ptyp_ID( post_ptyp_ID ),
							ADD INDEX post_pst_ID( post_pst_ID ) ";
		$DB->query( $query );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '8855' );
	}


	if( $old_db_version < 8860 )
	{
		echo 'Updating post data... ';
		$query = "UPDATE {$tableprefix}posts
							SET post_lastedit_user_ID = post_creator_user_ID,
									post_datecreated = post_datestart";
		$DB->query( $query );
		echo "OK.<br />\n";


		task_begin( 'Upgrading users table... ' );
		$DB->query( 'UPDATE T_users
									  SET dateYMDhour = \'2000-01-01 00:00:00\'
									WHERE dateYMDhour = \'0000-00-00 00:00:00\'' );
		$DB->query( 'ALTER TABLE T_users
							MODIFY COLUMN dateYMDhour DATETIME NOT NULL DEFAULT \'2000-01-01 00:00:00\',
							CHANGE COLUMN ID user_ID int(11) unsigned NOT NULL auto_increment,
							MODIFY COLUMN user_icq int(11) unsigned DEFAULT 0 NOT NULL,
							ADD COLUMN user_showonline tinyint(1) NOT NULL default 1 AFTER user_notify' );
		task_end();


		set_upgrade_checkpoint( '8860' );
	}


	if( $old_db_version < 8900 )
	{

		echo 'Setting new defaults... ';
		$query = 'INSERT INTO T_settings (set_name, set_value)
							VALUES
								( "reloadpage_timeout", "300" ),
								( "upload_enabled", "'.(isset($use_fileupload) ? (int)$use_fileupload : '1').'" ),
								( "upload_allowedext", "'.(isset($fileupload_allowedtypes) ? $fileupload_allowedtypes : 'jpg gif png').'" ),
								( "upload_maxkb", "'.(isset($fileupload_maxk) ? (int)$fileupload_maxk : '96').'" )
							';
		$DB->query( $query );
		// Replace "paged" mode with "posts" // note: moved to blogsettings in 2.0
		$DB->query( 'UPDATE T_settings
										SET set_value = "posts"
									WHERE set_name = "what_to_show"
									  AND set_value = "paged"' );
		echo "OK.<br />\n";


		if( !isset( $tableblogusers_isuptodate ) )
		{	// We have created the blogusers table before and it's already clean!
			echo 'Altering table for Blog-User permissions... ';
			$DB->query( 'ALTER TABLE T_coll_user_perms
										MODIFY COLUMN bloguser_blog_ID int(11) unsigned NOT NULL default 0,
										MODIFY COLUMN bloguser_user_ID int(11) unsigned NOT NULL default 0,
										ADD COLUMN bloguser_perm_media_upload tinyint NOT NULL default 0,
										ADD COLUMN bloguser_perm_media_browse tinyint NOT NULL default 0,
										ADD COLUMN bloguser_perm_media_change tinyint NOT NULL default 0' );
			echo "OK.<br />\n";
		}


		task_begin( 'Altering comments table...' );
		$DB->query( 'UPDATE T_comments
									  SET comment_date = \'2000-01-01 00:00:00\'
									WHERE comment_date = \'0000-00-00 00:00:00\'' );
		$DB->query( 'ALTER TABLE T_comments
									MODIFY COLUMN comment_date DATETIME NOT NULL DEFAULT \'2000-01-01 00:00:00\',
									MODIFY COLUMN comment_post_ID		int(11) unsigned NOT NULL default 0' );
		task_end();

		set_upgrade_checkpoint( '8900' );
	}

	if( $old_db_version < 9000 )
	{
		echo 'Altering Posts to Categories table... ';
		$DB->query( "ALTER TABLE T_postcats
									MODIFY COLUMN postcat_post_ID int(11) unsigned NOT NULL,
									MODIFY COLUMN postcat_cat_ID int(11) unsigned NOT NULL" );
		echo "OK.<br />\n";


		echo 'Altering Categories table... ';
		$DB->query( "ALTER TABLE T_categories
									MODIFY COLUMN cat_ID int(11) unsigned NOT NULL auto_increment,
									MODIFY COLUMN cat_parent_ID int(11) unsigned NULL,
									MODIFY COLUMN cat_blog_ID int(11) unsigned NOT NULL default 2" );
		echo "OK.<br />\n";


		echo 'Altering Locales table... ';
		$DB->query( 'ALTER TABLE T_locales
									ADD loc_startofweek TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER loc_timefmt' );
		echo "OK.<br />\n";


		if( !isset( $tablegroups_isuptodate ) )
		{	// We have created the groups table before and it's already clean!
			echo 'Altering Groups table... ';
			$DB->query( "ALTER TABLE T_groups
										ADD COLUMN grp_perm_admin enum('none','hidden','visible') NOT NULL default 'visible' AFTER grp_name,
										ADD COLUMN grp_perm_files enum('none','view','add','edit') NOT NULL default 'none'" );
			echo "OK.<br />\n";
		}


		echo 'Creating table for Post Links... ';
		$DB->query( "CREATE TABLE T_links (
									link_ID               int(11) unsigned  not null AUTO_INCREMENT,
									link_datecreated      datetime          not null,
									link_datemodified     datetime          not null,
									link_creator_user_ID  int(11) unsigned  not null,
									link_lastedit_user_ID int(11) unsigned  not null,
									link_item_ID          int(11) unsigned  NOT NULL,
									link_dest_item_ID     int(11) unsigned  NULL,
									link_file_ID          int(11) unsigned  NULL,
									link_ltype_ID         int(11) unsigned  NOT NULL default 1,
									link_external_url     VARCHAR(255)      NULL,
									link_title            TEXT              NULL,
									PRIMARY KEY (link_ID),
									INDEX link_item_ID( link_item_ID ),
									INDEX link_dest_item_ID (link_dest_item_ID),
									INDEX link_file_ID (link_file_ID)
								)" );
		echo "OK.<br />\n";


		echo 'Creating default Post Types... ';
		$DB->query( "
			INSERT INTO {$tableprefix}posttypes ( ptyp_ID, ptyp_name )
			VALUES ( 1, 'Post' ),
			       ( 2, 'Link' )" );
		echo "OK.<br />\n";


		set_upgrade_checkpoint( '9000' );
	}


	if( $old_db_version < 9100 )
	{	// 1.8 ALPHA

		echo 'Creating table for plugin events... ';
		$DB->query( '
			CREATE TABLE T_pluginevents(
					pevt_plug_ID INT(11) UNSIGNED NOT NULL,
					pevt_event VARCHAR(40) NOT NULL,
					pevt_enabled TINYINT NOT NULL DEFAULT 1,
					PRIMARY KEY( pevt_plug_ID, pevt_event )
				)' );
		echo "OK.<br />\n";


		echo 'Altering Links table... ';
		$DB->query( 'ALTER TABLE T_links
		             CHANGE link_item_ID link_itm_ID INT( 11 ) UNSIGNED NOT NULL,
		             CHANGE link_dest_item_ID link_dest_itm_ID INT( 11 ) UNSIGNED NULL' );
		echo "OK.<br />\n";


		if( $old_db_version >= 9000 )
		{ // sess_agnt_ID used in Phoenix-Alpha
			echo 'Altering sessions table... ';
			$query = "
					ALTER TABLE T_sessions
					 DROP COLUMN sess_agnt_ID";
			$DB->query( $query );
			echo "OK.<br />\n";
		}

		echo 'Creating table for file types... ';
		$DB->query( '
				CREATE TABLE T_filetypes (
					ftyp_ID int(11) unsigned NOT NULL auto_increment,
					ftyp_extensions varchar(30) NOT NULL,
					ftyp_name varchar(30) NOT NULL,
					ftyp_mimetype varchar(50) NOT NULL,
					ftyp_icon varchar(20) default NULL,
					ftyp_viewtype varchar(10) NOT NULL,
					ftyp_allowed tinyint(1) NOT NULL default 0,
					PRIMARY KEY (ftyp_ID)
				)' );
		echo "OK.<br />\n";

		echo 'Creating default file types... ';
		$DB->query( "INSERT INTO T_filetypes
				(ftyp_ID, ftyp_extensions, ftyp_name, ftyp_mimetype, ftyp_icon, ftyp_viewtype, ftyp_allowed)
			VALUES
				(1, 'gif', 'GIF image', 'image/gif', 'image2.png', 'image', 1),
				(2, 'png', 'PNG image', 'image/png', 'image2.png', 'image', 1),
				(3, 'jpg jpeg', 'JPEG image', 'image/jpeg', 'image2.png', 'image', 1),
				(4, 'txt', 'Text file', 'text/plain', 'document.png', 'text', 1),
				(5, 'htm html', 'HTML file', 'text/html', 'html.png', 'browser', 0),
				(6, 'pdf', 'PDF file', 'application/pdf', 'pdf.png', 'browser', 1),
				(7, 'doc', 'Microsoft Word file', 'application/msword', 'doc.gif', 'external', 1),
				(8, 'xls', 'Microsoft Excel file', 'application/vnd.ms-excel', 'xls.gif', 'external', 1),
				(9, 'ppt', 'Powerpoint', 'application/vnd.ms-powerpoint', 'ppt.gif', 'external', 1),
				(10, 'pps', 'Slideshow', 'pps', 'pps.gif', 'external', 1),
				(11, 'zip', 'ZIP archive', 'application/zip', 'zip.gif', 'external', 1),
				(12, 'php php3 php4 php5 php6', 'PHP script', 'application/x-httpd-php', 'php.gif', 'text', 0),
				(13, 'css', 'Style sheet', 'text/css', '', 'text', 1)
			" );
		echo "OK.<br />\n";

		echo 'Giving Administrator Group edit perms on files... ';
		$DB->query( 'UPDATE T_groups
		             SET grp_perm_files = "edit"
		             WHERE grp_ID = 1' );
	 	// Later versions give 'all' on install, but we won't upgrade to that for security.
		echo "OK.<br />\n";

		echo 'Giving Administrator Group full perms on media for all blogs... ';
		$DB->query( 'UPDATE T_coll_group_perms
		             SET bloggroup_perm_media_upload = 1,
		                 bloggroup_perm_media_browse = 1,
		                 bloggroup_perm_media_change = 1
		             WHERE bloggroup_group_ID = 1' );
		echo "OK.<br />\n";


		if( $old_db_version >= 9000 )
		{ // Uninstall all ALPHA (potentially incompatible) plugins
			echo 'Uninstalling all existing plugins... ';
			$DB->query( 'DELETE FROM T_plugins WHERE 1=1' );
			echo "OK.<br />\n";
		}

		// NOTE: basic plugins get installed separatly for upgrade and install..


		set_upgrade_checkpoint( '9100' );
	}


	if( $old_db_version < 9190 ) // Note: changed from 9200, to include the block below, if DB is not yet on 1.8
	{	// 1.8 ALPHA (block #2)
		echo 'Altering Posts table... ';
		$DB->query( "ALTER TABLE {$tableprefix}posts
		             CHANGE post_comments post_comment_status ENUM('disabled', 'open', 'closed') NOT NULL DEFAULT 'open'" );
		echo "OK.<br />\n";


		set_upgrade_checkpoint( '9190' );
	}


	if( $old_db_version < 9192 )
	{ // 1.8 ALPHA (block #3) - The payload that db_delta() handled before

		// This is a fix, which broke upgrade to 1.8 (from 1.6) in MySQL strict mode (inserted after 1.8 got released!):
		if( $DB->get_row( 'SHOW COLUMNS FROM T_hitlog LIKE "hit_referer_type"' ) )
		{ // a niiiiiiiice extra check :p
			task_begin( 'Deleting all "spam" hitlog entries... ' );
			$DB->query( '
					DELETE FROM T_hitlog
					 WHERE hit_referer_type = "spam"' );
			task_end();
		}

		task_begin( 'Upgrading users table... ' );
		$DB->query( 'ALTER TABLE T_users
										CHANGE COLUMN user_firstname user_firstname varchar(50) NULL,
										CHANGE COLUMN user_lastname user_lastname varchar(50) NULL,
										CHANGE COLUMN user_nickname user_nickname varchar(50) NULL,
										CHANGE COLUMN user_icq user_icq int(11) unsigned NULL,
										CHANGE COLUMN user_email user_email varchar(255) NOT NULL,
										CHANGE COLUMN user_url user_url varchar(255) NULL,
										CHANGE COLUMN user_ip user_ip varchar(15) NULL,
										CHANGE COLUMN user_domain user_domain varchar(200) NULL,
										CHANGE COLUMN user_browser user_browser varchar(200) NULL,
										CHANGE COLUMN user_aim user_aim varchar(50) NULL,
										CHANGE COLUMN user_msn user_msn varchar(100) NULL,
										CHANGE COLUMN user_yim user_yim varchar(50) NULL,
										ADD COLUMN user_allow_msgform TINYINT NOT NULL DEFAULT \'1\' AFTER user_idmode,
										ADD COLUMN user_validated TINYINT(1) NOT NULL DEFAULT 0 AFTER user_grp_ID' );
		task_end();

		task_begin( 'Creating blog settings...' );
		$DB->query( 'CREATE TABLE T_coll_settings (
															cset_coll_ID INT(11) UNSIGNED NOT NULL,
															cset_name    VARCHAR( 30 ) NOT NULL,
															cset_value   VARCHAR( 255 ) NULL,
															PRIMARY KEY ( cset_coll_ID, cset_name )
											)' );
		task_end();
		set_upgrade_checkpoint( '9192' );
	}


	if( $old_db_version < 9195 )
	{
		task_begin( 'Upgrading posts table... ' );
		$DB->query( 'ALTER TABLE '.$tableprefix.'posts
										CHANGE COLUMN post_content post_content         text NULL,
										CHANGE COLUMN post_url post_url              		VARCHAR(255) NULL DEFAULT NULL,
										CHANGE COLUMN post_renderers post_renderers     TEXT NOT NULL' );
		task_end();

		task_begin( 'Upgrading comments table... ' );
		$DB->query( 'ALTER TABLE T_comments
										CHANGE COLUMN comment_author_email comment_author_email varchar(255) NULL,
										CHANGE COLUMN comment_author_url comment_author_url varchar(255) NULL,
										ADD COLUMN comment_spam_karma TINYINT NULL AFTER comment_karma,
										ADD COLUMN comment_allow_msgform TINYINT NOT NULL DEFAULT 0 AFTER comment_spam_karma' );
		task_end();

		set_upgrade_checkpoint( '9195' );
	}


	if( $old_db_version < 9200 )
	{
		task_begin( 'Upgrading hitlog table... ' );
 		flush();
		$DB->query( 'ALTER TABLE T_hitlog
										CHANGE COLUMN hit_referer_type hit_referer_type   ENUM(\'search\',\'blacklist\',\'referer\',\'direct\') NOT NULL,
										ADD COLUMN hit_agnt_ID        INT UNSIGNED NULL AFTER hit_remote_addr' );
		task_end();

		task_begin( 'Upgrading post links table... ' );
		$DB->query( 'ALTER TABLE T_links
										ADD INDEX link_itm_ID( link_itm_ID ),
										ADD INDEX link_dest_itm_ID (link_dest_itm_ID)' );
		task_end();

		task_begin( 'Upgrading plugins table... ' );
		$DB->query( 'ALTER TABLE T_plugins
										CHANGE COLUMN plug_priority plug_priority        TINYINT NOT NULL default 50,
										ADD COLUMN plug_code            VARCHAR(32) NULL AFTER plug_classname,
										ADD COLUMN plug_apply_rendering ENUM( \'stealth\', \'always\', \'opt-out\', \'opt-in\', \'lazy\', \'never\' ) NOT NULL DEFAULT \'never\' AFTER plug_code,
										ADD COLUMN plug_version         VARCHAR(42) NOT NULL default \'0\' AFTER plug_apply_rendering,
										ADD COLUMN plug_status          ENUM( \'enabled\', \'disabled\', \'needs_config\', \'broken\' ) NOT NULL AFTER plug_version,
										ADD COLUMN plug_spam_weight     TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER plug_status,
										ADD UNIQUE plug_code( plug_code ),
										ADD INDEX plug_status( plug_status )' );
		task_end();

		task_begin( 'Creating plugin settings table... ' );
		$DB->query( 'CREATE TABLE T_pluginsettings (
															pset_plug_ID INT(11) UNSIGNED NOT NULL,
															pset_name VARCHAR( 30 ) NOT NULL,
															pset_value TEXT NULL,
															PRIMARY KEY ( pset_plug_ID, pset_name )
											)' );
		task_end();

		task_begin( 'Creating plugin user settings table... ' );
		$DB->query( 'CREATE TABLE T_pluginusersettings (
															puset_plug_ID INT(11) UNSIGNED NOT NULL,
															puset_user_ID INT(11) UNSIGNED NOT NULL,
															puset_name VARCHAR( 30 ) NOT NULL,
															puset_value TEXT NULL,
															PRIMARY KEY ( puset_plug_ID, puset_user_ID, puset_name )
											)' );
		task_end();

		task_begin( 'Creating scheduled tasks table... ' );
		$DB->query( 'CREATE TABLE T_cron__task(
												 ctsk_ID              int(10) unsigned      not null AUTO_INCREMENT,
												 ctsk_start_datetime  datetime              not null,
												 ctsk_repeat_after    int(10) unsigned,
												 ctsk_name            varchar(50)           not null,
												 ctsk_controller      varchar(50)           not null,
												 ctsk_params          text,
												 primary key (ctsk_ID)
											)' );
		task_end();

		task_begin( 'Creating cron log table... ' );
		$DB->query( 'CREATE TABLE T_cron__log(
															 clog_ctsk_ID              int(10) unsigned   not null,
															 clog_realstart_datetime   datetime           not null,
															 clog_realstop_datetime    datetime,
															 clog_status               enum(\'started\',\'finished\',\'error\',\'timeout\') not null default \'started\',
															 clog_messages             text,
															 primary key (clog_ctsk_ID)
											)' );
		task_end();

		task_begin( 'Upgrading blogs table... ' );
		// blog_allowpingbacks is "DEFAULT 1" in the 0.9.0.11 dump.. - changed in 0.9.2?!
		$DB->query( 'ALTER TABLE T_blogs
										ALTER COLUMN blog_allowpingbacks SET DEFAULT 0,
    								CHANGE COLUMN blog_media_subdir blog_media_subdir VARCHAR( 255 ) NULL,
										CHANGE COLUMN blog_media_fullpath blog_media_fullpath VARCHAR( 255 ) NULL,
										CHANGE COLUMN blog_media_url blog_media_url VARCHAR( 255 ) NULL' );
		task_end();


		set_upgrade_checkpoint( '9200' ); // at 1.8 "Summer Beta" release
	}


	// ____________________________ 1.9: ____________________________

	if( $old_db_version < 9290 )
	{
		echo 'Post-fix hit_referer_type == NULL... ';
		// If you've upgraded from 1.6 to 1.8 and it did not break because of strict mode, there are now NULL values for what "spam" was:
		$DB->query( '
					DELETE FROM T_hitlog
					 WHERE hit_referer_type IS NULL' );
		echo "OK.<br />\n";

		echo 'Marking administrator accounts as validated... ';
		$DB->query( '
				UPDATE T_users
				   SET user_validated = 1
				 WHERE user_grp_ID = 1' );
		echo "OK.<br />\n";

		echo 'Converting auto_prune_stats setting... ';
		$old_auto_prune_stats = $DB->get_var( '
				SELECT set_value
				  FROM T_settings
				 WHERE set_name = "auto_prune_stats"' );
		if( ! is_null($old_auto_prune_stats) && $old_auto_prune_stats < 1 )
		{ // This means it has been disabled before, so set auto_prune_stats_mode to "off"!
			$DB->query( '
					REPLACE INTO T_settings ( set_name, set_value )
					 VALUES ( "auto_prune_stats_mode", "off" )' );
		}
		echo "OK.<br />\n";

		echo 'Converting time_difference from hours to seconds... ';
		$DB->query( 'UPDATE T_settings SET set_value = set_value*3600 WHERE set_name = "time_difference"' );
		echo "OK.<br />\n";


		echo 'Updating hitlog capabilities... ';
		$DB->query( '
				ALTER TABLE '.$tableprefix.'useragents ADD INDEX agnt_type ( agnt_type )' );
		$DB->query( '
				ALTER TABLE T_hitlog
				  CHANGE COLUMN hit_referer_type hit_referer_type ENUM(\'search\',\'blacklist\',\'referer\',\'direct\',\'self\',\'admin\') NOT NULL' );
		echo "OK.<br />\n";

		echo 'Updating plugin capabilities... ';
		$DB->query( '
				ALTER TABLE T_plugins
					MODIFY COLUMN plug_status ENUM( \'enabled\', \'disabled\', \'needs_config\', \'broken\' ) NOT NULL' );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '9290' );
	}


	if( $old_db_version < 9300 )
	{
		// This can be so long, it needs its own checkpoint protected block in case of failure
		echo 'Updating hitlog indexes... ';
		$DB->query( '
				ALTER TABLE T_hitlog
				  ADD INDEX hit_agnt_ID        ( hit_agnt_ID ),
				  ADD INDEX hit_uri            ( hit_uri ),
				  ADD INDEX hit_referer_dom_ID ( hit_referer_dom_ID )
				' );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '9300' );
	}


	if( $old_db_version < 9310 )
	{
		echo 'Updating basedomains... ';
		$DB->query( '
				UPDATE T_basedomains
				   SET dom_status = "unknown"' );		// someone has filled this up with junk blacklists before
		$DB->query( '
				ALTER TABLE T_basedomains  ADD INDEX dom_type (dom_type)' );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '9310' );
	}


	if( $old_db_version < 9315 )
	{
		echo 'Altering locales table... ';
		$DB->query( "ALTER TABLE T_locales CHANGE COLUMN loc_datefmt loc_datefmt varchar(20) NOT NULL default 'y-m-d'" );
		$DB->query( "ALTER TABLE T_locales CHANGE COLUMN loc_timefmt loc_timefmt varchar(20) NOT NULL default 'H:i:s'" );
		echo "OK.<br />\n";

		echo 'Creating item prerendering cache table... ';
		$DB->query( "
				CREATE TABLE {$tableprefix}item__prerendering(
					itpr_itm_ID                   INT(11) UNSIGNED NOT NULL,
					itpr_format                   ENUM('htmlbody', 'entityencoded', 'xml', 'text') NOT NULL,
					itpr_renderers                TEXT NOT NULL,
					itpr_content_prerendered      TEXT NULL,
					itpr_datemodified             TIMESTAMP NOT NULL,
					PRIMARY KEY (itpr_itm_ID, itpr_format)
				)" );
		echo "OK.<br />\n";

		echo 'Altering plugins table... ';
		$DB->query( "ALTER TABLE T_plugins ADD COLUMN plug_name            VARCHAR(255) NULL default NULL AFTER plug_version" );
		$DB->query( "ALTER TABLE T_plugins ADD COLUMN plug_shortdesc       VARCHAR(255) NULL default NULL AFTER plug_name" );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '9315' );
	}


	if( $old_db_version < 9320 )
	{ // Dropping hit_datetime because it's very slow on INSERT (dh)
		// This can be so long, it needs its own checkpoint protected block in case of failure
		if( db_index_exists( 'T_hitlog', 'hit_datetime' ) )
		{ // only drop, if it still exists (may have been removed manually)
			echo 'Updating hitlog indexes... ';
			$DB->query( '
					ALTER TABLE T_hitlog
						DROP INDEX hit_datetime
					' );
			echo "OK.<br />\n";
		}

		set_upgrade_checkpoint( '9320' );
	}


	if( $old_db_version < 9326 )
	{
		echo 'Removing obsolete settings... ';
		$DB->query( 'DELETE FROM T_settings WHERE set_name = "upload_allowedext"' );
		echo "OK.<br />\n";

		echo 'Updating blogs... ';
		db_drop_col( 'T_blogs', 'blog_allowpingbacks' );

		// Remove and transform obsolete fields blog_pingb2evonet, blog_pingtechnorati, blog_pingweblogs, blog_pingblodotgs
		if( db_cols_exist( 'T_blogs', array('blog_pingb2evonet', 'blog_pingtechnorati', 'blog_pingweblogs', 'blog_pingblodotgs') ) )
		{
			foreach( $DB->get_results( '
					SELECT blog_ID, blog_pingb2evonet, blog_pingtechnorati, blog_pingweblogs, blog_pingblodotgs
						FROM T_blogs' ) as $row )
			{
				$ping_plugins = $DB->get_var( 'SELECT cset_value FROM T_coll_settings WHERE cset_coll_ID = '.$row->blog_ID.' AND cset_name = "ping_plugins"' );
				$ping_plugins = explode(',', $ping_plugins);
				if( $row->blog_pingb2evonet )
				{
					$ping_plugins[] = 'ping_b2evonet';
				}
				if( $row->blog_pingtechnorati || $row->blog_pingweblogs || $row->blog_pingblodotgs )
				{ // if either one of the previous pingers was enabled, add ping-o-matic:
					$ping_plugins[] = 'ping_pingomatic';
				}

				// Insert transformed/generated ping plugins collection setting:
				$ping_plugins = array_unique($ping_plugins);
				$DB->query( 'REPLACE INTO T_coll_settings
						( cset_coll_ID, cset_name, cset_value )
						VALUES ( '.$row->blog_ID.', "ping_plugins", "'.implode( ',', $ping_plugins ).'" )' );
			}
			$DB->query( 'ALTER TABLE T_blogs
					DROP COLUMN blog_pingb2evonet,
					DROP COLUMN blog_pingtechnorati,
					DROP COLUMN blog_pingweblogs,
					DROP COLUMN blog_pingblodotgs' );
		}
		echo "OK.<br />\n";


		set_upgrade_checkpoint( '9326' );
	}


	if( $old_db_version < 9328 )
	{
		echo 'Updating posts... ';
		db_add_col( "{$tableprefix}posts", 'post_notifications_status',  'ENUM("noreq","todo","started","finished") NOT NULL DEFAULT "noreq" AFTER post_flags' );
		db_add_col( "{$tableprefix}posts", 'post_notifications_ctsk_ID', 'INT(10) unsigned NULL DEFAULT NULL AFTER post_notifications_status' );
		echo "OK.<br />\n";
		set_upgrade_checkpoint( '9328' );
	}


	if( $old_db_version < 9330 )
	{
		if( db_col_exists( "{$tableprefix}posts", 'post_flags') )
		{
			echo 'Updating post notifications... ';
			$DB->query( "
				UPDATE {$tableprefix}posts
					 SET post_notifications_status = 'finished'
				 WHERE post_flags LIKE '%pingsdone%'" );
			db_drop_col( "{$tableprefix}posts", 'post_flags' );
			echo "OK.<br />\n";
		}
		set_upgrade_checkpoint( '9330' );
	}


	if( $old_db_version < 9340 )
	{
		echo 'Removing duplicate post link indexes... ';
		if( db_index_exists( 'T_links', 'link_item_ID' ) )
		{ // only drop, if it still exists (may have been removed manually)
			$DB->query( '
					ALTER TABLE T_links
						DROP INDEX link_item_ID
					' );
		}
		if( db_index_exists( 'T_links', 'link_dest_item_ID' ) )
		{ // only drop, if it still exists (may have been removed manually)
			$DB->query( '
					ALTER TABLE T_links
						DROP INDEX link_dest_item_ID
					' );
		}
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '9340' );
	}

	// ____________________________ 1.10: ____________________________

	if( $old_db_version < 9345 )
	{
		echo 'Updating post table... ';
		$DB->query( "ALTER TABLE {$tableprefix}posts CHANGE COLUMN post_content post_content MEDIUMTEXT NULL" );
		echo "OK.<br />\n";
		set_upgrade_checkpoint( '9345' );
	}

	if( $old_db_version < 9346 )
	{
		echo 'Updating prerendering table... ';
		$DB->query( "ALTER TABLE {$tableprefix}item__prerendering CHANGE COLUMN itpr_content_prerendered itpr_content_prerendered MEDIUMTEXT NULL" );
		echo "OK.<br />\n";
		set_upgrade_checkpoint( '9346' );
	}

	if( $old_db_version < 9348 )
	{
		echo 'Updating sessions table... ';
		$DB->query( 'ALTER TABLE T_sessions CHANGE COLUMN sess_data sess_data MEDIUMBLOB DEFAULT NULL' );
		echo "OK.<br />\n";
		set_upgrade_checkpoint( '9348' );
	}

	if( $old_db_version < 9350 )
	{
		echo 'Updating hitlog table... ';
		$DB->query( 'ALTER TABLE T_hitlog CHANGE COLUMN hit_referer_type hit_referer_type   ENUM(\'search\',\'blacklist\',\'spam\',\'referer\',\'direct\',\'self\',\'admin\') NOT NULL' );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '9350' );
	}


	// TODO: "If a user has permission to edit a blog, he should be able to put files in the media folder for that blog." - see http://forums.b2evolution.net/viewtopic.php?p=36417#36417
	/*
	// blueyed>> I've came up with the following, but it's too generic IMHO
	if( $old_db_version < 9300 )
	{
		echo 'Setting automatic media perms on blogs (members can upload)... ';
		$users = $DB->query( '
				UPDATE T_users
				   SET bloguser_perm_media_upload = 1
				 WHERE bloguser_ismember = 1' );
		echo "OK.<br />\n";
	}
	*/


	// ____________________________ 2.0: ____________________________

	if( $old_db_version < 9406 )
	{
		echo 'Updating chapter url names... ';
		$DB->query( '
			ALTER TABLE T_categories
				ADD COLUMN cat_urlname VARCHAR(255) NOT NULL' );

		// Create cat_urlname from cat_name:
		// TODO: Also use it for cafelog upgrade.
		load_funcs('locales/_charset.funcs.php');
		foreach( $DB->get_results('SELECT cat_ID, cat_name FROM T_categories') as $cat )
		{
			$cat_name = trim($cat->cat_name);
			if( strlen($cat_name) )
			{
				// TODO: dh> pass locale (useful for transliteration). From main blog?
				$cat_urlname = urltitle_validate('', $cat_name, $cat->cat_ID, false, 'cat_urlname', 'cat_ID', 'T_categories');
			}
			else
			{
				$cat_urlname = 'c'.$cat->cat_ID;
			}

			$DB->query( '
				UPDATE T_categories
					 SET cat_urlname = '.$DB->quote($cat_urlname).'
				 WHERE cat_ID = '.$cat->cat_ID );
		}

		$DB->query( '
			ALTER TABLE T_categories
				ADD UNIQUE cat_urlname ( cat_urlname )' );
		echo "OK.<br />\n";

		echo 'Updating Settings... ';
		$DB->query( '
      UPDATE T_settings
         SET set_value = "disabled"
       WHERE set_name = "links_extrapath"
         AND set_value = 0' );
		$DB->query( '
      UPDATE T_settings
         SET set_value = "ymd"
       WHERE set_name = "links_extrapath"
         AND set_value <> 0' );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '9406' );
	}


	if( $old_db_version < 9407 )
	{
		echo 'Moving general settings to blog settings... ';
		$DB->query( 'REPLACE INTO T_coll_settings( cset_coll_ID, cset_name, cset_value )
		             SELECT blog_ID, set_name, set_value
									 FROM T_blogs, T_settings
									WHERE set_name = "posts_per_page"
									   OR set_name = "what_to_show"
									   OR set_name = "archive_mode"' );
		$DB->query( 'DELETE FROM T_settings
									WHERE set_name = "posts_per_page"
									   OR set_name = "what_to_show"
									   OR set_name = "archive_mode"' );
		echo "OK.<br />\n";

		echo 'Upgrading blogs table... ';
		$query = "ALTER TABLE T_blogs
							DROP COLUMN blog_force_skin";
		$DB->query( $query );
		echo "OK.<br />\n";

		echo 'Upgrading groups table... ';
		$query = "ALTER TABLE T_groups
							CHANGE COLUMN grp_perm_files grp_perm_files enum('none','view','add','edit','all') NOT NULL default 'none'";
		$DB->query( $query );
		echo "OK.<br />\n";

		echo 'Upgrading files table... ';
		$query = "ALTER TABLE T_files
							CHANGE COLUMN file_root_type file_root_type enum('absolute','user','group','collection','skins') not null default 'absolute'";
		$DB->query( $query );
		echo "OK.<br />\n";

		echo 'Updating file types... ';
		// Only change this if it's close enough to a default install (non customized)
		$DB->query( "UPDATE T_filetypes
										SET ftyp_viewtype = 'text'
									WHERE ftyp_ID = 12
										AND ftyp_extensions = 'php php3 php4 php5 php6'
										AND ftyp_mimetype ='application/x-httpd-php'
										AND ftyp_icon = 'php.gif'" );
		echo "OK.<br />\n";

		echo 'Remove obsolete user settings... ';
		$DB->query( 'DELETE FROM '.$tableprefix.'usersettings
									WHERE uset_name = "plugins_disp_avail"' );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '9407' );
	}


	if( $old_db_version < 9408 )
	{
		echo 'Creating skins table... ';
		$DB->query( 'CREATE TABLE T_skins__skin (
              skin_ID      int(10) unsigned      NOT NULL auto_increment,
              skin_name    varchar(32)           NOT NULL,
              skin_type    enum(\'normal\',\'feed\') NOT NULL default \'normal\',
              skin_folder  varchar(32)           NOT NULL,
              PRIMARY KEY skin_ID (skin_ID),
              UNIQUE skin_folder( skin_folder ),
              KEY skin_name( skin_name )
            )' );
		echo "OK.<br />\n";

		echo 'Creating skin containers table... ';
		$DB->query( 'CREATE TABLE T_skins__container (
              sco_skin_ID   int(10) unsigned      NOT NULL,
              sco_name      varchar(40)           NOT NULL,
              PRIMARY KEY (sco_skin_ID, sco_name)
            )' );
		echo "OK.<br />\n";

		echo 'Creating widgets table... ';
		$DB->query( 'CREATE TABLE T_widget (
 						wi_ID					INT(10) UNSIGNED auto_increment,
						wi_coll_ID    INT(11) UNSIGNED NOT NULL,
						wi_sco_name   VARCHAR( 40 ) NOT NULL,
						wi_order			INT(10) UNSIGNED NOT NULL,
						wi_type       ENUM( \'core\', \'plugin\' ) NOT NULL DEFAULT \'core\',
						wi_code       VARCHAR(32) NOT NULL,
						wi_params     TEXT NULL,
						PRIMARY KEY ( wi_ID ),
						UNIQUE wi_order( wi_coll_ID, wi_sco_name, wi_order )
          )' );
		echo "OK.<br />\n";

		install_basic_skins();

		echo 'Updating blogs table... ';
		$DB->query( 'ALTER TABLE T_blogs
								 ALTER COLUMN blog_allowtrackbacks SET DEFAULT 0,
									DROP COLUMN blog_default_skin,
									 ADD COLUMN blog_owner_user_ID   int(11) unsigned NOT NULL default 1 AFTER blog_name,
									 ADD COLUMN blog_skin_ID INT(10) UNSIGNED NOT NULL DEFAULT 1 AFTER blog_allowusercss' );
		echo "OK.<br />\n";


		install_basic_widgets();

		set_upgrade_checkpoint( '9408' );
	}


	if( $old_db_version < 9409 )
	{
		// Upgrade the blog access types:
		echo 'Updating blogs access types... ';
		$DB->query( 'UPDATE T_blogs
										SET blog_access_type = "absolute"
									WHERE blog_siteurl LIKE "http://%"
									   OR blog_siteurl LIKE "https://%"' );

		$DB->query( 'UPDATE T_blogs
										SET blog_access_type = "relative",
												blog_siteurl = CONCAT( blog_siteurl, blog_stub )
									WHERE blog_access_type = "stub"' );

		db_drop_col( 'T_blogs', 'blog_stub' );

		echo "OK.<br />\n";


 		echo 'Updating columns... ';
		$DB->query( "ALTER TABLE T_groups CHANGE COLUMN grp_perm_stats grp_perm_stats enum('none','user','view','edit') NOT NULL default 'none'" );

		$DB->query( "ALTER TABLE T_coll_user_perms CHANGE COLUMN bloguser_perm_poststatuses bloguser_perm_poststatuses set('published','deprecated','protected','private','draft','redirected') NOT NULL default ''" );

		$DB->query( "ALTER TABLE T_coll_group_perms CHANGE COLUMN bloggroup_perm_poststatuses bloggroup_perm_poststatuses set('published','deprecated','protected','private','draft','redirected') NOT NULL default ''" );

		$DB->query( "ALTER TABLE {$tableprefix}posts CHANGE COLUMN post_status post_status enum('published','deprecated','protected','private','draft','redirected') NOT NULL default 'published'" );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '9409' );
	}


	if( $old_db_version < 9410 )
	{
 		echo 'Updating columns... ';
		$DB->query( "ALTER TABLE T_comments CHANGE COLUMN comment_status comment_status ENUM('published','deprecated','protected','private','draft','redirected') DEFAULT 'published' NOT NULL" );

		$DB->query( "ALTER TABLE T_sessions CHANGE COLUMN sess_data sess_data MEDIUMBLOB DEFAULT NULL" );

		$DB->query( "ALTER TABLE T_hitlog CHANGE COLUMN hit_referer_type hit_referer_type ENUM('search','blacklist','spam','referer','direct','self','admin') NOT NULL" );

		echo "OK.<br />\n";

		set_upgrade_checkpoint( '9410' );
	}


	if( $old_db_version < 9411 )
	{
		echo 'Adding default Post Types... ';
		$DB->query( "
			REPLACE INTO {$tableprefix}posttypes ( ptyp_ID, ptyp_name )
			VALUES ( 1000, 'Page' ),
						 ( 2000, 'Reserved' ),
						 ( 3000, 'Reserved' ),
						 ( 4000, 'Reserved' ),
						 ( 5000, 'Reserved' ) " );
		echo "OK.<br />\n";
		set_upgrade_checkpoint( '9411' );
	}


	if( $old_db_version < 9412 )
	{
		echo 'Adding field for post excerpts... ';
		$DB->query( "ALTER TABLE {$tableprefix}posts ADD COLUMN post_excerpt  text NULL AFTER post_content" );
		echo "OK.<br />\n";
		set_upgrade_checkpoint( '9412' );
	}

	if( $old_db_version < 9414 )
	{
		echo "Renaming tables...";
		$DB->query( "RENAME TABLE {$tableprefix}item__prerendering TO T_items__prerendering" );
		$DB->query( "RENAME TABLE {$tableprefix}poststatuses TO T_items__status" );
		$DB->query( "RENAME TABLE {$tableprefix}posttypes TO T_items__type" );
		$DB->query( "RENAME TABLE {$tableprefix}posts TO T_items__item" );
		echo "OK.<br />\n";

		echo "Creating Tag tables...";
		$DB->query( "CREATE TABLE T_items__tag (
		      tag_ID   int(11) unsigned not null AUTO_INCREMENT,
		      tag_name varchar(50) not null,
		      primary key (tag_ID),
		      UNIQUE tag_name( tag_name )
		    )" );

		$DB->query( "CREATE TABLE T_items__itemtag (
		      itag_itm_ID int(11) unsigned NOT NULL,
		      itag_tag_ID int(11) unsigned NOT NULL,
		      PRIMARY KEY (itag_itm_ID, itag_tag_ID),
		      UNIQUE tagitem ( itag_tag_ID, itag_itm_ID )
		    )" );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '9414' );
	}


	if( $old_db_version < 9416 )
	{
		echo "Updating blogs table...";
		$DB->query( "ALTER TABLE T_blogs
									ADD COLUMN blog_advanced_perms  TINYINT(1) NOT NULL default 0 AFTER blog_owner_user_ID,
									DROP COLUMN blog_staticfilename" );
		$DB->query( "UPDATE T_blogs
									  SET blog_advanced_perms = 1" );
		echo "OK.<br />\n";

		echo "Additionnal blog permissions...";
		$DB->query( "ALTER TABLE T_coll_user_perms
									ADD COLUMN bloguser_perm_admin tinyint NOT NULL default 0 AFTER bloguser_perm_properties,
									ADD COLUMN bloguser_perm_edit  ENUM('no','own','lt','le','all','redirected') NOT NULL default 'no' AFTER bloguser_perm_poststatuses" );

		$DB->query( "ALTER TABLE T_coll_group_perms
									ADD COLUMN bloggroup_perm_admin tinyint NOT NULL default 0 AFTER bloggroup_perm_properties,
									ADD COLUMN bloggroup_perm_edit  ENUM('no','own','lt','le','all','redirected') NOT NULL default 'no' AFTER bloggroup_perm_poststatuses" );

		// Preserve full admin perms:
		$DB->query( "UPDATE T_coll_user_perms
										SET bloguser_perm_admin = 1
									WHERE bloguser_perm_properties <> 0" );
		$DB->query( "UPDATE T_coll_group_perms
										SET bloggroup_perm_admin = 1
									WHERE bloggroup_perm_properties <> 0" );

		// Preserve full edit perms:
		$DB->query( "UPDATE T_coll_user_perms
										SET bloguser_perm_edit = 'all'" );
		$DB->query( "UPDATE T_coll_group_perms
										SET bloggroup_perm_edit = 'all'" );

		echo "OK.<br />\n";

		set_upgrade_checkpoint( '9416' );
	}


	if( $old_db_version < 9500 )
	{
		task_begin( 'Normalizing columns...' );
		$DB->query( 'ALTER TABLE T_blogs
										ALTER COLUMN blog_shortname SET DEFAULT \'\',
										ALTER COLUMN blog_tagline SET DEFAULT \'\',
										CHANGE COLUMN blog_description blog_description     varchar(250) NULL default \'\',
										ALTER COLUMN blog_siteurl SET DEFAULT \'\'' );
		task_end();

		task_begin( 'Normalizing dates...' );
		$DB->query( 'UPDATE T_users
										SET dateYMDhour = \'2000-01-01 00:00:00\'
									WHERE dateYMDhour = \'0000-00-00 00:00:00\'' );
		$DB->query( 'ALTER TABLE T_users
									MODIFY COLUMN dateYMDhour DATETIME NOT NULL DEFAULT \'2000-01-01 00:00:00\'' );
		$DB->query( 'UPDATE T_comments
										SET comment_date = \'2000-01-01 00:00:00\'
									WHERE comment_date = \'0000-00-00 00:00:00\'' );
		$DB->query( 'ALTER TABLE T_comments
									MODIFY COLUMN comment_date DATETIME NOT NULL DEFAULT \'2000-01-01 00:00:00\'' );
		task_end();

		task_begin( 'Normalizing cron jobs...' );
		$DB->query( 'UPDATE T_cron__task
										SET ctsk_controller = REPLACE(ctsk_controller, "cron/_", "cron/jobs/_" )
									WHERE ctsk_controller LIKE "cron/_%"' );
		task_end();

		task_begin( 'Extending comments table...' );
		$DB->query( 'ALTER TABLE T_comments
									ADD COLUMN comment_rating     TINYINT(1) NULL DEFAULT NULL AFTER comment_content,
									ADD COLUMN comment_featured   TINYINT(1) NOT NULL DEFAULT 0 AFTER comment_rating,
									ADD COLUMN comment_nofollow   TINYINT(1) NOT NULL DEFAULT 1 AFTER comment_featured;');
		task_end();

		set_upgrade_checkpoint( '9500' );
	}


	if( $old_db_version < 9600 )
	{	// 2.2.0
		task_begin( 'Creating global cache table...' );
		$DB->query( 'CREATE TABLE T_global__cache (
							      cach_name VARCHAR( 30 ) NOT NULL ,
							      cach_cache MEDIUMBLOB NULL ,
							      PRIMARY KEY ( cach_name )
							    )' );
		task_end();

		task_begin( 'Altering posts table...' );
		$DB->query( 'ALTER TABLE T_items__item
										MODIFY COLUMN post_datestart DATETIME NOT NULL DEFAULT \'2000-01-01 00:00:00\',
										MODIFY COLUMN post_datemodified DATETIME NOT NULL DEFAULT \'2000-01-01 00:00:00\',
										ADD COLUMN post_order    float NULL AFTER post_priority,
										ADD COLUMN post_featured tinyint(1) NOT NULL DEFAULT 0 AFTER post_order,
										ADD INDEX post_order( post_order )' );
		task_end();

		set_upgrade_checkpoint( '9600' );
	}


	if( $old_db_version < 9700 )
	{	// 2.3.2
	  echo 'Creating PodCast Post Type... ';
		$DB->query( "
			REPLACE INTO T_items__type ( ptyp_ID, ptyp_name )
			VALUES ( 2000, 'Podcast' )" );
		echo "OK.<br />\n";

		// 2.4.0
	  echo 'Adding additional group permissions... ';
		$DB->query( "
	      ALTER TABLE T_groups
					ADD COLUMN grp_perm_bypass_antispam         TINYINT(1)  NOT NULL DEFAULT 0        AFTER grp_perm_blogs,
					ADD COLUMN grp_perm_xhtmlvalidation         VARCHAR(10) NOT NULL default 'always' AFTER grp_perm_bypass_antispam,
					ADD COLUMN grp_perm_xhtmlvalidation_xmlrpc  VARCHAR(10) NOT NULL default 'always' AFTER grp_perm_xhtmlvalidation,
					ADD COLUMN grp_perm_xhtml_css_tweaks        TINYINT(1)  NOT NULL DEFAULT 0        AFTER grp_perm_xhtmlvalidation_xmlrpc,
      		ADD COLUMN grp_perm_xhtml_iframes           TINYINT(1)  NOT NULL DEFAULT 0        AFTER grp_perm_xhtml_css_tweaks,
      		ADD COLUMN grp_perm_xhtml_javascript        TINYINT(1)  NOT NULL DEFAULT 0        AFTER grp_perm_xhtml_iframes,
					ADD COLUMN grp_perm_xhtml_objects           TINYINT(1)  NOT NULL DEFAULT 0        AFTER grp_perm_xhtml_javascript " );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '9700' );
	}


	if( $old_db_version < 9800 )
	{	// 2.5.0
		echo 'Upgrading blogs table... ';
		db_drop_col( 'T_blogs', 'blog_commentsexpire' );
		echo "OK.<br />\n";

		echo 'Upgrading items table... ';
		$DB->query( "ALTER TABLE T_items__item
			CHANGE COLUMN post_urltitle post_urltitle VARCHAR(210) NULL DEFAULT NULL,
			CHANGE COLUMN post_order    post_order DOUBLE NULL,
			ADD COLUMN post_titletag  VARCHAR(255) NULL DEFAULT NULL AFTER post_urltitle,
			ADD COLUMN post_double1   DOUBLE NULL COMMENT 'Custom double value 1' AFTER post_priority,
			ADD COLUMN post_double2   DOUBLE NULL COMMENT 'Custom double value 2' AFTER post_double1,
			ADD COLUMN post_double3   DOUBLE NULL COMMENT 'Custom double value 3' AFTER post_double2,
			ADD COLUMN post_double4   DOUBLE NULL COMMENT 'Custom double value 4' AFTER post_double3,
			ADD COLUMN post_double5   DOUBLE NULL COMMENT 'Custom double value 5' AFTER post_double4,
			ADD COLUMN post_varchar1  VARCHAR(255) NULL COMMENT 'Custom varchar value 1' AFTER post_double5,
			ADD COLUMN post_varchar2  VARCHAR(255) NULL COMMENT 'Custom varchar value 2' AFTER post_varchar1,
			ADD COLUMN post_varchar3  VARCHAR(255) NULL COMMENT 'Custom varchar value 3' AFTER post_varchar2" );
		echo "OK.<br />\n";

 		echo 'Creating keyphrase table... ';
		$query = "CREATE TABLE T_track__keyphrase (
            keyp_ID      INT UNSIGNED NOT NULL AUTO_INCREMENT,
            keyp_phrase  VARCHAR( 255 ) NOT NULL,
            PRIMARY KEY        ( keyp_ID ),
            UNIQUE keyp_phrase ( keyp_phrase )
          )";
		$DB->query( $query );
		echo "OK.<br />\n";

 		echo 'Upgrading hitlog table... ';
 		flush();
		$query = "ALTER TABLE T_hitlog
			 CHANGE COLUMN hit_ID hit_ID              INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			 CHANGE COLUMN hit_datetime hit_datetime  DATETIME NOT NULL DEFAULT '2000-01-01 00:00:00',
			 ADD COLUMN hit_keyphrase_keyp_ID         INT UNSIGNED DEFAULT NULL AFTER hit_referer_dom_ID,
			 ADD INDEX hit_remote_addr ( hit_remote_addr ),
			 ADD INDEX hit_sess_ID        ( hit_sess_ID )";
		$DB->query( $query );
		echo "OK.<br />\n";

		echo 'Upgrading sessions table... ';
		$DB->query( "ALTER TABLE T_sessions
			ALTER COLUMN sess_lastseen SET DEFAULT '2000-01-01 00:00:00',
			ADD COLUMN sess_hitcount  INT(10) UNSIGNED NOT NULL DEFAULT 1 AFTER sess_key,
			ADD KEY sess_user_ID (sess_user_ID)" );
		echo "OK.<br />\n";

		echo 'Creating goal tracking table... ';
    $DB->query( "CREATE TABLE T_track__goal(
					  goal_ID int(10) unsigned NOT NULL auto_increment,
					  goal_name varchar(50) default NULL,
					  goal_key varchar(32) default NULL,
					  goal_redir_url varchar(255) default NULL,
					  goal_default_value double default NULL,
					  PRIMARY KEY (goal_ID),
					  UNIQUE KEY goal_key (goal_key)
          )" );

    $DB->query( "CREATE TABLE T_track__goalhit (
					  ghit_ID int(10) unsigned NOT NULL auto_increment,
					  ghit_goal_ID    int(10) unsigned NOT NULL,
					  ghit_hit_ID     int(10) unsigned NOT NULL,
					  ghit_params     TEXT default NULL,
					  PRIMARY KEY  (ghit_ID),
					  KEY ghit_goal_ID (ghit_goal_ID),
					  KEY ghit_hit_ID (ghit_hit_ID)
         )" );
		echo "OK.<br />\n";

		set_upgrade_checkpoint( '9800' );
	}


	if( $old_db_version < 9900 )
	{	// 3.0 part 1
		task_begin( 'Updating keyphrases in hitlog table... ' );
		flush();
		load_class( 'sessions/model/_hit.class.php', 'Hit' );
		$sql = 'SELECT SQL_NO_CACHE hit_ID, hit_referer
  		          FROM T_hitlog
   		         WHERE hit_referer_type = "search"
		           AND hit_keyphrase_keyp_ID IS NULL'; // this line just in case we crashed in the middle, so we restart where we stopped
		$rows = $DB->get_results( $sql, OBJECT, 'get all search hits' );
		foreach( $rows as $row )
		{
			$params = Hit::extract_params_from_referer( $row->hit_referer );
			if( empty( $params['keyphrase'] ) )
			{
				continue;
			}

			$DB->begin();

			$sql = 'SELECT keyp_ID
			          FROM T_track__keyphrase
			         WHERE keyp_phrase = '.$DB->quote($params['keyphrase']);
			$keyp_ID = $DB->get_var( $sql, 0, 0, 'Get keyphrase ID' );

			if( empty( $keyp_ID ) )
			{
				$sql = 'INSERT INTO T_track__keyphrase( keyp_phrase )
				        VALUES ('.$DB->quote($params['keyphrase']).')';
				$DB->query( $sql, 'Add new keyphrase' );
				$keyp_ID = $DB->insert_id;
			}

			$DB->query( 'UPDATE T_hitlog
			                SET hit_keyphrase_keyp_ID = '.$keyp_ID.'
			              WHERE hit_ID = '.$row->hit_ID, 'Update hit' );

			$DB->commit();
			echo ". \n";
		}
		task_end();

		task_begin( 'Upgrading widgets table... ' );
		$DB->query( "ALTER TABLE T_widget
			CHANGE COLUMN wi_order wi_order INT(10) NOT NULL" );
		task_end();

		task_begin( 'Upgrading Files table... ' );
		$DB->query( "ALTER TABLE T_files
								CHANGE COLUMN file_root_type file_root_type enum('absolute','user','collection','shared','skins') not null default 'absolute'" );
		task_end();

		set_upgrade_checkpoint( '9900' );
	}

	if( $old_db_version < 9910 )
	{	// 3.0 part 2

		task_begin( 'Upgrading Blogs table... ' );
		$DB->query( "ALTER TABLE T_blogs CHANGE COLUMN blog_name blog_name varchar(255) NOT NULL default ''" );
		task_end();

		task_begin( 'Adding new Post Types...' );
		$DB->query( "
			REPLACE INTO T_items__type( ptyp_ID, ptyp_name )
			VALUES ( 1500, 'Intro-Main' ),
						 ( 1520, 'Intro-Cat' ),
						 ( 1530, 'Intro-Tag' ),
						 ( 1570, 'Intro-Sub' ),
						 ( 1600, 'Intro-All' ) " );
		task_end();

		task_begin( 'Updating User table' );
		$DB->query( "ALTER TABLE T_users
									ADD COLUMN user_avatar_file_ID int(10) unsigned default NULL AFTER user_validated" );
		task_end();

		task_begin( 'Creating table for User field definitions' );
		$DB->query( "CREATE TABLE T_users__fielddefs (
				ufdf_ID int(10) unsigned NOT NULL,
				ufdf_type char(8) NOT NULL,
				ufdf_name varchar(255) collate latin1_general_ci NOT NULL,
				PRIMARY KEY  (ufdf_ID)
			)" );
		task_end();

		task_begin( 'Creating default field definitions...' );
		$DB->query( "
	    INSERT INTO T_users__fielddefs (ufdf_ID, ufdf_type, ufdf_name)
			 VALUES ( 10000, 'email',    'MSN/Live IM'),
							( 10100, 'word',     'Yahoo IM'),
							( 10200, 'word',     'AOL AIM'),
							( 10300, 'number',   'ICQ ID'),
							( 40000, 'phone',    'Skype'),
							( 50000, 'phone',    'Main phone'),
							( 50100, 'phone',    'Cell phone'),
							( 50200, 'phone',    'Office phone'),
							( 50300, 'phone',    'Home phone'),
							( 60000, 'phone',    'Office FAX'),
							( 60100, 'phone',    'Home FAX'),
							(100000, 'url',      'Website'),
							(100100, 'url',      'Blog'),
							(110000, 'url',      'Linkedin'),
							(120000, 'url',      'Twitter'),
							(130100, 'url',      'Facebook'),
							(130200, 'url',      'Myspace'),
							(140000, 'url',      'Flickr'),
							(150000, 'url',      'YouTube'),
							(160000, 'url',      'Digg'),
							(160100, 'url',      'StumbleUpon'),
							(200000, 'text',     'Role'),
							(200100, 'text',     'Company/Org.'),
							(200200, 'text',     'Division'),
							(211000, 'text',     'VAT ID'),
							(300000, 'text',     'Main address'),
							(300300, 'text',     'Home address');" );
		task_end();

		task_begin( 'Creating table for User fields...' );
		$DB->query( "CREATE TABLE T_users__fields (
				uf_ID      int(10) unsigned NOT NULL auto_increment,
			  uf_user_ID int(10) unsigned NOT NULL,
			  uf_ufdf_ID int(10) unsigned NOT NULL,
			  uf_varchar varchar(255) NOT NULL,
			  PRIMARY KEY (uf_ID)
			)" );
		task_end();

		set_upgrade_checkpoint( '9910' );
	}

	if( $old_db_version < 9920 )
	{	// 3.1
		task_begin( 'Upgrading Posts table... ' );
		// This is for old posts that may have a post type of NULL which should never happen. ptyp 1 is for regular posts
		$DB->query( "UPDATE T_items__item
										SET post_ptyp_ID = 1
									WHERE post_ptyp_ID IS NULL" );
		$DB->query( "ALTER TABLE T_items__item
							CHANGE COLUMN post_ptyp_ID post_ptyp_ID int(10) unsigned NOT NULL DEFAULT 1" );
		task_end();

		task_begin( 'Upgrading Categories table... ' );
		$DB->query( "ALTER TABLE T_categories
			CHANGE COLUMN cat_name cat_name varchar(255) NOT NULL,
			CHANGE COLUMN cat_description cat_description varchar(255) NULL DEFAULT NULL" );
		db_add_col( 'T_categories', 'cat_order', 'int(11) NULL DEFAULT NULL AFTER cat_description' );
		db_add_index( 'T_categories', 'cat_order', 'cat_order' );

		$DB->query( "UPDATE T_categories
					SET cat_order = cat_ID" );
		task_end();

		task_begin( 'Upgrading widgets table... ' );
		db_add_col( 'T_widget', 'wi_enabled', 'tinyint(1) NOT NULL DEFAULT 1 AFTER wi_order' );
		task_end();
	}
	if( $old_db_version < 9930 )
	{	// 3.1 continued
		task_begin( 'Updating item types...' );
		$DB->query( "
			REPLACE INTO T_items__type ( ptyp_ID, ptyp_name )
			VALUES ( 3000, 'Sidebar link' )" );
		echo "OK.<br />\n";
		task_end();

		task_begin( 'Updating items table...' );
		$DB->query( "ALTER TABLE T_items__item ENGINE=innodb" );	// fp> hum... this originally was a test :)
		task_end();

		task_begin( 'Creating versions table...' );
		$DB->query( "CREATE TABLE T_items__version (
	            iver_itm_ID        INT UNSIGNED NOT NULL ,
	            iver_edit_user_ID  INT UNSIGNED NOT NULL ,
	            iver_edit_datetime DATETIME NOT NULL ,
	            iver_status        ENUM('published','deprecated','protected','private','draft','redirected') NULL ,
	            iver_title         TEXT NULL ,
	            iver_content       MEDIUMTEXT NULL ,
	            INDEX iver_itm_ID ( iver_itm_ID )
	            ) ENGINE = innodb" );
		task_end();

		task_begin( 'Updating group permissions...' );
		$DB->query( "UPDATE T_groups
										SET grp_perm_xhtml_css_tweaks = 1
									WHERE grp_ID <= 3" );
		task_end();

		set_upgrade_checkpoint( '9930' );
	}

	if( $old_db_version < 9940 )
	{	// 3.2
		task_begin( 'Updating hitlog table...' );
		$DB->query( "ALTER TABLE T_hitlog ADD COLUMN hit_serprank INT UNSIGNED DEFAULT NULL AFTER hit_keyphrase_keyp_ID" );
		task_end();

		task_begin( 'Updating versions table...' );
		$DB->query( "ALTER TABLE T_items__version
								CHANGE COLUMN iver_edit_user_ID iver_edit_user_ID  INT UNSIGNED NULL" );
		task_end();
	}

	if( $old_db_version < 9950 )
	{	// 3.3
		task_begin( 'Altering Blogs table... ' );
		$DB->query( "ALTER TABLE T_blogs CHANGE COLUMN blog_shortname blog_shortname varchar(255) default ''" );
		task_end();

		task_begin( 'Altering default dates... ' );
		$DB->query( "ALTER TABLE T_links
      ALTER COLUMN link_datecreated SET DEFAULT '2000-01-01 00:00:00',
      ALTER COLUMN link_datemodified SET DEFAULT '2000-01-01 00:00:00'" );
		$DB->query( "ALTER TABLE T_cron__task
      ALTER COLUMN ctsk_start_datetime SET DEFAULT '2000-01-01 00:00:00'" );
		$DB->query( "ALTER TABLE T_cron__log
      ALTER COLUMN clog_realstart_datetime SET DEFAULT '2000-01-01 00:00:00'" );
		task_end();

 		task_begin( 'Altering Items table... ' );
		$DB->query( "ALTER TABLE T_items__item
			ADD COLUMN post_metadesc VARCHAR(255) NULL DEFAULT NULL AFTER post_titletag,
			ADD COLUMN post_metakeywords VARCHAR(255) NULL DEFAULT NULL AFTER post_metadesc,
			ADD COLUMN post_editor_code VARCHAR(32) NULL COMMENT 'Plugin code of the editor used to edit this post' AFTER post_varchar3" );
		task_end();

		task_begin( 'Forcing AutoP posts to html editor...' );
		$DB->query( 'UPDATE T_items__item
											SET post_editor_code = "html"
										WHERE post_renderers = "default"
											 OR post_renderers LIKE "%b2WPAutP%"' );
		task_end();

		set_upgrade_checkpoint( '9950' );
	}

	if( $old_db_version < 9960 )
	{	// 3.3

		echo "Renaming tables...";
		$DB->save_error_state();
		$DB->halt_on_error = false;
		$DB->show_errors = false;
		$DB->query( "ALTER TABLE {$tableprefix}users_fields RENAME TO T_users__fields" );
		$DB->restore_error_state();
		echo "OK.<br />\n";

		// fp> The following is more tricky to do with CHARACTER SET. During upgrade, we don't know what the admin actually wants.
		task_begin( 'Making sure all tables use desired storage ENGINE as specified in the b2evo schema...' );
		foreach( $schema_queries as $table_name=>$table_def )
		{
			if( $DB->query( 'SHOW TABLES LIKE \''.$table_name.'\'' )
				&& preg_match( '/\sENGINE\s*=\s*([a-z]+)/is', $table_def[1], $matches ) )
			{	// If the table exists and has an ENGINE definition:
				echo $table_name.':'.$matches[1].'<br />';
				$DB->query( "ALTER TABLE $table_name ENGINE = ".$matches[1] );
			}
		}
		task_end();

		set_upgrade_checkpoint( '9960' );
	}

	if( $old_db_version < 9970 )
	{	// 4.0 part 1

		// For create_default_currencies() and create_default_countries():
		require_once dirname(__FILE__).'/_functions_create.php';

		task_begin( 'Creating table for default currencies... ' );
		$DB->query( 'CREATE TABLE '.$tableprefix.'currency (
				curr_ID int(10) unsigned NOT NULL auto_increment,
				curr_code char(3) NOT NULL,
				curr_shortcut varchar(30) NOT NULL,
				curr_name varchar(40) NOT NULL,
				PRIMARY KEY curr_ID (curr_ID),
				UNIQUE curr_code (curr_code)
			) ENGINE = innodb' );
		task_end();

		create_default_currencies( $tableprefix.'currency' );

		task_begin( 'Creating table for default countries... ' );
		$DB->query( 'CREATE TABLE '.$tableprefix.'country (
				ctry_ID int(10) unsigned NOT NULL auto_increment,
				ctry_code char(2) NOT NULL,
				ctry_name varchar(40) NOT NULL,
				ctry_curr_ID int(10) unsigned,
				PRIMARY KEY ctry_ID (ctry_ID),
				UNIQUE ctry_code (ctry_code)
			) ENGINE = innodb' );
		task_end();

		create_default_countries( $tableprefix.'country' );

		task_begin( 'Upgrading user permissions table... ' );
		$DB->query( "ALTER TABLE T_coll_user_perms
			ADD COLUMN bloguser_perm_page		tinyint NOT NULL default 0 AFTER bloguser_perm_media_change,
			ADD COLUMN bloguser_perm_intro		tinyint NOT NULL default 0 AFTER bloguser_perm_page,
			ADD COLUMN bloguser_perm_podcast	tinyint NOT NULL default 0 AFTER bloguser_perm_intro,
			ADD COLUMN bloguser_perm_sidebar	tinyint NOT NULL default 0 AFTER bloguser_perm_podcast" );
		task_end();

		task_begin( 'Upgrading group permissions table... ' );
		$DB->query( "ALTER TABLE T_coll_group_perms
			ADD COLUMN bloggroup_perm_page		tinyint NOT NULL default 0 AFTER bloggroup_perm_media_change,
			ADD COLUMN bloggroup_perm_intro		tinyint NOT NULL default 0 AFTER bloggroup_perm_page,
			ADD COLUMN bloggroup_perm_podcast	tinyint NOT NULL default 0 AFTER bloggroup_perm_intro,
			ADD COLUMN bloggroup_perm_sidebar	tinyint NOT NULL default 0 AFTER bloggroup_perm_podcast" );
		task_end();

		task_begin( 'Upgrading users table... ' );
		$DB->query( "ALTER TABLE T_users
			ADD COLUMN user_ctry_ID int(10) unsigned NULL AFTER user_avatar_file_ID" );
		task_end();

		// Creating tables for messaging module

		task_begin( 'Creating table for message threads... ' );
		$DB->query( "CREATE TABLE T_messaging__thread (
			thrd_ID int(10) unsigned NOT NULL auto_increment,
			thrd_title varchar(255) NOT NULL,
			thrd_datemodified datetime NOT NULL,
			PRIMARY KEY thrd_ID (thrd_ID)
		) ENGINE = innodb" );
		task_end();

		task_begin( 'Creating table for messagee... ' );
		$DB->query( "CREATE TABLE T_messaging__message (
			msg_ID int(10) unsigned NOT NULL auto_increment,
			msg_author_user_ID int(10) unsigned NOT NULL,
			msg_datetime datetime NOT NULL,
			msg_thread_ID int(10) unsigned NOT NULL,
			msg_text text NULL,
			PRIMARY KEY msg_ID (msg_ID)
		) ENGINE = innodb" );
		task_end();

		task_begin( 'Creating table for message thread statuses... ' );
		$DB->query( "CREATE TABLE T_messaging__threadstatus (
			tsta_thread_ID int(10) unsigned NOT NULL,
			tsta_user_ID int(10) unsigned NOT NULL,
			tsta_first_unread_msg_ID int(10) unsigned NULL,
			INDEX(tsta_user_ID)
		) ENGINE = innodb" );
		task_end();

		task_begin( 'Creating table for messaging contacts... ' );
		$DB->query( "CREATE TABLE T_messaging__contact (
			mct_from_user_ID int(10) unsigned NOT NULL,
			mct_to_user_ID int(10) unsigned NOT NULL,
			mct_blocked tinyint(1) default 0,
			mct_last_contact_datetime datetime NOT NULL,
			PRIMARY KEY mct_PK (mct_from_user_ID, mct_to_user_ID)
		) ENGINE = innodb" );
		task_end();

		task_begin( 'Upgrading skins table... ' );
		$DB->query( "ALTER TABLE T_skins__skin
						MODIFY skin_type enum('normal','feed','sitemap') NOT NULL default 'normal'" );
		task_end();

		task_begin( 'Setting skin type of sitemap skin to "sitemap"... ' );
		$DB->query( "UPDATE T_skins__skin
						SET skin_type = 'sitemap'
						WHERE skin_folder = '_sitemap'" );
		task_end();

		// Creating table for pluggable permissions

		// This table gets created during upgrade to v0.8.9 at checkpoint 8050
		task_begin( 'Creating table for Group Settings... ' );
		$DB->query( "CREATE TABLE IF NOT EXISTS T_groups__groupsettings (
			gset_grp_ID INT(11) UNSIGNED NOT NULL,
			gset_name VARCHAR(30) NOT NULL,
			gset_value VARCHAR(255) NULL,
			PRIMARY KEY (gset_grp_ID, gset_name)
		) ENGINE = innodb" );
		task_end();

		// Rename T_usersettings table to T_users__usersettings
		task_begin( 'Rename T_usersettings table to T_users__usersettings... ' );
		$DB->query( 'ALTER TABLE '.$tableprefix.'usersettings RENAME TO T_users__usersettings' );
		task_end();

		set_upgrade_checkpoint( '9970' );
	}


	if( $old_db_version < 9980 )
	{	// 4.0 part 2

		task_begin( 'Upgrading posts... ' );
		$DB->query( '
			UPDATE T_items__item
			   SET post_datestart = FROM_UNIXTIME( FLOOR(UNIX_TIMESTAMP(post_datestart)/60)*60 )
			 WHERE post_datestart > NOW()' );
		db_add_col( 'T_items__item', 'post_excerpt_autogenerated', 'TINYINT NULL DEFAULT NULL AFTER post_excerpt' );
		db_add_col( 'T_items__item', 'post_dateset', 'tinyint(1) NOT NULL DEFAULT 1 AFTER post_assigned_user_ID' );
		task_end();

		task_begin( 'Upgrading countries... ' );
		db_add_col( $tableprefix.'country', 'ctry_enabled', 'tinyint(1) NOT NULL DEFAULT 1 AFTER ctry_curr_ID' );
		task_end();


		task_begin( 'Upgrading links... ' );

		// Add link_position. Temporary allow NULL, set compatibility default, then do not allow NULL.
		// TODO: dh> actually, using "teaser" for the first link and "aftermore" for the rest would make more sense (and "aftermore" should get displayed with "no-more" posts anyway).
		//           Opinions? Could be heavy to transform this though..
		// fp> no, don't change past posts unexpectedly.
		db_add_col( 'T_links', 'link_position', "varchar(10) NULL AFTER link_title" );
		$DB->query( "UPDATE T_links SET link_position = 'teaser' WHERE link_position IS NULL" );
		db_add_col( 'T_links', 'link_position', "varchar(10) NOT NULL AFTER link_title" ); // change to NOT NULL

		// Add link_order. Temporary allow NULL, use order from ID, then do not allow NULL and add UNIQUE index.
		db_add_col( 'T_links', 'link_order', 'int(11) unsigned NULL AFTER link_position' );
		$DB->query( "UPDATE T_links SET link_order = link_ID WHERE link_order IS NULL" );
		db_add_col( 'T_links', 'link_order', 'int(11) unsigned NOT NULL AFTER link_position' ); // change to NOT NULL
		db_add_index( 'T_links', 'link_itm_ID_order', 'link_itm_ID, link_order', 'UNIQUE' );

		task_end();

		task_begin( 'Upgrading sessions... ' );
		$DB->query( "ALTER TABLE T_sessions CHANGE COLUMN sess_ipaddress sess_ipaddress VARCHAR(39) NOT NULL DEFAULT ''" );
		task_end();

		set_upgrade_checkpoint( '9980' );
	}

	if( $old_db_version < 9990 )
	{	// 4.0 part 3

		task_begin( 'Upgrading hitlog... ' );

		db_add_col( 'T_hitlog', 'hit_agent_type', "ENUM('rss','robot','browser','unknown') DEFAULT 'unknown' NOT NULL AFTER hit_remote_addr" );

		if( db_col_exists('T_hitlog', 'hit_agnt_ID') )
		{
			$DB->query( 'UPDATE T_hitlog, '.$tableprefix.'useragents
			                SET hit_agent_type = agnt_type
			              WHERE hit_agnt_ID = agnt_ID
			                AND agnt_type <> "unknown"' ); // We already have the unknown as default
			db_drop_col( 'T_hitlog', 'hit_agnt_ID' );
		}
		$DB->query( 'DROP TABLE IF EXISTS '.$tableprefix.'useragents' );

		task_end();

		set_upgrade_checkpoint( '9990' );
	}

	if( $old_db_version < 10000 )
	{	// 4.0 part 4
		// Integrate comment_secret
		task_begin( 'Extending Comment table... ' );
		db_add_col( 'T_comments', 'comment_secret', 'varchar(32) NULL default NULL' );
		task_end();

		// Create T_slug table and, Insert all slugs from T_items
		task_begin( 'Create Slugs table... ' );
		$DB->query( 'CREATE TABLE IF NOT EXISTS T_slug (
						slug_ID int(10) unsigned NOT NULL auto_increment,
						slug_title varchar(255) NOT NULL COLLATE ascii_bin,
						slug_type char(6) NOT NULL DEFAULT "item",
						slug_itm_ID int(11) unsigned,
						PRIMARY KEY slug_ID (slug_ID),
						UNIQUE	slug_title (slug_title)
					) ENGINE = innodb' );
		task_end();

		task_begin( 'Making sure all posts have a slug...' );
		// Get posts with empty urltitle:
		$sql = 'SELECT post_ID, post_title
				      FROM T_items__item
				     WHERE post_urltitle IS NULL OR post_urltitle = ""';
		$rows = $DB->get_results( $sql, OBJECT, 'Get posts with empty urltitle' );
		// Create URL titles when non existent:
		foreach( $rows as $row )
		{
			// TODO: dh> pass locale (useful for transliteration).
			$DB->query( 'UPDATE T_items__item
				              SET post_urltitle = "'.urltitle_validate( '', $row->post_title, 0 ).'"
		                WHERE post_ID = '.$row->post_ID, 'Set posts urltitle' );
		}
		task_end();

		task_begin( 'Populating Slugs table... ' );
		$DB->query( 'REPLACE INTO T_slug( slug_title, slug_type, slug_itm_ID)
		              SELECT post_urltitle, "item", post_ID
							      FROM T_items__item' );
		task_end();

		task_begin( 'Add canonical and tiny slug IDs to post table...' );
		// modify post_urltitle column -> Not allow NULL value
		db_add_col( 'T_items__item', 'post_urltitle', 'VARCHAR(210) NOT NULL' );
		db_add_col( 'T_items__item', 'post_canonical_slug_ID', 'int(10) unsigned NULL default NULL after post_urltitle' );
		db_add_col( 'T_items__item', 'post_tiny_slug_ID', 'int(10) unsigned NULL default NULL after post_canonical_slug_ID' );
		task_end();

		task_begin( 'Upgrading posts...' );
		$DB->query( 'UPDATE T_items__item, T_slug
			              SET post_canonical_slug_ID = slug_ID
			            WHERE CONVERT( post_urltitle USING ASCII ) COLLATE ascii_bin = slug_title' );
		task_end();

		task_begin( 'Adding "help" slug...' );
		if( db_key_exists( 'T_slug', 'slug_title', '"help"' ) )
		{
			echo '<strong>Warning: "help" slug already exists!</strong><br /> ';
		}
		else
		{
			$DB->query( 'INSERT INTO T_slug( slug_title, slug_type )
			             VALUES( "help", "help" )', 'Add "help" slug' );
			task_end();
		}

		// fp> Next time we should use pluggable permissions instead.
		task_begin( 'Updgrading groups: Giving Administrators Group edit perms on slugs...' );
		db_add_col( 'T_groups', 'grp_perm_slugs', "enum('none','view','edit') NOT NULL default 'none'" );
		$DB->query( 'UPDATE T_groups
		             SET grp_perm_slugs = "edit"
		             WHERE grp_ID = 1' );
		task_end();

		task_begin( 'Upgrading settings table... ');
		$DB->query( 'UPDATE T_settings
		                SET set_value = 1
		              WHERE set_name = "fm_enable_roots_user"
		                    AND set_value = 0' );
		task_end();

		// New perms for comment moderation depending on status:
		task_begin( 'Upgrading Blog-User permissions...' );
		db_add_col( 'T_coll_user_perms', 'bloguser_perm_draft_cmts', 'tinyint NOT NULL default 0 AFTER bloguser_perm_comments' );
		db_add_col( 'T_coll_user_perms', 'bloguser_perm_publ_cmts', 'tinyint NOT NULL default 0 AFTER bloguser_perm_comments' );
		db_add_col( 'T_coll_user_perms', 'bloguser_perm_depr_cmts', 'tinyint NOT NULL default 0 AFTER bloguser_perm_comments' );

		if( db_col_exists( 'T_coll_user_perms', 'bloguser_perm_comments' ) )
		{ // if user had perm_comments he now gets all 3 new perms also:
			$DB->query( 'UPDATE T_coll_user_perms
						SET bloguser_perm_draft_cmts = bloguser_perm_comments,
							bloguser_perm_publ_cmts = bloguser_perm_comments,
							bloguser_perm_depr_cmts = bloguser_perm_comments');
			db_drop_col( 'T_coll_user_perms', 'bloguser_perm_comments' );
		}
		task_end();

		task_begin( 'Upgrading Blog-Group permissions...' );
		db_add_col( 'T_coll_group_perms', 'bloggroup_perm_draft_cmts', 'tinyint NOT NULL default 0 AFTER bloggroup_perm_comments' );
		db_add_col( 'T_coll_group_perms', 'bloggroup_perm_publ_cmts', 'tinyint NOT NULL default 0 AFTER bloggroup_perm_comments' );
		db_add_col( 'T_coll_group_perms', 'bloggroup_perm_depr_cmts', 'tinyint NOT NULL default 0 AFTER bloggroup_perm_comments' );

		if( db_col_exists( 'T_coll_group_perms', 'bloggroup_perm_comments' ) )
		{ // if group had perm_comments he now gets all 3 new perms also:
			$DB->query( 'UPDATE T_coll_group_perms
						SET bloggroup_perm_draft_cmts = bloggroup_perm_comments,
							bloggroup_perm_publ_cmts = bloggroup_perm_comments,
							bloggroup_perm_depr_cmts = bloggroup_perm_comments');
			db_drop_col( 'T_coll_group_perms', 'bloggroup_perm_comments' );
		}
		task_end();

		task_begin( 'Upgrading messaging permissions...' );
		$DB->query( 'ALTER TABLE T_users ALTER COLUMN user_allow_msgform SET DEFAULT "2"' );
		$DB->query( 'UPDATE T_users
					SET user_allow_msgform = 3
					WHERE user_allow_msgform = 1');
		task_end();

		task_begin( 'Upgrading currency table...' );
		$DB->query( 'ALTER TABLE '.$tableprefix.'currency ADD COLUMN curr_enabled tinyint(1) NOT NULL DEFAULT 1 AFTER curr_name' );
		task_end();

		task_begin( 'Upgrading default blog access type for new blogs...' );
		$DB->query( 'ALTER TABLE T_blogs ALTER COLUMN blog_access_type SET DEFAULT "extrapath"' );
		task_end();

		task_begin( 'Upgrading tags table...' );
		$DB->query( 'ALTER TABLE T_items__tag CHANGE COLUMN tag_name tag_name varbinary(50) not null' );
		task_end();

		// fp> I don't understand why we need to carry this out "again" but I observed the installer barking on
		// this setting missing when upgrading from older 2.x versions. I figured it would be no big deal to do it twice...
		task_begin( 'Makin sure usersettings table is InnoDB...' );
		$DB->query( 'ALTER TABLE T_users__usersettings ENGINE=innodb' );
		task_end();

		set_upgrade_checkpoint( '10000' );
	}

	if( $old_db_version < 10100 )
	{	// 4.1
		task_begin( 'Convert group permissions to pluggable permissions...' );
		// asimo>This delete query needs just in case if this version of b2evo was used, before upgrade process call
		$DB->query( 'DELETE FROM T_groups__groupsettings
						WHERE gset_name = "perm_files" OR gset_name = "perm_options" OR gset_name = "perm_templates"' );
		// Get current permission values from groups table
		$sql = 'SELECT grp_ID, grp_perm_spamblacklist, grp_perm_slugs, grp_perm_files, grp_perm_options, grp_perm_templates
				      FROM T_groups';
		$rows = $DB->get_results( $sql, OBJECT, 'Get groups converted permissions' );
		// Insert values into groupsettings table
		foreach( $rows as $row )
		{	// "IGNORE" is needed if we already created T_groups__groupsettings during upgrade to v0.8.9 at checkpoint 8050
			$DB->query( 'INSERT IGNORE INTO T_groups__groupsettings( gset_grp_ID, gset_name, gset_value )
							VALUES( '.$row->grp_ID.', "perm_spamblacklist", "'.$row->grp_perm_spamblacklist.'" ),
								( '.$row->grp_ID.', "perm_slugs", "'.$row->grp_perm_slugs.'" ),
								( '.$row->grp_ID.', "perm_files", "'.$row->grp_perm_files.'" ),
								( '.$row->grp_ID.', "perm_options", "'.$row->grp_perm_options.'" ),
								( '.$row->grp_ID.', "perm_templates", "'.$row->grp_perm_templates.'" )' );
		}

		// Drop all converted permissin colums from groups table
		db_drop_col( 'T_groups', 'grp_perm_spamblacklist' );
		db_drop_col( 'T_groups', 'grp_perm_slugs' );
		db_drop_col( 'T_groups', 'grp_perm_files' );
		db_drop_col( 'T_groups', 'grp_perm_options' );
		db_drop_col( 'T_groups', 'grp_perm_templates' );
		task_end();

		task_begin( 'Upgrading users table, adding user gender...' );
		db_add_col( 'T_users', 'user_gender', 'char(1) NULL DEFAULT NULL AFTER user_showonline' );
		task_end();

		task_begin( 'Upgrading edit timpestamp blog-user permission...' );
		db_add_col( 'T_coll_user_perms', 'bloguser_perm_edit_ts', 'tinyint NOT NULL default 0 AFTER bloguser_perm_delpost' );
		$DB->query( 'UPDATE T_coll_user_perms, T_users
							SET bloguser_perm_edit_ts = 1
							WHERE bloguser_user_ID = user_ID  AND user_level > 4' );
		task_end();

		task_begin( 'Upgrading edit timpestamp blog-group permission...' );
		db_add_col( 'T_coll_group_perms', 'bloggroup_perm_edit_ts', 'tinyint NOT NULL default 0 AFTER bloggroup_perm_delpost' );
		$DB->query( 'UPDATE T_coll_group_perms
							SET bloggroup_perm_edit_ts = 1
							WHERE bloggroup_group_ID = 1' );
		task_end();

		task_begin( 'Upgrading comments table, add trash status...' );
		$DB->query( "ALTER TABLE T_comments MODIFY COLUMN comment_status ENUM('published','deprecated','draft', 'trash') DEFAULT 'published' NOT NULL");
		task_end();

		task_begin( 'Upgrading groups admin access permission...' );
		$sql = 'SELECT grp_ID, grp_perm_admin
					FROM T_groups';
		$rows = $DB->get_results( $sql, OBJECT, 'Get groups admin perms' );
		foreach( $rows as $row )
		{
			switch( $row->grp_perm_admin )
			{
				case 'visible':
					$value = 'normal';
					break;
				case 'hidden':
					$value = 'restricted';
					break;
				default:
					$value = 'none';
			}
			// "IGNORE" is needed if we already created T_groups__groupsettings during upgrade to v0.8.9 at checkpoint 8050
			$DB->query( 'INSERT IGNORE INTO T_groups__groupsettings( gset_grp_ID, gset_name, gset_value )
							VALUES( '.$row->grp_ID.', "perm_admin", "'.$value.'" )' );
		}
		db_drop_col( 'T_groups', 'grp_perm_admin' );
		task_end();

		task_begin( 'Upgrading users table, add users source...' );
		db_add_col( 'T_users', 'user_source', 'varchar(30) NULL' );
		task_end();

		task_begin( 'Upgrading blogs table: more granularity for comment allowing...' );
		$DB->query( 'INSERT INTO T_coll_settings( cset_coll_ID, cset_name, cset_value )
						SELECT blog_ID, "allow_comments", "never"
							FROM T_blogs
							WHERE blog_allowcomments = "never"' );
		db_drop_col( 'T_blogs', 'blog_allowcomments' );
		task_end();

		task_begin( 'Upgrading blogs table: allow_rating fields...' );
		$DB->query( 'UPDATE T_coll_settings
						SET cset_value = "any"
						WHERE cset_value = "always" AND cset_name = "allow_rating"' );
		task_end();

		task_begin( 'Upgrading links table, add link_cmt_ID...' );
		$DB->query( 'ALTER TABLE T_links
						MODIFY COLUMN link_itm_ID int(11) unsigned NULL,
						MODIFY COLUMN link_creator_user_ID int(11) unsigned NULL,
						MODIFY COLUMN link_lastedit_user_ID int(11) unsigned NULL,
						ADD COLUMN link_cmt_ID int(11) unsigned NULL COMMENT "Used for linking files to comments (comment attachments)" AFTER link_itm_ID,
						ADD INDEX link_cmt_ID ( link_cmt_ID )' );
		task_end();

		task_begin( 'Upgrading filetypes table...' );
		// get allowed filetype ids
		$sql = 'SELECT ftyp_ID
					FROM T_filetypes
					WHERE ftyp_allowed != 0';
		$allowed_ids = implode( ',', $DB->get_col( $sql, 0, 'Get allowed filetypes' ) );

		// update table column  -- this column is about who can edit the filetype: any user, registered users or only admins.
		$DB->query( 'ALTER TABLE T_filetypes
						MODIFY COLUMN ftyp_allowed enum("any","registered","admin") NOT NULL default "admin"' );

		// update ftyp_allowed column content
		$DB->query( 'UPDATE T_filetypes
						SET ftyp_allowed = "registered"
						WHERE ftyp_ID IN ('.$allowed_ids.')' );
		$DB->query( 'UPDATE T_filetypes
						SET ftyp_allowed = "admin"
						WHERE ftyp_ID NOT IN ('.$allowed_ids.')' );
		$DB->query( 'UPDATE T_filetypes
						SET ftyp_allowed = "any"
						WHERE ftyp_extensions = "gif" OR ftyp_extensions = "png" OR ftyp_extensions LIKE "%jpg%"' );

		// Add m4v file type if not exists
		if( !db_key_exists( 'T_filetypes', 'ftyp_extensions', '"m4v"' ) )
		{
			$DB->query( 'INSERT INTO T_filetypes (ftyp_extensions, ftyp_name, ftyp_mimetype, ftyp_icon, ftyp_viewtype, ftyp_allowed)
				             VALUES ("m4v", "MPEG video file", "video/x-m4v", "", "browser", "registered")', 'Add "m4v" file type' );
		}
		task_end();

		// The AdSense plugin needs to store quite long strings of data...
		task_begin( 'Upgrading collection settings table, change cset_value type...' );
		$DB->query( 'ALTER TABLE T_coll_settings
								 MODIFY COLUMN cset_name VARCHAR(50) NOT NULL,
								 MODIFY COLUMN cset_value VARCHAR(10000) NULL' );
		task_end();

		set_upgrade_checkpoint( '10100' );
	}

	if( $old_db_version < 10200 )
	{	// 4.1b
		task_begin( 'Creating table for a specific blog post subscriptions...' );
		$DB->query( "CREATE TABLE T_items__subscriptions (
						isub_item_ID  int(11) unsigned NOT NULL,
						isub_user_ID  int(11) unsigned NOT NULL,
						isub_comments tinyint(1) NOT NULL default 0 COMMENT 'The user wants to receive notifications for new comments on this post',
						PRIMARY KEY (isub_item_ID, isub_user_ID )
					) ENGINE = innodb" );
		task_end();

		task_begin( 'Upgrading comments table, add subscription fields...' );
		db_add_col( 'T_comments', 'comment_notif_status', 'ENUM("noreq","todo","started","finished") NOT NULL DEFAULT "noreq" COMMENT "Have notifications been sent for this comment? How far are we in the process?" AFTER comment_secret' );
		db_add_col( 'T_comments', 'comment_notif_ctsk_ID', 'INT(10) unsigned NULL DEFAULT NULL COMMENT "When notifications for this comment are sent through a scheduled job, what is the job ID?" AFTER comment_notif_status' );
		task_end();

		task_begin( 'Upgrading users table...' );
		db_add_col( 'T_users', 'user_notify_moderation', 'tinyint(1) NOT NULL default 0 COMMENT "Notify me by email whenever a comment is awaiting moderation on one of my blogs" AFTER user_notify' );
		db_add_col( 'T_users', 'user_unsubscribe_key', 'varchar(32) NOT NULL default "" COMMENT "A specific key, it is used when a user wants to unsubscribe from a post comments without signing in" AFTER user_notify_moderation' );
		// Set unsubscribe keys for existing users with no unsubscribe key
		$sql = 'SELECT user_ID
							FROM T_users
						 WHERE user_unsubscribe_key = ""';
		$rows = $DB->get_results( $sql, OBJECT, 'Get users with no unsubscribe key' );
		foreach( $rows as $row )
		{
			$DB->query( 'UPDATE T_users
							SET user_unsubscribe_key = "'.generate_random_key().'"
							WHERE user_ID = '.$row->user_ID );
		}
		task_end();

		task_begin( 'Upgrading settings table... ');
		$DB->query( 'INSERT INTO T_settings (set_name, set_value)
						VALUES ( "smart_hit_count", 1 )' );
		$DB->query( 'ALTER TABLE T_coll_settings
									CHANGE COLUMN cset_value cset_value   VARCHAR( 10000 ) NULL COMMENT "The AdSense plugin wants to store very long snippets of HTML"' );
  		task_end();

  		// The following two upgrade task were created subsequently to "Make sure DB schema is up to date".
  		// Note: These queries don't modify the correct databases
		task_begin( 'Upgrading users table, no notification by default...');
		$DB->query( 'ALTER TABLE T_users ALTER COLUMN user_notify SET DEFAULT 0' );
		task_end();

		task_begin( 'Upgrading items table...');
		$DB->query( 'ALTER TABLE T_items__item CHANGE COLUMN post_priority post_priority int(11) unsigned null COMMENT "Task priority in workflow"' );
		task_end();

		set_upgrade_checkpoint( '10200' );
	}


	if( $old_db_version < 10300 )
	{	// 4.2
		task_begin( 'Upgrading user fields...' );
		$DB->query( 'ALTER TABLE T_users__fielddefs
									ADD COLUMN ufdf_required enum("hidden","optional","recommended","require") NOT NULL default "optional"');
		$DB->query( 'UPDATE T_users__fielddefs
										SET ufdf_required = "recommended"
									WHERE ufdf_name in ("Website", "Twitter", "Facebook") ' );
		$DB->query( "REPLACE INTO T_users__fielddefs (ufdf_ID, ufdf_type, ufdf_name, ufdf_required)
			 						VALUES (400000, 'text', 'About me', 'recommended');" );
		task_end();

		task_begin( 'Moving data to user fields...' );
		$DB->query( 'INSERT INTO T_users__fields( uf_user_ID, uf_ufdf_ID, uf_varchar )
								 SELECT user_ID, 10300, user_icq
									 FROM T_users
								  WHERE user_icq IS NOT NULL AND TRIM(user_icq) <> ""' );
		$DB->query( 'INSERT INTO T_users__fields( uf_user_ID, uf_ufdf_ID, uf_varchar )
								 SELECT user_ID, 10200, user_aim
									 FROM T_users
								  WHERE user_aim IS NOT NULL AND TRIM(user_aim) <> ""' );
		$DB->query( 'INSERT INTO T_users__fields( uf_user_ID, uf_ufdf_ID, uf_varchar )
								 SELECT user_ID, 10000, user_msn
									 FROM T_users
								  WHERE user_msn IS NOT NULL AND TRIM(user_msn) <> ""' );
		$DB->query( 'INSERT INTO T_users__fields( uf_user_ID, uf_ufdf_ID, uf_varchar )
								 SELECT user_ID, 10100, user_yim
									 FROM T_users
								  WHERE user_yim IS NOT NULL AND TRIM(user_yim) <> ""' );
		task_end();

		task_begin( 'Dropping obsolete user columns...' );
		$DB->query( 'ALTER TABLE T_users
									DROP COLUMN user_icq,
									DROP COLUMN user_aim,
									DROP COLUMN user_msn,
									DROP COLUMN user_yim' );
		task_end();

		// ---

		task_begin( 'Adding new user columns...' );
		$DB->query( 'ALTER TABLE T_users
									ADD COLUMN user_postcode varchar(12) NULL AFTER user_ID,
									ADD COLUMN user_age_min int unsigned NULL AFTER user_postcode,
									ADD COLUMN user_age_max int unsigned NULL AFTER user_age_min' );
		task_end();

		task_begin( 'Upgrading item table for hide teaser...' );
		$DB->query( 'ALTER TABLE T_items__item
						ADD COLUMN post_hideteaser tinyint(1) NOT NULL DEFAULT 0 AFTER post_featured');
		$DB->query( 'UPDATE T_items__item
										SET post_hideteaser = 1
									WHERE post_content LIKE "%<!--noteaser-->%"' );
		task_end();

		task_begin( 'Creating table for a specific post settings...' );
		$DB->query( "CREATE TABLE T_items__item_settings (
						iset_item_ID  int(10) unsigned NOT NULL,
						iset_name     varchar( 50 ) NOT NULL,
						iset_value    varchar( 2000 ) NULL,
						PRIMARY KEY ( iset_item_ID, iset_name )
					) ENGINE = innodb" );
		task_end();

		task_begin( 'Adding new column to comments...' );
		$DB->query( 'ALTER TABLE T_comments
									ADD COLUMN comment_in_reply_to_cmt_ID INT(10) unsigned NULL AFTER comment_status' );
		task_end();

		task_begin( 'Create table for internal searches...' );
		$DB->query( 'CREATE TABLE T_logs__internal_searches (
						isrch_ID bigint(20) NOT NULL auto_increment,
						isrch_coll_ID bigint(20) NOT NULL,
						isrch_hit_ID bigint(20) NOT NULL,
						isrch_keywords varchar(255) NOT NULL,
						PRIMARY KEY (isrch_ID)
					) ENGINE = MyISAM' );
		task_end();

		task_begin( 'Create table for comments votes...' );
		$DB->query( 'CREATE TABLE T_comments__votes (
						cmvt_cmt_ID  int(10) unsigned NOT NULL,
						cmvt_user_ID int(10) unsigned NOT NULL,
						cmvt_helpful TINYINT(1) NULL DEFAULT NULL,
						cmvt_spam    TINYINT(1) NULL DEFAULT NULL,
						PRIMARY KEY (cmvt_cmt_ID, cmvt_user_ID),
						KEY cmvt_cmt_ID (cmvt_cmt_ID),
						KEY cmvt_user_ID (cmvt_user_ID)
					) ENGINE = innodb' );
		task_end();

		task_begin( 'Adding new comments columns...' );
		$DB->query( 'ALTER TABLE T_comments
									ADD comment_helpful_addvotes INT NOT NULL DEFAULT 0 AFTER comment_nofollow ,
									ADD comment_helpful_countvotes INT UNSIGNED NOT NULL DEFAULT 0 AFTER comment_helpful_addvotes ,
									ADD comment_spam_addvotes INT NOT NULL DEFAULT 0 AFTER comment_helpful_countvotes ,
									ADD comment_spam_countvotes INT UNSIGNED NOT NULL DEFAULT 0 AFTER comment_spam_addvotes ,
									CHANGE COLUMN comment_notif_ctsk_ID comment_notif_ctsk_ID      INT(10) unsigned NULL DEFAULT NULL COMMENT "When notifications for this comment are sent through a scheduled job, what is the job ID?"');
		task_end();

		task_begin( 'Adding new user permission for spam voting...' );
		$DB->query( 'ALTER TABLE T_coll_user_perms
									ADD bloguser_perm_vote_spam_cmts tinyint NOT NULL default 0 AFTER bloguser_perm_edit_ts' );
		task_end();

		task_begin( 'Adding new group permission for spam voting...' );
		$DB->query( 'ALTER TABLE T_coll_group_perms
									ADD bloggroup_perm_vote_spam_cmts tinyint NOT NULL default 0 AFTER bloggroup_perm_edit_ts' );
		task_end();

		task_begin( 'Upgrading countries table...' );
		$DB->query( 'ALTER TABLE '.$tableprefix.'country ADD COLUMN ctry_preferred tinyint(1) NOT NULL DEFAULT 0 AFTER ctry_enabled' );
		task_end();

		$DB->query( 'ALTER TABLE T_items__subscriptions CHANGE COLUMN isub_comments isub_comments   tinyint(1) NOT NULL DEFAULT 0 COMMENT "The user wants to receive notifications for new comments on this post"' );

		set_upgrade_checkpoint( '10300' );
	}


	if( $old_db_version < 10400 )
	{	// 4.2 part 2
		task_begin( 'Updating "Post by Email" settings...' );
		$DB->query( 'UPDATE T_settings SET set_name = "eblog_autobr" WHERE set_name = "AutoBR"' );
		task_end();

		if( $DB->get_var('SELECT set_value FROM T_settings WHERE set_name = "eblog_enabled"') )
		{	// eblog enabled, let's create a scheduled job for it
			task_begin( 'Creating "Post by Email" scheduled job...' );
			$start_date = form_date( date2mysql($GLOBALS['localtimenow'] + 86400), '05:00:00' ); // start tomorrow
			$DB->query( '
				INSERT INTO T_cron__task ( ctsk_start_datetime, ctsk_repeat_after, ctsk_name, ctsk_controller, ctsk_params )
				VALUES ( '.$DB->quote( $start_date ).', 86400, '.$DB->quote( T_('Create posts by email') ).', '.$DB->quote( 'cron/jobs/_post_by_email.job.php' ).', '.$DB->quote( 'N;' ).' )' );
			task_end();
		}

		task_begin( 'Upgrading hitlog table...' );
 		flush();
		$DB->query( 'ALTER TABLE T_hitlog
								ADD COLUMN hit_disp        VARCHAR(30) DEFAULT NULL AFTER hit_uri,
								ADD COLUMN hit_ctrl        VARCHAR(30) DEFAULT NULL AFTER hit_disp,
								ADD COLUMN hit_response_code     INT DEFAULT NULL AFTER hit_agent_type ' );
		task_end();

		task_begin( 'Upgrading file types...' );
		// Update ftyp_icon column
		// Previous versions used a image file name for this field,
		// but from now we should use a icon name from the file /conf/_icons.php
		$DB->query( 'UPDATE T_filetypes
						SET ftyp_icon = "file_image"
						WHERE ftyp_extensions IN ( "gif", "png", "jpg jpeg" )' );
		$DB->query( 'UPDATE T_filetypes
						SET ftyp_icon = "file_document"
						WHERE ftyp_extensions = "txt"' );
		$DB->query( 'UPDATE T_filetypes
						SET ftyp_icon = "file_www"
						WHERE ftyp_extensions = "htm html"' );
		$DB->query( 'UPDATE T_filetypes
						SET ftyp_icon = "file_pdf"
						WHERE ftyp_extensions = "pdf"' );
		$DB->query( 'UPDATE T_filetypes
						SET ftyp_icon = "file_doc"
						WHERE ftyp_extensions = "doc"' );
		$DB->query( 'UPDATE T_filetypes
						SET ftyp_icon = "file_xls"
						WHERE ftyp_extensions = "xls"' );
		$DB->query( 'UPDATE T_filetypes
						SET ftyp_icon = "file_ppt"
						WHERE ftyp_extensions = "ppt"' );
		$DB->query( 'UPDATE T_filetypes
						SET ftyp_icon = "file_pps"
						WHERE ftyp_extensions = "pps"' );
		$DB->query( 'UPDATE T_filetypes
						SET ftyp_icon = "file_zip"
						WHERE ftyp_extensions = "zip"' );
		$DB->query( 'UPDATE T_filetypes
						SET ftyp_icon = "file_php"
						WHERE ftyp_extensions = "php php3 php4 php5 php6"' );
		$DB->query( 'UPDATE T_filetypes
						SET ftyp_icon = ""
						WHERE ftyp_extensions = "css"' );
		$DB->query( 'UPDATE T_filetypes
						SET ftyp_icon = "file_sound"
						WHERE ftyp_extensions IN ( "mp3", "m4a" )' );
		$DB->query( 'UPDATE T_filetypes
						SET ftyp_icon = "file_video"
						WHERE ftyp_extensions IN ( "mp4", "mov", "m4v" )' );
		task_end();

		set_upgrade_checkpoint( '10400' );
	}


	if( $old_db_version < 10500 )
	{	//  part 3
		task_begin( 'Upgrading hitlog table...' );
 		flush();
		$DB->query( "ALTER TABLE T_hitlog
								CHANGE COLUMN hit_referer_type  hit_referer_type ENUM(  'search',  'special',  'spam',  'referer',  'direct',  'self',  'admin', 'blacklist' ) NOT NULL,
								ADD COLUMN hit_type ENUM('standard','rss','admin','ajax', 'service') DEFAULT 'standard' NOT NULL AFTER hit_ctrl,
								ADD COLUMN hit_action VARCHAR(30) DEFAULT NULL AFTER hit_ctrl" );
		$DB->query( 'UPDATE T_hitlog SET hit_referer_type = "special" WHERE hit_referer_type = "blacklist"' );
		$DB->query( 'UPDATE T_hitlog SET hit_type = "admin", hit_referer_type = "direct"  WHERE hit_referer_type = "admin"' );
		$DB->query( "ALTER TABLE T_hitlog
								CHANGE COLUMN hit_referer_type  hit_referer_type ENUM(  'search',  'special',  'spam',  'referer',  'direct',  'self' ) NOT NULL");
		task_end();

		task_begin( 'Creating table for Groups of user field definitions...' );
		$DB->query( 'CREATE TABLE T_users__fieldgroups (
				ufgp_ID int(10) unsigned NOT NULL auto_increment,
				ufgp_name varchar(255) NOT NULL,
				ufgp_order int(11) NOT NULL,
				PRIMARY KEY (ufgp_ID)
			) ENGINE = innodb' );
		$DB->query( 'INSERT INTO T_users__fieldgroups ( ufgp_name, ufgp_order )
				VALUES ( "Instant Messaging", "1" ),
							 ( "Phone", "2" ),
							 ( "Web", "3" ),
							 ( "Organization", "4" ),
							 ( "Address", "5" ),
							 ( "Other", "6" ) ' );
		task_end();

		task_begin( 'Upgrading user field definitions...' );
		// Add new fields:
		// 		"ufdf_options" to save a values of the Option list
		// 		"ufdf_duplicated" to add a several instances
		// 		"ufdf_ufgp_ID" - Group ID
		// 		"ufdf_order" - Order number
		// 		"ufdf_suggest" - Suggest values
		$DB->query( 'ALTER TABLE T_users__fielddefs
						ADD ufdf_options    TEXT NOT NULL AFTER ufdf_name,
						ADD ufdf_duplicated enum("forbidden","allowed","list") NOT NULL default "allowed",
						ADD ufdf_ufgp_ID    int(10) unsigned NOT NULL AFTER ufdf_ID,
						ADD ufdf_order      int(11) NOT NULL,
						ADD ufdf_suggest    tinyint(1) NOT NULL DEFAULT 0,
						CHANGE ufdf_ID ufdf_ID int(10) UNSIGNED NOT NULL AUTO_INCREMENT' );
		// Set default values of the field "ufdf_duplicated"
		$DB->query( 'UPDATE T_users__fielddefs
						SET ufdf_duplicated = "allowed"
						WHERE ufdf_ID IN ( 10000, 10100, 10200, 10300, 50100, 50200, 100000, 100100 )' );
		// Group fields by default
		$DB->query( 'UPDATE T_users__fielddefs
						SET ufdf_ufgp_ID = "1"
						WHERE ufdf_ID <= 40000 ' );
		$DB->query( 'UPDATE T_users__fielddefs
						SET ufdf_ufgp_ID = "2"
						WHERE ufdf_ID > 40000 AND ufdf_ID <= 60100' );
		$DB->query( 'UPDATE T_users__fielddefs
						SET ufdf_ufgp_ID = "3"
						WHERE ufdf_ID > 60100 AND ufdf_ID <= 160100' );
		$DB->query( 'UPDATE T_users__fielddefs
						SET ufdf_ufgp_ID = "4"
						WHERE ufdf_ID > 160100 AND ufdf_ID <= 211000' );
		$DB->query( 'UPDATE T_users__fielddefs
						SET ufdf_ufgp_ID = "5"
						WHERE ufdf_ID > 211000 AND ufdf_ID <= 300300' );
		$DB->query( 'UPDATE T_users__fielddefs
						SET ufdf_ufgp_ID = "6"
						WHERE ufdf_ID > 300300' );
		// Set order field
		$userfields = $DB->get_results( 'SELECT ufdf_ID, ufdf_ufgp_ID
				FROM T_users__fielddefs
				ORDER BY ufdf_ufgp_ID, ufdf_ID' );
		$userfield_order = 1;
		foreach( $userfields as $uf => $userfield )
		{
			if( $uf > 0 )
			{
				if( $userfields[$uf-1]->ufdf_ufgp_ID != $userfield->ufdf_ufgp_ID )
				{	// New group is starting, reset $userfield_order
					$userfield_order = 1;
				}
			}
			$DB->query( 'UPDATE T_users__fielddefs
						SET ufdf_order = "'.$userfield_order.'"
						WHERE ufdf_ID = '.$userfield->ufdf_ID );
			$userfield_order++;
		}
		// Change field type for Group 'Organization' (group_ID=4)
		$DB->query( 'UPDATE T_users__fielddefs
					SET ufdf_type = "word"
					WHERE ufdf_ufgp_ID = "4"' );
		// Create a default additional info for administrator (user_ID=1)
		$DB->query( 'INSERT INTO T_users__fields ( uf_user_ID, uf_ufdf_ID, uf_varchar )
			VALUES ( 1, 200000, "Site administrator" ),
						 ( 1, 200000, "Moderator" ),
						 ( 1, 100000, "'.$baseurl.'" )' );
		// Add Indexes
		$DB->query( 'ALTER TABLE T_users__fields
						ADD INDEX uf_ufdf_ID ( uf_ufdf_ID ),
						ADD INDEX uf_varchar ( uf_varchar ) ' );
		task_end();

		task_begin( 'Upgrading permissions...' );
		// Group permissions
		$DB->query( 'ALTER TABLE T_coll_group_perms
						ADD bloggroup_perm_own_cmts tinyint NOT NULL default 0 AFTER bloggroup_perm_edit_ts' );
		// Set default values for Administrators & Privileged Bloggers groups
		$DB->query( 'UPDATE T_coll_group_perms
						SET bloggroup_perm_own_cmts = "1"
						WHERE bloggroup_group_ID IN ( 1, 2 )' );
		// User permissions
		$DB->query( 'ALTER TABLE T_coll_user_perms
						ADD bloguser_perm_own_cmts tinyint NOT NULL default 0 AFTER bloguser_perm_edit_ts' );
		task_end();


		set_upgrade_checkpoint( '10500' );
	}


	if( $old_db_version < 10600 )
	{	//  part 4

		// For create_default_regions() and create_default_subregions():
		require_once dirname(__FILE__).'/_functions_create.php';

		task_begin( 'Renaming Countries table...' );
		$DB->query( 'RENAME TABLE '.$tableprefix.'country TO T_regional__country' );
		task_end();

		task_begin( 'Renaming Currencies table...' );
		$DB->query( 'RENAME TABLE '.$tableprefix.'currency TO T_regional__currency' );
		task_end();

		task_begin( 'Creating Regions table...' );
		$DB->query( 'CREATE TABLE T_regional__region (
			rgn_ID        int(10) unsigned NOT NULL auto_increment,
			rgn_ctry_ID   int(10) unsigned NOT NULL,
			rgn_code      char(6) NOT NULL,
			rgn_name      varchar(40) NOT NULL,
			rgn_enabled   tinyint(1) NOT NULL DEFAULT 1,
			rgn_preferred tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY rgn_ID (rgn_ID),
			UNIQUE rgn_ctry_ID_code (rgn_ctry_ID, rgn_code)
		) ENGINE = innodb' );
		task_end();

		create_default_regions();

		task_begin( 'Creating Sub-regions table...' );
		$DB->query( 'CREATE TABLE T_regional__subregion (
			subrg_ID        int(10) unsigned NOT NULL auto_increment,
			subrg_rgn_ID    int(10) unsigned NOT NULL,
			subrg_code      char(6) NOT NULL,
			subrg_name      varchar(40) NOT NULL,
			subrg_enabled   tinyint(1) NOT NULL DEFAULT 1,
			subrg_preferred tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY subrg_ID (subrg_ID),
			UNIQUE subrg_rgn_ID_code (subrg_rgn_ID, subrg_code)
		) ENGINE = innodb' );
		task_end();

		create_default_subregions();

		task_begin( 'Creating Cities table...' );
		$DB->query( 'CREATE TABLE T_regional__city (
			city_ID         int(10) unsigned NOT NULL auto_increment,
			city_ctry_ID    int(10) unsigned NOT NULL,
			city_rgn_ID     int(10) unsigned NULL,
			city_subrg_ID   int(10) unsigned NULL,
			city_postcode   char(12) NOT NULL,
			city_name       varchar(40) NOT NULL,
			city_enabled    tinyint(1) NOT NULL DEFAULT 1,
			city_preferred  tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY city_ID (city_ID),
			INDEX city_ctry_ID_postcode ( city_ctry_ID, city_postcode ),
			INDEX city_rgn_ID_postcode ( city_rgn_ID, city_postcode ),
			INDEX city_subrg_ID_postcode ( city_subrg_ID, city_postcode )
		) ENGINE = innodb' );
		task_end();

		task_begin( 'Update Item Settings...' );
		// Admin: full rights for all blogs (look 'ma, doing a natural join! :>)
		$query = "INSERT INTO T_items__item_settings( iset_item_ID, iset_name, iset_value )
						SELECT post_ID, 'hide_teaser', post_hideteaser
							FROM T_items__item";
		$DB->query( $query );

		db_drop_col( 'T_items__item', 'post_hideteaser' );
		task_end();

		task_begin( 'Upgrading hitlog table...' );
 		flush();
		$DB->query( "ALTER TABLE T_hitlog
						ADD COLUMN hit_keyphrase VARCHAR(255) DEFAULT NULL AFTER hit_keyphrase_keyp_ID" );
		task_end();

		task_begin( 'Upgrading track__keyphrase...' );
		$DB->query( "ALTER TABLE T_track__keyphrase
						ADD COLUMN keyp_count_refered_searches INT UNSIGNED DEFAULT 0 AFTER keyp_phrase,
						ADD COLUMN keyp_count_internal_searches INT UNSIGNED DEFAULT 0 AFTER keyp_count_refered_searches" );
		task_end();



		task_begin( 'Droping table internal searches...' );

		$DB->query( "DROP TABLE T_logs__internal_searches" );
		task_end();


		task_begin( 'Upgrading users table...' );
		db_add_col( 'T_users', 'user_rgn_ID', 'int(10) unsigned NULL AFTER user_ctry_ID' );
		db_add_col( 'T_users', 'user_subrg_ID', 'int(10) unsigned NULL AFTER user_rgn_ID' );
		db_add_col( 'T_users', 'user_city_ID', 'int(10) unsigned NULL AFTER user_subrg_ID' );
		task_end();

		task_begin( 'Upgrading hitlog table...' );
 		flush();
		$DB->query( 'UPDATE T_hitlog
						SET hit_type = "rss",
							hit_agent_type = "unknown"
						WHERE hit_agent_type = "rss"' );

		$DB->query( "ALTER TABLE T_hitlog
								CHANGE COLUMN hit_agent_type hit_agent_type ENUM('robot','browser','unknown') DEFAULT 'unknown' NOT NULL" );
		task_end();

		task_begin( 'Creating mail log table...' );
		$DB->query( 'CREATE TABLE '.$tableprefix.'mail__log (
		  emlog_ID        INT(10) UNSIGNED NOT NULL auto_increment,
		  emlog_timestamp TIMESTAMP NOT NULL,
		  emlog_to        VARCHAR(255) DEFAULT NULL,
		  emlog_success   TINYINT(1) NOT NULL DEFAULT 0,
		  emlog_subject   VARCHAR(255) DEFAULT NULL,
		  emlog_headers   TEXT DEFAULT NULL,
		  emlog_message   TEXT DEFAULT NULL,
		  PRIMARY KEY     (emlog_ID)
		) ENGINE = myisam' );
		task_end();

		set_upgrade_checkpoint( '10600' );
	}


	if( $old_db_version < 10700 )
	{	// part 5

		task_begin( 'Upgrading user notifications settings...' );
		$DB->query( 'INSERT INTO T_users__usersettings ( uset_user_ID, uset_name, uset_value )
						SELECT user_ID, "notify_published_comments", user_notify
							FROM T_users', 'Move notify settings from users to users_usersettings' );
		$DB->query( 'INSERT INTO T_users__usersettings ( uset_user_ID, uset_name, uset_value )
						SELECT user_ID, "notify_comment_moderation", user_notify_moderation
							FROM T_users', 'Move notify moderation settings from users to users_usersettings' );
		$DB->query( 'INSERT INTO T_users__usersettings ( uset_user_ID, uset_name, uset_value )
						SELECT user_ID, "enable_PM", 1
							FROM T_users
								WHERE user_allow_msgform = 1 OR user_allow_msgform = 3', 'Set enable PM on users_usersettings' );
		$DB->query( 'INSERT INTO T_users__usersettings ( uset_user_ID, uset_name, uset_value )
						SELECT user_ID, "enable_PM", 0
							FROM T_users
								WHERE user_allow_msgform = 0 OR user_allow_msgform = 2', 'Set enable PM on users_usersettings' );
		$DB->query( 'INSERT INTO T_users__usersettings ( uset_user_ID, uset_name, uset_value )
						SELECT user_ID, "enable_email", 1
							FROM T_users
								WHERE user_allow_msgform > 1', 'Set enable email true on users_usersettings' );
		$DB->query( 'INSERT INTO T_users__usersettings ( uset_user_ID, uset_name, uset_value )
						SELECT user_ID, "enable_email", 0
							FROM T_users
								WHERE user_allow_msgform < 2', 'Set enable email false on users_usersettings' );
		db_drop_col( 'T_users', 'user_notify' );
		db_drop_col( 'T_users', 'user_notify_moderation' );
		db_drop_col( 'T_users', 'user_allow_msgform' );
		task_end();

		task_begin( 'Upgrading Item table...' );
		db_add_col( 'T_items__item', 'post_ctry_ID', 'INT(10) UNSIGNED NULL' );
		db_add_col( 'T_items__item', 'post_rgn_ID', 'INT(10) UNSIGNED NULL' );
		db_add_col( 'T_items__item', 'post_subrg_ID', 'INT(10) UNSIGNED NULL' );
		db_add_col( 'T_items__item', 'post_city_ID', 'INT(10) UNSIGNED NULL' );
		task_end();

		task_begin( 'Upgrading users table...' );
		db_drop_col( 'T_users', 'user_postcode' );	// Previously obsoleted
		db_drop_col( 'T_users', 'user_idmode' );
		task_end();

		task_begin( 'Upgrading users fields table...' );
		db_add_col( 'T_users__fielddefs', 'ufdf_bubbletip', 'varchar(2000) NULL' );
		task_end();


		task_begin( 'Creating table for groups of messaging contacts...' );
		$DB->query( "CREATE TABLE T_messaging__contact_groups (
			cgr_ID      int(10) unsigned NOT NULL auto_increment,
			cgr_user_ID int(10) unsigned NOT NULL,
			cgr_name    varchar(50) NOT NULL,
			PRIMARY KEY cgr_ID (cgr_ID)
		) ENGINE = innodb" );
		task_end();

		task_begin( 'Creating table for group users of messaging contacts...' );
		$DB->query( "CREATE TABLE T_messaging__contact_groupusers (
			cgu_user_ID int(10) unsigned NOT NULL,
			cgu_cgr_ID  int(10) unsigned NOT NULL,
			PRIMARY KEY cgu_PK (cgu_user_ID, cgu_cgr_ID)
		) ENGINE = innodb" );
		task_end();

		task_begin( 'Upgrading mail log table...' );
		db_add_col( $tableprefix.'mail__log', 'emlog_user_ID', 'INT(10) UNSIGNED DEFAULT NULL AFTER emlog_timestamp' );
		task_end();

		set_upgrade_checkpoint( '10700' );
	}

	if( $old_db_version < 10800 )
	{	// part 6 aka between "i1-i2" and "i2"

		task_begin( 'Upgrading users table, add user status...' );
		db_add_col( 'T_users', 'user_status', 'enum( "activated", "autoactivated", "closed", "deactivated", "emailchanged", "new" ) NOT NULL default "new" AFTER user_validated' );
		$update_user_status_query = 'UPDATE T_users SET user_status = ';
		// check if new users must activate their account. If users are not required to activate their account, then all existing users will be considerated as activated user.
		$new_users_must_validate = $DB->get_var( 'SELECT set_value FROM T_settings WHERE set_name = '.$DB->quote( 'newusers_mustvalidate' ) );
		if( $new_users_must_validate || ( $new_users_must_validate == NULL ) )
		{ // newusers_mustvalidate setting is set to true, or it is not set at all. If it is not set, we know that the default value is true!
			// set activated status only for validated users
			$update_user_status_query .= $DB->quote( 'activated' );
			$update_user_status_query .= ' WHERE user_validated = 1';
		}
		else
		{
			$update_user_status_query .= $DB->quote( 'autoactivated' );
		}
		// set activated status for corresponding users
		$DB->query( $update_user_status_query );
		db_drop_col( 'T_users', 'user_validated' );
		task_end();

		set_upgrade_checkpoint( '10800' );
	}

	if( $old_db_version < 10900 )
	{	// part 7 aka "i3"

		task_begin( 'Upgrading user settings table...' );
		$DB->query( 'INSERT INTO T_users__usersettings ( uset_user_ID, uset_name, uset_value )
						SELECT user_ID, "show_online", 0
							FROM T_users
								WHERE user_showonline = 0', 'Set show online on users_usersettings' );
		$DB->query( 'INSERT INTO T_users__usersettings ( uset_user_ID, uset_name, uset_value )
						SELECT user_ID, "user_ip", user_ip
							FROM T_users
								WHERE user_ip IS NOT NULL', 'Set user ip on users_usersettings' );
		$DB->query( 'INSERT INTO T_users__usersettings ( uset_user_ID, uset_name, uset_value )
						SELECT user_ID, "user_domain", user_domain
							FROM T_users
								WHERE user_domain IS NOT NULL', 'Set user domain on users_usersettings' );
		$DB->query( 'INSERT INTO T_users__usersettings ( uset_user_ID, uset_name, uset_value )
						SELECT user_ID, "user_browser", user_browser
							FROM T_users
								WHERE user_browser IS NOT NULL', 'Set user browser on users_usersettings' );
		db_drop_col( 'T_users', 'user_showonline' );
		db_drop_col( 'T_users', 'user_ip' );
		db_drop_col( 'T_users', 'user_domain' );
		db_drop_col( 'T_users', 'user_browser' );
		task_end();

		task_begin( 'Upgrading user activation settings...' );
		// Remove all last_activation_email timestamps because we will use date instead of them
		$DB->query( 'DELETE FROM T_users__usersettings WHERE uset_name = "last_activation_email"' );
		task_end();

		task_begin( 'Upgrading Users table...' );
		// Update user_status column add 'failedactivation' status
		$DB->query( 'ALTER TABLE T_users CHANGE user_status
					user_status enum( "activated", "autoactivated", "closed", "deactivated", "emailchanged", "failedactivation", "new" ) NOT NULL default "new"' );
		db_add_col( 'T_users', 'user_created_fromIPv4', 'int(10) unsigned NOT NULL' );
		db_add_col( 'T_users', 'user_email_dom_ID', 'int(10) unsigned NULL' );
		$DB->query( 'ALTER TABLE T_users CHANGE dateYMDhour user_created_datetime DATETIME NOT NULL DEFAULT \'2000-01-01 00:00:00\'' );
		db_add_col( 'T_users', 'user_reg_ctry_ID', 'int(10) unsigned NULL AFTER user_age_max' );
		db_add_col( 'T_users', 'user_profileupdate_ts', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP' );
		$DB->query( 'ALTER TABLE T_users ADD INDEX user_email ( user_email )' );
		task_end();

		task_begin( 'Renaming Email log table...' );
		$DB->query( 'RENAME TABLE '.$tableprefix.'mail__log TO T_email__log' );
		task_end();

		task_begin( 'Creating email returns table...' );
		$DB->query( "CREATE TABLE T_email__returns (
			  emret_ID        INT(10) UNSIGNED NOT NULL auto_increment,
			  emret_address   VARCHAR(255) DEFAULT NULL,
			  emret_errormsg  VARCHAR(255) DEFAULT NULL,
			  emret_timestamp TIMESTAMP NOT NULL,
			  emret_headers   TEXT DEFAULT NULL,
			  emret_message   TEXT DEFAULT NULL,
			  emret_errtype   CHAR(1) NOT NULL DEFAULT 'U',
			  PRIMARY KEY     (emret_ID)
			) ENGINE = myisam" );
		task_end();

		task_begin( 'Upgrading general settings table...' );
		$DB->query( 'ALTER TABLE T_settings CHANGE set_value set_value VARCHAR( 5000 ) NULL' );
		task_end();

		task_begin( 'Upgrading sessions table...' );
		db_add_col( 'T_sessions', 'sess_device', 'VARCHAR(8) NOT NULL DEFAULT \'\'' );
		task_end();

		task_begin( 'Creating table for Antispam IP Ranges...' );
		$DB->query( "CREATE TABLE T_antispam__iprange (
			aipr_ID         int(10) unsigned NOT NULL auto_increment,
			aipr_IPv4start  int(10) unsigned NOT NULL,
			aipr_IPv4end    int(10) unsigned NOT NULL,
			aipr_user_count int(10) unsigned DEFAULT 0,
			PRIMARY KEY aipr_ID (aipr_ID)
		) ENGINE = innodb" );
		task_end();

		task_begin( 'Upgrading base domains table...' );
		$DB->query( "ALTER TABLE T_basedomains CHANGE dom_type dom_type ENUM( 'unknown', 'normal', 'searcheng', 'aggregator', 'email' ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'unknown'" );
		$DB->query( 'ALTER TABLE T_basedomains DROP INDEX dom_name' );
		$DB->query( 'ALTER TABLE T_basedomains DROP INDEX dom_type' );
		$DB->query( 'ALTER TABLE T_basedomains ADD UNIQUE dom_type_name ( dom_type, dom_name )' );
		task_end();

		/*** Update user_email_dom_ID for all already existing users ***/
		task_begin( 'Upgrading users email domains...' );
		$DB->begin();
		// Get the users
		$uemails_SQL = new SQL();
		$uemails_SQL->SELECT( 'user_ID, user_email' );
		$uemails_SQL->FROM( 'T_users' );
		$users_emails = $DB->get_assoc( $uemails_SQL->get() );

		if( count( $users_emails ) > 0 )
		{
			// Get all email domains
			$edoms_SQL = new SQL();
			$edoms_SQL->SELECT( 'dom_ID, dom_name' );
			$edoms_SQL->FROM( 'T_basedomains' );
			$edoms_SQL->WHERE( 'dom_type = \'email\'' );
			$email_domains = $DB->get_assoc( $edoms_SQL->get() );
			// pre_dump( $email_domains );

			foreach( $users_emails as $user_ID => $user_email )
			{
				if( preg_match( '#@(.+)#i', strtolower($user_email), $ematch ) )
				{	// Get email domain from user's email address
					$email_domain = $ematch[1];
					$dom_ID = array_search( $email_domain, $email_domains );

					if( ! $dom_ID )
					{	// Insert new email domain
						$DB->query( 'INSERT INTO T_basedomains ( dom_type, dom_name )
							VALUES ( \'email\', '.$DB->quote( $email_domain ).' )' );
						$dom_ID = $DB->insert_id;

						// Memorize inserted domain to prevent duplicates
						$email_domains[$dom_ID] = $email_domain;
						// pre_dump( $dom_ID, $email_domain );
					}

					// Update user_email_dom_ID
					$DB->query( 'UPDATE T_users
						SET user_email_dom_ID = '.$DB->quote( $dom_ID ).'
						WHERE user_ID = '.$DB->quote( $user_ID ) );
				}
			}
		}
		$DB->commit();
		task_end();

		task_begin( 'Upgrading users fields table...' );
		$DB->query( 'ALTER TABLE T_users__fields CHANGE uf_varchar uf_varchar VARCHAR( 10000 ) NOT NULL' );
		// Modify Indexes
		$DB->query( 'ALTER TABLE T_users__fields
						DROP INDEX uf_varchar,
						ADD INDEX uf_varchar ( uf_varchar )' );
		task_end();

		task_begin( 'Upgrading cron tasks table...' );
		$DB->query( 'ALTER TABLE T_cron__task CHANGE ctsk_name ctsk_name VARCHAR(255) NOT NULL' );
		task_end();

		task_begin( 'Upgrading comments table...' );
		db_add_col( 'T_comments', 'comment_IP_ctry_ID', 'int(10) unsigned NULL AFTER comment_author_IP' );
		task_end();

		task_begin( 'Creating table for Blocked Email Addreses...' );
		$DB->query( "CREATE TABLE T_email__blocked (
			emblk_ID                    INT(10) UNSIGNED NOT NULL auto_increment,
			emblk_address               VARCHAR(255) DEFAULT NULL,
			emblk_status                ENUM ( 'unknown', 'warning', 'suspicious1', 'suspicious2', 'suspicious3', 'prmerror', 'spammer' ) NOT NULL DEFAULT 'unknown',
			emblk_sent_count            INT(10) UNSIGNED NOT NULL DEFAULT 0,
			emblk_sent_last_returnerror INT(10) UNSIGNED NOT NULL DEFAULT 0,
			emblk_prmerror_count        INT(10) UNSIGNED NOT NULL DEFAULT 0,
			emblk_tmperror_count        INT(10) UNSIGNED NOT NULL DEFAULT 0,
			emblk_spamerror_count       INT(10) UNSIGNED NOT NULL DEFAULT 0,
			emblk_othererror_count      INT(10) UNSIGNED NOT NULL DEFAULT 0,
			emblk_last_sent_ts          TIMESTAMP NULL,
			emblk_last_error_ts         TIMESTAMP NULL,
			PRIMARY KEY                 (emblk_ID),
			UNIQUE                      emblk_address (emblk_address)
		) ENGINE = myisam" );
		task_end();

		task_begin( 'Upgrading email log table...' );
		// Get old values of emlog_success field
		$SQL = new SQL();
		$SQL->SELECT( 'emlog_ID' );
		$SQL->FROM( 'T_email__log' );
		$SQL->WHERE( 'emlog_success = 0' );
		$email_failed_logs = $DB->get_col( $SQL->get() );
		// Change a field emlog_success to new format
		$DB->query( 'ALTER TABLE T_email__log CHANGE emlog_success emlog_result ENUM ( "ok", "error", "blocked" ) NOT NULL DEFAULT "ok"' );
		if( !empty( $email_failed_logs ) )
		{	// Update only the failed email logs to new values
			// Do NOT update the success email logs, because we already have a result type 'ok' as default
			$DB->query( 'UPDATE T_email__log
					SET emlog_result = '.$DB->quote( 'error' ).'
				WHERE emlog_ID IN ( '.$DB->quote( $email_failed_logs ).' )' );
		}
		task_end();

		/*
		 * ADD UPGRADES FOR i3 BRANCH __ABOVE__ IN THIS BLOCK.
		 *
		 * This part will be included in trunk and i3 branches
		 */

		set_upgrade_checkpoint( '10900' );
	}

	if( $old_db_version < 11000 )
	{	// part 8 trunk aka first part of "i4"

		task_begin( 'Upgrading Locales table...' );
		db_add_col( 'T_locales', 'loc_transliteration_map', 'VARCHAR(10000) NOT NULL default \'\' AFTER loc_priority' );
		task_end();

		task_begin( 'Upgrading general settings table...' );
		$DB->query( 'UPDATE T_settings SET set_name = '.$DB->quote( 'smart_view_count' ).' WHERE set_name = '.$DB->quote( 'smart_hit_count' ) );
		task_end();

		task_begin( 'Upgrading sessions table...' );
		$DB->query( "ALTER TABLE T_sessions CHANGE COLUMN sess_lastseen sess_lastseen_ts TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00' COMMENT 'User last logged activation time. Value may be off by up to 60 seconds'" );
		db_add_col( 'T_sessions', 'sess_start_ts', "TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00' AFTER sess_hitcount" );
		$DB->query( 'UPDATE T_sessions SET sess_start_ts = TIMESTAMPADD( SECOND, -1, sess_lastseen_ts )' );
		db_drop_col( 'T_sessions', 'sess_hitcount' );
		task_end();

		task_begin( 'Upgrading users table...' );
		db_add_col( 'T_users', 'user_lastseen_ts', 'TIMESTAMP NULL AFTER user_created_datetime' );
		$DB->query( 'UPDATE T_users SET user_lastseen_ts = ( SELECT MAX( sess_lastseen_ts ) FROM T_sessions WHERE sess_user_ID = user_ID )' );
		$DB->query( 'UPDATE T_users SET user_profileupdate_ts = user_created_datetime WHERE user_profileupdate_ts < user_created_datetime' );
		$DB->query( "ALTER TABLE T_users CHANGE COLUMN user_profileupdate_ts user_profileupdate_date DATE NOT NULL DEFAULT '2000-01-01' COMMENT 'Last day when the user has updated some visible field in his profile.'" );
		task_end();

		task_begin( 'Updating versions table...' );
		db_add_col( 'T_items__version', 'iver_ID', 'INT UNSIGNED NOT NULL FIRST' );
		$DB->query( 'ALTER TABLE T_items__version DROP INDEX iver_itm_ID, ADD INDEX iver_ID_itm_ID ( iver_ID , iver_itm_ID )' );
		task_end();

		task_begin( 'Upgrading messaging contact group users...' );
		db_add_foreign_key( 'T_messaging__contact_groupusers', 'cgu_cgr_ID', 'T_messaging__contact_groups', 'cgr_ID', 'ON DELETE CASCADE' );
		task_end();

		task_begin( 'Creating table for a latest version of the POT file...' );
		$DB->query( "CREATE TABLE T_i18n_original_string (
			iost_ID        int(10) unsigned NOT NULL auto_increment,
			iost_string    varchar(10000) NOT NULL default '',
			iost_inpotfile tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (iost_ID)
		) ENGINE = innodb" );
		task_end();

		task_begin( 'Creating table for a latest versions of the PO files...' );
		$DB->query( "CREATE TABLE T_i18n_translated_string (
			itst_ID       int(10) unsigned NOT NULL auto_increment,
			itst_iost_ID  int(10) unsigned NOT NULL,
			itst_locale   varchar(20) NOT NULL default '',
			itst_standard varchar(10000) NOT NULL default '',
			itst_custom   varchar(10000) NULL,
			itst_inpofile tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (itst_ID)
		) ENGINE = innodb DEFAULT CHARSET = utf8" );
		task_end();

		task_begin( 'Updating Antispam IP Ranges table...' );
		db_add_col( 'T_antispam__iprange', 'aipr_status', 'enum( \'trusted\', \'suspect\', \'blocked\' ) NULL DEFAULT NULL' );
		db_add_col( 'T_antispam__iprange', 'aipr_block_count', 'int(10) unsigned DEFAULT 0' );
		$DB->query( "ALTER TABLE T_antispam__iprange CHANGE COLUMN aipr_user_count aipr_user_count int(10) unsigned DEFAULT 0" );
		task_end();

		task_begin( 'Creating default antispam IP ranges... ' );
		$DB->query( '
			INSERT INTO T_antispam__iprange ( aipr_IPv4start, aipr_IPv4end, aipr_status )
			VALUES ( '.$DB->quote( ip2int( '127.0.0.0' ) ).', '.$DB->quote( ip2int( '127.0.0.255' ) ).', "trusted" ),
				( '.$DB->quote( ip2int( '10.0.0.0' ) ).', '.$DB->quote( ip2int( '10.255.255.255' ) ).', "trusted" ),
				( '.$DB->quote( ip2int( '172.16.0.0' ) ).', '.$DB->quote( ip2int( '172.31.255.255' ) ).', "trusted" ),
				( '.$DB->quote( ip2int( '192.168.0.0' ) ).', '.$DB->quote( ip2int( '192.168.255.255' ) ).', "trusted" )
			' );
		task_end();


		task_begin( 'Adding new countries...' );
		// IGNORE is needed for upgrades from DB version 9970 or later
		$DB->query( 'INSERT IGNORE INTO T_regional__country ( ctry_code, ctry_name, ctry_curr_ID ) VALUES ( \'ct\', \'Catalonia\', \'2\' )' );
		task_end();

		task_begin( 'Upgrading message thread statuses table...' );
		db_add_col( 'T_messaging__threadstatus', 'tsta_thread_leave_msg_ID', 'int(10) unsigned NULL DEFAULT NULL' );
		task_end();

		task_begin( 'Upgrading Item Settings...' );
		// Convert item custom fields to custom item settings ( move custom fields from T_items__item table to T_items__item_settings table )
		$query = "INSERT INTO T_items__item_settings( iset_item_ID, iset_name, iset_value ) ";
		for( $i = 1; $i <= 8; $i++ )
		{ // For each custom fields:
			if( $i > 1 )
			{
				$query .= ' UNION';
			}
			$field_name = ( $i > 5 ) ? 'varchar'.( $i - 5 ) : 'double'.$i;
			$query .= " SELECT post_ID, 'custom_".$field_name."', post_".$field_name."
							FROM T_items__item WHERE post_".$field_name." IS NOT NULL";
		}
		$DB->query( $query );

		for( $i = 1; $i <= 5; $i++ )
		{ // drop custom double columns from items tabe
			db_drop_col( 'T_items__item', 'post_double'.$i );
		}
		for( $i = 1; $i <= 3; $i++ )
		{ // drop custom varchar columns from items tabe
			db_drop_col( 'T_items__item', 'post_varchar'.$i );
		}

		// Convert post_editor_code item field to item settings
		$DB->query( 'INSERT INTO T_items__item_settings ( iset_item_ID, iset_name, iset_value )
						SELECT post_ID, "editor_code", post_editor_code
							FROM T_items__item
							WHERE post_editor_code IS NOT NULL' );
		db_drop_col( 'T_items__item', 'post_editor_code' );

		// Convert post_metadesc item field to item settings
		$DB->query( 'INSERT INTO T_items__item_settings ( iset_item_ID, iset_name, iset_value )
						SELECT post_ID, "post_metadesc", post_metadesc
							FROM T_items__item
							WHERE post_metadesc IS NOT NULL' );
		db_drop_col( 'T_items__item', 'post_metadesc' );

		// Convert and rename post_metakeywords item field to post_custom_headers item settings
		$DB->query( 'INSERT INTO T_items__item_settings ( iset_item_ID, iset_name, iset_value )
						SELECT post_ID, "post_custom_headers", post_metakeywords
							FROM T_items__item
							WHERE post_metakeywords IS NOT NULL' );
		db_drop_col( 'T_items__item', 'post_metakeywords' );
		task_end();

		task_begin( 'Upgrading items table...' );
		// Drop not used column
		db_drop_col( 'T_items__item', 'post_commentsexpire' );
		task_end();

		task_begin( 'Adding new video file types...' );
		$ftyp = $DB->get_row('SELECT ftyp_ID, ftyp_extensions
									FROM T_filetypes
									WHERE ftyp_mimetype = "video/mp4"
									AND ftyp_extensions NOT LIKE "%f4v%"
									LIMIT 1');

		if( $ftyp )
		{	// Add f4v extension to mp4 file type, if not exists
			$DB->query( 'UPDATE T_filetypes SET ftyp_extensions = "'.$DB->escape($ftyp->ftyp_extensions.' f4v').'"
							WHERE ftyp_ID = '.$DB->quote($ftyp->ftyp_ID) );
		}
		// Add flv file type if not exists
		if( !db_key_exists( 'T_filetypes', 'ftyp_extensions', '"flv"' ) )
		{
			$DB->query( 'INSERT INTO T_filetypes (ftyp_extensions, ftyp_name, ftyp_mimetype, ftyp_icon, ftyp_viewtype, ftyp_allowed)
				             VALUES ("flv", "Flash video file", "video/x-flv", "", "browser", "registered")', 'Add "flv" file type' );
		}
		// Add swf file type if not exists
		if( !db_key_exists( 'T_filetypes', 'ftyp_extensions', '"swf"' ) )
		{
			$DB->query( 'INSERT INTO T_filetypes (ftyp_extensions, ftyp_name, ftyp_mimetype, ftyp_icon, ftyp_viewtype, ftyp_allowed)
				             VALUES ("swf", "Flash video file", "application/x-shockwave-flash", "", "browser", "registered")', 'Add "swf" file type' );
		}
		task_end();

		task_begin( 'Upgrading custom item settings...' );
		$DB->begin();
		// Convert latitude and longitude from custom setting to normal item settings
		// Get those blog ids where Latitude and Longitude are both set
		$result = $DB->get_col( 'SELECT cs_left.cset_coll_ID
									FROM T_coll_settings as cs_left
									INNER JOIN T_coll_settings as cs_right ON cs_left.cset_coll_ID = cs_right.cset_coll_ID
									WHERE cs_left.cset_name = "custom_double3" AND cs_left.cset_value = "Latitude" AND
										cs_right.cset_name = "custom_double4" AND cs_right.cset_value = "Longitude"' );
		if( $result )
		{ // blogs were found where Latitude and Longitude custom fields were set for google maps plugin
			// Set "Show location coordinates" on where Latitude and Longitude were set
			$query_values = '( '.implode( ', "show_location_coordinates", 1 ), ( ', $result ).', "show_location_coordinates", 1 )';
			$DB->query( 'INSERT INTO T_coll_settings( cset_coll_ID, cset_name, cset_value )
							VALUES '.$query_values );

			$coll_ids = implode( ', ', $result );
			// Update latitude Item settings
			$DB->query( 'UPDATE T_items__item_settings SET iset_name = "latitude"
							WHERE iset_name = "custom_double3" AND iset_item_ID IN (
								SELECT post_ID FROM T_items__item
								INNER JOIN T_categories ON post_main_cat_ID = cat_ID
								WHERE cat_blog_ID IN ( '.$coll_ids.' )
							)' );
			// Update longitude Item settings
			$DB->query( 'UPDATE T_items__item_settings SET iset_name = "longitude"
							WHERE iset_name = "custom_double4" AND iset_item_ID IN (
								SELECT post_ID FROM T_items__item
								INNER JOIN T_categories ON post_main_cat_ID = cat_ID
								WHERE cat_blog_ID IN ( '.$coll_ids.' )
							)' );
			// Delete proessed latitude & longitude custom fields from collection settings
			$DB->query( 'DELETE FROM T_coll_settings
						WHERE ( cset_name = "custom_double3" OR cset_name = "custom_double4" ) AND
							cset_coll_ID IN ( '.$coll_ids.' )' );
		}
		$DB->commit(); // End convert latitude and longitude

		$DB->begin(); // Convert custom fields
		// Delete not used custom fields
		$DB->query( 'DELETE FROM T_coll_settings WHERE ( cset_value IS NULL OR cset_value = "" ) AND cset_name LIKE "custom\_%"' );
		// Set custom double fields count
		$DB->query( 'INSERT INTO T_coll_settings( cset_coll_ID, cset_name, cset_value )
						SELECT cset_coll_ID, "count_custom_double", COUNT( cset_name )
						FROM T_coll_settings
						WHERE cset_name LIKE "custom\_double%"
						GROUP BY cset_coll_ID' );
		// Set custom varchar fields count
		$DB->query( 'INSERT INTO T_coll_settings( cset_coll_ID, cset_name, cset_value )
						SELECT cset_coll_ID, "count_custom_varchar", COUNT( cset_name )
						FROM T_coll_settings
						WHERE cset_name LIKE "custom\_varchar%"
						GROUP BY cset_coll_ID' );
		// Select all custom fields from all blog, to create converted field values
		$result = $DB->get_results( 'SELECT cset_coll_ID as coll_ID, cset_name as name, cset_value as value
										FROM T_coll_settings
										WHERE cset_name LIKE "custom\_%"
										ORDER BY cset_coll_ID, cset_name' );
		if( !empty( $result ) )
		{ // There are custom fields in blog settings
			$convert_field_values = '';
			$reorder_field_values = '';
			$old_prefix = "";
			$old_coll_ID = "";
			foreach( $result as $row )
			{ // process each custom field
				$custom_id = uniqid( '' );
				$prefix = ( substr( $row->name, 7, 6 ) === 'double' ) ? 'custom_double' : 'custom_varchar';
				// replace custom_double{N} and custom_varchar{N} values with a custom_id where N is number
				$convert_field_values .= '( '.$row->coll_ID.', "'.$row->name.'", "'.$custom_id.'" ), ';
				// add new custom_double_{customid} and custom_varchar_{customid} entries with the old correspinding custom field values
				$convert_field_values .= '( '.$row->coll_ID.', "'.$prefix.'_'.$custom_id.'", "'.$row->value.'" ), ';

				// create reorder values to replace e.g. custom_double2 to custom_double1 if custom_double1 doesn't exists yet
				$index = ( ( $old_prefix == $prefix ) && ( $old_coll_ID == $row->coll_ID ) ) ? $index + 1 : 1;
				$reorder_field_values .= '( '.$row->coll_ID.', "'.$prefix.$index.'", "'.$custom_id.'" ), ';
				$old_prefix = $prefix;
				$old_coll_ID = $row->coll_ID;
			}
			$convert_field_values = substr( $convert_field_values, 0, -2 );
			$reorder_field_values = substr( $reorder_field_values, 0, -2 );
			// Convert custom fields in collection setting
			$DB->query( 'REPLACE INTO T_coll_settings( cset_coll_ID, cset_name, cset_value )
							VALUES '.$convert_field_values );
			// Update double custom field name_ids in item settings table
			$DB->query( 'UPDATE T_items__item_settings SET iset_name = (
								SELECT CONCAT( "custom_double_", cset_value ) FROM T_coll_settings
									INNER JOIN T_categories ON cset_coll_ID = cat_blog_ID
									INNER JOIN T_items__item ON cat_ID = post_main_cat_ID
									WHERE cset_name = iset_name AND post_ID  = iset_item_ID )
							WHERE iset_name LIKE "custom\_double%"' );
			// Update varchar custom field name_ids in item settings table
			$DB->query( 'UPDATE T_items__item_settings SET iset_name = (
								SELECT CONCAT( "custom_varchar_", cset_value ) FROM T_coll_settings
									INNER JOIN T_categories ON cset_coll_ID = cat_blog_ID
									INNER JOIN T_items__item ON cat_ID = post_main_cat_ID
									WHERE cset_name = iset_name AND post_ID  = iset_item_ID )
							WHERE iset_name LIKE "custom\_varchar%"' );
			// Reorder custom fields in collection settings
			$DB->query( 'REPLACE INTO T_coll_settings( cset_coll_ID, cset_name, cset_value )
							VALUES '.$reorder_field_values );
		}
		$DB->commit(); // End convert custom fields
		task_end();

		task_begin( 'Convert group users permissions to pluggable permissions...' );
		$DB->query( 'REPLACE INTO T_groups__groupsettings( gset_grp_ID, gset_name, gset_value )
						SELECT grp_ID, "perm_users", grp_perm_users
							FROM T_groups' );
		db_drop_col( 'T_groups', 'grp_perm_users' );
		task_end();

		task_begin( 'Update Post Types... ' );
		$DB->query( "REPLACE INTO T_items__type ( ptyp_ID, ptyp_name )
			VALUES ( 4000, 'Advertisement' )" );
		task_end();

		task_begin( 'Update files table... ' );
		db_add_col( 'T_files', 'file_hash', 'char(32) default NULL' );
		task_end();

		task_begin( 'Create table for files voting... ' );
		$DB->query( 'CREATE TABLE T_files__vote (
				fvot_file_ID       int(11) UNSIGNED NOT NULL,
				fvot_user_ID       int(11) UNSIGNED NOT NULL,
				fvot_like          tinyint(1),
				fvot_inappropriate tinyint(1),
				fvot_spam          tinyint(1),
				primary key (fvot_file_ID, fvot_user_ID)
			) ENGINE = innodb' );
		task_end();

		task_begin( 'Create table for users reporting...' );
		$DB->query( "CREATE TABLE T_users__reports (
			urep_target_user_ID int(11) unsigned NOT NULL,
			urep_reporter_ID    int(11) unsigned NOT NULL,
			urep_status         enum( 'fake', 'guidelines', 'harass', 'spam', 'other' ),
			urep_info           varchar(240),
			urep_datetime		datetime NOT NULL,
			PRIMARY KEY ( urep_target_user_ID, urep_reporter_ID )
		) ENGINE = innodb" );
		task_end();

		task_begin( 'Upgrading skins type...' );
		$DB->query( "ALTER TABLE T_skins__skin MODIFY COLUMN skin_type enum('normal','feed','sitemap','mobile','tablet') NOT NULL default 'normal'" );
		task_end();

		task_begin( 'Upgrading blogs skins...' );
		// Convert blog skin ID to blog settings
		$DB->query( 'INSERT INTO T_coll_settings( cset_coll_ID, cset_name, cset_value )
						SELECT blog_ID, "normal_skin_ID", blog_skin_ID
						FROM T_blogs' );
		db_drop_col( 'T_blogs', 'blog_skin_ID' );
		task_end();

		task_begin( 'Update categories table... ' );
		db_add_col( 'T_categories', 'cat_meta', 'tinyint(1) NOT NULL DEFAULT 0' );
		db_add_col( 'T_categories', 'cat_lock', 'tinyint(1) NOT NULL DEFAULT 0' );
		task_end();

		task_begin( 'Plugin settings update...' );
		$all_blog_ids = $DB->get_col( 'SELECT blog_ID FROM T_blogs' );
		$plugin_ids = $DB->get_assoc( 'SELECT pset_plug_ID, pset_value FROM T_pluginsettings WHERE pset_name = "render_comments"' );
		$insert_values = '';
		foreach( $all_blog_ids as $blog_ID )
		{
			foreach( $plugin_ids as $plugin_ID => $setting_value )
			{
				$apply_comment_rendering = $setting_value ? 'stealth' : 'never';
				$insert_values .= '( '.$blog_ID.', "plugin'.$plugin_ID.'_coll_apply_comment_rendering", "'.$apply_comment_rendering.'" ),';
			}
		}
		if( !empty( $insert_values ) )
		{
			$DB->query( 'INSERT INTO T_coll_settings( cset_coll_ID, cset_name, cset_value )
							VALUES '.substr( $insert_values, 0, strlen( $insert_values ) - 1 ) );
		}
		$DB->query( 'DELETE FROM T_pluginsettings WHERE pset_name = "render_comments"' );
		task_end();

		task_begin( 'Creating comment prerendering cache table...' );
		$DB->query( "CREATE TABLE T_comments__prerendering (
			cmpr_cmt_ID INT(11) UNSIGNED NOT NULL,
			cmpr_format ENUM('htmlbody','entityencoded','xml','text') NOT NULL,
			cmpr_renderers TEXT NOT NULL,
			cmpr_content_prerendered MEDIUMTEXT NULL,
			cmpr_datemodified TIMESTAMP NOT NULL,
			PRIMARY KEY (cmpr_cmt_ID, cmpr_format)
		) ENGINE = innodb" );
		db_add_col( 'T_comments', 'comment_renderers', "TEXT NOT NULL AFTER comment_content" );
		$DB->query( 'UPDATE T_comments SET comment_renderers = "default"' );
		task_end();

		task_begin( 'Upgrading plugins table...' );
		db_drop_col( 'T_plugins', 'plug_apply_rendering' );
		task_end();

		task_begin( 'Upgrading Auto_P plugin...' );
		$blog_settings = $DB->get_assoc( 'SELECT cset_coll_ID, cset_value FROM T_coll_settings WHERE cset_name = "comment_autobr"' );
		$insert_values = array();
		$plugin_ids = $DB->get_col( 'SELECT plug_ID FROM T_plugins WHERE plug_code = "b2WPAutP"' );
		foreach( $blog_settings as $blog_ID => $blog_setting_value )
		{
			foreach( $plugin_ids as $plugin_ID )
			{
				switch( $blog_setting_value )
				{
					case 'never':
						$apply_comment_rendering = 'never';
						break;
					case 'optional':
						$apply_comment_rendering = 'opt-out';
						break;
					case 'always':
						$apply_comment_rendering = 'stealth';
						break;
					default:
						break 2;
				}
				$insert_values[] = '( '.$blog_ID.', "plugin'.$plugin_ID.'_coll_apply_comment_rendering", "'.$apply_comment_rendering.'" )';
			}
		}
		if( count( $insert_values ) > 0 )
		{
			$DB->query( 'REPLACE INTO T_coll_settings( cset_coll_ID, cset_name, cset_value )
						VALUES '.implode( ',', $insert_values ) );
		}
		$DB->query( 'DELETE FROM T_coll_settings WHERE cset_name = "comment_autobr"' );
		$DB->query( 'UPDATE T_comments SET comment_content = REPLACE( REPLACE( comment_content, "<br>\n", "\n" ), "<br />\n", "\n" )' );
		task_end();

		set_upgrade_checkpoint( '11000' );
	}

	if( $old_db_version < 11010 )
	{	// part 9 trunk aka second part of "i4"

		task_begin( 'Upgrading post statuses...' );
		$DB->query( "ALTER TABLE T_items__item CHANGE COLUMN post_status post_status enum('published','community','deprecated','protected','private','review','draft','redirected') NOT NULL default 'published'" );
		$DB->query( "ALTER TABLE T_items__version CHANGE COLUMN iver_status iver_status ENUM('published','community','deprecated','protected','private','review','draft','redirected') NULL" );
		$DB->query( "ALTER TABLE T_coll_user_perms CHANGE COLUMN bloguser_perm_poststatuses bloguser_perm_poststatuses set('review','draft','private','protected','deprecated','community','published','redirected') NOT NULL default ''" );
		$DB->query( "ALTER TABLE T_coll_group_perms CHANGE COLUMN bloggroup_perm_poststatuses bloggroup_perm_poststatuses set('review','draft','private','protected','deprecated','community','published','redirected') NOT NULL default ''" );
		task_end();

		task_begin( 'Upgrading groups table...' );
		$pbloggers_renamed_to_moderators = $DB->query( 'UPDATE T_groups SET grp_name = "Moderators" WHERE grp_ID = 2 AND grp_name = "Privileged Bloggers"' );
		// Update administrators and moderators users coll setting permissions with new permissions
		// Note we can change moderators permission if the group name and ID was not changed after the original install
		$moderators_condition = $pbloggers_renamed_to_moderators ? ' OR bloggroup_group_ID = 2' : '';
		$DB->query( "UPDATE T_coll_group_perms SET bloggroup_perm_poststatuses = 'published,community,deprecated,protected,private,review,draft' WHERE bloggroup_group_ID = 1".$moderators_condition );
		// Change groups name
		$DB->query( 'UPDATE T_groups SET grp_name = "Trusted Users" WHERE grp_ID = 3 AND grp_name = "Bloggers"' );
		$DB->query( 'UPDATE T_groups SET grp_name = "Normal Users" WHERE grp_ID = 4 AND grp_name = "Basic Users"' );

		// Get "Misbehaving/Suspect Users" group ID
		$suspect_query = 'SELECT grp_ID
						FROM T_groups
						WHERE grp_name = "Misbehaving/Suspect Users"
						ORDER BY grp_ID DESC
						LIMIT 1';
		$suspect_group_ID = $DB->get_var( $suspect_query );
		if( empty( $suspect_group_ID ) )
		{ // suspect group doesn't exists, check spammers because probably it does not exists either
			$insert_values = '( "Misbehaving/Suspect Users" )';
			// Get "Spammers/Restricted Users" group ID
			$query = 'SELECT grp_ID
					FROM T_groups
					WHERE grp_name = "Spammers/Restricted Users"
					ORDER BY grp_ID DESC
					LIMIT 1';
			$spammers_group_ID = $DB->get_var( $query );
			if( empty( $spammers_group_ID ) )
			{
				$insert_values .= ', ( "Spammers/Restricted Users" )';
			}
			// Insert two new group
			$DB->query( 'INSERT INTO T_groups ( grp_name )
						VALUES '.$insert_values );

			$suspect_group_ID = $DB->get_var( $suspect_query );
			if( $suspect_group_ID )
			{ // Set coll setting permissions for Misbehaving/Suspect Users in Forums
				$query = "
					INSERT INTO T_coll_group_perms( bloggroup_blog_ID, bloggroup_group_ID, bloggroup_ismember,
						bloggroup_perm_poststatuses, bloggroup_perm_delpost, bloggroup_perm_edit_ts,
						bloggroup_perm_own_cmts, bloggroup_perm_vote_spam_cmts, bloggroup_perm_draft_cmts, bloggroup_perm_publ_cmts, bloggroup_perm_depr_cmts,
						bloggroup_perm_cats, bloggroup_perm_properties,
						bloggroup_perm_media_upload, bloggroup_perm_media_browse, bloggroup_perm_media_change )
					SELECT blog_ID, ".$suspect_group_ID.", 1, 'review,draft', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0
						FROM T_blogs WHERE blog_ID = 5 AND blog_shortname = 'Forums'";
				$DB->query( $query );
			}
		}
		task_end();

		task_begin( 'Upgrading blogs table...' );
		db_add_col( 'T_blogs', 'blog_type', 'ENUM( "std", "photo", "group", "forum", "manual" ) DEFAULT "std" NOT NULL' );
		task_end();

		task_begin( 'Upgrading comment statuses...' );
		$DB->query( "ALTER TABLE T_comments CHANGE COLUMN comment_status comment_status ENUM('published','community','deprecated','protected','private','review','draft','trash') DEFAULT 'published' NOT NULL" );
		task_end();

		task_begin( 'Updating collection user/group permissions...' );
		db_add_col( 'T_coll_user_perms', 'bloguser_perm_cmtstatuses', "set('review','draft','private','protected','deprecated','community','published') NOT NULL default '' AFTER bloguser_perm_vote_spam_cmts" );
		db_add_col( 'T_coll_user_perms', 'bloguser_perm_edit_cmt', "ENUM('no','own','anon','lt','le','all') NOT NULL default 'no' AFTER bloguser_perm_cmtstatuses" );
		db_add_col( 'T_coll_group_perms', 'bloggroup_perm_cmtstatuses', "set('review','draft','private','protected','deprecated','community','published') NOT NULL default '' AFTER bloggroup_perm_vote_spam_cmts" );
		db_add_col( 'T_coll_group_perms', 'bloggroup_perm_edit_cmt', "ENUM('no','own','anon','lt','le','all') NOT NULL default 'no' AFTER bloggroup_perm_cmtstatuses" );

		// Add access to those comment statuses for what user had before
		$DB->query( 'UPDATE T_coll_user_perms
					SET bloguser_perm_cmtstatuses = ( bloguser_perm_publ_cmts * 1 ) | ( bloguser_perm_depr_cmts * 4 ) | ( bloguser_perm_draft_cmts * 64 )' );
		// Add access to all cmt statuses for those users which had edit permission on all comment statuses
		$DB->query( 'UPDATE T_coll_user_perms
					SET bloguser_perm_cmtstatuses = "published,community,deprecated,protected,private,review,draft", bloguser_perm_edit_cmt = "all"
					WHERE bloguser_perm_publ_cmts <> 0 AND bloguser_perm_depr_cmts <> 0 AND bloguser_perm_draft_cmts <> 0' );
		// Add "lower then" edit permission to those users who had permission to edit published or draft comments
		$DB->query( 'UPDATE T_coll_user_perms
					SET bloguser_perm_edit_cmt = "lt"
					WHERE ( bloguser_perm_cmtstatuses & 65 ) != 0 AND bloguser_perm_edit_cmt = "no"' );

		// Add access to those comment statuses for what group had before
		$DB->query( 'UPDATE T_coll_group_perms
					SET bloggroup_perm_cmtstatuses = ( bloggroup_perm_publ_cmts * 1 ) | ( bloggroup_perm_depr_cmts * 4 ) | ( bloggroup_perm_draft_cmts * 64 )' );
		// Add access to all cmt statuses for those groups which had edit permission on all comment statuses
		$DB->query( 'UPDATE T_coll_group_perms
					SET bloggroup_perm_cmtstatuses = "published,community,deprecated,protected,private,review,draft", bloggroup_perm_edit_cmt = "all"
					WHERE bloggroup_perm_publ_cmts <> 0 AND bloggroup_perm_depr_cmts <> 0 AND bloggroup_perm_draft_cmts <> 0' );
		// Add "lower then" edit permission to those groups who had permission to edit published or draft comments
		$DB->query( 'UPDATE T_coll_group_perms
					SET bloggroup_perm_edit_cmt = "lt"
					WHERE ( bloggroup_perm_cmtstatuses & 65 ) != 0 AND bloggroup_perm_edit_cmt = "no"' );

		db_drop_col( 'T_coll_user_perms', 'bloguser_perm_draft_cmts' );
		db_drop_col( 'T_coll_user_perms', 'bloguser_perm_publ_cmts' );
		db_drop_col( 'T_coll_user_perms', 'bloguser_perm_depr_cmts' );
		db_drop_col( 'T_coll_group_perms', 'bloggroup_perm_draft_cmts' );
		db_drop_col( 'T_coll_group_perms', 'bloggroup_perm_publ_cmts' );
		db_drop_col( 'T_coll_group_perms', 'bloggroup_perm_depr_cmts' );

		db_add_col( 'T_coll_user_perms', 'bloguser_perm_delcmts', 'tinyint NOT NULL default 0 AFTER bloguser_perm_edit_ts' );
		db_add_col( 'T_coll_group_perms', 'bloggroup_perm_delcmts', 'tinyint NOT NULL default 0 AFTER bloggroup_perm_edit_ts' );
		// GRANT delete comment perms for moderators
		$DB->query( 'UPDATE T_coll_group_perms
					SET bloggroup_perm_delcmts = 1
					WHERE bloggroup_perm_edit_cmt = "le" OR bloggroup_perm_edit_cmt = "all"' );

		$DB->query( "ALTER TABLE T_coll_user_perms CHANGE COLUMN bloguser_perm_own_cmts bloguser_perm_recycle_owncmts tinyint NOT NULL default 0" );
		$DB->query( "ALTER TABLE T_coll_group_perms CHANGE COLUMN bloggroup_perm_own_cmts bloggroup_perm_recycle_owncmts tinyint NOT NULL default 0" );
		task_end();

		task_begin( 'Updating blogs settings...' );
		$DB->query( 'UPDATE T_coll_settings SET cset_value = "blog" WHERE cset_name = "enable_goto_blog" AND cset_value = "1"' );
		$DB->query( 'UPDATE T_coll_settings SET cset_value = "no" WHERE cset_name = "enable_goto_blog" AND cset_value = "0"' );
		task_end();

		/*
		 * ADD UPGRADES FOR i4 BRANCH __ABOVE__ IN THIS BLOCK.
		 *
		 * This part will be included in trunk and i4 branches
		 */

		set_upgrade_checkpoint( '11010' );
	}

	if( $old_db_version < 11020 )
	{	// part 10 trunk aka third part of "i4"

		task_begin( 'Upgrading users table...' );
		// Get all users with defined IPs
		$users_SQL = new SQL();
		$users_SQL->SELECT( 'user_ID, user_created_fromIPv4' );
		$users_SQL->FROM( 'T_users' );
		$users_SQL->WHERE( 'user_created_fromIPv4 IS NOT NULL' );
		$users_SQL->WHERE_and( 'user_created_fromIPv4 != '.$DB->quote( ip2int( '0.0.0.0' ) ) );
		$users_SQL->WHERE_and( 'user_created_fromIPv4 != '.$DB->quote( ip2int( '127.0.0.1' ) ) );
		$users = $DB->get_assoc( $users_SQL->get() );
		// Get user's IPs from settings table
		$settings_SQL = new SQL();
		$settings_SQL->SELECT( 'uset_user_ID, uset_value' );
		$settings_SQL->FROM( 'T_users__usersettings' );
		$settings_SQL->WHERE( 'uset_name = "user_ip"' );
		if( count( $users ) > 0 )
		{	// Get IPs only for users which have not IP in T_users table
			$settings_SQL->WHERE_and( 'uset_user_ID NOT IN ('.$DB->quote( array_keys( $users ) ).')' );
		}
		$settings = $DB->get_assoc( $settings_SQL->get() );
		if( count( $users ) > 0 || count( $settings ) > 0 )
		{
			$users_settings_insert_sql = array();
			foreach( $users as $user_ID => $user_IP )
			{
				$users_settings_insert_sql[] = '( '.$DB->quote( $user_ID ).', "created_fromIPv4", '.$DB->quote( $user_IP ).' )';
			}
			foreach( $settings as $user_ID => $user_IP )
			{
				$users_settings_insert_sql[] = '( '.$DB->quote( $user_ID ).', "created_fromIPv4", '.$DB->quote( ip2int( $user_IP ) ).' )';
			}
			// Insert IPs values into settings table
			$DB->query( 'INSERT INTO T_users__usersettings ( uset_user_ID, uset_name, uset_value )
				VALUES '.implode( ', ', $users_settings_insert_sql ) );
		}
		// Remove old IPs from settings table
		$DB->query( 'DELETE FROM T_users__usersettings
			WHERE uset_name = "user_ip"' );
		db_drop_col( 'T_users', 'user_created_fromIPv4' );
		task_end();

		/*
		 * ADD UPGRADES FOR i4 BRANCH __ABOVE__ IN THIS BLOCK.
		 *
		 * This part will be included in trunk and i4 branches
		 */

		set_upgrade_checkpoint( '11020' );
	}

	if( $old_db_version < 11025 )
	{	// part 11 trunk aka fourth part of "i4"

		task_begin( 'Upgrading items table...' );
		flush();
		$DB->query( "ALTER TABLE T_items__item CHANGE COLUMN post_datecreated post_datecreated TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00'" );
		$DB->query( "ALTER TABLE T_items__item CHANGE COLUMN post_datemodified post_datemodified TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00'" );
		db_add_col( 'T_items__item', 'post_last_touched_ts', "TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00' AFTER post_datemodified" );
		$DB->query( 'UPDATE T_items__item SET post_last_touched_ts = post_datemodified' );
		task_end();

		/*
		 * ADD UPGRADES FOR i4 BRANCH __ABOVE__ IN THIS BLOCK.
		 *
		 * This part will be included in trunk and i4 branches
		 */

		set_upgrade_checkpoint( '11025' );
	}

	// In some upgrade versions ( currently only in "i5" ) we would like to create profile pictures links from the user's files in the profile pictures folder
	// To be able to do that we need an up to date database version, so we will create profile pictures after the ugrade script run successfully.
	// Set this $create_profile_picture_links to true only in those upgrade block where it's required.
	$create_profile_picture_links = false;

	if( $old_db_version < 11100 )
	{	// part 12 trunk aka "i5"

		task_begin( 'Update links table...' );
		db_add_col( 'T_links', 'link_usr_ID', 'int(11) unsigned  NULL COMMENT "Used for linking files to users (user profile picture)" AFTER link_cmt_ID' );
		db_add_index( 'T_links', 'link_usr_ID', 'link_usr_ID' );
		task_end();

		task_begin( 'Creating links for users profile pictures...' );
		// Create links for main profile pictures
		$link_create_date = date2mysql( time() );
		$DB->query( 'INSERT INTO T_links( link_datecreated, link_datemodified, link_usr_ID, link_file_ID, link_position, link_order )
						SELECT '.$DB->quote( $link_create_date ).', '.$DB->quote( $link_create_date ).', user_ID, user_avatar_file_ID, "", 1
						FROM T_users
						WHERE user_avatar_file_ID IS NOT NULL' );
		// Set $create_profile_picture_links to true to create links for all files from the users profile_pictures folder
		$create_profile_picture_links = true;
		task_end();

		task_begin( 'Upgrading categories table...' );
		$DB->query( "ALTER TABLE T_categories CHANGE COLUMN cat_urlname cat_urlname varchar(255) NOT NULL COLLATE ascii_bin" );
		task_end();

		task_begin( 'Upgrading items table...' );
		$DB->query( "ALTER TABLE T_items__item CHANGE COLUMN post_urltitle post_urltitle VARCHAR(210) NOT NULL COLLATE ascii_bin" );
		task_end();

		task_begin( 'Upgrading custom item settings...' );
		$DB->begin(); // Add names for custom fields
		// Select all custom fields from all blogs, to create field names
		$result = $DB->get_results( 'SELECT cset_coll_ID as coll_ID, cset_name as name, cset_value as value
										FROM T_coll_settings
										WHERE cset_name LIKE "custom\_double\_%"
										   OR cset_name LIKE "custom\_varchar\_%"
										ORDER BY cset_coll_ID, cset_name' );
		if( !empty( $result ) )
		{ // There are custom fields in blog settings
			$insert_field_names = '';
			foreach( $result as $row )
			{ // process each custom field
				$field_guid = preg_replace( '/^custom_(double|varchar)_([a-f0-9\-]+)$/', '$2', $row->name );
				// Replace special chars/umlauts, if we can convert charsets:
				load_funcs('locales/_charset.funcs.php');
				$field_name = strtolower( preg_replace( '/[^a-z0-9\-_]+/i', '_', $row->value ) );
				$field_name = replace_special_chars( $field_name );

				$insert_field_names .= '( '.$row->coll_ID.', "custom_fname_'.$field_guid.'", "'.$field_name.'" ), ';
			}
			// Insert names for custom fields in collection settings
			$DB->query( 'INSERT INTO T_coll_settings( cset_coll_ID, cset_name, cset_value )
							VALUES '.substr( $insert_field_names, 0, -2 ) );
		}
		$DB->commit(); // End adding of names for custom fields
		task_end();

		task_begin( 'Upgrading comments table...' );
		db_add_index( 'T_comments', 'comment_status', 'comment_status' );
		task_end();

		/*
		 * ADD UPGRADES FOR i5 BRANCH __ABOVE__ IN THIS BLOCK.
		 *
		 * This part will be included in trunk and i5 branches
		 */

		//set_upgrade_checkpoint( '11100' );
	}

	// Update modules own b2evo tables
	echo "Calling modules for individual upgrades...<br>\n";
	flush();
	modules_call_method( 'upgrade_b2evo_tables' );

	// Just in case, make sure the db schema version is up to date at the end.
	if( $old_db_version != $new_db_version )
	{ // Update DB schema version to $new_db_version
		set_upgrade_checkpoint( $new_db_version );
	}

	// We're going to need some environment in order to init caches and create profile picture links...
	if( ! is_object( $Settings ) )
	{ // create Settings object
		load_class( 'settings/model/_generalsettings.class.php', 'GeneralSettings' );
		$Settings = new GeneralSettings();
	}
	if( ! is_object( $Plugins ) )
	{ // create Plugins object
		load_class( 'plugins/model/_plugins.class.php', 'Plugins' );
		$Plugins = new Plugins();
	}

	// Init Caches: (it should be possible to do this with each upgrade)
	task_begin( '(Re-)Initializing caches...' );
	load_funcs('tools/model/_system.funcs.php');
	if( system_init_caches() )
	{ // cache was initialized successfully
		// Check all cache folders if exist and work properly. Try to repair cache folders if they aren't ready for operation.
		system_check_caches();
	}
	else
	{
		echo "<strong>".T_('The /cache folder could not be created/written to. b2evolution will still work but without caching, which will make it operate slower than optimal.')."</strong><br />\n";
	}
	task_end();

	// Check if profile picture links should be recreated. It won't be executed in each upgrade, but only in those cases when it is required.
	// This requires an up to date database, and also $Plugins and $GeneralSettings objects must be initialized before this.
	// Note: Check $create_profile_picture_links intialization and usage above to get more information.
	if( $create_profile_picture_links )
	{ // Create links for all files from the users profile_pictures folder
		task_begin( 'Creating profile picture links...' );
		create_profile_picture_links();
		task_end();
	}

	// Invalidate all page caches after every upgrade.
	// A new version of b2evolution may not use the same links to access special pages.
	// We want to play it safe here so that people don't think that upgrading broke their blog!
	task_begin( 'Invalidating all page caches to make sure they don\'t contain old action links...' );
	invalidate_pagecaches();
	task_end();


	// Reload plugins after every upgrade, to detect even those changes on plugins which didn't require db modifications
	task_begin( 'Reloading installed plugins to make sure their config is up to date...' );
	$Plugins_admin = & get_Plugins_admin();
	$Plugins_admin->reload_plugins();
	task_end();


	// This has to be at the end because plugin install may fail if the DB schema is not current (matching Plugins class).
	// Only new default plugins will be installed, based on $old_db_version.
	// dh> NOTE: if this fails (e.g. fatal error in one of the plugins), it will not get repeated
	task_begin( 'Installing new default plugins (if any)...' );
	install_basic_plugins( $old_db_version );
	task_end();


	// Create default cron jobs (this can be done at each upgrade):
	echo "Checking if some default cron jobs need to be installed...<br/>\n";
	flush();
	require_once dirname(__FILE__).'/_functions_create.php';
	create_default_jobs( true );


	// "Time running low" test: Check if the upgrade script elapsed time is close to the max execution time.
	// Note: This should not really happen except the case when many plugins must be installed.
	task_begin( 'Checking timing of upgrade...' );
	$elapsed_time = time() - $script_start_time;
	$max_exe_time = ini_get( 'max_execution_time' );
	if( $max_exe_time && ( $elapsed_time > ( $max_exe_time - 20 ) ) )
	{ // Max exe time not disabled and we're recahing the end
		echo 'We are reaching the time limit for this script. Please click <a href="index.php?locale='.$locale.'&amp;action=evoupgrade">continue</a>...';
		// Dirty temporary solution:
		exit(0);
	}
	task_end();


	/*
	 * -----------------------------------------------
	 * Check to make sure the DB schema is up to date:
	 * -----------------------------------------------
	 */
	echo "Starting to check DB...<br/>\n";
	flush();

	$upgrade_db_deltas = array(); // This holds changes to make, if any (just all queries)

	global $debug;

	foreach( $schema_queries as $table => $query_info )
	{	// For each table in the schema, check diffs...
		if( $debug )
		{
			echo '<br />Checking table: '.$table.': ';
		}
		$updates = db_delta( $query_info[1], array('drop_column', 'drop_index'), false, true );
		if( empty($updates) )
		{
			if( $debug ) echo 'ok';
		}
		else
		{
			if( $debug ) echo 'NEEDS UPDATE!';
			foreach( $updates as $table => $queries )
			{
				foreach( $queries as $qinfo )
				{
					foreach( $qinfo['queries'] as $query )
					{ // subqueries for this query (usually one, but may include required other queries)
						$upgrade_db_deltas[] = $query;
					}
				}
			}
		}
	}

	if( $debug )
	{
		echo '<br />';
	}

	if( empty($upgrade_db_deltas) )
	{	// no upgrades needed:
		echo '<p>'.T_('The database schema is up to date.').'</p>';
	}
	else
	{	// Upgrades are needed:

		$confirmed_db_upgrade = param('confirmed', 'integer', 0); // force confirmation
		$upgrade_db_deltas_confirm_md5 = param( 'upgrade_db_deltas_confirm_md5', 'string', '' );

		if( ! $confirmed_db_upgrade )
		{
			if( ! empty($upgrade_db_deltas_confirm_md5) )
			{ // received confirmation from form
				if( $upgrade_db_deltas_confirm_md5 != md5( implode('', $upgrade_db_deltas) ) )
				{ // unlikely to happen
					echo '<p class="error">'
						.T_('The DB schema has been changed since confirmation.')
						.'</p>';
				}
				else
				{
					$confirmed_db_upgrade = true;
				}
			}
		}

		if( ! $confirmed_db_upgrade )
		{
			global $action, $form_action;
			load_class( '_core/ui/forms/_form.class.php', 'Form' );

			if( !empty( $form_action ) )
			{
				$Form = new Form( $form_action, '', 'post' );
			}
			else
			{
				$Form = new Form( NULL, '', 'post' );
			}

			$Form->begin_form( 'fform', T_('Upgrade database') );

			$Form->begin_fieldset();
			$Form->hidden( 'upgrade_db_deltas_confirm_md5', md5(implode( '', $upgrade_db_deltas )) );
			$Form->hidden( 'action', $action );
			$Form->hidden( 'locale', $locale );


			echo '<p>'.T_('The version number is correct, but we have detected changes in the database schema. This can happen with CVS versions...').'</p>';

			echo '<p>'.T_('The following database changes will be carried out. If you are not sure what this means, it will probably be alright.').'</p>';

			echo '<ul>';
			foreach( $upgrade_db_deltas as $l_delta )
			{
				#echo '<li><code>'.nl2br($l_delta).'</code></li>';
				echo '<li><pre>'.str_replace( "\t", '  ', $l_delta ).'</pre></li>';
			}
			echo '</ul>';
			$Form->submit( array( '', T_('Upgrade database!'), 'ActionButton' ) );
			$Form->end_form();

			return false;
		}

		// Alter DB to match DB schema:
		install_make_db_schema_current( true );
	}

	return true;
}

?>