<?php
/**
 * This file implements the UI view for the blogs list on user profile page.
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @package admin
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * @var instance of GeneralSettings class
 */
global $Settings;
/**
 * @var instance of User class
 */
global $edited_User;
/**
 * @var current action
 */
global $action;
/**
 * @var user permission, if user is only allowed to edit his profile
 */
global $user_profile_only;

global $dispatcher, $user_tab, $user_ID;


memorize_param( 'user_tab', 'string', '', $user_tab );
memorize_param( 'user_ID', 'integer', 0, $user_ID );

// Begin payload block:
$this->disp_payload_begin();

if( !$user_profile_only )
{
	echo '<span style="float:right">';
	echo action_icon( T_('Delete this user!'), 'delete', '?ctrl=users&amp;action=delete&amp;user_ID='.$edited_User->ID.'&amp;'.url_crumb('user'), ' '.T_('Delete'), 3, 4  );
	echo action_icon( T_('Compose message'), 'comments', '?ctrl=threads&action=new&user_login='.$edited_User->login );
	echo action_icon( ( $action != 'view' ? T_('Cancel editing!') : T_('Close user profile!') ), 'close', regenerate_url( 'user_ID,action,ctrl', 'ctrl=users' ) );
	echo '</span>';
}

echo '<h2>'.sprintf( T_('View personal blogs for user %s'), $edited_User->dget('fullname').' &laquo;'.$edited_User->dget('login').'&raquo;' ).'</h2>';

$SQL = 'SELECT * FROM T_blogs WHERE blog_owner_user_ID = '.$DB->quote($edited_User->ID);

// Create result set:
$Results = new Results( $SQL, 'blog_' );
$Results->Cache = & get_BlogCache();
$Results->title = T_('Personal blogs');
$Results->no_results_text = T_('You have no personal blogs yet');

$Results->cols[] = array(
						'th' => T_('ID'),
						'order' => 'blog_ID',
						'th_class' => 'shrinkwrap',
						'td_class' => 'shrinkwrap',
						'td' => '$blog_ID$',
					);

function disp_coll_name( $coll_name, $coll_ID )
{
	$edit_url = regenerate_url( 'ctrl', 'ctrl=coll_settings&amp;blog='.$coll_ID );

	return '<a href="'.$edit_url.'" title="'.T_('Edit properties...').'">'.$coll_name.'</a>';
}
$Results->cols[] = array(
						'th' => T_('Name'),
						'order' => 'blog_shortname',
						'td' => '<strong>%disp_coll_name( #blog_shortname#, #blog_ID# )%</strong>',
					);

$Results->cols[] = array(
						'th' => T_('Full Name'),
						'order' => 'blog_name',
						'td' => '%strmaxlen( #blog_name#, 40, NULL, "raw" )%',
					);

$Results->cols[] = array(
						'th' => T_('Blog URL'),
						'td' => '<a href="@get(\'url\')@">@get(\'url\')@</a>',
					);

$Results->cols[] = array(
						'th' => T_('Locale'),
						'order' => 'blog_locale',
						'th_class' => 'shrinkwrap',
						'td_class' => 'shrinkwrap',
						'td' => '%locale_flag( #blog_locale# )%',
					);


function disp_actions( $curr_blog_ID )
{
	$r = action_icon( T_('Edit properties...'), 'properties', regenerate_url( 'ctrl', 'ctrl=coll_settings&amp;blog='.$curr_blog_ID ) );
	$r .= action_icon( T_('Edit categories...'), 'edit', regenerate_url( 'ctrl', 'ctrl=chapters&amp;blog='.$curr_blog_ID ) );
	$r .= action_icon( T_('Delete this blog...'), 'delete', regenerate_url( 'ctrl', 'ctrl=collections&amp;action=delete&amp;blog='.$curr_blog_ID.'&amp;'.url_crumb('collection') ) );

	return $r;
}
$Results->cols[] = array(
						'th' => T_('Actions'),
						'th_class' => 'shrinkwrap',
						'td_class' => 'shrinkwrap',
						'td' => '%disp_actions( #blog_ID# )%',
					);


$Results->display( NULL, 'session' );

// End payload block:
$this->disp_payload_end();


/*
 * $Log$
 * Revision 1.3  2011/05/11 07:11:52  efy-asimo
 * User settings update
 *
 * Revision 1.2  2011/02/20 22:31:39  fplanque
 * minor / doc
 *
 * Revision 1.1  2010/11/04 18:29:46  sam2kb
 * View personal blogs in user profile
 *
 */
?>