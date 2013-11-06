<?php
/**
 * This file implements the UI view for Tools > Email > Sent
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
 * }}
 *
 * @package admin
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

global $blog, $admin_url, $UserSettings;


global $datestartinput, $datestart, $datestopinput, $datestop, $email;

if( param_date( 'datestartinput', T_('Invalid date'), false,  NULL ) !== NULL )
{	// We have a user provided localized date:
	memorize_param( 'datestart', 'string', NULL, trim(form_date($datestartinput)) );
}
else
{	// We may have an automated param transmission date:
	param( 'datestart', 'string', '', true );
}
if( param_date( 'datestopinput', T_('Invalid date'), false, NULL ) !== NULL )
{	// We have a user provided localized date:
	memorize_param( 'datestop', 'string', NULL, trim(form_date($datestopinput)) );
}
else
{	// We may have an automated param transmission date:
	param( 'datestop', 'string', '', true );
}
param( 'email', 'string', '', true );

// Create result set:

$SQL = new SQL();
$SQL->SELECT( 'SQL_NO_CACHE emlog_ID, emlog_timestamp, emlog_user_ID, emlog_to, emlog_result, emlog_subject' );
$SQL->FROM( 'T_email__log' );

$CountSQL = new SQL();
$CountSQL->SELECT( 'SQL_NO_CACHE COUNT(emlog_ID)' );
$CountSQL->FROM( 'T_email__log' );

if( !empty( $datestart ) )
{	// Filter by start date
	$SQL->WHERE_and( 'emlog_timestamp >= '.$DB->quote( $datestart.' 00:00:00' ) );
	$CountSQL->WHERE_and( 'emlog_timestamp >= '.$DB->quote($datestart.' 00:00:00' ) );
}
if( !empty( $datestop ) )
{	// Filter by end date
	$SQL->WHERE_and( 'emlog_timestamp <= '.$DB->quote( $datestop.' 23:59:59' ) );
	$CountSQL->WHERE_and( 'emlog_timestamp <= '.$DB->quote( $datestop.' 23:59:59' ) );
}
if( !empty( $email ) )
{	// Filter by email
	$SQL->WHERE_and( 'emlog_to LIKE '.$DB->quote( $email ) );
	$CountSQL->WHERE_and( 'emlog_to LIKE '.$DB->quote( $email ) );
}


$Results = new Results( $SQL->get(), 'emlog_', 'D', $UserSettings->get( 'results_per_page' ), $CountSQL->get() );

$Results->title = T_('Sent emails');

/**
 * Callback to add filters on top of the result set
 *
 * @param Form
 */
function filter_email_sent( & $Form )
{
	global $datestart, $datestop, $email;

	$Form->date_input( 'datestartinput', $datestart, T_('From date') );
	$Form->date_input( 'datestopinput', $datestop, T_('To date') );
	$Form->text_input( 'email', $email, 40, T_('Email') );
}
$Results->filter_area = array(
	'callback' => 'filter_email_sent',
	'presets' => array(
		'all' => array( T_('All'), $admin_url.'?ctrl=email&amp;tab=sent'),
		)
	);

$Results->cols[] = array(
		'th' => T_('ID'),
		'order' => 'emlog_ID',
		'th_class' => 'shrinkwrap',
		'td_class' => 'right',
		'td' => '$emlog_ID$',
	);

$Results->cols[] = array(
		'th' => T_('Date Time'),
		'order' => 'emlog_timestamp',
		'default_dir' => 'D',
		'th_class' => 'shrinkwrap',
		'td_class' => 'timestamp compact_data',
		'td' => '%mysql2localedatetime_spans( #emlog_timestamp#, "M-d" )%',
	);

$Results->cols[] = array(
		'th' => T_('Result'),
		'order' => 'emlog_result',
		'td' => '%emlog_result_info( #emlog_result#, array( \'link_blocked\' => true, \'email\' => #emlog_to# ) )%',
		'th_class' => 'shrinkwrap',
		'td_class' => 'nowrap'
	);

function emlog_to( $emlog_ID, $emlog_to, $emlog_user_ID )
{
	$deleted_user_note = '';
	if( !empty( $emlog_user_ID ) )
	{	// Get user
		$UserCache = & get_UserCache();
		if( $User = $UserCache->get_by_ID( $emlog_user_ID, false ) )
		{
			$to = $User->get_identity_link();
		}
		else
		{ // could not find user, probably it was deleted
			$deleted_user_note = '( '.T_( 'Deleted user' ).' )';
		}
	}

	if( empty( $to ) )
	{	// User is not defined
		global $admin_url;
		$to = '<a href="'.$admin_url.'?ctrl=email&amp;tab=sent&amp;emlog_ID='.$emlog_ID.'">'.htmlspecialchars( $emlog_to ).$deleted_user_note.'</a>';
	}

	return $to;
}
$Results->cols[] = array(
		'th' => T_('To'),
		'order' => 'emlog_to',
		'td' => '%emlog_to( #emlog_ID#, #emlog_to#, #emlog_user_ID# )%',
	);

$Results->cols[] = array(
		'th' => T_('Subject'),
		'order' => 'emlog_subject',
		'td' => '<a href="'.$admin_url.'?ctrl=email&amp;tab=sent&amp;emlog_ID=$emlog_ID$">%htmlspecialchars(#emlog_subject#)%</a>',
	);



// Display results:
$Results->display();

/*
 * $Log$
 * Revision 1.3  2013/11/06 09:08:59  efy-asimo
 * Update to version 5.0.2-alpha-5
 *
 */
?>