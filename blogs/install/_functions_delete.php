<?php
/**
 * This file implements deletion of DB tables
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2007 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @package install
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * db_delete(-)
 */
function db_delete()
{
	global $DB;

	echo "Dropping Prerendering cache table...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_items__prerendering' );

	echo "Dropping Cron log table...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_cron__log' );

	echo "Dropping Cron tasks table...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_cron__task' );

	echo "Dropping Collection settings table...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_coll_settings' );

	echo "Dropping Filetypes table...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_filetypes' );

	echo "Dropping Antispam table...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_antispam' );

	echo "Dropping Hit-Logs...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_hitlog' );

	echo "Dropping Comments...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_comments' );

	echo "Dropping Categories-to-Posts relationships...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_postcats' );

	echo "Dropping Links...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_links' );

	echo "Dropping Files...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_files' );

	echo "Dropping Posts...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_items__item' );

	echo "Dropping Categories...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_categories' );

	echo "Dropping Post Statuses...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_items__status' );

	echo "Dropping Post Types...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_items__type' );

	echo "Dropping User Settings...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_usersettings' );

	echo "Dropping User sessions...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_sessions' );

	echo "Dropping User permissions on Blogs...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_coll_user_perms' );

	echo "Dropping User subscriptions on Blogs...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_subscriptions' );

	echo "Dropping Users...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_users' );

	echo "Dropping Group permissions on Blogs...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_coll_group_perms' );

	echo "Dropping Groups...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_groups' );

	echo "Dropping Widgets...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_widget' );

	echo "Dropping Blogs...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_blogs' );

	echo "Dropping skin containers...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_skins__container' );

	echo "Dropping skins...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_skins__skin' );

	echo "Dropping Settings...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_settings' );

	echo "Dropping Locales...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_locales' );

	echo "Dropping User Settings...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_usersettings' );

	echo "Dropping Plugin Events...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_pluginevents' );

	echo "Dropping Plugin Settings...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_pluginsettings' );

	echo "Dropping Plugin User Settings...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_pluginusersettings' );

	echo "Dropping Plugins registrations...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_plugins' );

	echo "Dropping base domains...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_basedomains' );

	echo "Dropping user agents...<br />\n";
	$DB->query( 'DROP TABLE IF EXISTS T_useragents' );
}

/*
 * $Log$
 * Revision 1.33  2007/05/14 02:43:06  fplanque
 * Started renaming tables. There probably won't be a better time than 2.0.
 *
 * Revision 1.32  2007/04/26 00:11:09  fplanque
 * (c) 2007
 *
 * Revision 1.31  2007/01/08 02:11:56  fplanque
 * Blogs now make use of installed skins
 * next step: make use of widgets inside of skins
 *
 * Revision 1.30  2006/11/25 19:20:26  fplanque
 * MFB 1.9
 *
 * Revision 1.29  2006/07/04 17:32:30  fplanque
 * no message
 *
 * Revision 1.28  2006/03/01 23:43:30  blueyed
 * T_pluginusersettings
 *
 * Revision 1.27  2006/02/13 20:20:10  fplanque
 * minor / cleanup
 *
 * Revision 1.26  2005/12/30 18:08:24  fplanque
 * no message
 *
 */
?>