<?php
/**
 * This file is part of b2evolution - {@link http://b2evolution.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2009-2013 by Francois PLANQUE - {@link http://fplanque.net/}
 * Parts of this file are copyright (c)2009 by The Evo Factory - {@link http://www.evofactory.com/}.
 *
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 *
 * {@internal Open Source relicensing agreement:
 * The Evo Factory grants Francois PLANQUE the right to license
 * The Evo Factory's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package messaging
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author efy-maxim: Evo Factory / Maxim.
 * @author fplanque: Francois Planque.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * Load all of the recipients of current thread into the UserCache
 *
 * @param current thread ID
 */
function load_messaging_thread_recipients( $thrd_ID )
{
	global $DB;

	$SQL = new SQL();

	$SQL->SELECT( 'u.*' );

	$SQL->FROM( 'T_messaging__threadstatus ts
					INNER JOIN T_users u
						ON ts.tsta_user_ID = u.user_ID' );

	$SQL->WHERE( 'ts.tsta_thread_ID = '.$thrd_ID );

	$UserCache = & get_UserCache();

	foreach( $DB->get_results( $SQL->get() ) as $row )
	{
		if( !isset($UserCache->cache[$row->user_ID]) )
		{
			$UserCache->add( new User( $row ) );
		}
	}
}


/**
 * Check blocked contacts in recipients list.
 * Note: If current User has only reply permission then users who didn't have the current User in their contact list will be considerated as blocked contacts!
 *
 * @param array recipients list
 * @return array blocked contacts
 */
function check_blocked_contacts( $recipients_list )
{
	global $DB, $current_User;

	if( empty( $recipients_list ) )
	{ // there are no recipients to check
		return NULL;
	}

	if( $current_User->check_perm( 'perm_messaging', 'delete' ) )
	{ // user with delete messaging permission are allowed to send private messages to anyone who has access to read them.
		return NULL;
	}

	$SQL = new SQL();

	$SQL->SELECT( 'u.user_login' );

	if( $current_User->check_perm( 'perm_messaging', 'write', false ) )
	{ // get blocked contacts for user with write permission
		$sub_SQL = new SQL();

		// Select users who has blocked current_User
		$sub_SQL->SELECT( 'mct_from_user_ID as user_ID' );
		$sub_SQL->FROM( 'T_messaging__contact' );
		$sub_SQL->WHERE( 'mct_to_user_ID = '.$current_User->ID );
		$sub_SQL->WHERE_and( 'mct_blocked = 1' );

		// Select users from sub query result
		$SQL->FROM( 'T_users u' );
		$SQL->WHERE( 'u.user_ID IN ( '.$sub_SQL->get().' )' );
	}
	else
	{ // get every user, except non blocked contacts, for users with only reply permission
		// This will select users who has blocked current user, and users blocked by current User won't be selected, and this is OK!
		$SQL->FROM( 'T_users u
						LEFT OUTER JOIN T_messaging__contact mcu
							ON u.user_ID = mcu.mct_from_user_ID
	    					AND mcu.mct_to_user_ID = '.$current_User->ID.'
	    					AND mcu.mct_blocked = 0' );

		$SQL->WHERE( 'u.user_ID <> '.$current_User->ID );
		$SQL->WHERE_and( 'mcu.mct_from_user_ID IS NULL' );
	}

	// check if recipient list contains blocked contacts, if yes return them
	$SQL->WHERE_and( 'u.user_ID IN ('.implode( ',', $recipients_list ).')' );
	$SQL->ORDER_BY( 'u.user_login' );

	$blocked_contacts = array();
	foreach( $DB->get_results( $SQL->get() ) as $row )
	{
		$blocked_contacts[] = $row->user_login;
	}

	return $blocked_contacts;
}


/**
 * Block or unblock contact
 *
 * @param integer contact user id
 * @param boolean true to block | false to unblock
 * @result mixed 1 on success, 0 if the given user was not in the current user contact list, false on error
 */
function set_contact_blocked( $user_ID, $blocked )
{
	global $current_User, $DB;

	$sql = 'UPDATE T_messaging__contact
				SET mct_blocked = '.$blocked.'
					WHERE mct_from_user_ID = '.$current_User->ID.'
					AND mct_to_user_ID = '.$user_ID;

	return $DB->query( $sql );
}


/**
 * Create new messaging thread from request
 *
 * @return boolean true on success
 */
function create_new_thread()
{
	global $Settings, $current_User, $Messages, $edited_Thread, $edited_Message;

	// Insert new thread:
	$edited_Thread = new Thread();
	$edited_Message = new Message();
	$edited_Message->Thread = & $edited_Thread;

	// Check permission:
	$current_User->check_perm( 'perm_messaging', 'reply', true );

	if( $Settings->get('system_lock') )
	{ // System is locked for maintenance, All users cannot send a message
		$Messages->add( T_('You cannot send a message at this time because the system is under maintenance. Please try again in a few moments.'), 'error' );
		return false;
	}

	param( 'thrd_recipients', 'string' );
	param( 'thrd_recipients_array', 'array' );

	// Load data from request
	if( $edited_Message->load_from_Request() )
	{ // We could load data from form without errors:
		// Insert in DB:
		if( param( 'thrdtype', 'string', 'discussion' ) == 'discussion' )
		{
			$edited_Message->dbinsert_discussion();
			// update author user last new thread setting
			update_todays_thread_settings( 1 );
		}
		else
		{
			$edited_Message->dbinsert_individual();
			// update author user last new thread setting
			if( empty( $edited_Thread->recipients_list ) )
			{
				$edited_Thread->load_recipients();
			}
			update_todays_thread_settings( count( $edited_Thread->recipients_list ) );
		}

		$Messages->add( T_('Message sent.'), 'success' );

		return true;
	}
	return false;
}


/**
 * Create a new message from request in the given thread
 *
 * @param integer thread ID
 * @return boolean true on success
 */
function create_new_message( $thrd_ID )
{
	global $Settings, $current_User, $Messages, $edited_Message;

	// Insert new message:
	$edited_Message = new Message();
	$edited_Message->thread_ID = $thrd_ID;

	// Check permission:
	$current_User->check_perm( 'perm_messaging', 'reply', true );

	if( $Settings->get('system_lock') )
	{ // System is locked for maintenance, All users cannot send a message
		$Messages->add( T_('You cannot send a message at this time because the system is under maintenance. Please try again in a few moments.'), 'error' );
		return false;
	}

	// Load data from request
	if( $edited_Message->load_from_Request() )
	{ // We could load data from form without errors:
		// Insert in DB:
		$edited_Message->dbinsert_message();
		$Messages->add( T_('Message sent.'), 'success' );

		return true;
	}

	return false;
}


/**
 * User leave a thread
 *
 * @param integer thread ID
 * @param integer user ID
 * @param boolean set true to close the thread, which means no one can reply, leave false if thread must remain open
 * @return mixed number 1 on success, false otherwise
 */
function leave_thread( $thread_ID, $user_ID, $close_thread = false )
{
	global $DB;

	$ThreadCache = & get_ThreadCache();
	$edited_Thread = & $ThreadCache->get_by_ID( $thread_ID );

	if( !$edited_Thread->check_thread_recipient( $user_ID ) )
	{ // user is not between the thread recipients
		debug_die( 'Invalid request, current User is not recipient of the selected thread!' );
	}

	// create subquery to select the last message ID in this thread. This will be the last visible message for the user from this thread.
	$msg_subquery = 'SELECT msg_ID FROM T_messaging__message
						WHERE msg_thread_ID = '.$DB->quote( $thread_ID ).'
						ORDER BY msg_datetime DESC
						LIMIT 1';
	// set last visible thread ID query
	$query = 'UPDATE T_messaging__threadstatus
			SET tsta_thread_leave_msg_ID = ( '.$msg_subquery.' )
		WHERE tsta_thread_ID = '.$DB->quote( $thread_ID ).'
			AND tsta_thread_leave_msg_ID IS NULL';
	if( ! $close_thread )
	{ // don't close the thread for all user only the given user wants to leave
		$query .= ' AND tsta_user_ID = '.$DB->quote( $user_ID );
	}

	return $DB->query( $query );
}


/**
 * Get messaging menu urls
 *
 * @param string specific sub entry url, possible values: 'threads', 'contacts', 'messages'
 */
function get_messaging_url( $disp = 'threads' )
{
	global $admin_url, $is_admin_page, $Blog;
	if( $is_admin_page || empty( $Blog ) )
	{
		return $admin_url.'?ctrl='.$disp;
	}
	return url_add_param( $Blog->gen_blogurl(), 'disp='.$disp );
}


/**
 * Get messaging urls for the messaging notification emails
 *
 * @param integer thread ID the corresponding thread ID to display messages, and null to display all threads
 * @return array( threads/messages url, messaging preferences url )
 */
function get_messages_link_to( $thread_ID = NULL )
{
	global $Settings, $admin_url;

	if( empty( $thread_ID ) )
	{
		$link_tail = 'threads';
	}
	else
	{
		$link_tail = 'messages&thrd_ID='.$thread_ID;
	}
	$messages_link_to = $Settings->get( 'messages_link_to' );
	if( $messages_link_to != 'admin' )
	{
		$BlogCache = & get_BlogCache();
		/*
		 * @var Blog
		 */
		$link_to_Blog = $BlogCache->get_by_ID( $messages_link_to, false, false );
		if( $link_to_Blog )
		{
			$message_link = url_add_param( $link_to_Blog->gen_blogurl(), 'disp='.$link_tail );
			$prefs_link =  url_add_param( $link_to_Blog->gen_blogurl(), 'disp=userprefs' );
			return array( $message_link, $prefs_link );
		}
	}

	// link to admin
	$message_link = $admin_url.'?ctrl='.$link_tail;
	$prefs_link = $admin_url.'?ctrl=user&user_tab=userprefs';

	return array( $message_link, $prefs_link );
}


/**
 * Get messaging menu sub entries
 *
 * @param boolean true to get admin interface messaging sub menu entries, false to get front office messaging sub menu entries
 * @param integer owner user ID
 * @return array user sub entries
 */
function get_messaging_sub_entries( $is_admin )
{
	global $Blog, $current_User;

	if( $is_admin )
	{
		$url = '?ctrl=';
	}
	else
	{
		$url = url_add_param( $Blog->gen_blogurl(), 'disp=' );
	}

	$messaging_sub_entries = array(
								'threads' => array(
									'text' => T_('Messages'),
									'href' => $url.'threads' ),
								'contacts' => array(
									'text' => T_('Contacts'),
									'href' => $url.'contacts' ),
							);

	if( $is_admin && $current_User->check_perm( 'options', 'edit' ) )
	{
		$messaging_sub_entries[ 'msgsettings' ] = array(
													'text' => T_('Settings'),
													'href' => $url.'msgsettings'
												);
	}
	if( $current_User->check_perm( 'perm_messaging', 'abuse' ) )
	{
		$messaging_sub_entries[ 'abuse' ] = array(
													'text' => T_('Abuse Management'),
													'href' => $url.'abuse'
												);
	}

	return $messaging_sub_entries;
}


/**
 * Save message form params into the current Session
 *
 * @param Array message form params
 */
function save_message_params_to_session( $unsaved_message_Array )
{
	global $Session;
	$Session->set( 'core.unsaved_message_Array', $unsaved_message_Array );
}


/**
 * Get message form params from the current Session
 *
 * @return Array|NULL message form params array if Session core.unsaved_message_Array is set, NULL otherwise
 */
function get_message_params_from_session()
{
	global $Session;
	if( ( $unsaved_message_Array = $Session->get( 'core.unsaved_message_Array' ) ) && is_array( $unsaved_message_Array ) )
	{
		$Session->delete( 'core.unsaved_message_Array' );
		return $unsaved_message_Array;
	}
	return NULL;
}


/**
 * Get threads recipients SQL
 *
 * @param integer Thread ID
 * @return SQL object
 */
function get_threads_recipients_sql( $thread_ID = 0 )
{
	global $perm_abuse_management, $current_User;

	$read_user_sql_limit = '';
	$unread_user_sql_limit = '';
	$left_user_sql_limit = '';
	if( ! $perm_abuse_management )
	{	// Non abuse management
		$read_user_sql_limit = ' AND ur.user_ID <> '.$current_User->ID;
		$unread_user_sql_limit = ' AND uu.user_ID <> '.$current_User->ID;
		$left_user_sql_limit = ' AND ul.user_ID <> '.$current_User->ID;
	}

	$recipients_SQL = new SQL();

	$recipients_SQL->SELECT( 'ts.tsta_thread_ID AS thr_ID,
								GROUP_CONCAT(DISTINCT ur.user_login ORDER BY ur.user_login SEPARATOR \', \') AS thr_read,
								GROUP_CONCAT(DISTINCT uu.user_login ORDER BY uu.user_login SEPARATOR \', \') AS thr_unread,
								GROUP_CONCAT(DISTINCT ul.user_login ORDER BY ul.user_login SEPARATOR \', \') AS thr_left' );

	$recipients_SQL->FROM( 'T_messaging__threadstatus ts
								LEFT OUTER JOIN T_messaging__threadstatus tsr
									ON ts.tsta_thread_ID = tsr.tsta_thread_ID AND tsr.tsta_first_unread_msg_ID IS NULL
										AND tsr.tsta_thread_leave_msg_ID IS NULL
								LEFT OUTER JOIN T_users ur
									ON tsr.tsta_user_ID = ur.user_ID'.$read_user_sql_limit.'
								LEFT OUTER JOIN T_messaging__threadstatus tsu
									ON ts.tsta_thread_ID = tsu.tsta_thread_ID AND tsu.tsta_first_unread_msg_ID IS NOT NULL
										AND tsu.tsta_thread_leave_msg_ID IS NULL
								LEFT OUTER JOIN T_users uu
									ON tsu.tsta_user_ID = uu.user_ID'.$unread_user_sql_limit.'
								LEFT OUTER JOIN T_messaging__threadstatus tsl
									ON ts.tsta_thread_ID = tsl.tsta_thread_ID AND tsl.tsta_thread_leave_msg_ID IS NOT NULL
								LEFT OUTER JOIN T_users ul
									ON tsl.tsta_user_ID = ul.user_ID'.$left_user_sql_limit );

	if( $thread_ID > 0 )
	{	// Limit with thread ID
		$recipients_SQL->WHERE( 'ts.tsta_thread_ID = '.$thread_ID );
	}

	if( ! $perm_abuse_management )
	{	// Get a messages only of current user
		$recipients_SQL->WHERE_and( 'ts.tsta_user_ID ='.$current_User->ID );
	}

	$recipients_SQL->GROUP_BY( 'ts.tsta_thread_ID' );

	return $recipients_SQL;
}


/**
 * Get all those user threads and their recipients where the corresponding users have unread messages.
 * This function is used for the different email notifications.
 *
 * @param array required user id list
 * @param integer a thread ID that should be skipped from the result list. Leave it to NULL if you don't want to skip any thread.
 * @param string Threads format ( string | array )
 * @param string User logins format ( text | html )
 * @return array ( user_ID -> (string or array) ) pairs, the string contains the users unread threads titles and their recipients list
 */
function get_users_unread_threads( $userid_list, $skip_thread_ID = NULL, $threads_format = 'string', $login_format = 'text' )
{
	global $DB;
	$result = array();

	if( empty( $userid_list ) )
	{ // requested user id list is empty, return empty result
		return $result;
	}

	// Get all those user threads where the corresponding user has unread messages, and sort descending by last modifiaction date
	$query = 'SELECT DISTINCT tsta_user_ID, GROUP_CONCAT( CONVERT( thrd_ID, CHAR(10) ) ORDER BY thrd_datemodified DESC SEPARATOR "," ) as threads
			FROM T_messaging__threadstatus
			LEFT JOIN T_messaging__thread ON thrd_ID = tsta_thread_ID
			WHERE tsta_first_unread_msg_ID IS NOT NULL AND tsta_user_ID IN ( '.implode( ',', $userid_list ).')
			GROUP BY tsta_user_ID';
	$user_unread_threads = $DB->get_assoc( $query, 'Get all threads where the corresponding users have unread messages' );

	if( empty( $user_unread_threads ) )
	{ // requested users have no unread threads
		return $result;
	}

	$all_involved_threads = array();
	$user_threads = array();
	// create an all involved threads array
	foreach( $user_unread_threads as $key => $threads )
	{
		$user_threads[ $key ] = explode( ',', $threads );
		$all_involved_threads = array_merge( $all_involved_threads, $user_threads[ $key ] );
	}
	$all_involved_threads = array_unique( $all_involved_threads );

	// Get all required threads recipients and titles
	$recipients_query = 'SELECT tsta_thread_ID as thread_ID, thrd_title, user_login
			FROM T_messaging__threadstatus
			LEFT JOIN T_users ON user_ID = tsta_user_ID
			LEFT JOIN T_messaging__thread ON thrd_ID = tsta_thread_ID
				WHERE tsta_thread_ID IN ( '.implode( ',', $all_involved_threads ).' )';
	$all_threads_recipients = $DB->get_results( $recipients_query, OBJECT, 'Load all required threads title and recipients' );
	$thread_recipients = array();
	$thread_titles = array();
	foreach( $all_threads_recipients as $row )
	{
		if( !isset( $thread_recipients[ $row->thread_ID ] ) )
		{
			$thread_recipients[ $row->thread_ID ] = array();
		}
		if( empty( $row->user_login ) )
		{ // User was deleted
			$thread_recipients[$row->thread_ID][] = 'Deleted user';
		}
		else
		{ // User exists
			if( $login_format == 'text' )
			{ // Use simple login as text
				$thread_recipients[$row->thread_ID][] = $row->user_login;
			}
			else // 'html'
			{ // Use a colored login with avatar
				$thread_recipients[$row->thread_ID][] = get_user_colored_login( $row->user_login );
			}
		}
		if( !isset( $thread_titles[ $row->thread_ID ] ) )
		{
			$thread_titles[ $row->thread_ID ] = $row->thrd_title;
		}
	}

	foreach( $userid_list as $user_ID )
	{
		if( !isset( $user_threads[ $user_ID ] ) )
		{
			$result[ $user_ID ] = NULL;
			continue;
		}
		$threads = $user_threads[ $user_ID ];
		$unread_threads = $threads_format == 'string' ? '' : array();
		// List all unread threads, starting with the most recent updated threads,
		// so that each new reminder looks as different as possible from the one from 72 hours before
		foreach( $threads as $thread_ID )
		{
			if( $skip_thread_ID == $thread_ID )
			{
				continue;
			}
			$recipient_names = implode( ', ', $thread_recipients[ $thread_ID ] );
			if( $threads_format == 'string' )
			{	// Store all threads in one string
				$unread_threads .= "\t - ".sprintf( '"%s" ( %s )', $thread_titles[ $thread_ID ], $recipient_names )."\n";
			}
			else
			{	// Store all threads in array
				$unread_threads[] = sprintf( '"%s" ( %s )', $thread_titles[ $thread_ID ], $recipient_names );
			}
		}
		$result[$user_ID] = $unread_threads;
	}

	return $result;
}


/**
 * Get threads SQL
 *
 * @param array Params
 * @return Results object
 */
function get_threads_results( $params = array() )
{
	global $perm_abuse_management, $current_User, $DB;

	// Make sure we are not missing any param:
	$params = array_merge( array(
			'results_param_prefix' => 'thrd_', // Param prefix for results list
			'user_ID' => $current_User->ID,    // To limit messages only by this user's ID
			'sent_user_ID' => '',              // To limit messages only for sent by given user ID
			'search_word' => '',               // Filter by this keyword
			'search_user' => '',               // Filter by this user name
			'show_closed_threads' => NULL,     // Show closed conversations
			'only_sql' => false,               // TRUE - to return only SQL object, FALSE - Results object
		), $params );


	$filter_sql = '';
	if( !empty( $params['search_word'] ) || !empty( $params['search_user'] ) || !empty( $params['sent_user_ID'] ) )
	{	// We want to filter on search keyword:
		$filter_sql = array();
		if( !empty( $params['search_word'] ) )
		{ // Search by title
			$filter_sql[] = 'thrd_title LIKE "%'.$DB->escape( $params['search_word'] ).'%"';
		}
		if( !empty( $params['search_user'] ) )
		{ // Search by user names

			// Get all threads IDs with searching user name
			$threads_SQL = new SQL();
			$threads_SQL->SELECT( 'tsta_thread_ID' );
			$threads_SQL->FROM( 'T_users' );
			$threads_SQL->FROM_add( 'INNER JOIN T_messaging__threadstatus ON tsta_user_ID = user_ID' );
			$threads_SQL->WHERE( 'user_login LIKE "%'.$DB->escape( $params['search_user'] ).'%"' );
			$threads_SQL->WHERE_or( 'user_firstname LIKE "%'.$DB->escape( $params['search_user'] ).'%"' );
			$threads_SQL->WHERE_or( 'user_lastname LIKE "%'.$DB->escape( $params['search_user'] ).'%"' );
			$threads_SQL->WHERE_or( 'user_nickname LIKE "%'.$DB->escape( $params['search_user'] ).'%"' );
			$threads_IDs = $DB->get_col( $threads_SQL->get() );

			if( empty( $threads_IDs ) )
			{	// No found related threads
				$threads_IDs[] = '-1';
			}

			$filter_sql[] = 'tsta_thread_ID IN ( '.implode( ',', $threads_IDs ).' )';
		}
		if( !empty( $params[ 'sent_user_ID' ] ) )
		{
			// Get all threads IDs with searching user name
			$threads_SQL = new SQL();
			$threads_SQL->SELECT( 'DISTINCT( msg_thread_ID )' );
			$threads_SQL->FROM( 'T_messaging__message' );
			$threads_SQL->WHERE( 'msg_author_user_ID = '.$DB->quote( $params[ 'sent_user_ID' ] ) );
			$threads_IDs = $DB->get_col( $threads_SQL->get() );

			if( empty( $threads_IDs ) )
			{	// No found related threads
				$threads_IDs[] = '-1';
			}

			$filter_sql[] = 'tsta_thread_ID IN ( '.implode( ',', $threads_IDs ).' )';
		}
		$filter_sql = ( count( $filter_sql ) > 0 ) ? implode( ' OR ', $filter_sql) : '';
	}

	$thrd_msg_ID = $perm_abuse_management ? '"abuse"' : 'tsta_first_unread_msg_ID';

	// Create SELECT SQL query
	$select_SQL = new SQL();
	$select_SQL->SELECT( 'thrd_ID, thrd_title, thrd_datemodified, '.$thrd_msg_ID.' AS thrd_msg_ID, tsta_thread_leave_msg_ID, msg_datetime AS thrd_unread_since' );
	$select_SQL->FROM( 'T_messaging__threadstatus' );
	$select_SQL->FROM_add( 'INNER JOIN T_messaging__thread ON tsta_thread_ID = thrd_ID' );
	$select_SQL->FROM_add( 'LEFT OUTER JOIN T_messaging__message ON tsta_first_unread_msg_ID = msg_ID' );
	if( ! $perm_abuse_management )
	{ // Limit threads by current user
		$select_SQL->WHERE( 'tsta_user_ID = '.$params['user_ID'] );
		if( $params['show_closed_threads'] === NULL )
		{ // Explicit param value was not set, use the default
			// Show closed messages by default only if there are unread messages in closed conversations
			$params['show_closed_threads'] = $DB->get_var(
				'SELECT COUNT( tsta_thread_ID )
					FROM T_messaging__threadstatus
					WHERE tsta_thread_leave_msg_ID IS NOT NULL AND tsta_first_unread_msg_ID IS NOT NULL
					AND tsta_first_unread_msg_ID <= tsta_thread_leave_msg_ID AND tsta_user_ID = '.$params['user_ID']
			);
			// Set 'show_closed' param value, so the checkobx filter can be displayed correctly
			set_param( 'show_closed', $params['show_closed_threads'] ? true : false );
		}
		if( ! $params['show_closed_threads'] )
		{ // Don't show the closed conversations
			$select_SQL->WHERE_and( '( tsta_thread_leave_msg_ID IS NULL )' );
		}
	}
	if( !empty( $filter_sql ) )
	{	// Filter
		$select_SQL->WHERE_and( $filter_sql );
	}
	$select_SQL->ORDER_BY( 'tsta_first_unread_msg_ID DESC, thrd_datemodified DESC' );
	if( $perm_abuse_management )
	{
		$select_SQL->GROUP_BY( 'tsta_thread_ID' );
	}

	// Create COUNT SQL query
	$count_SQL = new SQL();
	$count_SQL->SELECT( 'COUNT( DISTINCT tsta_thread_ID )' );
	$count_SQL->FROM( 'T_messaging__threadstatus' );
	if( ! empty( $filter_sql ) )
	{ // Filter
		$count_SQL->FROM_add( 'INNER JOIN T_messaging__thread ON tsta_thread_ID = thrd_ID' );
	}
	if( ! $perm_abuse_management )
	{ // Limit threads by current user
		$count_SQL->WHERE( 'tsta_user_ID = '.$params['user_ID'] );
		if( ! $params['show_closed_threads'] )
		{ // Don't show the closed conversations
			$count_SQL->WHERE_and( '( tsta_thread_leave_msg_ID IS NULL )' );
		}
	}
	if( !empty( $filter_sql ) )
	{ // Filter
		$count_SQL->WHERE_and( $filter_sql );
	}

	if( $params['only_sql'] )
	{ // Return only SQL object
		return $select_SQL;
	}

	// Create result set:
	$Results = new Results( $select_SQL->get(), $params['results_param_prefix'], '', NULL, $count_SQL->get() );

	return $Results;
}


/**
 * Insert user to contacts
 *
 * @param integer User ID
 * @param boolean set true to add as a blocked contact
 * @return true if success, else false
 */
function create_contacts_user( $user_ID, $blocked = false )
{
	global $DB, $current_User, $localtimenow;

	$contact = check_contact( $user_ID );
	if( !is_null( $contact ) )
	{ // This user already exists in the contact list for current user
		return true;
	}

	$datetime = date( 'Y-m-d H:i:s', $localtimenow );
	$blocked = $blocked ? 1 : 0;

	$sql = 'INSERT INTO T_messaging__contact
		       ( mct_from_user_ID, mct_to_user_ID, mct_blocked, mct_last_contact_datetime )
		VALUES ( '.$current_User->ID.', '.$user_ID.', '.$blocked.', '.$DB->quote( $datetime ).' )';

	return $DB->query( $sql, 'Insert contacts' );
}


/**
 * Insert contacts group into database
 *
 * @param string Group name
 * @return integer Group ID if success, else false
 */
function create_contacts_group( $group_name )
{
	global $DB, $current_User, $Messages;

	if( $group_name != '' )
	{	// Check new group name for duplicates
		$SQL = new SQL();
		$SQL->SELECT( 'cgr_ID' );
		$SQL->FROM( 'T_messaging__contact_groups' );
		$SQL->WHERE( 'cgr_user_ID = '.$current_User->ID );
		$SQL->WHERE_and( 'cgr_name = '.$DB->quote( $group_name ) );

		$group = $DB->get_var( $SQL->get() );
		if( !is_null( $group ) )
		{	// Duplicate group
			$Messages->add( T_('You already have a group with this name.'), 'error' );
		}
	}

	if( param_errors_detected() )
	{	// Errors exist, Exit here
		return false;
	}

	$sql = 'INSERT INTO T_messaging__contact_groups
			     ( cgr_user_ID, cgr_name )
		VALUES ( '.$current_User->ID.', '.$DB->quote( $group_name ).' )';

	if( $DB->query( $sql, 'Insert contacts group' ) )
	{	// Success query, Return ID of new group
		return $DB->insert_id;
	}
	else
	{	// Failed query
		return false;
	}
}


/**
 * Rename contacts group
 *
 * @param integer Group ID
 * @param string Name of input with new group name
 * @return boolean TRUE if group was renamed successfully
 */
function rename_contacts_group( $group_ID, $field_name = 'name' )
{
	global $DB, $current_User, $Messages;

	$name = param( $field_name, 'string', '' );
	param_check_not_empty( $field_name, T_('Please enter group name') );

	// Check if user is owner of this group
	$SQL = new SQL();
	$SQL->SELECT( 'cgr_ID' );
	$SQL->FROM( 'T_messaging__contact_groups' );
	$SQL->WHERE( 'cgr_user_ID = '.$current_User->ID );
	$SQL->WHERE_and( 'cgr_ID = '.$DB->quote( $group_ID ) );

	if( $DB->get_var( $SQL->get() ) == NULL )
	{
		$Messages->add( 'You don\'t have this group', 'error' );
		return false;
	}

	// Rename a group
	$sql = 'UPDATE T_messaging__contact_groups
		  SET cgr_name = '.$DB->quote( $name ).'
		WHERE cgr_ID = '.$DB->quote( $group_ID ).'
		  AND cgr_user_ID = '.$current_User->ID;

	$DB->query( $sql, 'Rename contacts group' );

	return true;
}


/**
 * Delete contacts group
 *
 * @param integer Group ID
 * @return boolean TRUE if group was renamed successfully
 */
function delete_contacts_group( $group_ID )
{
	global $DB, $current_User, $Messages;

	// Check if user is owner of this group
	$SQL = new SQL();
	$SQL->SELECT( 'cgr_ID' );
	$SQL->FROM( 'T_messaging__contact_groups' );
	$SQL->WHERE( 'cgr_user_ID = '.$current_User->ID );
	$SQL->WHERE_and( 'cgr_ID = '.$DB->quote( $group_ID ) );

	if( $DB->get_var( $SQL->get() ) == NULL )
	{
		$Messages->add( 'You don\'t have this group', 'error' );
		return false;
	}

	// Delete a group
	$sql_groups = 'DELETE FROM T_messaging__contact_groups
		WHERE cgr_ID = '.$DB->quote( $group_ID ).'
		  AND cgr_user_ID = '.$current_User->ID;
	$DB->query( $sql_groups, 'Delete contacts group' );

	// Delete users from this group
	$sql_users = 'DELETE FROM T_messaging__contact_groupusers
		WHERE cgu_cgr_ID = '.$DB->quote( $group_ID );
	$DB->query( $sql_users, 'Delete users from contacts group' );

	return true;
}


/**
 * Insert users for contacts group into database
 *
 * @param integer/string Group ID or 'new'
 * @param string Users IDs separated with comma
 * @param string Name of input element with new group name
 * @return array/boolean Array( 'count_users', 'group_name' ) if success, else false
 */
function create_contacts_group_users( $group, $users, $new_group_field_name = 'group_combo' )
{
	global $DB, $current_User, $Messages;

	$users_IDs = explode( ',', $users );
	if( count( $users_IDs ) == 0 || strlen( $users ) == 0 )
	{	// No selected users
		$Messages->add( T_('Please select at least one user.'), 'error' );
		return false;
	}

	if( $group == 'new' || (int)$group < 0 )
	{	// Add new group
		if( (int)$group < 0 )
		{	// Default group
			$default_groups = get_contacts_groups_default();
			if( isset( $default_groups[$group] ) )
			{	// Get group name
				$group_name = $default_groups[$group];
			}
			else
			{	// Error
				$Messages->add( 'No found this group.', 'error' );
				return false;
			}
		}
		else
		{	// New entered group
			$group_name = param( $new_group_field_name, 'string', true );
			param_check_not_empty( $new_group_field_name, T_('Please enter name for new group.') );
		}

		if( $group_ID = create_contacts_group( $group_name ) )
		{	// Create group
			$Messages->add( T_('New contacts group has been created.'), 'success' );
		}
		else
		{	// Errors
			return false;
		}
	}
	else
	{	// Existing group
		$group_ID = (int)$group;

		if( $group_ID == 0 )
		{	// No defined group ID
			return false;
		}

		$SQL = new SQL();
		$SQL->SELECT( 'cgr_name AS name' );
		$SQL->FROM( 'T_messaging__contact_groups' );
		$SQL->WHERE( 'cgr_user_ID = '.$current_User->ID );
		$SQL->WHERE_and( 'cgr_ID = '.$DB->quote( $group_ID ) );

		$group = $DB->get_row( $SQL->get() );
		if( is_null( $group ) )
		{	// User try use a group of another user
			return false;
		}
		$group_name = $group->name;
	}

	// Get all Users IDs of selected group in order to exclude duplicates
	$SQL = new SQL();
	$SQL->SELECT( 'cgu_user_ID, cgu_cgr_ID' );
	$SQL->FROM( 'T_messaging__contact_groupusers' );
	$SQL->WHERE_and( 'cgu_cgr_ID = '.$DB->quote( $group_ID ) );
	$users_already_grouped = $DB->get_assoc( $SQL->get() );

	$sql = 'INSERT INTO T_messaging__contact_groupusers ( cgu_user_ID, cgu_cgr_ID ) VALUES ';

	$records = array();
	foreach( $users_IDs as $user_ID )
	{
		$user_ID = (int)trim( $user_ID );
		if( $user_ID == 0 )
		{	// User ID is empty
			continue;
		}
		else if( isset( $users_already_grouped[$user_ID] ) )
		{
			if( $users_already_grouped[$user_ID] == $group_ID )
			{	// This user already is added in selected group
				continue;
			}
		}
		$records[] = '( '.$user_ID.', '.$DB->quote( $group_ID ).' )';
	}
	$sql .= implode( ', ', $records );

	if( count( $records ) == 0 )
	{	// No data to add
		return false;
	}

	if( $DB->query( $sql, 'Insert users for contacts group' ) )
	{	// Success query
		return array(
			'count_users' => count( $records ),
			'group_name'  => $group_name );
	}
	else
	{	// Failed query
		return false;
	}
}


/**
 * Remove user from contacts group
 *
 * @param integer/string Group ID or 'new'
 * @param string Users IDs separated with comma
 * @param string Name of input element with new group name
 * @return boolean true if success
 */
function remove_contacts_group_user( $group_ID, $user_ID )
{
	global $DB, $current_User, $Messages;

	$SQL = new SQL();
	$SQL->SELECT( 'cgr_name AS name' );
	$SQL->FROM( 'T_messaging__contact_groups' );
	$SQL->FROM_add( 'LEFT JOIN T_messaging__contact_groupusers ON cgr_ID = cgu_cgr_ID' );
	$SQL->WHERE( 'cgr_user_ID = '.$current_User->ID );
	$SQL->WHERE_and( 'cgu_user_ID = '.$DB->quote( $user_ID ) );
	$SQL->WHERE_and( 'cgu_cgr_ID = '.$DB->quote( $group_ID ) );

	$group = $DB->get_row( $SQL->get() );
	if( is_null( $group ) )
	{	// User try use a group of another user
		return false;
	}

	$sql = 'DELETE FROM T_messaging__contact_groupusers
		WHERE cgu_user_ID = '.$DB->quote( $user_ID ).'
		  AND cgu_cgr_ID = '.$DB->quote( $group_ID );

	if( $DB->query( $sql, 'Remove user from contacts group' ) )
	{	// Success query
		$UserCache = & get_UserCache();
		$User = & $UserCache->get_by_ID( $user_ID );

		$Messages->add( sprintf( T_('User &laquo;%s&raquo; has been removed from the &laquo;%s&raquo; group.'), $User->get_preferred_name(), $group->name ), 'success' );
		return true;
	}
	else
	{	// Failed query
		return false;
	}
}


/**
 * Get default groups for user contacts
 *
 * @return array Groups
 */
function get_contacts_groups_default()
{
	$default_groups = array(
			'-1' => T_('Close Friends'),
			'-2' => T_('Acquaintances'),
		);

	return $default_groups;
}


/**
 * Get tags <option> for contacts groups of current User
 *
 * @param integer Selected group ID
 * @param boolean TRUE if we need in null option, else FALSE
 * @return string Tags <option>
 */
function get_contacts_groups_options( $selected_group_ID = NULL, $value_null = true )
{
	global $DB, $current_User;

	$default_groups = get_contacts_groups_default();
	$selected_default_group_name = $default_groups[-1]; // Close Friends

	// Get user groups
	$SQL = new SQL();
	$SQL->SELECT( 'cgr_ID AS ID, cgr_name AS name' );
	$SQL->FROM( 'T_messaging__contact_groups' );
	$SQL->WHERE( 'cgr_user_ID = '.$current_User->ID );
	$SQL->ORDER_BY( 'cgr_name' );
	$user_groups = $DB->get_assoc( $SQL->get() );

	// Merge default and user groups (don't use a function array_merge() because it clears the keys)
	$groups = array();
	foreach( $default_groups as $group_ID => $group_name )
	{
		if( !in_array( $group_name, $user_groups ) )
		{	// Set this default group If it doesn't exist in DB
			$groups[$group_ID] = $group_name;
		}
	}
	foreach( $user_groups as $group_ID => $group_name )
	{
		$groups[$group_ID] = $group_name;
		if( $selected_default_group_name == $group_name )
		{	// To know group ID of selected default group
			$selected_default_group_ID = $group_ID;
		}
	}

	if( isset( $selected_default_group_ID ) && $selected_group_ID == -1 )
	{	// If default group already exists in DB we should use this group ID
		$selected_group_ID = $selected_default_group_ID;
	}

	$options = '';

	if( $value_null )
	{	// Null option
		$options .= '<option value="0">'.T_('All').'</option>'."\n";
	}

	foreach( $groups as $group_ID => $group_name )
	{
		$selected = '';
		if( $selected_group_ID == $group_ID )
		{	// Group is selected
			$selected = ' selected="selected"';
		}
		$options .= '<option value="'.$group_ID.'"'.$selected.'>'.$group_name.'</option>'."\n";
	}

	return $options;
}


/**
 * Get list with contacts groups of current User
 *
 * @param integer User ID
 * @param array Params
 * @return string HTML list
 */
function get_contacts_groups_list( $user_ID, $params = array() )
{
	global $DB, $current_User, $Blog;

	$params = array_merge( array(
			'list_start'  => '<table cellspacing="0" class="user_contacts_groups">',
			'col1_header' => '<tr><th class="col1">'.T_('In these groups').'</th>',
			'col2_header' => '<th>'.T_('Not in these groups').'</th></tr>',
			'col1_start'  => '<tr><td class="col1">',
			'col1_end'    => '</td>',
			'col2_start'  => '<td>',
			'col2_end'    => '</tr>',
			'list_end'    => '</table>',
		) );

	$default_groups = get_contacts_groups_default();

	// Get all groups of current User
	$SQL = new SQL();
	$SQL->SELECT( 'cgr_ID AS ID, cgr_name AS name' );
	$SQL->FROM( 'T_messaging__contact_groups' );
	$SQL->WHERE( 'cgr_user_ID = '.$current_User->ID );
	$SQL->ORDER_BY( 'cgr_name' );
	$all_groups = $DB->get_assoc( $SQL->get() );

	// Merge default and user groups (don't use a function array_merge() because it clears the keys)
	$groups = array();
	foreach( $default_groups as $group_ID => $group_name )
	{
		if( !in_array( $group_name, $all_groups ) )
		{	// Set this default group If it doesn't exist in DB
			$groups[$group_ID] = $group_name;
		}
	}
	foreach( $all_groups as $group_ID => $group_name )
	{
		$groups[$group_ID] = $group_name;
	}
	$all_groups = $groups;
	unset( $groups );

	if( count( $all_groups ) == 0 )
	{	// No groups
		return;
	}

	// Get groups of selected User
	$SQL = new SQL();
	$SQL->SELECT( 'cgu_cgr_ID, cgu_user_ID' );
	$SQL->FROM( 'T_messaging__contact_groupusers' );
	$SQL->WHERE( 'cgu_user_ID = '.$user_ID );
	$user_groups = $DB->get_assoc( $SQL->get() );

	if( !empty( $Blog ) )
	{	// Set url to contacts list
		$contacts_url = url_add_param( $Blog->gen_blogurl(), 'disp=contacts' );
	}

	$groups_1st = array();
	$groups_2nd = array();
	foreach( $all_groups as $group_ID => $group_name )
	{
		if( !empty( $contacts_url ) && $group_ID > 0 )
		{	// Set link to contacts list with filtered by current group
			$group_link = '<a href="'.url_add_param( $contacts_url, 'g='.$group_ID ).'">'.$group_name.'</a>';
		}
		else
		{	// No link, use name
			$group_link = $group_name;
		}
		if( isset( $user_groups[$group_ID] ) )
		{	// User is in this group
			$groups_1st[] = '<th><strong>'.$group_link.'</strong></th>'.
					'<td>'.action_icon( T_('Remove from group'), 'move_right', get_dispctrl_url( 'contacts', 'action=remove_user&amp;user_ID='.$user_ID.'&amp;group_ID='.$group_ID.'&amp;'.url_crumb( 'messaging_contacts' ) ) ).'</td>';
		}
		else
		{
			$groups_2nd[] = '<td>'.action_icon( T_('Add to group'), 'move_left', get_dispctrl_url( 'contacts', 'action=add_user&amp;user_ID='.$user_ID.'&amp;group_ID='.$group_ID.'&amp;'.url_crumb( 'messaging_contacts' ) ) ).'</td>'.
					'<th>'.$group_link.'</th>';
		}
	}

	$groups_list = $params['list_start'];

	$groups_list .= $params['col1_header'];
	$groups_list .= $params['col2_header'];

	$groups_list .= $params['col1_start'];
	if( count( $groups_1st ) >0 )
	{
		$groups_list .= '<table cellspacing="0"><tr>'.implode( '</tr><tr>', $groups_1st ).'</tr></table>';
	}
	else
	{
		$groups_list .= '&nbsp;';
	}
	$groups_list .= $params['col1_end'];

	$groups_list .= $params['col2_start'];
	if( count( $groups_2nd ) >0 )
	{
		$groups_list .= '<table cellspacing="0"><tr>'.implode( '</tr><tr>', $groups_2nd ).'</tr></table>';
	}
	else
	{
		$groups_list .= '&nbsp;';
	}
	$groups_list .= $params['col2_end'];

	$groups_list .= $params['list_end'];

	return $groups_list;
}


/**
 * Check contact for current user with selected user
 *
 * @param integer User ID
 * @return mixed NULL if contact was not found, boolean TRUE if contact is not blocked, FALSE otherwise
 */
function check_contact( $to_user_ID )
{
	global $DB, $current_User;

	$is_blocked = $DB->get_var( 'SELECT mct_blocked
		 FROM T_messaging__contact
		WHERE mct_from_user_ID = '.$current_User->ID.'
		  AND mct_to_user_ID = '.$to_user_ID );

	if( is_null( $is_blocked ) )
	{
		return NULL;
	}
	return !$is_blocked;
}


/**
 * Update current User last new thread timestamp, and today's new thread count
 *
 * @param integer how many new thread was just created
 */
function update_todays_thread_settings( $new_threads_count )
{
	global $current_User, $UserSettings, $servertimenow;

	$last_new_thread_ts = $UserSettings->get( 'last_new_thread',  $current_User->ID );
	if( empty( $last_new_thread_ts ) )
	{ // this is the current User first new thread
		$UserSettings->set( 'last_new_thread', $servertimenow, $current_User->ID );
		$UserSettings->set( 'new_thread_count', $new_threads_count, $current_User->ID );
	}
	else
	{
		$today = date( 'Y-m-d', $servertimenow );
		$last_new_thread_date = date( 'Y-m-d', $last_new_thread_ts );
		if( $last_new_thread_date < $today )
		{ // this is the current User first new thread today
			$UserSettings->set( 'new_thread_count', $new_threads_count, $current_User->ID );
		}
		else
		{ // current User has already created other new threads today
			$new_thread_count = $UserSettings->get( 'new_thread_count', $current_User->ID );
			$UserSettings->set( 'new_thread_count', $new_thread_count + $new_threads_count, $current_User->ID );
		}
		// update last new thread timestamp
		$UserSettings->set( 'last_new_thread', $servertimenow, $current_User->ID );
	}
	// update User Settings
	$UserSettings->dbupdate();
}


/**
 * Get current User max new threads settings for today
 *
 * @return array ( max new threads per day, how many threads has already created today )
 */
function get_todays_thread_settings()
{
	global $current_User, $UserSettings, $servertimenow;

	$user_Group = & $current_User->get_Group();
	$user_GroupSettings = & $user_Group->get_GroupSettings();
	$max_new_threads = $user_GroupSettings->get( 'max_new_threads', $user_Group->ID );
	if( $max_new_threads === '0' )
	{ // user limit is 0, user must not be able to create new threads
		return array( '0', 0 );
	}

	$last_new_thread_ts = $UserSettings->get( 'last_new_thread', $current_User->ID );
	if( empty( $last_new_thread_ts ) )
	{ // user has not created any new threads yet
		return array( $max_new_threads, 0 );
	}

	$today = date( 'Y-m-d', $servertimenow );
	$last_new_thread_date = date( 'Y-m-d', $last_new_thread_ts );
	if( $last_new_thread_date < $today )
	{ // user's last new thread was not created today, it's older
		return array( $max_new_threads, 0 );
	}

	// User has already created at least one new thread today
	$new_thread_count = $UserSettings->get( 'new_thread_count', $current_User->ID );
	return array( $max_new_threads, $new_thread_count );
}


/**
 * Check if current User already reached his 'create new thread' limit for today or not.
 * Important: The current User messaging permission is not checked here!
 *
 * @param boolean true to add a success/error message to $Messages about the current User thread limit, false otherwise
 * @return boolean true if current user already reached his limit, false otherwise
 */
function check_create_thread_limit( $add_message = false )
{
	global $Messages;

	list( $max_new_threads, $new_threads_count ) = get_todays_thread_settings();
	if( $max_new_threads === '0' )
	{ // user new conversation limit is 0
		if( $add_message )
		{
			$Messages->add( T_( 'You are not allowed to start a new conversation, because your limit is 0.' ) );
		}
		return true;
	}

	if( empty( $max_new_threads ) )
	{ // user has no limit
		return false;
	}

	if( $max_new_threads > $new_threads_count )
	{ // user didn't reach his limit yet
		if( $add_message )
		{
			$Messages->add( sprintf( T_( 'You can still start %d new conversations today.' ), $max_new_threads - $new_threads_count ), 'success' );
		}
		return false;
	}

	if( $add_message )
	{ // user has already reached his limit
		$Messages->add( sprintf( T_( 'You have reached the limit of %d new conversations today. You can start new conversations again tomorrow.' ), $max_new_threads ) );
	}
	return true;
}


/**
 * Get a count of unread messages for the user
 *
 * @param integer User ID (0 - current user)
 * @return integer A count of unread messages
 */
function get_unread_messages_count( $user_ID = 0 )
{
	if( empty( $user_ID ) )
	{	// Current user
		if( !is_logged_in() )
		{
			return 0;
		}
		global $current_User;
		$user_ID = $current_User->ID;
	}

	global $DB;

	$SQL = new SQL();
	$SQL->SELECT( 'COUNT(*)' );
	$SQL->FROM( 'T_messaging__threadstatus ts' );
	$SQL->FROM_add( 'LEFT OUTER JOIN T_messaging__message mu
				ON ts.tsta_first_unread_msg_ID = mu.msg_ID' );
	$SQL->FROM_add( 'INNER JOIN T_messaging__message mm
				ON ts.tsta_thread_ID = mm.msg_thread_ID
				AND mm.msg_datetime >= mu.msg_datetime' );
	$SQL->WHERE( 'ts.tsta_first_unread_msg_ID IS NOT NULL' );
	$SQL->WHERE_and( 'ts.tsta_thread_leave_msg_ID IS NULL OR ts.tsta_first_unread_msg_ID <= tsta_thread_leave_msg_ID' );
	$SQL->WHERE_and( 'ts.tsta_user_ID = '.$DB->quote( $user_ID ) );

	return (int) $DB->get_var( $SQL->get() );
}


/**
 * Get the first ( oldest ) unread message of the user
 *
 * @param integer user ID
 * @return mixed NULL if the user doesn't have unread messages, or the oldest unread message datetime otherwise
 */
function get_first_unread_message_date( $user_ID )
{
	if( empty( $user_ID ) )
	{	// Current user
		if( !is_logged_in() )
		{
			return NULL;
		}
		global $current_User;
		$user_ID = $current_User->ID;
	}

	global $DB;

	$SQL = new SQL();
	$SQL->SELECT( 'min( msg_datetime )' );
	$SQL->FROM( 'T_messaging__threadstatus ts' );
	$SQL->FROM_add( 'INNER JOIN T_messaging__message mu
				ON ts.tsta_first_unread_msg_ID = mu.msg_ID' );
	$SQL->WHERE( 'ts.tsta_first_unread_msg_ID IS NOT NULL' );
	$SQL->WHERE_and( 'ts.tsta_thread_leave_msg_ID IS NULL OR ts.tsta_first_unread_msg_ID <= ts.tsta_thread_leave_msg_ID' );
	$SQL->WHERE_and( 'ts.tsta_user_ID = '.$DB->quote( $user_ID ) );

	return $DB->get_var( $SQL->get() );
}


/**
 * Get next 'Unread message reminder' datetime information for the given user. This is used on the user admin settings form.
 *
 * @param integer user ID
 * @result mixed string with the info field content if additional note is not required, and array( info, note ) otherwise
 */
function get_next_reminder_info( $user_ID )
{
	global $UserSettings, $DB, $servertimenow, $unread_message_reminder_delay, $unread_messsage_reminder_threshold;

	if( ! $UserSettings->get( 'notify_unread_messages', $user_ID ) )
	{ // The user doesn't want to recive unread messages reminders
		return T_('This user doesn\'t want to receive notification emails about unread messages.');
	}

	$first_unread_message_date = get_first_unread_message_date( $user_ID );
	if( empty( $first_unread_message_date ) )
	{ // The user doesn't have unread messages
		return T_('This user doesn\'t have unread messages.');
	}

	// We assume that reminder is not delayed because of the user was not logged in since too many days
	$reminder_is_delayed = false;
	$last_unread_messages_reminder = $UserSettings->get( 'last_unread_messages_reminder', $user_ID );
	if( empty( $last_unread_messages_reminder ) )
	{ // User didn't get new message notification or unread message reminder yet
		// Set reminder issue timestamp to one day after the first unread message was received
		$reminder_issue_ts = strtotime( '+1 day', strtotime( $first_unread_message_date ) );
	}
	else
	{ // Count next unread message reminder date, this can be delayed if the edited User didn't logged in since many days
		$UserCache = & get_UserCache();
		$edited_User = & $UserCache->get_by_ID( $user_ID );
		$lastseen_ts = strtotime( $edited_User->get( 'lastseen_ts' ) );
		$days_since_lastseen = floor(( $servertimenow - $lastseen_ts )/(60*60*24));
		// Presuppose that the User was not logged in since so many days, that should not get reminder any more
		$dont_send_reminder = true;
		// Get the number of delayed days for that case when we have to space out the notifications
		foreach( $unread_message_reminder_delay as $lastseen => $delay )
		{ // Get the corresponding number of delay for the edited User
			if( $days_since_lastseen < $lastseen )
			{ // We have found the correct delay value, reminders should be sent
				$dont_send_reminder = false;
				break;
			}
			// The reminder is delayed because the user was not logged in since more days then the first key of the delay array
			$reminder_is_delayed = true;
		}
		if( $dont_send_reminder )
		{ // User was not logged in since too long
			return sprintf( T_('The user has not logged in for %d days, so we will not send him notifications any more'), $days_since_lastseen );
		}

		// Set reminder issue timestamp to x days after the last unread message notification date, where x is the delay from the configuration array
		$reminder_issue_ts = strtotime( '+'.$delay.' day', strtotime( $last_unread_messages_reminder ) );
	}

	if( $reminder_issue_ts > $servertimenow )
	{ // The next reminder issue date is in the future
		$time_left = seconds_to_period( $reminder_issue_ts - $servertimenow );
		$info = sprintf( T_('%s left before next notification - sent by "Send reminders about unread messages" scheduled job'), $time_left );
	}
	else
	{ // The next reminder issue date was in the past
		$time_since = seconds_to_period( $servertimenow - $reminder_issue_ts );
		$info = sprintf( T_('next notification pending since %s - check the "Send reminders about unread messages" scheduled job'), $time_since );
	}

	if( $reminder_is_delayed )
	{ // Reminder is delayed, add a note about this
		$note = sprintf( T_('The user has not logged in for %d days, so we will space out notifications by %d days.'), $days_since_lastseen, $delay );
	}
	elseif( empty( $last_unread_messages_reminder ) )
	{ // The user didn't get unread messages reminder emails before
		$note = sprintf( T_('The user has never received a notification yet, so the first notification is sent with %s delay'), seconds_to_period( $unread_messsage_reminder_threshold ) );
	}
	else
	{ // Reminder is not delayed
		reset( $unread_message_reminder_delay );
		$lasstseen_threshold = key( $unread_message_reminder_delay );
		$delay = $unread_message_reminder_delay[$lasstseen_threshold];
		$note = sprintf( T_('The user has logged in in the last %d days, so we will space out notifications by %d days.'), $lasstseen_threshold, $delay );
	}

	return array( $info, $note );
}


/**
 * Mark a thread as read by the given user.
 *
 * @param integer thread ID
 * @param integer user ID
 */
function mark_as_read_by_user( $thrd_ID, $user_ID )
{
	global $DB;

	// Update user unread message status in the given thread
	$DB->query( 'UPDATE T_messaging__threadstatus
					SET tsta_first_unread_msg_ID = NULL
					WHERE tsta_thread_ID = '.$thrd_ID.'
					AND tsta_user_ID = '.$user_ID );
}


/**
 * Delete orphan threads
 *
 * @param integer user_ID - to delete those orphan threads where this user was involved, leave it to NULL to delete all orphan threads
 * @return boolean true on success
 */
function delete_orphan_threads( $user_ID = NULL )
{
	global $DB;

	$DB->begin();

	// get orphan thread ids
	$SQL = new SQL();
	$SQL->SELECT( 'DISTINCT( tsta_thread_ID )' );
	$SQL->FROM( 'T_messaging__threadstatus' );
	// sub query to select not orphan thread ids
	$sub_query = 'SELECT DISTINCT ( tsta_thread_ID )
					FROM T_messaging__threadstatus
					INNER JOIN T_users ON user_ID = tsta_user_ID';
	if( empty( $user_ID ) )
	{
		$SQL->WHERE( 'tsta_thread_ID NOT IN ( '.$sub_query.' )' );
	}
	else
	{
		$SQL->WHERE( 'tsta_user_ID = '.$user_ID );
		$SQL->WHERE_and( 'tsta_thread_ID NOT IN ( '.$sub_query.' AND tsta_user_ID <> '.$user_ID.' )' );
	}

	$thread_ids = implode( ', ', $DB->get_col( $SQL->get(), 0, 'Get orphan threads' ) );
	if( empty( $thread_ids ) )
	{ // orphan threads not exists
		$DB->commit();
		return true;
	}

	// Delete Messages
	$ret = $DB->query( 'DELETE FROM T_messaging__message WHERE msg_thread_ID IN ( '.$thread_ids.')' );

	// Delete Statuses
	$ret = $ret && $DB->query( 'DELETE FROM T_messaging__threadstatus WHERE tsta_thread_ID IN ( '.$thread_ids.')' );

	// Delete Threads
	$ret = $ret && $DB->query( 'DELETE FROM T_messaging__thread WHERE thrd_ID IN ( '.$thread_ids.')' );

	if( $ret )
	{
		$DB->commit();
		return true;
	}

	$DB->rollback();
	return false;
}


/**
 * Get ID of previous/next thread
 *
 * @param integer Current thread ID
 * @param string Type of url ('prev', 'next')
 * @return integer Thread ID
 */
function get_thread_prevnext_ID( $current_thread_ID, $type = 'prev' )
{
	global $thread_prevnext_ids_cache;

	if( empty( $current_thread_ID ) )
	{
		return false;
	}

	if( !isset( $thread_prevnext_ids_cache ) )
	{	// Initialize list with threads IDs
		global $DB;

		$threads_SQL = get_threads_results( array(
				'only_sql' => true
			) );

		$threads_SQL->SELECT( 'thrd_ID' );
		$thread_prevnext_ids_cache = $DB->get_col( $threads_SQL->get() );
	}

	$side_thread_i = ( $type == 'prev' ) ? 1 : -1;
	foreach( $thread_prevnext_ids_cache as $t => $thread_ID )
	{
		if( isset( $thread_prevnext_ids_cache[ $t + $side_thread_i ] ) && $thread_prevnext_ids_cache[ $t + $side_thread_i ] == $current_thread_ID )
		{	// This thread is previous/next for current thread
			return $thread_ID;
		}
	}
}


/**
 * Get the links for previous/next threads
 *
 * @param integer Current thread ID
 * @param string Type of url ('prev', 'next')
 * @return integer Thread ID
 */
function get_thread_prevnext_links( $current_thread_ID, $params = array() )
{
	$params = array_merge( array(
			'before'        => '<div class="floatright">',
			'after'         => '</div>',
			'title_text'    => T_('Conversations').': ',
			'separator'     => ' :: ',
			'previous_text' => '&laquo; '.T_('Previous'),
			'next_text'     => T_('Next').' &raquo;',
		), $params );

	$prev_thread_ID = get_thread_prevnext_ID( $current_thread_ID, 'prev' );
	if( !empty( $prev_thread_ID ) )
	{	// Link to previous thread
		$prev_link = '<a href="'.get_dispctrl_url( 'messages', 'thrd_ID='.$prev_thread_ID ).'">'.$params['previous_text'].'</a>';
	}

	$next_thread_ID = get_thread_prevnext_ID( $current_thread_ID, 'next' );
	if( !empty( $next_thread_ID ) )
	{	// Link to previous thread
		$next_link = '<a href="'.get_dispctrl_url( 'messages', 'thrd_ID='.$next_thread_ID ).'">'.$params['next_text'].'</a>';
	}

	if( empty( $prev_link ) && empty( $next_link ) )
	{	// No found previous and next threads
		return;
	}

	$r = $params['before'];
	$r .= $params['title_text'];

	if( !empty( $prev_link ) )
	{
		$r .= $prev_link;
	}

	if( !empty( $prev_link ) && !empty( $next_link ) )
	{
		$r .= $params['separator'];
	}

	if( !empty( $next_link ) )
	{
		$r .= $next_link;
	}

	$r .= $params['after'];

	return $r;
}


/**
 * Display threads results table
 *
 * @param array Params
 */
function threads_results_block( $params = array() )
{
	// Make sure we are not missing any param:
	$params = array_merge( array(
			'edited_User'          => NULL,
			'results_param_prefix' => 'actv_thrd_',
			'results_title'        => T_('Threads with private messages sent by the user'),
			'results_no_text'      => T_('User has not sent any private messages'),
		), $params );

	if( !is_logged_in() )
	{	// Only logged in users can access to this function
		return;
	}

	global $current_User;
	if( !$current_User->check_perm( 'users', 'edit' ) || !$current_User->check_perm( 'perm_messaging', 'reply' ) )
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

	global $DB, $current_User;

	param( 'user_tab', 'string', '', true );
	param( 'user_ID', 'integer', 0, true );


	// Check permission:
	if( $current_User->check_perm( 'perm_messaging', 'abuse' ) )
	{
		// Create result set:
		$threads_Results = get_threads_results( array(
				'results_param_prefix' => $params['results_param_prefix'],
				'user_ID' => $edited_User->ID,
				'sent_user_ID' => $edited_User->ID
			) );
		$threads_Results->Cache = & get_ThreadCache();
		$threads_Results->title = $params['results_title'];
		$threads_Results->no_results_text = $params['results_no_text'];

		if( $threads_Results->total_rows > 0 )
		{	// Display action icon to delete all records if at least one record exists
			$threads_Results->global_icon( sprintf( T_('Delete all private messages sent by %s'), $edited_User->login ), 'delete', '?ctrl=user&amp;user_tab=activity&amp;action=delete_all_messages&amp;user_ID='.$edited_User->ID.'&amp;'.url_crumb('user'), ' '.T_('Delete all'), 3, 4 );
		}

		// Load classes
		load_class( 'messaging/model/_thread.class.php', 'Thread' );

		// Initialize Results object
		threads_results( $threads_Results, array(
				'abuse_management' => 1,
				'show_only_date' => 1,
			) );

		if( is_ajax_content() )
		{ // init results param by template name
			if( !isset( $params[ 'skin_type' ] ) || ! isset( $params[ 'skin_name' ] ) )
			{
				debug_die( 'Invalid ajax results request!' );
			}
			$threads_Results->init_params_by_skin( $params[ 'skin_type' ], $params[ 'skin_name' ] );
		}

		$display_params = array(
			'before' => '<div class="results" style="margin-top:25px" id="threads_result">'
		);
		$threads_Results->display( $display_params );

		if( !is_ajax_content() )
		{	// Create this hidden div to get a function name for AJAX request
			echo '<div id="'.$params['results_param_prefix'].'ajax_callback" style="display:none">'.__FUNCTION__.'</div>';
		}
	}
	else
	{	// No permission for abuse management
		echo '<div style="margin-top:25px;font-weight:bold">'.sprintf( T_('User has sent %s private messages'), $edited_User->get_num_messages( 'sent' ) ).'</div>';
	}
}


/**
 * Initialize Results object for threads list
 *
 * @param object Results
 * @param array Params
 */
function threads_results( & $threads_Results, $params = array() )
{
	global $current_User;

	// Make sure we are not missing any param:
	$params = array_merge( array(
			'abuse_management' => 0, // 1 - abuse management mode
			'display_recipients' => true,
			'display_subject' => true,
			'display_date' => true,
			'display_read' => true,
			'display_actions' => true,
			'show_only_date' => 0,
		), $params );

	if( $params['display_recipients'] )
	{	// Display Recipients column
		$threads_Results->cols[] = array(
				'th' => $params['abuse_management'] ? T_('Between') : T_('With'),
				'th_class' => 'thread_with shrinkwrap',
				'td_class' => 'thread_with',
				'td' => '%col_thread_recipients( #thrd_ID#, '.(int)$params['abuse_management'].' )%',
			);
	}

	if( $params['display_subject'] )
	{	// Display Subject column
		$threads_Results->cols[] = array(
				'th' => T_('Subject'),
				'th_class' => 'thread_subject',
				'td_class' => 'thread_subject',
				'td' => '%col_thread_subject_link( #thrd_ID#, #thrd_title#, #thrd_msg_ID#, #tsta_thread_leave_msg_ID#, '.(int)$params['abuse_management'].' )%',
			);
	}

	if( $params['display_date'] )
	{	// Display Date column
		$show_only_date = $params[ 'show_only_date' ];
		$threads_Results->cols[] = array(
				'th' => T_('Last msg'),
				'th_class' => 'shrinkwrap',
				'td_class' => 'shrinkwrap',
				'td' => '~conditional( #thrd_msg_ID#>0 && #thrd_msg_ID#!="abuse", \'%col_thread_date(#thrd_unread_since#,'.$show_only_date.')%\', \'%col_thread_date(#thrd_datemodified#,'.$show_only_date.')%\')~'
			);
	}

	if( $params['display_read'] )
	{	// Display Read column
		$threads_Results->cols[] = array(
				'th' => T_('Read?'),
				'th_class' => 'shrinkwrap',
				'td_class' => 'center',
				'td' => '%col_thread_read_by( #thrd_ID# )%',
			);
	}

	if( $params['display_actions'] )
	{	// Display Actions column
		if( $current_User->check_perm( 'perm_messaging', 'delete' ) )
		{	// We have permission to modify:
			$threads_Results->cols[] = array(
					'th' => T_('Del'),
					'th_class' => 'shrinkwrap',
					'td_class' => 'shrinkwrap',
					'td' => '%col_thread_delete_action(  #thrd_ID#  )%',
				);
		}
	}
}


/**
 * Helper functions to display Threads results.
 * New ( not display helper ) functions must be created above threads_results function
 */

/**
 * Get thread's recipients
 *
 * @param integer Thread ID
 * @param boolean TRUE for abuse management mode
 * @return string Recipients (avatar + login)
 */
function col_thread_recipients( $thread_ID, $abuse_management )
{
	global $DB, $Blog;

	$SQL = new SQL();
	$SQL->SELECT( 'user_login' );
	$SQL->FROM( 'T_messaging__threadstatus mts' );
	$SQL->FROM_add( 'LEFT JOIN T_users u ON user_ID = tsta_user_ID' );
	$SQL->WHERE( 'tsta_thread_ID = '.$thread_ID );
	if( !$abuse_management )
	{	// Show current user only in abuse management
		global $current_User;
		$SQL->WHERE_and( 'tsta_user_ID != '.$current_User->ID );
	}
	$recipients = $DB->get_col( $SQL->get() );

	$image_size = isset( $Blog ) ? $Blog->get_setting( 'image_size_messaging' ) : 'crop-top-32x32';

	return get_avatar_imgtags( $recipients, true, true, $image_size, 'avatar_before_login_middle mb1', '', NULL, true, '<br />', true );
}


/**
 * Get subject as link with icon (read or unread)
 *
 * @param thread ID
 * @param thread title
 * @param message ID (If ID > 0 - message is still unread)
 * @param message ID
 * @param boolean TRUE for abuse management mode
 * @return string link with subject
 */
function col_thread_subject_link( $thrd_ID, $thrd_title, $thrd_msg_ID, $thrd_leave_msg_ID, $abuse_management )
{
	$messages_url = get_dispctrl_url( 'messages' );
	if( $thrd_title == '' )
	{
		$thrd_title = '<i>(no subject)</i>';
	}

	$read_icon = '';
	if( $thrd_msg_ID != 'abuse' )
	{	// Don't show the read status for abuse management
		if( !empty( $thrd_leave_msg_ID ) )
		{ // Conversation is left
			$read_icon = get_icon( 'bullet_black', 'imgtag', array( 'style' => 'margin:0 2px', 'alt' => T_( "Conversation left" ) ) );
			if( $thrd_msg_ID > 0 )
			{ // also show unread messages icon, because user has not seen all messages yet
				$read_icon .= get_icon( 'bullet_red', 'imgtag', array( 'style' => 'margin:0 2px', 'alt' => T_( "You have unread messages" ) ) );
			}
		}
		elseif( $thrd_msg_ID > 0 )
		{ // Message is unread OR Show all messages in Abuse Management as unread
			$read_icon = get_icon( 'bullet_red', 'imgtag', array( 'style' => 'margin:0 2px', 'alt' => T_( "You have unread messages" ) ) );
		}
		else
		{ // Message is read
			$read_icon = get_icon( 'allowback', 'imgtag', array( 'alt' => "You have read all messages" ) );
		}
	}

	$tab = '';
	if( $abuse_management )
	{ // We are in Abuse Management
		$tab = '&amp;tab=abuse';
	}

	$link = $read_icon.'<a href="'.$messages_url.'&amp;thrd_ID='.$thrd_ID.$tab.'" title="'.T_('Show messages...').'">';
	$link .= '<strong>'.$thrd_title.'</strong>';
	$link .= '</a>';

	return $link;
}


/**
 * Return the given date in the correct date format
 *
 * @param string Date
 * @param boolean TRUE to show only date
 * @return string Date
*/
function col_thread_date( $date, $show_only_date )
{
	if( $show_only_date )
	{
		return mysql2localedate( $date );
	}

	return mysql2localedatetime( $date );
}


/**
 * Read? column
 *
 * @param integer Thread ID
 * @return string Status icons
 */
function col_thread_read_by( $thread_ID )
{
	global $DB;

	// Select read/unread users for this thread
	$recipients_SQL = get_threads_recipients_sql( $thread_ID );

	$read_by = '';

	if( $row = $DB->get_row( $recipients_SQL->get() ) )
	{
		if( !empty( $row->thr_read ) )
		{
			$read_by .= get_avatar_imgtags( $row->thr_read, false, false, 'crop-top-15x15', '', '', true, false );
		}

		if( !empty( $row->thr_unread ) )
		{
			if( !empty( $read_by ) )
			{
				$read_by .= '<br />';
			}
			$read_by .= get_avatar_imgtags( $row->thr_unread, false, false, 'crop-top-15x15', '', '', false, false );
		}

		if( !empty( $row->thr_left ) )
		{
			if( !empty( $read_by ) )
			{
				$read_by .= '<br />';
			}
			$read_by .= get_avatar_imgtags( $row->thr_left, false, false, 'crop-top-15x15', '', '', 'left', false );
		}
	}

	return $read_by;
}


/**
 * Get action icons to delete thread
 *
 * @param integer Thread ID
 * @return string Action icon
 */
function col_thread_delete_action( $thread_ID )
{
	global $Blog, $samedomain_htsrv_url, $admin_url;

	if( is_admin_page() )
	{
		$redirect_to = rawurlencode( regenerate_url( '', '', '', '&' ) );
		return action_icon( T_( 'Delete'), 'delete', $admin_url.'?ctrl=threads&amp;thrd_ID='.$thread_ID.'&amp;action=delete&amp;'.url_crumb( 'messaging_threads' ).'&amp;redirect_to='.$redirect_to );
	}
	else
	{
		$redirect_to = get_dispctrl_url( 'threads' );
		return action_icon( T_( 'Delete'), 'delete', $samedomain_htsrv_url.'action.php?mname=messaging&thrd_ID='.$thread_ID.'&action=delete&redirect_to='.$redirect_to.'&'.url_crumb( 'messaging_threads' ) );
	}
}

/**
 * Helper functions to display Threads results.
 * New ( not display helper ) functions must be created above threads_results function
 */

?>