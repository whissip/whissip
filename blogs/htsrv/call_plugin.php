<?php
/**
 * This file gets used to access {@link Plugin} methods that are marked to be accessible this
 * way. See {@link Plugin::GetHtsrvMethods()}.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2009 by Francois PLANQUE - {@link http://fplanque.net/}
 * Parts of this file are copyright (c)2004-2006 by Daniel HAHLER - {@link http://thequod.de/contact}.
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
 * Daniel HAHLER grants Francois PLANQUE the right to license
 * Daniel HAHLER's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package htsrv
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE
 * @author blueyed: Daniel HAHLER
 *
 * @version $Id$
 */


/**
 * Initialize:
 * TODO: Don't do a full init!
 */
require_once dirname(__FILE__).'/../conf/_config.php';
require_once $inc_path.'_main.inc.php';


param( 'plugin_ID', 'integer', true );
param( 'method', 'string', '' );
param( 'params', 'string', null ); // serialized

if( is_null($params) )
{ // Default:
	$params = array();
}
else
{ // params given. This may result in "false", but this means that unserializing failed.
	$params = @unserialize($params);
}


if( $plugin_ID )
{
	$Plugin = & $Plugins->get_by_ID( $plugin_ID );

	if( ! $Plugin )
	{
		debug_die( 'Invalid Plugin!' );
	}


	if( method_exists( $Plugin, 'get_htsrv_methods' ) )
	{ // TODO: get_htsrv_methods is deprecated, but should stay here for transformation! (blueyed, 2006-04-27)
		if( ! in_array( $method, $Plugin->get_htsrv_methods() ) )
		{
			debug_die( 'Call to non-htsrv Plugin method!' );
		}
	}
	else
	if( ! in_array( $method, $Plugin->GetHtsrvMethods() ) )
	{
		debug_die( 'Call to non-htsrv Plugin method!' );
	}
	elseif( ! method_exists( $Plugin, 'htsrv_'.$method ) )
	{
		debug_die( 'htsrv method does not exist!' );
	}

	// Call the method:
	$Plugins->call_method( $Plugin->ID, 'htsrv_'.$method, $params );
}


/* {{{ Revision log:
 * $Log$
 * Revision 1.12  2009/03/08 23:57:36  fplanque
 * 2009
 *
 * Revision 1.11  2008/01/21 09:35:23  fplanque
 * (c) 2008
 *
 * Revision 1.10  2007/07/04 21:11:11  blueyed
 * $params default to array()
 *
 * Revision 1.9  2007/04/26 00:11:14  fplanque
 * (c) 2007
 *
 * Revision 1.8  2007/01/18 00:21:16  blueyed
 * doc
 *
 * Revision 1.7  2006/11/24 18:27:22  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.6  2006/09/30 20:03:52  blueyed
 * Do not debug_die() if Plugins htsrv-method returned false.
 *
 * Revision 1.5  2006/04/27 20:07:19  blueyed
 * Renamed Plugin::get_htsrv_methods() to GetHtsrvMethods() (normalization)
 *
 * Revision 1.4  2006/04/19 20:13:48  fplanque
 * do not restrict to :// (does not catch subdomains, not even www.)
 *
 * Revision 1.3  2006/03/12 23:08:53  fplanque
 * doc cleanup
 *
 * Revision 1.2  2006/02/28 18:07:55  blueyed
 * Path fixes
 *
 * Revision 1.1  2006/01/28 23:43:35  blueyed
 * htsrv method for plugins. See Plugin::get_htsrv_url().
 *
 * }}}
 */
?>