<?php
/**
 * This file is the template that includes required css files to display users
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2013 by Francois Planque - {@link http://fplanque.com/}
 *
 * @package evoskins
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

global $htsrv_url, $Messages;

if( !is_logged_in() && !$Settings->get( 'allow_anonymous_user_list' ) ) 
{ // Redirect to the login page if not logged in and allow anonymous user setting is OFF
	$Messages->add( T_( 'You must log in to view the user directory.' ) );
	header_redirect( get_login_url( 'cannot see user' ), 302 );
	// will have exited
}

if( is_logged_in() && ( !check_user_status( 'can_view_users' ) ) )
{ // user status doesn't permit to view users list
	if( check_user_status( 'can_be_validated' ) )
	{ // user is logged in but his/her account is not active yet
		// Redirect to the account activation page
		$Messages->add( T_( 'You must activate your account before you can view the user directory. <b>See below:</b>' ) );
		header_redirect( get_activate_info_url(), 302 );
		// will have exited
	}

	// set where to redirect
	$error_redirect_to = ( empty( $Blog) ? $baseurl : $Blog->gen_blogurl() );
	$Messages->add( T_( 'Your account status currently does not permit to view the user directory.' ) );
	header_redirect( $error_redirect_to, 302 );
	// will have exited
}

// var bgxy_expand is used by toggle_filter_area() and toggle_clickopen()
// var htsrv_url is used for AJAX callbacks
add_js_headline( "// Paths used by JS functions:
		var bgxy_expand = '".get_icon( 'expand', 'xy' )."';
		var bgxy_collapse = '".get_icon( 'collapse', 'xy' )."';" );

// Require results.css to display thread query results in a table
require_css( 'results.css' ); // Results/tables styles

// Require functions.js to show/hide a panel with filters
require_js( 'functions.js', 'blog' );
// Include this file to expand/collapse the filters panel when JavaScript is disabled
require_once $inc_path.'_filters.inc.php';

require $ads_current_skin_path.'index.main.php';

/*
 * $Log$
 * Revision 1.11  2013/11/06 08:05:36  efy-asimo
 * Update to version 5.0.1-alpha-5
 *
 */
?>