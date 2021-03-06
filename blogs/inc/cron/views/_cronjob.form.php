<?php
/**
 * This file implements the UI view for the cron job form.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}
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

global $localtimenow, $cron_job_names;

$Form = new Form( NULL, 'cronjob' );

$Form->global_icon( T_('Cancel!'), 'close', regenerate_url( 'action' ) );

$Form->begin_form( 'fform', T_('New scheduled job') );

	$Form->add_crumb( 'crontask' );
	$Form->hiddens_by_key( get_memorized( 'action' ) );
	$Form->hidden( 'action', 'create' );

	$Form->begin_fieldset( T_('Job details').get_manual_link('scheduler_job_form') );

		$Form->select_input_array( 'cjob_type', NULL, $cron_job_names, T_('Job type') );

		$Form->date_input( 'cjob_date', date2mysql( $localtimenow ), T_('Schedule date'), array(
							 'required' => true ) );

		$Form->time_input( 'cjob_time', date2mysql( $localtimenow ), T_('Schedule time'), array(
							 'required' => true ) );

		$Form->duration_input( 'cjob_repeat_after', 0, T_('Repeat every'), 'days', 'minutes', array( 'minutes_step' => 1 ) );

	$Form->end_fieldset();

$Form->end_form( array(
			array( 'submit', 'submit', T_('Create'), 'SaveButton' ),
			array( 'reset', '', T_('Reset'), 'ResetButton' ),
		) );


/*
 * $Log$
 * Revision 1.8  2010/02/08 17:52:14  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.7  2010/01/30 18:55:22  blueyed
 * Fix "Assigning the return value of new by reference is deprecated" (PHP 5.3)
 *
 * Revision 1.6  2010/01/03 13:45:38  fplanque
 * set some crumbs (needs checking)
 *
 * Revision 1.5  2009/10/28 09:39:52  efy-maxim
 * position of arguments of duration_input function have been changed
 *
 * Revision 1.4  2009/03/08 23:57:42  fplanque
 * 2009
 *
 * Revision 1.3  2008/01/21 09:35:28  fplanque
 * (c) 2008
 *
 * Revision 1.2  2007/09/12 21:00:31  fplanque
 * UI improvements
 *
 * Revision 1.1  2007/06/25 10:59:48  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.5  2007/04/26 00:11:09  fplanque
 * (c) 2007
 *
 * Revision 1.4  2007/01/23 08:57:36  fplanque
 * decrap!
 *
 * Revision 1.3  2006/11/24 18:27:25  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.2  2006/07/01 23:47:42  fplanque
 * fixed dirty bug
 *
 * Revision 1.1  2006/06/26 23:09:34  fplanque
 * Really working cronjob environment :)
 *
 */
?>