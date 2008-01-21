<?php
/**
 * This is the handler/dispatcher for asynchronous calls (both AJax calls and HTTP GET fallbacks)
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2008 by Francois PLANQUE - {@link http://fplanque.net/}
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
 * @package evocore
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

param( 'collapse', 'string', '' );
if( !empty( $collapse ) )
{	// We want to record a 'collapse' value:
	$set_status = 'collapsed';
	$set_target = $collapse;
}
param( 'expand', 'string', '' );
if( !empty( $expand ) )
{	// We want to record an 'expand' value:
	$set_status = 'expanded';
	$set_target = $expand;
}

if( !empty($set_target) )
{
	switch( $set_target )
	{
		case 'antispam_filters':
		case 'crontab_filters':
		case 'refdom_filters':	// referring domains
		case 'uagnt_filters':	// user agents
		case 'user_filters':
			// We have a valid value:
			$Session->set( $set_target, $set_status );
			break;

		default:
			// Warning: you may not see this on AJAX calls
			echo( 'Cannot ['.$set_status.'] unknown param ['.$set_target.']' );
	}
}


/*
 * $Log$
 * Revision 1.10  2008/01/21 09:35:23  fplanque
 * (c) 2008
 *
 * Revision 1.9  2007/06/25 10:58:50  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.8  2007/04/26 00:11:04  fplanque
 * (c) 2007
 *
 * Revision 1.7  2006/11/24 18:27:22  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.6  2006/08/24 21:41:13  fplanque
 * enhanced stats
 *
 * Revision 1.5  2006/06/26 23:10:24  fplanque
 * minor / doc
 *
 * Revision 1.4  2006/06/25 20:04:06  blueyed
 * doc/todo
 *
 * Revision 1.3  2006/06/25 17:42:46  fplanque
 * better use of Results class (mainly for filtering)
 *
 * Revision 1.2  2006/06/13 21:52:44  blueyed
 * Added files from 1.8 branch
 *
 * Revision 1.1.2.1  2006/06/12 20:00:33  fplanque
 * one too many massive syncs...
 */
?>