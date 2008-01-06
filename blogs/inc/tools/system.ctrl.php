<?php
/**
 * This file implements the UI controller for System configuration and analysis.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2007 by Francois PLANQUE - {@link http://fplanque.net/}
 * Parts of this file are copyright (c)2006 by Daniel HAHLER - {@link http://daniel.hahler.de/}.
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
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE.
 * @author blueyed
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


// Check minimum permission:
$current_User->check_perm( 'options', 'view', true );

$AdminUI->set_path( 'tools', 'system' );

// Display <html><head>...</head> section! (Note: should be done early if actions do not redirect)
$AdminUI->disp_html_head();

// Display title, menu, messages, etc. (Note: messages MUST be displayed AFTER the actions)
$AdminUI->disp_body_top();

// Begin payload block:
$AdminUI->disp_payload_begin();

function init_system_check( $name, $value )
{
	global $syscheck_name, $syscheck_value;
	$syscheck_name = $name;
	$syscheck_value = $value;
}

function disp_system_check( $condition, $message = '' )
{
	global $syscheck_name, $syscheck_value;
	echo '<div class="system_check">';
	echo '<div class="system_check_name">';
	echo $syscheck_name;
	echo '</div>';
	echo '<div class="system_check_value_'.$condition.'">';
	echo $syscheck_value;
	echo '&nbsp;</div>';
	if( !empty( $message ) )
	{
		echo '<div class="system_check_message_'.$condition.'">';
		echo $message;
		echo '</div>';
	}
	echo '</div>';
}

$facilitate_exploits = '<p>'.T_('When enabled, this feature is known to facilitate hacking exploits in any PHP application.')."</p>\n<p>"
	.T_('b2evolution includes additional measures in order not to be affected by this.
	However, for maximum security, we still recommend disabling this PHP feature.')."</p>\n";
$change_ini = '<p>'.T_('If possible, change this setting to <code>%s</code> in your php.ini or ask your hosting provider about it.').'</p>';


echo '<h2>'.T_('About this system').'</h2>';

$block_item_Widget = & new Widget( 'block_item' );


$block_item_Widget->title = 'b2evolution';
$block_item_Widget->disp_template_replaced( 'block_start' );

/**
 * b2evo version
 */
$app_timestamp = mysql2timestamp( $app_date );
init_system_check( 'b2evolution version', $app_version.' released on '.date_i18n( locale_datefmt(), $app_timestamp ) );
$app_age = ($localtimenow - $app_timestamp) / 3600 / 24 / 30;	// approx age in months
if( $app_age > 12 )
{
	disp_system_check( 'error', sprintf( T_('This version is old. You should check for newer releases on <a %s>b2evolution.net</a>.'),
		' href="http://b2evolution.net/downloads/"'	) );
}
elseif( $app_age > 6 )
{
	disp_system_check( 'warning', sprintf( T_('This version is aging. You may want to check for newer releases on <a %s>b2evolution.net</a>.'),
		' href="http://b2evolution.net/downloads/"'	) );
}
else
{
	disp_system_check( 'ok' );
}


/*
 * /install/ folder
 */
$install_removed = ! is_dir( $basepath.$install_subdir );
init_system_check( 'Install folder', $install_removed ?  T_('Deleted') : T_('Not deleted') );
if( ! $install_removed )
{
	disp_system_check( 'warning', T_('For maximum security, it is recommended that you delete your /blogs/install/ folder once you are done with install or upgrade.') );

	init_system_check( 'Database reset', $allow_evodb_reset ?  T_('Allowed!') : T_('Forbidden') );
	if( $allow_evodb_reset )
	{
  	disp_system_check( 'error', '<p>'.T_('Currently, anyone who accesses your install folder could entirely reset your b2evolution database.')."</p>\n"
  	 .'<p>'.T_('ALL YOUR DATA WOULD BE LOST!')."</p>\n"
  	 .'<p>'.T_('As soon as possible, change the setting <code>$allow_evodb_reset = 0;</code> in your /conf/_basic.config.php.').'</p>' );
	}
	else
	{
		disp_system_check( 'ok' );
	}
}
else
{
	disp_system_check( 'ok' );
}

$block_item_Widget->disp_template_raw( 'block_end' );


/**
 * Time
 */
$block_item_Widget->title = T_('Time');
$block_item_Widget->disp_template_replaced( 'block_start' );

init_system_check( 'Server time', date_i18n( locale_datetimefmt( ' - ' ), $servertimenow ) );
disp_system_check( 'note' );

init_system_check( 'GMT / UTC time', gmdate( locale_datetimefmt( ' - ' ), $servertimenow ) );
disp_system_check( 'note' );

init_system_check( 'b2evolution time', date_i18n( locale_datetimefmt( ' - ' ), $localtimenow ) );
disp_system_check( 'note' );

$block_item_Widget->disp_template_raw( 'block_end' );



$block_item_Widget->title = T_('MySQL');
$block_item_Widget->disp_template_replaced( 'block_start' );

/*
 * MySQL Version
 */
$mysql_version = $DB->get_version();
init_system_check( 'MySQL version', $DB->version_long );
if( version_compare( $mysql_version, '4.0' ) < 0 )
{
	disp_system_check( 'warning', T_('This version is not guaranteed to work.') );
}
else
{
	disp_system_check( 'ok' );
}


/*
 * MySQL "SET NAMES"
 */
$save_show_errors = $DB->show_errors;
$save_halt_on_error = $DB->halt_on_error;
// Blatantly ignore any error generated by SET NAMES...
$DB->show_errors = false;
$DB->halt_on_error = false;
$last_error = $DB->last_error;
$error = $DB->error;
if( $DB->query( 'SET NAMES utf8' ) === false )
{
	$ok = false;
}
else
{
	$ok = true;
}
$DB->show_errors = $save_show_errors;
$DB->halt_on_error = $save_halt_on_error;
$DB->last_error = $last_error;
$DB->error = $error;
$DB->halt_on_error = false;
init_system_check( 'MySQL UTF-8 support', $ok ?  T_('Yes') : T_('No') );
if( ! $ok )
{
	disp_system_check( 'warning', T_('UTF-8 is not supported by your MySQL server.') ); // fp> TODO: explain why this is bad. Better yet: try to detect if we really need it, base don other conf variables.
}
else
{
	disp_system_check( 'ok' );
}

$block_item_Widget->disp_template_raw( 'block_end' );



$block_item_Widget->title = T_('PHP');
$block_item_Widget->disp_template_replaced( 'block_start' );


/*
 * Note about process user:
 */
$process_uid = null;
$process_user = null;
$process_gid = null;
$process_group = null;
// User ID:
if( function_exists('posix_geteuid') )
{
	$process_uid = posix_geteuid();

	if( function_exists('posix_getpwuid')
		&& ($process_user = posix_getpwuid($process_uid)) )
	{
		$process_user = $process_user['name'];
	}

	// Group ID:
	if( function_exists('posix_getegid') )
	{
		$process_gid = posix_getegid();

		if( function_exists('posix_getgrgid')
			&& ($process_group = posix_getgrgid($process_group)) )
		{
			$process_group = $process_group['name'];
		}
	}

	$running_as = sprintf( '%s (uid %s), group %s (gid %s)',
	($process_user ? $process_user : '?'), ($process_uid ? $process_uid : '?'),
	($process_group ? $process_group : '?'), ($process_gid ? $process_gid : '?') );
}
else
{
	$running_as = '('.T_('Unkown').')';
}
init_system_check( 'PHP process running as', $running_as );
disp_system_check( 'note' );


/*
 * PHP version
 */
init_system_check( 'PHP version', PHP_VERSION );
if( version_compare( PHP_VERSION, '4.1', '<' ) )
{
	disp_system_check( 'error', T_('This version is too old. b2evolution will not run correctly. You must ask your host to upgrade PHP before you can run b2evolution.') );
}
elseif( version_compare( PHP_VERSION, '4.3', '<' ) )
{
	disp_system_check( 'warning', T_('This version is old. b2evolution may run but some features may fail. You should ask your host to upgrade PHP before running b2evolution.') );
}
else
{
	disp_system_check( 'ok' );
}


/*
 * register_globals
 */
init_system_check( 'PHP register_globals', ini_get('register_globals') ?  T_('On') : T_('Off') );
if( ini_get('register_globals' ) )
{
	disp_system_check( 'warning', $facilitate_exploits.' '.sprintf( $change_ini, 'register_globals = Off' )  );
}
else
{
	disp_system_check( 'ok' );
}


if( version_compare(PHP_VERSION, '5.2', '>=') )
{
	/*
	 * allow_url_include (since 5.2, supercedes allow_url_fopen for require()/include()
	 */
	init_system_check( 'PHP allow_url_include', ini_get('allow_url_include') ?  T_('On') : T_('Off') );
	if( ini_get('allow_url_include' ) )
	{
		disp_system_check( 'warning', $facilitate_exploits.' '.sprintf( $change_ini, 'allow_url_include = Off' )  );
	}
	else
	{
		disp_system_check( 'ok' );
	}
}


/*
 * allow_url_fopen
 * Note: this allows including of remote files (PHP 4 only) as well as opening remote files with fopen() (all versions of PHP)
 * Both have potential for exploits. (The first is easier to exploit than the second).
 * dh> Should we check for curl etc then also and warn the user until there's no method for us anymore to open remote files?
 * fp> Yes
 */
init_system_check( 'PHP allow_url_fopen', ini_get('allow_url_fopen') ?  T_('On') : T_('Off') );
if( ini_get('allow_url_fopen' ) )
{
	disp_system_check( 'warning', $facilitate_exploits.' '.sprintf( $change_ini, 'allow_url_fopen = Off' )  );
}
else
{
	disp_system_check( 'ok' );
}


/*
 * Magic quotes:
 */
if( !strcasecmp( ini_get('magic_quotes_sybase'), 'on' ) )
{
	$magic_quotes = T_('On').' (magic_quotes_sybase)';
	$message = 'magic_quotes_sybase = Off';
}
elseif( get_magic_quotes_gpc() )
{
	$magic_quotes = T_('On').' (magic_quotes_gpc)';
	$message = 'magic_quotes_gpc = Off';
}
else
{
	$magic_quotes = T_('Off');
	$message = '';
}
init_system_check( 'PHP Magic Quotes', $magic_quotes );
if( !empty( $message ) )
{
	disp_system_check( 'warning', T_('PHP is adding extra quotes to all inputs. This leads to unnecessary extra processing.')
		.' '.sprintf( $change_ini, $message ) );
}
else
{
	disp_system_check( 'ok' );
}


/*
 * Sizes
 */
$upload_max_filesize = ini_get('upload_max_filesize');
if( strpos( $upload_max_filesize, 'M' ) )
{
	$upload_max_filesize = intval($upload_max_filesize) * 1024;
}
init_system_check( 'PHP upload_max_filesize', ini_get('upload_max_filesize') );
disp_system_check( 'ok' );


$post_max_size = ini_get('post_max_size');
if( strpos( $post_max_size, 'M' ) )
{
	$post_max_size = intval($post_max_size) * 1024;
}
init_system_check( 'PHP post_max_size', ini_get('post_max_size') );
if( $post_max_size > $upload_max_filesize )
{
	disp_system_check( 'ok' );
}
else
{
	disp_system_check( 'error', T_('post_max_size should be larger than upload_max_filesize') );
}


$memory_limit = ini_get('memory_limit');
if( empty($memory_limit) )
{
	init_system_check( 'PHP memory_limit', T_('n.a.') );
	disp_system_check( 'note' );
}
else
{
	if( strpos( $memory_limit, 'M' ) )
	{
		$memory_limit = intval($memory_limit) * 1024;
	}
	init_system_check( 'PHP memory_limit', ini_get('memory_limit') );
	if( $memory_limit < 8096 )
	{
		disp_system_check( 'error', T_('The memory_limit is very low. Some features of b2evolution will fail to work;') );
	}
	elseif( $memory_limit < 12288 )
	{
		disp_system_check( 'warining', T_('The memory_limit is low. Some features of b2evolution may fail to work;') );
	}
	else
	{
		disp_system_check( 'ok' );
	}
}

/*
 * XML extension
 */
init_system_check( 'PHP XML extension', extension_loaded('xml') ?  T_('Loaded') : T_('Not loaded') );
if( ! extension_loaded('xml' ) )
{
	disp_system_check( 'warning', T_('The XML extension is not loaded.') ); // fp> This message only repeats the exact same info that is already displayed. Not helpful.
	// fp>TODO: explain what we need it for. Is it a problem or not.
	// furthermore I think xmlrpc does dynamic loading (or has it been removed?), in which case this should be tested too.
	// dh> You mean the deprecated dl() loading? (fp>yes) We might just try this then here also before any warning.
}
else
{
	disp_system_check( 'ok' );
}


$block_item_Widget->disp_template_raw( 'block_end' );


/*
 * GD Library
 * windows: extension=php_gd2.dll
 * unix: ?
 * fp> Note: I'm going to use this for thumbnails for now, but I plan to use it for other things like small stats & status graphics.
 */
$block_item_Widget->title = T_('GD Library (image handling)');
$block_item_Widget->disp_template_replaced( 'block_start' );

$gd_info = function_exists( 'gd_info' ) ? gd_info() : array( 'GD Version' => NULL );
$gd_version = $gd_info['GD Version'];
init_system_check( 'GD Library version', isset($gd_version) ? $gd_version : T_('Not installed') );
if( ! isset($gd_version) )
{
	disp_system_check( 'warning', T_('You will not be able to automatically generate thumbnails for images.') );
}
else
{
	disp_system_check( 'ok' );

	init_system_check( 'GD JPG Support', !empty($gd_info['JPG Support']) ? T_('Read/Write') : T_('No') );
	if( empty($gd_info['JPG Support']) )
	{
		disp_system_check( 'warning', T_('You will not be able to automatically generate thumbnails for JPG images.') );
	}
	else
	{
		disp_system_check( 'ok' );
	}

	init_system_check( 'GD PNG Support', !empty($gd_info['JPG Support']) ? T_('Read/Write') : T_('No') );
	if( empty($gd_info['PNG Support']) )
	{
		disp_system_check( 'warning', T_('You will not be able to automatically generate thumbnails for PNG images.') );
	}
	else
	{
		disp_system_check( 'ok' );
	}

	if( !empty($gd_info['GIF Create Support']) )
	{
		$gif_support = T_('Read/Write');
	}
	elseif( !empty($gd_info['GIF Read Support']) )
	{
		$gif_support = T_('Read');
	}
	else
	{
		$gif_support = T_('No');
	}
	init_system_check( 'GD GIF Support', $gif_support );
	if( $gif_support == T_('No') )
	{
		disp_system_check( 'warning', T_('You will not be able to automatically generate thumbnails for GIF images.') );
	}
	elseif( $gif_support == T_('Read') )
	{
		disp_system_check( 'warning', T_('Thumbnails for GIF images will be generated as PNG or JPG.') );
	}
	else
	{
		disp_system_check( 'ok' );
	}

	// pre_dump( $gd_info );
}
$block_item_Widget->disp_template_raw( 'block_end' );



// TODO: dh> memory_limit!
// TODO: dh> output_buffering (recommend off)
// TODO: dh> session.auto_start (recommend off)
// TODO: dh> How to change ini settings in .htaccess (for mod_php), link to manual
// fp> all good ideas :)
// fp> MySQL version
// TODO: dh> link to phpinfo()? It's included in the /install/ folder, but that is supposed to be deleted
// fp> we can just include it a second time as an 'action' here.
// TODO: dh> submit the report into a central database
// fp>yope, with a Globally unique identifier in order to avoid duplicates.

// pre_dump( ini_get_all() );


// End payload block:
$AdminUI->disp_payload_end();

// Display body bottom, debug info and close </html>:
$AdminUI->disp_global_footer();

/*
 * $Log$
 * Revision 1.6  2008/01/06 17:52:50  fplanque
 * minor/doc
 *
 * Revision 1.5  2008/01/05 22:02:55  blueyed
 * Add info about process user and her group
 *
 * Revision 1.4  2007/10/06 21:31:51  fplanque
 * minor
 *
 * Revision 1.3  2007/10/01 19:02:23  fplanque
 * MySQL version check
 *
 * Revision 1.2  2007/09/04 15:29:16  fplanque
 * interface cleanup
 *
 * Revision 1.1  2007/06/25 11:01:42  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.17  2007/05/20 01:02:32  fplanque
 * magic quotes fix
 *
 * Revision 1.16  2007/04/26 00:11:15  fplanque
 * (c) 2007
 *
 * Revision 1.15  2007/03/04 20:14:16  fplanque
 * GMT date now in system checks
 *
 * Revision 1.14  2007/02/22 19:08:31  fplanque
 * file/memory size checks (not fully tested)
 *
 * Revision 1.13  2006/12/21 21:50:32  fplanque
 * removed rant
 *
 * Revision 1.11  2006/12/13 03:08:28  fplanque
 * thumbnail implementation design demo
 *
 * Revision 1.10  2006/12/13 00:57:18  fplanque
 * GD... just for fun ;)
 *
 * Revision 1.9  2006/12/07 23:21:00  fplanque
 * dashboard blog switching
 *
 * Revision 1.8  2006/12/07 23:16:08  blueyed
 * doc: we want no remote file opening anymore?!
 *
 * Revision 1.7  2006/12/06 23:38:45  fplanque
 * doc
 *
 * Revision 1.6  2006/12/06 22:51:41  blueyed
 * doc
 *
 * Revision 1.5  2006/12/05 15:15:56  fplanque
 * more tests
 *
 * Revision 1.4  2006/12/05 12:26:39  blueyed
 * Test for "SET NAMES utf8"
 *
 * Revision 1.3  2006/12/05 12:11:14  blueyed
 * Some more checks and todos
 *
 * Revision 1.2  2006/12/05 11:30:26  fplanque
 * presentation
 *
 * Revision 1.1  2006/12/05 10:20:18  fplanque
 * A few basic systems checks
 *
 * Revision 1.15  2006/12/05 04:27:49  fplanque
 * moved scheduler to Tools (temporary until UI redesign)
 *
 * Revision 1.14  2006/11/26 01:42:08  fplanque
 * doc
 */
?>
