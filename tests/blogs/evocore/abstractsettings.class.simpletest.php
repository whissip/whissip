<?php
/**
 * Tests for the {@link AbstractSettings} class.
 * @package tests
 */

/**
 * SimpleTest config
 */
require_once( dirname(__FILE__).'/../../config.simpletest.php' );


/**
 * @package tests
 */
class AbstractSettingsTestCase extends EvoMockDbUnitTestCase
{
	var $mocked_DB_methods = array('get_results');

	function __construct()
	{
		parent::__construct( 'AbstractSettings class test' );
	}


	function setUp()
	{
		parent::setup();

		$this->TestSettings =& new AbstractSettings( 'testtable', array( 'test_name' ), 'test_value' );
	}


	function testLoad()
	{
		$this->MockDB->expectOnce( 'get_results', array( new PatternExpectation('/SELECT test_name, test_value\s+FROM testtable/i') ), 'DB select ok.' );
		$this->TestSettings->_load();
		$this->TestSettings->_load();
	}


	/**
	 * Check if we get defaults right.
	 */
	function testDefaults()
	{
		$this->TestSettings->_defaults = array(
			'default_1' => '1',
			'default_abc' => 'abc',
		);

		$this->TestSettings->_load();
		$this->assertEqual( 'abc', $this->TestSettings->get_default( 'default_abc' ) );

		$this->assertEqual( 'abc', $this->TestSettings->get( 'default_abc' ) );

		// After delete it should return the default again:
		$this->TestSettings->set( 'default_abc', 'foo' );
		$this->TestSettings->delete( 'default_abc' );
		$this->assertEqual( 'abc', $this->TestSettings->get( 'default_abc' ) );
	}


	/**
	 * Tests AbstractSettings::set()
	 */
	function testPreferExplicitSet()
	{
		$this->MockDB->expectOnce( 'get_results', array( new PatternExpectation('/SELECT test_name, test_value\s+FROM testtable/i') ), 'DB select ok.' );
		$this->TestSettings->set( 'lala', 1 );

		$this->MockDB->expectNever( 'get_results', false, 'Did not reload settings from DB.' );
		$this->TestSettings->_load();

		$this->assertEqual( $this->TestSettings->get( 'lala' ), 1, 'Prefer setting which was set before explicit load().' );
		$this->assertNull( $this->TestSettings->get( 'lala_notset' ), 'Return NULL for non-existing setting.' );
	}
}


if( !isset( $this ) )
{ // Called directly, run the TestCase alone
	$test = new AbstractSettingsTestCase();
	$test->run_html_or_cli();
	unset( $test );
}
?>
