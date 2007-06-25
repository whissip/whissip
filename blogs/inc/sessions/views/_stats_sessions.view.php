<?php
/**
 * This file implements the UI view for the Session stats.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2007 by Francois PLANQUE - {@link http://fplanque.net/}
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

global $blog, $admin_url, $rsc_url;

echo '<h2>'.T_('Last sessions').'</h2>';

// Create result set:
$sql = 'SELECT user_login, COUNT( sess_ID ) AS nb_sessions, MAX( sess_lastseen ) AS sess_lastseen
					FROM T_sessions LEFT JOIN T_users ON sess_user_ID = user_ID
				 GROUP BY user_login';

$count_sql = 'SELECT COUNT( DISTINCT(user_login) )
								FROM T_sessions LEFT JOIN T_users ON sess_user_ID = user_ID';

$Results = & new Results( $sql, 'usess_', '-D', 20, $count_sql );

$Results->title = T_('Last sessions');

function stat_session_login( $login )
{
	if( empty($login) )
	{
		return T_('Anonymous');
	}
	else
	{
		return '<strong>'.$login.'</strong>';
	}
}
$Results->cols[] = array(
						'th' => T_('User login'),
						'order' => 'user_login',
						'td' => '%stat_session_login( #user_login# )%',
					);

$Results->cols[] = array(
						'th' => T_('Last seen'),
						'order' => 'sess_lastseen',
						'default_dir' => 'D',
						'td' => '%mysql2localedatetime( #sess_lastseen# )%',
 					);

$Results->cols[] = array(
						'th' => T_('Session count'),
						'order' => 'nb_sessions',
						'td_class' => 'right',
						'total_class' => 'right',
						'td' => '$nb_sessions$',
					);

// Display results:
$Results->display();

/*
 * $Log$
 * Revision 1.1  2007/06/25 11:01:07  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.3  2007/04/26 00:11:13  fplanque
 * (c) 2007
 *
 * Revision 1.2  2006/11/24 18:27:26  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 */
?>