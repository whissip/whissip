<?php
/**
 * This is the main install menu
 *
 * IF YOU ARE READING THIS IN YOUR WEB BROWSER, IT MEANS THAT PHP IS NOT PROPERLY INSTALLED
 * ON YOUR WEB SERVER. IF YOU DON'T KNOW WHAT THIS MEANS, CONTACT YOUR SERVER ADMINISTRATOR
 * OR YOUR HOSTING COMPANY.
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2007 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @package install
 */

/**
 * include config and default functions:
 */
require_once dirname(__FILE__).'/../conf/_config.php';

// Make the includes believe they are being called in the right place...
define( 'EVO_MAIN_INIT', true );

/**
 * Define that we're in the install process.
 */
define( 'EVO_IS_INSTALLING', true );

$script_start_time = time();

if( ! $config_is_done )
{	// Base config is not done yet, try to guess some values needed for correct display:
	$rsc_url = '../rsc/';
}

require_once $inc_path.'_core/_class4.funcs.php';

load_class('_core/model/_log.class.php');
$Debuglog = & new Log( 'note' );
$Messages = & new Log('error');
load_funcs('_core/_misc.funcs.php');
require_once $conf_path.'_upgrade.php';
require_once $inc_path.'_vars.inc.php';
load_class('/_core/model/db/_db.class.php');
load_funcs('collections/model/_blog.funcs.php');
load_funcs('collections/model/_category.funcs.php');
load_class('items/model/_item.class.php');
load_funcs('items/model/_item.funcs.php');
load_funcs('users/model/_user.funcs.php');
load_funcs( '_core/ui/forms/_form.funcs.php' );
load_class('_core/model/_timer.class.php');
load_class('plugins/model/_plugins.class.php');
require_once dirname(__FILE__).'/_functions_install.php';

$Timer = & new Timer('main');

load_class('_core/_param.funcs.php');
param( 'action', 'string', 'default' );
param( 'locale', 'string' );

if( preg_match('/[a-z]{2}-[A-Z]{2}(-.{1,14})?/', $locale) )
{
	$default_locale = $locale;
}
else
{ // detect language
	$default_locale = locale_from_httpaccept();
	// echo 'detected locale: ' . $default_locale. '<br />';
}
// Activate default locale:
locale_activate( $default_locale );

init_charsets($current_charset);

$timestamp = time() - 120; // We start dates 2 minutes ago because their dates increase 1 second at a time and we want everything to be visible when the user watches the blogs right after install :P


switch( $action ) {
	case 'evoupgrade':
		$title = T_('Upgrade from a previous version');
		break;

	case 'newdb':
		$title = T_('New Install');
		break;

	case 'cafelogupgrade':
		$title = T_('Upgrade from Cafelog/b2');
		break;

	case 'deletedb':
		$title = T_('Delete b2evolution tables');
		break;

	case 'start':
		$title = T_('Base configuration');
		break;

	default:
		$title = '';

}

header('Content-Type: text/html; charset='.$io_charset);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php locale_lang() ?>" lang="<?php locale_lang() ?>"><!-- InstanceBegin template="/Templates/evo_distrib_2.dwt" codeOutsideHTMLIsLocked="false" -->
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<!-- InstanceBeginEditable name="doctitle" -->
	<title><?php echo T_('b2evo installer').( $title ? ': '.$title : '' ) ?></title>
	<!-- InstanceEndEditable -->
	<link href="../rsc/css/evo_distrib_2.css" rel="stylesheet" type="text/css" />
	<!-- InstanceBeginEditable name="head" --><!-- InstanceEndEditable -->
	<!-- InstanceParam name="lang" type="text" value="&lt;?php locale_lang() ?&gt;" --> 
</head>

<body>
	<!-- InstanceBeginEditable name="BodyHead" --><!-- InstanceEndEditable -->

	<div class="wrapper1">
	<div class="wrapper2">
		<span class="version_top"><!-- InstanceBeginEditable name="Version" --><?php echo T_('Installer for version ').' '. $app_version ?><!-- InstanceEndEditable --></span>	
	
		<a href="http://b2evolution.net/" target="_blank"><img src="../rsc/img/distrib/b2evolution-logo.gif" alt="b2evolution" width="237" height="92" /></a>
		
		<div class="menu_top"><!-- InstanceBeginEditable name="MenuTop" --> 
			<span class="floatright"><?php echo T_('After install') ?>: <a href="../index.php"><?php echo T_('Blogs') ?></a> &middot;
			<a href="../admin.php"><?php echo T_('Admin') ?></a>
			</span>
		<?php echo T_('Current installation') ?>:
		<a href="index.php?locale=<?php echo $default_locale ?>"><?php echo T_('Install menu') ?></a> &middot;
		<a href="phpinfo.php"><?php echo T_('PHP info') ?></a> 
		<!-- InstanceEndEditable --></div>
		
		<!-- InstanceBeginEditable name="Main" -->
		<div class="block1">
		<div class="block2">
		<div class="block3">
<?php

if( $config_is_done || (($action != 'start') && ($action != 'default') && ($action != 'conf')) )
{ // Connect to DB:
	$tmp_evoconf_db = $db_config;
	// We want a friendly message if we can't connect:
	$tmp_evoconf_db['halt_on_error'] = false;
	$tmp_evoconf_db['show_errors'] = false;
	$DB = new DB( $tmp_evoconf_db );
	unset($tmp_evoconf_db);

	if( $DB->error )
	{ // restart conf
		// TODO: Use title/headline, or just:
		// Log::display( T_('MySQL error!'), '', T_('Check your database config settings below and update them if necessary...') );
		echo '<p class="error">'.T_('Check your database config settings below and update them if necessary...').'</p>';
		$action = 'start';
	}
	else
	{
		$DB->halt_on_error = true;  // From now on, halt on errors.
		$DB->show_errors = true;    // From now on, show errors (they're helpful in case of errors!).

		// Check MySQL version
		$mysql_version = $DB->get_var( 'SELECT VERSION()' );
		list( $mysl_version_main, $mysl_version_minor ) = explode( '.', $mysql_version );
		if( ($mysl_version_main * 100 + $mysl_version_minor) < 323 )
		{
			die( '<div class="error"><p class="error"><strong>'.sprintf(T_('The minimum requirement for this version of b2evolution is %s version %s but you are trying to use version %s!'), 'MySQL', '3.23', $mysql_version ).'</strong></p></div>');
		}
	}
}

// Check PHP version
list( $version_main, $version_minor ) = explode( '.', phpversion() );
if( ($version_main * 100 + $version_minor) < 401 )
{
	die( '<div class="error"><p class="error"><strong>'.sprintf(T_('The minimum requirement for this version of b2evolution is %s version %s but you are trying to use version %s!'), 'PHP', '4.1.0', phpversion() ).'</strong></p></div>');
}

// Check other dependencies:
// TODO: Non-install/upgrade-actions should be allowed (e.g. "deletedb")
if( $req_errors = install_validate_requirements() )
{
	echo '<div class="error">';
	echo '<p class="error"><strong>'.'b2evolution cannot be installed, because of the following errors:'.'</strong></p>';
	echo '<ul class="error"><li>'.implode( '</li><li>', $req_errors ).'</li></ul>';
	echo '</div>';
	die;
}


switch( $action )
{
	case 'conf':
		/*
		 * -----------------------------------------------------------------------------------
		 * Write conf file:
		 * -----------------------------------------------------------------------------------
		 */
		param( 'conf_db_user', 'string', true );
		param( 'conf_db_password', 'string', true );
		param( 'conf_db_name', 'string', true );
		param( 'conf_db_host', 'string', true );
		param( 'conf_db_tableprefix', 'string', true );
		param( 'conf_baseurl', 'string', true );
		$conf_baseurl = preg_replace( '#(/)?$#', '', $conf_baseurl ).'/'; // force trailing slash
		param( 'conf_admin_email', 'string', true );

		// Connect to DB:
		$DB = new DB( array(
			'user' => $conf_db_user,
			'password' => $conf_db_password,
			'name' => $conf_db_name,
			'host' => $conf_db_host,
			'aliases' => $db_config['aliases'],
			'use_transactions' => $db_config['use_transactions'],
			'table_options' => $db_config['table_options'],
			'connection_charset' => $db_config['connection_charset'],
			'halt_on_error' => false ) );
		if( $DB->error )
		{ // restart conf
			echo '<p class="error">'.T_('It seems that the database config settings you entered don\'t work. Please check them carefully and try again...').'</p>';
			$action = 'start';
		}
		else
		{
			$conf_filepath = $conf_path.'_basic_config.php';
			// Read original:
			$conf = implode( '', file( $conf_filepath ) );

			if( empty( $conf ) )
			{ // This should actually never happen, just in case...
				printf( '<p class="error">Could not load original conf file [%s]. Is it missing?</p>', $conf_filepath );
				break;
			}

			// File loaded...
			// Update conf:
			$conf = preg_replace(
				array(
					'#\$db_config\s*=\s*array\(
						\s*[\'"]user[\'"]\s*=>\s*[\'"].*?[\'"],     ([^\n\r]*\r?\n)
						\s*[\'"]password[\'"]\s*=>\s*[\'"].*?[\'"], ([^\n\r]*\r?\n)
						\s*[\'"]name[\'"]\s*=>\s*[\'"].*?[\'"],     ([^\n\r]*\r?\n)
						\s*[\'"]host[\'"]\s*=>\s*[\'"].*?[\'"],     ([^\n\r]*\r?\n)
						#ixs',
					"#tableprefix\s*=\s*'.*?';#",
					"#baseurl\s*=\s*'.*?';#",
					"#admin_email\s*=\s*'.*?';#",
					"#config_is_done\s*=.*?;#",
				),
				array(
					"\$db_config = array(\n"
						."\t'user'     => '$conf_db_user',\$1"
						."\t'password' => '$conf_db_password',\$2"
						."\t'name'     => '$conf_db_name',\$3"
						."\t'host'     => '$conf_db_host',\$4",
					"tableprefix = '$conf_db_tableprefix';",
					"baseurl = '$conf_baseurl';",
					"admin_email = '$conf_admin_email';",
					'config_is_done = 1;',
				), $conf );

			$f = @fopen( $conf_filepath , 'w' );
			if( $f == false )
			{
				?>
				<h1><?php echo T_('Config file update') ?></h1>
				<p><strong><?php printf( T_('We cannot automatically update your config file [%s]!'), $conf_filepath ); ?></strong></p>
				<p><?php echo T_('There are two ways to deal with this:') ?></p>
				<ul>
					<li><strong><?php echo T_('You can allow the installer to update the config file by changing its permissions:') ?></strong>
						<ol>
							<li><?php printf( T_('<code>chmod 666 %s</code>. If needed, see the <a %s>online manual about permissions</a>.'), $conf_filepath, 'href="http://manual.b2evolution.net/Directory_and_file_permissions" target="_blank"' ); ?></li>
							<li><?php echo T_('Come back to this page and refresh/reload.') ?></li>
						</ol>
						<br />
					</li>
					<li><strong><?php echo T_('Alternatively, you can update the config file manually:') ?></strong>
						<ol>
							<li><?php echo T_('Open the _basic_config.php file locally with a text editor.') ?></li>
							<li><?php echo T_('Delete all contents!') ?></li>
							<li><?php echo T_('Copy the contents from the box below.') ?></li>
							<li><?php echo T_('Paste them into your local text editor. <strong>ATTENTION: make sure there is ABSOLUTELY NO WHITESPACE after the final <code>?&gt;</code> in the file.</strong> Any space, tab, newline or blank line at the end of the conf file may prevent cookies from being set when you try to log in later.') ?></li>
							<li><?php echo T_('Save the new _basic_config.php file locally.') ?></li>
							<li><?php echo T_('Upload the file to your server, into the /_conf folder.') ?></li>
							<li><?php printf( T_('<a %s>Call the installer from scratch</a>.'), 'href="index.php?locale='.$default_locale.'"') ?></li>
						</ol>
					</li>
				</ul>
				<p><?php echo T_('This is how your _basic_config.php should look like:') ?></p>
				<blockquote>
				<pre><?php
					echo htmlspecialchars( $conf );
				?></pre>
				</blockquote>
				<?php
				break;
			}
			else
			{ // Write new contents:
				fwrite( $f, $conf );
				fclose($f);

				printf( '<p>'.T_('Your configuration file [%s] has been successfully updated.').'</p>', $conf_filepath );

				$tableprefix = $conf_db_tableprefix;
				$baseurl = $conf_baseurl;
				$admin_email = $conf_admin_email;
				$config_is_done = 1;
				$action = 'menu';
			}
		}
		// ATTENTION: we continue here...

	case 'start':
	case 'default':
		/*
		 * -----------------------------------------------------------------------------------
		 * Start of install procedure:
		 * -----------------------------------------------------------------------------------
		 */
		if( (($action == 'start') && ($allow_evodb_reset == 1)) || (!$config_is_done) )
		{
			// Set default params if not provided otherwise:
			param( 'conf_db_user', 'string', $db_config['user'] );
			param( 'conf_db_password', 'string', $db_config['password'] );
			param( 'conf_db_name', 'string', $db_config['name'] );
			param( 'conf_db_host', 'string', $db_config['host'] );
			param( 'conf_db_tableprefix', 'string', $tableprefix );
			// Guess baseurl:
			// TODO: dh> IMHO HTTP_HOST would be a better default, because it's what the user accesses for install.
			//       fp, please change it, if it's ok. SERVER_NAME might get used if HTTP_HOST is not given, but that shouldn't be the case normally.
			$baseurl = 'http://'.( isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : 'yourserver.com' );
			if( isset( $_SERVER['SERVER_PORT'] ) && ( $_SERVER['SERVER_PORT'] != '80' ) )
				$baseurl .= ':'.$_SERVER['SERVER_PORT'];
			$baseurl .= preg_replace( '#/install(/(index.php)?)?$#', '', $ReqPath ).'/';
			param( 'conf_baseurl', 'string', $baseurl );
			param( 'conf_admin_email', 'string', $admin_email );

			?>
			<h1><?php echo T_('Base configuration') ?></h1>

			<p><?php echo T_('Your base config file has not been edited yet. You can do this by filling in the form below.') ?></p>

			<p><?php echo T_('This is the minimum info we need to set up b2evolution on this server:') ?></p>

			<form class="fform" name="form" action="index.php" method="post">
				<input type="hidden" name="action" value="conf" />
				<input type="hidden" name="locale" value="<?php echo $default_locale; ?>" />

				<fieldset>
					<legend><?php echo T_('Database you want to install into') ?></legend>
					<?php
						form_text( 'conf_db_user', $conf_db_user, 16, T_('MySQL Username'), sprintf( T_('Your username to access the database' ) ), 100 );
						form_text( 'conf_db_password', $conf_db_password, 16, T_('MySQL Password'), sprintf( T_('Your password to access the database' ) ), 100, '', 'password' );
						form_text( 'conf_db_name', $conf_db_name, 16, T_('MySQL Database'), sprintf( T_('Name of the database you want to use' ) ), 100);
						form_text( 'conf_db_host', $conf_db_host, 16, T_('MySQL Host'), sprintf( T_('You probably won\'t have to change this' ) ), 120 );
						form_text( 'conf_db_tableprefix', $conf_db_tableprefix, 16, T_('MySQL tables prefix'), sprintf( T_('All DB tables will be prefixed with this. You need to change this only if you want to have multiple b2evo installations in the same DB.' ) ), 30 );
					?>
				</fieldset>

				<fieldset>
					<legend><?php echo T_('Additional settings') ?></legend>
					<?php
						form_text( 'conf_baseurl', $conf_baseurl, 50, T_('Base URL'), sprintf( T_('This is where b2evo and your blogs reside by default. CHECK THIS CAREFULLY or not much will work. If you want to test b2evolution on your local machine, in order for login cookies to work, you MUST use http://<strong>localhost</strong>/path... Do NOT use your machine\'s name!' ) ), 120 );

						form_text( 'conf_admin_email', $conf_admin_email, 50, T_('Your email'), sprintf( T_('Will be used in severe error messages so that users can contact you. You will also receive notifications for new user registrations.' ) ), 80 );
					?>
				</fieldset>

				<fieldset>
					<fieldset>
						<div class="input">
							<input type="submit" name="submit" value="<?php echo T_('Update config file') ?>" class="search" />
							<input type="reset" value="<?php echo T_('Reset') ?>" class="search" />
						</div>
					</fieldset>
				</fieldset>

			</form>

			<?php
			break;
		}
		// if config was already done, move on to main menu:

	case 'menu':
		/*
		 * -----------------------------------------------------------------------------------
		 * Menu
		 * -----------------------------------------------------------------------------------
		 */
		?>
		<h1><?php echo T_('How do you want to install b2evolution?') ?></h1>

		<form action="index.php" method="get">
			<input type="hidden" name="locale" value="<?php echo $default_locale ?>" />
			<input type="hidden" name="confirmed" value="0" />

			<p><?php echo T_('The installation can be done in different ways. Choose one:')?></p>

			<p><input type="radio" name="action" id="newdb" value="newdb" checked="checked" />
				<label for="newdb"><?php echo T_('<strong>New Install</strong>: Install b2evolution database tables.')?></label></p>
			<p style="margin-left: 2em;">
				<input type="checkbox" name="create_sample_contents" id="create_sample_contents" value="1" checked="checked" />
				<label for="create_sample_contents"><?php echo T_('Also install sample blogs &amp; sample contents. The sample posts explain several features of b2evolution. This is highly recommended for new users.')?></label>
			</p>

			<p><input type="radio" name="action" id="evoupgrade" value="evoupgrade" />
				<label for="evoupgrade"><?php echo T_('<strong>Upgrade from a previous version of b2evolution</strong>: Upgrade your b2evolution database tables in order to make them compatible with the current version. <strong>WARNING:</strong> If you have modified your database, this operation may fail. Make sure you have a backup.') ?></label></p>

			<?php
				if( $allow_evodb_reset == 1 )
				{
					?>
					<p><input type="radio" name="action" id="deletedb" value="deletedb" />
					<label for="deletedb"><strong><?php echo T_('Delete b2evolution tables')?></strong>:
					<?php echo T_('If you have installed b2evolution tables before and wish to start anew, you must delete the b2evolution tables before you can start a new installation. <strong>WARNING: All your b2evolution tables and data will be lost!!!</strong> Any non-b2evolution tables will remain untouched though.')?></label></p>

					<p><input type="radio" name="action" id="start" value="start" />
					<label for="start"><?php echo T_('<strong>Change your base configuration</strong> (see recap below): You only want to do this in rare occasions where you may have moved your b2evolution files or database to a different location...')?></label></p>
					<?php
				}
			?>

			<p>
			<input type="submit" value="&nbsp; <?php echo T_('GO!')?> &nbsp;"
				onclick="var dc = document.getElementById( 'deletedb' ); if( dc && dc.checked ) { if ( confirm( '<?php
					printf( /* TRANS: %s gets replaced by app name, usually "b2evolution" */ TS_( 'Are you sure you want to delete your existing %s tables?\nDo you have a backup?' ), $app_name );
					?>' ) ) { this.form.confirmed.value = 1; return true; } else return false; }" />
			</p>
			</form>
		<?php
		if( $allow_evodb_reset != 1 )
		{
			?>
			<br />
			<h2><?php echo T_('Need to start anew?') ?></h2>
			<p><?php echo T_('If you have installed b2evolution tables before and wish to start anew, you must delete the b2evolution tables before you can start a new installation. b2evolution can delete its own tables for you, but for obvious security reasons, this feature is disabled by default.');
			echo '</p>';
			echo( '<p>To enable it, please go to the /conf/_basic_config.php file and change:</p>
<pre>$allow_evodb_reset = 0;</pre>
to
<pre>$allow_evodb_reset = 1;</pre>
<p>Then reload this page and a reset option will appear.</p>
<p>This will also allow you to change your base configuration.</p>');
		}
		?>

	</div>
	</div>
	</div>

	<div class="block1">
	<div class="block2">
	<div class="block3">

		<h2><?php echo T_('Base config recap...')?></h2>

		<p><?php printf( T_('If you don\'t see correct settings here, STOP before going any further, and <a %s>update your base configuration</a>.'), 'href="index.php?action=start&amp;locale='.$default_locale.'"' ) ?></p>

		<?php
		if( !isset($conf_db_user) ) $conf_db_user = $db_config['user'];
		if( !isset($conf_db_password) ) $conf_db_password = $db_config['password'];
		if( !isset($conf_db_name) ) $conf_db_name = $db_config['name'];
		if( !isset($conf_db_host) ) $conf_db_host = $db_config['host'];

		echo '<pre>',
		T_('MySQL Username').': '.$conf_db_user."\n".
		T_('MySQL Password').': '.(($conf_db_password != 'demopass' ? T_('(Set, but not shown for security reasons)') : 'demopass') )."\n".
		T_('MySQL Database').': '.$conf_db_name."\n".
		T_('MySQL Host').': '.$conf_db_host."\n".
		T_('MySQL tables prefix').': '.$tableprefix."\n\n".
		T_('Base URL').': '.$baseurl."\n\n".
		T_('Admin email').': '.$admin_email.
		'</pre>';
		break;


	case 'newdb':
		/*
		 * -----------------------------------------------------------------------------------
		 * NEW DB: Create a plain new db structure + sample contents
		 * -----------------------------------------------------------------------------------
		 */
		require_once dirname(__FILE__).'/_functions_create.php';

		param( 'create_sample_contents', 'integer', 0 );

		echo '<h2>'.T_('Creating b2evolution tables...').'</h2>';
		flush();
		create_tables();

		echo '<h2>'.T_('Creating minimum default data...').'</h2>';
		flush();
		create_default_data();

		if( $create_sample_contents )
		{
			echo '<h2>'.T_('Installing sample contents...').'</h2>';
			flush();
			create_demo_contents();
		}

		echo '<h2>'.T_('Installation successful!').'</h2>';

		echo '<p><strong>';
		printf( T_('Now you can <a %s>log in</a> with the login "admin" and password "%s".'), 'href="'.$admin_url.'"', $random_password );
		echo '</strong></p>';

		echo '<p>'.T_('Note that password carefully! It is a <em>random</em> password that is given to you when you install b2evolution. If you lose it, you will have to delete the database tables and re-install anew.').'</p>';

		break;


	case 'evoupgrade':
		/*
		 * -----------------------------------------------------------------------------------
		 * EVO UPGRADE: Upgrade data from existing b2evolution database
		 * -----------------------------------------------------------------------------------
		 */
		require_once( dirname(__FILE__). '/_functions_evoupgrade.php' );

		echo '<h2>'.T_('Upgrading data in existing b2evolution database...').'</h2>';
		flush();
		if( upgrade_b2evo_tables() )
		{
			?>
			<p><?php echo T_('Upgrade completed successfully!')?></p>
			<p><?php printf( T_('Now you can <a %s>log in</a> with your usual %s username and password.'), 'href="'.$admin_url.'"', 'b2evolution')?></p>
			<?php
		}
		break;


	case 'deletedb':
		/*
		 * -----------------------------------------------------------------------------------
		 * DELETE DB: Delete the db structure!!! (Everything will be lost)
		 * -----------------------------------------------------------------------------------
		 */
		require_once( dirname(__FILE__). '/_functions_delete.php' );

		echo '<h2>'.T_('Deleting b2evolution tables from the datatase...').'</h2>';
		flush();

		if( $allow_evodb_reset != 1 )
		{
			echo '<p>'.T_('For security reasons, the reset feature is disabled by default.' ).'</p>';
			echo( '<p>To enable it, please go to the /conf/_basic_config.php file and change:</p>
<pre>$allow_evodb_reset = 0;</pre>
to
<pre>$allow_evodb_reset = 1;</pre>
<p>Then reload this page and a reset option will appear.</p>');
			break;
		}
		if( ! param('confirmed', 'integer', 1) )
		{
			?>
			<p>
			<?php
			echo nl2br( htmlspecialchars( sprintf( /* TRANS: %s gets replaced by app name, usually "b2evolution" */ T_( "Are you sure you want to delete your existing %s tables?\nDo you have a backup?" ), $app_name ) ) );
			?>
			</p>
			<p>
			<form class="inline" name="form" action="index.php" method="post">
				<input type="hidden" name="action" value="deletedb" />
				<input type="hidden" name="confirmed" value="1" />
				<input type="hidden" name="locale" value="<?php echo $default_locale; ?>" />
				<input type="submit" value="&nbsp; <?php echo T_('I am sure!')?> &nbsp;" />
			</form>

			<form class="inline" name="form" action="index.php" method="get">
				<input type="hidden" name="locale" value="<?php echo $default_locale; ?>" />
				<input type="submit" value="&nbsp; <?php echo T_('CANCEL')?> &nbsp;" />
			</form>
			</p>
			<?php
			break;
		}

		// Uninstall Plugins
// TODO: fp>> I don't trust the plugins to uninstall themselves correctly. There will be tons of lousy poorly written plugins. All I trust them to do is to crash the uninstall procedure. We want a hardcore brute force uninsall! and most users "may NOT want" to even think about "ma-nu-al-ly" removing something from their DB.
/*
		$DB->show_errors = $DB->halt_on_error = false;
		$Plugins = new Plugins();
		$DB->show_errors = $DB->halt_on_error = true;
		$at_least_one_failed = false;
		foreach( $Plugins->get_list_by_event( 'Uninstall' ) as $l_Plugin )
		{
			$success = $Plugins->call_method( $l_Plugin->ID, 'Uninstall', $params = array( 'unattended' => true ) );
			if( $success === false )
			{
				echo "Failed un-installing plugin $l_Plugin->classname (ID $l_Plugin->ID)...<br />\n";
				$at_least_one_failed = false;
			}
			else
			{
				echo "Uninstalled plugin $l_Plugin->classname (ID $l_Plugin->ID)...<br />\n";
			}
		}
		if( $at_least_one_failed )
		{
			echo "You may want to manually remove left files or DB tables from the failed plugin(s).<br />\n";
		}
		$DB->show_errors = $DB->halt_on_error = true;
*/
		db_delete();
		?>
		<p><?php echo T_('Reset done!')?></p>
		<p><a href="index.php?locale=<?php echo $default_locale ?>"><?php echo T_('Back to menu')?></a>.</p>
		<?php
		break;
}

?>

</div>
</div>
</div>

<?php
// Locales selector:
if( ($action == 'start') || ($action == 'default') || ($action == 'conf') || ($action == 'menu') )
{
	?>
	<div class="block1">
	<div class="block2">
	<div class="block3">
	<h2><?php echo T_('Language / Locale')?> - Temporarily misplaced</h2>
	<p><?php echo T_('Choose a default language/locale for your b2evo installation.')?></p>

	<?php
	// present available locales on first screen
	foreach( $locales as $lkey => $lvalue )
	{
		if( $default_locale == $lkey ) echo '<strong>';
		echo ' <a href="index.php?action='.$action.'&amp;locale='.$lkey.'">';
		locale_flag( $lkey, 'w16px', 'flag', '', true, $rsc_url.'flags' );
		echo T_( $lvalue['name'] );
		echo '</a>';
		if( $default_locale == $lkey ) echo '</strong>';
		echo ' &middot; ';

	}
	?>
	</div>
	</div>
	</div>
	<?php
}
?>
<!-- InstanceEndEditable -->
	</div>
		
	<div class="body_fade_out">
		
	<div class="menu_bottom"><!-- InstanceBeginEditable name="MenuBottom" -->
			<?php echo T_('Online resources') ?>: <a href="http://b2evolution.net/" target="_blank"><?php echo T_('Official website') ?></a> &bull; <a href="http://b2evolution.net/about/recommended-hosting-lamp-best-choices.php" target="_blank"><?php echo T_('Find a host') ?></a> &bull; <a href="http://manual.b2evolution.net/" target="_blank"><?php echo T_('Manual') ?></a> &bull; <a href="http://forums.b2evolution.net/" target="_blank"><?php echo T_('Forums') ?></a>
		<!-- InstanceEndEditable --></div>
	
	<div class="copyright"><!-- InstanceBeginEditable name="CopyrightTail" -->Copyright � 2003-2007 by Fran�ois Planque & others � <a href="http://b2evolution.net/about/license.html" target="_blank">GNU GPL license</a> &middot; <a href="http://b2evolution.net/contact/" target="_blank">Contact</a>
		<!-- InstanceEndEditable --></div>
		
	</div>
	</div>

	<!-- InstanceBeginEditable name="BodyFoot" -->
	<?php
		debug_info(); // output debug info if requested
	// the following comment gets checked in the automatic install script of demo.b2evolution.net:
?>
<!-- b2evo-install-end -->
	<!-- InstanceEndEditable -->
</body>
<!-- InstanceEnd --></html>


<?php
/*
 * $Log$
 * Revision 1.137  2007/09/19 02:54:16  fplanque
 * bullet proof upgrade
 *
 * Revision 1.136  2007/07/14 02:44:22  fplanque
 * New default page design.
 *
 * Revision 1.135  2007/07/14 00:24:53  fplanque
 * New installer design.
 *
 * Revision 1.134  2007/07/01 18:47:11  fplanque
 * fixes
 *
 * Revision 1.133  2007/06/25 11:02:31  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.132  2007/06/24 18:28:55  fplanque
 * refactored skin install
 *
 * Revision 1.129  2007/06/12 21:00:02  blueyed
 * Added non-JS handling of deletedb confirmation
 *
 * Revision 1.128  2007/04/26 00:11:10  fplanque
 * (c) 2007
 *
 * Revision 1.127  2007/01/20 01:44:22  blueyed
 * typo
 *
 * Revision 1.126  2007/01/15 19:10:29  fplanque
 * install refactoring
 *
 * Revision 1.125  2007/01/15 18:48:44  fplanque
 * allow blank install.
 *
 * Revision 1.124  2007/01/15 03:53:24  fplanque
 * refactoring / simplified installer
 *
 * Revision 1.123  2007/01/14 03:47:53  fplanque
 * killed upgrade from b2/cafelog
 * (if people haven't upgraded yet, there's little chance they ever will,
 * no need to maintain this. We also provide an upgrade path with 1.x)
 *
 * Revision 1.122  2007/01/12 02:40:26  fplanque
 * widget default params proof of concept
 * (param customization to be done)
 *
 * Revision 1.121  2007/01/08 02:11:56  fplanque
 * Blogs now make use of installed skins
 * next step: make use of widgets inside of skins
 *
 * Revision 1.120  2006/11/30 06:13:23  blueyed
 * Moved Plugins::install() and sort() galore to Plugins_admin
 *
 * Revision 1.119  2006/11/30 05:43:40  blueyed
 * Moved Plugins::discover() to Plugins_admin::discover(); Renamed Plugins_no_DB to Plugins_admin_no_DB (and deriving from Plugins_admin)
 *
 * Revision 1.118  2006/11/14 00:47:32  fplanque
 * doc
 *
 * Revision 1.117  2006/10/31 04:44:00  blueyed
 * Fixed cafelogupgrade
 *
 * Revision 1.116  2006/10/27 20:11:24  blueyed
 * TODO
 *
 * Revision 1.115  2006/10/14 20:50:29  blueyed
 * Define EVO_IS_INSTALLING for /install/ and use it in Plugins to skip "dangerous" but unnecessary instantiating of other Plugins
 *
 * Revision 1.114  2006/10/01 15:23:28  blueyed
 * Fixed install
 */
?>