<?php
/**
 * General EvoUnitTestCase.
 *
 * Implements common methods for testing.
 */


/**
 * We use a derived reporter, which shows passes
 */
require_once( dirname(__FILE__).'/HtmlReporterShowPasses.class.php' );

load_class( '_core/model/_log.class.php' );
load_class( 'files/model/_filerootcache.class.php' );
load_class( 'files/model/_filecache.class.php' );
load_class( 'files/model/_filetypecache.class.php' );
load_class( '_core/model/_timer.class.php' );
load_class( 'plugins/model/_plugins_admin.class.php' );
load_class( 'plugins/model/_plugins_admin_no_db.class.php' );
load_class( 'settings/model/_generalsettings.class.php' );
load_class( 'users/model/_usersettings.class.php' );
load_class( 'users/model/_user.class.php' );
load_class( 'users/model/_usercache.class.php' );
load_class( 'files/model/_file.class.php' );
load_class( 'files/model/_filetype.class.php' );

/**
 * Class EvoUnitTestCase
 */
class EvoUnitTestCase extends UnitTestCase
{
	/**
	 * Setup required globals
	 */
	function setUp()
	{
		global $FileRootCache, $FiletypeCache, $FileCache, $GroupCache, $DB, $db_config, $Debuglog, $Messages, $UserCache, $Timer, $Plugins, $Settings, $UserSettings;

		parent::setUp(); // just because..

		$Debuglog = new Log('note');
		$Messages = new Log('error');
		$FileRootCache = new FileRootCache();
		$UserCache = new UserCache();
		$FileCache = new FileCache();
		$FileRootCache = new FileRootCache();
		$FiletypeCache = new FiletypeCache();
		$GroupCache = new DataObjectCache( 'Group', true, 'T_groups', 'grp_', 'grp_ID' );
		$Timer = new Timer();
		$Plugins = new Plugins_admin_no_DB();

		$db_params = $db_config;
		$db_params['new_link'] = true; // needed to not interfere with the DB connection to the test DB (setup in DbUnitTestCase).
		$DB = new DB( $db_params );

		$Settings = new GeneralSettings();
		$UserSettings = new UserSettings();
	}


	/**
	 * Extend run() method to recognize cli mode.
	 *
	 * @param SimpleReporter Reporter for HTML mode
	 * @param SimpleReporter Reporter for CLI mode
	 * @access public
	 */
	function run_html_or_cli()
	{
		if( TextReporter::inCli() )
		{
			exit( parent::run( new TextReporter() ) ? 0 : 1 );
		}
		parent::run( new HtmlReporter() );
	}
}

?>
