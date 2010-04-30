<?php
/**
 * This file implements the EvoMockDbUnitTestCase class, which
 * setups the global $DB as a mocked DB object.
 */


/**
 * The base class for all unit tests that use a mocked DB object.
 *
 * It sets the global $DB to a mocked one.
 */
class EvoMockDbUnitTestCase extends EvoUnitTestCase
{
	/**
	 * mocked DB object
	 * @var DB
	 */
	var $MockDB;

	/**
	 * List of methods that should get mocked
	 * @var array
	 */
	var $mocked_DB_methods = array();


	/**
	 * Check $testDB_conf and create mocked DB class.
	 */
	function __construct( $label )
	{
		global $testDB_conf;
		if( !isset( $testDB_conf['name'] ) )
		{
			die( 'Please set the DB name to use for tests in /tests/config.php or /tests/config.OVERRIDE.php. See $testDB_conf there..' );
		}
		Mock::generatePartial('DB', 'EvoMockDbUnitTestCase_DB_'.get_class($this), $this->mocked_DB_methods);
		parent::__construct($label);
	}


	/**
	 * Setup global $DB as reference to a mocked one.
	 */
	function setUp()
	{
		global $testDB_conf;

		parent::setup();

		$classname = 'EvoMockDbUnitTestCase_DB_'.get_class($this);
		$this->MockDB = new $classname($this);
		$this->MockDB->DB( $testDB_conf ); // this will do 2-3 calls to query() already

		$this->old_DB_EvoMockDbUnitTestCase = & $GLOBALS['DB'];
		$GLOBALS['DB'] = & $this->MockDB;
	}


	/**
	 * Setup global $DB as original DB object again.
	 */
	function tearDown()
	{
		$GLOBALS['DB'] = $this->old_DB_EvoMockDbUnitTestCase;

		parent::tearDown();
	}
}

?>
