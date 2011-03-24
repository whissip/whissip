<?php
/**
 * This is the handler for asynchronous logging calls.
 *
 * This has been copied from async.php to provide better performance and
 * anonymous access.
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
 * @package evocore
 *
 * @version $Id$
 */


/**
 * Do the MAIN initializations:
 */
require_once dirname(__FILE__).'/../conf/_config.php';

// Prevent logging of this hit
$is_htsrv_request = true;

/**
 * HEAVY :(
 *
 * @todo dh> refactor _main.inc.php to be able to include small parts
 *           (e.g. $current_User, charset init, ...) only..
 *           It worked already for $DB (_connect_db.inc.php).
 * fp> I think I'll try _core_main.inc , _evo_main.inc , _blog_main.inc ; this file would only need _core_main.inc
 */
require_once $inc_path.'_main.inc.php';

param( 'action', 'string', true );

// Make sure the async responses are never cached:
header_nocache();
// header_content_type( 'text/html', $io_charset );

switch( $action )
{
  case 'log_view':
    // get item
    param( 'item_ID', 'integer', true );
    param( 'v', 'string', true );

    $ItemCache = get_ItemCache();
    $Item = $ItemCache->get_by_ID($item_ID);

    if( $v != $Item->get_viewcount_verify_hash() ) {
      bad_request_die('Invalid request: $v mismatch: '.$v.'/'.$Item->get_viewcount_verify_hash());
    }

    header_content_type('text/plain'); // better not image, it might get considered/reported as corrupted.

    if( ! $Item->inc_viewcount() ) {
      header('HTTP/1.0 304 Not Modified'); # useful in logs
    }
    exit(0);
}

