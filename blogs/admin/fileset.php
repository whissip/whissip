<?php
/**
 * This file implements the UI controller for file settings management.
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2004 by Francois PLANQUE - {@link http://fplanque.net/}.
 * Parts of this file are copyright (c)2004 by Daniel HAHLER - {@link http://thequod.de/contact}.
 *
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
 * {@internal
 * b2evolution is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * b2evolution is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with b2evolution; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 * }}
 *
 * {@internal
 * Daniel HAHLER grants Fran�ois PLANQUE the right to license
 * Daniel HAHLER's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Fran�ois PLANQUE.
 * @author blueyed: Daniel HAHLER.
 *
 * @version $Id$
 */

/**
 * Includes:
 */
require( dirname(__FILE__).'/_header.php' );
$admin_tab = 'options';
$tab = 'files';
$admin_pagetitle = T_('Settings').$admin_path_seprator.T_('Files');

param( 'action', 'string' );

require( dirname(__FILE__).'/_menutop.php' );

switch( $action )
{
	case 'update':
		// Check permission:
		$current_User->check_perm( 'options', 'edit', true );

		param( 'submit', 'string', '' );

		if( $submit == T_('Set defaults') )
		{
			$Settings->deleteArray( array( 'fm_enabled', 'fm_enable_roots_blog', 'fm_enable_roots_group', 'fm_enable_roots_user', 'fm_enable_create_dir', 'fm_enable_create_file', 'upload_enabled', 'upload_allowedext', 'upload_allowedmime', 'upload_maxkb' ) );
		}
		else
		{
			// Filemanager
			$Settings->setByParam( 'fm_enabled', 'fm_enabled', 'integer', 0 );
			$Settings->setByParam( 'fm_enable_roots_blog', 'fm_enable_roots_blog', 'integer', 0 );
			$Settings->setByParam( 'fm_enable_roots_group', 'fm_enable_roots_group', 'integer', 0 );
			$Settings->setByParam( 'fm_enable_roots_user', 'fm_enable_roots_user', 'integer', 0 );
			$Settings->setByParam( 'fm_enable_create_dir', 'fm_enable_create_dir', 'integer', 0 );
			$Settings->setByParam( 'fm_enable_create_file', 'fm_enable_create_file', 'integer', 0 );

			// Upload
			$Settings->setByParam( 'upload_enabled', 'upload_enabled', 'integer', 0 );
			param( 'upload_allowedext', 'string', true );
			$Settings->set( 'upload_allowedext', trim($upload_allowedext) );
			param( 'upload_allowedmime', 'string', true );
			$Settings->set( 'upload_allowedmime', trim($upload_allowedmime) );
			$Settings->setByParam( 'upload_maxkb', 'upload_maxkb', 'integer', true );

			#param( 'upload_minlevel', 'integer', true );
			#$Settings->set( 'upload_minlevel', $reloadpage_minlevel );
		}

		if( $Settings->updateDB() )
		{
			$Messages->add( T_('File settings updated.'), 'note' );
		}

		break;
}

$Messages->displayParagraphs( 'note' );

// Check permission to view:
$current_User->check_perm( 'options', 'view', true );

// Display submenu:
require dirname(__FILE__).'/_submenu.inc.php';

require dirname(__FILE__).'/_set_files.form.php';

require dirname(__FILE__).'/_sub_end.inc.php';

require dirname(__FILE__).'/_footer.php';
?>